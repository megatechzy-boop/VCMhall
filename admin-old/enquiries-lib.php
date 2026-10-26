<?php
declare(strict_types=1);

const ENQUIRY_STATUSES = ['pending', 'in_discussion', 'quote_sent', 'follow_up', 'converted', 'cancelled'];
const ENQUIRY_SOURCES = ['website', 'instagram', 'whatsapp', 'phone', 'walk-in', 'other'];

function enquiry_schema_ready(PDO $db): bool
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $columns = array_column($db->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');
        $tables = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('enquiry_notes','enquiry_followups')")->fetchColumn();
        return in_array('record_type', $columns, true) && in_array('enquiry_id', $columns, true) && in_array('hall', $columns, true) && (int) $tables === 2;
    }
    $columns = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME IN ('record_type','enquiry_id','hall')")->fetchColumn();
    $tables = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('enquiry_notes','enquiry_followups')")->fetchColumn();
    return (int) $columns === 3 && (int) $tables === 2;
}

function enquiry_filters(array $input): array
{
    $date = static fn(string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $source = strtolower(trim((string) ($input['source'] ?? '')));
    $hall = strtolower(trim((string) ($input['hall'] ?? '')));
    return [
        'q' => admin_text_cut(trim((string) ($input['q'] ?? '')), 120),
        'from' => $date(trim((string) ($input['from'] ?? ''))),
        'to' => $date(trim((string) ($input['to'] ?? ''))),
        'status' => in_array($status, ENQUIRY_STATUSES, true) ? $status : '',
        'event_type' => admin_text_cut(trim((string) ($input['event_type'] ?? '')), 40),
        'source' => in_array($source, ENQUIRY_SOURCES, true) ? $source : '',
        'hall' => in_array($hall, ADMIN_HALLS, true) ? $hall : '',
    ];
}

function enquiry_where(array $filters, array &$params): string
{
    $where = ["b.record_type = 'enquiry'"];
    if ($filters['q'] !== '') {
        $search = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $reference = preg_match('/^(?:ENQ-?)?0*(\d+)$/i', $filters['q'], $match) ? (int) $match[1] : null;
        $where[] = "(b.name LIKE ? ESCAPE '!' OR b.phone LIKE ? ESCAPE '!' OR b.email LIKE ? ESCAPE '!' OR b.event_type LIKE ? ESCAPE '!'" . ($reference ? ' OR b.id = ?' : '') . ')';
        array_push($params, $search, $search, $search, $search);
        if ($reference) $params[] = $reference;
    }
    foreach (['from' => '>=', 'to' => '<='] as $key => $operator) if ($filters[$key] !== '') { $where[] = "b.event_date {$operator} ?"; $params[] = $filters[$key]; }
    if ($filters['status'] !== '') { $where[] = 'b.status = ?'; $params[] = $filters['status']; }
    if ($filters['event_type'] !== '') { $where[] = 'b.event_type = ?'; $params[] = $filters['event_type']; }
    if ($filters['source'] !== '') { $where[] = 'b.source = ?'; $params[] = $filters['source']; }
    if ($filters['hall'] !== '') { $where[] = 'b.hall = ?'; $params[] = $filters['hall']; }
    return ' WHERE ' . implode(' AND ', $where);
}

function enquiry_rows(PDO $db, array $filters, int $page, int $perPage, bool $export = false): array
{
    $params = []; $where = enquiry_where($filters, $params);
    $sql = "SELECT b.*,
        (SELECT scheduled_at FROM enquiry_followups f WHERE f.enquiry_id=b.id ORDER BY f.id DESC LIMIT 1) latest_followup_at,
        (SELECT completed_at FROM enquiry_followups f WHERE f.enquiry_id=b.id ORDER BY f.id DESC LIMIT 1) latest_followup_completed_at,
        (SELECT id FROM bookings linked WHERE linked.enquiry_id=b.id AND linked.record_type='booking' ORDER BY linked.id DESC LIMIT 1) linked_booking_id
        FROM bookings b {$where} ORDER BY b.created_at DESC, b.id DESC";
    if ($export) $sql .= ' LIMIT 5000';
    else $sql .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    $query = $db->prepare($sql); $query->execute($params); return $query->fetchAll();
}

function enquiry_total(PDO $db, array $filters): int
{
    $params = []; $query = $db->prepare('SELECT COUNT(*) FROM bookings b' . enquiry_where($filters, $params)); $query->execute($params); return (int) $query->fetchColumn();
}

function enquiry_summary(PDO $db): array
{
    $sql = "SELECT COUNT(*) total,
        SUM(CASE WHEN status='converted' THEN 1 ELSE 0 END) converted,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) cancelled,
        COALESCE(SUM(guests),0) total_guests,
        SUM(CASE WHEN status NOT IN ('converted','cancelled') AND EXISTS (SELECT 1 FROM enquiry_followups f WHERE f.enquiry_id=b.id AND f.completed_at IS NULL) THEN 1 ELSE 0 END) pending_followup
        FROM bookings b WHERE record_type='enquiry'";
    return array_map(static fn($value) => $value ?? 0, $db->query($sql)->fetch() ?: []);
}

