<?php
declare(strict_types=1);
require dirname(__DIR__) . '/booking-store.php';
require __DIR__ . '/dashboard-lib.php';
require __DIR__ . '/settings-lib.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self' https://wa.me; base-uri 'self'; frame-ancestors 'none'");
session_name('vcmhall_admin');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(mixed $value): string { return '₹' . number_format((float) $value, 0); }
function admin_finish(string $message): never { $_SESSION['admin_notice'] = $message; header('Location: index.php'); exit; }
function admin_id(): int { return (int) (filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0); }

try {
    $config = booking_config();
    $db = booking_db();
    $passwordHash = (string) ($config['admin_password_hash'] ?? '');
    if ($passwordHash === '' || $passwordHash === 'PASTE_PASSWORD_HASH_HERE') throw new RuntimeException('Admin password is not configured.');
} catch (Throwable $error) {
    error_log('Booking admin setup: ' . $error->getMessage());
    http_response_code(503);
    exit('The admin panel is still being set up. Please contact the person who manages the website.');
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$loggedIn = admin_session_authenticated($db, $passwordHash);
$usersTableReady = false;
try { $db->query('SELECT 1 FROM admin_users LIMIT 1'); $usersTableReady = true; } catch (Throwable) { }
$notice = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);
$schemaReady = false;
$paymentMethodLabels = ['cash' => 'Cash', 'bank-transfer' => 'Bank transfer', 'upi' => 'UPI', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];
$paymentMethods = admin_payment_methods($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Your session expired. Please sign in again, then repeat your last action.'); }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login' && !$loggedIn) {
        $lockedUntil = (int) ($_SESSION['locked_until'] ?? 0);
        if ($lockedUntil > time()) $notice = 'Too many attempts. Try again later.';
        else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $user = null;
            if ($usersTableReady && $username !== '') {
                $userQuery = $db->prepare('SELECT id, username, role, password_hash FROM admin_users WHERE active = 1 AND (username = ? OR email = ?) LIMIT 1');
                $userQuery->execute([$username, $username]);
                $user = $userQuery->fetch() ?: null;
            }
            $validUser = $user && password_verify($password, (string) $user['password_hash']);
            $validLegacy = !$usersTableReady && password_verify($password, $passwordHash);
            if ($validUser || $validLegacy) {
                session_regenerate_id(true); $_SESSION['password_hash'] = $passwordHash; $_SESSION['admin_user_id'] = $user ? (int) $user['id'] : 0; $_SESSION['admin_role'] = $user['role'] ?? 'admin'; $_SESSION['admin_username'] = $user['username'] ?? 'admin'; $_SESSION['attempts'] = 0; $loggedIn = true;
            } else {
                $_SESSION['attempts'] = (int) ($_SESSION['attempts'] ?? 0) + 1;
                if ($_SESSION['attempts'] >= 5) { $_SESSION['locked_until'] = time() + 900; $_SESSION['attempts'] = 0; }
                $notice = 'Incorrect username or password.';
            }
        }
    } elseif ($loggedIn && $action === 'logout') {
        $_SESSION = []; session_destroy(); header('Location: index.php'); exit;
    } elseif ($loggedIn) {
        if (!admin_schema_ready($db)) admin_finish('Admin setup is incomplete. Please contact the person who manages the website before making changes.');
        $sources = ['website', 'whatsapp', 'instagram', 'phone', 'walk-in', 'other'];
        try {
            if ($action === 'block') {
                $date = trim((string) ($_POST['date'] ?? ''));
                $hall = strtolower(trim((string) ($_POST['hall'] ?? '')));
                if (!booking_valid_date($date) || !in_array($hall, ADMIN_HALLS, true)) admin_finish('Choose a valid hall and current or future date.');
                $insert = $db->prepare("INSERT INTO bookings (name, phone, event_type, hall, event_date, booked_date, message, status, record_type, source) VALUES ('Admin block', '', 'Manual block', ?, ?, ?, '', 'blocked', 'block', 'other')");
                $insert->execute([$hall, $date, $date]); $id = (int) $db->lastInsertId();
                admin_activity($db, $id, 'date_blocked', ucfirst($hall) . ' Hall marked unavailable by admin'); admin_finish(ucfirst($hall) . ' Hall marked unavailable on ' . date('d M Y', strtotime($date)) . '.');
            }
            if (in_array($action, ['confirm', 'cancel'], true)) {
                $id = admin_id(); if (!$id) admin_finish('Please select an item and try again.');
                if ($action === 'confirm') { $bookingId = admin_convert_enquiry($db, $id); admin_finish('Booking confirmed. Reference: VCM-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT) . '.'); }
                $kindQuery = $db->prepare('SELECT record_type FROM bookings WHERE id = ? AND status NOT IN (\'cancelled\', \'converted\')');
                $kindQuery->execute([$id]); $recordType = $kindQuery->fetchColumn();
                if ($recordType === false) admin_finish('This item was already updated. Refresh the page and try again.');
                $query = $db->prepare("UPDATE bookings SET status = 'cancelled', booked_date = NULL WHERE id = ? AND status NOT IN ('cancelled','converted')");
                $query->execute([$id]); if (!$query->rowCount()) admin_finish('Record could not be updated.');
                admin_activity($db, $id, 'cancelled', $recordType === 'block' ? 'Date made available by admin' : ($recordType === 'enquiry' ? 'Enquiry closed' : 'Booking cancelled'));
                admin_finish($recordType === 'block' ? 'Date is available again.' : ($recordType === 'enquiry' ? 'Enquiry closed.' : 'Booking cancelled. The date is available again.'));
            }
            if ($action === 'add_booking') {
                $name = trim((string) ($_POST['name'] ?? '')); $phone = trim((string) ($_POST['phone'] ?? '')); $email = trim((string) ($_POST['email'] ?? ''));
                $event = trim((string) ($_POST['event_type'] ?? '')); $date = trim((string) ($_POST['event_date'] ?? '')); $guests = trim((string) ($_POST['guests'] ?? ''));
                $hall = strtolower(trim((string) ($_POST['hall'] ?? '')));
                $source = strtolower(trim((string) ($_POST['source'] ?? 'other'))); $total = trim((string) ($_POST['total_amount'] ?? '0'));
                if ($name === '' || admin_text_length($name) > 120 || !preg_match('/^[0-9+() -]{10,18}$/', $phone)
                    || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || admin_text_length($email) > 254)) || $event === '' || admin_text_length($event) > 40 || !booking_valid_date($date)
                    || ($guests !== '' && (!ctype_digit($guests) || (int) $guests < 1 || (int) $guests > 10000)) || !in_array($source, $sources, true)
                    || !in_array($hall, ADMIN_HALLS, true) || !is_numeric($total) || (float) $total < 0 || (float) $total > 99999999) admin_finish('Check the booking details and try again.');
                $insert = $db->prepare("INSERT INTO bookings (name, phone, email, event_type, hall, event_date, booked_date, guests, message, status, record_type, source, total_amount, confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', 'confirmed', 'booking', ?, ?, CURRENT_TIMESTAMP)");
                $insert->execute([$name, $phone, $email, $event, $hall, $date, $date, $guests === '' ? null : (int) $guests, $source, (float) $total]);
                $id = (int) $db->lastInsertId(); admin_activity($db, $id, 'booking_created', 'Direct booking added by admin'); admin_finish('Booking added and the date is reserved.');
            }
            if ($action === 'save_details') {
                $id = admin_id(); $source = strtolower(trim((string) ($_POST['source'] ?? 'other'))); $total = trim((string) ($_POST['total_amount'] ?? '0'));
                if (!$id || !in_array($source, $sources, true) || !is_numeric($total) || (float) $total < 0 || (float) $total > 99999999) admin_finish('Invalid booking details.');
                $query = $db->prepare("UPDATE bookings SET source = ?, total_amount = ? WHERE id = ? AND status <> 'blocked'"); $query->execute([$source, (float) $total, $id]);
                admin_activity($db, $id, 'details_updated', 'Source or total booking amount updated'); admin_finish('Booking details saved.');
            }
            if ($action === 'save_notes') {
                $id = admin_id(); $notes = trim((string) ($_POST['internal_notes'] ?? ''));
                if (!$id || admin_text_length($notes) > 5000) admin_finish('Notes must be 5,000 characters or fewer.');
                $query = $db->prepare('UPDATE bookings SET internal_notes = ? WHERE id = ?'); $query->execute([$notes, $id]);
                admin_activity($db, $id, 'notes_updated', 'Internal notes updated'); admin_finish('Internal notes saved.');
            }
            if ($action === 'schedule_followup') {
                $id = admin_id(); $value = admin_valid_datetime(trim((string) ($_POST['follow_up_at'] ?? '')));
                if (!$id || !$value) admin_finish('Choose a valid follow-up date and time.');
                $query = $db->prepare("UPDATE bookings SET follow_up_at = ?, follow_up_completed_at = NULL WHERE id = ? AND status IN ('pending','in_discussion','quote_sent','follow_up','confirmed')"); $query->execute([$value, $id]);
                if (!$query->rowCount()) admin_finish('Active enquiry or booking not found.');
                admin_activity($db, $id, 'followup_scheduled', 'Reminder set for ' . $value); admin_finish('Reminder set.');
            }
            if ($action === 'complete_followup') {
                $id = admin_id(); $query = $db->prepare("UPDATE bookings SET follow_up_completed_at = CURRENT_TIMESTAMP WHERE id = ? AND follow_up_at IS NOT NULL AND status IN ('pending','in_discussion','quote_sent','follow_up','confirmed')"); $query->execute([$id]);
                if ($query->rowCount()) admin_activity($db, $id, 'followup_completed', 'Follow-up marked complete');
                admin_finish($query->rowCount() ? 'Reminder completed.' : 'No active follow-up found.');
            }
            if ($action === 'add_payment') {
                $id = admin_id(); $amount = trim((string) ($_POST['amount'] ?? '')); $method = strtolower(trim((string) ($_POST['payment_method'] ?? 'other')));
                $reference = trim((string) ($_POST['payment_reference'] ?? '')); $paymentNotes = trim((string) ($_POST['payment_notes'] ?? ''));
                $paymentLimit = settings_schema_ready($db) ? max(1, min(5, (int) setting($db, 'max_payment_steps', '5'))) : 5;
                try { admin_record_payment($db, $id, (float) $amount, $method, $reference, $paymentNotes, $paymentLimit, $paymentMethods); }
                catch (InvalidArgumentException | RuntimeException $error) { admin_finish($error->getMessage()); }
                admin_finish('Payment saved.');
            }
            if ($action === 'send_email') {
                $id = admin_id(); $query = $db->prepare('SELECT * FROM bookings WHERE id = ?'); $query->execute([$id]); $row = $query->fetch();
                $from = (string) (($config['email_from'] ?? '') ?: 'bookings@venutaihall.com');
                if (!$row || !filter_var($row['email'], FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) admin_finish('This guest does not have a valid email address.');
                $subject = 'Your venue enquiry VCM-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                $body = "Hello {$row['name']},\n\nWe are following up about your {$row['event_type']} on {$row['event_date']}. Please reply to this email or call us if you would like to discuss your booking.\n\nLate Venutai Chavan Multipurpose Hall";
                $sent = mail((string) $row['email'], $subject, $body, ['From' => $from, 'Reply-To' => $from, 'Content-Type' => 'text/plain; charset=UTF-8']);
                admin_activity($db, $id, $sent ? 'email_sent' : 'email_failed', $sent ? 'Follow-up email accepted by mail server' : 'Follow-up email failed'); admin_finish($sent ? 'Email sent to the mail server.' : 'Email could not be sent.');
            }
        } catch (PDOException $error) {
            if ($db->inTransaction()) $db->rollBack(); error_log('Booking admin database action: ' . $error->getMessage());
            admin_finish($error->getCode() === '23000' ? 'That hall is already booked or blocked on this date.' : 'The change could not be saved.');
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack(); error_log('Booking admin action: ' . $error->getMessage()); admin_finish('The change could not be completed.');
        }
    }
}

