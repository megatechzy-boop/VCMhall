<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$payload = [
    'enabled' => false,
    'type' => 'text',
    'cooldownHours' => 24,
    'delaySeconds' => 30,
    'headline' => '',
    'text' => '',
    'primaryText' => '',
    'primaryUrl' => '',
    'secondaryText' => '',
    'secondaryUrl' => '',
    'desktopImage' => '',
    'mobileImage' => '',
    'clickUrl' => '',
];

try {
    $bookingStore = __DIR__ . '/booking-store.php';
    if (!is_file($bookingStore)) $bookingStore = __DIR__ . '/server-patch/booking-store.php';
    require_once $bookingStore;
    require_once __DIR__ . '/admin/settings-lib.php';
    $db = booking_db();
    if (!settings_schema_ready($db)) throw new RuntimeException('Settings are unavailable.');
    $get = static fn(string $key, string $default = ''): string => setting($db, $key, $default);
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    $start = $get('popup_start_date');
    $end = $get('popup_end_date');
    $dateValid = static function (string $value): bool {
        if ($value === '') return true;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format('Y-m-d') === $value;
    };
    $inDateRange = $dateValid($start) && $dateValid($end) && ($start === '' || $today >= $start) && ($end === '' || $today <= $end);

    $safeUrl = static function (string $value): string {
        $value = trim($value);
        if (str_starts_with($value, '//')) return '';
        try { return setting_url($value); } catch (Throwable) { return ''; }
    };
    $asset = static function (string $value): string {
        if (!preg_match('~^assets/popups/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp)$~i', $value)) return '';
        $root = realpath(__DIR__ . '/assets/popups');
        $file = realpath(__DIR__ . '/' . $value);
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) return '';
        return $value;
    };

    $payload['type'] = $get('popup_type', 'text') === 'image' ? 'image' : 'text';
    $payload['cooldownHours'] = max(1, min(720, (int)$get('popup_cooldown_hours', '24')));
    $delay = (int)$get('popup_delay_seconds', '30');
    $payload['delaySeconds'] = in_array($delay, [30, 60], true) ? $delay : 30;
    $payload['headline'] = $get('popup_headline');
    $payload['text'] = $get('popup_text');
    $payload['primaryText'] = $get('popup_primary_text');
    $payload['primaryUrl'] = $safeUrl($get('popup_primary_url'));
    $payload['secondaryText'] = $get('popup_secondary_text');
    $payload['secondaryUrl'] = $safeUrl($get('popup_secondary_url'));
    $payload['desktopImage'] = $asset($get('popup_desktop_image'));
    $payload['mobileImage'] = $asset($get('popup_mobile_image'));
    $clickType = $get('popup_click_type', 'none');
    if ($clickType === 'enquiry') $payload['clickUrl'] = 'contact.html#enquire';
    elseif ($clickType === 'whatsapp') {
        $phone = preg_replace('/\D+/', '', $get('venue_phone')) ?? '';
        if (strlen($phone) === 10) $phone = '91' . $phone;
        if ($phone !== '') $payload['clickUrl'] = 'https://wa.me/' . $phone;
    } elseif ($clickType === 'custom') $payload['clickUrl'] = $safeUrl($get('popup_click_url'));

    if ($payload['type'] === 'image' && !$payload['desktopImage'] && !$payload['mobileImage']) $payload['type'] = 'text';
    $buttonsValid = ($payload['primaryText'] === '' || $payload['primaryUrl'] !== '') && ($payload['secondaryText'] === '' || $payload['secondaryUrl'] !== '');
    $payload['enabled'] = $get('popup_enabled') === '1' && $inDateRange && $buttonsValid;
} catch (Throwable $error) {
    error_log('Website popup configuration: ' . $error->getMessage());
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
