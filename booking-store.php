<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

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
    $fullConfig = booking_config();
    $sqlite = $fullConfig['sqlite'] ?? null;
    if (is_array($sqlite) && is_string($sqlite['path'] ?? null) && $sqlite['path'] !== '') {
        $newDatabase = !is_file($sqlite['path']);
        $db = new PDO('sqlite:' . $sqlite['path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys = ON');
        if ($newDatabase) {
            $schema = file_get_contents(__DIR__ . '/schema-sqlite.sql');
            if ($schema === false) throw new RuntimeException('Local SQLite schema is missing.');
            $db->exec($schema);
        }
        return $db;
    }
    $config = $fullConfig['mysql'] ?? [];
    $host = $config['host'] ?? '';
    $port = (int) ($config['port'] ?? 3306);
    $name = $config['name'] ?? '';
    $user = $config['user'] ?? '';
    $password = $config['password'] ?? '';
    if (!is_string($host) || $host === '' || !is_string($name) || $name === '' || !is_string($user) || $user === ''
        || !is_string($password) || $port < 1 || $port > 65535) {
        throw new RuntimeException('MySQL booking configuration is incomplete.');
    }
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $db->exec("SET time_zone = '+05:30'");
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
