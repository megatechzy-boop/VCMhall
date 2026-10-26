<?php
declare(strict_types=1);

// WhatsApp Cloud API inbox: message storage, webhook parsing and sending.
// Requires booking-store.php to be loaded first (booking_db, booking_setting, secrets).

const WA_STATUS_RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

function wa_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS wa_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            wa_id TEXT UNIQUE,
            phone TEXT NOT NULL,
            contact_name TEXT,
            direction TEXT NOT NULL,
            msg_type TEXT NOT NULL DEFAULT 'text',
            body TEXT,
            media_id TEXT,
            media_mime TEXT,
            status TEXT,
            error TEXT,
            sent_by TEXT,
            sent_at TEXT NOT NULL,
            read_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec('CREATE INDEX IF NOT EXISTS wa_messages_phone ON wa_messages(phone, id)');
        $db->exec("CREATE TABLE IF NOT EXISTS wa_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, meta_id TEXT, name TEXT NOT NULL, language TEXT NOT NULL, category TEXT, status TEXT, reason TEXT, body TEXT, variable_count INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(name, language))");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS wa_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            wa_id VARCHAR(191) NULL,
            phone VARCHAR(20) NOT NULL,
            contact_name VARCHAR(120) NULL,
            direction VARCHAR(3) NOT NULL,
            msg_type VARCHAR(20) NOT NULL DEFAULT 'text',
            body TEXT NULL,
            media_id VARCHAR(191) NULL,
            media_mime VARCHAR(100) NULL,
            status VARCHAR(20) NULL,
            error VARCHAR(500) NULL,
            sent_by VARCHAR(80) NULL,
            sent_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY wa_messages_wa_id (wa_id),
            KEY wa_messages_phone (phone, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS wa_templates (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            meta_id VARCHAR(64) NULL, name VARCHAR(512) NOT NULL, language VARCHAR(15) NOT NULL,
            category VARCHAR(20) NULL, status VARCHAR(30) NULL, reason VARCHAR(500) NULL, body TEXT NULL, variable_count TINYINT UNSIGNED NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY wa_templates_name_lang (name(191), language)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $done = true;
}

function wa_phone(string $value): string
{
    return substr(preg_replace('/\D+/', '', $value) ?? '', 0, 20);
}

function wa_now(): string
{
    return date('Y-m-d H:i:s');
}

function wa_config(PDO $db, array $config): array
{
    return [
        // graph_api_base exists only for local testing against a mock; production uses Meta.
        'base' => rtrim((string) ($config['graph_api_base'] ?? 'https://graph.facebook.com'), '/'),
        'phone_id' => booking_setting($db, 'whatsapp_phone_number_id'),
        'version' => booking_setting($db, 'whatsapp_api_version', 'v20.0'),
        'token' => booking_whatsapp_token($db, $config),
        'app_secret' => booking_decrypt_secret(booking_setting($db, 'whatsapp_app_secret_enc'), $config),
        'verify_token' => booking_setting($db, 'whatsapp_verify_token'),
        'template' => booking_setting($db, 'whatsapp_inbox_template'),
        'template_lang' => booking_setting($db, 'whatsapp_inbox_template_lang', 'en'),
    ];
}

function wa_valid_signature(string $rawBody, string $header, string $appSecret): bool
{
    if ($appSecret === '' || !str_starts_with($header, 'sha256=')) return false;
    return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $appSecret), $header);
}

/** Turns one webhook message object into [type, body, media_id, media_mime]. */
function wa_message_content(array $m): array
{
    $type = (string) ($m['type'] ?? 'unknown');
    $media = $m[$type] ?? [];
    switch ($type) {
        case 'text':
            return ['text', (string) ($m['text']['body'] ?? ''), null, null];
        case 'image': case 'video': case 'audio': case 'sticker': case 'document':
            $caption = (string) ($media['caption'] ?? ($media['filename'] ?? ''));
            return [$type, $caption, isset($media['id']) ? (string) $media['id'] : null, isset($media['mime_type']) ? (string) $media['mime_type'] : null];
        case 'location':
            $label = trim((string) ($media['name'] ?? '') . ' ' . (string) ($media['address'] ?? ''));
            return ['location', ($label !== '' ? $label . "\n" : '') . ($media['latitude'] ?? '') . ',' . ($media['longitude'] ?? ''), null, null];
        case 'button':
            return ['text', (string) ($m['button']['text'] ?? ''), null, null];
        case 'interactive':
            $reply = $m['interactive']['button_reply'] ?? $m['interactive']['list_reply'] ?? [];
            return ['text', (string) ($reply['title'] ?? ''), null, null];
        case 'reaction':
            return ['reaction', (string) ($m['reaction']['emoji'] ?? ''), null, null];
        case 'contacts':
            $names = array_map(static fn($c) => (string) ($c['name']['formatted_name'] ?? ''), $m['contacts'] ?? []);
            return ['contacts', implode(', ', array_filter($names)), null, null];
        default:
            return [$type, '', null, null];
    }
}

