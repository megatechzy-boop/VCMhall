<?php
declare(strict_types=1);

// Meta webhook for the WhatsApp Business Account (messages, template status) and the
// Facebook Page (Lead Ads leadgen): verification handshake (GET) and events (POST).
$store = __DIR__ . '/booking-store.php';
if (!is_file($store)) $store = __DIR__ . '/server-patch/booking-store.php';
require $store;
require __DIR__ . '/server-patch/whatsapp-store.php';
require __DIR__ . '/server-patch/leads-store.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $config = booking_config();
    $db = booking_db();
    $wa = wa_config($db, $config);
} catch (Throwable $error) {
    error_log('WhatsApp webhook setup: ' . $error->getMessage());
    http_response_code(503);
    exit('Unavailable');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? '');
    if ($mode === 'subscribe' && $wa['verify_token'] !== '' && hash_equals($wa['verify_token'], $token) && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $challenge)) {
        exit($challenge);
    }
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$raw = (string) file_get_contents('php://input', false, null, 0, 1048576);
if (!wa_valid_signature($raw, (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''), $wa['app_secret'])) {
    http_response_code(403);
    exit('Invalid signature');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid payload');
}

$leadgen = [];
try {
    if (($payload['object'] ?? '') === 'page') {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'leadgen' && is_array($change['value'] ?? null)) $leadgen[] = $change['value'];
            }
        }
    } else {
        wa_handle_webhook($db, $payload, $wa['phone_id']);
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'message_template_status_update') wa_template_status_event($db, (array) ($change['value'] ?? []));
            }
        }
    }
} catch (Throwable $error) {
    // Meta retries non-2xx responses, so a storage failure is reported back for a retry.
    error_log('Meta webhook storage: ' . $error->getMessage());
    http_response_code(500);
    exit('Error');
}
echo 'OK';
if ($leadgen) {
    // Reply to Meta first; fetching each lead and sending alerts can take several seconds.
    lead_finish_response();
    foreach ($leadgen as $value) {
        try {
            meta_handle_leadgen($db, $config, $value);
        } catch (Throwable $error) {
            error_log('Meta leadgen: ' . $error->getMessage());
        }
    }
}
exit;
