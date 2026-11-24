<?php
declare(strict_types=1);

// WhatsApp auto-reply in Marathi, Hindi and English: menu, free dates, hall FAQ, a 3-step booking enquiry,
// and a "shall our team call you?" yes/no whenever the bot does not understand, so guests are never left hanging.
// Requires booking-store.php, whatsapp-store.php and leads-store.php to be loaded first.
// Button and list ids carry their own data (e.g. "dm:big:2026-12"); only the booking form keeps state.

// Stored in the database and sent to staff in English; shown to guests in their language.
const WA_BOT_EVENTS = ['Wedding', 'Engagement', 'Reception', 'Birthday', 'Naming Ceremony', 'Family Function', 'Corporate Event', 'Social Gathering', 'Venue Visit', 'Other Event'];
const WA_BOT_EVENT_NAMES = [
    'hi' => ['शादी', 'सगाई', 'रिसेप्शन', 'जन्मदिन', 'नामकरण', 'पारिवारिक कार्यक्रम', 'कॉर्पोरेट इवेंट', 'सामाजिक कार्यक्रम', 'हॉल देखना', 'अन्य कार्यक्रम'],
    'mr' => ['लग्न', 'साखरपुडा', 'रिसेप्शन', 'वाढदिवस', 'बारसे / नामकरण', 'कौटुंबिक कार्यक्रम', 'कॉर्पोरेट कार्यक्रम', 'सामाजिक कार्यक्रम', 'हॉल पाहणी', 'इतर कार्यक्रम'],
];
const WA_BOT_HALLS = ['big' => 'Big Hall', 'small' => 'Small Hall'];
const WA_BOT_HALL_NAMES = ['en' => ['big' => 'Big Hall', 'small' => 'Small Hall'], 'hi' => ['big' => 'बड़ा हॉल', 'small' => 'छोटा हॉल'], 'mr' => ['big' => 'मोठा हॉल', 'small' => 'छोटा हॉल']];
const WA_BOT_GUESTS = [50 => 'Up to 50', 100 => 'Up to 100', 200 => 'Up to 200', 350 => 'Up to 350', 500 => 'Up to 500', 800 => '500 or more'];
const WA_BOT_GUEST_NAMES = [
    'hi' => [50 => '50 तक', 100 => '100 तक', 200 => '200 तक', 350 => '350 तक', 500 => '500 तक', 800 => '500 से ज़्यादा'],
    'mr' => [50 => '50 पर्यंत', 100 => '100 पर्यंत', 200 => '200 पर्यंत', 350 => '350 पर्यंत', 500 => '500 पर्यंत', 800 => '500 पेक्षा जास्त'],
];
const WA_BOT_MONTHS = [
    'hi' => ['जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितंबर', 'अक्टूबर', 'नवंबर', 'दिसंबर'],
    'mr' => ['जानेवारी', 'फेब्रुवारी', 'मार्च', 'एप्रिल', 'मे', 'जून', 'जुलै', 'ऑगस्ट', 'सप्टेंबर', 'ऑक्टोबर', 'नोव्हेंबर', 'डिसेंबर'],
];
const WA_BOT_STAFF_PAUSE = 43200; // seconds the bot stays in the background after staff write or the guest asks for the team
const WA_BOT_ADDRESS = "Late Venutai Chavan Multipurpose Hall\nYashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune 411044";
const WA_BOT_MAP = 'https://share.google/urV9xBR8XqT2aH8nh'; // the hall's Google listing (map, photos, reviews)
const WA_BOT_SITE = 'https://venutaihall.com/';
const WA_BOT_PIN = ['latitude' => 18.651293965168612, 'longitude' => 73.77019107492065]; // from the website's map embed

