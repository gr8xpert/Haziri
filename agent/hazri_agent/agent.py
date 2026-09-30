"""Hazri bridge agent: pulls punches from ZKTeco devices on the LAN (pyzk) and pushes them to Hazri.

Spec §6.2. Safe to re-run: the server dedupes, and the local watermark only advances after a 2xx.
"""

from __future__ import annotations

import logging
import time
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any, Callable, Iterable, Protocol

from .api import HazriApi
from .state import Watermarks

__version__ = "1.0.0"

log = logging.getLogger("hazri.agent")

BATCH_SIZE = 500
USERS_EVERY_SECONDS = 7 * 24 * 3600


class DeviceConnection(Protocol):
    """The subset of pyzk's connection object the agent uses (lets tests pass a fake)."""

    def get_attendance(self) -> list[Any]: ...
    def get_users(self) -> list[Any]: ...
    def set_user(self, **kwargs: Any) -> Any: ...
    def delete_user(self, **kwargs: Any) -> Any: ...
    def set_time(self, value: datetime) -> Any: ...
    def restart(self) -> Any: ...
    def clear_attendance(self) -> Any: ...
    def disable_device(self) -> Any: ...
    def enable_device(self) -> Any: ...
    def disconnect(self) -> Any: ...


@dataclass
class DeviceConfig:
    serial: str
    ip: str
    port: int = 4370
    password: int = 0
    timeout: int = 10
    force_udp: bool = False
    clear_after_sync: bool = False


@dataclass
class AgentConfig:
    server: str
    api_key: str
    devices: list[DeviceConfig]
    poll_seconds: int = 60
    state_file: str = "hazri-agent.sqlite"
    verify_tls: bool = True
    extra: dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_dict(cls, raw: dict[str, Any]) -> "AgentConfig":
        for key in ("server", "api_key", "devices"):
            if not raw.get(key):
                raise ValueError(f"agent.yaml: '{key}' is required")
        devices = [DeviceConfig(**{k: v for k, v in d.items() if k in DeviceConfig.__dataclass_fields__}) for d in raw["devices"]]
        for d in devices:
            d.serial = str(d.serial)
        return cls(
            server=str(raw["server"]).rstrip("/"),
            api_key=str(raw["api_key"]),
            devices=devices,
            poll_seconds=int(raw.get("poll_seconds", 60)),
            state_file=str(raw.get("state_file", "hazri-agent.sqlite")),
            verify_tls=bool(raw.get("verify_tls", True)),
        )


def pyzk_connector(device: DeviceConfig) -> DeviceConnection:
    from zk import ZK  # imported lazily so tests don't need pyzk

    return ZK(device.ip, port=device.port, timeout=device.timeout, password=device.password,
              force_udp=device.force_udp, ommit_ping=True).connect()


