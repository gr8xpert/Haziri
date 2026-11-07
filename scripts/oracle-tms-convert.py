"""Convert an Oracle "Time Attendance Management System" (TMS) export dump into Haziri import files.

No Oracle install is needed: the classic `exp` dump format is read directly.

    python -I scripts/oracle-tms-convert.py docs/TMSHOSEP.DMP
    python -I scripts/oracle-tms-convert.py docs/TMSHOSEP.DMP --from 2026-07-01 --to 2026-08-31 --out docs/import

Writes into the output folder (default: next to the dump, "<dump name>-import"):
  employees.csv  -> Employees > Import employees (tick "Create missing departments")
  punches.csv    -> Devices > USB import
  report.md      -> shifts, holidays and leave to set up by hand, plus every warning

By default it takes the last two calendar months that have punches, and active employees only.
"""

from __future__ import annotations

import argparse
import collections
import csv
import mmap
import re
import struct
import sys
from datetime import date, datetime
from pathlib import Path

# --- classic exp reader ---------------------------------------------------------------------------

NULL_FIELD = 0xFFFE
END_OF_TABLE = 0xFFFF
TYPE_VARCHAR2, TYPE_NUMBER, TYPE_DATE, TYPE_CHAR = 1, 2, 12, 96


def decode_number(raw: bytes):
    """Oracle NUMBER: exponent byte + base-100 mantissa."""
    if not raw:
        return None
    head = raw[0]
    if head == 0x80:
        return 0
    if head & 0x80:
        exponent, digits, sign = (head & 0x7F) - 65, [d - 1 for d in raw[1:]], 1
    else:
        body = raw[1:-1] if raw[-1] == 102 else raw[1:]
        exponent, digits, sign = ((~head) & 0x7F) - 65, [101 - d for d in body], -1
    value = 0
    fractional = False
    for i, digit in enumerate(digits):
        power = exponent - i
        if power < 0:
            fractional = True
            value += digit / (100 ** -power)
        else:
            value += digit * (100 ** power)
    return sign * (round(value, 10) if fractional else value)


def decode_date(raw: bytes):
    if len(raw) < 7:
        return None
    century, year, month, day, hour, minute, second = raw[:7]
    try:
        return datetime((century - 100) * 100 + (year - 100), month, day, hour - 1, minute - 1, second - 1)
    except ValueError:
        return None


class ExpDump:
    """Reads table rows out of an Oracle classic `exp` dump file."""

    def __init__(self, path: Path):
        self.file = open(path, "rb")
        self.data = mmap.mmap(self.file.fileno(), 0, access=mmap.ACCESS_READ)
        header = self.data[:200]
        if b"EXPORT:V" not in header:
            raise SystemExit(f"{path} is not an Oracle classic export (exp) dump.")
        self.tables = sorted(set(m.group(1).decode() for m in re.finditer(rb'CREATE TABLE "([A-Z0-9_$#]+)"', self.data)))

    def rows(self, table: str):
        """Yield one dict per row. Raises KeyError when the table is not in the dump."""
        match = re.search(rb'INSERT INTO "' + table.encode() + rb'" \(([^)]*)\) VALUES \([^)]*\)\n', self.data)
        if match is None:
            raise KeyError(table)
        columns = [c.strip().strip('"') for c in match.group(1).decode().split(",")]
        data, pos = self.data, match.end()
        count = struct.unpack_from("<H", data, pos)[0]
        pos += 2
        if count != len(columns):
            raise SystemExit(f"{table}: column count {count} does not match {len(columns)} names.")
        types = []
        for _ in columns:
            col_type = struct.unpack_from("<H", data, pos)[0]
            pos += 4
            if col_type in (TYPE_VARCHAR2, TYPE_CHAR):
                pos += 4  # character set id + form
            types.append(col_type)
        if struct.unpack_from("<I", data, pos)[0] == 0:
            pos += 4

        while True:
            values = {}
            for index, name in enumerate(columns):
                length = struct.unpack_from("<H", data, pos)[0]
                pos += 2
                if index == 0 and length == END_OF_TABLE:
                    return
                if length == NULL_FIELD:
                    values[name] = None
                    continue
                raw = bytes(data[pos:pos + length])
                pos += length
                col_type = types[index]
                if col_type == TYPE_NUMBER:
                    values[name] = decode_number(raw)
                elif col_type == TYPE_DATE:
                    values[name] = decode_date(raw)
                elif col_type in (TYPE_VARCHAR2, TYPE_CHAR):
                    values[name] = raw.decode("cp1252", "replace").strip()
                else:
                    raise SystemExit(f"{table}.{name}: column type {col_type} is not supported.")
            if struct.unpack_from("<H", data, pos)[0] != 0:
                raise SystemExit(f"{table}: unexpected bytes at offset {pos}; the dump may use a format this script does not read.")
            pos += 2
            yield values


