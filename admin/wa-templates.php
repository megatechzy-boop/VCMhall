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

function th(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tpl_finish(string $message): never { $_SESSION['admin_notice'] = $message; header('Location: wa-templates.php'); exit; }

const TPL_EXAMPLES = [
    'enquiry_received' => ['Priya', 'Wedding'],
    'new_lead_alert' => ['Website', 'Priya Sharma', '9876543210', 'Wedding', '12 Dec 2026'],
];

/** Validates a template body and its example values; returns the Meta "components" array. */
function tpl_components(string $body, array $examples, string $footer): array
{
    $body = trim(str_replace("\r\n", "\n", $body));
    if ($body === '' || mb_strlen($body) > 1024) throw new InvalidArgumentException('Message text is required (up to 1,024 characters).');
    preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $body, $m);
    $vars = array_map('strval', $m[1]);
    foreach ($vars as $i => $v) if ($v !== (string) ($i + 1)) throw new InvalidArgumentException('Number the variables in order: {{1}}, {{2}}, {{3}} …');
    if ($vars && (preg_match('/^\{\{/', $body) || preg_match('/\}\}[.!]?$/', $body))) throw new InvalidArgumentException('Meta rejects messages that start or end with a variable. Add some words before {{1}} and after the last variable.');
    $examples = array_values(array_filter(array_map('trim', $examples), static fn($v) => $v !== ''));
    if (count($examples) < count($vars)) throw new InvalidArgumentException('Give an example value for every variable (' . count($vars) . ' needed).');
    $component = ['type' => 'BODY', 'text' => $body];
    if ($vars) $component['example'] = ['body_text' => [array_slice($examples, 0, count($vars))]];
    $components = [$component];
    $footer = trim($footer);
    if ($footer !== '') {
        if (mb_strlen($footer) > 60 || str_contains($footer, '{{')) throw new InvalidArgumentException('Footer: up to 60 characters, no variables.');
        $components[] = ['type' => 'FOOTER', 'text' => $footer];
    }
    return $components;
}

function tpl_sync(PDO $db, array $wa, string $wabaId): int
{
    $count = 0;
    $path = rawurlencode($wabaId) . '/message_templates?fields=id,name,language,status,category,components,rejected_reason&limit=100';
    for ($page = 0; $page < 5 && $path !== ''; $page++) {
        [$code, $data] = wa_api($wa, 'GET', $path);
        if ($code !== 200) throw new RuntimeException('Meta did not return templates: ' . (string) ($data['error']['message'] ?? 'HTTP ' . $code));
        foreach ($data['data'] ?? [] as $t) { wa_template_store($db, $t); $count++; }
        $after = (string) ($data['paging']['cursors']['after'] ?? '');
        $path = !empty($data['paging']['next']) && $after !== '' ? rawurlencode($wabaId) . '/message_templates?fields=id,name,language,status,category,components,rejected_reason&limit=100&after=' . rawurlencode($after) : '';
    }
    return $count;
}

