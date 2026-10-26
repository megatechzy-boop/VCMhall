# WORKLOG — venutaihall.com

Read RULES.md and DEPLOY.md first after any reset.

This is the in-repo worklog (survives sandbox resets). Append-only. Newest group at the bottom.

---
Date: 2026-10-10
Group: Spaces We Offer — ship modified Option A (editorial split) onto index.html
Branch: wip (off main @ 7c90a17)
Status: in progress

Plan:
- Replace the homepage Spaces We Offer section (the Group-2 pkg-num-grid + chips layout) with the modified Option A editorial split: solid wine numbers panel beside the photo, ivory numerals (weight 400-500, 64-88px desktop / >=56px mobile), brass hairline row dividers, Guest Rooms row featured (1.4x, brass/gold numeral, sage Speciality badge), 4 chips, Full details button, alternating photo side (Big left / Small right), mobile photo-on-top + 2x2 numerals.
- Delete design-lab.html + _labshots/ (user authorised). Remove design-lab generation from _build.py.
- Verify 320/360/390/768/1280 + 1366x768 + 1920x1080: zero overflow/console, AA contrast.
- Screenshots outside repo. Append results here. Commit + push to wip only.

Open questions:
- None blocking. Small Hall dining row keeps data-placeholder="CONFIRM with client: small hall dining details".

---
Date: 2026-10-10
Group: Spaces We Offer — shipped modified Option A (editorial split) onto index.html
Branch: wip
Commit: (pending)
Status: DONE — verified, ready for review

What changed:
- index.html: replaced the Group-2 pkg-num-grid + chips layout in #spaces with the modified Option A editorial split. Per hall: solid WINE panel beside the photo (not on it), ivory numerals (Fraunces weight 450, opsz 144; desktop 64-84px, mobile >=56px), brass hairline dividers between rows, row labels in small caps ivory (desktop 17px / mobile 16px) with the exact wording: "Full A/C Hall · up to 1200 guests" etc. Guest Rooms row is the star: gold (#f2dcae) numeral at 1.40x the others (desktop 96.8px vs 69.1px; mobile 78px vs 56px), sage Speciality badge. 4 chips (Catering/Acoustic/Parking/CCTV, 14px text + 22px icon, icon color #c9a866 for AA on wine). "Full details" button-light on each hall. Kept the section heading eyebrow + Marathi bracket + "See everything included" link. Alternating photo side: Big Hall photo left, Small Hall photo right. Mobile (<=960): photo on top, panel below; Big Hall's 4 numerals become a 2x2 grid at <=768, each cell numeral-on-top + label-below.
- styles.css: removed the now-dead .pkg-num-grid/.pkg-num-cell/.pkg-chip block (homepage-only). Added the .hall-* system (hall-card, hall-photo, hall-panel, hall-row, hall-row-num, hall-row-featured, hall-row-num-featured, hall-speciality, hall-chips, hall-chip, hall-cta) + responsive overrides at <=960 (stack), <=768 (2x2 numeral grid), <=480 (chip tighten), prefers-reduced-motion (hover transforms off).
- _build.py: removed the design-lab SEO entry, the design_lab_main f-string, and the page("design-lab.html", ...) call. (extra_head param kept on page() — harmless, reusable.)
- Deleted: design-lab.html (the generated file) and _labshots/ (the temp screenshot-serving folder). Neither referenced anywhere.
- Small Hall dining row keeps data-placeholder="CONFIRM with client: small hall dining details".

Verification:
- _build.py: 28 pages (was 29; design-lab no longer generated). _check.py: 1217 local refs all resolve. CSS braces 0. node --check OK. _build.py syntax OK.
- Overflow + console + page-errors sweep at 320x568, 360x640, 390x844, 768x1024, 1280x800, 1366x768, 1920x1080 => ALL ZERO.
- Desktop 1280: 2 cards, Big Hall photo-left/panel-right, Small Hall photo-right/panel-left (alternating OK), panel bg wine #641c2c, numeral ivory #f6f1e8 weight 450 @ 69.1px, featured gold #f2dcae @ 96.8px (ratio 1.40), sage Speciality badge, 8 chips (4/hall), chip text 14px, chip icon 22px @ #c9a866, 2 Full-details CTAs, Marathi bracket + See-everything link present.
- Mobile 390: photo order -1 (top), panel below, card 1-col, Big Hall 4 numerals in 2x2 grid (145px cols), normal 56px, featured 78px, label 16px, row dir column.
- AA contrast: ivory numerals on wine = ~10.7:1; gold featured on wine = ~8.6:1; chip ivory text on wine = ~10.7:1; chip icons #c9a866 on wine = ~5:1. All pass.
- Screenshots (outside repo): /home/z/qa-shots/spaces-a-modified/spaces-desktop-1280.png, spaces-mobile-390.png.

