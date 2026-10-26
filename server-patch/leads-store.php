<?php
declare(strict_types=1);

// Lead alerts: notify the guest and every active team member by WhatsApp and email
// when a website or Meta Lead Ads enquiry arrives, and log each attempt.
// Requires booking-store.php and whatsapp-store.php to be loaded first.

const LEAD_SOURCE_LABELS = ['website' => 'Website', 'meta-ads' => 'Meta Ads', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'phone' => 'Phone', 'walk-in' => 'Walk-in', 'other' => 'Other'];
// Default template wording; the templates page submits these to Meta. Variable order is fixed by lead_template_params().
const LEAD_DEFAULT_TEMPLATES = [
    'customer' => ['name' => 'enquiry_received', 'body' => "Namaste {{1}}, thank you for your {{2}} enquiry at Late Venutai Chavan Multipurpose Hall, Nigdi. Our team will call you shortly to discuss dates and details. For urgent help, reply to this message."],
    'team' => ['name' => 'new_lead_alert', 'body' => "New {{1}} lead received.\nName: {{2}}\nPhone: {{3}}\nEvent: {{4}}\nDate: {{5}}\nPlease call the guest as soon as possible."],
];

function lead_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    wa_ensure_schema($db);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec('CREATE TABLE IF NOT EXISTS lead_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT, email TEXT, notify_whatsapp INTEGER NOT NULL DEFAULT 1, notify_email INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE IF NOT EXISTS lead_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER, audience TEXT NOT NULL, channel TEXT NOT NULL, recipient TEXT NOT NULL, status TEXT NOT NULL, detail TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE INDEX IF NOT EXISTS lead_notifications_booking ON lead_notifications(booking_id)');
        $db->exec('CREATE TABLE IF NOT EXISTS meta_leads (id INTEGER PRIMARY KEY AUTOINCREMENT, leadgen_id TEXT NOT NULL UNIQUE, page_id TEXT, form_id TEXT, booking_id INTEGER, status TEXT NOT NULL, error TEXT, raw_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    } else {
        $db->exec('CREATE TABLE IF NOT EXISTS lead_recipients (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL, phone VARCHAR(20) NULL, email VARCHAR(254) NULL,
            notify_whatsapp TINYINT(1) NOT NULL DEFAULT 1, notify_email TINYINT(1) NOT NULL DEFAULT 1, active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->exec('CREATE TABLE IF NOT EXISTS lead_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NULL, audience VARCHAR(10) NOT NULL, channel VARCHAR(10) NOT NULL,
            recipient VARCHAR(254) NOT NULL, status VARCHAR(10) NOT NULL, detail VARCHAR(500) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY lead_notifications_booking (booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->exec('CREATE TABLE IF NOT EXISTS meta_leads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            leadgen_id VARCHAR(64) NOT NULL, page_id VARCHAR(64) NULL, form_id VARCHAR(64) NULL, booking_id INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL, error VARCHAR(500) NULL, raw_json MEDIUMTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY meta_leads_leadgen (leadgen_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $done = true;
}

/** WhatsApp number in international form; a plain 10-digit Indian mobile gets the 91 prefix. */
function lead_wa_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);
    if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) $digits = '91' . $digits;
    return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : '';
}

function lead_setting_on(PDO $db, string $key): bool
{
    return booking_setting($db, $key, '1') === '1';
}

function lead_log(PDO $db, ?int $bookingId, string $audience, string $channel, string $recipient, string $status, string $detail = ''): void
{
    try {
        $db->prepare('INSERT INTO lead_notifications (booking_id, audience, channel, recipient, status, detail) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$bookingId, $audience, $channel, mb_substr($recipient, 0, 254), $status, $detail !== '' ? mb_substr($detail, 0, 500) : null]);
    } catch (Throwable $error) {
        error_log('Lead log failed: ' . $error->getMessage());
    }
}

function lead_event_date_label(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : 'Not given';
}

/** Template variables in the order used by LEAD_DEFAULT_TEMPLATES. */
function lead_template_params(string $audience, array $lead): array
{
    if ($audience === 'customer') return [$lead['name'] ?: 'there', $lead['event_type'] ?: 'event'];
    return [LEAD_SOURCE_LABELS[$lead['source']] ?? 'new', $lead['name'] ?: '-', $lead['phone'] ?: '-', $lead['event_type'] ?: '-', lead_event_date_label($lead['event_date'] ?? null)];
}

function lead_render(string $body, array $params): string
{
    return preg_replace_callback('/\{\{(\d+)\}\}/', static fn($m) => (string) ($params[(int) $m[1] - 1] ?? ''), $body) ?? $body;
}

function lead_email_text(string $audience, array $lead, PDO $db): array
{
    $venue = 'Late Venutai Chavan Multipurpose Hall';
    $phone = booking_setting($db, 'venue_phone', '9359567494');
    if ($audience === 'customer') {
        return [
            'We received your enquiry – ' . $venue,
            "Namaste {$lead['name']},\n\nThank you for your " . ($lead['event_type'] ?: 'event') . " enquiry at {$venue}, Nigdi, Pune.\n"
            . ($lead['event_date'] ? 'Requested date: ' . lead_event_date_label($lead['event_date']) . "\n" : '')
            . "\nOur team will call you shortly to discuss availability and details. Your date is not booked until our team confirms it.\n\n"
            . "For quick help call or WhatsApp {$phone}.\n\nWarm regards,\n{$venue}",
        ];
    }
    $source = LEAD_SOURCE_LABELS[$lead['source']] ?? $lead['source'];
    return [
        "New {$source} lead: {$lead['name']}",
        "A new lead has arrived.\n\nSource: {$source}\nName: {$lead['name']}\nPhone: {$lead['phone']}\nEmail: " . ($lead['email'] ?: '-')
        . "\nEvent: " . ($lead['event_type'] ?: '-') . "\nDate: " . lead_event_date_label($lead['event_date'] ?? null)
        . "\nHall: " . ucfirst((string) ($lead['hall'] ?: '-')) . "\nGuests: " . ($lead['guests'] ?: '-')
        . ($lead['message'] ? "\n\nMessage:\n{$lead['message']}" : '')
        . "\n\nReference: ENQ-" . str_pad((string) ($lead['id'] ?? 0), 6, '0', STR_PAD_LEFT) . "\nOpen the admin panel to follow up.",
    ];
}

/** Sends every enabled guest and team alert for one lead. Never throws; every attempt is logged. */
function lead_notify(PDO $db, array $config, array $lead): void
{
    try {
        lead_ensure_schema($db);
        $wa = wa_config($db, $config);
    } catch (Throwable $error) {
        error_log('Lead alerts unavailable: ' . $error->getMessage());
        return;
    }
    $bookingId = isset($lead['id']) ? (int) $lead['id'] : null;
    $waReady = $wa['token'] !== '' && $wa['phone_id'] !== '';
    $jobs = [];

    if (lead_setting_on($db, 'lead_customer_whatsapp')) $jobs[] = ['customer', 'whatsapp', $lead['phone'] ?? ''];
    if (lead_setting_on($db, 'lead_customer_email')) $jobs[] = ['customer', 'email', $lead['email'] ?? ''];
    $team = $db->query('SELECT name, phone, email, notify_whatsapp, notify_email FROM lead_recipients WHERE active = 1 ORDER BY id')->fetchAll();
    foreach ($team as $member) {
        if ((int) $member['notify_whatsapp'] && lead_setting_on($db, 'lead_team_whatsapp')) $jobs[] = ['team', 'whatsapp', (string) $member['phone']];
        if ((int) $member['notify_email'] && lead_setting_on($db, 'lead_team_email')) $jobs[] = ['team', 'email', (string) $member['email']];
    }

    foreach ($jobs as [$audience, $channel, $to]) {
        try {
            if ($channel === 'email') {
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { if ($to !== '') lead_log($db, $bookingId, $audience, 'email', $to, 'skipped', 'Invalid email address.'); continue; }
                [$subject, $body] = lead_email_text($audience, $lead, $db);
                $result = booking_send_email_message($db, $config, $to, $subject, $body, $audience === 'team' ? (string) ($lead['email'] ?? '') : '');
                lead_log($db, $bookingId, $audience, 'email', $to, $result['sent'] ? 'sent' : 'failed', (string) $result['message']);
                continue;
            }
            $number = lead_wa_number($to);
            if ($number === '') { if ($to !== '') lead_log($db, $bookingId, $audience, 'whatsapp', $to, 'skipped', 'Invalid WhatsApp number.'); continue; }
            $template = booking_setting($db, "lead_{$audience}_template", LEAD_DEFAULT_TEMPLATES[$audience]['name']);
            if (!$waReady) { lead_log($db, $bookingId, $audience, 'whatsapp', $number, 'skipped', 'WhatsApp is not connected yet.'); continue; }
            if ($template === '') { lead_log($db, $bookingId, $audience, 'whatsapp', $number, 'skipped', 'No template chosen.'); continue; }
            $params = lead_template_params($audience, $lead);
            wa_send_template($db, $wa, $number, $template, booking_setting($db, "lead_{$audience}_template_lang", 'en'), $params, 'lead-alerts', $audience === 'customer');
            lead_log($db, $bookingId, $audience, 'whatsapp', $number, 'sent', 'Template ' . $template);
        } catch (Throwable $error) {
            lead_log($db, $bookingId, $audience, $channel, (string) $to, 'failed', $error->getMessage());
        }
    }
}

/** Sends the HTTP response now, so slow notification calls do not delay the visitor. */
function lead_finish_response(): void
{
    if (function_exists('litespeed_finish_request')) litespeed_finish_request();
    elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { @ob_end_flush(); flush(); }
    ignore_user_abort(true);
    @set_time_limit(120);
}

/* ---------- Meta Lead Ads ---------- */

function meta_page_token(PDO $db, array $config): string
{
    return booking_decrypt_secret(booking_setting($db, 'meta_page_token_enc'), $config);
}

/** Maps Meta lead form answers to enquiry fields; unknown questions go into the message. */
function meta_map_lead(array $fieldData): array
{
    $lead = ['name' => '', 'phone' => '', 'email' => '', 'event_type' => '', 'event_date' => null, 'hall' => 'big', 'guests' => null, 'message' => ''];
    $first = $last = '';
    $extra = [];
    foreach ($fieldData as $field) {
        $key = strtolower((string) ($field['name'] ?? ''));
        $value = trim(implode(', ', array_map('strval', (array) ($field['values'] ?? []))));
        if ($value === '') continue;
        if ($key === 'full_name' || $key === 'name') $lead['name'] = $value;
        elseif ($key === 'first_name') $first = $value;
        elseif ($key === 'last_name') $last = $value;
        elseif ($key === 'phone_number' || $key === 'phone' || str_contains($key, 'mobile') || str_contains($key, 'whatsapp')) $lead['phone'] = $lead['phone'] ?: $value;
        elseif ($key === 'email') $lead['email'] = $value;
        elseif (str_contains($key, 'date')) {
            $time = strtotime(str_replace('/', '-', $value));
            if ($time && $time >= strtotime('today')) $lead['event_date'] = date('Y-m-d', $time);
            else $extra[] = $field['name'] . ': ' . $value;
        } elseif (str_contains($key, 'event') || str_contains($key, 'function') || str_contains($key, 'occasion')) $lead['event_type'] = $value;
        elseif (str_contains($key, 'guest') || str_contains($key, 'people') || str_contains($key, 'pax')) {
            $lead['guests'] = ctype_digit(preg_replace('/\D+/', '', $value) ?? '') && preg_replace('/\D+/', '', $value) !== '' ? min(10000, (int) preg_replace('/\D+/', '', $value)) : null;
            if ($lead['guests'] === null) $extra[] = $field['name'] . ': ' . $value;
        } elseif (str_contains($key, 'hall')) {
            $lead['hall'] = str_contains(strtolower($value), 'small') ? 'small' : 'big';
        } else $extra[] = str_replace('_', ' ', (string) $field['name']) . ': ' . $value;
    }
    if ($lead['name'] === '') $lead['name'] = trim($first . ' ' . $last);
    if ($lead['name'] === '') $lead['name'] = 'Meta lead';
    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) $lead['email'] = '';
    $lead['event_type'] = mb_substr($lead['event_type'] ?: 'Other Event', 0, 40);
    $lead['message'] = mb_substr(implode("\n", $extra), 0, 2000);
    $lead['name'] = mb_substr($lead['name'], 0, 120);
    $lead['phone'] = mb_substr($lead['phone'], 0, 18);
    return $lead;
}

/** Handles one leadgen webhook change: fetch the lead, save it as an enquiry, send alerts. */
function meta_handle_leadgen(PDO $db, array $config, array $value): void
{
    lead_ensure_schema($db);
    $leadgenId = preg_replace('/\D+/', '', (string) ($value['leadgen_id'] ?? '')) ?? '';
    if ($leadgenId === '') return;
    $pageId = (string) ($value['page_id'] ?? '');
    $configuredPage = booking_setting($db, 'meta_page_id');
    if ($configuredPage !== '' && $pageId !== '' && $configuredPage !== $pageId) return;
    $ignore = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $claim = $db->prepare("$ignore INTO meta_leads (leadgen_id, page_id, form_id, status) VALUES (?, ?, ?, 'received')");
    $claim->execute([$leadgenId, mb_substr($pageId, 0, 64), mb_substr((string) ($value['form_id'] ?? ''), 0, 64)]);
    if ($claim->rowCount() === 0) return; // Meta retries deliveries; each lead is handled once.

    $update = $db->prepare('UPDATE meta_leads SET status = ?, error = ?, booking_id = ?, raw_json = COALESCE(?, raw_json) WHERE leadgen_id = ?');
    $token = meta_page_token($db, $config);
    if ($token === '') { $update->execute(['failed', 'Meta Page access token is not saved.', null, null, $leadgenId]); return; }
    $wa = wa_config($db, $config);
    [$code, $data] = wa_api(['token' => $token, 'version' => $wa['version'], 'base' => $wa['base']], 'GET', rawurlencode($leadgenId) . '?fields=created_time,field_data,form_id,ad_name,campaign_name');
    if ($code !== 200 || !isset($data['field_data'])) {
        $update->execute(['failed', mb_substr((string) ($data['error']['message'] ?? 'Could not read the lead from Meta (HTTP ' . $code . ').'), 0, 500), null, null, $leadgenId]);
        return;
    }
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE) ?: null;
    $lead = meta_map_lead($data['field_data']);
    if (!empty($data['campaign_name']) || !empty($data['ad_name'])) {
        $lead['message'] = trim($lead['message'] . "\nCampaign: " . ($data['campaign_name'] ?? '-') . ' / Ad: ' . ($data['ad_name'] ?? '-'));
    }
    $lead['source'] = 'meta-ads';
    try {
        $insert = $db->prepare("INSERT INTO bookings (name, phone, email, event_type, hall, event_date, guests, message, status, record_type, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'enquiry', 'meta-ads')");
        $insert->execute([$lead['name'], $lead['phone'], $lead['email'], $lead['event_type'], $lead['hall'], $lead['event_date'], $lead['guests'], $lead['message']]);
        $lead['id'] = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO booking_activity (booking_id, action, details) VALUES (?, 'enquiry_created', 'Meta Lead Ads enquiry received')")->execute([$lead['id']]);
        $update->execute(['saved', null, $lead['id'], $raw, $leadgenId]);
    } catch (Throwable $error) {
        // Keep the lead and still alert the team, so nothing is lost if the enquiry row cannot be saved.
        error_log('Meta lead save failed: ' . $error->getMessage());
        $update->execute(['not_saved', mb_substr($error->getMessage(), 0, 500), null, $raw, $leadgenId]);
    }
    lead_notify($db, $config, $lead);
}
