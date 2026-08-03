# eSSL Biometric Device Sync Agent

Bridges an eSSL biometric device (LAN-only, no internet access) to the
VODOHRMS cloud portal. Runs on any Windows PC on the **same LAN** as the
device — the cloud server cannot reach the device directly.

## How it works

1. Connects to the device over its SDK protocol (TCP, port 4370 by default).
2. Reads attendance punches, keeping only ones newer than the last successful
   sync (tracked in `last_sync.json`).
3. POSTs them in batches to `https://<your-domain>/api/biometric/punches`.
4. Only advances the cursor after the cloud confirms receipt, so a failed run
   is retried in full next time — no punches are lost.

## One-time setup

1. In VODOHRMS: **Attendance → Biometric Devices → Create**. Copy the API
   token shown on creation — it is never shown again (use "Regenerate Token"
   on the device's edit page if you lose it).
2. For every employee who uses this device, set their **Biometric Device ID**
   (Employees → [employee] → Employment tab) to match the numeric
   enrollment/user ID configured for them on the device itself.
3. On the LAN machine:
   ```
   pip install -r requirements.txt
   copy config.ini.example config.ini
   ```
   Edit `config.ini`: device IP/port, VODOHRMS base URL, and the token from
   step 1.
4. Test it once manually: `python essl_sync_agent.py`. Check `sync_agent.log`
   for errors, and in VODOHRMS check **Attendance → Biometric Punch Logs** —
   punches should show as `matched` (or `unmatched` if an employee's device ID
   hasn't been set yet; fix it and run `php artisan biometric:rematch` on the
   server to recover them).

## Scheduling (Windows Task Scheduler)

1. Task Scheduler → Create Task.
2. Trigger: Daily, repeat every 5 minutes for a duration of 1 day (or use the
   "Repeat task every" option under Triggers → Advanced).
3. Action: Start a program —
   - Program: path to `python.exe` (or `pythonw.exe` to run without a console
     window)
   - Arguments: `essl_sync_agent.py`
   - Start in: this `device-agent` folder
4. Run whether user is logged on or not, so it survives a reboot/logout.

No persistent Windows service is needed — periodic polling every few minutes
is sufficient for attendance purposes.

## Troubleshooting

- **Connection refused / timeout to the device**: confirm the device's LAN IP
  hasn't changed (set a DHCP reservation for it) and that port 4370 isn't
  blocked by a firewall between this PC and the device.
- **401 from the cloud API**: token is wrong, was regenerated, or the device
  was deactivated in Biometric Devices.
- **Punches show `unmatched`**: the device's `device_user_id` doesn't match
  any employee's `biometric_enroll_id`. Fix the mapping, then run
  `php artisan biometric:rematch` on the server.
- **`punch_type` looks backwards**: the script maps eSSL's raw `punch` value
  0/1 to in/out — some device configurations report this differently. Check
  a few log entries against the device's own report and adjust the mapping
  in `essl_sync_agent.py` (`push_batch` payload construction) if needed.
