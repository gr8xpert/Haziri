"""Local SQLite state: per-device watermark (last pushed timestamp + uid) and last user-list upload."""

from __future__ import annotations

import sqlite3


class Watermarks:
    def __init__(self, path: str) -> None:
        self.db = sqlite3.connect(path)
        self.db.execute(
            "CREATE TABLE IF NOT EXISTS watermarks (serial TEXT PRIMARY KEY, last_ts TEXT, last_uid INTEGER NOT NULL DEFAULT 0,"
            " users_pushed_at REAL NOT NULL DEFAULT 0)"
        )
        self.db.commit()

    def get(self, serial: str) -> tuple[str | None, int]:
        row = self.db.execute("SELECT last_ts, last_uid FROM watermarks WHERE serial = ?", (serial,)).fetchone()
        return (row[0], int(row[1])) if row else (None, 0)

    def set(self, serial: str, last_ts: str, last_uid: int) -> None:
        self.db.execute(
            "INSERT INTO watermarks (serial, last_ts, last_uid) VALUES (?, ?, ?)"
            " ON CONFLICT(serial) DO UPDATE SET last_ts = excluded.last_ts, last_uid = excluded.last_uid",
            (serial, last_ts, last_uid),
        )
        self.db.commit()

    def users_pushed_at(self, serial: str) -> float:
        row = self.db.execute("SELECT users_pushed_at FROM watermarks WHERE serial = ?", (serial,)).fetchone()
        return float(row[0]) if row else 0.0

    def set_users_pushed_at(self, serial: str, at: float) -> None:
        self.db.execute(
            "INSERT INTO watermarks (serial, users_pushed_at) VALUES (?, ?)"
            " ON CONFLICT(serial) DO UPDATE SET users_pushed_at = excluded.users_pushed_at",
            (serial, at),
        )
        self.db.commit()
