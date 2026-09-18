<?php
declare(strict_types=1);

function booking_config(): array
{
    $file = __DIR__ . '/booking-config.php';
    if (!is_file($file)) {
        throw new RuntimeException('Booking configuration is missing.');
    }
    $config = require $file;
    if (!is_array($config)) {
        throw new RuntimeException('Booking configuration is invalid.');
    }
    return $config;
}

function booking_db(): PDO
{
    $path = booking_config()['database'] ?? '';
    if (is_string($path)) {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
    $folder = is_string($path) ? realpath(dirname($path)) : false;
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
    if (!$folder || !is_writable($folder) || !is_string($path) || !str_starts_with($path, $folder . DIRECTORY_SEPARATOR)
        || ($root && ($folder === $root || str_starts_with($folder . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)))) {
        throw new RuntimeException('Booking database must be in a writable folder outside the public website.');
    }
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT '',
        event_type TEXT NOT NULL,
        event_date TEXT NOT NULL,
        guests INTEGER,
        message TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'blocked', 'cancelled')),
        source TEXT NOT NULL DEFAULT 'website',
        email_state TEXT NOT NULL DEFAULT 'not_configured',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS one_booking_per_date ON bookings(event_date) WHERE status IN ('confirmed', 'blocked')");
    return $db;
}

function booking_today(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
}

function booking_valid_date(string $date): bool
{
    $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date && $date >= booking_today();
}

function booking_send_email(PDO $db, int $id, array $row): void
{
    $config = booking_config();
    $to = ($config['email_to'] ?? '') ?: 'bookings@venutaihall.com';
    $from = ($config['email_from'] ?? '') ?: 'bookings@venutaihall.com';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subject = 'New venue enquiry #' . $id;
    $body = "New venue enquiry\n\n" . implode("\n", [
        'Reference: ' . $id,
        'Name: ' . $row['name'],
        'Phone: ' . $row['phone'],
        'Email: ' . $row['email'],
        'Event: ' . $row['event_type'],
        'Date: ' . $row['event_date'],
        'Guests: ' . ($row['guests'] ?? ''),
        'Message: ' . $row['message'],
    ]) . "\n\nConfirm or cancel this request in the admin panel. The date is not blocked until confirmation.";
    $headers = ['From' => $from, 'Content-Type' => 'text/plain; charset=UTF-8'];
    if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        $headers['Reply-To'] = $row['email'];
    }
    $sent = false;
    try {
        $sent = mail($to, $subject, $body, $headers);
    } catch (Throwable) {
        // Keep the saved request visible to the admin when mail delivery fails.
    }
    $update = $db->prepare('UPDATE bookings SET email_state = ? WHERE id = ?');
    $update->execute([$sent ? 'accepted_by_mail_server' : 'failed', $id]);
}
