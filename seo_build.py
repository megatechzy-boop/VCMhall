"""Source-driven SEO, AEO and GEO additions for the VCMhall static site."""
from __future__ import annotations

from datetime import date
from html import escape
from html import unescape
from html.parser import HTMLParser
import hashlib
import json
import re
import subprocess
from pathlib import Path

BASE = "https://venutaihall.com"
BRAND = "Late Venutai Chavan Multipurpose Hall"
PHONE = ["+91-9359567494", "+91-9834005348", "+91-9527926303"]
ADDRESS = "Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune 411044"

# Titles are deliberately kept short and descriptions are individually authored.
META = {
    "": ("Banquet & Wedding Hall in Nigdi, Pune | Venutai Chavan Hall", "Air-conditioned halls in Nigdi, Pune for weddings, receptions, birthdays and corporate events. Compare spaces and enquire about your date."),
    "about": ("About the Venue | Late Venutai Chavan Hall, Pune", "Learn about Late Venutai Chavan Multipurpose Hall, its venue spaces and facilities for events in Nigdi, Pune."),
    "big-hall": ("Big Hall, Up to 1,200 Guests | Nigdi, Pune", "Explore the air-conditioned Big Hall at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune. Ask about capacity, event setup and your preferred date."),
    "small-hall": ("Small Hall, Up to 350 Guests | Nigdi, Pune", "Explore the air-conditioned Small Hall in Nigdi, Pune for engagements, birthdays and family functions. Enquire about your guest count and event date."),
    "birthdays": ("Birthday Party Hall in Nigdi, Pune | Venue Details", "Compare air-conditioned halls for birthday parties in Nigdi, Pune. Review guest capacities, dining spaces and facilities, then enquire about your date."),
    "check-availability": ("Check Venue Availability in Nigdi, Pune | Date Enquiry", "Ask about your preferred event date at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune. Sending this form is an enquiry, not a booking."),
    "compare": ("Compare Venue Spaces | Nigdi, Pune Hall Capacities", "Compare the Big Hall, Small Hall, dining spaces and guest rooms at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
    "contact": ("Contact the Venue | Nigdi, Pune Hall Enquiries", "Contact Late Venutai Chavan Multipurpose Hall in Nigdi, Pune to ask about dates, event arrangements or a venue visit."),
    "corporate-events": ("Corporate Event Venue in Nigdi, Pune | Hall Details", "Plan a corporate event in Nigdi, Pune. Explore the air-conditioned halls, projector, LED screen, acoustic system and Wi-Fi listed by the venue."),
    "details": ("Venue Details | Late Venutai Chavan Hall, Pune", "Review venue spaces, capacities, facilities and planning information for Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
    "dining-hall": ("Dining Hall for 400 at a Time | Nigdi, Pune Venue", "Explore the air-conditioned Dining Hall in Nigdi, Pune, listed for 400 guests at a time, with catering support for your own caterer."),
    "engagements": ("Engagement Hall in Nigdi, Pune | Venue Details", "Explore air-conditioned hall and dining options for an engagement in Nigdi, Pune. Share your guest count and date with the venue team."),
    "events": ("Events & Functions at a Nigdi, Pune Venue | Occasions", "Explore wedding, engagement, reception, birthday, family, corporate and social event options at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
    "everything-included": ("Hall Facilities & Inclusions | Nigdi, Pune", "See the facilities and spaces listed at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune, and ask the team about arrangements for your event."),
    "facilities": ("Hall Facilities in Nigdi, Pune | Venue Amenities", "Explore air-conditioning, dining, guest rooms, AV equipment, Wi-Fi, CCTV, fire alarm, lift and lobby facilities listed for the Nigdi venue."),
    "family-functions": ("Family Function Hall in Nigdi, Pune | Venue Details", "Explore air-conditioned halls and dining spaces for a family function in Nigdi, Pune. Share your guest count and preferred date to enquire."),
    "gallery": ("Venue Photo Gallery | Nigdi, Pune Event Halls", "View photos of the halls, dining spaces, guest rooms and facilities at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
    "guest-rooms": ("Guest Rooms in Nigdi, Pune | A/C Venue Accommodation", "Ask about up to eight air-conditioned guest rooms at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune and availability for your event dates."),
    "naming-ceremonies": ("Naming Ceremony Hall in Nigdi, Pune | Venue", "Plan a naming ceremony in Nigdi, Pune. Explore the Small Hall, dining spaces and guest rooms listed at Late Venutai Chavan Multipurpose Hall."),
    "privacy-policy": ("Privacy Policy | Late Venutai Chavan Hall", "Read how Late Venutai Chavan Multipurpose Hall handles information submitted through its website enquiry forms."),
    "receptions": ("Reception Hall in Nigdi, Pune | Venue Details", "Plan a reception in Nigdi, Pune with air-conditioned halls, dining spaces and listed AV facilities. Enquire about your guest count and date."),
    "small-hall": ("Small Hall, Up to 350 Guests | Nigdi, Pune", "Explore the air-conditioned Small Hall in Nigdi, Pune for engagements, birthdays and family functions. Enquire about your guest count and event date."),
    "social-gatherings": ("Social Gathering Venue in Nigdi, Pune | Hall", "Explore air-conditioned hall and dining options for a social gathering in Nigdi, Pune. Ask the team about a suitable setup and preferred date."),
    "spaces": ("Banquet Halls & Event Spaces in Nigdi, Pune", "Compare five air-conditioned event spaces in Nigdi, Pune, including two halls, dining rooms and guest accommodation at one venue."),
    "terms": ("Terms & Conditions | Late Venutai Chavan Hall", "Read the terms for using venutaihall.com. An availability enquiry is not a confirmed booking; dates require venue confirmation."),
    "vip-dining": ("VIP A/C Dining for 50 Guests | Nigdi, Pune", "Explore the private, air-conditioned VIP Dining room listed for 50 guests at a time at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
    "visit": ("Plan a Venue Visit | Late Venutai Chavan Hall", "Arrange a visit to Late Venutai Chavan Multipurpose Hall in Nigdi, Pune to see the halls, dining spaces and guest rooms in person."),
    "weddings": ("Wedding Hall in Nigdi, Pune | Venue Details", "Explore air-conditioned halls, dining spaces and guest rooms for a wedding in Nigdi, Pune. Ask the venue team about your date and guest count."),
    "wedding-hall-nigdi-pune": ("Wedding Hall in Nigdi, Pune | Late Venutai Chavan", "Explore wedding hall options in Nigdi, Pune, with seating for up to 1,200 guests, dining spaces and A/C guest rooms. Enquire about your date."),
    "banquet-hall-nigdi-pune": ("Banquet Hall in Nigdi, Pune | Compare Venue Spaces", "Compare air-conditioned banquet spaces in Nigdi, Pune, including the Big Hall, Small Hall, dining rooms and guest rooms. Ask about your date."),
    "engagement-hall-nigdi-pune": ("Engagement Hall in Nigdi, Pune | Venue Options", "Plan an engagement in Nigdi, Pune with the air-conditioned Small Hall, dining spaces and up to eight guest rooms listed at the venue."),
    "birthday-party-hall-nigdi-pune": ("Birthday Party Hall in Nigdi, Pune | Venue Options", "Compare air-conditioned halls for a birthday party in Nigdi, Pune, with seating for up to 350 or 1,200 guests and dining spaces."),
    "corporate-event-venue-nigdi-pune": ("Corporate Event Venue in Nigdi, Pune | Hall Options", "Plan a corporate event in Nigdi, Pune with air-conditioned halls, projector, LED screen, acoustic system and Wi-Fi listed at the venue."),
    "reception-hall-pradhikaran-pune": ("Reception Hall in Pradhikaran, Pune | Venue Options", "Explore reception hall options in Pradhikaran, Pune, with dining spaces and guest rooms. Ask about guest capacity, setup and date availability."),
    "hall-with-ac-rooms-nigdi-pune": ("Hall with A/C Guest Rooms in Nigdi, Pune | Banquet Venue", "Plan an event at a Nigdi, Pune hall with up to eight listed air-conditioned guest rooms, two halls and separate dining spaces."),
    "faq": ("Venue FAQs | Hall Capacity, Facilities & Enquiries", "Answers about hall capacities, dining, guest rooms, facilities, location and how to enquire at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune."),
}

LANDINGS = {
    "wedding-hall-nigdi-pune": {
        "label": "Wedding Hall", "event": "wedding", "recommend": "Big Hall", "capacity": "up to 1,200 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall is a wedding venue in Nigdi, Pune. Its air-conditioned Big Hall is listed for up to 1,200 guests, with a Small Hall for up to 350, separate dining spaces and up to eight A/C guest rooms. Ask the team to match your guest count and schedule to the spaces.",
        "fit": "For a large wedding guest list, start by asking about the Big Hall and the event layout. For a smaller gathering, compare the Small Hall capacity with your expected attendance. The listed dining spaces seat 400 and 50 guests at a time; ask how meal service can fit around your ceremony and programme.",
        "plan": "When you enquire, include the ceremony date, approximate guest count, timing, stage or presentation needs, meal schedule and whether family needs guest rooms. These details help the venue team discuss space availability and a practical event flow. The website enquiry does not reserve a date; the venue confirms dates directly.",
        "access": "The venue lists an acoustic system, LED screen, projector, Wi-Fi, CCTV, fire alarm, lift and lobby. It also lists parking and catering support for your own caterer. Confirm which facilities are available for your date and the specific setup you need. Pricing and package details are not published here; request a quote from the venue team.",
        "faqs": [("How many guests can the wedding hall accommodate?", "The Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350. Ask the venue team which layout suits your guest count."), ("Is dining available at the venue?", "The Dining Hall is listed for 400 guests at a time and VIP A/C Dining for 50 at a time. Confirm meal arrangements with the team."), ("Are guest rooms available?", "The venue lists up to eight air-conditioned guest rooms. Ask about availability for your dates."), ("How do I enquire about a wedding date?", "Use the Check Availability form or call the venue. Your date is confirmed only after the venue confirms it."), ("What is the wedding package price?", "Pricing is not published on the website. Contact the venue team for a current quote.")]
    },
    "banquet-hall-nigdi-pune": {
        "label": "Banquet Hall", "event": "event", "recommend": "Big Hall or Small Hall", "capacity": "up to 1,200 or 350 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall is an air-conditioned banquet venue in Nigdi, Pune, with a Big Hall for up to 1,200 guests and a Small Hall for up to 350. Dining, VIP dining and guest rooms are also listed at the venue. Compare the spaces by expected attendance, then enquire about your date.",
        "fit": "The Big Hall is the larger option for a high guest count; the Small Hall is listed for mid-sized events. Dining is a separate function: the Dining Hall is listed for 400 at a time and VIP A/C Dining for 50 at a time. Capacities refer to the figures published by the venue; ask about seating layout and event setup for your plan.",
        "plan": "Share the kind of event, preferred date, estimated attendance and any stage, dining or presentation requirements. If guests need to stay, mention the A/C rooms in your enquiry. The venue team can tell you which spaces are available and how they may work together. An enquiry is not a booking and does not hold a date.",
        "access": "Facilities listed include air-conditioning, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby and catering support for your own caterer. Availability and arrangements can depend on the event and date, so confirm details directly. The site does not publish starting rates; ask for a quote rather than relying on an estimate.",
        "faqs": [("Which banquet hall is larger?", "The Big Hall is listed for up to 1,200 guests; the Small Hall is listed for up to 350."), ("Can I choose a hall by guest count?", "Yes. Share your expected attendance with the venue team to discuss the hall and setup."), ("Can I bring my own caterer?", "The venue lists catering support for your own caterer. Confirm current arrangements directly."), ("What facilities are listed?", "Air-conditioning, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift and lobby are listed."), ("How can I get the price?", "Pricing is not published here. Contact the venue for current rates and package details.")]
    },
    "engagement-hall-nigdi-pune": {
        "label": "Engagement Hall", "event": "engagement", "recommend": "Small Hall", "capacity": "up to 350 guests",
        "intro": "For an engagement in Nigdi, Pune, Late Venutai Chavan Multipurpose Hall lists an air-conditioned Small Hall for up to 350 guests and a Big Hall for up to 1,200. Separate dining rooms and up to eight A/C guest rooms are also listed. Share your attendance and ceremony plans to discuss a suitable space.",
        "fit": "The Small Hall is a starting point for a mid-sized ring ceremony or family gathering. If your invitation list is larger, ask about the Big Hall. The final layout depends on the ceremony, seating and stage needs, so compare the published capacities with your expected guest count and ask the team about the arrangement.",
        "plan": "Consider the time needed for guest arrival, the ring exchange, family photos and meal service. In your enquiry, share your preferred date, attendance estimate, event timing, seating or stage requirements and whether you want a separate dining space. Mention guest rooms if family members may stay. The team will confirm availability and next steps.",
        "access": "The venue lists fully air-conditioned halls, an acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift and lobby. Dining is listed for 400 guests at a time, with VIP A/C Dining for 50 at a time. Ask which facilities and dining arrangements are suitable for your function. Prices are not listed; request a current quote.",
        "faqs": [("Which hall can I enquire about for an engagement?", "The Small Hall is listed for up to 350 guests and the Big Hall for up to 1,200. Share your attendance to discuss options."), ("Can the venue arrange dining?", "Separate Dining Hall and VIP A/C Dining spaces are listed. Ask about timing and arrangements."), ("Are the halls air-conditioned?", "The venue lists fully air-conditioned halls."), ("Can I check a date online?", "Submit an enquiry using Check Availability. The venue team must confirm the date."), ("What does an engagement cost?", "Prices are not published on the website. Contact the venue team for a quote.")]
    },
    "birthday-party-hall-nigdi-pune": {
        "label": "Birthday Party Hall", "event": "birthday party", "recommend": "Small Hall", "capacity": "up to 350 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall offers air-conditioned event spaces in Nigdi, Pune, including a Small Hall listed for up to 350 guests and a Big Hall for up to 1,200. Dining spaces and guest rooms are also listed. Choose a starting option by guest count and ask the team about your birthday date.",
        "fit": "For a smaller birthday gathering, ask about the Small Hall. For a larger guest list, compare the Big Hall capacity. Consider whether you need a stage, music or presentation equipment, and plan how guests will move between the event and dining spaces. Share your expected attendance so the team can discuss a workable arrangement.",
        "plan": "In your enquiry, mention the date, age or type of celebration if useful, guest count, timing, seating and any stage or screen needs. If the event includes a meal, ask about the Dining Hall, listed for 400 guests at a time, and VIP A/C Dining for 50. The venue team can confirm availability, setup options and the next steps; a form submission alone is not a reservation.",
        "access": "Facilities listed by the venue include air-conditioning, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby and catering support for your own caterer. Confirm which equipment and services can be arranged for your date. No starting price is published, so ask the team for current rates and package information.",
        "faqs": [("Which hall suits a birthday party?", "The Small Hall is listed for up to 350 guests; the Big Hall is listed for up to 1,200. Share your guest count to discuss fit."), ("Can the hall support music or a programme?", "An acoustic system, LED screen and projector are listed. Confirm your requirements with the venue."), ("Is dining available?", "Dining Hall and VIP A/C Dining spaces are listed. Ask about the meal arrangement."), ("How do I check a date?", "Use the Check Availability form or call the venue team. Wait for venue confirmation."), ("How much does a birthday booking cost?", "Pricing is not published on the website. Contact the venue for a quote.")]
    },
    "corporate-event-venue-nigdi-pune": {
        "label": "Corporate Event Venue", "event": "corporate event", "recommend": "Big Hall or Small Hall", "capacity": "up to 1,200 or 350 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall is a corporate event venue in Nigdi, Pune, with two air-conditioned halls: the Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350. The venue also lists a projector, LED screen, acoustic system and Wi-Fi. Enquire about your programme and date.",
        "fit": "Start with your attendee count and event format. The Big Hall is the larger space; the Small Hall may fit a mid-sized meeting or gathering. For presentations, ask about the projector, LED screen, acoustic system and Wi-Fi. Confirm technical compatibility and availability with the venue team before relying on any equipment for a programme.",
        "plan": "Share the event date, attendee estimate, schedule, seating style, presentation needs, meal timings and any access requirements. Ask whether dining should use the separate Dining Hall, listed for 400 guests at a time, or VIP A/C Dining for 50. Include setup and breakdown timing in your discussion. The team will respond with availability and next steps; submitting an enquiry does not confirm the booking.",
        "access": "The venue lists air-conditioning, parking, CCTV, fire alarm, lift and lobby, as well as catering support for your own caterer. Ask which services apply to your event and whether any equipment needs to be brought in. The site does not publish corporate rental rates or packages, so request a quote for your schedule and requirements.",
        "faqs": [("What is the capacity for a corporate event?", "The Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350."), ("Is presentation equipment available?", "A projector, LED screen, acoustic system and Wi-Fi are listed. Confirm specifications and date availability."), ("Can the venue provide dining?", "Separate Dining Hall and VIP A/C Dining spaces are listed. Ask about your meal schedule."), ("How can I enquire?", "Use the Check Availability form and include date, attendance and event requirements."), ("What are the corporate rates?", "Rates are not published on the website. Ask the venue team for a current quote.")]
    },
    "reception-hall-pradhikaran-pune": {
        "label": "Reception Hall", "event": "reception", "recommend": "Big Hall", "capacity": "up to 1,200 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall in Pradhikaran, Pune, lists an air-conditioned Big Hall for up to 1,200 guests and a Small Hall for up to 350. Dining spaces and up to eight A/C guest rooms are available to ask about. Share your reception attendance, date and programme when you enquire.",
        "fit": "The Big Hall is the larger option for a reception with many guests; the Small Hall may suit a smaller celebration. Think about guest arrival, stage moments, seating and the timing of meals. The Dining Hall is listed for 400 guests at a time and VIP A/C Dining for 50. Ask how these spaces can fit the flow of your reception.",
        "plan": "Include your preferred date, estimated guest count, event timing, stage or presentation needs and dining schedule in the enquiry. If you expect family to stay, ask about the eight listed air-conditioned guest rooms. You can also request a venue visit to see the spaces. The venue team must confirm availability and booking; the website enquiry itself does not hold a date.",
        "access": "The venue lists air-conditioning, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby and catering support for your own caterer. Confirm which facilities and arrangements are available on your date. Reception pricing and package inclusions are not published; request a quote from the venue team.",
        "faqs": [("How many guests can the reception hall accommodate?", "The Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350."), ("Can guests dine separately?", "The venue lists separate Dining Hall and VIP A/C Dining spaces. Ask about meal service and timing."), ("Are rooms available for family?", "Up to eight air-conditioned guest rooms are listed. Confirm availability for your date."), ("Can I visit before deciding?", "You can enquire about arranging a venue visit to see the spaces."), ("What is the reception price?", "Pricing is not listed online. Contact the venue team for a current quote.")]
    },
    "hall-with-ac-rooms-nigdi-pune": {
        "label": "Hall with A/C Guest Rooms", "event": "event", "recommend": "Big Hall or Small Hall", "capacity": "up to 1,200 or 350 guests",
        "intro": "Late Venutai Chavan Multipurpose Hall in Nigdi, Pune, lists up to eight air-conditioned guest rooms alongside two air-conditioned halls. The Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350. Separate dining spaces are also listed. Ask about room and hall availability together for your event dates.",
        "fit": "This option may help when family or invitees need rooms at the same venue as the event. Choose a hall based on attendance, then tell the team how many rooms you may need and which dates guests would stay. The listing says up to eight rooms; it does not specify room rates, occupancy or check-in conditions, so confirm those details directly.",
        "plan": "When you enquire, include the event type, preferred date, expected guest count, hall preference and approximate room requirement. Ask whether rooms are available for the same dates and what arrangements apply to guests staying overnight. Dining spaces are listed separately, so mention meal timing as well. A venue visit can help you see the event spaces and rooms before you decide.",
        "access": "The venue lists fully air-conditioned halls and rooms, Dining Hall for 400 guests at a time, VIP A/C Dining for 50, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby and catering support. Confirm which facilities can be used with your event and whether rooms are available. Room and hall pricing is not published; request a quote.",
        "faqs": [("How many A/C guest rooms are listed?", "The venue lists up to eight air-conditioned guest rooms."), ("Can I enquire about a hall and rooms together?", "Yes. Include the event date, guest count and room needs in one enquiry, then ask the team to confirm availability."), ("Which hall capacities are listed?", "The Big Hall is listed for up to 1,200 guests and the Small Hall for up to 350."), ("Are room prices shown online?", "Room rates and occupancy details are not published here. Contact the venue for current information."), ("Where is the venue?", f"The address listed is {ADDRESS}.")]
    },
}

FAQ_GROUPS = {
    "Booking and visits": [
        ("How do I enquire about a date?", "Use the Check Availability form or call the venue team on 9359567494, 9834005348 or 9527926303."),
        ("Does an enquiry confirm my booking?", "No. An enquiry is not a booking. Your date is confirmed only after the venue team confirms it."),
        ("Can I visit the venue?", "Yes. Contact the team to arrange a visit and see the halls, dining spaces and guest rooms."),
        ("निगडीमध्ये लग्नासाठी हॉल कोणता?", "मोठ्या पाहुण्यांच्या यादीसाठी बिग हॉल आणि मध्यम आकाराच्या समारंभासाठी स्मॉल हॉलची क्षमता तपासा. योग्य जागा आणि उपलब्धता जाणून घेण्यासाठी पाहुण्यांची संख्या व तारीख venue टीमला सांगा."),
    ],
    "Capacity and spaces": [
        ("What is the Big Hall capacity?", "The Big Hall is listed for up to 1,200 guests."),
        ("What is the Small Hall capacity?", "The Small Hall is listed for up to 350 guests."),
        ("How many people can dine at once?", "The Dining Hall is listed for 400 at a time and VIP A/C Dining for 50 at a time."),
    ],
    "Facilities and catering": [
        ("Are the halls air-conditioned?", "The venue lists fully air-conditioned halls and dining spaces."),
        ("Can I bring my own caterer?", "Catering support for your own caterer is listed. Contact the team to confirm current arrangements."),
        ("What event equipment is listed?", "The listed facilities include an acoustic system, LED screen, projector and Wi-Fi."),
        ("Is parking available?", "Parking is listed among the venue facilities. Ask the team for current parking details."),
    ],
    "Guest rooms and location": [
        ("Are guest rooms available?", "The venue lists up to eight air-conditioned guest rooms. Confirm availability and room conditions directly."),
        ("Where is the venue located?", f"{BRAND} is at {ADDRESS}."),
        ("What are the venue hours?", "The listed hours are Monday to Sunday, 9:00 AM to 10:00 PM."),
    ],
    "Pricing": [
        ("What is the hall rental price?", "Prices and package rates are not published on the website. Contact the venue team for a current quote."),
    ],
}

REDIRECTS = {
    "weddings": "wedding-hall-nigdi-pune",
    "engagements": "engagement-hall-nigdi-pune",
    "birthdays": "birthday-party-hall-nigdi-pune",
    "corporate-events": "corporate-event-venue-nigdi-pune",
    "receptions": "reception-hall-pradhikaran-pune",
}

ANSWER_SUMMARIES = {
    "": "Late Venutai Chavan Multipurpose Hall is an air-conditioned banquet venue in Nigdi, Pune. It lists a Big Hall for up to 1,200 guests, a Small Hall for up to 350, dining for 400 at a time, VIP dining for 50 and up to eight A/C guest rooms. Contact the team to enquire about dates.",
    "big-hall": "The Big Hall at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune is listed for up to 1,200 guests. It is air-conditioned and suited to weddings, receptions and large events. Separate dining spaces and up to eight A/C guest rooms are also listed at the venue.",
    "small-hall": "The Small Hall at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune is air-conditioned and listed for up to 350 guests. The venue lists it for engagements, birthdays, naming ceremonies and family functions, with separate dining spaces and guest rooms available to ask about.",
    "spaces": "Late Venutai Chavan Multipurpose Hall in Nigdi, Pune lists five air-conditioned spaces: Big Hall for up to 1,200 guests, Small Hall for up to 350, Dining Hall for 400 at a time, VIP A/C Dining for 50 and up to eight guest rooms.",
    "events": "Late Venutai Chavan Multipurpose Hall in Nigdi, Pune lists spaces for weddings, engagements, receptions, birthdays, naming ceremonies, family functions, corporate events and social gatherings. Compare the Big Hall and Small Hall capacities, then ask the team about dining, facilities and availability for your date.",
    "dining-hall": "The Dining Hall at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune is air-conditioned and listed for 400 guests at a time. VIP A/C Dining for 50 guests at a time is also listed. Catering support for your own caterer is available to ask about.",
    "vip-dining": "VIP A/C Dining at Late Venutai Chavan Multipurpose Hall in Nigdi, Pune is a separate air-conditioned dining room listed for 50 guests at a time. The venue also lists a Dining Hall for 400 at a time. Ask the team how dining can fit your event schedule.",
    "guest-rooms": "Late Venutai Chavan Multipurpose Hall in Nigdi, Pune lists up to eight air-conditioned guest rooms alongside its event halls and dining spaces. Room availability, occupancy, rates and stay conditions are not published; contact the venue team to confirm details for your dates.",
    "facilities": "The Nigdi venue lists fully air-conditioned halls, dining spaces and guest rooms, plus an acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby and catering support for your own caterer. Confirm event-specific availability directly with the team.",
    "about": "Late Venutai Chavan Multipurpose Hall is an event venue at Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune. The venue lists two air-conditioned halls, separate dining spaces and up to eight guest rooms. Contact the team for current venue and event information.",
    "contact": "Contact Late Venutai Chavan Multipurpose Hall at Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune 411044. Call Jadhav Kedar on 9359567494, Jadhav Prashant on 9834005348 or Shinde Vikas on 9527926303 for event enquiries and visits.",
    "visit": "Late Venutai Chavan Multipurpose Hall welcomes venue visit enquiries in Nigdi, Pune. Visitors can ask to see the Big Hall, Small Hall, dining spaces and guest rooms. The listed hours are Monday to Sunday, 9:00 AM to 10:00 PM; contact the team to arrange a visit.",
    "check-availability": "Use the availability form to ask Late Venutai Chavan Multipurpose Hall in Nigdi, Pune about a preferred event date. Share your event type, guest count, hall preference and contact details. This form sends an enquiry; the venue team must confirm a booking.",
}


class BreadcrumbParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_nav = False
        self.depth = 0
        self.items = []
        self.current = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "nav" and "breadcrumb" in attrs.get("class", "").split():
            self.in_nav = True
        if not self.in_nav:
            return
        if tag == "a":
            self.current = {"name": "", "item": attrs.get("href", "/")}
        elif tag == "span" and attrs.get("aria-current") == "page":
            self.current = {"name": "", "item": None}

    def handle_data(self, data):
        if self.in_nav and self.current is not None:
            self.current["name"] += data.strip()

    def handle_endtag(self, tag):
        if self.in_nav and tag in ("a", "span") and self.current is not None:
            if self.current["name"]:
                self.items.append(self.current)
            self.current = None
        elif self.in_nav and tag == "nav":
            self.in_nav = False


class VisibleFaqParser(HTMLParser):
    """Collect question/answer pairs from the visible homepage accordion."""
    def __init__(self):
        super().__init__()
        self.in_item = False
        self.in_summary = False
        self.in_answer = False
        self.pairs = []
        self.question = ""
        self.answer = ""

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "details" and "faq-item" in attrs.get("class", "").split():
            self.in_item = True
            self.question = ""
            self.answer = ""
        elif self.in_item and tag == "summary":
            self.in_summary = True
        elif self.in_item and "faq-answer" in attrs.get("class", "").split():
            self.in_answer = True

    def handle_data(self, data):
        if self.in_summary:
            self.question += data
        elif self.in_answer:
            self.answer += data

    def handle_endtag(self, tag):
        if tag == "summary":
            self.in_summary = False
        elif self.in_item and tag == "div" and self.in_answer:
            self.in_answer = False
        elif tag == "details" and self.in_item:
            q = re.sub(r"\s+", " ", self.question).strip()
            a = re.sub(r"\s+", " ", self.answer).strip()
            if q and a:
                self.pairs.append((q, a))
            self.in_item = False


def wrap_new_pages(root: Path):
    home = (root / "index.html").read_text(encoding="utf-8")
    def section(a, b):
        i = home.index(a)
        j = home.index(b, i)
        return home[i:j].rstrip()
    body_start = home.index("<body")
    body_content_start = home.index(">", body_start) + 1
    main_start = home.index('<main id="main-content">', body_content_start)
    shared_chrome = home[body_content_start:main_start]
    footer = section('<footer class="site-footer"', "</footer>") + "</footer>"
    bottom_start = home.index('<nav class="bottom-bar"')
    floats_start = home.index('<div class="float-actions"')
    floats = home[floats_start:bottom_start]
    bottom = home[bottom_start:home.index("</nav>", bottom_start) + 6]
    lightbox_start = home.index('<div class="lightbox"')
    lightbox = home[lightbox_start:home.index("</body>", lightbox_start)].rstrip()
    for slug in [*LANDINGS, "faq"]:
        path = root / (slug + ".html")
        text = path.read_text(encoding="utf-8")
        text = text.replace('<body class="solid-header"><main id="main-content">', '<body class="solid-header">' + shared_chrome + '<main id="main-content">', 1)
        text = text.replace('</main></body></html>', '</main>\n' + footer + '\n' + floats + '\n' + bottom + '\n' + lightbox + '\n</body></html>', 1)
        path.write_text(text, encoding="utf-8")


def json_script(data):
    return '<script type="application/ld+json">' + json.dumps(data, ensure_ascii=False, separators=(",", ":")) + "</script>"


def normalized_title(title):
    plain = unescape(title)
    if len(plain) < 50:
        suffixes = [" | Nigdi", " | Local", " | Venue", " | Events", " | Hall", " | Pune"]
        for suffix in suffixes:
            word = suffix.rsplit(" ", 1)[-1].strip("|").strip().casefold()
            if word in plain.casefold():
                continue
            if 50 <= len(plain + suffix) <= 60:
                plain += suffix
                break
    if len(plain) > 60:
        plain = plain[:60].rsplit(" ", 1)[0].rstrip(" |,-")
    return plain


def normalized_description(description):
    plain = unescape(description).strip()
    additions = [
        " Learn more.", " For details.", " Contact us.", " Ask the team.",
        " Ask about dates.", " Call the venue.", " More venue details.", " See venue details.",
        " View the spaces.", " Ask about your date.", " Contact the team for details.",
        " Send an availability enquiry.", " Call or message us about dates.",
        " Contact the venue for event details.", " Ask the venue team about your date.",
        " Ask about dates and event setup.", " Contact the team about availability and plans.",
        " Ask about facilities, guest count and dates.", " Contact the venue team to discuss arrangements.",
    ]
    if 140 <= len(plain) <= 155:
        return plain
    candidates = [plain + addition for addition in additions]
    valid = [item for item in candidates if 140 <= len(item) <= 155]
    if valid:
        return min(valid, key=len)
    if len(plain) < 140:
        # A second short sentence keeps the description readable instead of cutting it mid-sentence.
        for first in additions:
            for second in additions:
                item = plain + first + second
                if 140 <= len(item) <= 155:
                    return item
    # Preserve a complete sentence where the old copy is over the display target.
    trimmed = plain[:153].rsplit(" ", 1)[0].rstrip(" ,;:-")
    return trimmed + "."


def page_schema(slug, html):
    schemas = []
    if slug:
        parser = BreadcrumbParser()
        parser.feed(html)
        if parser.items:
            elements = []
            for position, row in enumerate(parser.items, 1):
                item = {"@type": "ListItem", "position": position, "name": row["name"]}
                if row["item"]:
                    item["item"] = BASE + (row["item"] if row["item"].startswith("/") else "/" + row["item"].removesuffix(".html"))
                elements.append(item)
            schemas.append({"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": elements})
    if slug == "":
        schemas.append({"@context": "https://schema.org", "@type": "WebSite", "name": BRAND, "url": BASE + "/", "publisher": {"@id": BASE + "/#organization"}})
    if slug == "contact":
        schemas.append({"@context": "https://schema.org", "@type": "ContactPage", "mainEntity": {"@type": "Organization", "name": BRAND, "contactPoint": [{"@type": "ContactPoint", "telephone": phone, "contactType": "venue enquiries", "availableLanguage": ["English", "Marathi"]} for phone in PHONE]}})
    if slug == "gallery":
        assets = ["hero-hall.webp", "big-hall-primary.webp", "small-hall.webp", "dining-hall.webp", "vip-dining.webp", "guest-room.webp", "venue-exterior.webp"]
        schemas.append({"@context": "https://schema.org", "@type": "ImageGallery", "name": BRAND + " venue photo gallery", "image": [{"@type": "ImageObject", "contentUrl": BASE + "/assets/" + asset, "name": asset.rsplit(".", 1)[0].replace("-", " ").title()} for asset in assets]})
        video = re.search(r"https?://(?:www\.)?youtube\.com/embed/([\w-]+)", html)
        if video:
            schemas.append({"@context": "https://schema.org", "@type": "VideoObject", "name": BRAND + " venue video", "thumbnailUrl": "https://img.youtube.com/vi/" + video.group(1) + "/hqdefault.jpg", "embedUrl": "https://www.youtube.com/embed/" + video.group(1)})
    if slug in {"big-hall", "small-hall", "dining-hall", "vip-dining", "guest-rooms"}:
        facts = {
            "big-hall": ("Big Hall", 1200, "big-hall-primary.webp"),
            "small-hall": ("Small Hall", 350, "small-hall.webp"),
            "dining-hall": ("Dining Hall", 400, "dining-hall.webp"),
            "vip-dining": ("VIP A/C Dining", 50, "vip-dining.webp"),
            "guest-rooms": ("A/C Guest Rooms", 8, "guest-room.webp"),
        }[slug]
        schemas.append({"@context": "https://schema.org", "@type": "EventVenue", "name": facts[0] + " at " + BRAND, "containedInPlace": {"@type": "EventVenue", "name": BRAND, "address": {"@type": "PostalAddress", "streetAddress": "Yashwantrao Chavan Smarak, Sector 27A, Pradhikaran", "addressLocality": "Nigdi, Pune", "addressRegion": "Maharashtra", "postalCode": "411044", "addressCountry": "IN"}}, "maximumAttendeeCapacity": facts[1], "amenityFeature": [{"@type": "LocationFeatureSpecification", "name": "Air conditioning", "value": True}], "image": {"@type": "ImageObject", "contentUrl": BASE + "/assets/" + facts[2]}})
    return schemas


def render_landing(slug, data):
    title, desc = META[slug]
    title, desc = normalized_title(title), normalized_description(desc)
    canonical = f"{BASE}/{slug}"
    faq_html = "\n".join(f"<details class=\"faq-item\"><summary>{escape(q)}</summary><div class=\"faq-answer\"><p>{escape(a)}</p></div></details>" for q, a in data["faqs"])
    faq_schema = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in data["faqs"]]}
    nav_links = "".join(f'<li><a href="{href}">{label}</a></li>' for href, label in [("/big-hall", "Big Hall"), ("/small-hall", "Small Hall"), ("/facilities", "Facilities"), ("/gallery", "Gallery"), ("/visit", "Venue visit"), ("/faq", "All venue FAQs")])
    fit = escape(data["fit"])
    plan = escape(data["plan"])
    access = escape(data["access"])
    quick = f"{data['recommend']}; {data['capacity']}; address: {ADDRESS}; hours: Mon–Sun, 9:00 AM–10:00 PM."
    return f'''<!doctype html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#f6f1e8" /><meta name="description" content="{escape(desc, quote=True)}" />
<link rel="canonical" href="{canonical}" /><meta property="og:type" content="website" /><meta property="og:site_name" content="{BRAND}" />
<meta property="og:title" content="{escape(title, quote=True)}" /><meta property="og:description" content="{escape(desc, quote=True)}" /><meta property="og:url" content="{canonical}" /><meta property="og:image" content="{BASE}/assets/hero-hall.webp" />
<meta name="twitter:card" content="summary_large_image" /><meta name="twitter:title" content="{escape(title, quote=True)}" /><meta name="twitter:description" content="{escape(desc, quote=True)}" /><meta name="twitter:image" content="{BASE}/assets/hero-hall.webp" />
<title>{escape(title)}</title><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml" /><link rel="stylesheet" href="/styles.css" /><script src="/script.js" defer></script>
{json_script(faq_schema)}</head>
<body class="solid-header"><main id="main-content"><section class="legal-head"><div class="shell"><nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span class="sep" aria-hidden="true">/</span><span aria-current="page">{escape(data['label'])}</span></nav><p class="eyebrow">{escape(data['label'])} · Nigdi, Pune</p><h1>{escape(data['label'])} in Nigdi, Pune</h1><p class="lede">{escape(data['intro'])}</p></div></section>
<section class="section"><div class="shell"><div class="prose"><h2>Which hall may fit your {escape(data['event'])}?</h2><p>{fit}</p><div class="compare-wrap"><table class="compare-table"><thead><tr><th scope="col">Space</th><th scope="col">Published capacity</th><th scope="col">How to choose</th></tr></thead><tbody><tr><td><a href="/big-hall">Big Hall</a></td><td>Up to 1,200 guests</td><td>Ask about this option for larger guest lists.</td></tr><tr><td><a href="/small-hall">Small Hall</a></td><td>Up to 350 guests</td><td>Ask about this option for mid-sized gatherings.</td></tr><tr><td><a href="/dining-hall">Dining Hall</a></td><td>400 at a time</td><td>Separate dining space; confirm meal timing.</td></tr></tbody></table></div>
<h2>Plan the event details</h2><p>{plan}</p><h2>Facilities and practical arrangements</h2><p>{access}</p><h2>Quick facts</h2><ul><li>Recommended starting point: {escape(data['recommend'])} ({escape(data['capacity'])})</li><li>Location: {escape(ADDRESS)}</li><li>Hours: Monday to Sunday, 9:00 AM to 10:00 PM</li><li>Ask about pricing, packages and date availability directly; they are not published here.</li></ul>
<h2>How to enquire about the venue</h2><ol><li><a href="/check-availability">Send an availability enquiry</a> with the event type, date and expected guest count.</li><li><a href="/visit">Arrange a venue visit</a> if you would like to see the spaces.</li><li>Wait for the venue team to confirm availability and booking details.</li></ol>
<h2>Questions about this venue</h2><div class="faq-list">{faq_html}</div><h2>Explore the venue</h2><ul>{nav_links}</ul>
<p>Call the venue: <a href="tel:+919359567494">9359567494</a>, <a href="tel:+919834005348">9834005348</a>, <a href="tel:+919527926303">9527926303</a>. <a href="https://wa.me/919359567494" target="_blank" rel="noopener noreferrer">WhatsApp the venue</a>.</p>
<p><a class="button button-wine" href="/check-availability">Check Availability</a></p><p>Pricing is not listed online. Contact the team for a quote. A submitted enquiry is not a confirmed booking.</p></div></div></section></main></body></html>'''


def render_faq():
    title, desc = META["faq"]
    title, desc = normalized_title(title), normalized_description(desc)
    groups = []
    all_faqs = []
    for heading, rows in FAQ_GROUPS.items():
        items = "".join(f'<details class="faq-item"><summary>{escape(q)}</summary><div class="faq-answer"><p>{escape(a)}</p></div></details>' for q, a in rows)
        groups.append(f'<section class="space-block"><h2>{escape(heading)}</h2><div class="faq-list">{items}</div></section>')
        all_faqs.extend(rows)
    canon = BASE + "/faq"
    structured = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in all_faqs]}
    return f'''<!doctype html><html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta name="description" content="{escape(desc, quote=True)}" /><link rel="canonical" href="{canon}" /><meta property="og:type" content="website" /><meta property="og:site_name" content="{BRAND}" /><meta property="og:title" content="{escape(title, quote=True)}" /><meta property="og:description" content="{escape(desc, quote=True)}" /><meta property="og:url" content="{canon}" /><meta property="og:image" content="{BASE}/assets/hero-hall.webp" /><meta name="twitter:card" content="summary_large_image" /><meta name="twitter:title" content="{escape(title, quote=True)}" /><meta name="twitter:description" content="{escape(desc, quote=True)}" /><meta name="twitter:image" content="{BASE}/assets/hero-hall.webp" /><title>{escape(title)}</title><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml" /><link rel="stylesheet" href="/styles.css" /><script src="/script.js" defer></script>{json_script(structured)}</head><body class="solid-header"><main id="main-content"><section class="legal-head"><div class="shell"><nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span class="sep" aria-hidden="true">/</span><span aria-current="page">FAQs</span></nav><p class="eyebrow">Venue information</p><h1>Frequently asked venue questions</h1><p class="lede">Quick, direct answers about halls, capacities, dining, rooms, facilities, location and the enquiry process at {BRAND}.</p></div></section><section class="section"><div class="shell prose">{''.join(groups)}<h2>Contact the venue</h2><p>{ADDRESS}. Open Monday to Sunday, 9:00 AM to 10:00 PM. Call <a href="tel:+919359567494">9359567494</a>, <a href="tel:+919834005348">9834005348</a> or <a href="tel:+919527926303">9527926303</a>. <a href="/check-availability">Check availability</a> to send an enquiry.</p></div></section></main></body></html>'''


def update_homepage(root: Path):
    path = root / "index.html"
    text = path.read_text(encoding="utf-8")
    text = re.sub(r"<h1>Banquet Hall for<br class=\"hero-h1-break\" /> Every Celebration\.", '<h1>Banquet &amp; Wedding Hall in Nigdi, Pune<br class="hero-h1-break" />', text, count=1)
    text = re.sub(r'<meta name="description" content="[^"]*"\s*/>', '<meta name="description" content="' + escape(META["" ][1], quote=True) + '" />', text, count=1)
    text = re.sub(r"<title>.*?</title>", "<title>" + escape(normalized_title(META[""][0])) + "</title>", text, count=1, flags=re.S)
    for key, value in (("og:title", normalized_title(META[""][0])), ("twitter:title", normalized_title(META[""][0]))):
        text = re.sub(r'(<meta\s+(?:property|name)="' + re.escape(key) + r'"\s+content=")[^"]*("\s*/?>)', lambda m: m.group(1) + escape(value, quote=True) + m.group(2), text, count=1)
    homepage_desc = normalized_description(META[""][1])
    text = re.sub(r'<meta name="description" content="[^"]*"\s*/>', '<meta name="description" content="' + escape(homepage_desc, quote=True) + '" />', text, count=1)
    for key, value in (("og:description", homepage_desc), ("twitter:description", homepage_desc)):
        text = re.sub(r'(<meta\s+(?:property|name)="' + re.escape(key) + r'"\s+content=")[^"]*("\s*/?>)', lambda m: m.group(1) + escape(value, quote=True) + m.group(2), text, count=1)
    if "rel=\"preload\" as=\"image\"" not in text:
        text = text.replace('<title>', '<link rel="preload" as="image" href="/assets/hero-hall.webp" fetchpriority="high" />\n  <title>', 1)
    text = text.replace('srcset="/assets/hero-hall.webp" type="image/webp" /><img src="/assets/hero-hall.jpg" alt="Big Hall with chandeliers and rows of seats"', 'srcset="/assets/big-hall-primary.webp" type="image/webp" /><img src="/assets/big-hall-primary.jpg" alt="Big Hall wedding setup in Nigdi, Pune"', 1)
    text = text.replace('<label class="booking-trap" aria-hidden="true">Leave this empty<input name="website" tabindex="-1" autocomplete="off" /></label>', '<label class="booking-trap" aria-hidden="true" tabindex="-1">Leave this empty<input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" /></label>')
    if 'class="hero-facts"' not in text:
        text = text.replace('<p class="hero-place"><svg', '<p class="hero-facts">Big Hall up to 1,200 · Small Hall up to 350 · Dining for 400 · Up to 8 A/C guest rooms</p>\n          <p class="hero-place"><svg', 1)
    path.write_text(text, encoding="utf-8")


def update_home_schema(root: Path):
    path = root / "index.html"
    text = path.read_text(encoding="utf-8")
    scripts = list(re.finditer(r'<script type="application/ld\+json">(.*?)</script>', text, re.S))
    if not scripts:
        return
    graph = json.loads(scripts[0].group(1))
    graph["@type"] = ["LocalBusiness", "EventVenue"]
    graph["additionalType"] = "https://schema.org/BanquetHall"
    graph["image"] = [BASE + "/assets/" + x for x in ("hero-hall.webp", "big-hall-primary.webp", "small-hall.webp", "dining-hall.webp", "vip-dining.webp", "guest-room.webp", "venue-exterior.webp")]
    graph["logo"] = BASE + "/assets/logo.webp"
    graph["telephone"] = PHONE
    graph.pop("priceRange", None)
    graph["geo"] = {"@type": "GeoCoordinates", "latitude": 18.651294, "longitude": 73.770191}
    graph["openingHoursSpecification"] = [{"@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"], "opens": "09:00", "closes": "22:00"}]
    graph["maximumAttendeeCapacity"] = 1200
    graph["amenityFeature"] = [{"@type": "LocationFeatureSpecification", "name": name, "value": True} for name in ("Air conditioning", "Acoustic system", "LED screen", "Projector", "Wi-Fi", "Parking", "CCTV", "Fire alarm", "Lift", "Lobby", "Catering support for own caterer", "A/C guest rooms")]
    graph["hasMap"] = "https://www.google.com/maps/search/?api=1&query=Late+Venutai+Chavan+Multipurpose+Hall"
    graph["sameAs"] = [graph["hasMap"]]
    if len(scripts) > 1:
        second = json.loads(scripts[1].group(1))
        for node in second.get("@graph", []):
            if node.get("@type") == "Organization":
                node["logo"] = BASE + "/assets/logo.webp"
                node["telephone"] = PHONE
                node["sameAs"] = [graph["hasMap"]]
            if node.get("@type") == "FAQPage":
                visible = VisibleFaqParser()
                visible.feed(text)
                node["mainEntity"] = [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in visible.pairs]
        video = re.search(r"https?://(?:www\.)?youtube\.com/embed/([\w-]+)", text)
        if video and not any(node.get("@type") == "VideoObject" for node in second.get("@graph", [])):
            second.setdefault("@graph", []).append({"@type": "VideoObject", "name": BRAND + " venue video", "description": "Video tour of the venue spaces.", "thumbnailUrl": "https://img.youtube.com/vi/" + video.group(1) + "/hqdefault.jpg", "embedUrl": "https://www.youtube.com/embed/" + video.group(1)})
        s2 = '<script type="application/ld+json">' + json.dumps(second, ensure_ascii=False, indent=2) + "</script>"
        text = text[:scripts[1].start()] + s2 + text[scripts[1].end():]
        scripts = list(re.finditer(r'<script type="application/ld\+json">(.*?)</script>', text, re.S))
    s1 = '<script type="application/ld+json">' + json.dumps(graph, ensure_ascii=False, indent=2) + "</script>"
    text = text[:scripts[0].start()] + s1 + text[scripts[0].end():]
    path.write_text(text, encoding="utf-8")


