"""End-to-end check of the PHP request, admin confirmation, and date blocking flow."""
import http.cookiejar
import json
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php") or r"C:\xampp\php\php.exe"


def php_literal(value):
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def request(opener, url, fields=None):
    data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    try:
        response = opener.open(urllib.request.Request(url, data=data), timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode()


with tempfile.TemporaryDirectory() as temp:
    base = Path(temp)
    public = base / "public"
    private = base / "private"
    (public / "admin").mkdir(parents=True)
    private.mkdir()
    for name in ("booking.php", "booking-store.php", "admin/index.php"):
        shutil.copy2(ROOT / name, public / name)
    password_hash = subprocess.check_output([PHP, "-r", 'echo password_hash("test-password", PASSWORD_DEFAULT);'], text=True)
    db_path = private / "bookings.sqlite"
    (public / "booking-config.php").write_text(
        "<?php return ['database' => " + php_literal(db_path.as_posix())
        + ", 'admin_password_hash' => " + php_literal(password_hash)
        + ", 'email_to' => 'disabled-for-test', 'email_from' => ''];",
        encoding="utf-8",
    )
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    log_path = base / "server.log"
    log = log_path.open("w", encoding="utf-8")
    server = subprocess.Popen(
        [PHP, "-d", f"session.save_path={private}", "-S", f"127.0.0.1:{port}", "-t", str(public)],
        stdout=subprocess.DEVNULL, stderr=log,
    )
    try:
        url = f"http://127.0.0.1:{port}"
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        for _ in range(50):
            try:
                if request(opener, url + "/booking.php")[0] == 200:
                    break
            except urllib.error.URLError:
                time.sleep(0.1)
        else:
            raise AssertionError("PHP test server did not start")

        event_date = (date.today() + timedelta(days=14)).isoformat()
        fields = {"name": "Test Guest", "phone": "9876543210", "event": "Wedding", "date": event_date}
        status, body = request(opener, url + "/booking.php", fields)
        assert status == 201 and body, f"{status} {body!r} {log_path.read_text(encoding='utf-8')}"
        booking_id = json.loads(body)["reference"]
        assert event_date not in json.loads(request(opener, url + "/booking.php")[1])["bookedDates"]

        status, login = request(opener, url + "/admin/")
        assert status == 200
        token = re.search(r'name="csrf" value="([^"]+)"', login).group(1)
        status, panel = request(opener, url + "/admin/", {"csrf": token, "password": "test-password", "action": "login"})
        assert status == 200 and "Test Guest" in panel
        status, panel = request(opener, url + "/admin/", {"csrf": token, "id": str(booking_id), "action": "confirm"})
        assert status == 200 and "Booking updated." in panel
        assert event_date in json.loads(request(opener, url + "/booking.php")[1])["bookedDates"]
        assert request(opener, url + "/booking.php", fields)[0] == 409

        status, panel = request(opener, url + "/admin/", {"csrf": token, "id": str(booking_id), "action": "cancel"})
        assert status == 200 and "Booking updated." in panel
        assert event_date not in json.loads(request(opener, url + "/booking.php")[1])["bookedDates"]
        status, panel = request(opener, url + "/admin/", {"csrf": token, "date": event_date, "action": "block"})
        assert status == 200 and "Date blocked." in panel
        assert event_date in json.loads(request(opener, url + "/booking.php")[1])["bookedDates"]
        assert request(opener, url + "/booking.php", {**fields, "phone": "bad"})[0] == 422
        db = sqlite3.connect(db_path)
        try:
            assert db.execute("SELECT COUNT(*) FROM bookings WHERE status = 'blocked'").fetchone()[0] == 1
        finally:
            db.close()
        print("PASS: PHP request, admin confirmation, duplicate rejection, cancellation and manual block")
    finally:
        server.terminate()
        server.wait(timeout=5)
        log.close()
