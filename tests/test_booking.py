"""End-to-end check of PHP/MySQL enquiry, admin and date blocking."""
import http.cookiejar
import json
import os
import re
import secrets
import shutil
import socket
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
DB_PHP = r"""
$name = getenv('VCM_TEST_DB_NAME');
if (!preg_match('/^vcm_test_[a-f0-9]{16}$/', $name)) { throw new Exception('Invalid test database name'); }
$dsn = 'mysql:host=' . getenv('VCM_TEST_MYSQL_HOST') . ';port=' . getenv('VCM_TEST_MYSQL_PORT') . ';charset=utf8mb4';
$db = new PDO($dsn, getenv('VCM_TEST_MYSQL_USER'), getenv('VCM_TEST_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (getenv('VCM_TEST_ACTION') === 'create') {
  $db->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
  $db->exec('USE `' . $name . '`');
  $db->exec(file_get_contents(getenv('VCM_TEST_SCHEMA')));
} else {
  $db->exec('DROP DATABASE IF EXISTS `' . $name . '`');
}
"""


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


def run_test(env):
    with tempfile.TemporaryDirectory() as temp:
        base = Path(temp)
        public = base / "public"
        private = base / "private"
        (public / "admin").mkdir(parents=True)
        private.mkdir()
        for name in ("booking.php", "booking-store.php"):
            shutil.copy2(ROOT / name, public / name)
        for name in ("index.php", "dashboard-lib.php", "settings-lib.php", "admin.css", "admin.js"):
            shutil.copy2(ROOT / "admin" / name, public / "admin" / name)
        password_hash = subprocess.check_output([PHP, "-r", 'echo password_hash("test-password", PASSWORD_DEFAULT);'], text=True)
        (public / "booking-config.php").write_text(
            "<?php return ['mysql' => ['host' => " + php_literal(env["VCM_TEST_MYSQL_HOST"])
            + ", 'port' => " + str(int(env["VCM_TEST_MYSQL_PORT"]))
            + ", 'name' => " + php_literal(env["VCM_TEST_DB_NAME"])
            + ", 'user' => " + php_literal(env["VCM_TEST_MYSQL_USER"])
            + ", 'password' => " + php_literal(env["VCM_TEST_MYSQL_PASSWORD"])
            + "], 'admin_password_hash' => " + php_literal(password_hash)
            + ", 'email_to' => 'disabled-for-test', 'email_from' => ''];",
            encoding="utf-8",
        )
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        log_path = base / "server.log"
        with log_path.open("w", encoding="utf-8") as log:
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
                assert status == 201, f"{status} {body!r} {log_path.read_text(encoding='utf-8')}"
                first_id = json.loads(body)["reference"]
                status, body = request(opener, url + "/booking.php", {**fields, "name": "Second Guest"})
                assert status == 201, f"Second pending enquiry: {status} {body!r}"
                second_id = json.loads(body)["reference"]
                def booked():
                    return json.loads(request(opener, url + "/booking.php")[1])["bookedDates"]
                assert event_date not in booked()

                status, login = request(opener, url + "/admin/")
                assert status == 200
                token = re.search(r'name="csrf" value="([^"]+)"', login).group(1)
                def action(**fields):
                    return request(opener, url + "/admin/", {"csrf": token, **fields})
                status, panel = action(password="test-password", action="login")
                assert status == 200 and "Booking Management Dashboard" in panel
                assert "Test Guest" in panel and "Second Guest" in panel
                assert "Venue Calendar" in panel and 'id="detailDrawer"' in panel
                followup = (date.today() + timedelta(days=1)).isoformat() + "T10:30"
                assert "Follow-up scheduled." in action(id=str(first_id), action="schedule_followup", follow_up_at=followup)[1]
                assert "Internal notes saved." in action(id=str(first_id), action="save_notes", internal_notes="Prefers a morning call")[1]
                assert "Follow-up completed." in action(id=str(first_id), action="complete_followup")[1]
                converted = action(id=str(first_id), action="confirm")[1]
                first_booking_id = int(re.search(r"VCM-(\d+)", converted).group(1))
                assert event_date in booked()
                assert request(opener, url + "/booking.php", fields)[0] == 409
                assert "This date is already booked or blocked." in action(id=str(second_id), action="confirm")[1]
                assert "Booking details saved." in action(id=str(first_booking_id), action="save_details", source="whatsapp", total_amount="100000")[1]
                assert "Payment recorded." in action(id=str(first_booking_id), action="add_payment", amount="25000", payment_method="upi", payment_reference="UPI-TEST", payment_notes="Advance")[1]
                status, filtered = request(opener, url + "/admin/?tab=bookings&source=whatsapp&q=Test+Guest&date=" + event_date)
                assert status == 200 and "Test Guest" in filtered and "Partial" in filtered
                assert request(opener, url + "/admin/?tab=bookings&source=whatsapp&export=excel")[1].startswith("\ufeff<table>")
                assert request(opener, url + "/admin/?tab=bookings&source=whatsapp&export=pdf")[1].startswith("%PDF-1.4")
                assert "Record cancelled." in action(id=str(first_booking_id), action="cancel")[1]
                assert event_date not in booked()
                second_converted = action(id=str(second_id), action="confirm")[1]
                second_booking_id = int(re.search(r"VCM-(\d+)", second_converted).group(1))
                assert "Record cancelled." in action(id=str(second_booking_id), action="cancel")[1]
                assert "Date blocked." in action(date=event_date, action="block")[1]
                assert event_date in booked()
                assert "That date is already booked or blocked." in action(date=event_date, action="block")[1]
                direct_date = (date.today() + timedelta(days=21)).isoformat()
                direct = action(action="add_booking", name="Walk In Guest", phone="9988776655", email="walkin@example.com", event_type="Reception", event_date=direct_date, guests="250", source="walk-in", total_amount="150000")[1]
                assert "Booking added." in direct and direct_date in booked()
                assert request(opener, url + "/booking.php", {**fields, "phone": "bad"})[0] == 422
                print("PASS: dashboard filters, calendar, drawer data, conversion, notes, follow-up, payment, exports, direct booking and blocking")
            finally:
                server.terminate()
                server.wait(timeout=5)


required = ("VCM_TEST_MYSQL_HOST", "VCM_TEST_MYSQL_PORT", "VCM_TEST_MYSQL_USER", "VCM_TEST_MYSQL_PASSWORD")
missing = [name for name in required if name not in os.environ]
if missing:
    raise SystemExit("Set isolated MySQL test connection variables: " + ", ".join(missing))
test_env = os.environ.copy()
test_env["VCM_TEST_DB_NAME"] = "vcm_test_" + secrets.token_hex(8)
test_env["VCM_TEST_SCHEMA"] = str(ROOT / "schema.sql")
test_env["VCM_TEST_ACTION"] = "create"
subprocess.run([PHP, "-r", DB_PHP], env=test_env, check=True)
try:
    run_test(test_env)
finally:
    test_env["VCM_TEST_ACTION"] = "drop"
    subprocess.run([PHP, "-r", DB_PHP], env=test_env, check=True)