// Every guest-facing sentence: key => [en, hi, mr]. {placeholders} are filled by wa_bot_t().
const WA_BOT_TEXT = [
    'welcome' => ['Namaste{name} 🙏 Welcome to Late Venutai Chavan Multipurpose Hall, Nigdi.', 'नमस्ते{name} 🙏 स्व. वेणुताई चव्हाण मल्टीपर्पज़ हॉल, निगडी में आपका स्वागत है।', 'नमस्कार{name} 🙏 स्व. वेणूताई चव्हाण बहुउद्देशीय सभागृह, निगडी येथे आपले स्वागत आहे.'],
    'help' => ['How can we help you?', 'हम आपकी कैसे मदद करें?', 'आम्ही आपली कशी मदत करू?'],
    'privacy' => ['🔒 We use your details only to reply about your booking. Privacy: venutaihall.com/privacy-policy · Reply STOP to opt out.', '🔒 आपकी जानकारी सिर्फ़ बुकिंग के जवाब के लिए उपयोग होती है। Privacy: venutaihall.com/privacy-policy · बंद करने के लिए STOP लिखें।', '🔒 आपली माहिती फक्त बुकिंगच्या उत्तरासाठी वापरली जाते. Privacy: venutaihall.com/privacy-policy · थांबवण्यासाठी STOP लिहा.'],
    'langhint' => ["🌐 मराठीसाठी 'मराठी' लिहा · हिंदी के लिए 'हिंदी' लिखें", "🌐 English? Type 'English' · मराठीसाठी 'मराठी' लिहा", "🌐 English? Type 'English' · हिंदी के लिए 'हिंदी' लिखें"],
    'btn_dates' => ['📅 Available dates', '📅 खाली तारीखें', '📅 उपलब्ध तारखा'],
    'btn_book' => ['📝 Book / Enquire', '📝 बुकिंग / पूछताछ', '📝 बुकिंग / चौकशी'],
    'btn_info' => ['ℹ️ Hall info', 'ℹ️ हॉल की जानकारी', 'ℹ️ हॉलची माहिती'],
    'btn_more' => ['ℹ️ More info', 'ℹ️ और जानकारी', 'ℹ️ अधिक माहिती'],
    'btn_team' => ['📞 Talk to team', '📞 टीम से बात करें', '📞 टीमशी बोला'],
    'btn_menu' => ['🏠 Menu', '🏠 मेनू', '🏠 मेनू'],
    'btn_yes_call' => ['✅ Yes, call me', '✅ हाँ, कॉल करें', '✅ हो, कॉल करा'],
    'btn_no' => ['❌ No, thanks', '❌ नहीं', '❌ नको'],
    'btn_book_date' => ['📝 Book a date', '📝 तारीख बुक करें', '📝 तारीख बुक करा'],
    'btn_other_month' => ['📅 Another month', '📅 दूसरा महीना', '📅 दुसरा महिना'],
    'not_understood' => ["Sorry, I couldn't understand that 🙏\nShall our team call you on this number to help?", "माफ़ कीजिए, मैं समझ नहीं पाया 🙏\nक्या हमारी टीम आपको इसी नंबर पर कॉल करे?", "माफ करा, मला समजले नाही 🙏\nआमच्या टीमने आपल्याला याच नंबरवर कॉल करावा का?"],
    'call_yes' => ["✅ Done! Our team has been told and will call you {when}.\nMeanwhile, you can check dates or hall info here 👇", "✅ हो गया! हमारी टीम को बता दिया है, वे आपको {when} कॉल करेंगे।\nतब तक आप यहाँ तारीखें या हॉल की जानकारी देख सकते हैं 👇", "✅ झाले! आमच्या टीमला कळवले आहे, ते आपल्याला {when} कॉल करतील.\nतोपर्यंत आपण येथे तारखा किंवा हॉलची माहिती पाहू शकता 👇"],
    'call_no' => ['No problem 🙂 Choose an option below, or just type your question.', 'कोई बात नहीं 🙂 नीचे से चुनें, या अपना सवाल लिखें।', 'काही हरकत नाही 🙂 खालीलपैकी निवडा, किंवा आपला प्रश्न लिहा.'],
    'team' => ["✅ Thank you! Our team has been told and will call you {when}.\nFor urgent help call {phone}.", "✅ धन्यवाद! हमारी टीम को बता दिया है, वे आपको {when} कॉल करेंगे।\nज़रूरी हो तो {phone} पर कॉल करें।", "✅ धन्यवाद! आमच्या टीमला कळवले आहे, ते आपल्याला {when} कॉल करतील.\nतातडीसाठी {phone} वर कॉल करा."],
    'ack' => ['🙏 Noted. Our team will call you {when}. For urgent help call {phone}.', '🙏 नोट कर लिया। हमारी टीम आपको {when} कॉल करेगी। ज़रूरी हो तो {phone} पर कॉल करें।', '🙏 नोंद घेतली. आमची टीम आपल्याला {when} कॉल करेल. तातडीसाठी {phone} वर कॉल करा.'],
    'when_soon' => ['within 15 minutes', '15 मिनट के अंदर', '15 मिनिटांत'],
    'when_morning' => ['tomorrow morning after 9 AM', 'कल सुबह 9 बजे के बाद', 'उद्या सकाळी 9 नंतर'],
    'which_hall' => ['Which hall would you like to check?', 'कौन सा हॉल देखना है?', 'कोणता हॉल पाहायचा आहे?'],
    'which_month' => ['{hall}: which month?', '{hall}: कौन सा महीना?', '{hall}: कोणता महिना?'],
    'choose_month' => ['Choose month', 'महीना चुनें', 'महिना निवडा'],
    'free_dates' => ["📅 {hall} – free dates in {month}:\n{days}\n\nDates fill fast. A date is held only after our team confirms the booking.", "📅 {hall} – {month} में खाली तारीखें:\n{days}\n\nतारीखें जल्दी भरती हैं। टीम की पुष्टि के बाद ही तारीख पक्की होती है।", "📅 {hall} – {month} मधील उपलब्ध तारखा:\n{days}\n\nतारखा लवकर भरतात. टीमने पुष्टी केल्यावरच तारीख निश्चित होते."],
    'full_month' => ['😔 {hall} is fully booked in {month}. Please check another month or hall.', '😔 {month} में {hall} पूरा बुक है। कृपया दूसरा महीना या हॉल देखें।', '😔 {month} मध्ये {hall} पूर्ण बुक आहे. कृपया दुसरा महिना किंवा हॉल पहा.'],
    'step1' => ["📝 Booking request — step 1 of 3\nWhat is the occasion?", "📝 बुकिंग — स्टेप 1/3\nकौन सा कार्यक्रम है?", "📝 बुकिंग — पायरी 1/3\nकोणता कार्यक्रम आहे?"],
    'choose_event' => ['Choose event', 'कार्यक्रम चुनें', 'कार्यक्रम निवडा'],
    'step2' => ["Step 2 of 3 – which hall?\n🏛️ Big Hall: large weddings and receptions (up to 1,200)\n🏠 Small Hall: up to 350 guests", "स्टेप 2/3 – कौन सा हॉल?\n🏛️ बड़ा हॉल: बड़ी शादी और रिसेप्शन (1,200 तक)\n🏠 छोटा हॉल: 350 मेहमानों तक", "पायरी 2/3 – कोणता हॉल?\n🏛️ मोठा हॉल: मोठी लग्ने व रिसेप्शन (1,200 पर्यंत)\n🏠 छोटा हॉल: 350 पाहुण्यांपर्यंत"],
    'step3' => ["Step 3 of 3 – please type your event date, for example:\n25-12-2026 or 25 Dec", "स्टेप 3/3 – कार्यक्रम की तारीख लिखें, जैसे:\n25-12-2026 या 25 Dec", "पायरी 3/3 – कार्यक्रमाची तारीख लिहा, उदा.:\n25-12-2026 किंवा 25 Dec"],
    'bad_date' => ["I couldn't read that as a future date 🙏 Please type it like 25-12-2026.\nOr shall our team call you to help?", "यह आगे की तारीख समझ नहीं आई 🙏 कृपया ऐसे लिखें: 25-12-2026\nया हमारी टीम आपको कॉल करे?", "ही पुढची तारीख समजली नाही 🙏 कृपया अशी लिहा: 25-12-2026\nकिंवा आमच्या टीमने आपल्याला कॉल करावा का?"],
    'booked' => ['😔 {hall} is already booked on {date}. Please type another date.', '😔 {date} को {hall} पहले से बुक है। कृपया दूसरी तारीख लिखें।', '😔 {date} रोजी {hall} आधीच बुक आहे. कृपया दुसरी तारीख लिहा.'],
    'good_news' => ["✅ Good news, {name}! {hall} is available on {date}.\n🎉 {event}\nReference: {ref}\n\n📞 Our sales team has been alerted and will call you {when}. The date is held only after the team confirms the booking.\n\nTo help us prepare, about how many guests? (optional)", "✅ खुशखबरी, {name}! {date} को {hall} उपलब्ध है।\n🎉 {event}\nरेफ़रेंस: {ref}\n\n📞 हमारी सेल्स टीम को सूचना दे दी गई है, वे आपको {when} कॉल करेंगे। टीम की पुष्टि के बाद ही तारीख पक्की होगी।\n\nतैयारी के लिए, लगभग कितने मेहमान? (वैकल्पिक)", "✅ आनंदाची बातमी, {name}! {date} रोजी {hall} उपलब्ध आहे.\n🎉 {event}\nरेफरन्स: {ref}\n\n📞 आमच्या सेल्स टीमला कळवले आहे, ते आपल्याला {when} कॉल करतील. टीमने पुष्टी केल्यावरच तारीख निश्चित होईल.\n\nतयारीसाठी, अंदाजे किती पाहुणे? (ऐच्छिक)"],
    'choose_guests' => ['Choose guests', 'मेहमान चुनें', 'पाहुणे निवडा'],
    'guests_noted' => ['👍 Noted: {guests} guests. Our team will have everything ready when they call you.', '👍 नोट किया: {guests} मेहमान। कॉल के समय टीम पूरी तैयारी के साथ बात करेगी।', '👍 नोंद केली: {guests} पाहुणे. कॉलच्या वेळी टीम पूर्ण तयारीने बोलेल.'],
    'thanks' => ['🙏 You are welcome! Anything else? Choose below or type your question.', '🙏 आपका स्वागत है! और कुछ? नीचे से चुनें या सवाल लिखें।', '🙏 आपले स्वागत आहे! अजून काही? खालीलपैकी निवडा किंवा प्रश्न लिहा.'],
    'date_check' => ["📅 {date}\n🏛️ {bighall}: {big}\n🏠 {smallhall}: {small}\n\nA date is held only after our team confirms the booking.", "📅 {date}\n🏛️ {bighall}: {big}\n🏠 {smallhall}: {small}\n\nटीम की पुष्टि के बाद ही तारीख पक्की होती है।", "📅 {date}\n🏛️ {bighall}: {big}\n🏠 {smallhall}: {small}\n\nटीमने पुष्टी केल्यावरच तारीख निश्चित होते."],
    'is_free' => ['✅ available', '✅ खाली है', '✅ उपलब्ध आहे'],
    'is_booked' => ['❌ already booked', '❌ बुक है', '❌ बुक आहे'],
    'btn_book_this' => ['📝 Book this date', '📝 यह तारीख बुक करें', '📝 ही तारीख बुक करा'],
    'btn_other_dates' => ['📅 Other dates', '📅 दूसरी तारीखें', '📅 इतर तारखा'],
    'wrong' => ['No problem 🙏 Have a good day!', 'कोई बात नहीं 🙏 आपका दिन शुभ हो!', 'काही हरकत नाही 🙏 आपला दिवस शुभ जावो!'],
    'restart' => ['Let us start again.', 'चलिए फिर से शुरू करते हैं।', 'चला, पुन्हा सुरू करूया.'],
    'expired' => ['This request has expired. Let us start again.', 'यह अनुरोध पुराना हो गया है। चलिए फिर से शुरू करते हैं।', 'ही विनंती जुनी झाली आहे. चला, पुन्हा सुरू करूया.'],
    'info_title' => ['ℹ️ What would you like to know?', 'ℹ️ आप क्या जानना चाहेंगे?', 'ℹ️ आपल्याला काय जाणून घ्यायचे आहे?'],
    'choose_topic' => ['Choose topic', 'विषय चुनें', 'विषय निवडा'],
    'stop' => ["✅ Done. Our assistant will not message you again.\nTo ask us to delete your data, reply DELETE or email bookings@venutaihall.com. Write Hi any time to start again.", "✅ ठीक है। हमारा असिस्टेंट अब आपको मैसेज नहीं करेगा।\nअपना डेटा हटवाने के लिए DELETE लिखें या bookings@venutaihall.com पर ईमेल करें। दोबारा शुरू करने के लिए कभी भी Hi लिखें।", "✅ ठीक आहे. आमचा असिस्टंट आता आपल्याला मेसेज करणार नाही.\nआपला डेटा हटवण्यासाठी DELETE लिहा किंवा bookings@venutaihall.com वर ईमेल करा. पुन्हा सुरू करण्यासाठी कधीही Hi लिहा."],
    'delete' => ["✅ We have received your request to delete your personal data. Our team will complete it and confirm within 30 days (records we must keep by law, such as payment receipts, are kept only as long as required).\nOur assistant will not message you again. Write Hi any time to start again.", "✅ आपका डेटा हटाने का अनुरोध मिल गया है। हमारी टीम 30 दिनों में इसे पूरा करके पुष्टि करेगी (क़ानूनन ज़रूरी रिकॉर्ड, जैसे पेमेंट रसीद, सिर्फ़ ज़रूरी समय तक रखे जाएँगे)।\nहमारा असिस्टेंट अब मैसेज नहीं करेगा। दोबारा शुरू करने के लिए Hi लिखें।", "✅ आपला डेटा हटवण्याची विनंती मिळाली आहे. आमची टीम 30 दिवसांत ती पूर्ण करून कळवेल (कायद्याने आवश्यक नोंदी, जसे पेमेंट पावती, फक्त आवश्यक कालावधीपर्यंत ठेवल्या जातील).\nआमचा असिस्टंट आता मेसेज करणार नाही. पुन्हा सुरू करण्यासाठी Hi लिहा."],
];

