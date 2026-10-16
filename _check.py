import re, glob, os
from urllib.parse import urlparse

pages = sorted(glob.glob("*.html"))
missing = []
checked = 0

for f in pages:
    h = open(f, encoding="utf-8").read()
    refs = re.findall(r'(?:href|src)="([^"]+)"', h)
    for r in refs:
        if r.startswith(("http://", "https://", "mailto:", "tel:", "#", "javascript:")):
            continue
        path = urlparse(r).path
        if not path or path == "/":
            continue
        if path.startswith("/"):
            path = path[1:]
        if not path:
            continue
        checked += 1
        if os.path.exists(path):
            continue
        if os.path.exists(path + ".html"):
            continue
        if path.startswith("assets/") and path.lower().endswith((".jpg", ".jpeg", ".png")):
            if os.path.exists(os.path.splitext(path)[0] + ".webp"):
                continue
        missing.append(f"{f} -> {r}")

print(f"checked {checked} local refs across {len(pages)} pages")
if missing:
    print("MISSING:")
    for m in missing:
        print(" ", m)
else:
    print("all local refs resolve")
