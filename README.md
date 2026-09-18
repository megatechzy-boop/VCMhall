# Late Venutai Chavan Multipurpose Hall

Venue site with HTML, one shared CSS file, JavaScript, and a PHP/MySQL booking backend. The pages cover Home, About, Our Spaces, Events, Facilities, Gallery, and Contact.
The Events index and eight occasion pages cover weddings, engagements, receptions, birthdays, naming ceremonies, family functions, corporate events, and social gatherings.
The About page is at `about.html`.
The Facilities page is at `facilities.html`.
The Gallery page is at `gallery.html`.
The Contact page is at `contact.html`.

## Run locally

Serve this directory with PHP 8.2+, the `pdo_mysql` extension, and a MySQL/MariaDB database. Import `schema.sql` and configure `booking-config.php` as described below before testing bookings. A static file server will show the pages but cannot save booking requests. For example:

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

Do not deploy the booking forms until this setup is complete: without `booking-config.php`, the forms show a service-unavailable message. Use HTTPS, PHP 8.2+ with `pdo_mysql`, writable PHP sessions, and a working PHP mail transport.

1. In cPanel's MySQL Database Wizard, create a dedicated database and user with full privileges on that database. Import `schema.sql` into the database using phpMyAdmin.
2. Copy `booking-config.example.php` to `booking-config.php` on the server. Fill in the `mysql` host, port, database name, username and password; cPanel commonly prefixes database and user names with the account name. Generate `admin_password_hash` with `php -r "echo password_hash('YOUR_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"` and paste the resulting hash. The filled config is ignored by Git. Keep its permissions restricted and never commit it.
3. Confirm that `bookings@venutaihall.com` exists in cPanel and that PHP mail can send from it. Booking notifications use this address as sender and recipient unless changed in config.
4. Open `/admin/` and sign in with the password used to generate the hash. Review pending requests, confirm or cancel them, and manually block dates already booked offline.
5. Send a test request and check both the admin list and the mailbox. `accepted_by_mail_server` means PHP accepted the message; it does not prove inbox delivery.

The Home and Contact forms submit to `booking.php`. A new request is **pending** and does not reserve a date. Confirming it, or manually blocking an offline booking, removes that entire date from the visitor date lists and rejects further requests for that date. Cancelling releases the date. The date lists cover the next 24 months. The public API returns dates only, never guest details. MySQL credentials belong only in the ignored server config; database access must be limited to the dedicated user.

If an earlier SQLite version was used for real bookings, move those records to MySQL before switching the live site; importing the empty schema alone does not preserve them.

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

The booking integration test requires `VCM_TEST_MYSQL_HOST`, `VCM_TEST_MYSQL_PORT`, `VCM_TEST_MYSQL_USER`, and `VCM_TEST_MYSQL_PASSWORD` environment variables. Use an isolated local MySQL/MariaDB instance: the test user must be able to create and drop its temporary `vcm_test_*` database. An empty password still needs `VCM_TEST_MYSQL_PASSWORD` set to an empty string. Never point this test at production.

The venue images in `assets/` were generated from the supplied page screenshot as visual guidance and should be replaced with approved venue photography when available.