// Hall FAQ, facts as published on the website: key => [keyword regex for typed questions, [en, hi, mr] titles (24 max), [en, hi, mr] answers].
// Order matters for typed questions: the first matching topic answers.
const WA_BOT_FAQ = [
    'capacity' => ['/capacity|how many (guests|people)|how big|\bsize\b|kiti mot|kitna bada|kitni badi|\d{2,4}\s*\+?\s*(log|lok|people|guests?|pahun|पाहुण|लोग|लोक|मेहमान)|kitne log|kitne guest|kiti lok|seating|crowd|क्षमता|किती लोक|कितने लोग|किती पाहुणे|कितने मेहमान/iu',
        ['👥 Capacity', '👥 क्षमता', '👥 क्षमता'],
        ["👥 Capacity\n\n🏛️ Big Hall: up to 1,200 guests\n   🍽️ Dining: 400 at a time\n   ⭐ VIP A/C Dining: 50 at a time\n\n🏠 Small Hall: up to 350 guests\n   🍽️ Dining: 50 at a time",
         "👥 क्षमता\n\n🏛️ बड़ा हॉल: 1,200 मेहमानों तक\n   🍽️ डाइनिंग: एक बार में 400\n   ⭐ VIP A/C डाइनिंग: एक बार में 50\n\n🏠 छोटा हॉल: 350 मेहमानों तक\n   🍽️ डाइनिंग: एक बार में 50",
         "👥 क्षमता\n\n🏛️ मोठा हॉल: 1,200 पाहुण्यांपर्यंत\n   🍽️ डायनिंग: एका वेळी 400\n   ⭐ VIP A/C डायनिंग: एका वेळी 50\n\n🏠 छोटा हॉल: 350 पाहुण्यांपर्यंत\n   🍽️ डायनिंग: एका वेळी 50"]],
    'location' => ['/address|location|where|website|web ?site|site|link|वेबसाइट|लिंक|kaha|kidhar|kuthe|\bmap\b|direction|reach|\bdoor\b|\bdur\b|distance|station|\bbus\b|metro|railway|kaise aa|kase ya|route|दूर|स्टेशन|बस|पत्ता|कुठे|कहाँ|कहां|लोकेशन|पता/iu',
        ['📍 Location / address', '📍 पता / लोकेशन', '📍 पत्ता / लोकेशन'],
        ["📍 " . WA_BOT_ADDRESS . "\nNext to Sant Tukaram Udyan, Pradhikaran.\n\n🗺️ Google: " . WA_BOT_MAP . "\n🌐 Website: " . WA_BOT_SITE,
         "📍 पता:\n" . WA_BOT_ADDRESS . "\nसंत तुकाराम उद्यान के पास, प्राधिकरण।\n\n🗺️ Google: " . WA_BOT_MAP . "\n🌐 वेबसाइट: " . WA_BOT_SITE,
         "📍 पत्ता:\n" . WA_BOT_ADDRESS . "\nसंत तुकाराम उद्यानाजवळ, प्राधिकरण.\n\n🗺️ Google: " . WA_BOT_MAP . "\n🌐 वेबसाइट: " . WA_BOT_SITE]],
    'price' => ['/price|\brates?\b|\bcost|charges?\b|\brent\b|kitna|kitne ka|kiti paise|bhad|kiraya|how much|deposit|advance|payment|\bpay\b|\bupi\b|\bgst\b|पेमेंट|\bfees?\b|package|quot|budget|भाड|किंमत|पैसे|किराया|कितना|खर्च|रेट|दर किती/iu',
        ['💰 Rates / price', '💰 रेट / कीमत', '💰 दर / किंमत'],
        ["💰 Rates depend on the hall, the date and the package, so they are not fixed online.\n\nTap 📞 Talk to team for a quick quote, or 📝 Book / Enquire and our team will call you with the best rate.",
         "💰 रेट हॉल, तारीख और पैकेज पर निर्भर करता है, इसलिए ऑनलाइन तय नहीं है।\n\n📞 टीम से बात करें – तुरंत कोटेशन मिलेगा, या 📝 बुकिंग करें – टीम आपको सबसे अच्छे रेट के साथ कॉल करेगी।",
         "💰 दर हॉल, तारीख आणि पॅकेजवर अवलंबून असतो, त्यामुळे ऑनलाइन ठरलेला नाही.\n\n📞 टीमशी बोला – लगेच कोटेशन मिळेल, किंवा 📝 बुकिंग करा – टीम आपल्याला सर्वोत्तम दरासह कॉल करेल."]],
    'visit' => ['/visit|dekhn|dikha|bagh|see the hall|show the hall|बघाय|पहाय|पाहायला|देखन|देखने/iu',
        ['👀 Visit the hall', '👀 हॉल देखना', '👀 हॉल पाहणी'],
        ["👀 Yes, you are welcome to visit and see the halls, dining spaces and guest rooms.\n🕘 Mon – Sun, 9:00 AM – 10:00 PM\n\nTap 📞 Talk to team to fix a time.\n📍 " . WA_BOT_MAP,
         "👀 हाँ, आप हॉल, डाइनिंग और गेस्ट रूम देखने ज़रूर आइए।\n🕘 सोम – रवि, सुबह 9 – रात 10\n\nसमय तय करने के लिए 📞 टीम से बात करें।\n📍 " . WA_BOT_MAP,
         "👀 हो, हॉल, डायनिंग आणि गेस्ट रूम पाहण्यासाठी नक्की या.\n🕘 सोम – रवि, सकाळी 9 – रात्री 10\n\nवेळ ठरवण्यासाठी 📞 टीमशी बोला.\n📍 " . WA_BOT_MAP]],
    'catering' => ['/cater|ketring|catring|keter|kitchen|kichan|kichen|food|khana|jevan|lunch|dinner|halwai|menu card|non ?veg|nonveg|किचन|स्वयंपाक|रसोई|मांसाहार|नॉन ?व्हेज|नॉन ?वेज|जेवण|खाना|आचारी|केटरर|कैटरर|भोजन/iu',
        ['🍽️ Food / catering', '🍽️ खाना / कैटरिंग', '🍽️ जेवण / केटरिंग'],
        ["🍽️ You can bring your own caterer – catering support is available.\nBig Hall dining: 400 at a time · VIP A/C Dining: 50 · Small Hall dining: 50 at a time.\n🍳 Kitchen and food rules (for example non-veg): our team will confirm.\n\nPlease confirm current arrangements with our team.",
         "🍽️ आप अपना कैटरर ला सकते हैं – कैटरिंग सपोर्ट उपलब्ध है।\nबड़े हॉल की डाइनिंग: एक बार में 400 · VIP A/C डाइनिंग: 50 · छोटे हॉल की डाइनिंग: 50\n🍳 किचन और खाने के नियम (जैसे नॉन-वेज): हमारी टीम पुष्टि करेगी।\n\nकृपया टीम से वर्तमान व्यवस्था की पुष्टि करें।",
         "🍽️ आपण आपला केटरर आणू शकता – केटरिंग सपोर्ट उपलब्ध आहे.\nमोठ्या हॉलचे डायनिंग: एका वेळी 400 · VIP A/C डायनिंग: 50 · छोट्या हॉलचे डायनिंग: 50\n🍳 किचन आणि जेवणाचे नियम (उदा. नॉन-व्हेज): आमची टीम निश्चित सांगेल.\n\nकृपया सध्याची व्यवस्था टीमकडून निश्चित करा."]],
    'rooms' => ['/room|stay|accommodation|रूम|खोली|ठहर|राहण्या/iu',
        ['🛏️ Guest rooms', '🛏️ गेस्ट रूम', '🛏️ गेस्ट रूम'],
        ["🛏️ Up to 8 air-conditioned guest rooms are available at the venue.\nPlease confirm availability for your date with our team.",
         "🛏️ हॉल में 8 तक A/C गेस्ट रूम उपलब्ध हैं।\nअपनी तारीख के लिए उपलब्धता टीम से पूछें।",
         "🛏️ हॉलमध्ये 8 पर्यंत A/C गेस्ट रूम उपलब्ध आहेत.\nआपल्या तारखेसाठी उपलब्धता टीमकडून विचारा."]],
    'facilities' => ['/\bac\b|a\/c|air.?condition|generator|power ?backup|backup|\blight|\blift|elevator|wheel ?chair|ramp|decorat|decor\b|\bstage|mandap|cctv|सजावट|डेकोरेशन|जनरेटर|लिफ्ट|स्टेज|sound|speaker|\bdj\b|\bmic\b|projector|\bled\b|screen|wi-?fi|facilit|सुविधा|एसी|साउंड/iu',
        ['🎤 Facilities / A/C', '🎤 सुविधाएँ / A/C', '🎤 सुविधा / A/C'],
        ["🎤 Facilities\n❄️ Fully air-conditioned halls and dining spaces\n🔊 Acoustic sound system\n📺 LED screen and projector\n📶 Wi-Fi\n🚗 Parking\n🛗 Lift · 📹 CCTV · 🧯 Fire safety\n\nDecoration, stage or power backup? Our team will confirm.",
         "🎤 सुविधाएँ\n❄️ पूरी तरह A/C हॉल और डाइनिंग\n🔊 साउंड सिस्टम\n📺 LED स्क्रीन और प्रोजेक्टर\n📶 Wi-Fi\n🚗 पार्किंग\n🛗 लिफ्ट · 📹 CCTV · 🧯 फायर सेफ्टी\n\nडेकोरेशन, स्टेज या पावर बैकअप? हमारी टीम पुष्टि करेगी।",
         "🎤 सुविधा\n❄️ पूर्ण A/C हॉल आणि डायनिंग\n🔊 साउंड सिस्टम\n📺 LED स्क्रीन आणि प्रोजेक्टर\n📶 Wi-Fi\n🚗 पार्किंग\n🛗 लिफ्ट · 📹 CCTV · 🧯 फायर सेफ्टी\n\nडेकोरेशन, स्टेज किंवा पॉवर बॅकअप? आमची टीम निश्चित सांगेल."]],
    'parking' => ['/parking|park\b|पार्किंग/iu',
        ['🚗 Parking', '🚗 पार्किंग', '🚗 पार्किंग'],
        ["🚗 Yes, parking is available at the venue. Please ask our team about parking for your guest count.",
         "🚗 हाँ, हॉल में पार्किंग उपलब्ध है। अपने मेहमानों की संख्या के हिसाब से टीम से पूछ लें।",
         "🚗 हो, हॉलमध्ये पार्किंग उपलब्ध आहे. पाहुण्यांच्या संख्येनुसार टीमकडून माहिती घ्या."]],
    'photos' => ['/photo|pic\b|pics|picture|image|video|gallery|फोटो|व्हिडिओ|वीडियो|गॅलरी/iu',
        ['📸 Photos', '📸 फ़ोटो', '📸 फोटो'],
        ["📸 Photos of all halls, dining and rooms:\nhttps://venutaihall.com/gallery\n\nWant to see it in person? Tap 📞 Talk to team to fix a visit.",
         "📸 सभी हॉल, डाइनिंग और रूम की फ़ोटो:\nhttps://venutaihall.com/gallery\n\nखुद देखना चाहें तो विज़िट तय करने के लिए 📞 टीम से बात करें।",
         "📸 सर्व हॉल, डायनिंग आणि रूमचे फोटो:\nhttps://venutaihall.com/gallery\n\nप्रत्यक्ष पाहायचे असल्यास भेट ठरवण्यासाठी 📞 टीमशी बोला."]],
    'timing' => ['/timing|hours|open|close|kab tak|kitne baje|kiti vajta|वेळ|समय|टाइम|किती वाजता|कितने बजे/iu',
        ['🕘 Timings', '🕘 समय', '🕘 वेळ'],
        ["🕘 The venue is open Monday – Sunday, 9:00 AM – 10:00 PM.",
         "🕘 हॉल सोमवार – रविवार, सुबह 9 से रात 10 बजे तक खुला है।",
         "🕘 हॉल सोमवार – रविवार, सकाळी 9 ते रात्री 10 पर्यंत उघडा असतो."]],
];