def update_page_meta(root: Path):
    css_hash = hashlib.md5((root / "styles.css").read_bytes()).hexdigest()[:8]
    js_hash = hashlib.md5((root / "script.js").read_bytes()).hexdigest()[:8]
    for path in root.glob("*.html"):
        slug = path.stem
        if slug in {"403", "404", "500", "deploy-test", "our-spaces"}:
            continue
        key = "" if slug == "index" else slug
        if key not in META:
            continue
        title, desc = META[key]
        title, desc = normalized_title(title), normalized_description(desc)
        canonical_path = "/" if not key else "/" + key
        canonical = BASE + canonical_path
        text = path.read_text(encoding="utf-8")
        text = re.sub(r"<title>.*?</title>", "<title>" + escape(title) + "</title>", text, count=1, flags=re.S)
        text = re.sub(r'<meta\s+name="description"\s+content="[^"]*"\s*/?>', '<meta name="description" content="' + escape(desc, quote=True) + '" />', text, count=1)
        text = re.sub(r'<link\s+rel="canonical"\s+href="[^"]*"\s*/?>', '<link rel="canonical" href="' + canonical + '" />', text, count=1)
        for attr, name, value in (("property", "og:title", title), ("property", "og:description", desc), ("property", "og:url", canonical), ("name", "twitter:title", title), ("name", "twitter:description", desc)):
            pattern = r'<meta\s+' + attr + r'="' + re.escape(name) + r'"\s+content="[^"]*"\s*/?>'
            replacement = f'<meta {attr}="{name}" content="{escape(value, quote=True)}" />'
            if re.search(pattern, text):
                text = re.sub(pattern, replacement, text, count=1)
        if "<meta name=\"viewport\"" not in text:
            text = text.replace("<head>", '<head>\n<meta name="viewport" content="width=device-width, initial-scale=1.0" />', 1)
        main_content = re.search(r"<main\b.*?</main>", text, re.S)
        hero_image = re.search(r'<source\s+srcset="(/assets/[^" ]+\.webp)"', main_content.group(0) if main_content else "")
        if hero_image and 'rel="preload" as="image"' not in text:
            text = text.replace("</head>", f'<link rel="preload" as="image" href="{hero_image.group(1)}" fetchpriority="high" />\n</head>', 1)
        text = re.sub(r'href="(/styles\.css)(?:\?[^" ]*)?"', rf'href="\1?v={css_hash}"', text)
        text = re.sub(r'src="(/script\.js)(?:\?[^" ]*)?"', rf'src="\1?v={js_hash}"', text)
        replacements = {
            'href="weddings"': 'href="/wedding-hall-nigdi-pune"', 'href="weddings.html"': 'href="/wedding-hall-nigdi-pune"',
            'href="engagements"': 'href="/engagement-hall-nigdi-pune"', 'href="engagements.html"': 'href="/engagement-hall-nigdi-pune"',
            'href="birthdays"': 'href="/birthday-party-hall-nigdi-pune"', 'href="birthdays.html"': 'href="/birthday-party-hall-nigdi-pune"',
            'href="corporate-events"': 'href="/corporate-event-venue-nigdi-pune"', 'href="corporate-events.html"': 'href="/corporate-event-venue-nigdi-pune"',
            'href="receptions"': 'href="/reception-hall-pradhikaran-pune"', 'href="receptions.html"': 'href="/reception-hall-pradhikaran-pune"',
        }
        for old, new in replacements.items():
            text = text.replace(old, new)
        if "<meta name=\"robots\"" not in text:
            schemas = page_schema(key, text)
            if slug == "index" and re.search(r'"@type"\s*:\s*"WebSite"', text):
                schemas = [schema for schema in schemas if schema.get("@type") != "WebSite"]
            if schemas:
                text = text.replace("</head>", "\n".join(json_script(x) for x in schemas) + "\n</head>", 1)
        if key in REDIRECTS:
            target = "/" + REDIRECTS[key]
            text = text.replace('href="weddings"', 'href="/wedding-hall-nigdi-pune"').replace('href="engagements"', 'href="/engagement-hall-nigdi-pune"')
            text = text.replace('href="birthdays"', 'href="/birthday-party-hall-nigdi-pune"').replace('href="corporate-events"', 'href="/corporate-event-venue-nigdi-pune"').replace('href="receptions"', 'href="/reception-hall-pradhikaran-pune"')
        if key in {"big-hall", "small-hall", "dining-hall", "vip-dining", "guest-rooms"}:
            related = '<section class="section-tight"><div class="shell"><h2>Related venue information</h2><p><a href="/events">Events</a> · <a href="/facilities">Facilities</a> · <a href="/gallery">Gallery</a> · <a href="/visit">Venue visit</a> · <a href="/check-availability">Check availability</a></p></div></section>'
            text = text.replace("</main>", related + "\n</main>", 1)
        if key in {"weddings", "engagements", "birthdays", "corporate-events", "receptions", "naming-ceremonies", "family-functions", "social-gatherings"}:
            cross = '<p class="related-links"><a href="/big-hall">Big Hall</a> · <a href="/small-hall">Small Hall</a> · <a href="/check-availability">Check availability</a></p>'
            text = text.replace("</main>", cross + "\n</main>", 1)
        if key == "spaces":
            detail_links = '<section class="section-tight"><div class="shell"><p class="eyebrow">More ways to plan</p><p class="related-links"><a href="/banquet-hall-nigdi-pune">Banquet hall in Nigdi</a> · <a href="/hall-with-ac-rooms-nigdi-pune">Hall with A/C guest rooms</a> · <a href="/faq">Venue FAQs</a></p></div></section>'
            text = text.replace("</main>", detail_links + "\n</main>", 1)
        answer = ANSWER_SUMMARIES.get(key)
        if answer:
            answer_markup = '<p class="lede answer-summary">' + escape(answer) + '</p>'
            if answer_markup in text:
                while text.count(answer_markup) > 1:
                    text = text.replace(answer_markup + answer_markup, answer_markup, 1)
            else:
                text = re.sub(r"(<h1\b[^>]*>.*?</h1>)", lambda m: m.group(1) + answer_markup, text, count=1, flags=re.S)
        text = re.sub(r'(<iframe\b(?![^>]*\bloading=)[^>]*)(>)', r'\1 loading="lazy"\2', text, flags=re.I)
        path.write_text(text, encoding="utf-8")


