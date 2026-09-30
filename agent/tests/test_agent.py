"""Agent unit tests with a fake device and a fake API (no pyzk, no network): `python -m unittest discover -s tests`."""

from __future__ import annotations

import os
import sys
import tempfile
import unittest
from datetime import datetime
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from hazri_agent.agent import Agent, AgentConfig, DeviceConfig  # noqa: E402
from hazri_agent.state import Watermarks  # noqa: E402


def rec(pin: str, ts: str, punch: int = 0, uid: int = 0) -> SimpleNamespace:
    return SimpleNamespace(user_id=pin, timestamp=datetime.strptime(ts, "%Y-%m-%d %H:%M:%S"), punch=punch, status=1, uid=uid)


class FakeConn:
    def __init__(self, records, users=None):
        self.records = records
        self.users = users or []
        self.calls = []

    def get_attendance(self):
        return list(self.records)

    def get_users(self):
        return self.users

    def set_user(self, **kw):
        self.calls.append(("set_user", kw))

    def delete_user(self, **kw):
        self.calls.append(("delete_user", kw))

    def set_time(self, value):
        self.calls.append(("set_time", value))

    def restart(self):
        self.calls.append(("restart",))

    def clear_attendance(self):
        self.calls.append(("clear",))

    def disable_device(self):
        pass

    def enable_device(self):
        pass

    def disconnect(self):
        self.calls.append(("disconnect",))


class FakeApi:
    def __init__(self, fail_punches=False, commands=None):
        self.punches, self.users, self.results, self.beats = [], [], [], []
        self.fail_punches = fail_punches
        self._commands = commands or []

    def push_punches(self, version, serial, punches):
        if self.fail_punches:
            raise RuntimeError("HTTP 500")
        self.punches.append((serial, punches))
        return {}

    def push_users(self, serial, users):
        self.users.append((serial, users))

    def commands(self, serial):
        cmds, self._commands = self._commands, []
        return cmds

    def command_result(self, cid, ok, message):
        self.results.append((cid, ok, message))

    def heartbeat(self, version, devices):
        self.beats.append(devices)


class AgentTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.NamedTemporaryFile(suffix=".sqlite", delete=False).name
        self.config = AgentConfig(server="https://api.hazri.test", api_key="k", devices=[DeviceConfig(serial="ZK1", ip="10.0.0.5")])

    def agent(self, conn, api, clock=lambda: 0.0):
        return Agent(self.config, api=api, state=Watermarks(self.tmp), connect=lambda d: conn, clock=clock)

    def test_pushes_only_new_records_and_advances_watermark(self):
        conn = FakeConn([rec("47", "2026-09-01 09:02:11", 0, 1), rec("47", "2026-09-01 18:01:40", 1, 2)])
        api = FakeApi()
        a = self.agent(conn, api)

        a.poll_once()
        conn.records.append(rec("47", "2026-09-02 09:00:00", 0, 3))
        a.poll_once()

        self.assertEqual(len(api.punches), 2)
        self.assertEqual([p["uid"] for p in api.punches[0][1]], [1, 2])
        self.assertEqual([p["uid"] for p in api.punches[1][1]], [3])
        self.assertEqual(api.punches[0][1][1]["status"], 1)  # punch state → status
        self.assertEqual(api.punches[0][1][0]["timestamp"], "2026-09-01 09:02:11")

    def test_does_not_advance_watermark_when_upload_fails(self):
        conn = FakeConn([rec("47", "2026-09-01 09:02:11", uid=1)])
        a = self.agent(conn, FakeApi(fail_punches=True))
        health = a.poll_once()

        self.assertIn("HTTP 500", health[0]["error"])
        self.assertEqual(Watermarks(self.tmp).get("ZK1"), (None, 0))

    def test_runs_commands_and_reports_results(self):
        conn = FakeConn([])
        api = FakeApi(commands=[
            {"id": 1, "command": "set_user", "payload": {"pin": "47", "name": "Ali Khan"}},
            {"id": 2, "command": "sync_time", "payload": {}},
            {"id": 3, "command": "bogus", "payload": {}},
        ])
        self.agent(conn, api).poll_once()

        self.assertEqual(conn.calls[0][0], "set_user")
        self.assertEqual(conn.calls[0][1]["user_id"], "47")
        self.assertEqual([r[:2] for r in api.results], [(1, True), (2, True), (3, False)])

    def test_uploads_users_weekly_and_reports_unreachable_devices(self):
        conn = FakeConn([], users=[SimpleNamespace(user_id="47", name="Ali", card=0)])
        api = FakeApi()
        a = self.agent(conn, api, clock=lambda: 1_000_000.0)
        a.poll_once()
        a.poll_once()
        self.assertEqual(len(api.users), 1)

        def boom(_):
            raise OSError("timed out")

        b = Agent(self.config, api=api, state=Watermarks(self.tmp), connect=boom)
        health = b.poll_once()
        self.assertFalse(health[0]["reachable"])
        self.assertEqual(api.beats[-1][0]["serial"], "ZK1")

    def test_config_requires_server_key_and_devices(self):
        with self.assertRaises(ValueError):
            AgentConfig.from_dict({"server": "x", "devices": []})
        cfg = AgentConfig.from_dict({"server": "https://x/", "api_key": "k", "devices": [{"serial": 123, "ip": "1.2.3.4", "junk": 1}]})
        self.assertEqual(cfg.devices[0].serial, "123")
        self.assertEqual(cfg.server, "https://x")


if __name__ == "__main__":
    unittest.main()
