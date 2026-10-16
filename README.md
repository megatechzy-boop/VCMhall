# venutaihall.com

Marketing website for **Late Venutai Chavan Multipurpose Hall** (Nigdi, Pune) — five connected event spaces: Big Hall, Small Hall, Dining Hall, VIP Dining, Guest Rooms.

## Stack

- Hand-written static HTML + one shared stylesheet (`styles.css`) + one shared script (`script.js`). Sharp optimizes images and esbuild minifies the public CSS/JS build files.
- `_build.py` generates sub-pages and calls `seo_build.py` for SEO pages, metadata, redirects, robots rules, sitemap and optimized assets. It extracts the shared header, nav drawer, footer, floating actions and bottom bar from `index.html`. `popup.php` is preserved as its existing integration.
- Fonts: Fraunces (display), Manrope (body), Noto Sans + Noto Serif Devanagari (Marathi) via Google Fonts.
- Colors: ivory / wine / brass palette defined as custom properties in `styles.css` (`--ivory`, `--wine`, `--brass`, `--sand`, `--sage`, ...).
- CSS is organized with `@layer reset, base, components, pages, utilities`.

## Layout

| Path | Purpose |
| --- | --- |
| `index.html` | Homepage — also the single source of truth for shared chrome (sprite, header, drawer, footer, floats) |
| `styles.css` | All styles |
| `script.js` | Availability wizard, gallery filter + lightbox, reveals, event cards |
| `_build.py` | Page generator (run after editing `index.html` or `_build.py`) |
| `_check.py` | Verifies every local reference across all generated pages |
| `_src/` | Body sources used by the generator (page mains, event mains) |
| `assets/` | Optimized `.webp` photos/logos plus `favicon.svg` |
| `.original-assets/` | Original JPG/PNG image sources; excluded from the cPanel copy list and blocked by `.htaccess` |
| `booking.php`, `popup.php` | Availability JSON/POST endpoints (backend contract; do not modify) |

## Develop

```powershell
python _build.py     # regenerate all pages
python _check.py     # verify local refs
node --check script.min.js
python -m http.server 8090   # local preview at http://localhost:8090
```

Edit `index.html` for homepage or shared-chrome changes; never edit generated `.html` files directly.

## Content rules

- Never invent prices, capacities or facilities. Figures used: 10+ years, 1000+ events, capacity 1200 (Big Hall) / 350 (Small) / 400 at a time (Dining) / 50 at a time (VIP), 8 A/C rooms, 5 venue spaces, © 2025.
- The Big Hall Package section is an oversized spec list of the client's five confirmed points, verbatim (label + figure) — rewording or adding points needs client sign-off. Pattern rationale lives in `CHANGELOG.md`.
- The sage **Speciality** badge on the Guest Rooms row must stay at 19px / weight 800 — white on sage is only 4.27:1, which passes WCAG solely as large bold text (≥18.66px at 700+). Shrinking it breaks the contrast pass.
- WhatsApp: `https://wa.me/919359567494`
- Unconfirmed facts and optional image requests are tracked in `PLACEHOLDERS.md`; internal markers do not render in public pages.

## Backend contract (read-only)

- `GET booking.php?hall=big|small` → `{ today, hall, bookedDates[] }`
- `POST booking.php` with `name, phone, email, event, hall(small|big), date, guests, message, honeypot` → `201 { message, reference }`; `hall: ''` → `422`.
