# hazri-agent

Bridge for ZKTeco machines **without** ADMS/cloud push (K40, K50, older MB models). It runs on any always-on PC or
Raspberry Pi in the office LAN, pulls punches over TCP 4370 with [pyzk](https://github.com/fananimi/pyzk), and pushes
them to Haziri's agent API (spec §6.2).

- Only punches newer than the last successful upload are sent (SQLite watermark). Resending is harmless — the server dedupes.
- The device log is never cleared unless `clear_after_sync: true`.
- Weekly it uploads the device's user list (PIN + name only) so unmapped PINs are easy to match. **Fingerprint/face templates are never read or sent.**
- It executes remote commands queued in Haziri (push employee PIN/name, delete user, sync time, reboot) and reports the result.
- Every poll it sends a heartbeat, so Haziri shows "online / offline" and the last error per device.

## Setup

1. In Haziri: **Devices → New device**, type **agent**, with the machine's serial number (Menu → System Info → Device Info) and its LAN IP.
2. **Devices → Sync agent**: download the program and the setup file. The setup file is a ready `agent.yaml` with the
   server address, a new agent key and every agent machine. (Or by hand: **Devices → New agent key** and copy `agent.example.yaml`.)
3. Put `hazri-agent.exe` (or `hazri-agent` on Linux) and `agent.yaml` in one folder.
4. Test: `hazri-agent -c agent.yaml --once` → exit code 0 when every device was reached and uploaded.

### Windows service (NSSM)

```
nssm install HazriAgent "C:\hazri-agent\hazri-agent.exe" -c C:\hazri-agent\agent.yaml --log C:\hazri-agent\hazri-agent.log
nssm set HazriAgent AppDirectory C:\hazri-agent
nssm set HazriAgent Start SERVICE_AUTO_START
nssm start HazriAgent
```

### Raspberry Pi / Linux

See `hazri-agent.service` (systemd).

## Build

The Windows build goes to `public/downloads/hazri-agent.exe` on the server (shipped with the release ZIP, not in git) so **Sync agent** can offer it.


- Windows: `build.bat` → `dist\hazri-agent.exe`
- Linux / Pi: `./build.sh` → `dist/hazri-agent`

## Develop / test

```
pip install requests PyYAML
python -m unittest discover -s tests -v
```

The tests use a fake device and a fake API, so pyzk and a real machine are not needed.
