# -*- coding: utf-8 -*-
import hashlib
import re
from pathlib import Path

ROOT = Path(__file__).parent
SCRIPT_V = hashlib.md5((ROOT / "script.js").read_bytes()).hexdigest()[:8]
index = (ROOT / "index.html").read_text(encoding="utf-8")

sprite_start = index.index('<svg class="icon-library"')
sprite = index[sprite_start:index.index("</svg>", sprite_start) + 6]
skip_start = index.find('<a class="skip-link"')
skip_link = index[skip_start:index.index("</a>", skip_start) + 4] if skip_start >= 0 else ''
header = index[index.index('<header class="site-header"'):index.index("</header>") + 9]
drawer = index[index.index('<div class="nav-drawer"'):index.index('<main id="main-content">')].rstrip()
footer = index[index.index('<footer class="site-footer"'):index.index("</footer>") + 9]
floats = index[index.index('<div class="float-actions"'):index.index('<nav class="bottom-bar"')].rstrip()
bottom = index[index.index('<nav class="bottom-bar"'):index.index("</nav>", index.index('<nav class="bottom-bar"')) + 6]
lightbox = index[index.index('<div class="lightbox"'):index.index("</body>")].rstrip()
header = header.replace(' aria-current="page"', "")
drawer = drawer.replace(' aria-current="page"', "")

FONT_LINK = (
    '<link rel="preconnect" href="https://fonts.googleapis.com" />\n'
    '  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />\n'
    '  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500&family=Noto+Serif+Devanagari:wght@500;600&display=swap" rel="stylesheet" />'
)

OG_IMAGE = "https://venutaihall.com/assets/hero-hall.jpg"
SITE_NAME = "Late Venutai Chavan Multipurpose Hall"

SEO = {
    "details.html": ("Detailed Overview | Late Venutai Chavan Multipurpose Hall",
                     "The full detail behind the homepage highlights — the five spaces, event types, setups, planning notes, heritage story and venue visit information."),
    "compare.html": ("Compare the Spaces | Late Venutai Chavan Multipurpose Hall",
                     "Big Hall, Small Hall, Dining Hall, VIP Dining and Guest Rooms side by side — capacity, what sets each space apart and the events each suits."),
    "spaces.html": ("Banquet Halls in Nigdi Pradhikaran | Our Spaces",
                    "Compare five air-conditioned spaces in Nigdi Pradhikaran, Pune — Big Hall, Small Hall, Dining Hall, VIP Dining and Guest Rooms for weddings, parties and corporate events."),
    "big-hall.html": ("Big Hall — Up to 1200 Guests | Nigdi Pradhikaran",
                      "Explore the Big Hall at Late Venutai Chavan Multipurpose Hall — a fully air-conditioned banquet hall in Nigdi seating up to 1200 guests for weddings and large events."),
    "small-hall.html": ("Small Hall — Up to 350 Guests | Nigdi",
                        "Explore the Small Hall in Nigdi, Pune — a fully air-conditioned hall for up to 350 guests, ideal for engagements, birthdays, naming ceremonies and family functions."),
    "dining-hall.html": ("Dining Hall — 400 at a Time | Nigdi",
                         "Explore the Dining Hall in Nigdi Pradhikaran — a fully air-conditioned dining room for 400 at a time with catering support for your caterer."),
    "vip-dining.html": ("VIP A/C Dining — 50 at a Time | Nigdi",
                        "Explore VIP A/C Dining in Nigdi, Pune — a private air-conditioned dining room for 50 at a time guests, ideal for close family and honoured guests."),
    "guest-rooms.html": ("A/C Guest Rooms | Nigdi Pradhikaran, Pune",
                         "Explore up to 8 fully air-conditioned guest rooms at our venue in Nigdi, Pune — comfortable stay for family members and invitees."),
    "events.html": ("Events in Nigdi, Pune | Late Venutai Chavan Multipurpose Hall",
                    "Explore weddings, engagements, receptions, birthdays, naming ceremonies, family functions, corporate events and social gatherings at our hall in Nigdi, Pune."),
    "weddings.html": ("Wedding &amp; Marriage Hall in Nigdi Pradhikaran | Late Venutai Chavan",
                      "Explore a wedding and marriage hall in Nigdi Pradhikaran, Pune, with an air-conditioned main hall, dining spaces, guest rooms and parking."),
    "engagements.html": ("Engagement Hall in Nigdi Pradhikaran | Venutai Chavan",
                         "Plan an engagement in Nigdi Pradhikaran, Pune. Explore our AC halls, dining spaces and parking, then ask the team about your date and guest count."),
    "receptions.html": ("Reception Hall in Nigdi | Late Venutai Chavan",
                        "Plan a wedding reception at our hall in Nigdi Pradhikaran, Pune. Explore seating, stage, sound and dining facilities, then enquire about your date."),
    "birthdays.html": ("Birthday Party Hall in Nigdi | Late Venutai Chavan",
                       "Looking for a birthday party hall in Nigdi, Pune? Compare our air-conditioned Big Hall and Small Hall, dining spaces and facilities for your celebration."),
    "naming-ceremonies.html": ("Naming Ceremony Hall in Nigdi | Late Venutai Chavan",
                               "Plan a naming ceremony in Nigdi Pradhikaran, Pune. Explore our air-conditioned Small Hall, dining areas, guest rooms and parking for family guests."),
    "family-functions.html": ("Family Function Hall in Nigdi | Late Venutai Chavan",
                              "Explore AC halls and dining spaces for a family function in Nigdi, Pune. Share your guest count and plans with the venue team."),
    "corporate-events.html": ("Corporate Event Venue in Nigdi | Late Venutai Chavan",
                              "Explore a corporate event venue in Nigdi, Pune, with air-conditioned halls, projector and screen, sound system, Wi-Fi, dining areas and parking."),
    "social-gatherings.html": ("Social Gathering Venue in Nigdi | Late Venutai Chavan",
                               "Plan a social gathering in Nigdi, Pune. Explore the hall, dining spaces and facilities, then enquire about your date and setup."),
    "facilities.html": ("AC Hall &amp; Facilities in Nigdi | Late Venutai Chavan",
                        "Explore AC banquet hall facilities in Nigdi Pradhikaran, Pune, including dining spaces, catering support, parking, guest rooms and audiovisual equipment."),
    "gallery.html": ("Gallery | Late Venutai Chavan Multipurpose Hall",
                     "View halls, dining spaces, guest rooms, event setups and facilities at Late Venutai Chavan Multipurpose Hall, Nigdi, Pune."),
    "about.html": ("About | Late Venutai Chavan Multipurpose Hall",
                   "Learn about Late Venutai Chavan Multipurpose Hall, its inspiration, community purpose, and commitment to memorable events in Nigdi, Pune."),
    "contact.html": ("Contact | Late Venutai Chavan Multipurpose Hall",
                     "Contact Late Venutai Chavan Multipurpose Hall in Nigdi, Pune. Plan a venue visit or send a booking enquiry."),
    "visit.html": ("Venue Visit | Late Venutai Chavan Multipurpose Hall",
                   "Schedule a venue visit at Late Venutai Chavan Multipurpose Hall, Nigdi, Pune. See the halls, dining spaces and guest rooms in person."),
    "check-availability.html": ("Check Availability | Late Venutai Chavan Multipurpose Hall",
                                "Check date availability at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune. Share your event type, guest count and preferred date — this is an enquiry, not a booking."),
    "privacy-policy.html": ("Privacy Policy | Late Venutai Chavan Multipurpose Hall",
                            "How Late Venutai Chavan Multipurpose Hall collects and handles information submitted through this website."),
    "terms.html": ("Terms &amp; Conditions | Late Venutai Chavan Multipurpose Hall",
                   "Terms and conditions for using venutaihall.com. Enquiries are not bookings; dates are confirmed only after venue confirmation."),
    "everything-included.html": ("Everything Included | Late Venutai Chavan Multipurpose Hall",
                                 "Everything included with the Big Hall and Small Hall packages at Late Venutai Chavan Multipurpose Hall."),
}


def page(name, main, body_class="", extra_head=""):
    title, desc = SEO[name]
    slug = "" if name == "index.html" else name.removesuffix(".html")
    canon = f"https://venutaihall.com/{slug}"
    og_title = title
    cls = f' class="{body_class}"' if body_class else ""
    head_extra = f"  {extra_head}\n" if extra_head else ""
    html = f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#f6f1e8" />
  <meta name="description" content="{desc}" />
  <link rel="canonical" href="{canon}" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="{SITE_NAME}" />
  <meta property="og:title" content="{og_title}" />
  <meta property="og:description" content="{desc}" />
  <meta property="og:url" content="{canon}" />
  <meta property="og:image" content="{OG_IMAGE}" />
  <meta property="og:image:alt" content="Main hall of Late Venutai Chavan Multipurpose Hall with chandeliers and banquet seating" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="{og_title}" />
  <meta name="twitter:description" content="{desc}" />
  <meta name="twitter:image" content="{OG_IMAGE}" />
  <title>{title}</title>
  {FONT_LINK}
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="/styles.css" />
  <script src="/script.js?v={SCRIPT_V}" defer></script>
{head_extra}</head>
<body{cls}>
  {skip_link}
  {sprite}
  {header}
  {drawer}
  <main id="main-content">
{main}
  </main>
  {footer}
  {floats}
  {bottom}
  {lightbox}