What's next:
- Awaiting user review of the shipped Option A. If approved, the next "PUSH NOW" will push wip -> main + mirror. If tweaks wanted, another group on wip.

Open questions:
- Small Hall dining details still need client confirmation (placeholder kept).

---
Date: 2026-10-10
Group: Restore design-lab.html (keep it) + push wip to mirror
Branch: wip
Commit: (pending)
Status: DONE

What changed:
- Restored design-lab.html (deleted in ee016ab) and its generation in _build.py (SEO entry + design_lab_main f-string + page() call) from 7c90a17. The design lab page (Option A original + Option B) is kept as a reference. noindex intact, not linked from nav/footer/sitemap.
- _labshots/ stays deleted (it was a temp screenshot-serving folder, not the design lab).
- Homepage Spaces section unchanged from ee016ab (the modified Option A editorial split is still shipped on index.html).
- Verified: design-lab.html HTTP 200 (2 Option A cards + 2 Option B bands); homepage Spaces = 2 hall-cards, wine panel, featured ratio 1.40; 0 console/errors; build 29 pages, refs resolve, braces 0, JS OK.

Push:
- wip pushed to primary (OmKardile/venutaihall) — plain push.
- wip pushed to mirror (megatechzy-boop/VCMhall) — user-authorised override of the standing "not to the mirror" rule; plain push (new branch on the mirror, no force).
- main + mirror main untouched (still 7c90a17) — no "PUSH NOW" given.

What's next:
- Awaiting user review. On "PUSH NOW", push wip -> main (fast-forward) + mirror main (plain push).

---
Date: 2026-10-10
Group: Spaces fixes — bigger row labels + both photos on the left (no alternation)
Branch: wip
Commit: (pending)
Status: DONE

What changed (per user: "increase the font size of: Full A/C Hall · up to 1200 guests on those damn cards and i told u to start from the left not right"):
- styles.css: .hall-row-label font-size bumped from clamp(16px,1.4vw,17px) -> clamp(20px,2.2vw,26px) desktop; mobile override 16px -> 18px.
- styles.css: removed the .hall-alt .hall-photo { order: 2; } rule (the photo alternation). Both cards now have the photo on the LEFT (start from the left), Small Hall no longer flips its photo to the right.
- Mobile (<=960) still: photo on top, panel below; <=768: Big Hall 4 numerals in 2x2 grid.

Verification:
- Desktop 1280: both photos order 0 (left), label 26px ivory, overflow 0.
- Mobile 390: label 18px, photo order -1 (top), overflow 0.
- Sweep 320/360/390/768/1280 + 1366x768 + 1920x1080: 0 overflow, 0 console, 0 errors.
- build 29 pages, refs resolve, braces 0, JS OK.

Push:
- wip -> primary (OmKardile/venutaihall) plain push.
- Mirror main NOT updated this round (still 526e747 from the prior force-push; no "PUSH NOW" given). Mirror is now 1 commit behind wip on this fix.

What's next:
- If the user wants the mirror's main updated with this fix too, they can say so or "PUSH NOW".

---
Date: 2026-10-10
Group: Prep for cPanel deploy — delete design-lab.html + add frontend-only .cpanel.yml
Branch: wip
Commit: (pending)
Status: DONE, pushed to wip on BOTH repos. main untouched.

Why:
- cPanel GitDeployment (pulls from mirror megatechzy-boop/VCMhall main) cannot deploy: "The system cannot deploy" because no .cpanel.yml exists at the repo root.
- The live public_html (/home/venutaihall/public_html/) contains SERVER-ONLY files NOT in the git repo: booking-config.php, booking-store.php, admin/, error_log. These must NOT be overwritten by a deploy.
- design-lab.html is a design-study page, not for the public live site, so it should not ship.

What changed:
- _build.py: removed design-lab generation (SEO entry + design_lab_main f-string + page() call). design-lab.html no longer built.
- design-lab.html: deleted.
- .cpanel.yml: created at repo root, frontend-only (user-specified exact content):
    ---
    deployment:
      tasks:
        - export DEPLOYPATH=/home/venutaihall/public_html/
        - /bin/cp *.html $DEPLOYPATH
        - /bin/cp styles.css script.js $DEPLOYPATH
        - /bin/cp -R assets $DEPLOYPATH
  Copies ONLY pages + stylesheet + script + images. Leaves booking.php, popup.php, .htaccess, booking-config.php, booking-store.php, admin/ UNTOUCHED. DEPLOYPATH confirmed = /home/venutaihall/public_html/ via cPanel File Manager screenshot.