try {
    $config = booking_config();
    $db = booking_db();
    if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) { header('Location: index.php'); exit; }
    lead_ensure_schema($db);
} catch (Throwable $error) {
    error_log('Templates setup: ' . $error->getMessage());
    http_response_code(503);
    exit('The admin panel is still being set up. Please contact the person who manages the website.');
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$notice = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);
$wa = wa_config($db, $config);
$wabaId = booking_setting($db, 'whatsapp_business_account_id');
$ready = $wa['token'] !== '' && $wabaId !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Your session expired. Please sign in again, then repeat your last action.'); }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'use_for') {
            $audience = (string) ($_POST['audience'] ?? '');
            $name = (string) ($_POST['name'] ?? '');
            $lang = (string) ($_POST['language'] ?? 'en');
            if (!in_array($audience, ['customer', 'team'], true) || !wa_template_find($db, $name, $lang)) throw new InvalidArgumentException('Template not found.');
            setting_save($db, "lead_{$audience}_template", $name, 'lead_alerts');
            setting_save($db, "lead_{$audience}_template_lang", $lang, 'lead_alerts');
            tpl_finish("“{$name}” will be used for " . ($audience === 'customer' ? 'guest' : 'team') . ' lead alerts.');
        }
        if ($action === 'use_reopen') {
            $name = (string) ($_POST['name'] ?? '');
            $lang = (string) ($_POST['language'] ?? 'en');
            if (!wa_template_find($db, $name, $lang)) throw new InvalidArgumentException('Template not found.');
            setting_save($db, 'whatsapp_inbox_template', $name, 'whatsapp');
            setting_save($db, 'whatsapp_inbox_template_lang', $lang, 'whatsapp');
            tpl_finish("“{$name}” will re-open closed inbox chats.");
        }
        if (!$ready) throw new RuntimeException('Save the Business Account ID and access token in WhatsApp setup first.');
        if ($action === 'sync') {
            tpl_finish('Synced ' . tpl_sync($db, $wa, $wabaId) . ' templates from Meta.');
        }
        if ($action === 'create' || $action === 'create_default' || $action === 'resubmit') {
            if ($action === 'create_default') {
                $key = (string) ($_POST['default'] ?? '');
                $audience = $key === 'new_lead_alert' ? 'team' : 'customer';
                if (!isset(TPL_EXAMPLES[$key])) throw new InvalidArgumentException('Unknown ready-made template.');
                $name = $key; $lang = 'en'; $category = 'UTILITY'; $footer = 'Late Venutai Chavan Multipurpose Hall';
                $body = LEAD_DEFAULT_TEMPLATES[$audience]['body']; $examples = TPL_EXAMPLES[$key];
            } else {
                $name = strtolower(trim((string) ($_POST['name'] ?? '')));
                $lang = trim((string) ($_POST['language'] ?? 'en'));
                $category = strtoupper((string) ($_POST['category'] ?? 'UTILITY'));
                $body = (string) ($_POST['body'] ?? '');
                $footer = (string) ($_POST['footer'] ?? '');
                $examples = array_map('strval', (array) ($_POST['examples'] ?? []));
            }
            if (!preg_match('/^[a-z0-9_]{1,512}$/', $name)) throw new InvalidArgumentException('Name: lowercase letters, numbers and underscores only, e.g. enquiry_received.');
            if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $lang)) throw new InvalidArgumentException('Use a language code such as en, hi, mr or en_US.');
            if (!in_array($category, ['UTILITY', 'MARKETING'], true)) throw new InvalidArgumentException('Choose Utility or Marketing.');
            $components = tpl_components($body, $examples, $footer);
            $existing = wa_template_find($db, $name, $lang);
            if ($action === 'resubmit') {
                if (!$existing || !$existing['meta_id']) throw new InvalidArgumentException('Template not found. Sync from Meta first.');
                [$code, $data] = wa_api($wa, 'POST', rawurlencode((string) $existing['meta_id']), ['components' => $components]);
                if ($code < 200 || $code >= 300) throw new RuntimeException('Meta did not accept the change: ' . (string) ($data['error']['error_user_msg'] ?? $data['error']['message'] ?? 'HTTP ' . $code));
                wa_template_store($db, ['id' => $existing['meta_id'], 'name' => $name, 'language' => $lang, 'category' => $existing['category'], 'status' => 'PENDING', 'components' => $components]);
                tpl_finish("“{$name}” resubmitted. Meta usually reviews it within minutes to a few hours.");
            }
            if ($existing) throw new InvalidArgumentException("A template named “{$name}” ({$lang}) already exists. Edit it below or choose another name.");
            [$code, $data] = wa_api($wa, 'POST', rawurlencode($wabaId) . '/message_templates', ['name' => $name, 'language' => $lang, 'category' => $category, 'components' => $components]);
            if ($code < 200 || $code >= 300) throw new RuntimeException('Meta did not accept the template: ' . (string) ($data['error']['error_user_msg'] ?? $data['error']['message'] ?? 'HTTP ' . $code));
            wa_template_store($db, ['id' => (string) ($data['id'] ?? ''), 'name' => $name, 'language' => $lang, 'category' => (string) ($data['category'] ?? $category), 'status' => (string) ($data['status'] ?? 'PENDING'), 'components' => $components]);
            tpl_finish("“{$name}” submitted to Meta. Status: " . ucfirst(strtolower((string) ($data['status'] ?? 'PENDING'))) . '.');
        }
        if ($action === 'delete') {
            $name = (string) ($_POST['name'] ?? '');
            if (!preg_match('/^[a-z0-9_]{1,512}$/', $name)) throw new InvalidArgumentException('Template not found.');
            [$code, $data] = wa_api($wa, 'DELETE', rawurlencode($wabaId) . '/message_templates?name=' . rawurlencode($name));
            if ($code < 200 || $code >= 300) throw new RuntimeException('Meta did not delete the template: ' . (string) ($data['error']['message'] ?? 'HTTP ' . $code));
            $db->prepare('DELETE FROM wa_templates WHERE name = ?')->execute([$name]);
            tpl_finish("“{$name}” deleted (all languages).");
        }
    } catch (InvalidArgumentException | RuntimeException $error) {
        $_SESSION['tpl_draft'] = array_intersect_key($_POST, array_flip(['name', 'language', 'category', 'body', 'footer', 'examples']));
        tpl_finish($error->getMessage());
    }
    tpl_finish('Unknown action.');
}

