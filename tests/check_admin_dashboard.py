"""Dependency-free structural checks for the database-backed admin dashboard."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / "admin" / "index.php").read_text(encoding="utf-8")
library = (ROOT / "admin" / "dashboard-lib.php").read_text(encoding="utf-8")
script = (ROOT / "admin" / "admin.js").read_text(encoding="utf-8")
styles = (ROOT / "admin" / "admin.css").read_text(encoding="utf-8")
schema = (ROOT / "schema.sql").read_text(encoding="utf-8")
deploy = (ROOT / ".cpanel.yml").read_text(encoding="utf-8")

for label in ("Dashboard", "Enquiries", "Bookings", "Settings"):
    assert f">{label}<" in index, f"Missing sidebar item: {label}"
assert "Booking Management Dashboard" in index
for metric in (
    "New enquiries this week", "Upcoming bookings", "Pending follow-ups",
    "Total blocked dates", "This month bookings", "Advance received this month",
):
    assert metric in index, f"Missing metric: {metric}"
for status in ("available", "enquiry", "booked", "blocked"):
    assert status in index and status in styles, f"Missing calendar status: {status}"
for tab in ("details", "notes", "payments", "activity"):
    assert f'data-drawer-tab="{tab}"' in index
assert "data-calendar-date" in index and "filterForm" in script
assert "export=excel" in index and "export=pdf" in index
assert "admin_excel_export" in library and "admin_pdf_export" in library
assert "admin_filter_sql" in library and "prepare($sql)" in library
assert "booking_payments" in schema and "booking_activity" in schema
assert "follow_up_at" in schema and "internal_notes" in schema and "total_amount" in schema
assert "COUNT(p.id)" in index and ">= $paymentLimit" in index, "Configured payment limit must be server-enforced"
assert "hash_equals" in index and "password_verify" in index and "session_regenerate_id" in index
assert "@media(max-width:760px)" in styles and "@media(max-width:430px)" in styles
assert "transform:translateY(105%)" in styles, "Mobile drawer must use a bottom-sheet layout"
for asset in ("dashboard-lib.php", "admin.css", "admin.js"):
    assert asset in deploy, f"Admin asset missing from deployment: {asset}"

print("PASS: admin navigation, live metrics, calendar, filters, drawer, payments, exports, security and responsive rules")
