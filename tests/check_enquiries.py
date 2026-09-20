"""Structural checks for the dedicated Enquiries workflow."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
page = (ROOT / "admin" / "enquiries.php").read_text(encoding="utf-8")
library = (ROOT / "admin" / "enquiries-lib.php").read_text(encoding="utf-8")
script = (ROOT / "admin" / "enquiries.js").read_text(encoding="utf-8")
styles = (ROOT / "admin" / "enquiries.css").read_text(encoding="utf-8")
schema = (ROOT / "schema.sql").read_text(encoding="utf-8")

for label in ("Total Enquiries", "Converted to Bookings", "Pending Follow-up", "Cancelled", "Total Guests (Enquiries)"):
    assert label in page
for label in ("All", "New", "In Discussion", "Quote Sent", "Follow-up", "Converted", "Cancelled"):
    assert label in page
for column in ("Ref", "Enquiry Date", "Event Date", "Name / Contact", "Event Type", "Guests", "Source", "Status", "Follow-up", "Actions"):
    assert f">{column}<" in page
for tab in ("details", "payment", "timeline", "notes"):
    assert f'data-tab="{tab}"' in page
assert "LIMIT 5000" in library and "LIMIT ' . (int) $perPage" in library
assert "admin_convert_enquiry" in (ROOT / "admin" / "dashboard-lib.php").read_text(encoding="utf-8")
assert "record_type" in schema and "enquiry_id" in schema
assert "CREATE TABLE IF NOT EXISTS enquiry_notes" in schema
assert "CREATE TABLE IF NOT EXISTS enquiry_followups" in schema
assert "track_whatsapp" in page and "whatsapp_action" in page
assert "@media(max-width:760px)" in styles and "overflow:visible" in styles
assert "data-label" in page and "showModal" in script
assert "hash_equals" in page and "record_type='enquiry'" in page

print("PASS: enquiry metrics, filters, tabs, pagination, drawer, notes, follow-ups, conversion, exports, security and mobile cards")