def build_robots():
    agents = ["GPTBot", "OAI-SearchBot", "ChatGPT-User", "ClaudeBot", "Claude-SearchBot", "PerplexityBot", "Google-Extended", "Applebot-Extended", "CCBot"]
    group = "\n".join("User-agent: " + agent for agent in agents)
    return """User-agent: *
Allow: /
Disallow: /.original-assets/
Disallow: /.build/
Disallow: /admin/
Disallow: /booking.php
Disallow: /popup.php
Disallow: /deploy-test
Disallow: /thank-you
Disallow: /server-patch/
Disallow: /_removed/

""" + group + """
Allow: /
Disallow: /.original-assets/
Disallow: /.build/
Disallow: /admin/
Disallow: /booking.php
Disallow: /popup.php
Disallow: /deploy-test
Disallow: /thank-you
Disallow: /server-patch/
Disallow: /_removed/

Sitemap: https://venutaihall.com/sitemap.xml
"""


def build_htaccess(root: Path):
    old = (root / ".htaccess").read_text(encoding="utf-8")
    start = old.find("RewriteEngine On")
    end = old.find("ErrorDocument 403")
    rules = r'''RewriteEngine On
RewriteRule ^\.original-assets(?:/|$) - [F,L]
RewriteRule ^\.build(?:/|$) - [F,L]

# Canonical host, HTTPS and extensionless URLs.
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L,NE]
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^ https://%1%{REQUEST_URI} [R=301,L,NE]
RewriteRule ^index\.html$ / [R=301,L,NC]
RewriteRule ^our-spaces(?:\.html)?/?$ /spaces [R=301,L,NC]
RewriteRule ^weddings(?:\.html)?/?$ /wedding-hall-nigdi-pune [R=301,L,NC]
RewriteRule ^engagements(?:\.html)?/?$ /engagement-hall-nigdi-pune [R=301,L,NC]
RewriteRule ^birthdays(?:\.html)?/?$ /birthday-party-hall-nigdi-pune [R=301,L,NC]
RewriteRule ^corporate-events(?:\.html)?/?$ /corporate-event-venue-nigdi-pune [R=301,L,NC]
RewriteRule ^receptions(?:\.html)?/?$ /reception-hall-pradhikaran-pune [R=301,L,NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^assets/(logo|logo-sepia)\.png$ assets/$1.webp [L,NC]
RewriteCond %{THE_REQUEST} \s/+([^.?\s]+)\.html(?:[?\s]) [NC]
RewriteRule ^(.+)\.html$ /$1 [R=301,L,NE]

# Internally serve clean URLs from their source HTML files.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.+?)/?$ $1.html [L]

'''
    if start >= 0 and end > start:
        old = old[:start] + rules + old[end:]
    else:
        old = rules + old
    old = old.replace('ExpiresByType image/jpeg "access plus 6 months"', 'ExpiresByType image/jpeg "access plus 6 months"\n  ExpiresByType image/png "access plus 6 months"\n  ExpiresByType image/avif "access plus 6 months"')
    old = old.replace('ExpiresByType image/webp "access plus 6 months"', 'ExpiresByType image/webp "access plus 6 months"\n  ExpiresByType text/html "access plus 0 seconds"')
    old += '\n<IfModule mod_brotli.c>\n  AddOutputFilterByType BROTLI_COMPRESS text/html text/css text/javascript application/javascript application/json image/svg+xml\n</IfModule>\n'
    old = old.replace('<IfModule mod_expires.c>', '<IfModule mod_headers.c>\n  <FilesMatch "^(403|404|500|deploy-test)\\.html$">\n    Header always set X-Robots-Tag "noindex, nofollow"\n  </FilesMatch>\n  <FilesMatch "^(booking|popup)\\.php$">\n    Header always set X-Robots-Tag "noindex, nofollow"\n  </FilesMatch>\n</IfModule>\n\n<IfModule mod_expires.c>')
    (root / ".htaccess").write_text(old, encoding="utf-8")
    admin_rules = root / "admin" / ".htaccess"
    admin_text = admin_rules.read_text(encoding="utf-8") if admin_rules.exists() else ""
    if "X-Robots-Tag" not in admin_text:
        admin_text += '\n<IfModule mod_headers.c>\n  Header always set X-Robots-Tag "noindex, nofollow, noarchive"\n</IfModule>\n'
    admin_rules.write_text(admin_text, encoding="utf-8")


