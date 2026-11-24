<?php
declare(strict_types=1);

// WhatsApp auto-reply: menu, free dates per hall and month, and a step-by-step booking enquiry.
// Requires booking-store.php, whatsapp-store.php and leads-store.php to be loaded first.
// Button and list ids carry their own data (e.g. "dm:big:2026-12"); only the booking form keeps state.

const WA_BOT_EVENTS = ['Wedding', 'Engagement', 'Reception', 'Birthday', 'Naming Ceremony', 'Family Function', 'Corporate Event', 'Social Gathering', 'Venue Visit', 'Other Event'];
const WA_BOT_HALLS = ['big' => 'Big Hall', 'small' => 'Small Hall'];
const WA_BOT_GUESTS = [50 => 'Up to 50', 100 => 'Up to 100', 200 => 'Up to 200', 350 => 'Up to 350', 500 => 'Up to 500', 800 => '500 or more'];
const WA_BOT_STAFF_PAUSE = 43200; // seconds the bot stays quiet after staff write or the guest asks for the team
const WA_BOT_ADDRESS = "Late Venutai Chavan Multipurpose Hall\nYashwantrao Chavan Smarak, Sector 27A, Pradhikaran, Nigdi, Pune 411044";
const WA_BOT_MAP = 'https://www.google.com/maps/search/?api=1&query=Late+Venutai+Chavan+Multipurpose+Hall+Nigdi+Pune';
// Hall FAQ, facts as published on the website: key => [list title (24 max), keyword regex for typed questions, answer].
// Order matters for typed questions: the first matching topic answers.
const WA_BOT_FAQ = [
    'capacity' => ['👥 Capacity', '/capacity|how many (guests|people)|kitne log|kitne guest|seating|crowd|क्षमता|किती लोक/iu',
        "👥 Capacity\n\n🏛️ Big Hall: up to 1,200 guests\n   🍽️ Dining: 400 at a time\n   ⭐ VIP A/C Dining: 50 at a time\n\n🏠 Small Hall: up to 350 guests\n   🍽️ Dining: 50 at a time"],
    'price' => ['💰 Rates / price', '/price|\brates?\b|\bcost|charges?\b|\brent\b|kitna|kitne ka|\bfees?\b|package|quot|budget|भाड|किंमत|पैसे|किराया|कितना/iu',
        "💰 Rates depend on the hall, the date and the package, so they are not fixed online.\n\nTap 📞 Talk to team for a quick quote, or 📝 Book / Enquire and our team will call you with the best rate."],
    'visit' => ['👀 Visit the hall', '/visit|dekhn|dikha|see the hall|show the hall|बघाय|पहाय|देखन/iu',
        "👀 Yes, you are welcome to visit and see the halls, dining spaces and guest rooms.\n🕘 Mon – Sun, 9:00 AM – 10:00 PM\n\nTap 📞 Talk to team to fix a time.\n📍 " . WA_BOT_MAP],
    'location' => ['📍 Location / address', '/address|location|where|kaha|kidhar|\bmap\b|direction|reach|पत्ता|कुठे|कहाँ|कहां/iu',
        "📍 " . WA_BOT_ADDRESS . "\n\nGoogle Maps: " . WA_BOT_MAP],
    'catering' => ['🍽️ Food / catering', '/cater|food|khana|jevan|lunch|dinner|halwai|menu card|जेवण|खाना|आचारी/iu',
        "🍽️ You can bring your own caterer – catering support is available.\nBig Hall dining: 400 at a time · VIP A/C Dining: 50 · Small Hall dining: 50 at a time.\n\nPlease confirm current arrangements with our team."],
    'rooms' => ['🛏️ Guest rooms', '/room|stay|accommodation|रूम|खोली/iu',
        "🛏️ Up to 8 air-conditioned guest rooms are available at the venue.\nPlease confirm availability for your date with our team."],
    'facilities' => ['🎤 Facilities / A/C', '/\bac\b|a\/c|air.?condition|sound|speaker|\bdj\b|\bmic\b|projector|\bled\b|screen|wi-?fi|facilit|सुविधा/iu',
        "🎤 Facilities\n❄️ Fully air-conditioned halls and dining spaces\n🔊 Acoustic sound system\n📺 LED screen and projector\n📶 Wi-Fi\n🚗 Parking"],
    'parking' => ['🚗 Parking', '/parking|park\b|पार्किंग/iu',
        "🚗 Yes, parking is available at the venue. Please ask our team about parking for your guest count."],
    'timing' => ['🕘 Timings', '/timing|hours|open|close|kab tak|kitne baje|वेळ|समय/iu',
        "🕘 The venue is open Monday – Sunday, 9:00 AM – 10:00 PM."],
];