$draft = (array) ($_SESSION['tpl_draft'] ?? []);
unset($_SESSION['tpl_draft']);
$templates = $db->query('SELECT * FROM wa_templates ORDER BY name, language')->fetchAll();
$existingNames = array_column($templates, 'name');
$s = settings_all($db);
$inUse = [];
foreach ([($s['lead_customer_template'] ?? 'enquiry_received') => 'Guest alerts', ($s['lead_team_template'] ?? 'new_lead_alert') => 'Team alerts', ($s['whatsapp_inbox_template'] ?? '') => 'Inbox re-open'] as $name => $use) {
    if ($name !== '') $inUse[$name] = isset($inUse[$name]) ? $inUse[$name] . ' · ' . $use : $use;
}
$statusClass = static fn(?string $st): string => match (strtoupper((string) $st)) { 'APPROVED' => 'confirmed', 'REJECTED', 'DISABLED' => 'cancelled', 'PAUSED', 'PENDING_DELETION' => 'follow_up', default => 'pending' };
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>WhatsApp templates | Venue Admin</title>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<link rel="stylesheet" href="settings.css?v=<?= filemtime(__DIR__ . '/settings.css') ?>">
<link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__ . '/theme.css') ?>">
</head>
<body class="admin-page">
<div class="dashboard-layout">
<?php $activeAdminSection = 'settings'; $activeSettingsPage = 'watemplates'; require __DIR__ . '/sidebar.php'; ?>
<main class="dashboard-main settings-main">
    <header class="topbar">
        <button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="page-title">
            <p class="eyebrow">ADMINISTRATION</p>
            <h1>WhatsApp templates</h1>
            <p>Create message templates, send them to Meta for approval and track their status.</p>
        </div>
        <?php if ($ready): ?>
        <form class="header-actions" method="post"><input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>"><button class="secondary-button" name="action" value="sync">↻ Sync status from Meta</button></form>
        <?php endif; ?>
    </header>
    <?php if ($notice !== ''): ?><p class="notice"><?= th($notice) ?></p><?php endif; ?>
    <?php if (!$ready): ?><p class="notice">Add the WhatsApp Business Account ID and access token in <a href="whatsapp.php">WhatsApp setup</a> to submit templates. You can prepare them now.</p><?php endif; ?>

    <section class="settings-grid alerts-grid">
        <section class="settings-card-form">
            <div class="settings-heading"><div><h2>Ready-made templates</h2><p>Written for lead alerts. Submit with one click.</p></div></div>
            <?php foreach (['enquiry_received' => 'customer', 'new_lead_alert' => 'team'] as $key => $audience): ?>
            <article class="ready-template">
                <header><strong><?= th($key) ?></strong><span class="source-badge"><?= $audience === 'customer' ? 'To guest' : 'To team' ?> · Utility</span></header>
                <p class="template-body"><?= nl2br(th(LEAD_DEFAULT_TEMPLATES[$audience]['body'])) ?></p>
                <?php if (in_array($key, $existingNames, true)): ?>
                    <p class="helper-text">Already created — see its status below.</p>
                <?php else: ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>"><input type="hidden" name="default" value="<?= th($key) ?>">
                    <button class="primary-button" name="action" value="create_default" <?= $ready ? '' : 'disabled' ?>>Submit to Meta</button></form>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </section>

        <form class="settings-card-form" method="post" id="templateForm">
            <input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>">
            <div class="settings-heading"><div><h2>New template</h2><p>Use {{1}}, {{2}} … for details that change.</p></div></div>
            <div class="form-grid">
                <label>Name<input name="name" required pattern="[a-z0-9_]{1,512}" value="<?= th($draft['name'] ?? '') ?>" placeholder="booking_confirmed"><small>Lowercase, numbers, underscores.</small></label>
                <label>Category<select name="category"><option value="UTILITY">Utility (updates, confirmations)</option><option value="MARKETING" <?= ($draft['category'] ?? '') === 'MARKETING' ? 'selected' : '' ?>>Marketing (offers, promotions)</option></select></label>
                <label>Language<input name="language" required value="<?= th($draft['language'] ?? 'en') ?>" placeholder="en"><small>en, hi, mr or en_US</small></label>
                <label>Footer (optional)<input name="footer" maxlength="60" value="<?= th($draft['footer'] ?? '') ?>" placeholder="Late Venutai Chavan Hall"></label>
                <label class="wide">Message<textarea name="body" rows="5" maxlength="1024" required data-template-body placeholder="Namaste {{1}}, your booking for {{2}} is confirmed. See you soon!"><?= th($draft['body'] ?? '') ?></textarea></label>
            </div>
            <div class="example-fields" data-examples>
                <?php foreach ((array) ($draft['examples'] ?? []) as $i => $example): ?>
                <label>Example for {{<?= $i + 1 ?>}}<input name="examples[]" value="<?= th($example) ?>"></label>
                <?php endforeach; ?>
            </div>
            <p class="helper-text">Meta needs a sample value for each variable. Messages cannot start or end with a variable.</p>
            <button class="primary-button" name="action" value="create" <?= $ready ? '' : 'disabled' ?>>Submit for approval</button>
        </form>
    </section>

    <section class="settings-card-form">
        <div class="settings-heading"><div><h2>Your templates</h2><p>Status updates arrive automatically from Meta. Use “Sync” if something looks out of date.</p></div></div>
        <?php if (!$templates): ?><p class="muted">No templates yet. Submit the ready-made ones above, or sync if you already created templates in Meta.</p><?php endif; ?>
        <div class="template-list">
        <?php foreach ($templates as $t): $status = strtoupper((string) $t['status']); ?>
            <details class="member template-row">
                <summary>
                    <span class="member-who"><strong><?= th($t['name']) ?> <small class="muted">· <?= th($t['language']) ?> · <?= th(ucfirst(strtolower((string) $t['category']))) ?></small></strong>
                        <small><?= th(mb_strimwidth((string) $t['body'], 0, 110, '…')) ?></small></span>
                    <span class="member-tags">
                        <?php if (isset($inUse[$t['name']])): ?><span class="source-badge source-whatsapp"><?= th($inUse[$t['name']]) ?></span><?php endif; ?>
                        <span class="status-badge status-<?= $statusClass($t['status']) ?>"><?= th(ucfirst(strtolower(str_replace('_', ' ', $status ?: 'Unknown')))) ?></span>
                    </span>
                </summary>
                <div class="member-edit">
                    <p class="template-body"><?= nl2br(th($t['body'])) ?></p>
                    <?php if ($t['reason'] && $status === 'REJECTED'): ?><p class="notice">Meta’s reason: <?= th($t['reason']) ?>. Edit the text below and resubmit.</p><?php endif; ?>
                    <?php if ($status === 'APPROVED'): ?>
                    <div class="wa-actions">
                        <?php foreach (['customer' => 'Use for guest alerts', 'team' => 'Use for team alerts', 'reopen' => 'Use to re-open inbox chats'] as $use => $label): if ($use === 'reopen' && (int) $t['variable_count'] !== 0) continue; ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>"><input type="hidden" name="name" value="<?= th($t['name']) ?>"><input type="hidden" name="language" value="<?= th($t['language']) ?>"><input type="hidden" name="audience" value="<?= th($use) ?>">
                            <button class="secondary-button" name="action" value="<?= $use === 'reopen' ? 'use_reopen' : 'use_for' ?>"><?= th($label) ?></button></form>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($status, ['REJECTED', 'PAUSED'], true)): ?>
                    <form method="post" class="resubmit-form">
                        <input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>"><input type="hidden" name="name" value="<?= th($t['name']) ?>"><input type="hidden" name="language" value="<?= th($t['language']) ?>"><input type="hidden" name="category" value="<?= th($t['category'] ?: 'UTILITY') ?>">
                        <label>Message<textarea name="body" rows="4" maxlength="1024" data-template-body><?= th($t['body']) ?></textarea></label>
                        <div class="example-fields" data-examples></div>
                        <button class="primary-button" name="action" value="resubmit" <?= $ready ? '' : 'disabled' ?>>Resubmit for approval</button>
                    </form>
                    <?php endif; ?>
                    <form method="post"><input type="hidden" name="csrf" value="<?= th($_SESSION['csrf']) ?>"><input type="hidden" name="name" value="<?= th($t['name']) ?>">
                        <button class="secondary-button danger-text" name="action" value="delete" data-confirm="Delete “<?= th($t['name']) ?>” from Meta? Messages using it will stop." <?= $ready ? '' : 'disabled' ?>>Delete template</button></form>
                </div>
            </details>
        <?php endforeach; ?>
        </div>
    </section>
</main>
</div>
<script src="settings.js?v=<?= filemtime(__DIR__ . '/settings.js') ?>" defer></script>
<script src="templates.js?v=<?= filemtime(__DIR__ . '/templates.js') ?>" defer></script>
</body>
</html>
