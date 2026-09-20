<?php
declare(strict_types=1);

const ADMIN_HALLS = ['small', 'big'];

function admin_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function admin_text_cut(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

function admin_schema_ready(PDO $db): bool
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $tables = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ('booking_payments', 'booking_activity')");
        $columns = array_column($db->query("PRAGMA table_info(bookings)")->fetchAll(), 'name');
        return (int) $tables->fetchColumn() === 2 && count(array_intersect(['total_amount', 'internal_notes', 'follow_up_at', 'confirmed_at', 'hall'], $columns)) === 5;
    }
    $query = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME IN ('total_amount', 'internal_notes', 'follow_up_at', 'confirmed_at', 'hall')");
    $tables = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('booking_payments', 'booking_activity')");
    return (int) $query->fetchColumn() === 5 && (int) $tables->fetchColumn() === 2;
}

function admin_activity(PDO $db, int $bookingId, string $action, string $details = ''): void
{
    $query = $db->prepare('INSERT INTO booking_activity (booking_id, action, details) VALUES (?, ?, ?)');
    $query->execute([$bookingId, admin_text_cut($action, 40), admin_text_cut($details, 500)]);
}

function admin_convert_enquiry(PDO $db, int $enquiryId): int
{
    $db->beginTransaction();
    try {
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $db->prepare("SELECT * FROM bookings WHERE id = ? AND record_type = 'enquiry'" . $lock);
        $query->execute([$enquiryId]);
        $enquiry = $query->fetch();
        if (!$enquiry || !in_array($enquiry['status'], ['pending', 'in_discussion', 'quote_sent', 'follow_up'], true)) {
            throw new RuntimeException('Only an active enquiry can be converted.');
        }
        $duplicate = $db->prepare("SELECT id FROM bookings WHERE enquiry_id = ? AND record_type = 'booking' AND status = 'confirmed' LIMIT 1");
        $duplicate->execute([$enquiryId]);
        if ($duplicate->fetchColumn()) throw new RuntimeException('This enquiry already has an active booking.');
        $notesQuery = $db->prepare('SELECT note FROM enquiry_notes WHERE enquiry_id = ? ORDER BY created_at, id');
        $notesQuery->execute([$enquiryId]);
        $notes = implode("\n\n", $notesQuery->fetchAll(PDO::FETCH_COLUMN));
        $insert = $db->prepare("INSERT INTO bookings (name, phone, email, event_type, hall, event_date, booked_date, guests, message, status, record_type, enquiry_id, source, total_amount, internal_notes, confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'booking', ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $insert->execute([$enquiry['name'], $enquiry['phone'], $enquiry['email'], $enquiry['event_type'], $enquiry['hall'], $enquiry['event_date'], $enquiry['event_date'], $enquiry['guests'], $enquiry['message'], $enquiryId, $enquiry['source'], $enquiry['total_amount'], $notes ?: $enquiry['internal_notes']]);
        $bookingId = (int) $db->lastInsertId();
        $update = $db->prepare("UPDATE bookings SET status = 'converted', booked_date = NULL, follow_up_completed_at = COALESCE(follow_up_completed_at, CURRENT_TIMESTAMP) WHERE id = ?");
        $update->execute([$enquiryId]);
        $complete = $db->prepare('UPDATE enquiry_followups SET completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP) WHERE enquiry_id = ? AND completed_at IS NULL');
        $complete->execute([$enquiryId]);
        admin_activity($db, $enquiryId, 'converted_to_booking', 'Converted to booking VCM-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT));
        admin_activity($db, $bookingId, 'booking_created', 'Created from enquiry VCM-' . str_pad((string) $enquiryId, 6, '0', STR_PAD_LEFT));
        $db->commit();
        return $bookingId;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function admin_valid_datetime(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Asia/Kolkata'));
    return $date && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : null;
}

function admin_filter_input(array $input): array
{
    $tabs = ['all', 'enquiries', 'bookings', 'cancelled', 'blocked'];
    $sources = ['website', 'whatsapp', 'instagram', 'phone', 'walk-in', 'other'];
    $tab = strtolower(trim((string) ($input['tab'] ?? 'all')));
    $source = strtolower(trim((string) ($input['source'] ?? '')));
    $hall = strtolower(trim((string) ($input['hall'] ?? '')));
    $date = trim((string) ($input['date'] ?? ''));
    return [
        'tab' => in_array($tab, $tabs, true) ? $tab : 'all',
        'q' => admin_text_cut(trim((string) ($input['q'] ?? '')), 120),
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
        'source' => in_array($source, $sources, true) ? $source : '',
        'hall' => in_array($hall, ADMIN_HALLS, true) ? $hall : '',
    ];
}

function admin_filter_sql(array $filters, array &$params): string
{
    $where = [];
    $typeForTab = ['enquiries' => 'enquiry', 'bookings' => 'booking', 'blocked' => 'block'];
    if (isset($typeForTab[$filters['tab']])) { $where[] = 'b.record_type = ?'; $params[] = $typeForTab[$filters['tab']]; }
    if ($filters['tab'] === 'cancelled') { $where[] = "b.status = 'cancelled'"; }
    if ($filters['q'] !== '') {
        $search = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $reference = preg_match('/^(?:VCM-?)?0*(\d+)$/i', $filters['q'], $match) ? (int) $match[1] : null;
        $where[] = "(b.name LIKE ? ESCAPE '!' OR b.email LIKE ? ESCAPE '!' OR b.phone LIKE ? ESCAPE '!' OR b.event_type LIKE ? ESCAPE '!'" . ($reference ? ' OR b.id = ?' : '') . ')';
        array_push($params, $search, $search, $search, $search);
        if ($reference) $params[] = $reference;
    }
    if ($filters['date'] !== '') {
        $where[] = 'b.event_date = ?';
        $params[] = $filters['date'];
    }
    if ($filters['source'] !== '') {
        $where[] = "CASE WHEN b.source = 'admin' THEN 'other' ELSE b.source END = ?";
        $params[] = $filters['source'];
    }
    if ($filters['hall'] !== '') { $where[] = 'b.hall = ?'; $params[] = $filters['hall']; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function admin_fetch_rows(PDO $db, array $filters, int $limit = 500): array
{
    $params = [];
    $where = admin_filter_sql($filters, $params);
    $sql = "SELECT b.*, COALESCE(p.paid_amount, 0) AS paid_amount, COALESCE(p.payment_count, 0) AS payment_count
            FROM bookings b
            LEFT JOIN (SELECT booking_id, SUM(amount) paid_amount, COUNT(*) payment_count FROM booking_payments GROUP BY booking_id) p ON p.booking_id = b.id
            {$where}
            ORDER BY b.event_date ASC, b.created_at DESC, b.id DESC
            LIMIT " . max(1, min($limit, 2000));
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query->fetchAll();
}

function admin_metrics(PDO $db): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
    $week = $today->modify('monday this week')->format('Y-m-d 00:00:00');
    $monthStart = $today->modify('first day of this month')->format('Y-m-d 00:00:00');
    $monthEnd = $today->modify('first day of next month')->format('Y-m-d 00:00:00');
    $sql = "SELECT
        SUM(CASE WHEN record_type = 'enquiry' THEN 1 ELSE 0 END) total_enquiries,
        SUM(CASE WHEN record_type = 'booking' AND status = 'confirmed' THEN 1 ELSE 0 END) total_bookings,
        SUM(CASE WHEN record_type = 'enquiry' AND created_at >= ? THEN 1 ELSE 0 END) new_enquiries_week,
        SUM(CASE WHEN record_type = 'booking' AND status = 'confirmed' AND event_date >= ? THEN 1 ELSE 0 END) upcoming_bookings,
        SUM(CASE WHEN record_type = 'enquiry' AND status NOT IN ('converted','cancelled') AND follow_up_at IS NOT NULL AND follow_up_completed_at IS NULL THEN 1 ELSE 0 END) pending_followups,
        SUM(CASE WHEN record_type = 'block' AND status = 'blocked' THEN 1 ELSE 0 END) blocked_dates,
        SUM(CASE WHEN record_type = 'booking' AND status = 'confirmed' AND confirmed_at >= ? AND confirmed_at < ? THEN 1 ELSE 0 END) month_bookings
        FROM bookings";
    $query = $db->prepare($sql);
    $query->execute([$week, $today->format('Y-m-d'), $monthStart, $monthEnd]);
    $metrics = $query->fetch() ?: [];
    $payments = $db->prepare('SELECT COALESCE(SUM(amount), 0) FROM booking_payments WHERE received_at >= ? AND received_at < ?');
    $payments->execute([$monthStart, $monthEnd]);
    $metrics['month_advance'] = $payments->fetchColumn();
    return array_map(static fn($value) => $value ?? 0, $metrics);
}

function admin_calendar(PDO $db, DateTimeImmutable $month, string $hall = ''): array
{
    $start = $month->modify('first day of this month')->format('Y-m-d');
    $end = $month->modify('first day of next month')->format('Y-m-d');
    $sql = "SELECT event_date, status, COUNT(*) count FROM bookings WHERE event_date >= ? AND event_date < ?";
    $params = [$start, $end];
    if (in_array($hall, ADMIN_HALLS, true)) { $sql .= ' AND hall = ?'; $params[] = $hall; }
    $query = $db->prepare($sql . ' GROUP BY event_date, status');
    $query->execute($params);
    $days = [];
    foreach ($query->fetchAll() as $row) {
        $days[$row['event_date']][$row['status']] = (int) $row['count'];
    }
    return $days;
}

function admin_related(PDO $db, array $rows): array
{
    if (!$rows) {
        return ['payments' => [], 'activity' => []];
    }
    $ids = array_map(static fn($row) => (int) $row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $result = ['payments' => [], 'activity' => []];
    foreach (['payments' => "SELECT * FROM booking_payments WHERE booking_id IN ({$placeholders}) ORDER BY received_at DESC, id DESC", 'activity' => "SELECT * FROM booking_activity WHERE booking_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC"] as $key => $sql) {
        $query = $db->prepare($sql);
        $query->execute($ids);
        foreach ($query->fetchAll() as $item) {
            $result[$key][(string) $item['booking_id']][] = $item;
        }
    }
    return $result;
}

function admin_excel_export(array $rows): never
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="venue-enquiries-bookings-' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF<table><thead><tr><th>Reference</th><th>Event Date</th><th>Hall</th><th>Guest</th><th>Phone</th><th>Email</th><th>Event</th><th>Guests</th><th>Source</th><th>Total</th><th>Received</th><th>Balance</th><th>Follow-up</th><th>Status</th></tr></thead><tbody>";
    foreach ($rows as $row) {
        $values = ['VCM-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT), $row['event_date'], ucfirst((string) ($row['hall'] ?? 'big')) . ' Hall', $row['name'], $row['phone'], $row['email'], $row['event_type'], $row['guests'], $row['source'], $row['total_amount'], $row['paid_amount'], max(0, (float) $row['total_amount'] - (float) $row['paid_amount']), $row['follow_up_at'], $row['status']];
        echo '<tr>';
        foreach ($values as $value) {
            $cell = (string) $value;
            if (preg_match('/^[=+\-@]/u', $cell)) {
                $cell = "'" . $cell;
            }
            echo '<td>' . htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}

function admin_pdf_escape(string $text): string
{
    $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $plain = preg_replace('/[\x00-\x1F\x7F]/', ' ', $plain) ?? $plain;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $plain);
}

function admin_pdf_export(array $rows): never
{
    $lines = ['VENUE ENQUIRIES & BOOKINGS', 'Generated: ' . date('d M Y H:i'), str_repeat('-', 112)];
    foreach ($rows as $row) {
        $ref = 'VCM-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT);
        $guest = admin_text_cut((string) $row['name'], 22);
        $event = admin_text_cut((string) $row['event_type'], 18);
        $payment = number_format((float) $row['paid_amount'], 2) . '/' . number_format((float) $row['total_amount'], 2);
        $lines[] = sprintf('%-10s %-10s %-7s %-20s %-16s %-9s %-15s %-10s', $ref, $row['event_date'], ucfirst((string) ($row['hall'] ?? 'big')), $guest, $event, ucfirst((string) $row['source']), $payment, ucfirst((string) $row['status']));
    }
    if (count($lines) === 3) {
        $lines[] = 'No records matched the active filters.';
    }
    admin_pdf_download($lines, 'venue-enquiries-bookings-' . date('Y-m-d') . '.pdf');
}

function admin_pdf_download(array $lines, string $filename): never
{
    $chunks = array_chunk($lines, 45);
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
    $pageIds = [];
    $nextId = 4;
    foreach ($chunks as $pageLines) {
        $pageId = $nextId++;
        $contentId = $nextId++;
        $pageIds[] = $pageId;
        $stream = "BT /F1 8 Tf 36 806 Td 11 TL\n";
        foreach ($pageLines as $index => $line) {
            $stream .= ($index ? 'T* ' : '') . '(' . admin_pdf_escape($line) . ") Tj\n";
        }
        $stream .= 'ET';
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
        $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n{$stream}\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Count ' . count($pageIds) . ' /Kids [' . implode(' ', array_map(static fn($id) => "{$id} 0 R", $pageIds)) . '] >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= count($objects); $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