const WA_BOT_YES = '/^\s*(yes|y|yeah|yep|ok|okay|sure|please|haan|haa|han|ha|ji|ho|hoy|chalel|hmm+|हाँ|हां|हा|जी|हो|होय|चालेल|ठीक)(?=[\s.,!?🙏]|$)/iu';
const WA_BOT_NO = '/^\s*(no|nope|nah|nahi|nahin|nako|naka|नहीं|नही|नाही|नको)(?=[\s.,!?🙏]|$)/iu';
// Free-text understanding (Latin and Devanagari, common spellings). The order of checks lives in wa_bot_reply().
const WA_BOT_CALL_RE = '/\b(call|phone|ring)\s*(me|karo|kar do|kara|kariye|kijiye|back|please|kr|kro)\b|\bcall ?back|\bmanager|\bnumber\s*(do|dya|dena|bhejo|pathva|send|share)|baat\s*kar(ni|na|o)|bolaych|बोलायच|बात कर|कॉल कर|फोन कर|मॅनेजर|मैनेजर|नंबर (द्या|दो|भेजो|पाठवा)/iu';
const WA_BOT_STAFF_RE = '/cancel|confirm (hua|zala|ho gaya|jhala)|booking (status|confirm)|my booking|maz[ae] booking|majh[ae] booking|mera booking|meri booking|postpone|reschedule|change (the )?date|date (badal|change)|refund|paise wapas|receipt|pavti|पावती|रद्द|कॅन्सल|कैंसल|रिफंड/iu';
const WA_BOT_BOOK_RE = '/\bbook|booking|बुक|reserve|kara?ych|kr?aych|karna|krna|karvay|करायच|करना|pahije|पाहिजे|\bhava\b|\bhavi\b|हवा|हवी|chahiye|chaiye|चाहिए|\bneed\b|\bwant\b|enquir|inquir|चौकशी|पूछताछ/iu';
const WA_BOT_AVAIL_RE = '/availab|khali|khaali|खाली|upalabdh|उपलब्ध|\bfree\b|\bdate|tarikh|tarkh|तारीख|तारख/iu';
const WA_BOT_EVENT_RE = [
    0 => '/wedding|marriage|shaa?di|vivah|lagn|लग्न|शादी|विवाह/iu',
    1 => '/engagement|sakhar ?puda|sagai|ring ceremony|साखरपुडा|सगाई/iu',
    2 => '/reception|रिसेप्शन/iu',
    3 => '/birthday|b\'?day|vadh ?divas|wadh ?divas|janam ?din|जन्मदिन|वाढदिवस|बर्थडे/iu',
    4 => '/naming|barsa|barse|namkaran|नामकरण|बारस/iu',
    5 => '/family function|puja|pooja|satyanarayan|dohale|baby shower|godh ?bharai|griha ?pravesh|पूजा|सत्यनारायण|डोहाळ|गोदभराई|कौटुंबिक/iu',
    6 => '/corporate|office (event|party)|seminar|conference|meeting|मीटिंग|सेमिनार/iu',
    7 => '/social|gathering|get ?together|sammelan|melava|reunion|मेळावा|संमेलन/iu',
];
const WA_BOT_HALL_RE = [
    'big' => '/big hall|ba?d[ae] hall|mot(h|ha|hya) hall|मोठा हॉल|मोठ्या हॉल|बड़ा हॉल|बडा हॉल|बड़े हॉल/iu',
    'small' => '/small hall|chh?ot[ae] hall|chotya hall|lahan hall|छोटा हॉल|छोट्या हॉल|छोटे हॉल|लहान हॉल/iu',
];
const WA_BOT_NEUTRAL = '/^\s*(ok+|okay|k+|hmm+|acha|achha|accha|theek( hai)?|thik( hai)?|ठीक( है| आहे)?|बरं|बर|ji|जी)?[\s.!\p{So}\p{Sk}\x{FE0F}\x{200D}]*$/u';
const WA_BOT_WRONG = '/wrong number|galat number|chukicha number|चुकीचा नंबर|गलत नंबर|by mistake|mistake se/iu';
const WA_BOT_THANKS = '/^\s*(thanks?|thank you|thx|ty|ok thanks?|dhanyavad|dhanyawad|shukriya|धन्यवाद|शुक्रिया|आभार|thank u)(?=[\s.,!?🙏]|$)/iu';

function wa_bot_lang_index(string $lang): int
{
    return ['en' => 0, 'hi' => 1, 'mr' => 2][$lang] ?? 0;
}

function wa_bot_t(string $lang, string $key, array $vars = []): string
{
    $text = WA_BOT_TEXT[$key][wa_bot_lang_index($lang)] ?? WA_BOT_TEXT[$key][0];
    foreach ($vars as $name => $value) $text = str_replace('{' . $name . '}', (string) $value, $text);
    return $text;
}

/** 'mr', 'hi', 'en', or '' when the message gives no clue (e.g. just "Hi" or "ok"). */
function wa_bot_detect_lang(string $text): string
{
    if (preg_match('/^\s*(marathi|मराठी)\s*$/iu', $text)) return 'mr';
    if (preg_match('/^\s*(hindi|हिंदी|हिन्दी)\s*$/iu', $text)) return 'hi';
    if (preg_match('/^\s*(english|इंग्लिश|इंग्रजी)\s*$/iu', $text)) return 'en';
    $mr = preg_match_all('/आहे|आहेत|काय|मला|आम्हाला|तुम्ही|हवा|हवी|हवे|पाहिजे|किती|कुठे|कसे|नाही|होय|बघ|पाहा|लग्न|साठी|च्या|नमस्कार|राम राम|\b(aahe|ahe|ahet|kay|kiti|kuthe|pahije|mala|amhala|tumhi|hava|havi|nahi ka|ram ram|sanga|kasa|kasha|lagna|sathi|mhanje|kraycha|karaycha|baghayala|yeu|shakto|shakta|kadhi|asel|zala|hoil|la)\b/iu', $text);
    $hi = preg_match_all('/है|हैं|क्या|मुझे|हमें|चाहिए|कितना|कितने|कहाँ|कहां|कैसे|नहीं|हाँ|के लिए|आप|नमस्ते|\b(hai|hain|kya|kitna|kitne|kaha|kahan|chahiye|mujhe|hume|humko|nahi|haan|batao|bataiye|kab|kaise|kijiye|karna|krna|milega|liye|sakte|ko|wala|hua|karo|kro)\b/iu', $text);
    if ($mr === 0 && $hi === 0) {
        if (preg_match('/\p{Devanagari}/u', $text)) return 'mr'; // shortcut: unclear Devanagari is taken as Marathi (Pune guests)
        return preg_match('/\b(please|what|where|when|how|need|want|available|price|booking|book|hall|date|the|is|for)\b/i', $text) ? 'en' : '';
    }
    return $mr >= $hi ? 'mr' : 'hi';
}

/** The guest's language: detected from this message when it shows one, otherwise the remembered one (English for new guests). */
function wa_bot_lang(PDO $db, string $phone, string $text): string
{
    $detected = $text !== '' ? wa_bot_detect_lang($text) : '';
    $query = $db->prepare('SELECT lang FROM wa_bot_lang WHERE phone = ?');
    $query->execute([$phone]);
    $saved = (string) ($query->fetchColumn() ?: '');
    if ($detected !== '' && $detected !== $saved) {
        $db->prepare('DELETE FROM wa_bot_lang WHERE phone = ?')->execute([$phone]);
        $db->prepare('INSERT INTO wa_bot_lang (phone, lang, updated_at) VALUES (?, ?, ?)')->execute([$phone, $detected, time()]);
        return $detected;
    }
    return $detected ?: ($saved ?: 'en');
}