$filters = admin_filter_input($_GET); $rows = []; $metrics = []; $calendarDays = []; $related = ['payments' => [], 'activity' => []]; $totalRecords = 0; $page = max(1, min(100000, (int) ($_GET['page'] ?? 1))); $perPage = 50; $totalPages = 1; $isExport = false;
$monthValue = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$month = DateTimeImmutable::createFromFormat('!Y-m-d', $monthValue . '-01', new DateTimeZone('Asia/Kolkata')) ?: new DateTimeImmutable('first day of this month');
if ($loggedIn) {
    $schemaReady = admin_schema_ready($db);
    if ($schemaReady) {
        $isExport = in_array((string) ($_GET['export'] ?? ''), ['excel', 'pdf'], true);
        if (settings_schema_ready($db)) $perPage = max(10, min(100, (int) setting($db, 'items_per_page', '50')));
        if ($isExport) $rows = admin_fetch_rows($db, $filters, 2000);
        else {
            $totalRecords = admin_count_rows($db, $filters);
            $totalPages = max(1, (int) ceil($totalRecords / $perPage));
            $page = min($page, $totalPages);
            $rows = admin_fetch_rows($db, $filters, $perPage, ($page - 1) * $perPage);
        }
        if (($_GET['export'] ?? '') === 'excel') admin_excel_export($rows);
        if (($_GET['export'] ?? '') === 'pdf') admin_pdf_export($rows);
        $metrics = admin_metrics($db); $calendarDays = admin_calendar($db, $month, $filters['hall']); $related = admin_related($db, $rows);
    }
}
$statusLabels = ['pending' => 'New enquiry', 'in_discussion' => 'In discussion', 'quote_sent' => 'Quote sent', 'follow_up' => 'Reminder set', 'converted' => 'Booking created', 'confirmed' => 'Booked', 'blocked' => 'Date unavailable', 'cancelled' => 'Cancelled'];
$sourceLabels = ['website' => 'Website', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'phone' => 'Phone', 'walk-in' => 'Walk-in', 'other' => 'Other', 'admin' => 'Other'];
$queryForExport = http_build_query(array_filter($filters, static fn($value) => $value !== '' && $value !== 'all'));
$pageQuery = array_filter($filters, static fn($value) => $value !== '' && $value !== 'all');
$dashboardData = ['rows' => $rows, 'payments' => $related['payments'], 'activity' => $related['activity'], 'today' => booking_today(), 'paymentLimit' => $loggedIn && settings_schema_ready($db) ? max(1, min(5, (int) setting($db, 'max_payment_steps', '5'))) : 5];
?><!doctype html>
<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex, nofollow" /><title>Venue Overview | Venue Admin</title><link rel="stylesheet" href="admin.css" /></head>
<body class="admin-page">
<?php if (!$loggedIn): ?>
<main class="login-page"><section class="login-card"><div class="brand-mark">VC</div><p class="eyebrow">VENUE ADMIN</p><h1>Welcome back</h1><p>Sign in to manage venue enquiries and bookings.</p><?php if ($notice !== ''): ?><p class="notice" role="status"><?= h($notice) ?></p><?php endif; ?><form method="post" class="stack-form"><label>Username or email<input type="text" name="username" autocomplete="username" required /></label><label>Password<input type="password" name="password" autocomplete="current-password" required /></label><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><button class="primary-button" name="action" value="login">Sign in</button></form></section></main>
<?php else: ?>
<div class="dashboard-layout"><aside class="sidebar" id="sidebar"><div class="sidebar-brand"><div class="brand-mark">VC</div><div><strong>Venue Admin</strong><span>Management portal</span></div></div><nav aria-label="Admin navigation"><a class="active" href="index.php"><span>▦</span>Dashboard</a><a href="enquiries.php"><span>✉</span>Enquiries</a><a href="?tab=bookings#records"><span>▣</span>Bookings</a><a href="settings.php"><span>⚙</span>Settings</a></nav><div class="sidebar-footer"><span>Late Venutai Chavan</span><small>Multipurpose Hall</small></div></aside>
<main class="dashboard-main"><header class="topbar"><button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button><div class="page-title"><p class="eyebrow">VENUE OVERVIEW</p><h1>Venue Overview</h1><p>New enquiries, upcoming bookings and hall dates at a glance.</p></div><div class="header-actions"><button class="secondary-button" type="button" data-open-block>Mark Date Unavailable</button><button class="primary-button" type="button" data-open-booking>＋ Add Booking</button></div></header>
<?php if ($notice !== ''): ?><p class="notice" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if (!$schemaReady): ?><section class="migration-card"><h2>Admin setup needs attention</h2><p>The admin panel needs a one-time setup update before it can manage enquiries and bookings. Please contact the person who maintains the website. Existing records will be kept.</p></section>
<?php else: ?>
<section class="metric-grid" aria-label="Important venue numbers"><?php foreach ([['New enquiries this week',$metrics['new_enquiries_week'],'✦','amber'],['Upcoming bookings',$metrics['upcoming_bookings'],'▣','green'],['Follow-ups to do',$metrics['pending_followups'],'◷','maroon'],['Bookings confirmed this month',$metrics['month_bookings'],'↗','green']] as [$label,$value,$icon,$tone]): ?><article class="metric-card"><div><span><?= h($label) ?></span><strong><?= h($value) ?></strong></div><i class="metric-icon <?= h($tone) ?>"><?= h($icon) ?></i></article><?php endforeach; ?></section>
<section class="calendar-card" id="calendar"><div class="section-heading"><div><p class="eyebrow">DATES</p><h2>Hall availability</h2></div><div class="calendar-controls"><a href="?<?= h(http_build_query(array_merge($filters, ['month' => $month->modify('-1 month')->format('Y-m')]))) ?>" aria-label="Previous month">‹</a><a class="today-button" href="?<?= h(http_build_query(array_merge($filters, ['month' => date('Y-m')]))) ?>">Today</a><a href="?<?= h(http_build_query(array_merge($filters, ['month' => $month->modify('+1 month')->format('Y-m')]))) ?>" aria-label="Next month">›</a></div></div><div class="calendar-title"><strong><?= h($month->format('F Y')) ?></strong><div class="calendar-legend"><span class="available">Available</span><span class="enquiry">Enquiry received</span><span class="booked">Booked</span><span class="blocked">Date unavailable</span></div></div><p class="helper-text calendar-help">Select a date to see matching bookings and enquiries below.</p><div class="calendar-grid calendar-weekdays"><?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><span><?= $day ?></span><?php endforeach; ?></div><div class="calendar-grid calendar-days">
<?php $firstWeekday=(int)$month->format('N'); for($blank=1;$blank<$firstWeekday;$blank++): ?><span class="calendar-blank"></span><?php endfor; ?>
<?php $daysInMonth=(int)$month->format('t'); for($day=1;$day<=$daysInMonth;$day++): $date=$month->format('Y-m-').str_pad((string)$day,2,'0',STR_PAD_LEFT); $states=$calendarDays[$date]??[]; $hasEnquiry=(bool)array_intersect(['pending','in_discussion','quote_sent','follow_up'],array_keys($states)); $state=isset($states['blocked'])?'blocked':(isset($states['confirmed'])?'booked':($hasEnquiry?'enquiry':'available')); ?><button type="button" class="calendar-day <?= h($state) ?><?= $date===booking_today()?' is-today':'' ?>" data-calendar-date="<?= h($date) ?>"><b><?= $day ?></b><?php if($state!=='available'): ?><small><?= h(array_sum($states)) ?> <?= array_sum($states)===1?'record':'records' ?></small><?php endif; ?></button><?php endfor; ?></div></section>
<section class="records-card" id="records"><div class="section-heading"><div><p class="eyebrow">MANAGEMENT</p><h2>Enquiries &amp; Bookings</h2></div><span class="record-count"><?= h($totalRecords) ?> records</span></div><div class="record-tabs" role="navigation" aria-label="Record status"><?php foreach(['all'=>'All','enquiries'=>'Enquiries','bookings'=>'Bookings','cancelled'=>'Cancelled','blocked'=>'Unavailable dates'] as $tab=>$label): ?><a class="<?= $filters['tab']===$tab?'active':'' ?>" href="?<?= h(http_build_query(array_merge($filters,['tab'=>$tab,'page'=>1]))) ?>#records"><?= h($label) ?></a><?php endforeach; ?></div>
<form class="filters" method="get" action="index.php#records" id="filterForm"><input type="hidden" name="tab" value="<?= h($filters['tab']) ?>" /><label class="search-field"><span>⌕</span><input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Search guest, email, phone, event or reference" /></label><label><span class="sr-only">Event date</span><input type="date" name="date" value="<?= h($filters['date']) ?>" id="dateFilter" /></label><label><span class="sr-only">Hall</span><select name="hall"><option value="">Both halls</option><option value="big" <?= $filters['hall']==='big'?'selected':'' ?>>Big Hall</option><option value="small" <?= $filters['hall']==='small'?'selected':'' ?>>Small Hall</option></select></label><label><span class="sr-only">Contact method</span><select name="source"><option value="">All contact methods</option><?php foreach($sourceLabels as $value=>$label): if($value==='admin')continue; ?><option value="<?= h($value) ?>" <?= $filters['source']===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><button class="filter-button" type="submit">Show results</button><a class="clear-button" href="?tab=<?= h($filters['tab']) ?>#records">Clear filters</a><div class="export-actions"><a href="?<?= h($queryForExport?$queryForExport.'&':'') ?>export=excel">Export Excel</a><a href="?<?= h($queryForExport?$queryForExport.'&':'') ?>export=pdf">Export PDF</a></div></form>
<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Event Date</th><th>Hall</th><th>Guest</th><th>Event / Guests</th><th>Source</th><th>Payment</th><th>Reminder</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $row): $balance=max(0,(float)$row['total_amount']-(float)$row['paid_amount']); $paymentStatus=(float)$row['paid_amount']<=0?'Not Paid':($balance>0?'Partial':'Paid'); $overdue=$row['follow_up_at']&&!$row['follow_up_completed_at']&&$row['follow_up_at']<date('Y-m-d H:i:s'); $waPhone=preg_replace('/\D+/','',$row['phone']); if(strlen($waPhone)===10)$waPhone='91'.$waPhone; ?>
<tr data-record-id="<?= h($row['id']) ?>" tabindex="0"><td><strong>VCM-<?= str_pad(h($row['id']),6,'0',STR_PAD_LEFT) ?></strong><small><?= h(date('d M Y',strtotime($row['created_at']))) ?></small></td><td><strong><?= h(date('d M Y',strtotime($row['event_date']))) ?></strong><small><?= h(date('l',strtotime($row['event_date']))) ?></small></td><td><strong><?= h(ucfirst($row['hall'])) ?> Hall</strong></td><td><strong><?= h($row['name']) ?></strong><small><?= h($row['phone']) ?></small></td><td><?= h($row['event_type']) ?><small><?= $row['guests']?h($row['guests']).' guests':'Guest count not set' ?></small></td><td><span class="source-badge source-<?= h($row['source']==='admin'?'other':$row['source']) ?>"><?= h($sourceLabels[$row['source']]??'Other') ?></span></td><td><span class="payment-status <?= strtolower(str_replace(' ','-',$paymentStatus)) ?>"><?= h($paymentStatus) ?></span><small><?= money($row['paid_amount']) ?> / <?= money($row['total_amount']) ?></small></td><td><?php if($row['follow_up_at']): ?><strong class="<?= $overdue?'overdue':'' ?>"><?= h(date('d M, g:i A',strtotime($row['follow_up_at']))) ?></strong><small><?= $row['follow_up_completed_at']?'Reminder complete':($overdue?'Reminder overdue':'Reminder set') ?></small><?php else: ?><span class="muted">No reminder set</span><?php endif; ?></td><td><span class="status-badge status-<?= h($row['status']) ?>"><?= h($statusLabels[$row['status']]??$row['status']) ?></span></td><td><div class="row-actions"><?php if($waPhone!==''): ?><a class="whatsapp-action" href="https://wa.me/<?= h($waPhone) ?>?text=<?= h(rawurlencode('Hello '.$row['name'].', following up about your '.$row['event_type'].' in '.ucfirst($row['hall']).' Hall on '.date('d M Y',strtotime($row['event_date'])).'. - Late Venutai Chavan Multipurpose Hall')) ?>" target="_blank" rel="noopener" title="WhatsApp">WhatsApp</a><?php endif; ?><button class="view-button" type="button" data-view-id="<?= h($row['id']) ?>">Open details</button><details><summary>More actions</summary><div><?php if($row['status']==='pending'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" value="<?= h($row['id']) ?>" /><button name="action" value="confirm">Confirm as booking</button></form><?php endif; ?><?php if($row['status']!=='cancelled'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" value="<?= h($row['id']) ?>" /><button class="danger-link" name="action" value="cancel"><?= $row['status']==='blocked'?'Make date available':($row['record_type']==='enquiry'?'Close enquiry':'Cancel booking') ?></button></form><?php endif; ?></div></details></div></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="10" class="empty-state"><strong>No records found</strong><span>Try changing the active filters.</span></td></tr><?php endif; ?></tbody></table></div><?php if(!$isExport && $totalRecords > $perPage): ?><footer class="dashboard-pagination"><span>Showing <?= h(($page - 1) * $perPage + 1) ?>–<?= h(min($page * $perPage, $totalRecords)) ?> of <?= h($totalRecords) ?></span><nav aria-label="Booking pages"><?php if($page > 1): ?><a href="?<?= h(http_build_query(array_merge($pageQuery,['page'=>$page - 1]))) ?>#records">Previous</a><?php endif; ?><strong>Page <?= h($page) ?> of <?= h($totalPages) ?></strong><?php if($page < $totalPages): ?><a href="?<?= h(http_build_query(array_merge($pageQuery,['page'=>$page + 1]))) ?>#records">Next</a><?php endif; ?></nav></footer><?php endif; ?></section>
<section class="settings-card" id="settings"><div><p class="eyebrow">ACCOUNT</p><h2>Settings</h2><p>Signed in securely. Booking notifications use the email addresses configured on the server.</p></div><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><button class="danger-button" name="action" value="logout">Log out</button></form></section>
<?php endif; ?></main></div>
<?php if($schemaReady): ?>
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div><aside class="detail-drawer" id="detailDrawer" aria-hidden="true" aria-labelledby="drawerTitle"><header><div><p class="eyebrow" id="drawerReference"></p><h2 id="drawerTitle">Record details</h2></div><button class="icon-button" id="closeDrawer" type="button" aria-label="Close details">×</button></header><div class="drawer-tabs" role="tablist"><button class="active" type="button" data-drawer-tab="details">Details</button><button type="button" data-drawer-tab="notes">Notes</button><button type="button" data-drawer-tab="payments">Payments</button><button type="button" data-drawer-tab="activity">Activity</button></div><div class="drawer-content">
<section data-drawer-panel="details" class="active"><div id="drawerDetails" class="detail-list"></div><div id="drawerContactActions" class="contact-actions"></div><div id="drawerConversion"></div><hr /><h3>Booking amount</h3><form method="post" class="compact-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" data-record-input /><label>Source<select name="source" id="drawerSource"><?php foreach($sourceLabels as $value=>$label): if($value==='admin')continue; ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?></select></label><label>Agreed total amount (₹)<input type="number" name="total_amount" id="drawerTotal" min="0" max="99999999" step="0.01" /></label><button class="primary-button" name="action" value="save_details">Save changes</button></form><hr /><h3>Reminder</h3><div id="followupStatus"></div><form method="post" class="compact-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" data-record-input /><label>Reminder date and time<input type="datetime-local" name="follow_up_at" id="drawerFollowup" required /></label><button class="secondary-button" name="action" value="schedule_followup">Set reminder</button><button class="text-button" name="action" value="complete_followup">Mark complete</button></form></section>
<section data-drawer-panel="notes"><h3>Internal admin-only notes</h3><p class="helper-text">These notes are never shown to guests.</p><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" data-record-input /><textarea name="internal_notes" id="drawerNotes" rows="12" maxlength="5000" placeholder="Add call notes, preferences or internal reminders..."></textarea><button class="primary-button" name="action" value="save_notes">Save note</button></form></section>
<section data-drawer-panel="payments"><div class="payment-summary"><div><span>Agreed total (₹)</span><strong id="paymentTotal"></strong></div><div><span>Total received</span><strong id="paymentReceived"></strong></div><div><span>Still to pay</span><strong id="paymentBalance"></strong></div></div><div class="payment-steps" id="paymentSteps"></div><span class="payment-status" id="drawerPaymentStatus"></span><div id="paymentList" class="timeline"></div><form method="post" class="compact-form payment-form"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" data-record-input /><h3>Record a payment</h3><p class="helper-text">Enter money already received. The remaining balance updates automatically.</p><label>Amount received (₹)<input type="number" name="amount" min="0.01" max="99999999" step="0.01" required /></label><label>Payment method<select name="payment_method"><?php foreach ($paymentMethods as $method): ?><option value="<?= h($method) ?>"><?= h($paymentMethodLabels[$method]) ?></option><?php endforeach; ?></select></label><label>Receipt or transaction reference (optional)<input type="text" name="payment_reference" maxlength="100" /></label><label>Notes (optional)<input type="text" name="payment_notes" maxlength="500" /></label><button class="primary-button" name="action" value="add_payment">Record payment</button></form></section>
<section data-drawer-panel="activity"><h3>Activity</h3><div id="activityList" class="timeline"></div></section></div></aside>
<dialog id="bookingDialog"><form method="post" class="modal-form"><header><div><p class="eyebrow">NEW RECORD</p><h2>Add a confirmed booking</h2></div><button type="button" class="icon-button" data-close-dialog>×</button></header><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><p class="helper-text">Use this after the guest confirms. The selected hall and date will be marked as booked.</p><div class="form-grid"><label>Guest name<input name="name" maxlength="120" required /></label><label>Phone<input name="phone" maxlength="18" inputmode="tel" required /></label><label>Email (optional)<input type="email" name="email" maxlength="254" /></label><label>Event type<input name="event_type" maxlength="40" required /></label><label>Hall<select name="hall" required><option value="big">Big Hall — 1200 guests</option><option value="small">Small Hall — 350 guests</option></select></label><label>Event date<input type="date" name="event_date" min="<?= h(booking_today()) ?>" required /></label><label>How did the guest contact us?<select name="source" required><option value="" selected disabled>Choose a contact method</option><?php foreach($sourceLabels as $value=>$label): if($value==='admin')continue; ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?></select></label><label>Agreed total amount (₹)<input type="number" name="total_amount" min="0" max="99999999" step="0.01" value="0" /></label></div><footer><button type="button" class="secondary-button" data-close-dialog>Cancel</button><button class="primary-button" name="action" value="add_booking">Add Booking</button></footer></form></dialog>
<dialog id="blockDialog"><form method="post" class="modal-form"><header><div><p class="eyebrow">AVAILABILITY</p><h2>Mark a hall date unavailable</h2></div><button type="button" class="icon-button" data-close-dialog>×</button></header><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><p>Choose this if the hall is closed or was booked outside this panel.</p><label>Hall<select name="hall" required><option value="big">Big Hall</option><option value="small">Small Hall</option></select></label><label>Date<input type="date" name="date" min="<?= h(booking_today()) ?>" required /></label><footer><button type="button" class="secondary-button" data-close-dialog>Cancel</button><button class="danger-button" name="action" value="block">Mark unavailable</button></footer></form></dialog>
<script id="dashboardData" type="application/json"><?= json_encode($dashboardData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script><script src="admin.js" defer></script>
<?php endif; ?>
<?php endif; ?></body></html>
