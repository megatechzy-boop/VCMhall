# Late Venutai Chavan Multipurpose Hall

Venue site with HTML, one shared CSS file, JavaScript, and a PHP/SQLite booking backend. The pages cover Home, About, Our Spaces, Events, Facilities, Gallery, and Contact.
The Events index and eight occasion pages cover weddings, engagements, receptions, birthdays, naming ceremonies, family functions, corporate events, and social gatherings.
The About page is at `about.html`.
The Facilities page is at `facilities.html`.
The Gallery page is at `gallery.html`.
The Contact page is at `contact.html`.

## Run locally

Serve this directory with PHP 8.2+ and the `pdo_sqlite` extension. A static file server will show the pages but cannot save booking requests. For example:

```sh
php -S 127.0.0.1:8000 -t .
```

Then open `http://localhost:8000`.

Open `http://localhost:8000/about.html` for the About page.
Open `http://localhost:8000/our-spaces.html` for the Our Spaces page. There is no dependency installation or build step.
Open `http://localhost:8000/facilities.html` for the Facilities page.
Open `http://localhost:8000/gallery.html` for the Gallery page.
Open `http://localhost:8000/contact.html` for the Contact page.
Open `http://localhost:8000/events.html` to browse the event pages.

## Events

The eight static event pages have distinct titles, descriptions, planning points, space recommendations, questions, and links to the shared enquiry form. They use existing venue images and facts already presented elsewhere on the site. The Events index links every page, and the main navigation links the index. No event-specific photo collection or pricing was supplied; replace generic venue photos and confirm availability, capacities, and technical specifications with the venue before making stronger claims. The site has no confirmed production domain, so a domain-specific sitemap and canonical URLs are deferred.

## About

The About page follows the supplied reference with hall hero, portrait, inspiration and venue sections, values, commitment, highlights, booking banner, and light footer. It reuses the existing hall photograph and crops the portrait, venue entrance, and flowers from the supplied image. Biographical wording and the 10+/1000+ figures come from the reference and should be confirmed by the venue before publication elsewhere.

## Contact

The Contact page follows the supplied reference with a venue hero, contact details, enquiry form, location panel, visit banner, and light footer. It uses crops of the supplied image for the hero and location map. The map opens Google Maps search for the shown Nigdi address; the site has no confirmed venue pin. Its form now saves requests in the PHP backend. Working hours and nearby landmarks follow the reference and should be confirmed by the venue.

## Gallery

The Gallery page follows the supplied reference with a hall hero, eight photo filters, 12 photo cards, a booking banner, and a light footer. Cards open the shared photo viewer. Previous/next controls stay within the selected filter. The first five photos reuse existing venue images. Seven additional views display the matching photo regions of the supplied reference image (`assets/gallery-reference.jpg`); approved full-resolution photos should replace these when available.

## Facilities

The Facilities page follows the supplied reference with a maroon hero, 12 amenity cards, booking banner, and responsive two-column phone layout. It reuses the site photos for the hall, guest rooms, and dining hall. For the other nine cards, CSS shows the corresponding photo region of the user-provided reference image (`assets/facilities-reference.jpg`); those are layout references, so approved full-resolution venue photos should replace them when available. Booking links open the existing enquiry form on Home.

## Our Spaces

The page follows the supplied reference: cream and maroon hero, curved hall photograph, two large hall cards, three smaller dining/guest-room cards, booking banner and four-column footer. On phones the cards stack in one column and the navigation becomes a menu. All pages now load `styles.css`; page-specific selectors remain scoped by body class.

All five photos and the lotus mark are reused from the existing site. The photographs are approximate matches, not the exact images in the new reference. Existing contact information and capacities are retained. Booking links open the Home page enquiry form. Each View Photos button opens the selected room image in the shared gallery, with previous/next buttons, arrow-key navigation and Escape to close. Only one existing image per room is available. Facebook and Instagram remain decorative because the repository provides no profile URLs; WhatsApp is linked.

## Booking setup on cPanel

Do not deploy the new booking forms until this setup is complete: without `booking-config.php`, the forms show a service-unavailable message. Use HTTPS, PHP 8.2+ with `pdo_sqlite`, writable PHP sessions, and a working PHP mail transport.

1. Create a writable private folder **outside** `public_html`, for example `/home/CPANEL_USER/vcmhall-private/`. The SQLite file will be created there automatically.
2. Copy `booking-config.example.php` to `booking-config.php` on the server. Set the absolute `database` path and a `password_hash()` value for `admin_password_hash`. Booking notifications are addressed from and to `bookings@venutaihall.com`; confirm that this mailbox exists in cPanel. The filled config is ignored by Git.
3. Open `/admin/` and sign in with the password used to generate the hash. Review pending requests, confirm or cancel them, and manually block dates already booked offline.
4. Send a test request and check both the admin list and the mailbox. `accepted_by_mail_server` means PHP accepted the message; it does not prove inbox delivery.

The Home and Contact forms submit to `booking.php`. A new request is **pending** and does not reserve a date. Confirming it, or manually blocking an offline booking, removes that entire date from the visitor date lists and rejects further requests for that date. Cancelling releases the date. The date lists cover the next 24 months. The public API returns dates only, never guest details. The database must stay outside the web document root.

## Check

```sh
node --check script.js
node --check gallery.js
python tests/check_site.py
python tests/test_booking.py
php -l booking.php
php -l booking-store.php
php -l admin/index.php
```

The static check validates local page links, section targets, image/script/style paths, unique IDs and all five photo controls. For a browser smoke check, open both pages, exercise the mobile menu, all five photo buttons, next/previous and Escape, and follow Book Now to the enquiry form. Check widths of 320, 390, 768, 1024 and 1440 pixels. Google Fonts is optional; the site includes system-font fallbacks.

The venue images in `assets/` were generated from the supplied page screenshot as visual guidance and should be replaced with approved venue photography when available.
