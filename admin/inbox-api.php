<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
require $bookingStore;
require dirname(__DIR__) . '/server-patch/whatsapp-store.php';
require __DIR__ . '/dashboard-lib.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
admin_start_session();

function inbox_json(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Last 10 digits, so +91 98765 43210 and 9876543210 match. */
function inbox_key(string $phone): string
{
    return substr(preg_replace('/\D+/', '', $phone) ?? '', -10);
}

function inbox_records(PDO $db): array
{
    $map = [];
    $rows = $db->query("SELECT id, name, phone, event_type, event_date, hall, status, record_type FROM bookings WHERE record_type IN ('enquiry','booking') AND phone <> '' ORDER BY id DESC LIMIT 3000")->fetchAll();
    foreach ($rows as $row) {
        $key = inbox_key((string) $row['phone']);
        if (strlen($key) === 10) $map[$key][] = $row;
    }
    return $map;
}

try {
    $config = booking_config();
    $db = booking_db();
    if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) inbox_json(401, ['error' => 'Please sign in again.']);
    wa_ensure_schema($db);
} catch (Throwable $error) {
    error_log('Inbox API setup: ' . $error->getMessage());
    inbox_json(503, ['error' => 'The inbox is unavailable right now.']);
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) inbox_json(403, ['error' => 'Your session expired. Refresh the page.']);
    $phone = wa_phone((string) ($_POST['phone'] ?? ''));
    try {
        $wa = wa_config($db, $config);
        $by = (string) ($_SESSION['admin_username'] ?? 'admin');
        if ($action === 'send') {
            if (!wa_window_open($db, $phone)) inbox_json(409, ['error' => 'The 24-hour reply window has closed. Send the approved template to re-open the chat.']);
            wa_send($db, $wa, $phone, (string) ($_POST['body'] ?? ''), $by);
            inbox_json(200, ['ok' => true]);
        }
        if ($action === 'template') {
            wa_send($db, $wa, $phone, '', $by, true);
            inbox_json(200, ['ok' => true]);
        }
    } catch (InvalidArgumentException | RuntimeException $error) {
        inbox_json(422, ['error' => $error->getMessage()]);
    }
    inbox_json(400, ['error' => 'Unknown action.']);
}

if ($action === 'unread') {
    inbox_json(200, ['unread' => wa_unread_count($db)]);
}

if ($action === 'list') {
    $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80));
    $where = '';
    $params = [];
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $where = " AND m.phone IN (SELECT phone FROM wa_messages WHERE phone LIKE ? ESCAPE '!' OR contact_name LIKE ? ESCAPE '!' OR body LIKE ? ESCAPE '!')";
        $params = [$like, $like, $like];
    }
    $query = $db->prepare("SELECT m.phone, m.body, m.msg_type, m.direction, m.status, m.sent_at,
            (SELECT n.contact_name FROM wa_messages n WHERE n.phone = m.phone AND n.contact_name IS NOT NULL ORDER BY n.id DESC LIMIT 1) AS name,
            (SELECT COUNT(*) FROM wa_messages u WHERE u.phone = m.phone AND u.direction = 'in' AND u.read_at IS NULL) AS unread
        FROM wa_messages m WHERE m.id IN (SELECT MAX(id) FROM wa_messages GROUP BY phone)$where ORDER BY m.id DESC LIMIT 200");
    $query->execute($params);
    $records = inbox_records($db);
    $out = [];
    foreach ($query->fetchAll() as $row) {
        $linked = $records[inbox_key((string) $row['phone'])][0] ?? null;
        $out[] = [
            'phone' => $row['phone'],
            'name' => $row['name'] ?: ($linked['name'] ?? ''),
            'preview' => $row['body'] !== null && $row['body'] !== '' ? mb_substr((string) $row['body'], 0, 90) : ucfirst((string) $row['msg_type']),
            'type' => $row['msg_type'],
            'direction' => $row['direction'],
            'status' => $row['status'],
            'time' => $row['sent_at'],
            'unread' => (int) $row['unread'],
            'linked' => $linked ? ($linked['record_type'] === 'booking' ? 'Booking' : 'Enquiry') : '',
        ];
    }
    inbox_json(200, ['conversations' => $out, 'unread' => wa_unread_count($db)]);
}

if ($action === 'thread') {
    $phone = wa_phone((string) ($_GET['phone'] ?? ''));
    $after = max(0, (int) ($_GET['after'] ?? 0));
    if ($phone === '') inbox_json(422, ['error' => 'Choose a conversation.']);
    $query = $db->prepare('SELECT * FROM (SELECT id, direction, msg_type, body, media_id, media_mime, status, error, sent_by, sent_at FROM wa_messages WHERE phone = ? AND id > ? ORDER BY id DESC LIMIT 300) recent ORDER BY id ASC');
    $query->execute([$phone, $after]);
    $messages = $query->fetchAll();
    foreach ($messages as &$message) {
        $message['id'] = (int) $message['id'];
        $message['has_media'] = $message['media_id'] !== null;
        unset($message['media_id']);
    }
    unset($message);

    $unread = $db->prepare("SELECT wa_id FROM wa_messages WHERE phone = ? AND direction = 'in' AND read_at IS NULL ORDER BY id DESC LIMIT 1");
    $unread->execute([$phone]);
    $latestUnread = $unread->fetchColumn();
    if ($latestUnread !== false) {
        $db->prepare("UPDATE wa_messages SET read_at = ? WHERE phone = ? AND direction = 'in' AND read_at IS NULL")->execute([wa_now(), $phone]);
        try {
            // Blue ticks for the guest; failure here must not block reading the chat.
            $wa = wa_config($db, $config);
            if ($wa['token'] !== '' && $wa['phone_id'] !== '') wa_api($wa, 'POST', rawurlencode($wa['phone_id']) . '/messages', ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => (string) $latestUnread]);
        } catch (Throwable) {
        }
    }

    $nameQuery = $db->prepare('SELECT contact_name FROM wa_messages WHERE phone = ? AND contact_name IS NOT NULL ORDER BY id DESC LIMIT 1');
    $nameQuery->execute([$phone]);
    $records = array_slice(inbox_records($db)[inbox_key($phone)] ?? [], 0, 5);
    $windowQuery = $db->prepare("SELECT MAX(sent_at) FROM wa_messages WHERE phone = ? AND direction = 'in'");
    $windowQuery->execute([$phone]);
    $lastIn = $windowQuery->fetchColumn();
    inbox_json(200, [
        'phone' => $phone,
        'name' => (string) ($nameQuery->fetchColumn() ?: ($records[0]['name'] ?? '')),
        'messages' => $messages,
        'window_open' => is_string($lastIn) && strtotime($lastIn) > time() - 86400,
        'window_closes' => is_string($lastIn) ? date('Y-m-d H:i:s', strtotime($lastIn) + 86400) : null,
        'records' => array_map(static fn($r) => [
            'id' => (int) $r['id'],
            'type' => $r['record_type'],
            'ref' => ($r['record_type'] === 'booking' ? 'VCM-' : 'ENQ-') . str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT),
            'event' => $r['event_type'],
            'date' => $r['event_date'],
            'hall' => ucfirst((string) $r['hall']) . ' Hall',
            'status' => $r['status'],
        ], $records),
        'unread' => wa_unread_count($db),
    ]);
}

inbox_json(400, ['error' => 'Unknown action.']);