def update_images_and_minified_links(root: Path):
    dimensions_path = root / ".build" / "image-dimensions.json"
    dimensions = json.loads(dimensions_path.read_text(encoding="utf-8")) if dimensions_path.exists() else {}
    asset_ref = re.compile(r"((?:/assets/|assets/)[^\"'\s?#)]+)\.(?:jpe?g|png)\b", re.I)
    img_tag = re.compile(r"<img\b[^>]*>", re.I)

    def add_attr(tag, attr):
        if tag.rstrip().endswith("/>"):
            pos = tag.rfind("/>")
            return tag[:pos] + attr + tag[pos:]
        return tag[:-1] + attr + ">"

    for path in root.glob("*.html"):
        text = path.read_text(encoding="utf-8")
        text = re.sub(r'<div class="hall-row" data-placeholder="CONFIRM with client: small hall dining details">.*?</div>', "", text, flags=re.I | re.S)
        text = re.sub(r'<div class="facility-bullet-group[^>]*data-placeholder="CONFIRM with client: small hall dining details"[^>]*>.*?</div>', "", text, flags=re.I | re.S)
        protected = []
        for start_marker, end_marker in (
            ('<header class="site-header"', '</header>'),
            ('<div class="nav-drawer"', '<main id="main-content">'),
            ('<div class="float-actions"', '<nav class="bottom-bar"'),
            ('<nav class="bottom-bar"', '</nav>'),
        ):
            start = text.find(start_marker)
            end = text.find(end_marker, start) if start >= 0 else -1
            if start >= 0 and end >= 0:
                protected.append((start, end + len(end_marker)))
        chunks = []
        cursor = 0
        for start, end in sorted(protected):
            if start < cursor:
                continue
            chunks.append(asset_ref.sub(r"\1.webp", text[cursor:start]))
            chunks.append(text[start:end])
            cursor = end
        chunks.append(asset_ref.sub(r"\1.webp", text[cursor:]))
        text = "".join(chunks)
        text = re.sub(r"\sdata-placeholder=([\"']).*?\1", "", text, flags=re.I)
        text = re.sub(r"<!--(?:(?!-->).)*(?:placeholder|todo|lorem|xxx)(?:(?!-->).)*-->", "", text, flags=re.I | re.S)
        main_start = text.find("<main")

        def update_img(match):
            tag = match.group(0)
            src_match = re.search(r"\bsrc=['\"](?:/)?assets/([^'\"]+)['\"]", tag, re.I)
            size = dimensions.get(src_match.group(1)) if src_match else None
            if size:
                if not re.search(r"\bwidth=['\"]\d+['\"]", tag, re.I):
                    tag = add_attr(tag, f' width="{size["width"]}"')
                if not re.search(r"\bheight=['\"]\d+['\"]", tag, re.I):
                    tag = add_attr(tag, f' height="{size["height"]}"')
            in_main = main_start >= 0 and match.start() > main_start and match.start() < text.find("</main>", main_start)
            footer_start = text.find('<footer class="site-footer"')
            in_footer = footer_start >= 0 and match.start() > footer_start
            if (in_main or in_footer) and not re.search(r"\bloading=", tag, re.I) and not re.search(r"\bfetchpriority=['\"]high", tag, re.I):
                tag = add_attr(tag, ' loading="lazy"')
            return tag

        text = img_tag.sub(update_img, text)
        text = re.sub(r"href=([\"'])/?styles\.css(?:\?[^\"']*)?\1", 'href="/styles.css"', text, flags=re.I)
        text = re.sub(r"src=([\"'])/?script\.js(?:\?[^\"']*)?\1", 'src="/script.js"', text, flags=re.I)
        path.write_text(text, encoding="utf-8")

    for name in ("styles.css", "script.js"):
        path = root / name
        text = path.read_text(encoding="utf-8")
        path.write_text(asset_ref.sub(r"\1.webp", text), encoding="utf-8")

    subprocess.run(["node", "build-assets.js", "minify"], cwd=root, check=True)
    css_hash = hashlib.sha256((root / "styles.min.css").read_bytes()).hexdigest()[:10]
    js_hash = hashlib.sha256((root / "script.min.js").read_bytes()).hexdigest()[:10]
    for path in root.glob("*.html"):
        text = path.read_text(encoding="utf-8")
        text = re.sub(r"href=([\"'])/?styles\.css(?:\?[^\"']*)?\1", f'href="/styles.min.css?v={css_hash}"', text, flags=re.I)
        text = re.sub(r"src=([\"'])/?script\.js(?:\?[^\"']*)?\1", f'src="/script.min.js?v={js_hash}"', text, flags=re.I)
        path.write_text(text, encoding="utf-8")


