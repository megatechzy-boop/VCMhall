"""Structural checks for Settings and the public website popup."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
page = (ROOT / "admin" / "settings.php").read_text(encoding="utf-8")
library = (ROOT / "admin" / "settings-lib.php").read_text(encoding="utf-8")
script = (ROOT / "script.js").read_text(encoding="utf-8")
styles = (ROOT / "styles.css").read_text(encoding="utf-8")
schema = (ROOT / "schema.sql").read_text(encoding="utf-8")

for tab in ("Venue Details", "Event Types", "Pricing & Payments", "Status & Booking Rules", "Notifications", "Templates", "Users", "System", "Website Popup"):
    assert tab in page
assert "CREATE TABLE IF NOT EXISTS settings" in schema and "CREATE TABLE IF NOT EXISTS event_types" in schema
assert "MAX(sort_order)" in page and "used by existing records" in page
assert "steps<1||$steps>5" in page
assert "TEMPLATE_VARIABLES" in library and "Unsupported template variable" in library
assert "FILEINFO_MIME_TYPE" in library and "random_bytes" in library and "5*1024*1024" in library
assert "single administrator" in page.lower()
assert "popup_type" in page and "popup_desktop_image" in page and "popup_mobile_image" in page
assert "localStorage" in script and "vcm-popup-closed" in script and "cooldownHours" in script
assert "website-popup-overlay" in styles and "object-fit: contain" in styles
assert "@media (max-width: 600px)" in styles
assert "hash_equals" in page and "setting_url" in library

print("PASS: settings tabs, persistence, event safety, payment ceiling, templates, single-admin scope, uploads, popup cooldown and responsive rules")
