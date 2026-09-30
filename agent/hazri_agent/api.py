"""HTTP client for the Hazri agent API (spec §13.1)."""

from __future__ import annotations

from typing import Any

import requests


class ApiError(RuntimeError):
    pass


class HazriApi:
    def __init__(self, server: str, api_key: str, verify_tls: bool = True, timeout: int = 30,
                 session: requests.Session | None = None) -> None:
        self.base = server.rstrip("/") + "/api/v1/agent"
        self.session = session or requests.Session()
        self.session.headers.update({"Authorization": f"Bearer {api_key}", "Accept": "application/json"})
        self.verify = verify_tls
        self.timeout = timeout

    def push_punches(self, agent_version: str, serial: str, punches: list[dict[str, Any]]) -> dict[str, Any]:
        return self._post("punches", {"agent_version": agent_version, "device_serial": serial, "punches": punches},
                          ok=(200, 207))

    def push_users(self, serial: str, users: list[dict[str, Any]]) -> dict[str, Any]:
        return self._post("users", {"device_serial": serial, "users": users})

    def commands(self, serial: str) -> list[dict[str, Any]]:
        r = self.session.get(f"{self.base}/commands", params={"device_serial": serial}, timeout=self.timeout, verify=self.verify)
        self._check(r)
        return list(r.json().get("commands", []))

    def command_result(self, command_id: int, ok: bool, message: str) -> None:
        self._post(f"commands/{command_id}/result", {"ok": ok, "message": message})

    def heartbeat(self, agent_version: str, devices: list[dict[str, Any]]) -> dict[str, Any]:
        return self._post("heartbeat", {"agent_version": agent_version, "devices": devices})

    def _post(self, path: str, body: dict[str, Any], ok: tuple[int, ...] = (200,)) -> dict[str, Any]:
        r = self.session.post(f"{self.base}/{path}", json=body, timeout=self.timeout, verify=self.verify)
        self._check(r, ok)
        return r.json() if r.content else {}

    @staticmethod
    def _check(r: requests.Response, ok: tuple[int, ...] = (200,)) -> None:
        if r.status_code not in ok:
            raise ApiError(f"HTTP {r.status_code}: {r.text[:300]}")