</body>
</html>
"""
    (ROOT / name).write_text(html, encoding="utf-8")
    print("wrote", name)


def breadcrumb(*parts, in_hero=True):
    segs = ['<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span class="sep" aria-hidden="true">/</span>']
    for part in parts:
        label, href = part if isinstance(part, tuple) else (part, None)
        if href:
            segs.append(f'<a href="{href}">{label}</a><span class="sep" aria-hidden="true">/</span>')
        else:
            segs.append(f'<span aria-current="page">{label}</span>')
    segs.append("</nav>")
    inner = "".join(segs)
    if in_hero:
        return inner
    return f'<div class="shell">{inner}</div>'


def cta_band(title_html):
    return f"""
    <section class="cta-band" aria-labelledby="page-cta-title">
      <div class="shell cta-band-inner">
        <div class="reveal">
          <p class="eyebrow">Check availability</p>
          <h2 id="page-cta-title">{title_html}</h2>
          <p>Tell us what you're planning — event type, guest count and preferred date. Our team will contact you with availability and next steps.</p>
          <p class="cta-note">This is an enquiry. Your date is confirmed only after venue confirmation.</p>
        </div>
        <div class="cta-band-actions reveal reveal-delay-1">
          <a class="button button-light button-lg" href="check-availability">Check Availability <svg class="icon" aria-hidden="true"><use href="#i-arrow" /></svg></a>
          <a class="button button-ghost-light" href="tel:+919359567494" data-call-picker><svg class="icon" aria-hidden="true"><use href="#i-phone" /></svg> Talk to the Team</a>
        </div>
      </div>
    </section>"""


def icon(name, cls="icon"):
    return f'<svg class="{cls}" aria-hidden="true"><use href="#{name}" /></svg>'


def check_item(text):
    return f'<li>{icon("i-check")}{text}</li>'


def arrow_link(href, label):
    return f'<a href="{href}">{label} {icon("i-arrow")}</a>'


DIM = {
    "hero-hall.jpg": (1672, 941),
    "small-hall.jpg": (1536, 1024),
    "dining-hall.jpg": (1536, 1024),
    "vip-dining.jpg": (1536, 1024),
    "guest-room.jpg": (1536, 1024),
    "venue-exterior.jpg": (1916, 821),
    "contact-map.jpg": (673, 165),
    "about-portrait.jpg": (201, 236),
    "contact-hero.jpg": (589, 350),
    "gallery-reference.jpg": (1312, 1199),
    "facilities-reference.jpg": (1024, 1536),
    "about-venue.jpg": (325, 159),
    "about-flowers.jpg": (800, 600),
}


def pic(img, alt, *, lazy=True, priority=False):
    w, h = DIM.get(img, (1600, 900))
    base = img.rsplit(".", 1)[0]
    load = ' fetchpriority="high"' if priority else (' loading="lazy"' if lazy else "")
    return (
        f'<picture><source srcset="/assets/{base}.webp" type="image/webp" />'
        f'<img src="/assets/{img}" alt="{alt}" width="{w}" height="{h}"{load} /></picture>'
    )


def plain_tile(cat, img, caption, label, sub, alt):
    return (
        f'<button type="button" class="gallery-tile" data-gallery-item data-category="{cat}" '
        f'data-image="assets/{img}" data-caption="{caption}" data-label="{label}">'
        f'{pic(img, alt)}'
        f'<span class="gallery-tile-overlay"><strong>{label}<small>{sub}</small></strong>{icon("i-search")}</span></button>'
    )


def crop_tile(cat, caption, x, y, w, h, label=None):
    src = "assets/gallery-reference.jpg"
    style = f"--ref-src:url('{src}');--crop-x:{x};--crop-y:{y};--crop-w:{w};--crop-h:{h};"
    data_crop = f"--crop-x:{x};--crop-y:{y};--crop-w:{w};--crop-h:{h};"
    lab = label or caption
    return (
        f'<button type="button" class="gallery-tile" data-gallery-item data-category="{cat}" '
        f'data-image="{src}" data-caption="{caption}" data-label="{lab}" data-crop="{data_crop}">'
        f'<span class="reference-photo" style="{style}" role="img" aria-label="{caption}"></span>'
        f'<span class="gallery-tile-overlay"><strong>{lab}<small>Venue photo</small></strong>{icon("i-search")}</span></button>'
    )
EVENT_SLUGS = [
    ("weddings.html", "Weddings"),
    ("engagements.html", "Engagements"),
    ("receptions.html", "Receptions"),
    ("birthdays.html", "Birthdays"),
    ("naming-ceremonies.html", "Naming Ceremonies"),
    ("family-functions.html", "Family Functions"),
    ("corporate-events.html", "Corporate Events"),
    ("social-gatherings.html", "Social Gatherings"),
]

EVENT_CARDS = [
    ("weddings", "Weddings", "hero-hall.jpg", "Banquet seating in the Big Hall for weddings", "A welcoming setting for a wedding day shared with family and friends."),
    ("engagements", "Engagements", "small-hall.jpg", "Seating in the Small Hall for engagement ceremonies", "Bring both families together for a ring ceremony and celebration."),
    ("receptions", "Receptions", "hero-hall.jpg", "Main hall ready for a reception evening", "Room for a reception welcome, stage moments and a comfortable guest flow."),
    ("birthdays", "Birthdays", "small-hall.jpg", "Flexible hall space for birthday parties", "Celebrate a birthday in a hall that fits your guest list and plans."),
    ("naming-ceremonies", "Naming Ceremonies", "vip-dining.jpg", "VIP dining room for close family gatherings", "A calm, comfortable place to gather close family for a special milestone."),
    ("family-functions", "Family Functions", "dining-hall.jpg", "Dining hall set with round tables for family functions", "Flexible spaces for family milestones, gatherings and shared meals."),
    ("corporate-events", "Corporate Events", "hero-hall.jpg", "Spacious hall for corporate events and annual functions", "A practical setting for presentations, meetings and company gatherings."),
    ("social-gatherings", "Social Gatherings", "dining-hall.jpg", "Dining hall for community and social gatherings", "A place to reconnect with your community, friends and guests."),
]

EVENTS = {
    "weddings.html": dict(
        crumb="Weddings", hero="hero-hall.jpg", hero_alt="Air-conditioned venue space for weddings",
        eyebrow="Weddings at our venue", h1="Wedding hall in<br /><em>Nigdi, Pune</em>",
        lead="A welcoming setting for a wedding day shared with family and friends.",
        cta="Enquire About Your Wedding", query="Wedding",
        intro_h2="Plan the ceremony, guest seating and meal service around one connected venue.",
        intro_body="Explore our wedding venue in Nigdi Pradhikaran for a celebration with family and friends. The air-conditioned Big Hall suits larger groups, while the dining hall, VIP dining area and guest rooms give you options to discuss with the team.",
        fit_id="weddings-spaces", fit_h2="Plan your wedding <em>around your guests</em>",
        cards=[("big-hall", "hero-hall.jpg", "Big Hall at the venue", "Big Hall", "Use the larger hall for your guest seating and main celebration."),
               ("dining-hall", "dining-hall.jpg", "Dining spaces at the venue", "Dining spaces", "Discuss meal service in the Dining Hall or VIP A/C Dining area."),
               ("guest-rooms", "guest-room.jpg", "Guest rooms at the venue", "Guest rooms", "Ask about the air-conditioned rooms for family members or guests.")],
        plan="A few details help the team discuss a suitable arrangement for your wedding.",
        items=["Estimated guest count and seating layout", "Ceremony and reception timing", "Dining needs for guests and family", "Guest-room availability"],
        faq_h2="Planning a <em>wedding</em>?",
        faqs=[("Can the wedding and dining use separate spaces?", "The site lists a Big Hall and separate dining spaces. Share your schedule with the team to confirm a suitable arrangement."),
              ("Is there parking for guests?", "The venue lists an on-site parking area. Ask the team about the expected vehicle count for your date.")],
        final="Let’s plan your wedding",
    ),
    "engagements.html": dict(
        crumb="Engagements", hero="small-hall.jpg", hero_alt="Air-conditioned venue space for engagements",
        eyebrow="Engagements at our venue", h1="Engagement hall in<br /><em>Nigdi Pradhikaran</em>",
        lead="Bring both families together for a ring ceremony and celebration.",
        cta="Enquire About Your Engagement", query="Engagement",
        intro_h2="Plan the ring ceremony, family photos and a shared meal in spaces that suit your guests.",
        intro_body="The air-conditioned Small Hall is listed for engagements and mid-sized gatherings, while the Big Hall can suit a larger guest list. Discuss your ceremony setup, seating and dining schedule with the venue team before booking.",
        fit_id="engagements-spaces", fit_h2="Plan your engagement <em>around your guests</em>",
        cards=[("small-hall", "small-hall.jpg", "Small Hall at the venue", "Small Hall", "Gather family and friends for the ceremony in the mid-sized hall."),
               ("big-hall", "hero-hall.jpg", "Big Hall at the venue", "Big Hall", "Explore a larger seating arrangement if more guests will attend."),
               ("dining-hall", "dining-hall.jpg", "Dining spaces at the venue", "Dining spaces", "Ask about a separate dining arrangement after the ring ceremony.")],
        plan="A few details help the team discuss a suitable arrangement for your engagement.",
        items=["Ceremony timing and guest count", "Ring exchange and photo area", "Seating for both families", "Refreshment or meal schedule"],
        faq_h2="Planning an <em>engagement</em>?",
        faqs=[("Which hall can host an engagement?", "The Small Hall is listed for engagements; the Big Hall is also available for larger groups. Share your guest count to discuss the right arrangement."),
              ("Can guests dine after the ceremony?", "The venue lists a Dining Hall and VIP A/C Dining area. Confirm availability and meal arrangements with the team.")],
        final="Let’s plan your engagement",
    ),
    "receptions.html": dict(
        crumb="Receptions", hero="hero-hall.jpg", hero_alt="Air-conditioned venue space for receptions",
        eyebrow="Receptions at our venue", h1="Reception hall in<br /><em>Nigdi, Pune</em>",
        lead="Room for a reception welcome, stage moments and a comfortable guest flow.",
        cta="Enquire About Your Reception", query="Reception",
        intro_h2="Bring guests together for an evening reception with space to meet, celebrate and dine.",
        intro_body="The main hall offers generous seating beneath chandeliers. The site also lists a projector and LED screen, acoustic system and catering support. Tell the team which stage, sound and dining arrangements you need before booking.",
        fit_id="receptions-spaces", fit_h2="Plan your reception <em>around your guests</em>",
        cards=[("big-hall", "hero-hall.jpg", "Main hall at the venue", "Main hall", "Plan the seating and welcome flow around the spacious Big Hall."),
               ("facilities", "hero-hall.jpg", "Stage and visuals at the venue", "Stage and visuals", "Discuss the available screen, projector and sound setup for your program."),
               ("dining-hall", "dining-hall.jpg", "Dining hall at the venue", "Dining hall", "Arrange a separate meal flow for guests with the venue team.")],
        plan="A few details help the team discuss a suitable arrangement for your reception.",
        items=["Arrival and welcome sequence", "Stage program and presentation needs", "Guest seating and movement", "Dinner timing and catering needs"],
        faq_h2="Planning a <em>reception</em>?",
        faqs=[("Can we use a screen during the reception?", "The facilities page lists an LED screen and projector. Confirm the setup, media format and availability with the team."),
              ("Is there a separate dining area?", "The venue lists a Dining Hall and VIP A/C Dining area. Ask the team which spaces suit your guest count.")],
        final="Let’s plan your reception",
    ),
    "birthdays.html": dict(
        crumb="Birthdays", hero="small-hall.jpg", hero_alt="Air-conditioned venue space for birthdays",
        eyebrow="Birthdays at our venue", h1="Birthday party hall<br /><em>in Nigdi, Pune</em>",
        lead="Celebrate a birthday in a hall that fits your guest list and plans.",
        cta="Enquire About Your Birthday Party", query="Birthday",
        intro_h2="A birthday may be a close family gathering or a larger celebration with friends.",
        intro_body="The Small Hall is presented for mid-sized functions, while the Big Hall serves larger groups. Discuss your guest count, decoration setup, music needs and meal plan with the venue team to choose the right space.",
        fit_id="birthdays-spaces", fit_h2="Plan your birthday party <em>around your guests</em>",
        cards=[("small-hall", "small-hall.jpg", "Small Hall at the venue", "Small Hall", "A comfortable option for a mid-sized birthday gathering."),
               ("big-hall", "hero-hall.jpg", "Big Hall at the venue", "Big Hall", "Consider the larger hall when your guest list grows."),
               ("dining-hall", "dining-hall.jpg", "Dining spaces at the venue", "Dining spaces", "Plan how refreshments or a meal will fit the celebration.")],
        plan="A few details help the team discuss a suitable arrangement for your birthday party.",
        items=["Guest count and hall size", "Cake and decoration setup", "Music or presentation needs", "Refreshment or meal timing"],
        faq_h2="Planning a <em>birthday party</em>?",
        faqs=[("Which hall suits a birthday party?", "Start with your guest count. The Small Hall suits mid-sized functions; the Big Hall is available for larger gatherings."),
              ("Can we plan food for guests?", "The venue lists catering support and dining spaces. Ask the team about the arrangement available on your date.")],
        final="Let’s plan your birthday party",
    ),
    "naming-ceremonies.html": dict(
        crumb="Naming Ceremonies", hero="vip-dining.jpg", hero_alt="Air-conditioned venue space for naming ceremonies",
        eyebrow="Naming ceremonies at our venue", h1="Naming ceremony hall<br /><em>in Nigdi, Pune</em>",
        lead="A calm, comfortable place to gather close family for a special milestone.",
        cta="Enquire About Your Naming Ceremony", query="Naming Ceremony",
        intro_h2="Plan a welcoming celebration for the child, family and invited guests.",
        intro_body="The Small Hall provides a mid-sized, air-conditioned setting. The venue also lists dining areas, guest rooms, lifts and parking. Discuss a quiet family area and a comfortable program flow with the team.",
        fit_id="naming-ceremonies-spaces", fit_h2="Plan your naming ceremony <em>around your guests</em>",
        cards=[("small-hall", "small-hall.jpg", "Small Hall at the venue", "Small Hall", "Bring family together in a hall sized for a more intimate function."),
               ("guest-rooms", "guest-room.jpg", "Guest rooms at the venue", "Guest rooms", "Ask about a room if family members need a separate place during the visit."),
               ("vip-dining", "vip-dining.jpg", "Dining at the venue", "Dining", "Plan a convenient meal after the ceremony.")],
        plan="A few details help the team discuss a suitable arrangement for your naming ceremony.",
        items=["Ceremony timing and family seating", "Space for the child and close family", "Guest access and lift use", "Meal arrangements after the ceremony"],
        faq_h2="Planning a <em>naming ceremony</em>?",
        faqs=[("Is the venue air-conditioned?", "The Small Hall and other indoor spaces are listed as air-conditioned."),
              ("Are guest rooms available?", "The site lists A/C guest rooms. Confirm availability and any room conditions with the team.")],
        final="Let’s plan your naming ceremony",
    ),
    "family-functions.html": dict(
        crumb="Family Functions", hero="dining-hall.jpg", hero_alt="Air-conditioned venue space for family functions",
        eyebrow="Family functions at our venue", h1="Family function hall<br /><em>in Nigdi, Pune</em>",
        lead="Flexible spaces for family milestones, gatherings and shared meals.",
        cta="Enquire About Your Family Function", query="Family Function",
        intro_h2="Every family gathering has a different size and pace.",
        intro_body="Choose between the Big Hall and Small Hall based on your guest list, then discuss dining, accessibility and the schedule with the venue team. The site lists lifts, parking and air-conditioned spaces for guest comfort.",
        fit_id="family-functions-spaces", fit_h2="Plan your family function <em>around your guests</em>",
        cards=[("big-hall", "hero-hall.jpg", "Two hall sizes at the venue", "Two hall sizes", "Compare the Big Hall and Small Hall for your expected group."),
               ("dining-hall", "dining-hall.jpg", "Dining areas at the venue", "Dining areas", "Keep the meal flow convenient for guests of different ages."),
               ("facilities", "venue-exterior.jpg", "Guest access at the venue", "Guest access", "Ask about lift access, parking and arrival arrangements.")],
        plan="A few details help the team discuss a suitable arrangement for your family function.",
        items=["Type of family occasion and program", "Guest count across age groups", "Seating and accessibility needs", "Meal service and visit timing"],
        faq_h2="Planning a <em>family function</em>?",
        faqs=[("Can we choose a hall based on group size?", "Yes. The site lists both a Big Hall and a Small Hall. The team can help match the layout to your guest count."),
              ("Does the venue have lift access?", "Lift access is listed on the facilities page. Discuss any specific accessibility needs before your visit.")],
        final="Let’s plan your family function",
    ),
    "corporate-events.html": dict(
        crumb="Corporate Events", hero="hero-hall.jpg", hero_alt="Air-conditioned venue space for corporate events",
        eyebrow="Corporate events at our venue", h1="Corporate event venue<br /><em>in Nigdi, Pune</em>",
        lead="A practical setting for presentations, meetings and company gatherings.",
        cta="Enquire About Your Corporate Event", query="Corporate Event",
        intro_h2="Plan your program around the audience, presentation setup and breaks.",
        intro_body="The venue lists two air-conditioned halls, LED screen and projector, an acoustic system, Wi-Fi, dining areas and parking. Confirm technical specifications and availability with the team before finalizing a company event.",
        fit_id="corporate-events-spaces", fit_h2="Plan your corporate event <em>around your guests</em>",
        cards=[("small-hall", "small-hall.jpg", "Hall choice at the venue", "Hall choice", "Match the audience size to the Big Hall or Small Hall."),
               ("facilities", "hero-hall.jpg", "Presentations at the venue", "Presentations", "Discuss projector, screen, audio and Wi-Fi needs in advance."),
               ("dining-hall", "dining-hall.jpg", "Breaks and dining at the venue", "Breaks and dining", "Plan refreshments and meals around the event agenda.")],
        plan="A few details help the team discuss a suitable arrangement for your corporate event.",
        items=["Audience count and seating format", "Presentation and microphone requirements", "Wi-Fi and equipment requirements", "Registration, breaks and dining schedule"],
        faq_h2="Planning a <em>corporate event</em>?",
        faqs=[("Is presentation equipment listed?", "Yes. The facilities page lists an LED screen, projector and acoustic system. Confirm specifications and availability for your date."),
              ("Is Wi-Fi available?", "Wi-Fi connectivity is listed. Ask the team about coverage and capacity for your attendees.")],
        final="Let’s plan your corporate event",
    ),
    "social-gatherings.html": dict(
        crumb="Social Gatherings", hero="dining-hall.jpg", hero_alt="Air-conditioned venue space for social gatherings",
        eyebrow="Social gatherings at our venue", h1="Social gathering venue<br /><em>in Nigdi, Pune</em>",
        lead="A place to reconnect with your community, friends and guests.",
        cta="Enquire About Your Social Gathering", query="Social Gathering",
        intro_h2="Make room for conversation, a shared program and an easy meal flow.",
        intro_body="The venue has a Big Hall, Small Hall and dining spaces, along with parking and air-conditioned interiors. Tell the team whether your gathering is formal, seated or more flexible so they can discuss a suitable arrangement.",
        fit_id="social-gatherings-spaces", fit_h2="Plan your social gathering <em>around your guests</em>",
        cards=[("big-hall", "hero-hall.jpg", "Gathering space at the venue", "Gathering space", "Select a hall that gives your guests room to meet and take part."),
               ("dining-hall", "dining-hall.jpg", "Shared meal at the venue", "Shared meal", "Discuss dining options that suit the timing of your gathering."),
               ("facilities", "venue-exterior.jpg", "Easy arrival at the venue", "Easy arrival", "Use the listed parking and lift access when planning guest arrival.")],
        plan="A few details help the team discuss a suitable arrangement for your social gathering.",
        items=["Number of guests and seating style", "Program or speaker schedule", "Refreshments and dining", "Parking and accessibility needs"],
        faq_h2="Planning a <em>social gathering</em>?",
        faqs=[("Can the space fit different group sizes?", "The venue lists a Big Hall and Small Hall. Share your guest count to discuss a suitable space."),
              ("Where is the venue?", "It is listed at Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune 411044. The Contact page has directions.")],
        final="Let’s plan your social gathering",
    ),
}

SPACES = {
    "big-hall.html": dict(
        name="Big Hall", crumb="Big Hall", hero="hero-hall.jpg",
        hero_alt="Big Hall with chandeliers and rows of seats",
        eyebrow="Main hall", h1="Big Hall for<br /><em>grand celebrations</em>",
        lede="The largest space in the venue: a full-size banquet hall for weddings, receptions and large community events, with room for a stage, seating and procession flow.",
        specs=[("Capacity", "Up to 1200 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Weddings · Large events"), ("Pricing", "Enquire for pricing")],
        chips=[("Up to 1200 guests", "i-people"), ("Full AC", "i-ac"), ("Stage-ready", "i-star"), ("On-site parking", "i-car")],
        overview_h2="A full-size banquet hall for the days that matter most",
        overview=[
            "The Big Hall is the venue's main space: chandeliers, banquet seating and enough depth for a stage, a procession and a comfortable guest flow.",
            "Use it for weddings, receptions, large family functions, corporate gatherings and community events. Share your guest count and program with the team to discuss the layout for your date.",
        ],
        capacity=[("Capacity", "Up to 1200 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Seating", "Flexible banquet seating"), ("Pricing", "Enquire for pricing")],
        best_for=["Weddings and receptions", "Large family functions", "Corporate annual functions", "Community and social gatherings"],
        dining=[
            "A separate Dining Hall (up to 400) and VIP A/C Dining (up to 50) keep the meal out of the main programme.",
            "Catering support is listed so your caterer can work on site. Menu and pricing are handled with your caterer — contact the venue team for current arrangements.",
        ],
        stay=[
            "Up to 8 air-conditioned guest rooms are available for family members and invitees staying over.",
            "Ask about room allocation when you enquire about your date.",
        ],
        faqs=[("How many guests can the Big Hall seat?", "The Big Hall is listed for up to 1200 guests. Final layout depends on your programme — share your guest count with the team."),
              ("Is there a stage area?", "The hall is listed as suitable for stage setups. Confirm your stage and AV requirements with the team."),
              ("Is parking available?", "On-site parking is listed. Ask the team about vehicle arrangements for your date.")],
        gallery=[("big-hall", "hero-hall.jpg", "Big Hall — banquet seating", "Big Hall", "Up to 1200 guests")],
        related=[("small-hall", "Small Hall"), ("dining-hall", "Dining Hall"), ("guest-rooms", "Guest Rooms"), ("spaces", "All spaces")],
        final="Let’s plan your event in the Big Hall",
    ),
    "small-hall.html": dict(
        name="Small Hall", crumb="Small Hall", hero="small-hall.jpg",
        hero_alt="Small Hall seating with centre aisle",
        eyebrow="Celebration hall", h1="Small Hall for<br /><em>mid-sized functions</em>",
        lede="A mid-sized hall that suits engagements, birthdays, naming ceremonies and family functions — big enough for a stage, close enough to feel personal.",
        specs=[("Capacity", "Up to 350 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Engagements · Birthdays"), ("Pricing", "Enquire for pricing")],
        chips=[("Up to 350 guests", "i-people"), ("Full AC", "i-ac"), ("Centre aisle", "i-star"), ("Lift access", "i-lift")],
        overview_h2="A comfortable hall for celebrations that stay close",
        overview=[
            "The Small Hall seats up to 350 guests in an air-conditioned room with flexible seating and a centre aisle for processions.",
            "It is listed for engagements, birthdays, naming ceremonies and family functions. Discuss your layout, stage needs and meal timing with the team.",
        ],
        capacity=[("Capacity", "Up to 350 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Seating", "Flexible seating with aisle"), ("Pricing", "Enquire for pricing")],
        best_for=["Engagements", "Birthdays", "Naming ceremonies", "Family functions"],
        dining=[
            "Pair the hall with the Dining Hall or VIP A/C Dining for a separate meal room.",
            "Catering support is listed. Confirm meal arrangements for your date with the team.",
        ],
        stay=[
            "Guest rooms are available if family members need a place to rest during the event.",
            "Ask about room availability when you enquire.",
        ],
        faqs=[("How many guests can the Small Hall seat?", "The Small Hall is listed for up to 350 guests. Share your guest count to discuss the right layout."),
              ("Can we have a stage?", "The hall is listed as suitable for mid-sized programmes with stage needs. Confirm requirements with the team."),
              ("Is the hall air-conditioned?", "Yes. The Small Hall is listed as fully air-conditioned.")],
        gallery=[("small-hall", "small-hall.jpg", "Small Hall — celebration seating", "Small Hall", "Up to 350 guests")],
        related=[("big-hall", "Big Hall"), ("dining-hall", "Dining Hall"), ("vip-dining", "VIP Dining"), ("spaces", "All spaces")],
        final="Let’s plan your event in the Small Hall",
    ),
    "dining-hall.html": dict(
        name="Dining Hall", crumb="Dining Hall", hero="dining-hall.jpg",
        hero_alt="Dining hall with round tables",
        eyebrow="Dining", h1="Dining Hall for<br /><em>shared meals</em>",
        lede="A separate air-conditioned room for the meal — round-table seating for 400 at a time, with catering support so service stays out of the main programme.",
        specs=[("Capacity", "400 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Meals · Receptions dining"), ("Pricing", "Enquire for pricing")],
        chips=[("400 at a time", "i-people"), ("Full AC", "i-ac"), ("Round tables", "i-dining"), ("Catering support", "i-tray")],
        overview_h2="Give the meal its own room",
        overview=[
            "The Dining Hall is a dedicated air-conditioned space with round-table seating for 400 at a time.",
            "Keeping dining separate means the stage programme and the meal do not compete for the same room. Discuss service flow and timing with the venue team.",
        ],
        capacity=[("Capacity", "400 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Layout", "Round-table seating"), ("Pricing", "Enquire for pricing")],
        best_for=["Wedding meals", "Reception dining", "Family function dining", "Community gatherings"],
        dining=[
            "Catering support is listed so your caterer can work on site.",
            "Menu and pricing are handled with your caterer; contact the venue team for current arrangements.",
        ],
        stay=[
            "Guest rooms are available on site for family staying over — ask when you enquire.",
        ],
        faqs=[("How many guests can the Dining Hall seat?", "The Dining Hall is listed for 400 at a time."),
              ("Do you provide catering?", "Catering support is listed. Menu and pricing are handled with your caterer — contact the venue team for current arrangements."),
              ("Is VIP dining also available?", "Yes — VIP A/C Dining is listed for 50 at a time guests for close family and honoured guests.")],
        gallery=[("dining", "dining-hall.jpg", "Dining Hall — round-table seating", "Dining Hall", "400 at a time")],
        related=[("vip-dining", "VIP Dining"), ("big-hall", "Big Hall"), ("small-hall", "Small Hall"), ("spaces", "All spaces")],
        final="Let’s plan dining for your event",
    ),
    "vip-dining.html": dict(
        name="VIP A/C Dining", crumb="VIP Dining", hero="vip-dining.jpg",
        hero_alt="VIP dining room with gold tablecloths",
        eyebrow="Private dining", h1="VIP A/C Dining for<br /><em>close family</em>",
        lede="A private dining room for close family, elders and honoured guests — quieter than the main hall, finished to the same standard.",
        specs=[("Capacity", "50 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Close family · VIP dining"), ("Pricing", "Enquire for pricing")],
        chips=[("50 at a time", "i-people"), ("Full AC", "i-ac"), ("Private room", "i-shield"), ("Near main hall", "i-building")],
        overview_h2="A quieter room for the people closest to the occasion",
        overview=[
            "VIP A/C Dining is a private air-conditioned room for 50 at a time guests — suited to close family, elders and honoured guests.",
            "Ask the team how this room can work alongside the Dining Hall and main programme on your date.",
        ],
        capacity=[("Capacity", "50 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Layout", "Private dining room"), ("Pricing", "Enquire for pricing")],
        best_for=["Close family meals", "Elders and honoured guests", "Naming ceremonies", "Small private dinners"],
        dining=[
            "Pair with the Dining Hall for larger groups while keeping a private table for close family.",
            "Catering support is listed; confirm arrangements with the team.",
        ],
        stay=[
            "Guest rooms are available if family needs to rest between functions — ask when you enquire.",
        ],
        faqs=[("How many guests can VIP Dining seat?", "VIP A/C Dining is listed for 50 at a time guests."),
              ("Is it air-conditioned?", "Yes. The room is listed as fully air-conditioned."),
              ("Can we use it with the Big Hall?", "Discuss a combined arrangement with the team when you plan your event.")],
        gallery=[("dining", "vip-dining.jpg", "VIP A/C Dining — private dining room", "VIP Dining", "50 at a time")],
        related=[("dining-hall", "Dining Hall"), ("big-hall", "Big Hall"), ("guest-rooms", "Guest Rooms"), ("spaces", "All spaces")],
        final="Let’s plan dining for your guests",
    ),
    "guest-rooms.html": dict(
        name="A/C Guest Rooms", crumb="Guest Rooms", hero="guest-room.jpg",
        hero_alt="Guest bedroom with large bed",
        eyebrow="Stay", h1="A/C Guest Rooms for<br /><em>overnight family</em>",
        lede="Up to 8 comfortable, air-conditioned bedrooms so out-of-town family can stay at the venue instead of travelling between function and hotel.",
        specs=[("Rooms", "Up to 8 bedrooms"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Family stay · Invitees"), ("Pricing", "Enquire for pricing")],
        chips=[("8 rooms", "i-bed"), ("Full AC", "i-ac"), ("Family stay", "i-people"), ("On site", "i-building")],
        overview_h2="Keep family close to the celebration",
        overview=[
            "Up to 8 well-furnished, air-conditioned bedrooms are listed for family members and invitees staying over.",
            "Ask about room allocation, availability for your dates and any conditions when you enquire.",
        ],
        capacity=[("Rooms", "Up to 8 bedrooms"), ("Air-conditioning", "Fully air-conditioned"), ("Use", "Family and invitee stay"), ("Pricing", "Enquire for pricing")],
        best_for=["Out-of-town family", "Invitees staying overnight", "Multi-day functions", "Elders who need rest"],
        dining=[
            "Guests staying over can use the Dining Hall or VIP A/C Dining for meals — discuss meal timing with the team.",
        ],
        stay=[
            "Rooms are on site, so family does not need to travel between the function and a hotel.",
            "Confirm availability for your dates when you enquire.",
        ],
        faqs=[("How many guest rooms are there?", "The site lists up to 8 air-conditioned guest rooms."),
              ("Are the rooms air-conditioned?", "Yes. The guest rooms are listed as fully air-conditioned."),
              ("How do I book a room with the hall?", "Mention room needs in your enquiry. The team will confirm availability for your dates.")],
        gallery=[("guest-rooms", "guest-room.jpg", "A/C Guest Room", "Guest Rooms", "Up to 8 rooms")],
        related=[("big-hall", "Big Hall"), ("small-hall", "Small Hall"), ("vip-dining", "VIP Dining"), ("spaces", "All spaces")],
        final="Let’s plan a stay for your guests",
    ),
}


def related_event_links(current):
    out = []
    for slug, label in EVENT_SLUGS:
        if slug == current:
            continue
        out.append(arrow_link(slug, label))
    return "\n            ".join(out)


def fit_card(href, img, alt, title, desc):
    return (
        f'<article class="fit-card reveal"><a class="fit-card-media" href="{href}">'
        f'{pic(img, alt)}<span class="arrow-badge">{icon("i-arrow")}</span></a>'
        f'<div class="fit-card-body"><h3>{title}</h3><p>{desc}</p></div></article>'
    )


def faq_item(q, a, open_=False):
    op = " open" if open_ else ""
    return (
        f'<details class="faq-item"{op}><summary>{q} '
        f'<span class="faq-icon">{icon("i-plus", "icon icon-sm")}</span></summary>'
        f'<div class="faq-answer"><p>{a}</p></div></details>'
    )


def event_main(slug, e):
    cards = "\n            ".join(fit_card(*c) for c in e["cards"])
    plan_items = "\n            ".join(check_item(i) for i in e["items"])
    faqs = "\n            ".join(faq_item(q, a) for q, a in e["faqs"])
    crumb = breadcrumb(("Events", "events.html"), e["crumb"])
    return f"""
      <section class="page-hero" aria-labelledby="event-title">
        <div class="page-hero-media">{pic(e["hero"], e["hero_alt"], priority=True)}</div>
        <div class="page-hero-inner shell">
          {crumb}
          <p class="eyebrow">{e["eyebrow"]}</p>
          <h1 id="event-title">{e["h1"]}</h1>
          <p class="lede">{e["lead"]}</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability?event={e["query"]}">{e["cta"]} {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Talk to the Team</a>
          </div>
        </div>
      </section>

      <section class="section-tight">
        <div class="shell event-intro">
          <div class="reveal">
            <p class="eyebrow">Your occasion</p>
            <h2>{e["intro_h2"]}</h2>
          </div>
          <p class="event-body reveal reveal-delay-1">{e["intro_body"]}</p>
        </div>
      </section>

      <section class="section-tight" aria-labelledby="{e["fit_id"]}">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">Spaces &amp; facilities</p>
              <h2 id="{e["fit_id"]}">{e["fit_h2"]}</h2>
            </div>
            <p class="lede">These spaces and features are listed on our site. Confirm the setup for your date with the venue team.</p>
          </div>
          <div class="fit-grid">
            {cards}
          </div>
        </div>
      </section>

      <section class="section band-sand" aria-labelledby="plan-{slug}-title">
        <div class="shell planning-grid">
          <div class="reveal">
            <p class="eyebrow">Before you enquire</p>
            <h2 id="plan-{slug}-title">Details worth sharing</h2>
            <p class="lede">{e["plan"]}</p>
          </div>
          <ul class="check-list reveal reveal-delay-1">
            {plan_items}
          </ul>
        </div>
      </section>

      <section class="section" aria-labelledby="faq-{slug}-title">
        <div class="shell-narrow">
          <div class="section-head-center reveal">
            <p class="eyebrow">Common questions</p>
            <h2 id="faq-{slug}-title">{e["faq_h2"]}</h2>
            <p class="lede">Get started with these venue details. For date-specific arrangements, speak with the team.</p>
          </div>
          <div class="faq-list reveal">
            {faqs}
          </div>
          <p class="reveal" style="text-align:center">Still deciding? <a class="text-link" href="contact#enquire">Send an enquiry</a> and our team will help you plan.</p>
        </div>
      </section>

      <section class="section-tight" aria-labelledby="related-{slug}-title">
        <div class="shell reveal">
          <p class="eyebrow">Keep exploring</p>
          <h2 id="related-{slug}-title">Explore other occasions</h2>
          <div class="related-links">
            {related_event_links(slug)}
          </div>
        </div>
      </section>

      {cta_band(e["final"])}
