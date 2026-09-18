<?php
declare(strict_types=1);
require dirname(__DIR__) . '/booking-store.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
session_name('vcmhall_admin');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $config = booking_config();
    $db = booking_db();
    $passwordHash = (string) ($config['admin_password_hash'] ?? '');
    if ($passwordHash === '' || $passwordHash === 'PASTE_PASSWORD_HASH_HERE') {
        throw new RuntimeException('Admin password is not configured.');
    }
} catch (Throwable $error) {
    error_log('Booking admin setup: ' . $error->getMessage());
    http_response_code(503);
    exit('Booking admin is not configured yet.');
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$loggedIn = ($_SESSION['password_hash'] ?? null) === $passwordHash;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid form token. Reload the page and try again.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login' && !$loggedIn) {
        $lockedUntil = (int) ($_SESSION['locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            $notice = 'Too many attempts. Try again later.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), $passwordHash)) {
            session_regenerate_id(true);
            $_SESSION['password_hash'] = $passwordHash;
            $_SESSION['attempts'] = 0;
            $loggedIn = true;
        } else {
            $_SESSION['attempts'] = (int) ($_SESSION['attempts'] ?? 0) + 1;
            if ($_SESSION['attempts'] >= 5) {
                $_SESSION['locked_until'] = time() + 900;
                $_SESSION['attempts'] = 0;
            }
            $notice = 'Incorrect password.';
        }
    } elseif ($loggedIn && $action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif ($loggedIn && $action === 'block') {
        $date = trim((string) ($_POST['date'] ?? ''));
        if (!booking_valid_date($date)) {
            $notice = 'Choose a valid future date.';
        } else {
            try {
                $insert = $db->prepare("INSERT INTO bookings (name, phone, event_type, event_date, booked_date, message, status, source) VALUES ('Admin block', '', 'Manual block', ?, ?, '', 'blocked', 'admin')");
                $insert->execute([$date, $date]);
                $notice = 'Date blocked.';
            } catch (PDOException $error) {
                error_log('Booking admin block error: ' . $error->getMessage());
                $notice = $error->getCode() === '23000' ? 'That date is already booked or blocked.' : 'Could not block the date. Try again.';
            }
        }
    } elseif ($loggedIn && in_array($action, ['confirm', 'cancel'], true)) {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            $notice = 'Invalid booking.';
        } else {
            try {
                $query = $db->prepare($action === 'confirm'
                    ? "UPDATE bookings SET status = 'confirmed', booked_date = event_date WHERE id = ? AND status = 'pending'"
                    : "UPDATE bookings SET status = 'cancelled', booked_date = NULL WHERE id = ? AND status IN ('pending', 'confirmed', 'blocked')");
                $query->execute([$id]);
                $notice = $query->rowCount() ? 'Booking updated.' : 'Booking could not be updated.';
            } catch (PDOException $error) {
                error_log('Booking admin update error: ' . $error->getMessage());
                $notice = $error->getCode() === '23000' ? 'This date is already booked or blocked.' : 'Could not update the booking. Try again.';
            }
        }
    }
}

$rows = $loggedIn ? $db->query('SELECT * FROM bookings ORDER BY created_at DESC, id DESC LIMIT 300')->fetchAll() : [];
$emailLabels = ['not_configured' => 'Not set up', 'accepted_by_mail_server' => 'Sent to mail server', 'failed' => 'Failed'];
$statusLabels = ['pending' => 'Enquiry', 'confirmed' => 'Booked', 'blocked' => 'Blocked', 'cancelled' => 'Cancelled'];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Booking Admin | Late Venutai Chavan Multipurpose Hall</title>
  <link rel="stylesheet" href="../styles.css" />
</head>
<body class="admin-page">
  <main class="admin-shell">
    <header class="admin-header"><div><p>VENUE ADMIN</p><h1>Booking Management</h1></div><?php if ($loggedIn): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><button name="action" value="logout">Log out</button></form><?php endif; ?></header>
    <?php if ($notice !== ''): ?><p class="admin-notice" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if (!$loggedIn): ?>
      <form class="admin-login" method="post"><h2>Admin sign in</h2><label>Password<input type="password" name="password" autocomplete="current-password" required /></label><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><button name="action" value="login">Sign in</button></form>
    <?php else: ?>
      <section class="admin-summary"><p><strong><?= count($rows) ?></strong> recent enquiries</p><p>Visitors only enquire. After speaking with them, mark a booking as booked to make its date unavailable.</p></section>
      <form class="admin-block" method="post"><label>Block an offline-booked date<input type="date" name="date" min="<?= h(booking_today()) ?>" required /></label><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><button name="action" value="block">Block date</button></form>
      <div class="admin-table-wrap"><table><thead><tr><th>Reference</th><th>Event date</th><th>Guest</th><th>Event / guests</th><th>Message</th><th>Status</th><th>Email</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($rows as $row): ?><tr><td>#<?= h($row['id']) ?><small><?= h($row['created_at']) ?></small></td><td><?= h($row['event_date']) ?></td><td><?= h($row['name']) ?><small><?= h($row['phone']) ?><?= $row['email'] !== '' ? '<br />' . h($row['email']) : '' ?></small></td><td><?= h($row['event_type']) ?><small><?= h($row['guests'] ?? '') ?></small></td><td class="admin-message"><?= nl2br(h($row['message'])) ?></td><td><span class="admin-status admin-status-<?= h($row['status']) ?>"><?= h($statusLabels[$row['status']] ?? $row['status']) ?></span></td><td><?= h($emailLabels[$row['email_state']] ?? $row['email_state']) ?></td><td><?php if ($row['status'] === 'pending'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" value="<?= h($row['id']) ?>" /><button name="action" value="confirm">Mark booked</button></form><?php endif; ?><?php if ($row['status'] !== 'cancelled'): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>" /><input type="hidden" name="id" value="<?= h($row['id']) ?>" /><button class="admin-secondary" name="action" value="cancel"><?= $row['status'] === 'blocked' ? 'Unblock' : 'Cancel' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </main>
</body>
</html>