/** Stores messages and delivery statuses from a verified webhook payload. Returns stored message count. */
function wa_handle_webhook(PDO $db, array $payload, string $phoneNumberId): int
{
    wa_ensure_schema($db);
    $ignore = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $insert = $db->prepare("$ignore INTO wa_messages (wa_id, phone, contact_name, direction, msg_type, body, media_id, media_mime, sent_at) VALUES (?, ?, ?, 'in', ?, ?, ?, ?, ?)");
    $stored = 0;
    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            if (($change['field'] ?? '') !== 'messages') continue;
            if ($phoneNumberId !== '' && (string) ($value['metadata']['phone_number_id'] ?? '') !== $phoneNumberId) continue;
            $names = [];
            foreach ($value['contacts'] ?? [] as $contact) {
                $names[wa_phone((string) ($contact['wa_id'] ?? ''))] = mb_substr((string) ($contact['profile']['name'] ?? ''), 0, 120);
            }
            foreach ($value['messages'] ?? [] as $m) {
                $phone = wa_phone((string) ($m['from'] ?? ''));
                $waId = (string) ($m['id'] ?? '');
                if ($phone === '' || $waId === '') continue;
                [$type, $body, $mediaId, $mime] = wa_message_content($m);
                $time = isset($m['timestamp']) && ctype_digit((string) $m['timestamp']) ? date('Y-m-d H:i:s', (int) $m['timestamp']) : wa_now();
                $insert->execute([mb_substr($waId, 0, 191), $phone, ($names[$phone] ?? '') ?: null, mb_substr($type, 0, 20), mb_substr($body, 0, 4096), $mediaId, $mime ? mb_substr($mime, 0, 100) : null, $time]);
                $stored += $insert->rowCount();
            }
            foreach ($value['statuses'] ?? [] as $s) {
                wa_update_status($db, (string) ($s['id'] ?? ''), (string) ($s['status'] ?? ''), (string) ($s['errors'][0]['title'] ?? ''));
            }
        }
    }
    return $stored;
}

function wa_update_status(PDO $db, string $waId, string $status, string $error = ''): void
{
    if ($waId === '' || !isset(WA_STATUS_RANK[$status])) return;
    $query = $db->prepare("SELECT status FROM wa_messages WHERE wa_id = ? AND direction = 'out'");
    $query->execute([$waId]);
    $current = $query->fetchColumn();
    if ($current === false || (WA_STATUS_RANK[(string) $current] ?? -1) >= WA_STATUS_RANK[$status]) return;
    $db->prepare('UPDATE wa_messages SET status = ?, error = ? WHERE wa_id = ?')->execute([$status, $error !== '' ? mb_substr($error, 0, 500) : null, $waId]);
}

/** Calls the Graph API. Returns [httpCode, decodedBody]. */
function wa_api(array $wa, string $method, string $path, ?array $json = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for WhatsApp.');
    if ($wa['token'] === '') throw new RuntimeException('WhatsApp is not connected yet. Finish WhatsApp setup first.');
    $ch = curl_init(($wa['base'] ?? 'https://graph.facebook.com') . '/' . rawurlencode($wa['version']) . '/' . $path);
    $headers = ['Authorization: Bearer ' . $wa['token']];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => $method];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($body) ? (json_decode($body, true) ?: []) : []];
}

/** Sends a text reply (or a template when $template is set) and records it. Returns the stored row id. */
function wa_send(PDO $db, array $wa, string $phone, string $text, string $sentBy, bool $template = false): int
{
    if ($template) {
        if ($wa['template'] === '') throw new RuntimeException('Set a chat re-open template in WhatsApp setup first.');
        return wa_send_template($db, $wa, $phone, $wa['template'], $wa['template_lang'] ?: 'en', [], $sentBy);
    }
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 4096) throw new InvalidArgumentException('Write a message of up to 4,096 characters.');
    return wa_deliver($db, $wa, $phone, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]], 'text', $text, $sentBy);
}

/** Sends an approved template with body variables ({{1}}, {{2}} …) in order. */
function wa_send_template(PDO $db, array $wa, string $phone, string $name, string $lang, array $params, string $sentBy, bool $record = true): int
{
    if (!preg_match('/^[a-z0-9_]{1,512}$/', $name)) throw new InvalidArgumentException('Invalid template name.');
    $template = ['name' => $name, 'language' => ['code' => $lang ?: 'en']];
    $known = wa_template_find($db, $name, $lang ?: 'en');
    if ($known && $known['variable_count'] !== null) $params = array_slice($params, 0, (int) $known['variable_count']);
    if ($params) {
        // WhatsApp rejects template parameters with new lines, tabs or more than four spaces in a row.
        $clean = array_map(static fn($v) => mb_substr(trim(preg_replace('/\s{2,}|[\r\n\t]+/u', ' ', (string) $v) ?? ''), 0, 200) ?: '-', array_values($params));
        $template['components'] = [['type' => 'body', 'parameters' => array_map(static fn($v) => ['type' => 'text', 'text' => $v], $clean)]];
        $params = $clean;
    }
    $preview = $known && $known['body'] !== '' ? preg_replace_callback('/\{\{(\d+)\}\}/', static fn($m) => (string) ($params[(int) $m[1] - 1] ?? ''), (string) $known['body']) : 'Template: ' . $name . ($params ? ' (' . implode(', ', $params) . ')' : '');
    return wa_deliver($db, $wa, $phone, ['type' => 'template', 'template' => $template], 'template', (string) $preview, $sentBy, $record);
}