"""


def space_main(slug, s):
    chips = "".join(
        f'<span class="chip">{icon(ic)} {label}</span>' for label, ic in s["chips"]
    )
    specs = "".join(
        f'<div class="spec-row"><dt>{k}</dt><dd>{v}</dd></div>' for k, v in s["capacity"]
    )
    best = "\n            ".join(check_item(i) for i in s["best_for"])
    dining = "".join(f"<p>{p}</p>" for p in s["dining"])
    stay = "".join(f"<p>{p}</p>" for p in s["stay"])
    overview = "".join(f"<p>{p}</p>" for p in s["overview"])
    hero_specs = "".join(
        f'<span class="chip chip-wine">{v}</span>' for v, _ in s["specs"][:2]
    )
    faqs = "\n            ".join(faq_item(q, a) for q, a in s["faqs"])
    gallery_tiles = "\n            ".join(
        plain_tile(cat, img, cap, lab, sub, f"{lab} at the venue")
        for cat, img, cap, lab, sub in s["gallery"]
    )
    related = "\n            ".join(arrow_link(h, lab) for h, lab in s["related"])
    aside_points = "".join(check_item(i) for i in s["best_for"][:3])
    return f"""
      <section class="page-hero" aria-labelledby="space-title">
        <div class="page-hero-media">{pic(s["hero"], s["hero_alt"], priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb(("Spaces", "spaces.html"), s["crumb"])}
          <p class="eyebrow">{s["eyebrow"]}</p>
          <h1 id="space-title">{s["h1"]}</h1>
          <p class="lede">{s["lede"]}</p>
          <div class="space-hero-specs">{hero_specs}</div>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Talk to the Team</a>
          </div>
        </div>
      </section>

      <section class="section" aria-label="{s["name"]} details">
        <div class="shell space-layout">
          <div class="space-content">
            <div class="space-block reveal">
              <p class="eyebrow">Overview</p>
              <h2>{s["overview_h2"]}</h2>
              {overview}
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Specifications</p>
              <h2>At a glance</h2>
              <dl class="spec-list">{specs}</dl>
              <div class="space-hero-specs">{chips}</div>
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Best for</p>
              <h2>Works well for</h2>
              <ul class="check-list">
            {best}
              </ul>
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Dining</p>
              <h2>Food and service</h2>
              {dining}
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Stay</p>
              <h2>Guest accommodation</h2>
              {stay}
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Photos</p>
              <h2>See the space</h2>
              <div class="space-gallery">
            {gallery_tiles}
              </div>
            </div>
            <div class="space-block reveal">
              <p class="eyebrow">Questions</p>
              <h2>Before you enquire</h2>
              <div class="faq-list">
            {faqs}
              </div>
            </div>
          </div>
          <aside class="space-aside reveal reveal-delay-1">
            <p class="eyebrow">Check availability</p>
            <h3>{s["name"]}</h3>
            <p class="aside-cap">{s["capacity"][0][1]}<small>{s["capacity"][0][0]}</small></p>
            <ul class="aside-points">
              {aside_points}
            </ul>
            <a class="button button-wine button-block" href="check-availability">Check Availability {icon("i-arrow")}</a>
            <p class="aside-note">This is an enquiry. Your date is confirmed only after venue confirmation. Enquire for pricing.</p>
          </aside>
        </div>
      </section>

      <section class="section-tight band-paper" aria-labelledby="related-space-title">
        <div class="shell reveal">
          <p class="eyebrow">Keep exploring</p>
          <h2 id="related-space-title">Related spaces</h2>
          <div class="related-links">
            {related}
          </div>
        </div>
      </section>

      {cta_band(s["final"])}
"""
def explorer_card(index_num, role, name, body, specs, href, img, alt, cap_chip):
    spec_html = "".join(
        f"<div><strong>{k}</strong><span>{v}</span></div>" for k, v in specs
    )
    return f"""
          <article class="explorer-card reveal">
            <div class="explorer-media">
              <div class="media-frame">{pic(img, alt)}</div>
              <div class="explorer-cap">{cap_chip}<span class="chip">{icon("i-ac")} Full AC</span></div>
            </div>
            <div class="explorer-body">
              <p class="explorer-index">{index_num} — {role}</p>
              <h3>{name}</h3>
              <p>{body}</p>
              <div class="explorer-specs">{spec_html}</div>
              <a class="text-link" href="{href}">Explore space {icon("i-arrow")}</a>
            </div>
          </article>"""


EXPLORERS = [
    explorer_card("01", "Main Hall", "Big Hall",
                  "The largest space in the venue: a full-size banquet hall for weddings, receptions and large community events, with room for a stage, seating and procession flow.",
                  [("Capacity", "Up to 1200 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Weddings · Large events"), ("Pricing", "Enquire for pricing")],
                  "big-hall.html", "hero-hall.jpg", "Big Hall with chandeliers and rows of seats",
                  '<span class="chip chip-wine">Up to 1200</span>'),
    explorer_card("02", "Celebration Hall", "Small Hall",
                  "A mid-sized hall that suits engagements, birthdays, naming ceremonies and family functions — big enough for a stage, close enough to feel personal.",
                  [("Capacity", "Up to 350 guests"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Engagements · Birthdays"), ("Pricing", "Enquire for pricing")],
                  "small-hall.html", "small-hall.jpg", "Small Hall seating with centre aisle",
                  '<span class="chip chip-wine">Up to 350</span>'),
    explorer_card("03", "Dining", "Dining Hall",
                  "A separate air-conditioned room for the meal — round-table seating for 400 at a time, with catering support so service stays out of the main programme.",
                  [("Capacity", "400 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Meals · Receptions dining"), ("Pricing", "Enquire for pricing")],
                  "dining-hall.html", "dining-hall.jpg", "Dining hall with round tables",
                  '<span class="chip chip-wine">400 at a time</span>'),
    explorer_card("04", "Private Dining", "VIP A/C Dining",
                  "A private dining room for close family, elders and honoured guests — quieter than the main hall, finished to the same standard.",
                  [("Capacity", "50 at a time"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Close family · VIP dining"), ("Pricing", "Enquire for pricing")],
                  "vip-dining.html", "vip-dining.jpg", "VIP air-conditioned dining room",
                  '<span class="chip chip-wine">50 at a time</span>'),
    explorer_card("05", "Stay", "A/C Guest Rooms",
                  "Up to 8 comfortable, air-conditioned bedrooms so out-of-town family can stay at the venue instead of travelling between function and hotel.",
                  [("Rooms", "Up to 8 bedrooms"), ("Air-conditioning", "Fully air-conditioned"), ("Ideal for", "Family stay · Invitees"), ("Pricing", "Enquire for pricing")],
                  "guest-rooms.html", "guest-room.jpg", "Air-conditioned guest bedroom with large bed",
                  '<span class="chip chip-wine">8 Rooms</span>'),
]

COMPARE_ROWS = [
    ("big-hall", "Big Hall", "1200 guests",
     "<em>The scale</em> — room for a stage, seating and procession flow in one hall",
     "Weddings / Large events"),
    ("small-hall", "Small Hall", "350 guests",
     "<em>Mid-sized with a stage</em> — big enough to programme, close enough to feel personal",
     "Engagements / Birthdays"),
    ("dining-hall", "Dining Hall", "400 at a time",
     "<em>Keeps the meal separate</em> — dining never competes with the programme",
     "Meals / Receptions"),
    ("vip-dining", "VIP Dining", "50 at a time",
     "<em>Privacy</em> — a quieter room for close family and honoured guests",
     "Close family / VIP dining"),
    ("guest-rooms", "Guest Rooms", "8 rooms",
     "<em>Stay on site</em> — family rests at the venue instead of travelling",
     "Guest stay"),
]


def compare_table():
    rows = []
    for href, name, cap, diff, ideal in COMPARE_ROWS:
        rows.append(
            f"""              <tr>
                <td class="space-name"><a href="{href}">{name}</a></td>
                <td class="compare-cap">{cap}</td>
                <td class="compare-diff">{diff}</td>
                <td>{ideal}</td>
              </tr>"""
        )
    return "\n".join(rows)


def compare_main():
    return f"""
      <section class="legal-head">
        <div class="shell">
          {breadcrumb("Compare the Spaces")}
          <h1>Compare the Spaces<span class="heading-mr" lang="mr">(जागांची तुलना)</span></h1>
          <p class="lede">Five spaces, side by side — capacity, what each one does better, and the events it suits.</p>
        </div>
      </section>
      <section class="section">
        <div class="shell">
          <div class="compare-wrap reveal">
            <table class="compare-table">
              <thead>
                <tr>
                  <th scope="col">Space</th>
                  <th scope="col">Capacity</th>
                  <th scope="col">What sets it apart</th>
                  <th scope="col">Ideal for</th>
                </tr>
              </thead>
              <tbody>
{compare_table()}
              </tbody>
            </table>
          </div>
          <p class="compare-note">Enquire for pricing on any space. Ask about availability for your preferred date.</p>
        </div>
      </section>

      {cta_band("Ready to check <em>your date?</em>")}
"""


spaces_main = f"""
      <section class="page-hero" aria-labelledby="spaces-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Chandeliers and elegant seating in our main hall", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Spaces")}
          <p class="eyebrow">Our spaces</p>
          <h1 id="spaces-title">Spaces for<br /><em>every occasion</em></h1>
          <p class="lede">Five connected spaces — pick by guest count first, then refine with the <a href="compare">space comparison</a>. All spaces are fully air-conditioned.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a>
          </div>
        </div>
      </section>

      <section class="section" aria-labelledby="explorer-title">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">Space explorer</p>
              <h2 id="explorer-title">Find Your <em>Space.</em></h2>
              <p class="lede">Five connected spaces — halls, dining and stay under one address.</p>
            </div>
            <a class="text-link" href="check-availability">Check availability {icon("i-arrow")}</a>
          </div>
          <div class="explorer-grid">
{"".join(EXPLORERS)}
          </div>
        </div>
      </section>

      {cta_band("Ready to check <em>your date?</em>")}
"""

event_cards_html = "\n".join(
    f"""          <a class="event-card reveal" href="{href}">
            <div class="media-frame">{pic(img, alt)}</div>
            <div class="event-card-body">
              <h3>{title}</h3>
              <p>{desc}</p>
              <span class="event-card-cta">Explore {title} {icon("i-arrow")}</span>
            </div>
          </a>"""
    for href, title, img, alt, desc in EVENT_CARDS
)

events_main = f"""
      <section class="page-hero" aria-labelledby="events-title">
        <div class="page-hero-media">{pic("venue-exterior.jpg", "Exterior of Late Venutai Chavan Multipurpose Hall", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Events")}
          <p class="eyebrow">Celebrate with us</p>
          <h1 id="events-title">Events for<br /><em>every occasion</em></h1>
          <p class="lede">From family milestones to company gatherings, explore how our spaces can support your plans in Nigdi, Pune.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Plan Your Event {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="spaces">Explore the Spaces</a>
          </div>
        </div>
      </section>

      <section class="section" aria-labelledby="events-list-title">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">Explore events</p>
              <h2 id="events-list-title">Find the right setting<br /><em>for your day</em></h2>
            </div>
            <p class="lede">Choose an occasion to see relevant spaces, facilities and planning questions.</p>
          </div>
          <div class="events-grid">
{event_cards_html}
          </div>
        </div>
      </section>

      <section class="section-tight band-sand">
        <div class="shell">
          <div class="note-panel reveal" style="display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);padding:clamp(26px,3.5vw,40px)">
            <div>
              <h2>One venue. Different ways to gather.</h2>
              <p class="lede">The venue lists two air-conditioned halls, dining spaces, guest rooms, parking and other facilities. Share your guest count and program with the team to discuss the right setup for your event.</p>
            </div>
            <a class="button button-wine" href="spaces">View Our Spaces {icon("i-arrow")}</a>
          </div>
        </div>
      </section>

      {cta_band("Let’s talk about <em>your event</em>")}
"""

FACILITY_GROUPS = [
    ("i-ac", "Comfort", ["Fully air-conditioned halls", "A/C guest rooms", "Clean washrooms", "Lobby &amp; waiting area", "Lift access"]),
    ("i-dining", "Dining", ["Dining Hall — 400 at a time", "VIP A/C Dining — 50 at a time", "Catering support for your caterer"]),
    ("i-projector", "Event Technology", ["Acoustic system", "LED screen", "Projector", "Wi-Fi connectivity"]),
    ("i-shield", "Safety &amp; Access", ["CCTV surveillance", "Fire alarm system", "Easy access in Nigdi Pradhikaran"], "CONFIRM parking"),
]


def facility_group(icon_id, title, items, placeholder=None):
    ph = f' data-placeholder="{placeholder}"' if placeholder else ""
    lis = "\n                ".join(f'<li>{icon("i-check")}{item}</li>' for item in items)
    delay = {"Comfort": "", "Dining": " reveal-delay-1", "Event Technology": " reveal-delay-2", "Safety &amp; Access": " reveal-delay-3"}.get(title, "")
    return f"""
          <div class="facility-group reveal{delay}">
            <span class="group-icon">{icon(icon_id)}</span>
            <h3>{title}</h3>
            <ul{ph}>
                {lis}
            </ul>
          </div>"""


facilities_main = f"""
      <section class="page-hero" aria-labelledby="facilities-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Rows of seats beneath the chandeliers in our main hall", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Facilities")}
          <p class="eyebrow">Our facilities</p>
          <h1 id="facilities-title">Everything the day <em>needs.</em></h1>
          <p class="lede">Modern amenities. Thoughtful details. A seamless experience for every occasion — grouped by what you'll actually look for when planning an event.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="gallery">View Gallery</a>
          </div>
        </div>
      </section>

      <section class="section" aria-labelledby="amenities-title">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">Everything included</p>
              <h2 id="amenities-title">Our <em>facilities</em></h2>
            </div>
            <p class="lede">Our air-conditioned banquet halls in Nigdi Pradhikaran offer dining spaces, catering support and practical amenities to help you plan a comfortable event.</p>
          </div>
          <div class="facilities-layout">
            <aside class="facilities-aside reveal">
              <div class="facilities-aside-media">
                {pic("facilities-reference.jpg", "Venue facilities at Late Venutai Chavan Multipurpose Hall")}
                <span class="facilities-aside-cap"><strong>Everything for your event</strong><span>Under one roof, in one place</span></span>
              </div>
            </aside>
            <div class="facility-groups">
{"".join(facility_group(*g) for g in FACILITY_GROUPS)}
            </div>
          </div>
        </div>
      </section>

      <section class="section band-sand" aria-labelledby="setups-title">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">See the venue in use</p>
              <h2 id="setups-title">Event Setups <em>We Host.</em></h2>
              <p class="lede">Each setup is arranged around your programme. Photos below are of the venue spaces themselves; real event photography will be added here as it becomes available.</p>
            </div>
          </div>
          <div class="setups-grid">
            <article class="setup-card reveal">
              <div class="media-frame">{pic("hero-hall.jpg", "Big Hall arranged with banquet seating")}</div>
              <div class="setup-card-body">
                <h3>Wedding Setup</h3>
                <p>Stage, seating and procession flow in the Big Hall, with the Dining Hall handling the meal for 400 at a time.</p>
                <p class="setup-note">Ask our team about setup options.</p>
                <a class="text-link" href="check-availability?event=Wedding">Plan my wedding {icon("i-arrow")}</a>
              </div>
            </article>
            <article class="setup-card reveal reveal-delay-1">
              <div class="media-frame">{pic("hero-hall.jpg", "Main hall seating for an evening reception")}</div>
              <div class="setup-card-body">
                <h3>Reception Setup</h3>
                <p>Welcome seating, stage moments and a separate dining room so guests can move between programme and meal.</p>
                <p class="setup-note">Ask our team about setup options.</p>
                <a class="text-link" href="check-availability?event=Reception">Plan my reception {icon("i-arrow")}</a>
              </div>
            </article>
            <article class="setup-card reveal">
              <div class="media-frame">{pic("small-hall.jpg", "Small Hall set for a corporate or family programme")}</div>
              <div class="setup-card-body">
                <h3>Corporate Setup</h3>
                <p>Rows or clusters with LED screen, projector and acoustic system for presentations and annual functions.</p>
                <p class="setup-note">Ask our team about setup options.</p>
                <a class="text-link" href="check-availability?event=Corporate%20Event">Plan a corporate event {icon("i-arrow")}</a>
              </div>
            </article>
            <article class="setup-card reveal reveal-delay-1">
              <div class="media-frame">{pic("small-hall.jpg", "Flexible hall space for family functions")}</div>
              <div class="setup-card-body">
                <h3>Family Function Setup</h3>
                <p>Flexible seating for anniversaries, naming ceremonies and get-togethers, scaled to your guest list.</p>
                <p class="setup-note">Ask our team about setup options.</p>
                <a class="text-link" href="check-availability?event=Family%20Function">Plan a family function {icon("i-arrow")}</a>
              </div>
            </article>
            <article class="setup-card reveal reveal-delay-2">
              <div class="media-frame">{pic("dining-hall.jpg", "Dining hall arranged with round tables")}</div>
              <div class="setup-card-body">
                <h3>Dining Setup</h3>
                <p>Round-table seating for 400 at a time in the Dining Hall, or private service in VIP A/C Dining for 50 at a time.</p>
                <p class="setup-note">Ask about catering support for your caterer.</p>
                <a class="text-link" href="dining-hall">Explore dining {icon("i-arrow")}</a>
              </div>
            </article>
          </div>
        </div>
      </section>

      {cta_band("Ready to check <em>your date?</em>")}
"""

GALLERY_FILTERS = [
    ("all", "All", None),
    ("big-hall", "Big Hall", "i-people"),
    ("small-hall", "Small Hall", "i-people"),
    ("dining", "Dining", "i-dining"),
    ("guest-rooms", "Guest Rooms", "i-bed"),
    ("event-setups", "Event Setups", "i-image"),
    ("facilities", "Facilities", "i-gear"),
]

GALLERY_TILES = [
    plain_tile("big-hall", "hero-hall.jpg", "Big Hall — banquet seating", "Big Hall", "Up to 1200 guests", "Big Hall with chandeliers and rows of seats"),
    plain_tile("small-hall", "small-hall.jpg", "Small Hall — celebration seating", "Small Hall", "Up to 350 guests", "Small Hall seating with centre aisle"),
    plain_tile("dining", "dining-hall.jpg", "Dining Hall — round-table seating", "Dining Hall", "400 at a time", "Dining hall with round tables"),
    plain_tile("dining", "vip-dining.jpg", "VIP A/C Dining — private dining room", "VIP Dining", "50 at a time", "VIP dining room with gold tablecloths"),
    plain_tile("guest-rooms", "guest-room.jpg", "A/C Guest Room", "Guest Rooms", "Up to 8 rooms", "Guest bedroom with large bed"),
    plain_tile("facilities", "venue-exterior.jpg", "Venue exterior — Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran", "Exterior", "Nigdi, Pune", "Venue exterior"),
    crop_tile("facilities", "Lobby & Reception", "34.328%", "54.721%", "427.362%", "744.720%", "Lobby"),
    crop_tile("event-setups", "Stage Setup", "66.071%", "54.721%", "431.579%", "744.720%", "Stage Setup"),
    crop_tile("event-setups", "Catering Setup", "97.815%", "54.721%", "430.164%", "744.720%", "Catering Setup"),
    crop_tile("facilities", "Parking Area", "2.189%", "71.553%", "427.362%", "740.123%", "Parking"),
    crop_tile("facilities", "Bathrooms / Washrooms", "34.328%", "71.553%", "427.362%", "740.123%", "Washrooms"),
    crop_tile("facilities", "Lift & Access", "66.071%", "71.553%", "431.579%", "740.123%", "Lift & Access"),
    crop_tile("facilities", "CCTV & Safety", "97.815%", "71.553%", "430.164%", "740.123%", "CCTV & Safety"),
]


def filter_btn(value, label, icon_id):
    pressed = "true" if value == "all" else "false"
    ic = f"{icon(icon_id)}" if icon_id else ""
    return f'<button type="button" class="gallery-filter" data-filter="{value}" aria-pressed="{pressed}">{ic}{label}</button>'


gallery_main = f"""
      <section class="page-hero" aria-labelledby="gallery-title">
        <div class="page-hero-media">{pic("dining-hall.jpg", "Dining hall with round tables ready for guests", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Gallery")}
          <p class="eyebrow">Our gallery</p>
          <h1 id="gallery-title">See the space before you <em>visit.</em></h1>
          <p class="lede">Real photographs of the halls, dining rooms and guest spaces — no stock imagery.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="visit">Schedule a Venue Visit {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="spaces">Explore the Spaces</a>
          </div>
        </div>
      </section>

      <section class="section" aria-label="Venue photo gallery">
        <div class="shell">
          <div class="gallery-filters" role="group" aria-label="Filter photos">
            {"".join(filter_btn(*f) for f in GALLERY_FILTERS)}
          </div>
          <div class="gallery-masonry">
            {"".join(GALLERY_TILES)}
          </div>
          <p class="gallery-empty" hidden>No photos in this category.</p>
        </div>
      </section>

      {cta_band("Let’s create beautiful <em>memories together</em>")}
"""

about_main = f"""
      <section class="page-hero" aria-labelledby="about-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Main event hall with chandeliers and rows of chairs", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("About")}
          <p class="eyebrow">About us</p>
          <h1 id="about-title">A venue with a <em>purpose</em></h1>
          <p class="lede">A place where your special moments find the perfect setting — more than just a hall, a part of our community.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="visit">Schedule a Visit</a>
          </div>
        </div>
      </section>

      <section class="section" aria-label="Our inspiration">
        <div class="shell heritage">
          <figure class="heritage-portrait reveal">
            <div class="portrait-frame">
              {pic("about-portrait.jpg", "Portrait of Late Venutai Chavan")}
              <figcaption>Late Venutai Chavan</figcaption>
            </div>
          </figure>
          <div class="heritage-copy reveal reveal-delay-1">
            <p class="eyebrow">Our inspiration</p>
            <h2 id="inspiration-title">Built With a <em>Sense of Community.</em></h2>
            <p style="margin-top:22px">Late Venutai Chavan was a visionary leader who dedicated her life to the service of society. Her values of compassion, community upliftment and bringing people together continue to inspire us every day.</p>
            <p>This multipurpose hall is a tribute to her remarkable legacy — a space built to celebrate relationships, culture, and togetherness.</p>
            <p class="quote">“Her vision lives on in every celebration that happens here.”</p>
            <p lang="mr" style="margin-top:18px;color:var(--ink-3)">स्व. वेणुताई चव्हाण</p>
          </div>
        </div>
      </section>

      <section class="section band-sand" aria-labelledby="values-title">
        <div class="shell">
          <div class="section-head-center reveal">
            <p class="eyebrow">Our values</p>
            <h2 id="values-title">What we stand for</h2>
          </div>
          <div class="facility-groups">
            <div class="facility-group reveal">
              <span class="group-icon">{icon("i-people")}</span>
              <h3>Community Focused</h3>
              <p style="margin-top:18px;color:var(--ink-3);line-height:1.65">A space for people, by the people — built for the neighbourhood and the city around it.</p>
            </div>
            <div class="facility-group reveal reveal-delay-1">
              <span class="group-icon">{icon("i-star")}</span>
              <h3>Quality Infrastructure</h3>
              <p style="margin-top:18px;color:var(--ink-3);line-height:1.65">Modern facilities for your comfort — air-conditioned halls, dining and stay under one address.</p>
            </div>
            <div class="facility-group reveal reveal-delay-2">
              <span class="group-icon">{icon("i-image")}</span>
              <h3>Versatile Spaces</h3>
              <p style="margin-top:18px;color:var(--ink-3);line-height:1.65">Suitable for all types of events — from intimate naming ceremonies to large weddings.</p>
            </div>
            <div class="facility-group reveal reveal-delay-3">
              <span class="group-icon">{icon("i-heart")}</span>
              <h3>Memorable Experiences</h3>
              <p style="margin-top:18px;color:var(--ink-3);line-height:1.65">Creating moments that last a lifetime — your events, our commitment.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section" aria-labelledby="commitment-title">
        <div class="shell editorial-split">
          <div class="editorial-media reveal">
            <div class="media-frame">{pic("about-venue.jpg", "Front entrance of the multipurpose hall")}</div>
          </div>
          <div class="editorial-copy reveal reveal-delay-1">
            <p class="eyebrow">Our commitment</p>
            <h2 id="commitment-title">Safe, welcoming, <em>well managed</em></h2>
            <p class="lede">We are committed to providing a safe, clean, well-managed and welcoming environment for every guest. Whether it's a small family function or a large community event, our team ensures seamless arrangements, personalized support and a pleasant experience for you and your loved ones.</p>
            <p class="quote" style="font-family:var(--font-display);font-style:italic;color:var(--wine);font-size:22px;margin-top:26px">Your Events. Our Commitment.</p>
          </div>
        </div>
      </section>

      <section class="section-tight" aria-label="Venue highlights">
        <div class="shell">
          <div class="stats-row reveal">
            <div class="stat-cell"><strong>10+</strong><span>Years of Service</span></div>
            <div class="stat-cell"><strong>1000+</strong><span>Events Hosted</span></div>
            <div class="stat-cell"><strong>5</strong><span>Venue Spaces</span></div>
            <div class="stat-cell"><strong>Nigdi</strong><span>A Trusted Name in Pune</span></div>
          </div>
        </div>
      </section>

      <p class="about-credit shell reveal" style="text-align:center;font-size:13px;color:var(--ink-3);margin-top:8px;margin-bottom:0">Designed &amp; developed by <a href="https://omkardile.is-a.dev/" rel="author" style="color:var(--brass-deep);font-weight:600">Omkar Kardile</a></p>

      {cta_band("Let’s make your <em>next event special</em>")}
"""

CONTACT_DETAILS = [
    ("i-phone", "Call", '<a href="tel:+919359567494">Jadhav Kedar — 9359567494</a><br /><a href="tel:+919834005348">Jadhav Prashant — 9834005348</a><br /><a href="tel:+919527926303">Shinde Vikas — 9527926303</a>'),
    ("i-mail", "Online", "venutaihall.com"),
    ("i-pin", "Address", "Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran,<br />Nigdi, Pune – 411044"),
    ("i-clock", "Working hours", "Mon – Sun: 9:00 AM – 10:00 PM<br /><small>(Open for bookings &amp; venue visits)</small>"),
    ("i-whatsapp", "WhatsApp", '<a href="https://wa.me/919359567494" target="_blank" rel="noopener noreferrer">Message the venue</a>'),
]


def detail_item(icon_id, label, body):
    return f"""
              <div class="detail-item">
                <span class="detail-icon">{icon(icon_id)}</span>
                <div>
                  <span class="row-label">{label}</span>
                  <p>{body}</p>
                </div>
              </div>"""


EVENT_OPTIONS = [
    ("Wedding", "Wedding"),
    ("Engagement", "Engagement"),
    ("Reception", "Reception"),
    ("Birthday", "Birthday"),
    ("Naming Ceremony", "Naming Ceremony"),
    ("Family Function", "Family Function"),
    ("Corporate Event", "Corporate Event"),
    ("Social Gathering", "Social Gathering"),
    ("Venue Visit", "Venue Visit"),
    ("Other Event", "Other Event"),
]


def event_options(select_first="Select event type"):
    opts = [f'<option value="" selected disabled>{select_first}</option>']
    for val, label in EVENT_OPTIONS:
        opts.append(f"<option value=\"{val}\">{label}</option>")
    return "".join(opts)


def hall_options():
    return """<option value="">No preference — suggest one</option>
                      <option value="big">Big Hall — up to 1200</option>
                      <option value="small">Small Hall — up to 350</option>
                      <option value="dining">Dining Hall — 400 at a time</option>
                      <option value="vip">VIP A/C Dining — 50 at a time</option>
                      <option value="rooms">A/C Guest Rooms — 8 rooms</option>"""


def enquiry_form(form_id, title="Check your date"):
    """Unified 6-field enquiry form — same structure as the homepage hero
    form (Name, Mobile, Email, Space, Event, Date). Used everywhere a booking
    enquiry form appears so the field set is consistent across the site."""
    return f"""
          <form class="enquiry-form check-form" id="{form_id}" action="/booking.php" method="post" novalidate aria-labelledby="{form_id}-title">
            <div class="hero-form-card">
              <div class="hero-form-head"><p class="hero-form-title" id="{form_id}-title">{title}</p></div>
              <div class="form-grid form-grid-2">
                <div class="field"><label for="{form_id}-name">Name <span class="req">*</span></label><input id="{form_id}-name" name="name" autocomplete="name" required /></div>
                <div class="field"><label for="{form_id}-phone">Mobile <span class="req">*</span></label><input id="{form_id}-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" pattern="[0-9+() -]{{10,18}}" required /></div>
                <div class="field"><label for="{form_id}-email">Email</label><input id="{form_id}-email" name="email" type="email" autocomplete="email" /></div>
                <div class="field"><label for="{form_id}-hall">Space <span class="req">*</span></label><select id="{form_id}-hall" name="hall" required><option value="">Choose…</option><option value="big">Big Hall — up to 1200</option><option value="small">Small Hall — up to 350</option></select></div>
                <div class="field"><label for="{form_id}-event">Event <span class="req">*</span></label><select id="{form_id}-event" name="event" required><option value="">Select…</option><option>Wedding</option><option>Engagement</option><option>Reception</option><option>Birthday</option><option>Naming Ceremony</option><option>Family Function</option><option>Corporate Event</option><option>Social Gathering</option><option>Venue Visit</option><option>Other Event</option></select></div>
                <div class="field"><label for="{form_id}-date">Date <span class="req">*</span></label><input id="{form_id}-date" name="date" type="date" required /></div>
              </div>
              <label class="booking-trap" aria-hidden="true">Leave this empty<input name="website" tabindex="-1" autocomplete="off" /></label>
              <button class="button button-wine" type="submit">Send Enquiry {icon("i-arrow")}</button>
              <p class="form-status" role="status" aria-live="polite"></p>
            </div>
          </form>"""


def contact_form_card(form_id="enquire"):
    return enquiry_form(form_id, title="Send an enquiry")


contact_main = f"""
      <section class="page-hero" aria-labelledby="contact-title">
        <div class="page-hero-media">{pic("venue-exterior.jpg", "Entrance to Late Venutai Chavan Multipurpose Hall", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Contact")}
          <p class="eyebrow">Contact us</p>
          <h1 id="contact-title">Let’s plan your <em>special event</em></h1>
          <p class="lede">We're here to help you create memorable moments. Get in touch for bookings, queries or a venue visit.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Call the Venue</a>
            <a class="button button-ghost-light" href="https://wa.me/919359567494" target="_blank" rel="noopener noreferrer">{icon("i-whatsapp")} WhatsApp</a>
            <a class="button button-ghost-light" href="#enquire">Send an Enquiry</a>
          </div>
        </div>
      </section>

      <section class="section" aria-label="Contact details and enquiry form">
        <div class="shell contact-grid">
          <div class="reveal">
            <p class="eyebrow">Get in touch</p>
            <h2>We'd love to <em>hear from you</em></h2>
            <p class="lede">Fill out the form and our team will get back to you as soon as possible. You can also contact us directly using the details below.</p>
            <div class="detail-stack" style="margin-top:32px">
              {"".join(detail_item(*d) for d in CONTACT_DETAILS)}
            </div>
            <p class="quote" style="margin-top:30px;font-family:var(--font-display);font-style:italic;color:var(--wine);font-size:20px">“A perfect venue is just a conversation away.”</p>
          </div>
          {contact_form_card()}
        </div>
      </section>

      <section class="section band-paper" id="location" aria-labelledby="location-title">
        <div class="shell">
          <div class="section-head reveal">
            <div>
              <p class="eyebrow">Location</p>
              <h2 id="location-title">Easy to Reach. <em>Easy to Find.</em></h2>
            </div>
          </div>
          <div class="location-grid">
            <div class="location-map reveal">
              <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3780.282966385947!2d73.77019107492065!3d18.651293965168612!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bc2b92ff63cd819%3A0x8714164503e985f9!2sLate%20Venutai%20Chavan%20Multipurpose%20Hall!5e0!3m2!1sen!2sin!4v1791555939716!5m2!1sen!2sin" title="Late Venutai Chavan Multipurpose Hall location map" style="border:0; width:100%; height:100%;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
              <a class="map-overlay" href="https://www.google.com/maps/search/?api=1&amp;query=Yashwantrao+Chavan+Smarak+Sector+27A+Pradhikaran+Nigdi+Pune+411044" target="_blank" rel="noopener noreferrer">
                <span><strong>{SITE_NAME}</strong><span>Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044</span></span>
                <span class="chip">Open in Google Maps {icon("i-arrow")}</span>
              </a>
            </div>
            <div class="location-panel reveal reveal-delay-1">
              <address class="address-block">
                <strong>{SITE_NAME}</strong>
                Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran,<br />
                Nigdi, Pune – 411044
              </address>
              <ul class="nearby-list">
                <li>{icon("i-pin")} Nigdi Pradhikaran &amp; PCMC</li>
                <li>{icon("i-car")} Easy access from the Pune – Mumbai Highway</li>
                <li>{icon("i-arrow")} Close to bus stop and metro station</li>
                <li>{icon("i-car")} On-site parking available</li>
              </ul>
              <div class="location-actions">
                <a class="button button-wine" href="https://www.google.com/maps/search/?api=1&amp;query=Yashwantrao+Chavan+Smarak+Sector+27A+Pradhikaran+Nigdi+Pune+411044" target="_blank" rel="noopener noreferrer">Get Directions {icon("i-arrow")}</a>
                <a class="button button-ghost" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Call the Venue</a>
                <a class="button button-ghost" href="visit">Schedule a Visit</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      {cta_band("Ready to check <em>your date?</em>")}
"""

visit_main = f"""
      <section class="page-hero" aria-labelledby="visit-title">
        <div class="page-hero-media">{pic("venue-exterior.jpg", "Exterior of Late Venutai Chavan Multipurpose Hall", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Venue Visit")}
          <p class="eyebrow">Venue visits</p>
          <h1 id="visit-title">See it before you <em>celebrate here.</em></h1>
          <p class="lede">Visit the venue, explore the spaces and talk through your requirements with our team. Working hours: Mon – Sun, 9:00 AM – 10:00 PM.</p>
          <div class="hero-ctas">
            <a class="button button-light button-lg" href="check-availability">Request a Visit {icon("i-arrow")}</a>
            <a class="button button-ghost-light" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Call the Venue</a>
          </div>
        </div>
      </section>

      <section class="section" aria-label="Plan your venue visit">
        <div class="shell visit-grid">
          <div class="reveal">
            <p class="eyebrow">What to expect</p>
            <h2>A walkthrough of <em>every space</em></h2>
            <p class="lede">We'll show you the Big Hall, Small Hall, dining spaces and guest rooms, and talk through how they can work for your event.</p>
            <ul class="expect-list">
              <li>{icon("i-check")} Tour of the Big Hall and Small Hall</li>
              <li>{icon("i-check")} Dining Hall and VIP A/C Dining</li>
              <li>{icon("i-check")} Guest rooms and common areas</li>
              <li>{icon("i-check")} Discussion of capacity, layout and timing</li>
              <li>{icon("i-check")} Answers to your planning questions</li>
            </ul>
            <div class="detail-stack" style="margin-top:36px">
              {detail_item("i-clock", "Working hours", "Mon – Sun: 9:00 AM – 10:00 PM")}
              {detail_item("i-pin", "Address", "Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran,<br />Nigdi, Pune – 411044")}
              {detail_item("i-phone", "Call to schedule", '<a href="tel:+919359567494">Jadhav Kedar — 9359567494</a><br /><a href="tel:+919834005348">Jadhav Prashant — 9834005348</a>')}
            </div>
          </div>
          {contact_form_card("visit-form")}
        </div>
      </section>

      <section class="section band-paper" aria-labelledby="visit-location-title">
        <div class="shell">
          <div class="location-grid">
            <div class="location-map reveal">
              <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3780.282966385947!2d73.77019107492065!3d18.651293965168612!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bc2b92ff63cd819%3A0x8714164503e985f9!2sLate%20Venutai%20Chavan%20Multipurpose%20Hall!5e0!3m2!1sen!2sin!4v1791555939716!5m2!1sen!2sin" title="Late Venutai Chavan Multipurpose Hall location map" style="border:0; width:100%; height:100%;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
              <a class="map-overlay" href="https://www.google.com/maps/search/?api=1&amp;query=Yashwantrao+Chavan+Smarak+Sector+27A+Pradhikaran+Nigdi+Pune+411044" target="_blank" rel="noopener noreferrer">
                <span><strong>{SITE_NAME}</strong><span>Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044</span></span>
                <span class="chip">Open in Google Maps {icon("i-arrow")}</span>
              </a>
            </div>
            <div class="location-panel reveal reveal-delay-1">
              <address class="address-block">
                <strong>{SITE_NAME}</strong>
                Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran,<br />
                Nigdi, Pune – 411044
              </address>
              <ul class="nearby-list">
                <li>{icon("i-pin")} Nigdi Pradhikaran &amp; PCMC</li>
                <li>{icon("i-car")} Easy access from the Pune – Mumbai Highway</li>
                <li>{icon("i-arrow")} Close to bus stop and metro station</li>
                <li>{icon("i-car")} On-site parking available</li>
              </ul>
              <div class="location-actions">
                <a class="button button-wine" href="https://www.google.com/maps/search/?api=1&amp;query=Yashwantrao+Chavan+Smarak+Sector+27A+Pradhikaran+Nigdi+Pune+411044" target="_blank" rel="noopener noreferrer">Get Directions {icon("i-arrow")}</a>
                <a class="button button-ghost" href="tel:+919359567494" data-call-picker>{icon("i-phone")} Call the Venue</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      {cta_band("Ready to check <em>your date?</em>")}
"""

wizard_main = f"""
      <section class="page-hero" aria-labelledby="wizard-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Main event hall with chandeliers and banquet seating", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Check Availability")}
          <p class="eyebrow">Check availability</p>
          <h1 id="wizard-title">Check your <em>date.</em></h1>
          <p class="lede">Tell us what you're planning and our team will contact you with availability and next steps. This is an enquiry; your date is confirmed only after venue confirmation.</p>
        </div>
      </section>

      <section class="section" aria-label="Availability enquiry">
        <div class="shell-narrow">
          {enquiry_form("check-form", title="Check your date")}
        </div>
      </section>
"""


def legal_main(slug, title_html, lede, body_html):
    return f"""
      <section class="legal-head">
        <div class="shell">
          {breadcrumb(title_html.replace("<em>", "").replace("</em>", ""))}
          <h1>{title_html}</h1>
          <p class="lede">{lede}</p>
        </div>
      </section>
      <section class="section">
        <div class="shell">
          <div class="notice-band" style="margin-bottom:34px">
            {icon("i-shield")}
            <div><strong>Please read carefully.</strong> This page explains how this website handles information and the terms that apply when you use it. For bookings, contact the venue team directly.</div>
          </div>
          <div class="prose">
            {body_html}
          </div>
        </div>
      </section>
      {cta_band("Ready to check <em>your date?</em>")}
"""


privacy_body = """
            <h2>What we collect</h2>
            <p>When you submit an enquiry through the Check Availability form or a contact form on this site, we collect the details you choose to provide — typically your name, phone number, email address, event type, guest count, preferred date and any message you write.</p>
            <p>The website uses a honeypot field to reduce automated spam. It is not visible to people and is not intended to collect information from you.</p>
            <h2>How we use it</h2>
            <p>We use your details only to respond to your enquiry — to discuss availability, answer questions and help you plan a visit or event at the venue. We do not sell your information.</p>
            <h2>Storage and retention</h2>
            <p>Enquiry details are handled by the venue team for the purpose of following up. For current storage arrangements, contact the venue team.</p>
            <h2>Cookies and analytics</h2>
            <p>This site stores a small preference in your browser if you close a site popup, so it does not reappear immediately. No advertising cookies are set by this site.</p>
            <h2>Third-party links</h2>
            <p>The site links to Google Maps and WhatsApp. Those services have their own privacy policies, which apply when you use them.</p>
            <h2>Your choices</h2>
            <p>You can choose not to provide certain details, though that may limit how we can respond. To ask what information we hold about your enquiry, or to request a correction, contact the venue team on 9359567494.</p>
            <h2>Contact</h2>
            <p>Late Venutai Chavan Multipurpose Hall, Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044. Phone: 9359567494.</p>
            <p class="hint" style="margin-top:28px;color:var(--ink-3);font-size:14px">Last updated: September 2025.</p>
"""

terms_body = """
            <h2>About this website</h2>
            <p>These terms govern your use of venutaihall.com, the website for Late Venutai Chavan Multipurpose Hall. By using the site you agree to them. If you do not agree, please do not use the site.</p>
            <h2>Enquiries are not bookings</h2>
            <p>Forms on this site — including Check Availability — send an enquiry only. An enquiry does not reserve a date, hall or room. Your date is confirmed only after the venue team confirms it with you.</p>
            <p>For current availability, policies and arrangements, contact the venue team directly on 9359567494.</p>
            <h2>Information on this site</h2>
            <p>We aim to keep details about spaces, capacities, facilities and opening hours accurate. Capacities (Big Hall up to 1200, Small Hall up to 350, Dining Hall up to 400, VIP A/C Dining up to 50, up to 8 guest rooms) are as listed by the venue. Layouts, availability and arrangements for a specific date are confirmed with the team when you enquire.</p>
            <p>Pricing is not published on this site — enquire for pricing.</p>
            <h2>Acceptable use</h2>
            <p>Please do not misuse the site — for example, by submitting false or spam enquiries, attempting to disrupt the site, or copying content for commercial use without permission.</p>
            <h2>Intellectual property</h2>
            <p>Site content — text, design and venue photographs — belongs to the venue or its licensors. You may view and share links to the site for personal, non-commercial purposes.</p>
            <h2>External services</h2>
            <p>The site links to Google Maps, WhatsApp and telephone services. We are not responsible for those third-party services or their terms.</p>
            <h2>Liability</h2>
            <p>The site is provided as-is. To the extent permitted by law, we are not liable for decisions made solely based on website content without confirming details with the venue team.</p>
            <h2>Changes</h2>
            <p>We may update these terms. Continued use of the site after changes means you accept the updated terms.</p>
            <h2>Contact</h2>
            <p>Late Venutai Chavan Multipurpose Hall, Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044. Phone: 9359567494.</p>
            <p class="hint" style="margin-top:28px;color:var(--ink-3);font-size:14px">Last updated: September 2025.</p>
"""
our_spaces_redirect = """<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="refresh" content="0; url=spaces.html" />
  <link rel="canonical" href="https://venutaihall.com/spaces" />
  <meta name="robots" content="noindex" />
  <title>Spaces | Late Venutai Chavan Multipurpose Hall</title>
</head>
<body>
  <p>This page has moved to <a href="spaces">spaces.html</a>.</p>
</body>
</html>
"""

BOOKING_PHP = """<?php
declare(strict_types=1);

// Keep the public endpoint aligned with the database-backed booking handler.
require __DIR__ . '/server-patch/booking.php';
"""

POPUP_PHP = """<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'enabled' => false,
    'type' => 'text',
    'cooldownHours' => 24,
    'headline' => '',
    'text' => '',
    'primaryText' => '',
    'primaryUrl' => '',
    'secondaryText' => '',
    'secondaryUrl' => '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
"""

HTACCESS = """RewriteEngine On
RewriteRule ^our-spaces\\.html$ /spaces.html [R=301,L]

RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

RewriteCond %{HTTP_HOST} ^www\\.(.+)$ [NC]
RewriteRule ^(.*)$ https://%1/$1 [R=301,L]

# Serve extensionless page URLs from their matching HTML files.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.+?)/?$ $1.html [L]

ErrorDocument 403 /403.html
ErrorDocument 404 /404.html
ErrorDocument 500 /500.html

# Protect enquiry logs and private backend configuration/schema files.
<FilesMatch "^(enquiries\\.log|booking-config\\.php|schema[^/]*\\.sql|.*\\.log)$">
  Require all denied
</FilesMatch>

<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set X-Frame-Options "SAMEORIGIN"
  Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 6 months"
  ExpiresByType image/webp "access plus 6 months"
  ExpiresByType image/svg+xml "access plus 6 months"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
</IfModule>
"""

ROBOTS = """User-agent: *
Allow: /

Sitemap: https://venutaihall.com/sitemap.xml
"""


def details_main():
    return f"""
      <section class="legal-head">
        <div class="shell">
          {breadcrumb("Detailed Overview")}
          <h1>Detailed Overview</h1>
          <p class="lede">The full detail behind the homepage highlights — spaces, events, setups, planning notes and the story behind the venue.</p>
        </div>
      </section>
      <section class="section">
        <div class="shell">
          <div class="prose">
            <h2 id="overview">The venue at a glance</h2>
            <p>A spacious, air-conditioned event venue in Nigdi, Pune, designed for weddings, family functions, corporate gatherings and memorable celebrations.</p>
            <p>The Big Hall package includes everything: hall, dining, VIP dining and stay.</p>

            <h2 id="spaces">The five spaces</h2>
            <p>Five connected spaces — pick by guest count first, then refine with the <a href="compare">space comparison</a>.</p>
            <p><strong>Big Hall</strong> — The largest space in the venue: a full-size banquet hall for weddings, receptions and large community events, with room for a stage, seating and procession flow.</p>
            <p><strong>Small Hall</strong> — A mid-sized hall that suits engagements, birthdays, naming ceremonies and family functions — big enough for a stage, close enough to feel personal.</p>
            <p><strong>Dining Hall</strong> — A separate air-conditioned room for the meal — round-table seating for 400 at a time, with catering support so service stays out of the main programme.</p>
            <p><strong>VIP A/C Dining</strong> — A private dining room for close family, elders and honoured guests — quieter than the main hall, finished to the same standard.</p>
            <p><strong>A/C Guest Rooms</strong> — Up to 8 comfortable, air-conditioned bedrooms so out-of-town family can stay at the venue instead of travelling between function and hotel.</p>

            <h2 id="events">Events we host</h2>
            <p>Pick your occasion to see which spaces fit — then plan it with the team.</p>
            <p><strong>Weddings</strong> — Ceremony, reception and dining under one roof — with rooms for family staying overnight.</p>
            <p><strong>Engagements</strong> — Ring ceremonies and intimate gatherings, with a dining space nearby for the meal.</p>
            <p><strong>Receptions</strong> — Stage, seating and a separate dining flow for an evening reception.</p>
            <p><strong>Birthdays</strong> — Room for cake, games and the full guest list — scaled to your party size.</p>
            <p><strong>Naming Ceremonies</strong> — Close family gatherings with a dedicated meal space — and rooms if elders stay over.</p>
            <p><strong>Family Functions</strong> — Anniversaries, thread ceremonies and get-togethers — with room to dine together.</p>
            <p><strong>Corporate Events</strong> — Presentations, meetings and annual functions — acoustic system, LED screen and projector ready.</p>
            <p><strong>Social Gatherings</strong> — Community meetings, festivals and neighbourhood events with space to gather and eat.</p>

            <h2 id="compare">Compare the spaces</h2>
            <p>Capacities as listed by the venue. All spaces are fully air-conditioned. The full <a href="compare">differences table</a> is on its own page.</p>

            <h2 id="gallery">Photographs of the venue</h2>
            <p>Real photographs of the halls, dining rooms and guest spaces — no stock imagery.</p>

            <h2 id="facilities">Facilities</h2>
            <p>Grouped by what you'll actually look for when planning an event — comfort, dining, event technology, safety and access. The full list sits on the homepage and the facilities page.</p>

            <h2 id="setups">Event setups</h2>
            <p>Each setup is arranged around your programme. Photos on the homepage are of the venue spaces themselves; real event photography will be added as it becomes available.</p>
            <p><strong>Wedding Setup</strong> — Stage, seating and procession flow in the Big Hall, with the Dining Hall handling the meal for 400 at a time.</p>
            <p><strong>Reception Setup</strong> — Welcome seating, stage moments and a separate dining room so guests can move between programme and meal.</p>
            <p><strong>Corporate Setup</strong> — Rows or clusters with LED screen, projector and acoustic system for presentations and annual functions.</p>
            <p><strong>Family Function Setup</strong> — Flexible seating for anniversaries, naming ceremonies and get-togethers, scaled to your guest list.</p>
            <p><strong>Dining Setup</strong> — Round-table seating for 400 at a time in the Dining Hall, or private service in VIP A/C Dining for 50 at a time.</p>

            <h2 id="heritage">Our namesake</h2>
            <p>This multipurpose hall carries the name of Late Venutai Chavan — a life dedicated to the service of society, and to bringing people together.</p>
            <p>The venue was created as a tribute to that spirit: a well-equipped, welcoming place where neighbours, families and organisations can celebrate milestones and meet, right here in Nigdi Pradhikaran.</p>

            <h2 id="visit">Venue visits</h2>
            <p>Visit the venue, explore the spaces and talk through your requirements with our team.</p>
          </div>
        </div>
      </section>
      {cta_band("Ready to check <em>your date?</em>")}
"""


everything_included_main = f"""
      <section class="page-hero" aria-labelledby="ei-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Main hall with chandeliers", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Everything Included")}
          <p class="eyebrow">What's included</p>
          <h1 id="ei-title">Everything <em>Included.</em><span class="heading-mr" lang="mr">(सर्व काही समाविष्ट)</span></h1>
          <p class="lede">Every package comes fully equipped. Here is exactly what is included.</p>
        </div>
      </section>
      <section class="section" aria-labelledby="ei-big">
        <div class="shell">
          <div class="section-head reveal"><div><p class="eyebrow">Package</p><h2 id="ei-big">Big <em>Hall</em></h2></div><a class="text-link" href="big-hall">Big Hall page {icon("i-arrow")}</a></div>
          <div class="facility-bullets">
            <div class="facility-bullet-group reveal"><h3>Specialities</h3><ul><li>Fully Air-Conditioned Hall</li><li>Seating Capacity: Up to 1,200 People</li><li>Catering Facility Available</li><li>Fully Equipped with an Acoustic System</li><li>8 A/C Guest Rooms Available for Guest Accommodation</li><li>The Entire Building is Equipped with a Fire-Fighting System</li><li>4-Wheeler &amp; 2-Wheeler Parking Available</li><li>LED Screen &amp; Projector Available</li><li>CCTV Surveillance — The Entire Building is Under CCTV Surveillance</li></ul></div>
            <div class="facility-bullet-group reveal reveal-delay-1"><h3>Dining</h3><ul><li>Dining Hall — Seating Capacity: Up to 400 People at a Time</li><li>VIP A/C Dining Hall — Seating Capacity: Up to 50 People at a Time</li></ul></div>
          </div>
        </div>
      </section>
      <section class="section band-sand" aria-labelledby="ei-small">
        <div class="shell">
          <div class="section-head reveal"><div><p class="eyebrow">Package</p><h2 id="ei-small">Small <em>Hall</em></h2></div><a class="text-link" href="small-hall">Small Hall page {icon("i-arrow")}</a></div>
          <div class="facility-bullets">
            <div class="facility-bullet-group reveal"><h3>Specialities</h3><ul><li>Fully Air-Conditioned Hall</li><li>Seating Capacity: Up to 350 People</li><li>Catering Facility Available</li><li>Fully Equipped with an Acoustic System</li><li>The Entire Building is Equipped with a Fire-Fighting System</li><li>4-Wheeler &amp; 2-Wheeler Parking Available</li><li>LED Screen &amp; Projector Available</li><li>CCTV Surveillance — The Entire Building is Under CCTV Surveillance</li></ul></div>
            <div class="facility-bullet-group reveal reveal-delay-1" data-placeholder="CONFIRM with client: small hall dining details"><h3>Dining</h3><ul><li>Dining — Seating Capacity: Up to 50 People at a Time</li></ul></div>
          </div>
        </div>
      </section>
      {cta_band("Ready to check <em>your date?</em>")}
"""




def booking_form(form_id, with_message=True):
    """Unified 6-field booking enquiry form (matches the homepage hero form).
    The with_message parameter is kept for backward-call compatibility but
    no longer appends a message field — the hero form has no message field."""
    return f"""
        <section class="section" aria-label="Booking enquiry">
          <div class="shell-narrow">
            {enquiry_form(form_id, title="Check your date")}
          </div>
        </section>"""


big_hall_main = f"""
      <section class="page-hero" aria-labelledby="space-title">
        <div class="page-hero-media">{pic("hero-hall.jpg", "Big Hall with chandeliers and rows of seats", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Big Hall")}
          <p class="eyebrow">Late Venutai Chavan Multipurpose Hall</p>
          <h1 id="space-title">Big Hall <span class="heading-mr" lang="mr">(बिग हॉल)</span></h1>
          <p class="lede">Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044</p>
          <div class="hero-ctas"><a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a><a class="button button-ghost-light" href="gallery">View Gallery</a></div>
        </div>
      </section>
      <section class="section" aria-labelledby="nums-title">
        <div class="shell">
          <div class="hall-panel reveal" style="border-radius:10px">
            <p class="hall-eyebrow">01 — Big Hall</p>
            <h3 class="hall-name" id="nums-title">Big Hall numbers</h3>
            <dl class="hall-numbers">
              <div class="hall-row"><dt class="hall-row-label">Full A/C Hall</dt><dd class="hall-row-num"><span class="hall-num-val">1200</span><span class="hall-num-cap">up to · guests</span></dd></div>
              <div class="hall-row"><dt class="hall-row-label">Big Dining</dt><dd class="hall-row-num"><span class="hall-num-val">400</span><span class="hall-num-cap">at a time</span></dd></div>
              <div class="hall-row"><dt class="hall-row-label">VIP A/C Dining</dt><dd class="hall-row-num"><span class="hall-num-val">50</span><span class="hall-num-cap">max · at a time</span></dd></div>
              <div class="hall-row hall-row-featured"><dt class="hall-row-label">A/C Guest Rooms <span class="hall-speciality">Speciality</span></dt><dd class="hall-row-num hall-row-num-featured"><span class="hall-num-val">8</span><span class="hall-num-cap">rooms</span></dd></div>
            </dl>
          </div>
        </div>
      </section>
      <section class="section band-sand" aria-labelledby="avail-title"><div class="shell"><div class="section-head reveal"><div><p class="eyebrow">Events</p><h2 id="avail-title">Available <em>for</em></h2></div></div>
        <div class="chip-row reveal"><span class="chip chip-wine">Reception Parties</span><span class="chip chip-wine">Weddings</span><span class="chip chip-wine">Engagement Ceremonies</span><span class="chip chip-wine">Birthday Parties</span><span class="chip chip-wine">Naming Ceremonies</span><span class="chip chip-wine">Family Functions</span><span class="chip chip-wine">Corporate &amp; Social Events</span></div>
      </div></section>
      <section class="section" aria-labelledby="spec-title"><div class="shell"><div class="section-head reveal"><div><p class="eyebrow">Big Hall</p><h2 id="spec-title">Specialities</h2></div></div>
        <ol class="spec-numbered reveal">
          <li>{icon("i-ac")}<span>Fully Air-Conditioned Hall</span></li><li>{icon("i-people")}<span>Seating Capacity: Up to <span class="hl-num">1,200</span> People</span></li><li>{icon("i-tray")}<span>Catering Facility Available</span></li><li>{icon("i-mic")}<span>Fully Equipped with an Acoustic System</span></li><li>{icon("i-bed")}<span><span class="hl-num">8</span> A/C Guest Rooms Available for Guest Accommodation</span></li><li>{icon("i-shield")}<span>The Entire Building is Equipped with a Fire-Fighting System</span></li><li>{icon("i-car")}<span>4-Wheeler &amp; 2-Wheeler Parking Available</span></li><li>{icon("i-screen")}<span>LED Screen &amp; Projector Available</span></li><li>{icon("i-camera")}<span>CCTV Surveillance — The Entire Building is Under CCTV Surveillance</span></li>
        </ol></div></section>
      {booking_form("bh-form")}
      {cta_band("Ready to check <em>your date?</em>")}
"""

small_hall_main = f"""
      <section class="page-hero" aria-labelledby="space-title">
        <div class="page-hero-media">{pic("small-hall.jpg", "Small Hall with seating and centre aisle", priority=True)}</div>
        <div class="page-hero-inner shell">
          {breadcrumb("Small Hall")}
          <p class="eyebrow">Late Venutai Chavan Multipurpose Hall</p>
          <h1 id="space-title">Small Hall <span class="heading-mr" lang="mr">(स्मॉल हॉल)</span></h1>
          <p class="lede">Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune – 411044</p>
          <div class="hero-ctas"><a class="button button-light button-lg" href="check-availability">Check Availability {icon("i-arrow")}</a><a class="button button-ghost-light" href="gallery">View Gallery</a></div>
        </div>
      </section>
      <section class="section" aria-labelledby="nums-title">
        <div class="shell">
          <div class="hall-panel reveal" style="border-radius:10px">
            <p class="hall-eyebrow">02 — Small Hall</p>
            <h3 class="hall-name" id="nums-title">Small Hall numbers</h3>
            <dl class="hall-numbers">
              <div class="hall-row"><dt class="hall-row-label">Full A/C Hall</dt><dd class="hall-row-num"><span class="hall-num-val">350</span><span class="hall-num-cap">up to · guests</span></dd></div>
              <div class="hall-row" data-placeholder="CONFIRM with client: small hall dining details"><dt class="hall-row-label">A/C Dining</dt><dd class="hall-row-num"><span class="hall-num-val">50</span><span class="hall-num-cap">at a time</span></dd></div>
            </dl>
          </div>
        </div>
      </section>
      <section class="section band-sand" aria-labelledby="avail-title"><div class="shell"><div class="section-head reveal"><div><p class="eyebrow">Events</p><h2 id="avail-title">Available <em>for</em></h2></div></div>
        <div class="chip-row reveal"><span class="chip chip-wine">Reception Parties</span><span class="chip chip-wine">Weddings</span><span class="chip chip-wine">Engagement Ceremonies</span><span class="chip chip-wine">Birthday Parties</span><span class="chip chip-wine">Naming Ceremonies</span><span class="chip chip-wine">Family Functions</span><span class="chip chip-wine">Corporate &amp; Social Events</span></div>
      </div></section>
      <section class="section" aria-labelledby="spec-title"><div class="shell"><div class="section-head reveal"><div><p class="eyebrow">Small Hall</p><h2 id="spec-title">Specialities</h2></div></div>
        <ol class="spec-numbered reveal">
          <li>{icon("i-ac")}<span>Fully Air-Conditioned Hall</span></li><li>{icon("i-people")}<span>Seating Capacity: Up to <span class="hl-num">350</span> People</span></li><li>{icon("i-tray")}<span>Catering Facility Available</span></li><li>{icon("i-mic")}<span>Fully Equipped with an Acoustic System</span></li><li>{icon("i-shield")}<span>The Entire Building is Equipped with a Fire-Fighting System</span></li><li>{icon("i-car")}<span>4-Wheeler &amp; 2-Wheeler Parking Available</span></li><li>{icon("i-screen")}<span>LED Screen &amp; Projector Available</span></li><li>{icon("i-camera")}<span>CCTV Surveillance — The Entire Building is Under CCTV Surveillance</span></li>
        </ol></div></section>
      {booking_form("sh-form")}
      {cta_band("Ready to check <em>your date?</em>")}
"""
SITEMAP_PAGES = [
    ("", "1.0"),
    ("big-hall", "0.8"),
    ("small-hall", "0.8"),
    ("gallery", "0.8"),
    ("contact", "0.8"),
    ("visit", "0.7"),
    ("check-availability", "0.9"),
    ("everything-included", "0.7"),
    ("privacy-policy", "0.3"),
    ("terms", "0.3"),
]


def build_sitemap():
    today = "2025-09-23"
    urls = []
    for path, prio in SITEMAP_PAGES:
        loc = "https://venutaihall.com/" + path
        urls.append(
            f"  <url><loc>{loc}</loc><lastmod>{today}</lastmod>"
            f"<changefreq>{'weekly' if prio in ('1.0', '0.9') else 'monthly'}</changefreq>"
            f"<priority>{prio}</priority></url>"
        )
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        + "\n".join(urls)
        + "\n</urlset>\n"
    )
    (ROOT / "sitemap.xml").write_text(xml, encoding="utf-8")
    print("wrote sitemap.xml")


def build_webp():
    try:
        from PIL import Image
    except ImportError:
        print("PIL missing; skipped webp")
        return
    for jpg in sorted((ROOT / "assets").glob("*.jpg")):
        webp = jpg.with_suffix(".webp")
        if webp.exists() and webp.stat().st_mtime >= jpg.stat().st_mtime:
            continue
        try:
            with Image.open(jpg) as im:
                im = im.convert("RGB")
                if max(im.size) > 1600:
                    im.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
                im.save(webp, "WEBP", quality=82, method=6)
            print("webp", webp.name)
        except Exception as exc:
            print("webp failed", jpg.name, exc)


def build():
    ip = ROOT / "index.html"
    itxt = ip.read_bytes().decode("utf-8")
    itxt2 = re.sub(
        r'<script src="script\.js(?:\?[^"]*)?" defer></script>',
        f'<script src="/script.js?v={SCRIPT_V}" defer></script>',
        itxt,
        count=1,
    )
    if itxt2 != itxt:
        ip.write_bytes(itxt2.encode("utf-8"))
        print("index.html script versioned")
    page("spaces.html", spaces_main)
    for slug, s in SPACES.items():
        page(slug, space_main(slug, s))
    page("events.html", events_main)
    for slug, _ in EVENT_SLUGS:
        page(slug, event_main(slug, EVENTS[slug]))
    page("facilities.html", facilities_main)
    page("everything-included.html", everything_included_main)
    page("big-hall.html", big_hall_main)
    page("small-hall.html", small_hall_main)
    page("gallery.html", gallery_main)
    page("about.html", about_main)
    page("contact.html", contact_main)
    page("visit.html", visit_main)
    page("check-availability.html", wizard_main)
    page("details.html", details_main(), body_class="solid-header")
    page("compare.html", compare_main(), body_class="solid-header")
    page(
        "privacy-policy.html",
        legal_main(
            "privacy-policy.html",
            "Privacy Policy",
            "How we handle information you share through this website.",
            privacy_body,
        ),
        body_class="solid-header",
    )
    page(
        "terms.html",
        legal_main(
            "terms.html",
            "Terms &amp; Conditions",
            "The terms that apply when you use venutaihall.com.",
            terms_body,
        ),
        body_class="solid-header",
    )
    (ROOT / "our-spaces.html").write_text(our_spaces_redirect, encoding="utf-8")
    print("wrote our-spaces.html (redirect)")
    (ROOT / "booking.php").write_text(BOOKING_PHP, encoding="utf-8")
    print("wrote booking.php")
    (ROOT / "popup.php").write_text(POPUP_PHP, encoding="utf-8")
    print("wrote popup.php")
    (ROOT / ".htaccess").write_text(HTACCESS, encoding="utf-8")
    print("wrote .htaccess")
    (ROOT / "robots.txt").write_text(ROBOTS, encoding="utf-8")
    print("wrote robots.txt")
    build_sitemap()
    build_webp()
    print("done")


if __name__ == "__main__":
    build()