class Agent:
    def __init__(self, config: AgentConfig, api: HazriApi | None = None, state: Watermarks | None = None,
                 connect: Callable[[DeviceConfig], DeviceConnection] = pyzk_connector,
                 clock: Callable[[], float] = time.time) -> None:
        self.config = config
        self.api = api or HazriApi(config.server, config.api_key, verify_tls=config.verify_tls)
        self.state = state or Watermarks(config.state_file)
        self.connect = connect
        self.clock = clock

    # ------------------------------------------------------------------ loop
    def run_forever(self) -> None:
        log.info("Hazri agent %s started for %d device(s)", __version__, len(self.config.devices))
        while True:
            self.poll_once()
            time.sleep(self.config.poll_seconds)

    def poll_once(self) -> list[dict[str, Any]]:
        health = []
        for device in self.config.devices:
            health.append(self.sync_device(device))
        try:
            self.api.heartbeat(__version__, health)
        except Exception as exc:  # network down: next poll retries
            log.warning("Heartbeat failed: %s", exc)
        return health

    # ------------------------------------------------------------- per device
    def sync_device(self, device: DeviceConfig) -> dict[str, Any]:
        status: dict[str, Any] = {"serial": device.serial, "reachable": False, "unsynced": None, "error": None}
        conn = None
        try:
            conn = self.connect(device)
            status["reachable"] = True
            status["unsynced"] = self._push_attendance(device, conn)
            self._push_users_if_due(device, conn)
            self._run_commands(device, conn)
        except Exception as exc:
            log.exception("Device %s: %s", device.serial, exc)
            status["error"] = str(exc)[:500]
        finally:
            if conn is not None:
                try:
                    conn.disconnect()
                except Exception:  # pragma: no cover - best effort
                    pass
        return status

    def _push_attendance(self, device: DeviceConfig, conn: DeviceConnection) -> int:
        records = sorted(conn.get_attendance() or [], key=lambda r: (r.timestamp, getattr(r, "uid", 0)))
        mark = self.state.get(device.serial)
        fresh = [r for r in records if self._is_newer(r, mark)]
        if not fresh:
            return 0

        for batch in _chunks(fresh, BATCH_SIZE):
            self.api.push_punches(__version__, device.serial, [_punch_payload(r) for r in batch])
            last = batch[-1]
            self.state.set(device.serial, last.timestamp.strftime("%Y-%m-%d %H:%M:%S"), int(getattr(last, "uid", 0) or 0))
            log.info("Device %s: pushed %d punch(es) up to %s", device.serial, len(batch), last.timestamp)

        if device.clear_after_sync:
            conn.clear_attendance()
            log.info("Device %s: attendance log cleared after sync", device.serial)
        return 0

    @staticmethod
    def _is_newer(record: Any, mark: tuple[str | None, int]) -> bool:
        last_ts, last_uid = mark
        if last_ts is None:
            return True
        ts = record.timestamp.strftime("%Y-%m-%d %H:%M:%S")
        return ts > last_ts or (ts == last_ts and int(getattr(record, "uid", 0) or 0) > last_uid)

    def _push_users_if_due(self, device: DeviceConfig, conn: DeviceConnection) -> None:
        now = self.clock()
        if now - self.state.users_pushed_at(device.serial) < USERS_EVERY_SECONDS:
            return
        users = [{"pin": str(u.user_id), "name": (u.name or "")[:100], "card": str(u.card or "") or None}
                 for u in (conn.get_users() or [])]
        self.api.push_users(device.serial, users)
        self.state.set_users_pushed_at(device.serial, now)

    def _run_commands(self, device: DeviceConfig, conn: DeviceConnection) -> None:
        for cmd in self.api.commands(device.serial):
            ok, message = True, "done"
            try:
                self._execute(conn, cmd["command"], cmd.get("payload") or {})
            except Exception as exc:
                ok, message = False, str(exc)[:2000]
                log.warning("Device %s: command %s failed: %s", device.serial, cmd["command"], exc)
            self.api.command_result(cmd["id"], ok, message)

    @staticmethod
    def _execute(conn: DeviceConnection, command: str, payload: dict[str, Any]) -> None:
        if command == "set_user":
            conn.set_user(user_id=str(payload["pin"]), name=str(payload.get("name") or "")[:24],
                          privilege=0, password="", group_id="", card=int(payload.get("card") or 0))
        elif command == "delete_user":
            conn.delete_user(user_id=str(payload["pin"]))
        elif command == "sync_time":
            conn.set_time(datetime.now())
        elif command == "reboot":
            conn.restart()
        else:
            raise ValueError(f"unknown command {command!r}")


def _punch_payload(record: Any) -> dict[str, Any]:
    # pyzk: `punch` is the punch state (0 in / 1 out …), `status` is the verify mode.
    return {
        "pin": str(record.user_id),
        "timestamp": record.timestamp.strftime("%Y-%m-%d %H:%M:%S"),  # device-local; server applies device tz
        "status": int(getattr(record, "punch", 0) or 0),
        "verify": int(getattr(record, "status", 0) or 0),
        "uid": int(getattr(record, "uid", 0) or 0),
    }


def _chunks(items: list[Any], size: int) -> Iterable[list[Any]]:
    for i in range(0, len(items), size):
        yield items[i:i + size]
