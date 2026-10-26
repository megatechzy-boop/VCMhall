<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
require $bookingStore;
require dirname(__DIR__) . '/server-patch/whatsapp-store.php';
require dirname(__DIR__) . '/server-patch/leads-store.php';
require __DIR__ . '/dashboard-lib.php';
require __DIR__ . '/settings-lib.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
require_once __DIR__ . '/session.php';
admin_start_session();

function lh(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lead_pretty_phone(?string $p): string { $p = (string) $p; return strlen($p) === 12 && str_starts_with($p, '91') ? '+91 ' . substr($p, 2, 5) . ' ' . substr($p, 7) : ($p !== '' ? '+' . $p : ''); }
function lead_admin_finish(string $message): never { $_SESSION['admin_notice'] = $message; header('Location: lead-alerts.php'); exit; }

try {
    $config = booking_config();
    $db = booking_db();
    if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) { header('Location: index.php'); exit; }
    lead_ensure_schema($db);
} catch (Throwable $error) {
    error_log('Lead alerts setup: ' . $error->getMessage());
    http_response_code(503);
    exit('The admin panel is still being set up. Please contact the person who manages the website.');
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$notice = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Your session expired. Please sign in again, then repeat your last action.'); }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_rules') {
            foreach (['lead_customer_whatsapp', 'lead_customer_email', 'lead_team_whatsapp', 'lead_team_email'] as $key) setting_save($db, $key, setting_bool($_POST, $key), 'lead_alerts');
            foreach (['customer', 'team'] as $audience) {
                $name = trim((string) ($_POST["lead_{$audience}_template"] ?? ''));
                $lang = trim((string) ($_POST["lead_{$audience}_template_lang"] ?? 'en'));
                if ($name !== '' && !preg_match('/^[a-z0-9_]{1,512}$/', $name)) throw new InvalidArgumentException('Template names use lowercase letters, numbers and underscores.');
                if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $lang)) throw new InvalidArgumentException('Use a template language code such as en or en_US.');
                setting_save($db, "lead_{$audience}_template", $name, 'lead_alerts');
                setting_save($db, "lead_{$audience}_template_lang", $lang, 'lead_alerts');
            }
            lead_admin_finish('Alert settings saved.');
        }
        if ($action === 'add_member' || $action === 'save_member') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($name === '' || admin_text_length($name) > 120) throw new InvalidArgumentException('Enter the team member\'s name.');
            if ($phone === '' && $email === '') throw new InvalidArgumentException('Add a WhatsApp number or an email address.');
            if ($phone !== '' && lead_wa_number($phone) === '') throw new InvalidArgumentException('Enter a valid WhatsApp number, for example 9876543210 or +91 98765 43210.');
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || admin_text_length($email) > 254)) throw new InvalidArgumentException('Enter a valid email address.');
            $values = [$name, $phone !== '' ? lead_wa_number($phone) : null, $email !== '' ? $email : null, isset($_POST['notify_whatsapp']) ? 1 : 0, isset($_POST['notify_email']) ? 1 : 0, isset($_POST['active']) ? 1 : 0];
            if ($action === 'add_member') {
                $db->prepare('INSERT INTO lead_recipients (name, phone, email, notify_whatsapp, notify_email, active) VALUES (?, ?, ?, ?, ?, ?)')->execute($values);
                lead_admin_finish($name . ' added to lead alerts.');
            }
            $values[] = (int) ($_POST['id'] ?? 0);
            $db->prepare('UPDATE lead_recipients SET name = ?, phone = ?, email = ?, notify_whatsapp = ?, notify_email = ?, active = ? WHERE id = ?')->execute($values);
            lead_admin_finish($name . ' updated.');
        }
        if ($action === 'delete_member') {
            $db->prepare('DELETE FROM lead_recipients WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            lead_admin_finish('Team member removed.');
        }
        if ($action === 'save_meta') {
            $pageId = trim((string) ($_POST['meta_page_id'] ?? ''));
            $token = trim((string) ($_POST['meta_page_token'] ?? ''));
            if ($pageId !== '' && !preg_match('/^\d{5,30}$/', $pageId)) throw new InvalidArgumentException('Facebook Page ID contains digits only.');
            if ($token !== '' && (admin_text_length($token) > 600 || preg_match('/\s/', $token))) throw new InvalidArgumentException('Paste the Page access token without spaces.');
            setting_save($db, 'meta_page_id', $pageId, 'meta');
            if ($token !== '') setting_save($db, 'meta_page_token_enc', booking_encrypt_resend_key($token, $config), 'meta');
            lead_admin_finish('Meta Lead Ads settings saved. Secrets are encrypted and never displayed.');
        }
        if ($action === 'test_alert') {
            $before = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM lead_notifications')->fetchColumn();
            lead_notify($db, $config, ['id' => null, 'source' => 'website', 'name' => 'Test Guest', 'phone' => '', 'email' => '', 'event_type' => 'Wedding', 'event_date' => date('Y-m-d', strtotime('+30 days')), 'hall' => 'big', 'guests' => 300, 'message' => 'This is a test alert from the admin panel.']);
            $count = $db->prepare('SELECT status, COUNT(*) c FROM lead_notifications WHERE id > ? GROUP BY status');
            $count->execute([$before]);
            $summary = [];
            foreach ($count->fetchAll() as $row) $summary[] = $row['c'] . ' ' . $row['status'];
            lead_admin_finish($summary ? 'Test alert finished: ' . implode(', ', $summary) . '. See the log below.' : 'No team member has alerts switched on yet.');
        }
    } catch (InvalidArgumentException | RuntimeException $error) {
        lead_admin_finish($error->getMessage());
    }
    lead_admin_finish('Unknown action.');
}