function wa_bot_faq_match(string $text): string
{
    foreach (WA_BOT_FAQ as $key => [, $pattern]) {
        if (preg_match($pattern, $text)) return $key;
    }
    return '';
}

function wa_bot_faq(PDO $db, array $wa, string $phone, string $key): void
{
    wa_bot_save($db, $phone, 'menu');
    wa_bot_buttons($db, $wa, $phone, WA_BOT_FAQ[$key][2], ['m:book' => '📝 Book / Enquire', 'm:info' => 'ℹ️ More info', 'm:team' => '📞 Talk to team']);
}

function wa_bot_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $db->exec($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'CREATE TABLE IF NOT EXISTS wa_bot_state (phone TEXT PRIMARY KEY, step TEXT NOT NULL, data TEXT, updated_at INTEGER NOT NULL)'
        : 'CREATE TABLE IF NOT EXISTS wa_bot_state (phone VARCHAR(20) NOT NULL PRIMARY KEY, step VARCHAR(20) NOT NULL, data TEXT NULL, updated_at INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
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

function wa_bot_menu(PDO $db, array $wa, string $phone, string $name, string $lead = ''): void
{
    $hello = $lead !== '' ? $lead : 'Namaste' . ($name !== '' ? ' ' . $name : '') . ' 🙏 Welcome to Late Venutai Chavan Multipurpose Hall, Nigdi.';
    // DPDP notice on every menu: where the policy is and how to opt out.
    $privacy = "\n\n🔒 We use your details only to reply about your booking. Privacy: venutaihall.com/privacy-policy · Reply STOP to opt out.";
    wa_bot_save($db, $phone, 'menu');
    wa_bot_buttons($db, $wa, $phone, $hello . "\n\nHow can we help you?" . $privacy,['m:dates' => '📅 Available dates', 'm:book' => '📝 Book / Enquire', 'm:info' => 'ℹ️ Hall info']);
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

/** Reads "25-12-2026", "25/12/2026", "25.12.26" or "2026-12-25"; returns Y-m-d or ''. */
function wa_bot_parse_date(string $text): string
{
    $text = trim($text);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    elseif (preg_match('/^(\d{1,2})[-\/. ](\d{1,2})[-\/. ](\d{2}|\d{4})$/', $text, $m)) [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3]];
    else return '';
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
    $id = (string) ($m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? '');
    $text = trim((string) ($m['text']['body'] ?? $m['button']['text'] ?? ''));
    // A typed question ("Hi, is parking available?") gets its answer instead of the menu.
    $topic = $id === '' && ($m['type'] ?? '') === 'text' ? wa_bot_faq_match($text) : '';
    $greeting = $topic === '' && preg_match('/^(hi+|hello|hey|hlo|namaste|namaskar|menu|start|हाय|नमस्ते|नमस्कार)\b/iu', $text);
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
        wa_bot_text($db, $wa, $phone, $erase
            ? "✅ We have received your request to delete your personal data. Our team will complete it and confirm within 30 days (records we must keep by law, such as payment receipts, are kept only as long as required).\nOur assistant will not message you again. Write Hi any time to start again."
            : "✅ Done. Our assistant will not message you again.\nTo ask us to delete your data, reply DELETE or email bookings@venutaihall.com. Write Hi any time to start again.");
        return;
    }
    if ($state['step'] === 'optout' && !$greeting && $id === '') return;
    $handedOver =$state['step'] === 'team' && $state['age'] < WA_BOT_STAFF_PAUSE;
    if ($state['age'] > 7200) $state = ['step' => '', 'data' => [], 'age' => 0];

    if ($id === '' && !$greeting) {
        // A guest in the middle of the booking form always gets an answer.
        if ($state['step'] === 'date') { wa_bot_take_date($db, $config, $wa, $phone, $name, $state['data'], $text); return; }
        // Staff are handling this chat: stay quiet.
        if ($handedOver || wa_bot_staff_active($db, $phone)) return;
        if ($topic !== '') { wa_bot_faq($db, $wa, $phone, $topic); return; }
        // Photos, voice notes and reactions, and a free-text question right after the menu, are left for staff.
        if (($m['type'] ?? '') !== 'text' || ($state['step'] === 'menu' && $state['age'] < 1800)) return;
        wa_bot_menu($db, $wa, $phone, $name, "Sorry, I didn't understand that.");
        return;
    }
    if ($id === '' || $id === 'm:menu') { wa_bot_menu($db, $wa, $phone, $name); return; }

    [$kind, $a, $b] = array_pad(explode(':', $id, 3), 3, '');
    $data = $state['data'];
    switch ($kind) {
        case 'm':
            if ($a === 'dates') {
                wa_bot_buttons($db, $wa, $phone, 'Which hall would you like to check?', ['d:big' => '🏛️ Big Hall', 'd:small' => '🏠 Small Hall (≤350)']);
            } elseif ($a === 'book') {
                wa_bot_save($db, $phone, 'book');
                wa_bot_list($db, $wa, $phone, '📝 Booking request — step 1 of 3' . "\nWhat is the occasion?", 'Choose event', array_combine(array_map(static fn($i) => 'be:' . $i, array_keys(WA_BOT_EVENTS)), WA_BOT_EVENTS));
            } elseif ($a === 'info') {
                $rows = array_combine(array_map(static fn($key) => 'i:' . $key, array_keys(WA_BOT_FAQ)), array_column(WA_BOT_FAQ, 0));
                wa_bot_list($db, $wa, $phone, 'ℹ️ What would you like to know?', 'Choose topic', $rows + ['m:team' => '📞 Talk to team']);
            } elseif ($a === 'team') {
                wa_bot_save($db, $phone, 'team');
                wa_bot_text($db, $wa, $phone, "Thank you! 🙏 Our team will reply here or call you on this number shortly.\nFor urgent help call " . wa_bot_venue_phone($db) . '.');
            }
            return;
        case 'i':
            if (isset(WA_BOT_FAQ[$a])) wa_bot_faq($db, $wa, $phone, $a);
            return;
        case 'd':
            if (!isset(WA_BOT_HALLS[$a])) return;
            $rows = [];
            for ($i = 0, $month = new DateTimeImmutable('first day of this month', new DateTimeZone('Asia/Kolkata')); $i < 6; $i++, $month = $month->modify('+1 month')) {
                $rows['dm:' . $a . ':' . $month->format('Y-m')] = $month->format('F Y');
            }
            wa_bot_list($db, $wa, $phone, WA_BOT_HALLS[$a] . ': which month?', 'Choose month', $rows);
            return;
        case 'dm':
            if (!isset(WA_BOT_HALLS[$a]) || !preg_match('/^\d{4}-\d{2}$/', $b)) return;
            $free = wa_bot_free_dates($db, $a, $b);
            $label = (new DateTimeImmutable($b . '-01'))->format('F Y');
            $body = $free ? '📅 ' . WA_BOT_HALLS[$a] . " – free dates in {$label}:\n" . wa_bot_ranges($free) . "\n\nDates fill fast. A date is held only after our team confirms the booking."
                : '😔 ' . WA_BOT_HALLS[$a] . " is fully booked in {$label}. Please check another month or hall.";
            wa_bot_buttons($db, $wa, $phone, $body, ['m:book' => '📝 Book a date', 'd:' . $a => '📅 Another month', 'm:menu' => '🏠 Menu']);
            return;
        case 'be':
            if (!isset(WA_BOT_EVENTS[(int) $a])) return;
            wa_bot_save($db, $phone, 'book', ['event' => WA_BOT_EVENTS[(int) $a]]);
            wa_bot_buttons($db, $wa, $phone, "Step 2 of 3 – which hall?\n🏛️ Big Hall: large weddings and receptions\n🏠 Small Hall: up to 350 guests", ['bh:big' => '🏛️ Big Hall', 'bh:small' => '🏠 Small Hall']);
            return;
        case 'bh':
            if (!isset(WA_BOT_HALLS[$a]) || empty($data['event'])) { wa_bot_menu($db, $wa, $phone, $name, 'Let us start again.'); return; }
            wa_bot_save($db, $phone, 'date', $data + ['hall' => $a]);
            wa_bot_text($db, $wa, $phone, "Step 3 of 3 – please type your event date like this:\n25-12-2026");
            return;
        case 'bg':
            // Optional detail after the enquiry is already saved and the team alerted.
            if (!isset(WA_BOT_GUESTS[(int) $a]) || $state['step'] !== 'guests' || empty($data['id'])) { wa_bot_menu($db, $wa, $phone, $name); return; }
            $db->prepare("UPDATE bookings SET guests = ? WHERE id = ? AND phone = ? AND source = 'whatsapp'")->execute([(int) $a, (int) $data['id'], $phone]);
            wa_bot_save($db, $phone, '');
            wa_bot_buttons($db, $wa, $phone, '👍 Noted: ' . WA_BOT_GUESTS[(int) $a] . " guests. Our team will have everything ready when they call you.", ['m:info' => 'ℹ️ Hall info', 'm:menu' => '🏠 Menu']);
            return;
        case 'bc':
            // Confirm button from the older flow; the request is now sent as soon as the date is free.
            wa_bot_menu($db, $wa, $phone, $name, 'This request has expired. Let us start again.');
            return;
    }
}

