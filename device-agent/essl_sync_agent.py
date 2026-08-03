"""
LAN-side sync agent for an eSSL/ZKTeco biometric device.

Runs on a machine on the SAME LAN as the biometric device (the cloud server
cannot reach it directly). Pulls attendance punches from the device over the
SDK protocol (TCP, typically port 4370) and pushes new ones to the VODOHRMS
cloud API. Safe to run repeatedly (e.g. every 5 minutes via Windows Task
Scheduler) -- it only pushes punches newer than the last successful sync, and
the cloud endpoint de-duplicates on (device, device_user_id, punch_time) too.

Requires: pip install pyzk requests
"""

import configparser
import json
import logging
import os
import sys
from datetime import datetime

import requests
from zk import ZK

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.path.join(BASE_DIR, "config.ini")
CURSOR_PATH = os.path.join(BASE_DIR, "last_sync.json")
BATCH_SIZE = 200

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler(os.path.join(BASE_DIR, "sync_agent.log")),
        logging.StreamHandler(sys.stdout),
    ],
)
log = logging.getLogger("essl_sync_agent")


def load_config():
    parser = configparser.ConfigParser()
    if not parser.read(CONFIG_PATH):
        log.error("config.ini not found next to this script. Copy config.ini.example and fill it in.")
        sys.exit(1)

    device = parser["device"]
    cloud = parser["cloud"]

    return {
        "device_ip": device.get("ip"),
        "device_port": device.getint("port", fallback=4370),
        "device_password": device.getint("comm_password", fallback=0),
        "device_timeout": device.getint("timeout_seconds", fallback=10),
        "cloud_base_url": cloud.get("base_url").rstrip("/"),
        "device_token": cloud.get("device_token"),
    }


def load_cursor():
    if not os.path.exists(CURSOR_PATH):
        return None
    with open(CURSOR_PATH, "r", encoding="utf-8") as f:
        data = json.load(f)
    return datetime.fromisoformat(data["last_punch_time"])


def save_cursor(last_punch_time):
    with open(CURSOR_PATH, "w", encoding="utf-8") as f:
        json.dump({"last_punch_time": last_punch_time.isoformat()}, f)


def fetch_new_punches(config, since):
    zk = ZK(
        config["device_ip"],
        port=config["device_port"],
        password=config["device_password"],
        timeout=config["device_timeout"],
    )
    conn = zk.connect()
    try:
        conn.disable_device()
        records = conn.get_attendance() or []
    finally:
        conn.enable_device()
        conn.disconnect()

    records.sort(key=lambda r: r.timestamp)

    if since is not None:
        records = [r for r in records if r.timestamp > since]

    return records


def push_batch(config, punches):
    url = f"{config['cloud_base_url']}/api/biometric/punches"
    headers = {
        "Authorization": f"Bearer {config['device_token']}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }

    response = requests.post(url, headers=headers, json={"punches": punches}, timeout=30)
    response.raise_for_status()
    return response.json()


def main():
    config = load_config()
    since = load_cursor()

    log.info("Connecting to device at %s:%s ...", config["device_ip"], config["device_port"])
    records = fetch_new_punches(config, since)

    if not records:
        log.info("No new punches.")
        return

    log.info("Found %d new punch(es). Pushing to cloud in batches of %d.", len(records), BATCH_SIZE)

    latest_seen = since

    for i in range(0, len(records), BATCH_SIZE):
        chunk = records[i:i + BATCH_SIZE]
        payload = [
            {
                "device_user_id": str(r.user_id),
                "punch_time": r.timestamp.isoformat(),
                # punch value 0/1 on most eSSL/ZKTeco firmware maps to check-in/check-out;
                # adjust this mapping if your device reports differently.
                "punch_type": "in" if getattr(r, "punch", 0) == 0 else "out",
            }
            for r in chunk
        ]

        result = push_batch(config, payload)
        log.info("Batch %d-%d: %s", i, i + len(chunk), result.get("summary"))

        latest_seen = chunk[-1].timestamp
        save_cursor(latest_seen)

    log.info("Sync complete. Cursor advanced to %s.", latest_seen)


if __name__ == "__main__":
    try:
        main()
    except Exception:
        log.exception("Sync run failed")
        sys.exit(1)