/** Every FAQ topic the message asks about (at most three), in FAQ order. */
function wa_bot_faq_matches(string $text): array
{
    $keys = [];
    foreach (WA_BOT_FAQ as $key => [$pattern]) {
        if (preg_match($pattern, $text)) $keys[] = $key;
    }
    return array_slice($keys, 0, 3);
}

/** A future date written inside a sentence ("17 feb ko", "on 20 March", "25/12 la"); Y-m-d or ''. */
function wa_bot_find_date(string $text): string
{
    $text = strtr($text, ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9']);
    $candidates = [];
    if (preg_match_all('/(?<!\d)(\d{1,2})(?:st|nd|rd|th)?[\s\-\/.]+([a-z]+|[\p{Devanagari}\p{M}]+|\d{1,2})(?:[\s\-\/.,]+(\d{4}|\d{2})(?!\d))?/iu', $text, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) $candidates[] = trim($m[1] . ' ' . $m[2] . ' ' . ($m[3] ?? ''));
    }
    if (preg_match_all('/([a-z]+)\s+(\d{1,2})(?:st|nd|rd|th)?(?!\d)(?:,?\s+(\d{4}))?/iu', $text, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) $candidates[] = trim($m[1] . ' ' . $m[2] . ' ' . ($m[3] ?? ''));
    }
    foreach ($candidates as $candidate) {
        $date = wa_bot_parse_date($candidate);
        if ($date !== '' && booking_valid_date($date)) return $date;
    }
    return '';
}

/** What a typed message is about. */
function wa_bot_understand(string $text): array
{
    $sense = ['faq' => wa_bot_faq_matches($text), 'call' => (bool) preg_match(WA_BOT_CALL_RE, $text), 'staff' => (bool) preg_match(WA_BOT_STAFF_RE, $text),
        'book' => (bool) preg_match(WA_BOT_BOOK_RE, $text), 'avail' => (bool) preg_match(WA_BOT_AVAIL_RE, $text), 'date' => wa_bot_find_date($text), 'preset' => []];
    foreach (WA_BOT_EVENT_RE as $i => $pattern) {
        if (preg_match($pattern, $text)) { $sense['preset']['event_i'] = $i; break; }
    }
    foreach (WA_BOT_HALL_RE as $hall => $pattern) {
        if (preg_match($pattern, $text)) { $sense['preset']['hall'] = $hall; break; }
    }
    if (preg_match('/(\d{2,4})\s*\+?\s*(people|guests?|persons?|log|lok|pahun|पाहुण|लोग|लोक|मेहमान|जण)/iu', $text, $m)) {
        foreach (array_keys(WA_BOT_GUESTS) as $count) {
            if ((int) $m[1] <= $count) { $sense['preset']['guests'] = $count; break; }
        }
        $sense['preset']['guests'] = $sense['preset']['guests'] ?? 800;
    }
    if ($sense['date'] !== '') $sense['preset']['date'] = $sense['date'];
    if ($sense['faq'] === ['capacity'] && ($sense['book'] || isset($sense['preset']['event_i'])) && isset($sense['preset']['guests'])) $sense['faq'] = [];
    $sense['any'] = $sense['faq'] || $sense['call'] || $sense['staff'] || $sense['book'] || $sense['date'] !== '' || isset($sense['preset']['event_i']);
    return $sense;
}

function wa_bot_faq_match(string $text): string
{
    foreach (WA_BOT_FAQ as $key => [$pattern]) {
        if (preg_match($pattern, $text)) return $key;
    }
    return '';
}

function wa_bot_faq(PDO $db, array $wa, string $phone, string $lang, string|array $keys): void
{
    $answers = array_map(static fn($key) => WA_BOT_FAQ[$key][2][wa_bot_lang_index($lang)], (array) $keys);
    wa_bot_save($db, $phone, 'menu');
    // A tappable map pin first, then the address with the Google and website links.
    if (in_array('location', (array) $keys, true)) {
        wa_deliver($db, $wa, $phone, ['type' => 'location', 'location' => WA_BOT_PIN + ['name' => 'Late Venutai Chavan Multipurpose Hall', 'address' => str_replace("\n", ', ', WA_BOT_ADDRESS)]], 'text', '📍 Location pin', 'bot');
    }
    wa_bot_buttons($db, $wa, $phone, mb_substr(implode("\n\n━━━━━━━━\n\n", $answers), 0, 1000), ['m:book' => wa_bot_t($lang, 'btn_book'), 'm:info' => wa_bot_t($lang, 'btn_more'), 'm:team' => wa_bot_t($lang, 'btn_team')]);
}

function wa_bot_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $db->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS wa_bot_state (phone TEXT PRIMARY KEY, step TEXT NOT NULL, data TEXT, updated_at INTEGER NOT NULL)'
        : 'CREATE TABLE IF NOT EXISTS wa_bot_state (phone VARCHAR(20) NOT NULL PRIMARY KEY, step VARCHAR(20) NOT NULL, data TEXT NULL, updated_at INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS wa_bot_lang (phone TEXT PRIMARY KEY, lang TEXT NOT NULL, updated_at INTEGER NOT NULL)'
        : 'CREATE TABLE IF NOT EXISTS wa_bot_lang (phone VARCHAR(20) NOT NULL PRIMARY KEY, lang VARCHAR(2) NOT NULL, updated_at INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = true;
}

function wa_bot_state(PDO $db, string $phone): array
{
    $query = $db->prepare('SELECT step, data, updated_at FROM wa_bot_state WHERE phone = ?');
    $query->execute([$phone]);
    $row = $query->fetch();
    if (!$row) return ['step' => '', 'data' => [], 'age' => PHP_INT_MAX];
    return ['step' => (string) $row['step'], 'data' => json_decode((string) $row['data'], true) ?: [], 'age' => time() - (int) $row['updated_at']];
}

function wa_bot_save(PDO $db, string $phone, string $step, array $data = []): void
{
    $db->prepare('DELETE FROM wa_bot_state WHERE phone = ?')->execute([$phone]);
    if ($step !== '') $db->prepare('INSERT INTO wa_bot_state (phone, step, data, updated_at) VALUES (?, ?, ?, ?)')->execute([$phone, $step, json_encode($data, JSON_UNESCAPED_UNICODE), time()]);
}

function wa_bot_text(PDO $db, array $wa, string $phone, string $text): void
{
    wa_deliver($db, $wa, $phone, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]], 'text', $text, 'bot');
}

/** Up to three reply buttons: [id => title (20 chars max)]. */
function wa_bot_buttons(PDO $db, array $wa, string $phone, string $text, array $buttons): void
{
    $action = ['buttons' => array_map(static fn($id, $title) => ['type' => 'reply', 'reply' => ['id' => (string) $id, 'title' => mb_substr($title, 0, 20)]], array_keys($buttons), $buttons)];
    wa_deliver($db, $wa, $phone, ['type' => 'interactive', 'interactive' => ['type' => 'button', 'body' => ['text' => $text], 'action' => $action]], 'text', $text . "\n[" . implode(' | ', $buttons) . ']', 'bot');
}

/** A list of up to ten rows: [id => title (24 chars max)]. */
function wa_bot_list(PDO $db, array $wa, string $phone, string $text, string $button, array $rows): void
{
    $items = array_map(static fn($id, $title) => ['id' => (string) $id, 'title' => mb_substr($title, 0, 24)], array_keys($rows), $rows);
    $action = ['button' => mb_substr($button, 0, 20), 'sections' => [['title' => mb_substr($button, 0, 24), 'rows' => $items]]];
    wa_deliver($db, $wa, $phone, ['type' => 'interactive', 'interactive' => ['type' => 'list', 'body' => ['text' => $text], 'action' => $action]], 'text', $text . "\n[" . implode(' | ', $rows) . ']', 'bot');
}

function wa_bot_main_buttons(string $lang): array
{
    return ['m:dates' => wa_bot_t($lang, 'btn_dates'), 'm:book' => wa_bot_t($lang, 'btn_book'), 'm:info' => wa_bot_t($lang, 'btn_info')];
}

function wa_bot_menu(PDO $db, array $wa, string $phone, string $name, string $lang, string $lead = ''): void
{
    $hello = $lead !== '' ? $lead : wa_bot_t($lang, 'welcome', ['name' => $name !== '' ? ' ' . $name : '']);
    // DPDP notice on every menu: where the policy is and how to opt out.
    wa_bot_save($db, $phone, 'menu');
    wa_bot_buttons($db, $wa, $phone, $hello . "\n\n" . wa_bot_t($lang, 'help') . "\n\n" . wa_bot_t($lang, 'langhint') . "\n" . wa_bot_t($lang, 'privacy'), wa_bot_main_buttons($lang));
}

function wa_bot_hall(string $lang, string $hall): string
{
    return WA_BOT_HALL_NAMES[$lang][$hall] ?? WA_BOT_HALLS[$hall];
}

function wa_bot_event(string $lang, int $index): string
{
    return WA_BOT_EVENT_NAMES[$lang][$index] ?? WA_BOT_EVENTS[$index];
}

function wa_bot_guests(string $lang, int $count): string
{
    return WA_BOT_GUEST_NAMES[$lang][$count] ?? WA_BOT_GUESTS[$count];
}