function wa_bot_venue_phone(PDO $db): string
{
    return booking_setting($db, 'venue_phone', '') ?: '9359567494';
}

function wa_bot_take_date(PDO $db, array $config, array $wa, string $phone, string $name, array $data, string $text): void
{
    if (empty($data['event']) || !isset(WA_BOT_HALLS[$data['hall'] ?? ''])) { wa_bot_menu($db, $wa, $phone, $name, 'Let us start again.'); return; }
    $date = wa_bot_parse_date($text);
    if ($date === '' || !booking_valid_date($date)) { wa_bot_text($db, $wa, $phone, "Please type a future date like this: 25-12-2026"); return; }
    $check = $db->prepare('SELECT 1 FROM bookings WHERE booked_date = ? AND hall = ? LIMIT 1');
    $check->execute([$date, $data['hall'] ?? '']);
    if ($check->fetchColumn()) {
        wa_bot_text($db, $wa, $phone, '😔 ' . WA_BOT_HALLS[$data['hall']] . ' is already booked on ' . date('d M Y', strtotime($date)) . ". Please type another date.");
        return;
    }
    // The date is free: save the enquiry and alert the sales team now; the guest count is asked after, optionally.
    wa_bot_create_enquiry($db, $config, $wa, $phone, $name, $data + ['date' => $date]);
}