Verification:
- _build.py: 28 pages (design-lab gone). _check.py: 1217 refs all resolve. braces 0. node --check OK. _build.py syntax OK.
- .cpanel.yml: valid YAML, 4 tasks.
- Homepage @1280: 2 hall-cards intact (modified Option A), label 26px, featured ratio 1.40, no console/errors. design-lab.html 404.

Push:
- wip -> wip on primary (OmKardile/venutaihall): plain push.
- wip -> wip on mirror (megatechzy-boop/VCMhall): plain push.
- main untouched on both (no "PUSH NOW").

Next (user-driven, cPanel UI, no cmd):
1. Back up public_html in cPanel File Manager first.
2. Confirm git status clean on server (cd /home/venutaihall/VCMhall && git status).
3. "PUSH NOW" -> GLM promotes wip to main on the mirror (fast-forward + plain push).
4. cPanel: Update from Remote, then Deploy HEAD Commit.
5. Test live site (phone, one booking, admin login).

---
Date: 2026-10-10
Group: Deployment prep — delete _src clutter + verify deploy manifest
Branch: wip
Commit: (pending)
Status: DONE, will push wip to both repos. main untouched.

What changed:
- Deleted _src/ folder (orphaned tracked clutter from commit 09c345b; no references in _build.py/_check.py/script.js/any HTML; would NOT have been deployed since *.html is root-only and cp -R assets only copies assets/). Repo root is now clean.
- Confirmed (from prior commit 8950d47): design-lab.html gone + _build.py generation removed; .cpanel.yml present with exact frontend-only content.

Deploy manifest (what .cpanel.yml copies):
- *.html (28 files): about, big-hall, birthdays, check-availability, compare, contact, corporate-events, details, dining-hall, engagements, events, everything-included, facilities, family-functions, gallery, guest-rooms, index, naming-ceremonies, our-spaces, privacy-policy, receptions, small-hall, social-gatherings, spaces, terms, vip-dining, visit, weddings. No design-lab. No unexpected.
- styles.css, script.js.
- assets/ (37 files): all images (.jpg/.webp) + favicon.svg. Note: big-hall-primary.jpg/.webp and parking.jpg/.webp are unused (no HTML references) but harmless (copied to public_html/assets, just not displayed).
- NOT copied (server stays untouched): booking.php, popup.php, .htaccess, robots.txt, sitemap.xml, .cpanel.yml, _build.py, _check.py, *.md, (design-lab gone, _src gone).

Verification:
- _build.py 28 pages, _check.py 1217 refs all resolve, braces 0, node --check OK, _build.py syntax OK.
- Homepage @1280: 2 hall-cards (modified Option A intact), label 26px, featured ratio 1.40, 0 console/errors.
- design-lab.html 404, no _src, no _src refs.

Push:
- wip -> wip on primary (OmKardile/venutaihall): plain push.
- wip -> wip on mirror (megatechzy-boop/VCMhall): plain push.
- main untouched on both (no "PUSH NOW").

Next: user backs up public_html, confirms git status clean on server, says "PUSH NOW" to promote wip -> mirror main, then cPanel Update from Remote + Deploy HEAD Commit.

---
Date: 2026-10-10
Group: Spaces alternation flip + deploy-test/RULES/DEPLOY docs (committed + pushed to wip)
Branch: wip
Commits: e0904fb (Deploy test + RULES.md + DEPLOY.md), 049a845 (Spaces: alternate photo side)
Status: DONE, pushed wip to wip on BOTH repos. main untouched (no PUSH NOW).

What changed:
- index.html: Big Hall article gets class "hall-flip" (was "hall-card reveal" -> "hall-card hall-flip reveal").
- styles.css: added ".hall-flip .hall-photo { order: 2; }" so Big Hall photo goes RIGHT (detail panel LEFT). Small Hall stays default (photo LEFT, panel RIGHT = the alternate). Mobile (<=960): both photos on top (existing rule overrides order:2 with order:-1).
- deploy-test.html: new standalone noindex page ("Deploy test OK" + 2026-10-10). NOT in _build.py/nav/footer/sitemap. _check.py now sees 29 pages, no complaint.
- RULES.md + DEPLOY.md: created at repo root with the standing rules + cPanel deploy steps.
- WORKLOG.md: pointer line added at top ("Read RULES.md and DEPLOY.md first after any reset").

Verification:
- Desktop 1280: Big Hall photo order 2 (right), panel order 0 (left); Small Hall photo order 0 (left, default), panel right. ALTERNATION correct. 0 overflow, 0 console.
- Mobile 390: both photos order -1 (top). 0 overflow.
- _build.py 29 pages, _check.py 1217 refs all resolve, braces 0, JS OK.

