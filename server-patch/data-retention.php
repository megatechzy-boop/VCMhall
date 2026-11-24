<?php
declare(strict_types=1);

// DPDP retention: deletes personal data that is no longer needed, as promised in the privacy policy.
// Dry run (counts only):   php /home/venutaihall/public_html/server-patch/data-retention.php
// Daily cPanel cron:       php /home/venutaihall/public_html/server-patch/data-retention.php --apply
// Kept: bookings and their payments (record_type 'booking') and enquiries converted into a booking.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/booking-store.php';

const RETENTION_MONTHS = 12;

function retention_run(PDO $db, bool $apply, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $cutoff = $now->modify('-' . RETENTION_MONTHS . ' months')->format('Y-m-d H:i:s');
    $has = static function (string $table) use ($db): bool {
        try { $db->query("SELECT 1 FROM {$table} LIMIT 1"); return true; } catch (Throwable) { return false; }
    };

    // Enquiries that never became a booking, with no activity in the last 12 months.
    $old = $db->prepare("SELECT b.id FROM bookings b WHERE b.record_type = 'enquiry' AND b.status <> 'converted' AND b.created_at < ?
        AND NOT EXISTS (SELECT 1 FROM booking_activity a WHERE a.booking_id = b.id AND a.created_at >= ?)");
    $old->execute([$cutoff, $cutoff]);
    $ids = array_map('intval', $old->fetchAll(PDO::FETCH_COLUMN));

    $counts = ['enquiries' => count($ids)];
    $plan = [];
    if ($ids) {
        $in = implode(',', $ids);
        $plan['booking_activity'] = "DELETE FROM booking_activity WHERE booking_id IN ({$in})";
        if ($has('lead_notifications')) $plan['enquiry_alert_logs'] = "DELETE FROM lead_notifications WHERE booking_id IN ({$in})";
        $plan['enquiries'] = "DELETE FROM bookings WHERE id IN ({$in})";
    }
    $dated = [
        'whatsapp_messages' => ['wa_messages', 'sent_at', $cutoff],
        'alert_logs' => ['lead_notifications', 'created_at', $cutoff],
        'meta_leads' => ['meta_leads', 'created_at', $cutoff],
        'bot_state' => ['wa_bot_state', 'updated_at', $now->modify('-' . RETENTION_MONTHS . ' months')->getTimestamp()],
        'bot_language' => ['wa_bot_lang', 'updated_at', $now->modify('-' . RETENTION_MONTHS . ' months')->getTimestamp()],
        'rate_limit_hits' => ['rate_hits', 'created_at', $now->modify('-30 days')->getTimestamp()],
    ];
    $params = [];
    foreach ($dated as $label => [$table, $column, $before]) {
        if (!$has($table)) continue;
        $count = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} < ?");
        $count->execute([$before]);
        $counts[$label] = (int) $count->fetchColumn();
        $plan[$label . '_dated'] = "DELETE FROM {$table} WHERE {$column} < ?";
        $params[$label . '_dated'] = [$before];
    }
    if ($apply) {
        $db->beginTransaction();
        try {
            foreach ($plan as $key => $sql) $db->prepare($sql)->execute($params[$key] ?? []);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
    return ['cutoff' => $cutoff, 'counts' => $counts];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $apply = in_array('--apply', $argv, true);
    $result = retention_run(booking_db(), $apply);
    echo ($apply ? 'Deleted' : 'Would delete (dry run, add --apply)') . ' data older than ' . $result['cutoff'] . ': ' . json_encode($result['counts']) . "\n";
}