/** "17 Feb 2027" / "17 फ़रवरी 2027" / "17 फेब्रुवारी 2027"; with $monthOnly "February 2027". */
function wa_bot_date(string $lang, string $ymd, bool $monthOnly = false): string
{
    $time = strtotime($ymd);
    if (!isset(WA_BOT_MONTHS[$lang])) return date($monthOnly ? 'F Y' : 'd M Y', $time);
    $month = WA_BOT_MONTHS[$lang][(int) date('n', $time) - 1];
    return ($monthOnly ? '' : date('j', $time) . ' ') . $month . ' ' . date('Y', $time);
}

function wa_bot_when(string $lang): string
{
    $hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('G');
    return wa_bot_t($lang, $hour >= 9 && $hour < 21 ? 'when_soon' : 'when_morning');
}

/** Free dates of one hall in one month (Y-m), from today on; a date is taken when any row has it as booked_date. */
function wa_bot_free_dates(PDO $db, string $hall, string $month): array
{
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', new DateTimeZone('Asia/Kolkata'));
    if (!$first) return [];
    $last = $first->modify('last day of this month')->format('Y-m-d');
    $from = max($first->format('Y-m-d'), booking_today());
    $query = $db->prepare('SELECT booked_date FROM bookings WHERE hall = ? AND booked_date >= ? AND booked_date <= ?');
    $query->execute([$hall, $from, $last]);
    $taken = array_flip(array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN)));
    $free = [];
    for ($day = new DateTimeImmutable($from); $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
        if (!isset($taken[$day->format('Y-m-d')])) $free[] = (int) $day->format('j');
    }
    return $free;
}

/** [1,2,3,5,7,8] -> "1–3, 5, 7–8" */
function wa_bot_ranges(array $days): string
{
    $parts = [];
    for ($i = 0, $n = count($days); $i < $n; $i = $j + 1) {
        for ($j = $i; $j + 1 < $n && $days[$j + 1] === $days[$j] + 1; $j++);
        $parts[] = $i === $j ? (string) $days[$i] : $days[$i] . '–' . $days[$j];
    }
    return implode(', ', $parts);
}

/**
 * Reads "25-12-2026", "25/12/26", "2026-12-25", "25/12", "25 Dec", "Dec 25 2026", "25 डिसेंबर" (Devanagari digits too).
 * Without a year, the next such date from today. Returns Y-m-d or ''.
 */
