# Late Venutai Chavan Multipurpose Hall

Dependency-free HTML, CSS, and JavaScript venue site with a Home page (`index.html`) and a dedicated Our Spaces page (`our-spaces.html`).

## Run locally

Open `index.html` in a browser, or serve this directory with any static web server. For example:

```sh
python -m http.server 8000
```

Then open `http://localhost:8000`.

Open `http://localhost:8000/our-spaces.html` for the Our Spaces page. There is no dependency installation or build step.

## Our Spaces

The page follows the supplied reference: cream and maroon hero, curved hall photograph, two large hall cards, three smaller dining/guest-room cards, booking banner and four-column footer. On phones the cards stack in one column and the navigation becomes a menu. `our-spaces.css` scopes the new layout to this page; both pages share `styles.css` and `script.js`.

All five photos and the lotus mark are reused from the existing site. The photographs are approximate matches, not the exact images in the new reference. Existing contact information and capacities are retained. Booking links open the Home page enquiry form. Each View Photos button opens the selected room image in the shared gallery, with previous/next buttons, arrow-key navigation and Escape to close. Only one existing image per room is available. Facebook and Instagram remain decorative because the repository provides no profile URLs; WhatsApp is linked.

## Check

```sh
node --check script.js
python tests/check_site.py
```

The static check validates local page links, section targets, image/script/style paths, unique IDs and all five photo controls. For a browser smoke check, open both pages, exercise the mobile menu, all five photo buttons, next/previous and Escape, and follow Book Now to the enquiry form. Check widths of 320, 390, 768, 1024 and 1440 pixels. Google Fonts is optional; the site includes system-font fallbacks.

The enquiry form opens WhatsApp with a prefilled message to the contact number shown in the reference. No form data is stored on this site. The venue images in `assets/` were generated from the supplied page screenshot as visual guidance and should be replaced with approved venue photography when available.
