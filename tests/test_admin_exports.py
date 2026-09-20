"""Smoke-test generated Excel and PDF payloads without needing a database."""
import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which("php") or r"C:\xampp\php\php.exe"
LIBRARY = str(ROOT / "admin" / "dashboard-lib.php")
ROWS = [{
    "id": 42, "event_date": "2026-12-15", "name": "Export Guest",
    "phone": "9876543210", "email": "guest@example.com", "event_type": "Wedding",
    "guests": 300, "source": "website", "total_amount": "200000.00",
    "paid_amount": "50000.00", "follow_up_at": "2026-10-01 10:30:00", "status": "confirmed",
}]


def generate(function):
    code = f"require $argv[1]; $rows=json_decode($argv[2], true); {function}($rows);"
    return subprocess.check_output([PHP, "-r", code, LIBRARY, json.dumps(ROWS)])


excel = generate("admin_excel_export")
assert excel.startswith(b"\xef\xbb\xbf<table>") and b"Export Guest" in excel and b"50000.00" in excel

pdf = generate("admin_pdf_export")
assert pdf.startswith(b"%PDF-1.4\n") and pdf.rstrip().endswith(b"%%EOF")
assert b"xref\n" in pdf and b"VCM-000042" in pdf and b"Export Guest" in pdf

print("PASS: Excel and PDF exports contain filtered-record fields and valid file signatures")
