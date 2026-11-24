<?php
declare(strict_types=1);

// WhatsApp auto-reply: menu, free dates per hall and month, and a step-by-step booking enquiry.
// Requires booking-store.php, whatsapp-store.php and leads-store.php to be loaded first.
// Button and list ids carry their own data (e.g. "dm:big:2026-12"); only the booking form keeps state.

const WA_BOT_EVENTS = ['Wedding', 'Engagement', 'Reception', 'Birthday', 'Naming Ceremony', 'Family Function', 'Corporate Event', 'Social Gathering', 'Venue Visit', 'Other Event'];
const WA_BOT_HALLS = ['big' => 'Big Hall', 'small' => 'Small Hall'];
const WA_BOT_GUESTS = [50 => 'Up to 50', 100 => 'Up to 100', 200 => 'Up to 200', 350 => 'Up to 350', 500 => 'Up to 500', 800 => '500 or more'];
const WA_BOT_STAFF_PAUSE = 43200; // seconds the bot stays quiet after staff write or the guest asks for the team

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
    wa_bot_save($db, $phone, 'menu');
    wa_bot_buttons($db, $wa, $phone, $hello . "\n\nHow can we help you?", ['m:dates' => '📅 Available dates', 'm:book' => '📝 Book / Enquire', 'm:team' => '📞 Talk to team']);
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
    return $last && strtotime((string) $last) > strtotime(wa_now()) - WA_BOT_STAFF_PAUSE;
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
    $greeting = (bool) preg_match('/^(hi+|hello|hey|hlo|namaste|namaskar|menu|start|हाय|नमस्ते|नमस्कार)\b/iu', $text);
    $state = wa_bot_state($db, $phone);
    $handedOver = $state['step'] === 'team' && $state['age'] < WA_BOT_STAFF_PAUSE;
    if ($state['age'] > 7200) $state = ['step' => '', 'data' => [], 'age' => 0];

    if ($id === '' && !$greeting) {
        // Staff are handling this chat: stay quiet.
        if ($handedOver || wa_bot_staff_active($db, $phone)) return;
        if ($state['step'] === 'date') { wa_bot_take_date($db, $wa, $phone, $state['data'], $text); return; }
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
                wa_bot_list($db, $wa, $phone, '📝 Booking request — step 1 of 4' . "\nWhat is the occasion?", 'Choose event', array_combine(array_map(static fn($i) => 'be:' . $i, array_keys(WA_BOT_EVENTS)), WA_BOT_EVENTS));
            } elseif ($a === 'team') {
                wa_bot_save($db, $phone, 'team');
                wa_bot_text($db, $wa, $phone, "Thank you! 🙏 Our team will reply here or call you on this number shortly.\nFor urgent help call " . booking_setting($db, 'venue_phone', '9359567494') . '.');
            }
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
            wa_bot_buttons($db, $wa, $phone, "Step 2 of 4 – which hall?\n🏛️ Big Hall: large weddings and receptions\n🏠 Small Hall: up to 350 guests", ['bh:big' => '🏛️ Big Hall', 'bh:small' => '🏠 Small Hall']);
            return;
        case 'bh':
            if (!isset(WA_BOT_HALLS[$a]) || empty($data['event'])) { wa_bot_menu($db, $wa, $phone, $name, 'Let us start again.'); return; }
            wa_bot_save($db, $phone, 'date', $data + ['hall' => $a]);
            wa_bot_text($db, $wa, $phone, "Step 3 of 4 – please type your event date like this:\n25-12-2026");
            return;
        case 'bg':
            if (!isset(WA_BOT_GUESTS[(int) $a]) || empty($data['date'])) { wa_bot_menu($db, $wa, $phone, $name, 'Let us start again.'); return; }
            $data['guests'] = (int) $a;
            wa_bot_save($db, $phone, 'confirm', $data);
            wa_bot_buttons($db, $wa, $phone, "Please check your request:\n\n🎉 {$data['event']}\n🏛️ " . WA_BOT_HALLS[$data['hall']] . "\n📅 " . date('d M Y', strtotime($data['date'])) . "\n👥 " . WA_BOT_GUESTS[(int) $a] . ' guests', ['bc:yes' => '✅ Send request', 'm:menu' => '🔄 Start over']);
            return;
        case 'bc':
            if ($state['step'] !== 'confirm' || empty($data['guests'])) { wa_bot_menu($db, $wa, $phone, $name, 'This request has expired. Let us start again.'); return; }
            wa_bot_create_enquiry($db, $config, $wa, $phone, $name, $data);
            return;
    }
}

function wa_bot_take_date(PDO $db, array $wa, string $phone, array $data, string $text): void
{
    $date = wa_bot_parse_date($text);
    if ($date === '' || !booking_valid_date($date)) { wa_bot_text($db, $wa, $phone, "Please type a future date like this: 25-12-2026"); return; }
    $check = $db->prepare('SELECT 1 FROM bookings WHERE booked_date = ? AND hall = ? LIMIT 1');
    $check->execute([$date, $data['hall'] ?? '']);
    if ($check->fetchColumn()) {
        wa_bot_text($db, $wa, $phone, '😔 ' . WA_BOT_HALLS[$data['hall']] . ' is already booked on ' . date('d M Y', strtotime($date)) . ". Please type another date.");
        return;
    }
    wa_bot_save($db, $phone, 'guests', $data + ['date' => $date]);
    $rows = [];
    foreach (WA_BOT_GUESTS as $count => $label) $rows['bg:' . $count] = $label;
    wa_bot_list($db, $wa, $phone, 'Step 4 of 4 – about how many guests?', 'Choose guests', $rows);
}

function wa_bot_create_enquiry(PDO $db, array $config, array $wa, string $phone, string $name, array $data): void
{
    $name = $name !== '' ? $name : 'WhatsApp guest';
    $message = 'Booking request from the WhatsApp assistant.';
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
    wa_bot_save($db, $phone, '');
    wa_bot_text($db, $wa, $phone, "✅ Thank you, {$name}! Your request is received.\nReference: ENQ-" . str_pad((string) $id, 6, '0', STR_PAD_LEFT) . "\n\nOur team will call you shortly to confirm. The date is held only after our team confirms the booking.");
    lead_notify($db, $config, ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => '', 'event_type' => $data['event'], 'hall' => $data['hall'], 'event_date' => $data['date'], 'guests' => $data['guests'], 'message' => $message, 'source' => 'whatsapp']);
}
