# ADR 0003 — Machine sync (ADMS, bridge agent, USB)

Status: accepted (2026-10-01)

## ADMS package
- `msaied/zkteco` (v0.2) is a pyzk-style TCP **pull** client with no ADMS server, so per spec §2 we use
  **`tanemrahman/zkteco-adms` 1.4** for iClock push. The wire protocol is never hand-rolled.
- The package's tables (`zkteco_*`) live in **each tenant DB**: its migrations are copied into `database/migrations/tenant`.
  (The package also auto-registers them for the central `migrate`; those central copies stay empty and are harmless.)
- `ResolveAdmsTenant` replaces the package's `zkteco.adms.device` middleware alias: SN → central `device_registry` →
  tenant → `tenancy()->initialize()`, applies the device's comm key / timezone / photo setting, then delegates to the
  package middleware. The package's `TransactionsReceived` / `UsersSynced` events feed `IngestPunchAction` / unmapped PINs.
- Package defaults overridden: `attendance.retention_days = 0` (the default of 30 would silently drop punches from a
  device that was offline for a month), `queue_processing = false` (we ingest in-request; recompute is queued anyway).
- Templates: the package stores only FIDs and has-fp/has-face flags, never template data (spec §6.1 satisfied; test asserts it).

## Deviation from spec §6.1: unknown serials get 403, not `OK`
Spec says to answer `OK` and log the unknown device. ZKTeco firmware treats `OK` as "accepted" and **deletes its buffered
punches**, so those punches would be lost before the super-admin attaches the device. We record the serial in
`central.unknown_devices` and answer `Error: unknown device` (403): the device keeps its buffer and re-sends everything
once it is registered.

## Bridge agent
- Python 3.11, pyzk, SQLite watermark (`last_ts` + `last_uid` per serial), batches of 500, advances only after 2xx.
- Timestamps are sent as device-local wall-clock time without an offset; the server applies `devices.timezone`
  (fallback: tenant timezone). Timestamps that include an offset are honoured as-is.
- pyzk's `punch` is the punch state (in/out) and `status` is the verify mode; the agent maps them to the API's `status` / `verify`.
- Auth: bearer key → `agent_keys.key_hash` (SHA-256) → tenant. The device serial must be a tenant device of type `agent`.

## Dedupe across sources
`dedupe_hash = sha1(device_serial|pin|punched_at_utc)` is identical for ADMS, agent and USB when they carry the same
device serial, so re-importing a USB dump of punches that were already pushed produces only `already_stored` rows.
