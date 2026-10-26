<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
require $bookingStore;
require dirname(__DIR__) . '/server-patch/whatsapp-store.php';
require __DIR__ . '/dashboard-lib.php';
require_once __DIR__ . '/session.php';
admin_start_session();

// Streams a guest's WhatsApp photo or file through the server; Meta media URLs need the access token.
const INBOX_MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/aac' => 'aac', 'audio/mp4' => 'm4a', 'video/mp4' => 'mp4', 'video/3gpp' => '3gp'];

try {
    $config = booking_config();
    $db = booking_db();
    if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) { http_response_code(401); exit; }
    wa_ensure_schema($db);
    $query = $db->prepare('SELECT media_id, msg_type FROM wa_messages WHERE id = ? AND media_id IS NOT NULL');
    $query->execute([(int) ($_GET['id'] ?? 0)]);
    $row = $query->fetch();
    if (!$row) { http_response_code(404); exit; }
    $wa = wa_config($db, $config);
    [$code, $meta] = wa_api($wa, 'GET', rawurlencode((string) $row['media_id']));
    $url = (string) ($meta['url'] ?? '');
    $mime = strtolower(trim(explode(';', (string) ($meta['mime_type'] ?? ''))[0]));
    if ($code !== 200 || !str_starts_with($url, 'https://') || !isset(INBOX_MEDIA_TYPES[$mime]) || (int) ($meta['file_size'] ?? 0) > 25 * 1024 * 1024) { http_response_code(404); exit; }

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $wa['token']]]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $status !== 200) { http_response_code(502); exit; }

    $inline = str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="whatsapp-' . (int) $_GET['id'] . '.' . INBOX_MEDIA_TYPES[$mime] . '"');
    echo $body;
} catch (Throwable $error) {
    error_log('Inbox media: ' . $error->getMessage());
    http_response_code(502);
}