function wa_bot_parse_date(string $text, ?string $today = null): string
{
    $text = mb_strtolower(trim(strtr($text, ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9'])));
    $text = trim(preg_replace('/(\d)(st|nd|rd|th)\b/u', '$1', $text) ?? $text, " .,");
    $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    foreach (WA_BOT_MONTHS as $names) foreach ($names as $i => $word) $months[mb_strtolower($word)] = $i + 1;
    $full = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    $monthOf = static function (string $word) use ($months, $full): int {
        if (preg_match('/^[a-z]+$/', $word)) {
            foreach ($full as $i => $name) if (strlen($word) >= 3 && str_starts_with($name, $word)) return $i + 1;
            return 0;
        }
        foreach ($months as $key => $n) if (str_starts_with($word, $key)) return $n;
        return 0;
    };
    $y = 0;
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/u', $text, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    elseif (preg_match('/^(\d{1,2})[-\/. ](\d{1,2})(?:[-\/. ](\d{2}|\d{4}))?$/u', $text, $m)) [$d, $mo, $y] = [(int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0];
    elseif (preg_match('/^(\d{1,2})[\s\-\/.]*([\p{L}\p{M}]+)[\s,\-\/.]*(\d{2}|\d{4})?$/u', $text, $m) && ($mo = $monthOf($m[2]))) [$d, $y] = [(int) $m[1], isset($m[3]) ? (int) $m[3] : 0];
    elseif (preg_match('/^([\p{L}\p{M}]+)[\s\-\/.]*(\d{1,2})[\s,\-\/.]*(\d{2}|\d{4})?$/u', $text, $m) && ($mo = $monthOf($m[1]))) [$d, $y] = [(int) $m[2], isset($m[3]) ? (int) $m[3] : 0];
    else return '';
    $today = $today ?? booking_today();
    if ($y === 0) {
        $y = (int) substr($today, 0, 4);
        if (checkdate($mo, $d, $y) && sprintf('%04d-%02d-%02d', $y, $mo, $d) < $today) $y++;
    } elseif ($y < 100) {
        $y += 2000;
    }
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : '';
}

/** True when staff wrote to this guest recently, so the bot leaves the chat to them. */
function wa_bot_staff_active(PDO $db, string $phone): bool
{
    $query = $db->prepare("SELECT MAX(sent_at) FROM wa_messages WHERE phone = ? AND direction = 'out' AND sent_by NOT IN ('bot', 'lead-alerts', 'booking-alerts')");
    $query->execute([$phone]);
    $last = $query->fetchColumn();
    if (!$last || strtotime((string) $last) <= strtotime(wa_now()) - WA_BOT_STAFF_PAUSE) return false;
    // If the bot spoke after staff, the guest is now chatting with the bot, not with staff.
    $bot = $db->prepare("SELECT MAX(sent_at) FROM wa_messages WHERE phone = ? AND direction = 'out' AND sent_by = 'bot'");
    $bot->execute([$phone]);
    $lastBot = $bot->fetchColumn();
    return !$lastBot || strtotime((string) $last) > strtotime((string) $lastBot);
}

/** Replies to new guest messages. Never throws; a failed reply is logged and the message stays in the inbox for staff. */
function wa_bot_handle(PDO $db, array $config, array $wa, array $messages): void
{
    if (booking_setting($db, 'whatsapp_bot', '1') !== '1' || $wa['token'] === '' || $wa['phone_id'] === '') return;
    foreach ($messages as $item) {
        try {
            wa_bot_ensure_schema($db);
            wa_bot_reply($db, $config, $wa, (string) $item['phone'], (string) $item['name'], (array) $item['message']);
        } catch (Throwable $error) {
            error_log('WhatsApp bot: ' . $error->getMessage());
        }
    }
}

function wa_bot_reply(PDO $db, array $config, array $wa, string $phone, string $name, array $m): void
{
    // Meta can deliver late retries; an old message gets no automatic answer.
    if (isset($m['timestamp']) && ctype_digit((string) $m['timestamp']) && (int) $m['timestamp'] < time() - 900) return;
    $type = (string) ($m['type'] ?? '');
    $id = (string) ($m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? '');
    $text = trim((string) ($m['text']['body'] ?? $m['button']['text'] ?? ''));
    $lang = wa_bot_lang($db, $phone, $id === '' ? $text : '');
    $state = wa_bot_state($db, $phone);

    // DPDP: STOP withdraws consent for automated messages, DELETE asks for erasure. Both last until the guest writes Hi again.
    if ($id === '' && preg_match('/^\s*(stop|unsubscribe|opt ?out|delete( my data)?|erase|बंद)\s*[.!]*\s*$/iu', $text, $optout)) {
        $erase = (bool) preg_match('/delete|erase/i', $optout[1]);
        wa_bot_save($db, $phone, 'optout', ['erase' => $erase]);
        if ($erase) {
            try {
                $to = booking_setting($db, 'venue_email', '') ?: (string) (($config['email_to'] ?? '') ?: 'bookings@venutaihall.com');
                booking_send_email_message($db, $config, $to, 'DPDP: data deletion request from WhatsApp +' . $phone,
                    "A guest asked on WhatsApp to delete their personal data.\n\nWhatsApp number: +{$phone}\nName: " . ($name ?: '-') . "\nReceived: " . date('d M Y, H:i')
                    . "\n\nPlease within 30 days: delete or cancel their enquiries and WhatsApp chat in the admin panel (keep only booking/payment records the law requires), then reply to confirm.");
            } catch (Throwable $error) {
                error_log('WhatsApp bot erase alert: ' . $error->getMessage());
            }
        }
        wa_bot_text($db, $wa, $phone, wa_bot_t($lang, $erase ? 'delete' : 'stop'));
        return;
    }
    // Typing just a language name switches the language and shows the menu.
    $switch = $id === '' && preg_match('/^\s*(marathi|मराठी|hindi|हिंदी|हिन्दी|english|इंग्लिश|इंग्रजी)\s*$/iu', $text);
    // What the guest typed ("Hi, is parking available?", "shadi ke liye 17 feb ko hall chahiye") beats a plain greeting.
    $sense = $id === '' && $type === 'text' && !$switch ? wa_bot_understand($text) : ['any' => false];
    $greeting = $switch || (!$sense['any'] && preg_match('/^\s*(hi+|hello|helo|hey|hlo|namaste|namaskar|ram ram|menu|start|good (morning|afternoon|evening)|gm|हाय|हॅलो|हेलो|नमस्ते|नमस्कार|राम राम|मेनू|शुभ प्रभात|सुप्रभात|गुड मॉर्निंग)(?=[\s.,!?🙏]|$)/iu', $text));
    if ($state['step'] === 'optout' && !$greeting && $id === '') return;
    $handedOver = $state['step'] === 'team' && $state['age'] < WA_BOT_STAFF_PAUSE;
    if ($state['age'] > 7200 && !$handedOver) $state = ['step' => '', 'data' => [], 'age' => 0];

    if ($id === '' && !$greeting) {
        // A guest in the middle of the booking form always gets an answer.
        if ($state['step'] === 'date') { wa_bot_take_date($db, $config, $wa, $phone, $name, $lang, $state['data'], $text, $type); return; }
        // Staff are chatting with this guest: stay quiet.
        if (wa_bot_staff_active($db, $phone)) return;
        if (in_array($type, ['reaction', 'sticker', 'unsupported', 'system'], true)) return;
        if ($type === 'text' && preg_match(WA_BOT_WRONG, $text)) { wa_bot_save($db, $phone, 'optout'); wa_bot_text($db, $wa, $phone, wa_bot_t($lang, 'wrong')); return; }
        if ($sense['any']) {
            // Asked for a person, or about an existing booking (cancel, status, refund): the team takes it.
            if ($sense['call'] || $sense['staff']) { wa_bot_callback($db, $config, $wa, $phone, $name, $lang, $state['data'], $text, true); return; }
            if ($sense['faq']) { wa_bot_faq($db, $wa, $phone, $lang, $sense['faq']); return; }
            // "Is the big hall available on 15 Jan?" is a question, not yet a booking.
            $booking = $sense['book'] || isset($sense['preset']['event_i']) || (isset($sense['preset']['hall']) && !$sense['avail']);
            if ($sense['date'] !== '' && !$booking) { wa_bot_date_check($db, $wa, $phone, $lang, $sense['date']); return; }
            if ($booking) { wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, $sense['preset']); return; }
        }
        if ($type === 'text' && preg_match(WA_BOT_AVAIL_RE, $text) && $state['step'] !== 'callask') {
            wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'which_hall'), ['d:big' => '🏛️ ' . wa_bot_hall($lang, 'big'), 'd:small' => '🏠 ' . wa_bot_hall($lang, 'small') . ' (≤350)']);
            return;
        }
        // The team already has this guest: a short, occasional reminder that a call is coming.
        if ($handedOver) {
            if ($state['age'] > 1800) {
                wa_bot_save($db, $phone, 'team', $state['data']);
                wa_bot_text($db, $wa, $phone, wa_bot_t($lang, 'ack', ['when' => wa_bot_when($lang), 'phone' => wa_bot_venue_phone($db)]));
            }
            return;
        }
        if ($type === 'text' && preg_match(WA_BOT_THANKS, $text)) { wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'thanks'), wa_bot_main_buttons($lang)); return; }
        if ($state['step'] === 'callask') {
            if (preg_match(WA_BOT_NO, $text)) { wa_bot_call_no($db, $wa, $phone, $lang); return; }
            // "Yes", or a second message the bot cannot follow: the guest needs a person.
            wa_bot_callback($db, $config, $wa, $phone, $name, $lang, $state['data'], $text);
            return;
        }
        // "ok", "hmm", 👍 and the like: a gentle menu, not a question.
        if ($type === 'text' && preg_match(WA_BOT_NEUTRAL, $text)) {
            wa_bot_save($db, $phone, 'menu');
            wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'help'), wa_bot_main_buttons($lang));
            return;
        }
        // Not understood (text, voice note, photo...): offer a call with a simple yes/no instead of going silent.
        wa_bot_save($db, $phone, 'callask', $state['data']);
        wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'not_understood'), ['c:yes' => wa_bot_t($lang, 'btn_yes_call'), 'c:no' => wa_bot_t($lang, 'btn_no'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
        return;
    }
    if ($id === '' || $id === 'm:menu') { wa_bot_menu($db, $wa, $phone, $name, $lang); return; }

    [$kind, $a, $b] = array_pad(explode(':', $id, 3), 3, '');
    $data = $state['data'];
    switch ($kind) {
        case 'c':
            if ($a === 'yes') wa_bot_callback($db, $config, $wa, $phone, $name, $lang, $data, '');
            else wa_bot_call_no($db, $wa, $phone, $lang);
            return;
        case 'm':
            if ($a === 'dates') {
                wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'which_hall'), ['d:big' => '🏛️ ' . wa_bot_hall($lang, 'big'), 'd:small' => '🏠 ' . wa_bot_hall($lang, 'small') . ' (≤350)']);
            } elseif ($a === 'book') {
                wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, []);
            } elseif ($a === 'info') {
                $rows = [];
                foreach (WA_BOT_FAQ as $key => [, $titles]) if ($key !== 'photos') $rows['i:' . $key] = $titles[wa_bot_lang_index($lang)];
                wa_bot_list($db, $wa, $phone, wa_bot_t($lang, 'info_title'), wa_bot_t($lang, 'choose_topic'), $rows + ['m:team' => wa_bot_t($lang, 'btn_team')]);
            } elseif ($a === 'team') {
                wa_bot_callback($db, $config, $wa, $phone, $name, $lang, $data, '', true);
            }
            return;
        case 'i':
            if (isset(WA_BOT_FAQ[$a])) wa_bot_faq($db, $wa, $phone, $lang, $a);
            return;
        case 'd':
            if (!isset(WA_BOT_HALLS[$a])) return;
            $rows = [];
            for ($i = 0, $month = new DateTimeImmutable('first day of this month', new DateTimeZone('Asia/Kolkata')); $i < 6; $i++, $month = $month->modify('+1 month')) {
                $rows['dm:' . $a . ':' . $month->format('Y-m')] = wa_bot_date($lang, $month->format('Y-m-d'), true);
            }
            wa_bot_list($db, $wa, $phone, wa_bot_t($lang, 'which_month', ['hall' => wa_bot_hall($lang, $a)]), wa_bot_t($lang, 'choose_month'), $rows);
            return;
        case 'dm':
            if (!isset(WA_BOT_HALLS[$a]) || !preg_match('/^\d{4}-\d{2}$/', $b)) return;
            $free = wa_bot_free_dates($db, $a, $b);
            $vars = ['hall' => wa_bot_hall($lang, $a), 'month' => wa_bot_date($lang, $b . '-01', true), 'days' => wa_bot_ranges($free)];
            wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, $free ? 'free_dates' : 'full_month', $vars), ['m:book' => wa_bot_t($lang, 'btn_book_date'), 'd:' . $a => wa_bot_t($lang, 'btn_other_month'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
            return;
        case 'be':
            if (!isset(WA_BOT_EVENTS[(int) $a])) return;
            $keep = $state['step'] === 'book' ? array_intersect_key($data, ['hall' => 1, 'date' => 1, 'guests' => 1]) : [];
            wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, ['event_i' => (int) $a] + $keep);
            return;
        case 'bh':
            if (!isset(WA_BOT_HALLS[$a])) return;
            $keep = $state['step'] === 'book' ? array_intersect_key($data, ['event_i' => 1, 'date' => 1, 'guests' => 1]) : [];
            wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, ['hall' => $a] + $keep);
            return;
        case 'bd':
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) || !booking_valid_date($a)) { wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, []); return; }
            wa_bot_start_booking($db, $config, $wa, $phone, $name, $lang, ['date' => $a]);
            return;
        case 'bg':
            // Optional detail after the enquiry is already saved and the team alerted.
            if (!isset(WA_BOT_GUESTS[(int) $a]) || $state['step'] !== 'guests' || empty($data['id'])) { wa_bot_menu($db, $wa, $phone, $name, $lang); return; }
            $db->prepare("UPDATE bookings SET guests = ? WHERE id = ? AND phone = ? AND source = 'whatsapp'")->execute([(int) $a, (int) $data['id'], $phone]);
            wa_bot_save($db, $phone, '');
            wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'guests_noted', ['guests' => wa_bot_guests($lang, (int) $a)]), ['m:info' => wa_bot_t($lang, 'btn_info'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
            return;
        case 'bc':
            // Confirm button from the older flow; the request is now sent as soon as the date is free.
            wa_bot_menu($db, $wa, $phone, $name, $lang, wa_bot_t($lang, 'expired'));
            return;
    }
}

function wa_bot_venue_phone(PDO $db): string
{
    return booking_setting($db, 'venue_phone', '') ?: '9359567494';
}

function wa_bot_call_no(PDO $db, array $wa, string $phone, string $lang): void
{
    wa_bot_save($db, $phone, 'menu');
    wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'call_no'), wa_bot_main_buttons($lang));
}

/**
 * The guest wants a person: saves a "Call back request" enquiry and alerts the sales team (once per 12 hours per guest),
 * then keeps the bot in the background so staff can take over.
 */