Rule-4 audit (combined diff 27b350e..049a845):
- 6 files: DEPLOY.md, RULES.md, WORKLOG.md, deploy-test.html, index.html, styles.css. No .php/.htaccess/.cpanel.yml/robots.txt/sitemap.xml.
- Real-token grep (ghp_ + 20+ chars): CLEAN (0).
- Literal ghp_ count: 1 — FALSE POSITIVE (the string "ghp_" inside RULES.md rule #4 which describes the grep). No actual token. Proceeding per user's "push then" (implicit authorization) + the standing interpretation that rule #4 means "no real leaked token".

Push:
- wip -> wip on primary (OmKardile/venutaihall): 27b350e..049a845 plain.
- wip -> wip on mirror (megatechzy-boop/VCMhall): 27b350e..049a845 plain.
- main untouched on both (still 27b350e). No "PUSH NOW" given.

Next:
- To promote to main (so cPanel can deploy the alternation + the docs), user writes "PUSH NOW". Then wip (049a845) -> main on both (fast-forward, plain). Then cPanel Update from Remote + Deploy HEAD Commit.
- Note on ghp_ self-match: whenever RULES.md (or any doc quoting rule #4) is staged, the literal "ghp_" grep will match the rule text. The durable interpretation: rule #4's "ghp_ grep = 0" means "no real token (ghp_+20+ chars) = 0".

---
Date: 2026-10-10
Group: Drop wip workflow — work on main directly; rewrite RULES.md + DEPLOY.md
Branch: main (wip workflow dropped; wip branches left on remotes, just unused)
Status: DONE, will push main to both repos.

What changed:
- Fast-forwarded local main to wip (6e55403): main now has deploy-test.html, RULES.md, DEPLOY.md, WORKLOG.md pointer, the Spaces alternation flip (Big Hall detail-left/image-right, Small Hall image-left/detail-right), and the WORKLOG append.
- Rewrote RULES.md rule 1: "Work on main. After each finished, verified group, commit and push main to BOTH repos... plain push, never --force, keep identical. Pushing to GitHub does NOT change the live site... GLM never deploys." (Removed wip + PUSH NOW as a requirement.)
- Rewrote RULES.md rule 4: token check now uses the regex (ghp_ or github_pat_ followed by 20+ word chars), not the bare prefix. The bare string "ghp_" in docs is fine.
- Rewrote DEPLOY.md: added prominent "Pushing to GitHub does NOT change the live site" principle at the top; rest unchanged.
- wip branches on both remotes left intact (not deleted), just unused going forward.

Verification:
- Build 29 pages, _check.py 1217 refs all resolve, braces 0, JS OK.
- Homepage alternation verified (Big Hall photo order 2 / panel order 0; Small Hall photo order 0 / panel order 0 default = alternate).

Push:
- main -> main on primary (OmKardile/venutaihall): plain push, fast-forward from 27b350e.
- main -> main on mirror (megatechzy-boop/VCMhall): plain push, fast-forward from 27b350e.
- No --force anywhere. wip branches untouched.

---
Date: 2026-10-12
Group: Address rename + signature move + booking.php + footer map + homepage trims + htaccess snippet (partial mega-task)
Branch: main
Status: DONE, committing + pushing. Note: ba6f171 (prior Spaces rows de-dup) was lost to a sandbox reset (never pushed); re-applied here.

DONE this round (items 1, 4, 5, 6, 7, 8-htaccess, 11 + row de-dup re-apply):
- 6 Address: "Yashwantrao Chavan Smarak, " prepended everywhere (110 occurrences across 28 files via _build.py + index.html). 0 remaining without the prefix.
- 7 Signature: removed from shared footer (index.html); kept on about.html body only.
- 5 booking.php: event_time added to $allowed; callback_time + event_time whitelisted; guests already optional; 201 unchanged. (See DEPLOY-MANUAL.md for the server-side hand edit — .cpanel.yml does NOT copy booking.php.)
- 1 Homepage removals: visit-cta, location section, cta-band all removed.
- 4 Footer: logo box shrunk to 72px plate (logo+name+address row); full-width footer-map section (iframe + directions + bullets); Location nav/drawer links -> #footer-map.
- 8 htaccess-snippet.txt: clean-URL rewrite block (301 .html->extensionless + internal rewrite). NOT applied to server .htaccess (DEPLOY-MANUAL.md tells the owner to paste it).
- 11 DEPLOY-MANUAL.md: lists (a) .htaccess paste, (b) booking.php diff + note that .cpanel.yml won't deploy it, (c) design-lab.html to delete from public_html if present.
- Row de-dup re-applied (label left nowrap, numeral+caption right) — replaces lost ba6f171.

DEFERRED (substantial sub-systems, need focused follow-up turns):
- 2 Call picker (3-contact dialog on every Call button) — substantial JS/HTML/CSS + a11y.
- 3 Hero booking form + check-availability shared form — substantial shared component + the specific field set.
- 8 Extensionless URLs in the site itself (links/canonical/og:url/JSON-LD/sitemap + _check.py) — only the htaccess-snippet.txt was created; the in-site link rewrites + _check.py update are not done.
- 9 big-hall.html + small-hall.html update to the latest numbers-block design.
- 10 Highlights styling for capacity numbers + events-reveal space highlights.

Verification: build 29 pages, _check.py 1214 refs all resolve, braces 0, JS OK. Overflow 0 at 320/390/768/1280. Footer map + iframe present, logo 72px, label nowrap, visit-cta + cta-band removed, Location links -> #footer-map.

Push: main -> both repos (primary + mirror), plain, no force.

---
Date: 2026-10-12
Group: Hero — stop bg Ken Burns movement, add one-time entrance reveal
Branch: main
Status: DONE, pushing to both repos.

What changed:
- styles.css: removed .hero-media img { animation: hero-kenburns ... } + the @keyframes hero-kenburns. Image is now static (transform: scale(1.04), no animation).
- Added @keyframes hero-enter (opacity 0 -> 1, translateY 18px -> none) over 0.8s cubic-bezier(.2,.7,.2,1). Applied staggered to .hero h1 (0s), .hero-ctas (0.15s), .hero-place (0.3s), fill both.
- prefers-reduced-motion: .hero h1/ctas/place animation: none, opacity 1, transform none (entrance disabled, copy visible immediately).

Verification: braces 0. Ken Burns gone (0 refs). hero-enter present (4). Desktop 1280: h1/ctas anim = hero-enter, img anim = none, img transform = scale(1.04) static, overflow 0. Sweep 320/390/768/1280 = 0 overflow. Reduced-motion: h1 animationName = none. Rule-4: only styles.css changed, no forbidden files, token regex CLEAN.

---
Date: 2026-10-12
Group: Call picker + backend patches (call_back_time/event_time)
Branch: main
Status: DONE (call picker + backend), committing + pushing. Shared booking form (item 2) is the next group.

DONE:
- Call picker: dialog HTML (3 contacts) in index.html (extracted as shared chrome by _build.py via the lightbox-range extraction). CSS (.call-picker bottom-sheet mobile / centered desktop, reduced-motion). JS module in script.js (open on [data-call-picker] click, Esc + outside-click close, focus trap, body scroll lock, restore focus). Wired 4 call buttons in index.html (header-call, drawer, float-actions, bottom-bar) + 7 in _build.py templates. Header phone -> icon + "Call us". CONTACT_DETAILS footer links untouched (direct tel:).
- Backend: server-patch/booking.php (server's booking.php + call_back_time/event_time read + whitelist + append-to-message storage) + server-patch/booking-store.php (email body gets Call back/Event time lines). Not deployed by cPanel (user uploads by hand). DEPLOY-MANUAL.md updated with the diff + order (upload PHP first, then deploy front-end).

Verification: build 29 pages, braces 0, JS OK. Call picker QA @1280: 4 triggers, header "Call us", opens on click (is-open, 3 contacts, body overflow hidden), Esc closes. CONTACT_DETAILS untouched.

DEFERRED (next group): shared booking form (hero right-side + check-availability, the new field set, compact 2-col, 100svh fit).

Push: main -> both repos, plain.

---
Date: 2026-10-12
Group: Shared booking form on the hero (compact, no guests)
Branch: main
Status: DONE, pushing.

DONE:
- index.html: added the booking form to .hero-inner (sibling to .hero-copy). Fields: Name, Mobile, Email, Space (Big Hall/Small Hall only), Event (10 options), Date, Call Back Time, Event Time. No guests. Honeypot + form-status. class="enquiry-form hero-form" (reuses the existing postForm submit/success handler). Hero "Check Availability" button -> href="#hero-form" (scrolls to it).
- styles.css: .hero-inner 2-col desktop (1.1fr/0.9fr) / stacked mobile. .hero-form ivory card (rgba 0.97, border, shadow, scroll-margin-top). .form-grid-2 2-col with 1-col at <=560. Compact field sizing. Hero padding reduced (calc(header+40px), clamp(32,4vh,48px) bottom) to fit 100svh at 1366x768.

DEFERRED (next group): rewrite check-availability.html (wizard_main) to use the SAME shared single form (+ optional message field) instead of the multi-step wizard. The wizard still works as-is (posts the old fields, patched backend accepts them) — it's a layout/UX follow-up, not a correctness issue.

Verification: build 29 pages, braces 0, JS OK. Desktop 1280: hero-inner 2-col (633+518px), 8 fields, hall=3 options (Big/Small only), Check Availability -> #hero-form, overflow 0. 1366x768: heroH=768=viewport (fits 100svh). Mobile 390: stacked 1-col, form visible, overflow 0. Sweep 320/390/768/1280 = 0 overflow. Rule-4: index.html + styles.css only, no backend files, token CLEAN.

Push: main -> both repos, plain.

---
Date: 2026-10-12
Group: Clean URLs (extensionless) + footer tidy (items 1 + 4 of the 4-item task)
Branch: main
Status: DONE (items 1 + 4), pushing. Items 2 (big/small hall page rewrites) + 3 (highlights) deferred — substantial, need focused turns.

DONE:
- 1 Clean URLs: _build.py (canon via name.removesuffix(".html"); SITEMAP_PAGES extensionless; pic() assets root-relative /assets/; page head link/script root-relative), index.html (all hrefs extensionless + assets root-relative), _check.py (resolves root-relative by stripping leading / + tries path + ".html" for extensionless), script.js (currentFile home = "/"). Also updated the sandbox [...slug]/route.ts to resolve extensionless (try path + ".html") so the preview works. 0 .html hrefs remain (verified). All extensionless URLs serve 200 in preview. Nav works (/ -> /big-hall). Sweep 320/390/768/1280/1366x768/1920x1080 = 0 overflow.
- 4 Footer tidy: removed the Address row from the CONTACT column (Call + Hours remain; address stays in footer-brand + Find Us). .footer-map band dark (--wine-ink) + media container --wine-deep (hides white Google loading flash) + ivory/sand text. Verified mapBg=wine-ink, mediaBg=wine-deep, h3 ivory, overflow 0.

DEFERRED (substantial, next turns):
- 2 big-hall.html + small-hall.html rewrite to the latest numbers-block design + shared form + verbatim lists.
- 3 Highlights: capacity-number stand-out typography sitewide + events-reveal space highlights.

Verification: build 29 pages, _check.py all refs resolve, braces 0, JS OK. Extensionless URLs 200. Sweep 0 overflow. Dark footer-map verified.

DEPLOY ORDER (critical): htaccess MUST be applied to the server BEFORE deploying the extensionless site (DEPLOY-MANUAL.md (f)).

Push: main -> both repos, plain.

---
Date: 2026-10-12
Group: Hero form compact (half) + mobile popup modal
Branch: main
Status: DONE, pushing.

What changed:
- index.html: hero form content wrapped in <div class="hero-form-card">; added <div class="hero-form-head"> with title + a close button (.hero-form-close, data-hero-form-close).
- styles.css: .hero-form is now a neutral container (scroll-margin-top, display:none base). .hero-form-card holds the ivory card styling (compact padding 13-18px). Compact fields (38px min-height, 13px font, 11px labels). Desktop: .hero-inner 1.3fr/0.7fr (narrower form), .hero-form display:block (inline). Mobile (<=899px): .hero-form is position:fixed inset:0 z-index:210 display:none; .is-open -> display:flex (bottom-sheet); ::before scrim; .hero-form-card slides up (translateY 100%->0); close button shown. Reduced-motion: no transitions.
- script.js: heroForm module — on mobile (matchMedia max-width:899px) the "Check Availability" button (a[href="#hero-form"]) opens the modal (preventDefault + is-open + body lock + focus first field); close button + outside-click + Esc close. Desktop: default hash-scroll to the inline form (no preventDefault). Also opens on load if mobile + hash is #hero-form.

Verification: build 29 pages, braces 0, JS OK. Desktop 1280: form inline (403px col, 38px fields, close hidden, 0 overflow). Mobile 390: form hidden (display:none, position:fixed) -> click "Check Availability" opens modal (is-open, display:flex, body overflow hidden, card transform none) -> Esc closes. Sweep 320/390/768/1280 = 0 overflow.

Push: main -> both repos, plain.

---
Date: 2026-10-12
Group: check-availability single-form rewrite + footer-brand address removed (item 1 + footer tweak)
Branch: main
Status: DONE (item 1 + footer tweak), pushing. Items 2 (big/small hall) + 3 (highlights) deferred.

DONE:
- Footer: removed <p class="footer-brand-address"> from .footer-brand in index.html. Brand box = logo + English name only.
- check-availability: replaced wizard_main (multi-step, ~6800 chars) with a single-form version (~3700 chars) in _build.py. Form: class="enquiry-form check-form" id="check-form", action="/booking.php", content in .hero-form-card. Fields: Name, Mobile, Email, Space (Big/Small), Event (10 opts), Date, Call Back, Event Time + optional Message (field-full). Honeypot + form-status. Reuses the existing postForm submit handler (class enquiry-form, no data-custom-submit). Added #check-form ?event= prefill in script.js.
- Removed wizard CSS: the .steps-bar/.step-tab/.wizard/.wizard-pane/.wizard-grid/.wizard-rec/.wizard-actions/.wizard-note/.wizard-success block (base + media-query rules) — ~3000 chars + 6 responsive rules. 0 wizard CSS refs remain.
- Added .check-form to the compact field selectors (.form-grid-2, .field input/select, .field label, .button, .form-status) so the check-availability form matches the hero form's compact styling.

NOTE: The wizard JS block in script.js (const wizard = qs('#wizard'); if (wizard) { ... }) is dead — #wizard no longer exists, so `wizard` is null, `if (wizard)` is skipped. It's harmless guarded dead code. Left in place to avoid breaking the interleaved loadWebsitePopup logic. Flagged for surgical removal in a focused cleanup (the block is ~200 lines interleaved with other modules; removing blind risks syntax errors).

DEFERRED: item 2 (big-hall/small-hall latest design) + item 3 (highlights sitewide + events reveal).

Verification: build 29 pages, _check.py all refs resolve, braces 0, JS OK. check-availability: #check-form present (9 fields incl message), #wizard gone, steps-bar gone, action=/booking.php, ?event=Wedding prefill works, title correct, overflow 0. Sweep 320/390/768/1280/1366x768/1920x1080 = 0 overflow.

Push: main -> both repos, plain.

---
Date: 2026-10-12
Group: big-hall/small-hall latest design + highlights (items 2 + 3)
Branch: main
Status: DONE, pushing.

DONE:
- _build.py: added booking_form(form_id, with_message) helper (shared compact form). Replaced big_hall_main + small_hall_main with: page-hero + numbers block (.hall-panel wine panel with .hall-numbers rows) + Available for (verbatim chips) + Specialities (verbatim list, #i-mic fixed) + booking_form("bh-form"/"sh-form") + cta_band. Big Hall: 4 number rows (1200/400/50/8-featured). Small Hall: 2 rows (350/50-CONFIRM).
- script.js: added HALL_HIGHLIGHTS data (big: 4 points, small: 2 points). In the events reveal, after building reveal-spaces, builds #reveal-highlights: if keys includes big -> Big Hall group (4 rows); if small -> Small Hall group (2 rows). Each row: .hl-label + .hl-num + .hl-cap. SPACE_INFO page links -> extensionless. reveal-cta href -> check-availability?event= (extensionless).
- index.html: added <div id="reveal-highlights"> to the celebrate-reveal panel.
- styles.css: .hl-num (display, 1.2em, semibold, wine; gold on dark via .band-wine/.celebrate-reveal/.hall-panel/.hero). .reveal-highlights/.reveal-hl-group/.reveal-hl-title/.reveal-hl-row + .hl-label/.hl-cap styling (dark-panel variant).
- Applied .hl-num to Specialities capacity numbers: Big Hall "1,200" + "8"; Small Hall "350".

Verification: build 29 pages, _check.py all refs resolve, braces 0, JS OK. big-hall: 4 number rows, featured, 9 specialities, #bh-form (action=/booking.php), 7 chips, CTA, address, overflow 0. small-hall: 2 rows, CONFIRM marker, 8 specialities, #sh-form, overflow 0. Events reveal: Big Hall event -> 4 highlight rows (gold #f2dcae on dark); Small Hall event -> 2 rows. .hl-num in Specialities verified (1,200/8/350). Sweep 320/390/768/1280/1366x768/1920x1080 = 0 overflow.

Push: main -> both repos, plain.

---
Date: 2026-10-15
Group: Resend email setup in admin
Branch: main
Status: verified, ready to publish

What changed:
- Added an Email setup section in the Settings sidebar with Resend API-key entry, sender name/address, reply-to, setup guidance, and a guarded test-email action.
- The Resend API key is encrypted before it is stored in the settings database; the panel never fills it back into the page. Pasting a replacement rotates the stored key.
- Added a shared Resend API sender for new-enquiry alerts and manual email actions. When no key is saved, existing server mail remains active. The test action requires a saved key and sends only after an admin clicks it.
- Updated the tracked server patch copy at server-patch/booking-store.php; backend files are not copied by .cpanel.yml. A Git push alone does not install this PHP patch on the live server.
- No API key was entered and no test email was sent.

Verification:
- python _build.py completed; generated no unrelated working-tree changes.
- python _check.py passed: all 955 local references resolved across 32 HTML pages.
- PHP syntax checks passed for admin/settings.php, admin/index.php, admin/enquiries.php, and server-patch/booking-store.php.
- Dummy-key encryption round-trip passed; no real key or provider request used.
- Token-pattern scan on staged changes found 0 matches.

---
Date: 2026-10-26
Group: Admin modern redesign + WhatsApp Inbox
Branch: claude/magical-edison-c0pjzz
Status: verified locally, pushed to branch (not main). Needs manual upload — see DEPLOY-MANUAL.md (h).

What changed:
- New `admin/theme.css` loaded last on every admin page: light sidebar, Inter/system font, brand-wine accent, soft cards, pill statuses, 2-column stats on mobile. Sidebar uses inline SVG icons and shows the signed-in user.
- WhatsApp Inbox (`admin/inbox.php` + `inbox.js`/`inbox.css`/`inbox-api.php`/`inbox-media.php`): chat list with unread counts and search, chat thread with photos/files/audio, delivery ticks, reply box, 24-hour window notice with template fallback, linked enquiries/bookings by phone, "New enquiry from chat" prefill. Polls every 5–10 s while the tab is open. Unread badge in the sidebar.
- `whatsapp-webhook.php` + `server-patch/whatsapp-store.php`: Meta webhook (verify token handshake, X-Hub-Signature-256 check with the encrypted App Secret), stores messages and delivery statuses; `wa_messages` table auto-created (MySQL + SQLite).
- WhatsApp setup page: App Secret, re-open template + language, generated verify token, callback URL. Its inline `<style>` (blocked by the page CSP) moved into theme.css.
- Fixed broken "·" byte (Latin-1) in the dashboard's latest-enquiries list.

Verification:
- php -l on all admin, server-patch and webhook PHP: clean. node --check inbox.js, enquiries.js: clean.
- Local PHP + SQLite: webhook verify (200/403), signed POST stored, bad signature 403, duplicate message ignored, status updates never downgrade.
- Inbox API: 401 signed out, 403 bad CSRF, 409 closed window, 422 when not connected / no template, search with % and _ safe.
- Screenshots 1440px and 390px for dashboard, enquiries, bookings, inbox, WhatsApp setup, settings: no overflow, no console/CSP errors.
- Not tested against the live Meta API (no credentials in this environment).

---
Date: 2026-10-26
Group: Security limits + lead alerts + Meta Lead Ads + WhatsApp templates
Branch: claude/magical-edison-c0pjzz
Status: verified locally against a mock Graph API; pushed to branch. Needs manual upload — DEPLOY-MANUAL.md (i).

What changed:
- Login lock now in the database (`rate_hits`): 20 failures per IP / 8 per username per 15 min; survives cookie clearing.
- Website enquiries: 5 per hour / 15 per day per IP (honeypot unchanged).
- Settings redirects no longer point at 5 missing pages.
- Lead alerts (`admin/lead-alerts.php`, `server-patch/leads-store.php`): unlimited team members; guest WhatsApp template + email; team WhatsApp template + email; per-message log; test button. Website form responds first, alerts run after (litespeed/fastcgi finish request).
- Meta Lead Ads via the same webhook (`object: page`, `leadgen`): lead fetched with the Page token, mapped to name/phone/email/event/date/guests/hall, extra answers + campaign kept in the message, saved as source "Meta Ads", deduplicated by leadgen_id.
- WhatsApp templates (`admin/wa-templates.php`): ready-made templates, create with example values, sync from Meta, status webhook, resubmit rejected, delete, "use for guest/team/inbox re-open".
- "Meta Ads" source label everywhere; enquiries without a date show "Date not given".
- Fixed server-mail fallback header bug in booking-store.php.

Verification:
- php -l all PHP, node --check all admin JS: clean. No PHP warnings in server log.
- Rate limits: 9th sign-in with fresh cookies blocked; 6th enquiry → 429.
- Website lead: 201 in 0.03 s; guest + team WhatsApp sent via mock; failed number logged; paused member skipped.
- Meta leadgen: signed webhook → lead fetched, enquiry ENQ-000012 saved, alerts sent; replay ignored.
- Templates: sync, invalid body rejected with message and draft kept, create, use-for, resubmit, delete, status webhook → Approved.
- 1440px and 390px screenshots of Lead alerts and WhatsApp templates: no overflow, no console errors.
- Not run against real Meta (no credentials). Email not delivered locally (no mail server); logged as failed as expected.