def build_sitemap(root: Path):
    public = ["", "about", "big-hall", "small-hall", "dining-hall", "vip-dining", "guest-rooms", "spaces", "compare", "events", "wedding-hall-nigdi-pune", "banquet-hall-nigdi-pune", "engagement-hall-nigdi-pune", "birthday-party-hall-nigdi-pune", "corporate-event-venue-nigdi-pune", "reception-hall-pradhikaran-pune", "hall-with-ac-rooms-nigdi-pune", "naming-ceremonies", "family-functions", "social-gatherings", "facilities", "everything-included", "gallery", "visit", "contact", "check-availability", "faq", "details", "privacy-policy", "terms"]
    today = date.today().isoformat()
    entries = []
    for slug in public:
        route = "/" if not slug else "/" + slug
        entries.append(f"  <url><loc>{BASE}{route}</loc><lastmod>{today}</lastmod></url>")
    doc = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + "\n".join(entries) + "\n</urlset>\n"
    (root / "sitemap.xml").write_text(doc, encoding="utf-8")


def checklist():
    return """# SEO / AEO / GEO rollout checklist

Code-side work is implemented. Complete these owner and platform steps before treating organic visibility as verified.

## Search platforms
- [ ] Add and verify `https://venutaihall.com/` as a Domain property in Google Search Console.
- [ ] Submit `https://venutaihall.com/sitemap.xml`; inspect indexing and request indexing for the main pages.
- [ ] Verify the property in Bing Webmaster Tools, import Search Console where possible, and submit the sitemap.
- [ ] Recheck robots access, canonical selection and redirects after deploying to production.

## Business profile and citations
- [ ] Confirm the name, address, all three phone numbers and hours match the Google Business Profile exactly.
- [ ] Add accurate photos, services, venue categories, hours, questions and an enquiry/WhatsApp URL with campaign tracking to the Business Profile.
- [ ] Update Justdial, Sulekha, IndiaMART, WedMeGood, WeddingWire, Venuebookingz and Bookeventz with the same verified NAP.
- [ ] Supply official Google Maps, Instagram, Facebook and YouTube profile URLs before adding them as `sameAs` links.

## Owner facts to confirm (do not publish estimates)
- [ ] Confirm Google Maps coordinates against the official Business Profile (source map currently encodes 18.651294, 73.770191).
- [ ] Provide current hall/room starting prices and package inclusions, or confirm that all quotes are enquiry-only.
- [ ] Provide links and permission for genuine Google reviews; do not add fabricated ratings or testimonials.
- [ ] Confirm nearest bus, metro and railway information and parking arrangements before publishing directions.
- [ ] Confirm who Late Venutai Chavan was honoured as, years in operation and any verifiable events hosted.
- [ ] Confirm the actual room count, occupancy, terms and room rates before giving more detail than “up to 8 A/C guest rooms”.

## Analytics and reputation
- [ ] Configure GA4 and test call click, WhatsApp click, enquiry submit and availability submit events.
- [ ] Create a venue QR/review link and ask real customers for feedback after events; never gate or incentivize reviews.
- [ ] Seek relevant links from local Pune wedding publications and real venue partners such as caterers, decorators and photographers.
- [ ] Recheck Core Web Vitals in PageSpeed Insights/Search Console on deployed pages; LCP/CLS/INP targets are goals, not locally guaranteed results.
"""