function wa_bot_create_enquiry(PDO $db, array $config, array $wa, string $phone, string $name, array $data): void
{
    $name = $name !== '' ? $name : 'WhatsApp guest';
    $data['guests'] = $data['guests'] ?? null;
    $message = 'Booking request from the WhatsApp assistant. The date was free when the guest asked.';
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
    $hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('G');
    $when = $hour >= 9 && $hour < 21 ? 'within 15 minutes' : 'tomorrow morning after 9 AM';
    wa_bot_save($db, $phone, 'guests', ['id' => $id]);
    $rows = [];
    foreach (WA_BOT_GUESTS as $count => $label) $rows['bg:' . $count] = $label;
    wa_bot_list($db, $wa, $phone, "✅ Good news, {$name}! " . WA_BOT_HALLS[$data['hall']] . ' is available on ' . date('d M Y', strtotime($data['date'])) . ".\n🎉 {$data['event']}\nReference: ENQ-" . str_pad((string) $id, 6, '0', STR_PAD_LEFT)
        . "\n\n📞 Our sales team has been alerted and will call you {$when}. The date is held only after the team confirms the booking."
        . "\n\nTo help us prepare, about how many guests? (optional)", 'Choose guests', $rows);
    lead_notify($db, $config, ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => '', 'event_type' => $data['event'], 'hall' => $data['hall'], 'event_date' => $data['date'], 'guests' => $data['guests'], 'message' => $message, 'source' => 'whatsapp']);
}
