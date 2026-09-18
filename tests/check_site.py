"""Dependency-free checks for the site's local links and photo controls."""
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit
from xml.etree import ElementTree

ROOT = Path(__file__).resolve().parents[1]


class Page(HTMLParser):
    def __init__(self, path):
        super().__init__()
        self.ids = set()
        self.links = []
        self.photos = []
        self.feed(path.read_text(encoding="utf-8"))

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if "id" in attrs:
            assert attrs["id"] not in self.ids, f"Duplicate ID: {attrs['id']}"
            self.ids.add(attrs["id"])
        for key in ("href", "src", "data-image"):
            if attrs.get(key):
                self.links.append(attrs[key])
        if "data-image" in attrs:
            assert tag == "button" and attrs.get("data-caption"), "Photo control needs a caption"
            self.photos.append(attrs["data-image"])


pages = {path: Page(path) for path in ROOT.glob("*.html")}
checked = 0
for path, page in pages.items():
    for link in page.links:
        url = urlsplit(link)
        if url.scheme or url.netloc:
            continue
        target = (path.parent / unquote(url.path)).resolve() if url.path else path
        assert target.is_file(), f"{path.name}: missing file {link}"
        if url.fragment and target in pages:
            assert unquote(url.fragment) in pages[target].ids, f"{path.name}: missing anchor {link}"
        checked += 1

spaces = pages[ROOT / "our-spaces.html"]
assert len(spaces.photos) == 5 and len(set(spaces.photos)) == 5, "Expected five distinct room photos"
assert "our-spaces.html" in pages[ROOT / "index.html"].links, "Home must link to Our Spaces"
assert "about.html" in pages[ROOT / "index.html"].links, "Home must link to About"
assert "events.html" in pages[ROOT / "index.html"].links, "Home must link to Events"
for slug in ("weddings", "engagements", "receptions", "birthdays", "naming-ceremonies", "family-functions", "corporate-events", "social-gatherings"):
    assert f"{slug}.html" in pages[ROOT / "events.html"].links, f"Events must link to {slug}"
    assert "contact.html#enquire" in pages[ROOT / f"{slug}.html"].links, f"{slug} must link to enquiry"
assert "contact.html" in pages[ROOT / "index.html"].links, "Home must link to Contact"
assert "#enquire" in pages[ROOT / "contact.html"].links, "Contact must link to its enquiry form"
assert "index.html#enquire" in spaces.links, "Our Spaces must link to booking"
namespace = {"s": "http://www.sitemaps.org/schemas/sitemap/0.9"}
sitemap = ElementTree.parse(ROOT / "sitemap.xml")
urls = [element.text for element in sitemap.findall("s:url/s:loc", namespace)]
expected_urls = {"https://venutaihall.com/"} | {
    f"https://venutaihall.com/{path.name}" for path in pages if path.name != "index.html"
}
assert len(urls) == len(set(urls)) and set(urls) == expected_urls, "Sitemap must list every public HTML page once"
assert "Sitemap: https://venutaihall.com/sitemap.xml" in (ROOT / "robots.txt").read_text(encoding="utf-8")
print(f"PASS: {len(pages)} pages, {checked} local references, five room photo controls")