def run(root: Path):
    root = Path(root)
    subprocess.run(["node", "build-assets.js", "images"], cwd=root, check=True)
    update_homepage(root)
    # The 404 page is already noindex; add useful route links without changing its layout.
    error = root / "404.html"
    et = error.read_text(encoding="utf-8")
    if 'href="/big-hall"' not in et:
        et = et.replace('<a class="error-button" href="/check-availability">Check availability</a>', '<a class="error-button" href="/check-availability">Check availability</a><a class="error-button" href="/big-hall">Big Hall</a><a class="error-button" href="/events">Events</a><a class="error-button" href="/contact">Contact</a>')
    error.write_text(et, encoding="utf-8")
    # Mark utility document as non-indexable; real admin pages also require the existing PHP session gate.
    test = root / "deploy-test.html"
    if test.exists():
        tt = test.read_text(encoding="utf-8")
        if 'name="robots"' not in tt:
            tt = tt.replace("<head>", '<head><meta name="robots" content="noindex,nofollow" />', 1)
        test.write_text(tt, encoding="utf-8")
    for slug, info in LANDINGS.items():
        (root / (slug + ".html")).write_text(render_landing(slug, info), encoding="utf-8")
    (root / "faq.html").write_text(render_faq(), encoding="utf-8")
    wrap_new_pages(root)
    update_page_meta(root)
    update_home_schema(root)
    update_images_and_minified_links(root)
    (root / "robots.txt").write_text(build_robots(), encoding="utf-8")
    build_htaccess(root)
    build_sitemap(root)
    llms_content = f"""# {BRAND}

> Banquet and event venue at {ADDRESS}. Big Hall up to 1,200 guests, Small Hall up to 350, Dining Hall 400 at a time, VIP A/C Dining 50, up to 8 A/C guest rooms. Open Monday to Sunday, 9:00 AM to 10:00 PM.

Facilities: air-conditioning, acoustic system, LED screen, projector, Wi-Fi, parking, CCTV, fire alarm, lift, lobby, catering support for your own caterer.
Events: weddings, engagements, receptions, birthdays, naming ceremonies, family functions, corporate events, social gatherings.
Contact: Jadhav Kedar 9359567494; Jadhav Prashant 9834005348; Shinde Vikas 9527926303.
Pricing is not published; contact the venue for a quote. An enquiry does not confirm a booking.
Last updated: 2026-10-16

## Core pages
- [Home](https://venutaihall.com/): venue summary and spaces
- [Big Hall](https://venutaihall.com/big-hall): capacity up to 1,200 guests, details
- [Small Hall](https://venutaihall.com/small-hall): capacity up to 350 guests, details
- [Facilities](https://venutaihall.com/facilities): listed amenities
- [Everything Included](https://venutaihall.com/everything-included): what the venue provides
- [Events](https://venutaihall.com/events): events supported by the venue
- [FAQ](https://venutaihall.com/faq): answers about facilities, capacity and enquiries
- [Gallery](https://venutaihall.com/gallery): venue photos
- [Contact](https://venutaihall.com/contact): address, phones, enquiry
- [Check Availability](https://venutaihall.com/check-availability): send a date enquiry
- [Venue Visit](https://venutaihall.com/visit): schedule a visit

## Event and location pages
- [Wedding hall in Nigdi, Pune](https://venutaihall.com/wedding-hall-nigdi-pune)
- [Banquet hall in Nigdi, Pune](https://venutaihall.com/banquet-hall-nigdi-pune)
- [Engagement hall in Nigdi, Pune](https://venutaihall.com/engagement-hall-nigdi-pune)
- [Birthday party hall in Nigdi, Pune](https://venutaihall.com/birthday-party-hall-nigdi-pune)
- [Corporate event venue in Nigdi, Pune](https://venutaihall.com/corporate-event-venue-nigdi-pune)
- [Reception hall in Pradhikaran, Pune](https://venutaihall.com/reception-hall-pradhikaran-pune)
- [Hall with A/C rooms in Nigdi, Pune](https://venutaihall.com/hall-with-ac-rooms-nigdi-pune)

## Optional
- [Privacy Policy](https://venutaihall.com/privacy-policy)
- [Terms & Conditions](https://venutaihall.com/terms)
"""
    (root / "llms.txt").write_text(llms_content, encoding="utf-8")
    (root / "SEO-TODO.md").write_text(checklist(), encoding="utf-8")
    print("SEO/AEO/GEO pages, metadata, robots, redirects and sitemap updated")