function wa_bot_callback(PDO $db, array $config, array $wa, string $phone, string $name, string $lang, array $data, string $said, bool $team = false): void
{
    $recent = $db->prepare("SELECT id FROM bookings WHERE phone = ? AND source = 'whatsapp' AND record_type = 'enquiry' AND status = 'pending' AND created_at >= ? ORDER BY id DESC LIMIT 1");
    $recent->execute([$phone, date('Y-m-d H:i:s', time() - WA_BOT_STAFF_PAUSE)]);
    $alert = null;
    if (!$recent->fetchColumn()) {
        $guest = $name !== '' ? $name : 'WhatsApp guest';
        $event = (string) ($data['event'] ?? '');
        $message = 'Call back request from the WhatsApp assistant (guest language: ' . ['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi'][$lang] . ').'
            . ($event !== '' ? ' Interested in: ' . $event . (isset($data['hall'], WA_BOT_HALLS[$data['hall']]) ? ', ' . WA_BOT_HALLS[$data['hall']] : '') . '.' : ' Hall and date not chosen yet.')
            . ($said !== '' ? ' Guest wrote: "' . mb_substr($said, 0, 300) . '"' : '');
        $hall = isset($data['hall'], WA_BOT_HALLS[$data['hall']]) ? $data['hall'] : 'big';
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO bookings (name, phone, email, event_type, hall, event_date, guests, message, status, record_type, source) VALUES (?, ?, '', ?, ?, NULL, NULL, ?, 'pending', 'enquiry', 'whatsapp')")
                ->execute([mb_substr($guest, 0, 120), $phone, $event !== '' ? $event : 'Call back request', $hall, $message]);
            $id = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO booking_activity (booking_id, action, details) VALUES (?, 'enquiry_created', 'WhatsApp call back request')")->execute([$id]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
        $alert = ['id' => $id, 'name' => $guest, 'phone' => $phone, 'email' => '', 'event_type' => $event !== '' ? $event : 'Call back request', 'hall' => $hall, 'event_date' => null, 'guests' => null, 'message' => $message, 'source' => 'whatsapp'];
    }
    wa_bot_save($db, $phone, 'team', $data);
    $vars = ['when' => wa_bot_when($lang), 'phone' => wa_bot_venue_phone($db)];
    // The guest hears back first; the team alerts follow.
    if ($team) wa_bot_text($db, $wa, $phone, wa_bot_t($lang, 'team', $vars));
    else wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'call_yes', $vars), ['m:dates' => wa_bot_t($lang, 'btn_dates'), 'm:info' => wa_bot_t($lang, 'btn_info'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
    if ($alert) lead_notify($db, $config, $alert);
}

/** Starts (or continues) the booking form with whatever the guest already told us: event_i, hall, date, guests. */
function wa_bot_start_booking(PDO $db, array $config, array $wa, string $phone, string $name, string $lang, array $data): void
{
    // A date that is already taken for every hall the guest could mean: say so now, before asking anything.
    if (!empty($data['date']) && !isset($data['event_i'], $data['hall'])) {
        $taken = wa_bot_taken($db, (string) $data['date']);
        $halls = isset($data['hall'], WA_BOT_HALLS[$data['hall']]) ? [$data['hall']] : array_keys(WA_BOT_HALLS);
        if (!array_diff($halls, array_keys($taken))) { wa_bot_date_check($db, $wa, $phone, $lang, (string) $data['date']); return; }
    }
    if (!isset($data['event_i'], WA_BOT_EVENTS[$data['event_i']])) {
        unset($data['event_i']);
        wa_bot_save($db, $phone, 'book', $data);
        $rows = [];
        foreach (array_keys(WA_BOT_EVENTS) as $i) $rows['be:' . $i] = wa_bot_event($lang, $i);
        wa_bot_list($db, $wa, $phone, wa_bot_t($lang, 'step1'), wa_bot_t($lang, 'choose_event'), $rows);
        return;
    }
    $data['event'] = WA_BOT_EVENTS[$data['event_i']];
    if (!isset($data['hall'], WA_BOT_HALLS[$data['hall']])) {
        unset($data['hall']);
        wa_bot_save($db, $phone, 'book', $data);
        // With the date known, show which hall is still free on it.
        $note = '';
        if (!empty($data['date'])) {
            $taken = wa_bot_taken($db, (string) $data['date']);
            $note = "\n\n📅 " . wa_bot_date($lang, (string) $data['date']);
            foreach (['big' => '🏛️', 'small' => '🏠'] as $hall => $icon) $note .= "\n{$icon} " . wa_bot_hall($lang, $hall) . ': ' . wa_bot_t($lang, isset($taken[$hall]) ? 'is_booked' : 'is_free');
        }
        wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'step2') . $note, ['bh:big' => '🏛️ ' . wa_bot_hall($lang, 'big'), 'bh:small' => '🏠 ' . wa_bot_hall($lang, 'small')]);
        return;
    }
    if (empty($data['date'])) {
        wa_bot_save($db, $phone, 'date', $data);
        wa_bot_text($db, $wa, $phone, wa_bot_t($lang, 'step3'));
        return;
    }
    $date = (string) $data['date'];
    unset($data['date']);
    wa_bot_book_date($db, $config, $wa, $phone, $name, $lang, $data, $date);
}

/** A free date becomes an enquiry with a team alert; a taken one asks for another date. */
function wa_bot_book_date(PDO $db, array $config, array $wa, string $phone, string $name, string $lang, array $data, string $date): void
{
    $check = $db->prepare('SELECT 1 FROM bookings WHERE booked_date = ? AND hall = ? LIMIT 1');
    $check->execute([$date, $data['hall']]);
    if ($check->fetchColumn()) {
        wa_bot_save($db, $phone, 'date', $data);
        wa_bot_text($db, $wa, $phone, wa_bot_t($lang, 'booked', ['hall' => wa_bot_hall($lang, $data['hall']), 'date' => wa_bot_date($lang, $date)]));
        return;
    }
    // The date is free: save the enquiry and alert the sales team now; the guest count is asked after, optionally.
    wa_bot_create_enquiry($db, $config, $wa, $phone, $name, $lang, $data + ['date' => $date]);
}

/** "Is 17 Feb free?" without a booking request: both halls' status for that date. */
/** Halls already taken on a date: ['big' => 0, ...]. */
function wa_bot_taken(PDO $db, string $date): array
{
    $query = $db->prepare('SELECT hall FROM bookings WHERE booked_date = ?');
    $query->execute([$date]);
    return array_flip(array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN)));
}

function wa_bot_date_check(PDO $db, array $wa, string $phone, string $lang, string $date): void
{
    $taken = wa_bot_taken($db, $date);
    $status = static fn(string $hall) => wa_bot_t($lang, isset($taken[$hall]) ? 'is_booked' : 'is_free');
    wa_bot_save($db, $phone, 'menu');
    $body = wa_bot_t($lang, 'date_check', ['date' => wa_bot_date($lang, $date), 'bighall' => wa_bot_hall($lang, 'big'), 'smallhall' => wa_bot_hall($lang, 'small'), 'big' => $status('big'), 'small' => $status('small')]);
    $free = !isset($taken['big']) || !isset($taken['small']);
    wa_bot_buttons($db, $wa, $phone, $body, $free
        ? ['bd:' . $date => wa_bot_t($lang, 'btn_book_this'), 'm:dates' => wa_bot_t($lang, 'btn_other_dates'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]
        : ['m:dates' => wa_bot_t($lang, 'btn_other_dates'), 'm:team' => wa_bot_t($lang, 'btn_team'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
}

function wa_bot_take_date(PDO $db, array $config, array $wa, string $phone, string $name, string $lang, array $data, string $text, string $type = 'text'): void
{
    if (empty($data['event']) || !isset(WA_BOT_HALLS[$data['hall'] ?? ''])) { wa_bot_menu($db, $wa, $phone, $name, $lang, wa_bot_t($lang, 'restart')); return; }
    if ($type === 'text' && preg_match(WA_BOT_YES, $text)) { wa_bot_callback($db, $config, $wa, $phone, $name, $lang, $data, $text); return; }
    if ($type === 'text' && preg_match(WA_BOT_NO, $text)) { wa_bot_call_no($db, $wa, $phone, $lang); return; }
    $date = $type === 'text' ? wa_bot_parse_date($text) : '';
    if ($date === '' || !booking_valid_date($date)) {
        // The form stays open, so a corrected date still works; the buttons offer a person instead.
        wa_bot_buttons($db, $wa, $phone, wa_bot_t($lang, 'bad_date'), ['c:yes' => wa_bot_t($lang, 'btn_yes_call'), 'm:menu' => wa_bot_t($lang, 'btn_menu')]);
        return;
    }
    wa_bot_book_date($db, $config, $wa, $phone, $name, $lang, $data, $date);
}

function wa_bot_create_enquiry(PDO $db, array $config, array $wa, string $phone, string $name, string $lang, array $data): void
{
    $name = $name !== '' ? $name : 'WhatsApp guest';
    $data['guests'] = $data['guests'] ?? null;
    $message = 'Booking request from the WhatsApp assistant (guest language: ' . ['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi'][$lang] . '). The date was free when the guest asked.';
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO bookings (name, phone, email, event_type, hall, event_date, guests, message, status, record_type, source) VALUES (?, ?, '', ?, ?, ?, ?, ?, 'pending', 'enquiry', 'whatsapp')")
            ->execute([mb_substr($name, 0, 120), $phone, $data['event'], $data['hall'], $data['date'], $data['guests'], $message]);
        $id = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO booking_activity (booking_id, action, details) VALUES (?, 'enquiry_created', 'WhatsApp assistant enquiry')")->execute([$id]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    wa_bot_save($db, $phone, 'guests', ['id' => $id]);
    $rows = [];
    foreach (array_keys(WA_BOT_GUESTS) as $count) $rows['bg:' . $count] = wa_bot_guests($lang, $count);
    $event = isset($data['event_i']) ? wa_bot_event($lang, (int) $data['event_i']) : $data['event'];
    wa_bot_list($db, $wa, $phone, wa_bot_t($lang, 'good_news', ['name' => $name, 'hall' => wa_bot_hall($lang, $data['hall']), 'date' => wa_bot_date($lang, $data['date']), 'event' => $event,
        'ref' => 'ENQ-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'when' => wa_bot_when($lang)]), wa_bot_t($lang, 'choose_guests'), $rows);
    lead_notify($db, $config, ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => '', 'event_type' => $data['event'], 'hall' => $data['hall'], 'event_date' => $data['date'], 'guests' => $data['guests'], 'message' => $message, 'source' => 'whatsapp']);
}