function enquiry_tab_counts(PDO $db): array
{
    $rows = $db->query("SELECT status, COUNT(*) count FROM bookings WHERE record_type='enquiry' GROUP BY status")->fetchAll();
    $counts = ['all' => 0]; foreach (ENQUIRY_STATUSES as $status) $counts[$status] = 0;
    foreach ($rows as $row) { $counts[$row['status']] = (int) $row['count']; $counts['all'] += (int) $row['count']; }
    return $counts;
}

function enquiry_related(PDO $db, array $rows): array
{
    $result = ['notes' => [], 'followups' => [], 'activity' => [], 'bookings' => []];
    if (!$rows) return $result;
    $ids = array_map(static fn($row) => (int) $row['id'], $rows); $marks = implode(',', array_fill(0, count($ids), '?'));
    foreach (['notes' => "SELECT * FROM enquiry_notes WHERE enquiry_id IN ({$marks}) ORDER BY created_at DESC,id DESC", 'followups' => "SELECT * FROM enquiry_followups WHERE enquiry_id IN ({$marks}) ORDER BY scheduled_at DESC,id DESC", 'activity' => "SELECT * FROM booking_activity WHERE booking_id IN ({$marks}) ORDER BY created_at DESC,id DESC"] as $key => $sql) {
        $query = $db->prepare($sql); $query->execute($ids);
        foreach ($query->fetchAll() as $item) $result[$key][(string) ($item['enquiry_id'] ?? $item['booking_id'])][] = $item;
    }
    $query = $db->prepare("SELECT b.*,COALESCE(SUM(p.amount),0) paid_amount,COUNT(p.id) payment_count FROM bookings b LEFT JOIN booking_payments p ON p.booking_id=b.id WHERE b.enquiry_id IN ({$marks}) AND b.record_type='booking' GROUP BY b.id ORDER BY b.id DESC");
    $query->execute($ids); foreach ($query->fetchAll() as $booking) $result['bookings'][(string) $booking['enquiry_id']] = $booking;
    return $result;
}

function enquiry_excel(array $rows): never
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="venue-enquiries-' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF<table><thead><tr><th>Reference</th><th>Enquiry Date</th><th>Event Date</th><th>Hall</th><th>Name</th><th>Phone</th><th>Email</th><th>Event Type</th><th>Guests</th><th>Source</th><th>Status</th><th>Follow-up</th></tr></thead><tbody>";
    foreach ($rows as $row) { $values = ['ENQ-' . str_pad((string) $row['id'],6,'0',STR_PAD_LEFT),$row['created_at'],$row['event_date'],ucfirst((string) ($row['hall'] ?? 'big')).' Hall',$row['name'],$row['phone'],$row['email'],$row['event_type'],$row['guests'],$row['source'],$row['status'],$row['latest_followup_at']]; echo '<tr>'; foreach ($values as $value) { $cell=(string)$value; if(preg_match('/^[=+\-@]/u',$cell))$cell="'".$cell; echo '<td>'.htmlspecialchars($cell,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</td>'; } echo '</tr>'; }
    echo '</tbody></table>'; exit;
}

function enquiry_pdf(array $rows): never
{
    $lines=['VENUE ENQUIRIES','Generated: '.date('d M Y H:i'),str_repeat('-',112)];
    foreach($rows as $row){$ref='ENQ-'.str_pad((string)$row['id'],6,'0',STR_PAD_LEFT);$lines[]=sprintf('%-10s %-10s %-7s %-20s %-16s %-8s %-12s %-12s',$ref,$row['event_date'],ucfirst((string) ($row['hall'] ?? 'big')),admin_text_cut($row['name'],20),admin_text_cut($row['event_type'],16),(string)($row['guests']??'-'),ucfirst($row['source']),ucwords(str_replace('_',' ',$row['status'])));}
    if(count($lines)===3)$lines[]='No enquiries matched the active filters.';
    admin_pdf_download($lines,'venue-enquiries-'.date('Y-m-d').'.pdf');
}