$s = settings_all($db);
$on = static fn(string $key): bool => ($s[$key] ?? '1') === '1';
$members = $db->query('SELECT * FROM lead_recipients ORDER BY active DESC, name')->fetchAll();
$log = $db->query('SELECT * FROM lead_notifications ORDER BY id DESC LIMIT 60')->fetchAll();
$metaLeads = $db->query('SELECT leadgen_id, booking_id, status, error, created_at FROM meta_leads ORDER BY id DESC LIMIT 10')->fetchAll();
$approved = $db->query("SELECT name, language, status FROM wa_templates ORDER BY name")->fetchAll();
$metaTokenSaved = meta_page_token($db, $config) !== '';
$waConnected = booking_whatsapp_token($db, $config) !== '' && ($s['whatsapp_phone_number_id'] ?? '') !== '';
$webhookUrl = 'https://' . preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'venutaihall.com')) . '/whatsapp-webhook.php';
$templateValue = static fn(string $audience): string => (string) ($s["lead_{$audience}_template"] ?? LEAD_DEFAULT_TEMPLATES[$audience]['name']);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Lead alerts | Venue Admin</title>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<link rel="stylesheet" href="settings.css?v=<?= filemtime(__DIR__ . '/settings.css') ?>">
<link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__ . '/theme.css') ?>">
</head>
<body class="admin-page">
<div class="dashboard-layout">
<?php $activeAdminSection = 'settings'; $activeSettingsPage = 'alerts'; require __DIR__ . '/sidebar.php'; ?>
<main class="dashboard-main settings-main">
    <header class="topbar">
        <button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="page-title">
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>Lead alerts</h1>
            <p>Who gets a WhatsApp and email when a website or Meta Ads lead arrives.</p>
        </div>
    </header>
    <?php if ($notice !== ''): ?><p class="notice"><?= lh($notice) ?></p><?php endif; ?>
    <?php if (!$waConnected): ?><p class="notice">WhatsApp alerts are switched on but will be skipped until WhatsApp is connected in <a href="whatsapp.php">WhatsApp setup</a>. Emails work now.</p><?php endif; ?>

    <section class="settings-grid alerts-grid">
        <form class="settings-card-form" method="post">
            <input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>">
            <div class="settings-heading"><div><h2>Alert rules</h2><p>Switch each alert type on or off.</p></div></div>
            <fieldset class="alert-rules">
                <legend>Guest (person who enquired)</legend>
                <label class="switch-label"><input type="checkbox" name="lead_customer_whatsapp" <?= $on('lead_customer_whatsapp') ? 'checked' : '' ?>> WhatsApp “we got your enquiry”</label>
                <label class="switch-label"><input type="checkbox" name="lead_customer_email" <?= $on('lead_customer_email') ? 'checked' : '' ?>> Confirmation email (when they gave an email)</label>
                <div class="form-grid">
                    <label>Guest template<input name="lead_customer_template" list="templateNames" value="<?= lh($templateValue('customer')) ?>"></label>
                    <label>Language<input name="lead_customer_template_lang" value="<?= lh($s['lead_customer_template_lang'] ?? 'en') ?>"></label>
                </div>
            </fieldset>
            <fieldset class="alert-rules">
                <legend>Team members</legend>
                <label class="switch-label"><input type="checkbox" name="lead_team_whatsapp" <?= $on('lead_team_whatsapp') ? 'checked' : '' ?>> WhatsApp with lead details</label>
                <label class="switch-label"><input type="checkbox" name="lead_team_email" <?= $on('lead_team_email') ? 'checked' : '' ?>> Email with lead details</label>
                <div class="form-grid">
                    <label>Team template<input name="lead_team_template" list="templateNames" value="<?= lh($templateValue('team')) ?>"></label>
                    <label>Language<input name="lead_team_template_lang" value="<?= lh($s['lead_team_template_lang'] ?? 'en') ?>"></label>
                </div>
            </fieldset>
            <datalist id="templateNames"><?php foreach ($approved as $t): ?><option value="<?= lh($t['name']) ?>"><?= lh($t['language'] . ' · ' . $t['status']) ?></option><?php endforeach; ?></datalist>
            <p class="helper-text">WhatsApp only allows approved templates for these messages. Create and track them in <a href="wa-templates.php">WhatsApp templates</a>.</p>
            <div class="wa-actions">
                <button class="primary-button" name="action" value="save_rules">Save alert rules</button>
                <button class="secondary-button" name="action" value="test_alert" formnovalidate>Send test alert to team</button>
            </div>
        </form>

        <section class="settings-card-form">
            <div class="settings-heading"><div><h2>Team members</h2><p><?= count($members) ?> added · no limit</p></div></div>
            <form class="member-form" method="post">
                <input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>">
                <div class="form-grid">
                    <label>Name<input name="name" required maxlength="120" placeholder="e.g. Rohit (Manager)"></label>
                    <label>WhatsApp number<input name="phone" type="tel" inputmode="tel" placeholder="9876543210"></label>
                    <label class="wide">Email<input name="email" type="email" placeholder="name@example.com"></label>
                </div>
                <div class="member-switches">
                    <label class="switch-label"><input type="checkbox" name="notify_whatsapp" checked> WhatsApp</label>
                    <label class="switch-label"><input type="checkbox" name="notify_email" checked> Email</label>
                    <input type="hidden" name="active" value="1">
                    <button class="primary-button" name="action" value="add_member">+ Add member</button>
                </div>
            </form>
            <div class="member-list">
                <?php if (!$members): ?><p class="muted">No team members yet. Add everyone who should hear about new leads.</p><?php endif; ?>
                <?php foreach ($members as $m): ?>
                <details class="member<?= (int) $m['active'] ? '' : ' is-paused' ?>">
                    <summary>
                        <span class="avatar" aria-hidden="true"><?= lh(mb_strtoupper(mb_substr((string) $m['name'], 0, 1))) ?></span>
                        <span class="member-who"><strong><?= lh($m['name']) ?></strong><small><?= lh(trim(lead_pretty_phone($m['phone']) . ($m['phone'] && $m['email'] ? ' · ' : '') . ($m['email'] ?? ''))) ?></small></span>
                        <span class="member-tags">
                            <?php if (!(int) $m['active']): ?><span class="status-badge status-blocked">Paused</span><?php else: ?>
                            <?php if ((int) $m['notify_whatsapp'] && $m['phone']): ?><span class="source-badge source-whatsapp">WhatsApp</span><?php endif; ?>
                            <?php if ((int) $m['notify_email'] && $m['email']): ?><span class="source-badge">Email</span><?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </summary>
                    <form method="post" class="member-edit">
                        <input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <div class="form-grid">
                            <label>Name<input name="name" required maxlength="120" value="<?= lh($m['name']) ?>"></label>
                            <label>WhatsApp number<input name="phone" type="tel" value="<?= lh($m['phone'] ?? '') ?>"></label>
                            <label class="wide">Email<input name="email" type="email" value="<?= lh($m['email'] ?? '') ?>"></label>
                        </div>
                        <div class="member-switches">
                            <label class="switch-label"><input type="checkbox" name="notify_whatsapp" <?= (int) $m['notify_whatsapp'] ? 'checked' : '' ?>> WhatsApp</label>
                            <label class="switch-label"><input type="checkbox" name="notify_email" <?= (int) $m['notify_email'] ? 'checked' : '' ?>> Email</label>
                            <label class="switch-label"><input type="checkbox" name="active" <?= (int) $m['active'] ? 'checked' : '' ?>> Active</label>
                        </div>
                        <div class="wa-actions">
                            <button class="primary-button" name="action" value="save_member">Save</button>
                            <button class="secondary-button danger-text" name="action" value="delete_member" formnovalidate data-confirm="Remove <?= lh($m['name']) ?> from lead alerts?">Remove</button>
                        </div>
                    </form>
                </details>
                <?php endforeach; ?>
            </div>
        </section>
    </section>

    <section class="settings-grid alerts-grid">
        <form class="settings-card-form" method="post">
            <input type="hidden" name="csrf" value="<?= lh($_SESSION['csrf']) ?>">
            <div class="settings-heading">
                <div><h2>Meta Lead Ads</h2><p>Facebook and Instagram lead form entries become enquiries automatically.</p></div>
                <span class="wa-status <?= $metaTokenSaved && ($s['meta_page_id'] ?? '') !== '' ? 'saved' : 'pending' ?>"><?= $metaTokenSaved && ($s['meta_page_id'] ?? '') !== '' ? 'Connected' : 'Setup needed' ?></span>
            </div>
            <div class="form-grid">
                <label>Facebook Page ID<input name="meta_page_id" inputmode="numeric" value="<?= lh($s['meta_page_id'] ?? '') ?>" placeholder="123456789012345"></label>
                <label>Page access token<input type="password" name="meta_page_token" autocomplete="new-password" spellcheck="false" placeholder="<?= $metaTokenSaved ? 'Saved securely — paste only to replace' : 'Long-lived Page token with leads_retrieval' ?>"></label>
                <label class="wide">Webhook callback URL (same as WhatsApp)<input readonly value="<?= lh($webhookUrl) ?>"></label>
            </div>
            <p class="wa-help">In the Meta app: Webhooks → <strong>Page</strong> → use the same callback URL and verify token as WhatsApp → subscribe to <strong>leadgen</strong>. Then connect the Page to the app (Page → subscribed apps). The token needs <code>leads_retrieval</code> and <code>pages_manage_metadata</code>.</p>
            <button class="primary-button" name="action" value="save_meta">Save Meta Lead Ads</button>
            <?php if ($metaLeads): ?>
            <h3 class="sub-heading">Latest Meta leads</h3>
            <ul class="mini-log">
                <?php foreach ($metaLeads as $ml): ?>
                <li><span class="status-badge status-<?= $ml['status'] === 'saved' ? 'confirmed' : 'cancelled' ?>"><?= lh(ucfirst(str_replace('_', ' ', (string) $ml['status']))) ?></span>
                    <span><?= lh(date('d M, h:i A', strtotime((string) $ml['created_at']))) ?><?= $ml['booking_id'] ? ' · ENQ-' . str_pad((string) $ml['booking_id'], 6, '0', STR_PAD_LEFT) : '' ?></span>
                    <?php if ($ml['error']): ?><small><?= lh($ml['error']) ?></small><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </form>

        <section class="settings-card-form">
            <div class="settings-heading"><div><h2>Alert log</h2><p>Last 60 messages sent for leads.</p></div></div>
            <?php if (!$log): ?><p class="muted">Nothing sent yet. New leads and test alerts appear here.</p><?php else: ?>
            <div class="table-wrap"><table class="log-table">
                <thead><tr><th>Time</th><th>Lead</th><th>To</th><th>Channel</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($log as $row): ?>
                    <tr>
                        <td><?= lh(date('d M, h:i A', strtotime((string) $row['created_at']))) ?></td>
                        <td><?= $row['booking_id'] ? 'ENQ-' . str_pad((string) $row['booking_id'], 6, '0', STR_PAD_LEFT) : 'Test' ?></td>
                        <td><strong><?= lh(ucfirst((string) $row['audience'])) ?></strong><small><?= lh($row['recipient']) ?></small></td>
                        <td><?= $row['channel'] === 'whatsapp' ? 'WhatsApp' : 'Email' ?></td>
                        <td><span class="status-badge status-<?= $row['status'] === 'sent' ? 'confirmed' : ($row['status'] === 'failed' ? 'cancelled' : 'blocked') ?>"><?= lh(ucfirst((string) $row['status'])) ?></span><?php if ($row['detail'] && $row['status'] !== 'sent'): ?><small><?= lh($row['detail']) ?></small><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    </section>
</main>
</div>
<script src="settings.js?v=<?= filemtime(__DIR__ . '/settings.js') ?>" defer></script>
</body>
</html>