# --- conversion -----------------------------------------------------------------------------------

def text(value) -> str:
    return "" if value is None else str(value).strip()


def code(value) -> str:
    """Numeric IDs come back as int; keep them as plain strings."""
    return "" if value is None else str(int(value)) if isinstance(value, (int, float)) else str(value).strip()


def lookup(dump: ExpDump, table: str, key: str, label: str) -> dict[str, str]:
    try:
        return {code(r[key]): text(r[label]) for r in dump.rows(table)}
    except KeyError:
        return {}


def month_key(moment: datetime) -> str:
    return moment.strftime("%Y-%m")


def default_range(punch_months: collections.Counter) -> tuple[date, date]:
    months = sorted(punch_months)[-2:]
    if not months:
        raise SystemExit("The dump has no punches.")
    first = datetime.strptime(months[0], "%Y-%m").date()
    last = datetime.strptime(months[-1], "%Y-%m").date()
    next_month = date(last.year + (last.month == 12), last.month % 12 + 1, 1)
    return first, date.fromordinal(next_month.toordinal() - 1)


def valid_email(value: str) -> bool:
    return bool(re.fullmatch(r"[^@\s]+@[^@\s]+\.[A-Za-z]{2,}", value))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("dump", type=Path, help="the .DMP file")
    parser.add_argument("--from", dest="date_from", type=date.fromisoformat, help="first day to import (YYYY-MM-DD)")
    parser.add_argument("--to", dest="date_to", type=date.fromisoformat, help="last day to import (YYYY-MM-DD)")
    parser.add_argument("--out", type=Path, help="output folder")
    parser.add_argument("--company-id", help="only this COMPANY_ID (default: all)")
    parser.add_argument("--include-left", action="store_true", help="also export employees who have left")
    args = parser.parse_args()

    if not args.dump.is_file():
        raise SystemExit(f"File not found: {args.dump}")
    out = args.out or args.dump.with_name(args.dump.stem + "-import")
    out.mkdir(parents=True, exist_ok=True)
    dump = ExpDump(args.dump)
    warnings: list[str] = []
    print(f"Reading {args.dump} ({len(dump.tables)} tables)...")

    departments = lookup(dump, "DEPARTMENT", "DEPARTMENT_ID", "DEPARTMENT_NAME")
    designations = lookup(dump, "DESIGNATION", "DESIGNATION_ID", "DESIGNATION_NAME")
    grades = lookup(dump, "GRADE_TYPE", "GRADE_ID", "GRADE_DESC")
    categories = lookup(dump, "EMPLOYEE_CATEGORY", "CATEGORY_CODE", "CATEGORY")
    cities = lookup(dump, "CITIES", "CITY_ID", "CITY_NAME")
    locations = lookup(dump, "LOCATION", "LOCATION_ID", "LOCATION_NAME")
    leave_types = lookup(dump, "LEAVES_TYPES", "LEAVE_ID", "LEAVE_DESC")
    companies = lookup(dump, "COMPANY", "COMPANY_ID", "COMPANY_NAME")

    # Employees
    employees = {}
    for r in dump.rows("EMPLOYEE_INFORMATION"):
        if args.company_id and code(r.get("COMPANY_ID")) != args.company_id:
            continue
        if not args.include_left and code(r.get("STATUS")) != "1":
            continue
        employees[code(r["EMPLOYEE_ID"])] = r
    print(f"  {len(employees)} employees selected")

    # Punches: device punches + manual entries
    punch_months: collections.Counter = collections.Counter()
    raw_punches = []
    for r in dump.rows("DAILY_ATTENDANCE"):
        moment = r["ATT_DATE"]
        if moment is None:
            continue
        number = code(r["EMPLOYEE_NO"])
        if number in employees:
            punch_months[month_key(moment)] += 1
            raw_punches.append((number, moment, "device"))
    try:
        for r in dump.rows("MANNUAL_ATENDANCE"):
            day = r["ATT_DATE"]
            number = code(r["EMPLOYEE_NO"])
            if day is None or number not in employees:
                continue
            for column in ("ATT_TIME", "ATT_TIME_OUT"):
                clock = re.fullmatch(r"(\d{1,2}):(\d{2})", text(r.get(column)))
                if clock:
                    raw_punches.append((number, day.replace(hour=int(clock[1]), minute=int(clock[2]), second=0), "manual"))
    except KeyError:
        pass

    date_from, date_to = default_range(punch_months)
    date_from, date_to = args.date_from or date_from, args.date_to or date_to
    print(f"  importing {date_from} to {date_to}")

    punches = {}
    manual_count = 0
    for number, moment, source in raw_punches:
        if date_from <= moment.date() <= date_to:
            key = (number, moment.strftime("%Y-%m-%d %H:%M"))
            if key not in punches:
                manual_count += source == "manual"
            punches.setdefault(key, (number, moment))
    with open(out / "punches.csv", "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(["employee_code_or_pin", "date", "time", "direction"])
        for number, moment in sorted(punches.values(), key=lambda p: (p[1], p[0])):
            writer.writerow([number, moment.strftime("%Y-%m-%d"), moment.strftime("%H:%M:%S"), ""])
    last_punch = max((m for _, m in punches.values()), default=None)
    if last_punch and last_punch.date() < date_to:
        warnings.append(f"The last punch in the range is on {last_punch:%Y-%m-%d}, before the end date {date_to}.")

    # employees.csv
    with open(out / "employees.csv", "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(["code", "full_name", "father_name", "cnic", "gender", "dob", "joined_on", "device_pin",
                         "employment_type", "overtime_eligible", "department_code", "designation", "grade",
                         "phone", "home_phone", "email", "emergency_contact", "address", "city", "payroll_id"])
        for number, r in sorted(employees.items(), key=lambda e: int(e[0]) if e[0].isdigit() else e[0]):
            name = " ".join(text(r["EMPLOYEE_NAME"]).split())
            cnic = re.sub(r"\D", "", text(r.get("CNIC_NO")))
            if cnic and len(cnic) != 13:
                warnings.append(f"Employee {number} ({name}): CNIC '{text(r.get('CNIC_NO'))}' is not 13 digits, left blank.")
                cnic = ""
            email = text(r.get("OFFICE_EMAIL")) or text(r.get("EMAIL"))
            if email and not valid_email(email):
                warnings.append(f"Employee {number} ({name}): email '{email}' is not valid, left blank.")
                email = ""
            hired = r.get("HIRE_DATE")
            if hired is None:
                hired = r.get("START_DATE") or r.get("CREATED_ON")
                warnings.append(f"Employee {number} ({name}): no hire date, used "
                                + (f"{hired:%Y-%m-%d} (record start/created date). Check it." if hired else "nothing; the import will reject this row."))
            category = categories.get(code(r.get("CATEGORY_CODE")), "")
            employment_type = {"permanent": "permanent", "contract": "contract", "daily wages": "daily_wage"}.get(category.lower(), "")
            overtime = {"Y": "yes", "N": "no"}.get(text(r.get("OVER_TIME")).upper(), "")
            writer.writerow([
                number, name, text(r.get("FATHER_HUSBAND_NAME")), cnic, text(r.get("GENDER")).lower(),
                r["DATE_OF_BIRTH"].strftime("%Y-%m-%d") if r.get("DATE_OF_BIRTH") else "",
                hired.strftime("%Y-%m-%d") if hired else "",
                code(r.get("MECHINE_ID")) or number, employment_type, overtime,
                departments.get(code(r.get("DEPARTMENT_ID")), ""), designations.get(code(r.get("DESIGNATION_ID")), ""),
                grades.get(code(r.get("GRADE_ID")), ""),
                text(r.get("CELL_PHONE")) or text(r.get("OFFICIAL_MOBILE_NO")), text(r.get("HOME_PHONE")), email,
                text(r.get("EMERGENCY_CONTECT")), " ".join(text(r.get("ADDRESS")).split()),
                cities.get(code(r.get("CITY_ID")), ""), "" if code(r.get("PAYROLL_ID")) in ("", "0") else code(r.get("PAYROLL_ID")),
            ])

    # Shifts from the roster: each employee's most common timing in the range, plus their off days
    timing_by_employee: dict[str, collections.Counter] = collections.defaultdict(collections.Counter)
    off_days: dict[str, collections.Counter] = collections.defaultdict(collections.Counter)
    try:
        for r in dump.rows("ROSTER_DETAIL"):
            day, number = r.get("SHIFT_DATE"), code(r.get("EMPLOYEE_NO"))
            if day is None or number not in employees or not date_from <= day.date() <= date_to:
                continue
            time_in, time_out = text(r.get("TIME_IN")), text(r.get("TIME_OUT"))
            if time_in and time_out:
                timing_by_employee[number][(time_in, time_out)] += 1
            else:
                off_days[number][day.strftime("%A")] += 1
    except KeyError:
        warnings.append("No ROSTER_DETAIL table: shifts could not be worked out.")
    shift_groups: dict[tuple, list[str]] = collections.defaultdict(list)
    for number in employees:
        if timing_by_employee[number]:
            timing = timing_by_employee[number].most_common(1)[0][0]
            weeks = max(1, (date_to - date_from).days // 7)
            offs = tuple(sorted(d for d, n in off_days[number].items() if n >= weeks // 2))
            shift_groups[(timing, offs)].append(number)
        else:
            shift_groups[(None, ())].append(number)

    # Holidays and leave in the range
    holidays = []
    try:
        for r in dump.rows("HOLIDAYS_DETAIL"):
            start = r.get("HOLYDAY_DATE")
            if start is None:
                continue
            end = r.get("TO_DATE") or start
            if start.date() <= date_to and end.date() >= date_from:
                holidays.append((start.date(), end.date(), text(r.get("HOLIDAY_DETAIL"))))
    except KeyError:
        pass
    leaves = []
    try:
        for r in dump.rows("LEAVE_TRANSACTION"):
            start, end, number = r.get("FROM_DATE"), r.get("TO_DATE"), code(r.get("EMPLOYEE_NO"))
            if start is None or number not in employees:
                continue
            end = end or start
            if start.date() <= date_to and end.date() >= date_from:
                days = r.get("LEAVE_DAYS") or (end.date() - start.date()).days + 1
                leaves.append((number, start.date(), end.date(), leave_types.get(code(r.get("LEAVE_ID")), "?"), days))
    except KeyError:
        pass

    # report.md
    name_of = {n: " ".join(text(r["EMPLOYEE_NAME"]).split()) for n, r in employees.items()}
    lines = [
        f"# Import report: {args.dump.name}", "",
        f"Generated {datetime.now():%Y-%m-%d %H:%M}. Range **{date_from} to {date_to}**"
        f"{'' if args.include_left else ', active employees only'}.", "",
        "| File | Rows | Load it in Haziri |", "|---|---|---|",
        f"| employees.csv | {len(employees)} | Employees > Import employees. Tick \"Create missing departments\". |",
        f"| punches.csv | {len(punches)} ({manual_count} from manual attendance) | Devices > USB import, Device: 'Unknown / other'. |", "",
        "Load the employees first, then set up the shifts and holidays below, then load the punches.", "",
        "## Companies and locations in the dump", "",
        "Companies: " + (", ".join(f"{k} = {v}" for k, v in companies.items()) or "none"), "",
        "Employees per company: " + ", ".join(f"{companies.get(k, k)}: {v}" for k, v in collections.Counter(code(r.get('COMPANY_ID')) for r in employees.values()).items()), "",
        "Employees per location: " + ", ".join(f"{locations.get(k, k)}: {v}" for k, v in collections.Counter(code(r.get('LOCATION_ID')) for r in employees.values()).items()), "",
        "## Shifts (from the roster)", "",
        "Create these under Shifts, make the biggest one the default, then use Employees > select > Assign shift for the rest.", "",
        "| Timing | Weekly off | Employees | Codes |", "|---|---|---|---|",
    ]
    for (timing, offs), numbers in sorted(shift_groups.items(), key=lambda g: -len(g[1])):
        label = f"{timing[0]} to {timing[1]}" if timing else "no roster in range"
        lines.append(f"| {label} | {', '.join(offs) or '-'} | {len(numbers)} | {', '.join(sorted(numbers))} |")
    lines += ["", "## Holidays in the range", ""]
    lines += [f"- {s} to {e}: {d}" if s != e else f"- {s}: {d}" for s, e, d in sorted(holidays)] or ["None found."]
    lines += ["", "## Leave in the range", "",
              "Haziri shows these days as absent unless the leave is entered (Leave module).", ""]
    if leaves:
        lines += ["| Code | Name | From | To | Type | Days |", "|---|---|---|---|---|---|"]
        lines += [f"| {n} | {name_of[n]} | {s} | {e} | {t} | {d} |" for n, s, e, t, d in sorted(leaves, key=lambda x: (x[1], x[0]))]
    else:
        lines.append("None found.")
    lines += ["", "## Warnings", ""] + ([f"- {w}" for w in warnings] or ["None."])
    (out / "report.md").write_text("\n".join(lines) + "\n", encoding="utf-8")

    print(f"  {len(punches)} punches ({manual_count} manual), {len(holidays)} holidays, {len(leaves)} leave entries, {len(warnings)} warnings")
    print(f"Done. Files are in {out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