/** Sends one message; $record = false keeps internal alerts (e.g. to staff) out of the guest inbox. */
function wa_deliver(PDO $db, array $wa, string $phone, array $message, string $type, string $text, string $sentBy, bool $record = true): int
{
    wa_ensure_schema($db);
    $phone = wa_phone($phone);
    if (strlen($phone) < 10) throw new InvalidArgumentException('Invalid WhatsApp number.');
    if ($wa['phone_id'] === '') throw new RuntimeException('WhatsApp is not connected yet. Finish WhatsApp setup first.');
    $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone] + $message;
    [$code, $response] = wa_api($wa, 'POST', rawurlencode($wa['phone_id']) . '/messages', $payload);
    $waId = (string) ($response['messages'][0]['id'] ?? '');
    $ok = $code >= 200 && $code < 300 && $waId !== '';
    $error = $ok ? null : mb_substr((string) ($response['error']['error_data']['details'] ?? $response['error']['message'] ?? 'WhatsApp did not accept the message.'), 0, 500);
    $id = 0;
    if ($record) {
        $db->prepare("INSERT INTO wa_messages (wa_id, phone, direction, msg_type, body, status, error, sent_by, sent_at) VALUES (?, ?, 'out', ?, ?, ?, ?, ?, ?)")
            ->execute([$ok ? $waId : null, $phone, $type, mb_substr($text, 0, 4096), $ok ? 'sent' : 'failed', $error, mb_substr($sentBy, 0, 80), wa_now()]);
        $id = (int) $db->lastInsertId();
    }
    if (!$ok) {
        error_log('WhatsApp send failed with HTTP ' . $code . '.');
        throw new RuntimeException('Message not sent: ' . $error);
    }
    return $id;
}

/** True when the guest wrote in the last 24 hours, so a free-form reply is allowed. */
function wa_window_open(PDO $db, string $phone): bool
{
    $query = $db->prepare("SELECT MAX(sent_at) FROM wa_messages WHERE phone = ? AND direction = 'in'");
    $query->execute([wa_phone($phone)]);
    $last = $query->fetchColumn();
    return is_string($last) && strtotime($last) > time() - 86400;
}

function wa_unread_count(PDO $db): int
{
    try {
        wa_ensure_schema($db);
        return (int) $db->query("SELECT COUNT(*) FROM wa_messages WHERE direction = 'in' AND read_at IS NULL")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function wa_template_find(PDO $db, string $name, string $lang): ?array
{
    try {
        $query = $db->prepare('SELECT * FROM wa_templates WHERE name = ? AND language = ?');
        $query->execute([$name, $lang]);
        $row = $query->fetch();
        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

function wa_template_variable_count(string $body): int
{
    preg_match_all('/\{\{(\d+)\}\}/', $body, $m);
    return $m[1] ? max(array_map('intval', $m[1])) : 0;
}

/** Saves one template as reported by Meta (list sync or create response). */
function wa_template_store(PDO $db, array $t): void
{
    $body = '';
    foreach ($t['components'] ?? [] as $component) if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') $body = (string) ($component['text'] ?? '');
    $name = (string) ($t['name'] ?? '');
    $lang = (string) ($t['language'] ?? '');
    if ($name === '' || $lang === '') return;
    $values = [(string) ($t['id'] ?? ''), (string) ($t['category'] ?? ''), (string) ($t['status'] ?? ''), mb_substr((string) ($t['rejected_reason'] ?? ''), 0, 500), $body, wa_template_variable_count($body), wa_now(), $name, $lang];
    $update = $db->prepare('UPDATE wa_templates SET meta_id = ?, category = ?, status = ?, reason = ?, body = ?, variable_count = ?, updated_at = ? WHERE name = ? AND language = ?');
    $update->execute($values);
    if ($update->rowCount() === 0 && !wa_template_find($db, $name, $lang)) {
        $db->prepare('INSERT INTO wa_templates (meta_id, category, status, reason, body, variable_count, updated_at, name, language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($values);
    }
}

/** Applies a message_template_status_update webhook event. */
function wa_template_status_event(PDO $db, array $value): void
{
    $name = (string) ($value['message_template_name'] ?? '');
    $lang = (string) ($value['message_template_language'] ?? '');
    $event = strtoupper((string) ($value['event'] ?? ''));
    if ($name === '' || $lang === '' || $event === '') return;
    $reason = (string) ($value['reason'] ?? '');
    $db->prepare('UPDATE wa_templates SET status = ?, reason = ?, updated_at = ? WHERE name = ? AND language = ?')
        ->execute([mb_substr($event, 0, 30), $reason !== '' && $reason !== 'NONE' ? mb_substr($reason, 0, 500) : null, wa_now(), $name, $lang]);
}
