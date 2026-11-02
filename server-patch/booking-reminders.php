<?php
declare(strict_types=1);

// Daily cPanel cron: WhatsApps the event_reminder template to guests whose confirmed event is tomorrow.
// php /home/venutaihall/public_html/server-patch/booking-reminders.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/booking-store.php';
require __DIR__ . '/whatsapp-store.php';
require __DIR__ . '/leads-store.php';

$config = booking_config();
$db = booking_db();
lead_ensure_schema($db);
$tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
// Skips bookings already reminded, so a re-run on the same day sends nothing twice.
$query = $db->prepare("SELECT b.id FROM bookings b WHERE b.record_type = 'booking' AND b.status = 'confirmed' AND b.event_date = ?
    AND NOT EXISTS (SELECT 1 FROM lead_notifications n WHERE n.booking_id = b.id AND n.status = 'sent' AND n.detail = 'Template event_reminder')");
$query->execute([$tomorrow]);
$sent = 0;
foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) $sent += (int) lead_booking_whatsapp($db, $config, (int) $id, 'reminder');
echo "Event reminders for {$tomorrow}: {$sent} sent.\n";
