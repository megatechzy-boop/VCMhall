<?php
declare(strict_types=1);

// Daily cPanel cron: WhatsApps guests of confirmed bookings: event_reminder the day before,
// payment_due_reminder 7 days before when money is still due, and thank_you_review the day after.
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

// Each message goes once per booking: a re-run, or a later date change, never sends it twice.
$once = static fn(string $template): string => "AND NOT EXISTS (SELECT 1 FROM lead_notifications n WHERE n.booking_id = b.id AND n.status = 'sent' AND n.detail = 'Template {$template}')";

$dueDate = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->modify('+' . BOOKING_DUE_REMINDER_DAYS . ' days')->format('Y-m-d');
$query = $db->prepare("SELECT b.id FROM bookings b WHERE b.record_type = 'booking' AND b.status = 'confirmed' AND b.event_date = ? AND b.total_amount > 0
    AND b.total_amount > COALESCE((SELECT SUM(p.amount) FROM booking_payments p WHERE p.booking_id = b.id), 0) " . $once('payment_due_reminder'));
$query->execute([$dueDate]);
$sent = 0;
foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) $sent += (int) lead_booking_whatsapp($db, $config, (int) $id, 'due');
echo "Payment due reminders for {$dueDate}: {$sent} sent.\n";

$yesterday = (new DateTimeImmutable('yesterday', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
$query = $db->prepare("SELECT b.id FROM bookings b WHERE b.record_type = 'booking' AND b.status = 'confirmed' AND b.event_date = ? " . $once('thank_you_review'));
$query->execute([$yesterday]);
$sent = 0;
foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) $sent += (int) lead_booking_whatsapp($db, $config, (int) $id, 'thanks');
echo "Thank-you messages for {$yesterday}: {$sent} sent.\n";
