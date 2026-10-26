<?php
declare(strict_types=1);

$activeAdminSection = $activeAdminSection ?? '';
$activeSettingsPage = $activeSettingsPage ?? '';
$sidebarPrefix = $sidebarPrefix ?? '';
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$sidebarCsrf = (string) $_SESSION['csrf'];
$settingsLinks = [
    'venue' => ['Contact details', 'contact-details.php'],
    'payments' => ['Payments', 'payments.php'],
    'whatsapp' => ['WhatsApp setup', '../whatsapp.php'],
    'email' => ['Email setup', 'email.php'],
    'system' => ['Page display', 'page-display.php'],
    'popup' => ['Website pop-up', 'website-popup.php'],
];
$sidebarUnread = 0;
if (isset($db) && $db instanceof PDO) {
    require_once dirname(__DIR__) . '/server-patch/whatsapp-store.php';
    $sidebarUnread = wa_unread_count($db);
}
$sidebarIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'inbox' => '<path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.5L3 21l2-5.5A8.4 8.4 0 1 1 21 11.5Z"/>',
    'enquiries' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'bookings' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15l2 2 4-4"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
];
$sidebarIcon = static fn(string $name): string => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $sidebarIcons[$name] . '</svg>';
$sidebarMain = [
    'dashboard' => ['Dashboard', 'index.php'],
    'inbox' => ['WhatsApp Inbox', 'inbox.php'],
    'enquiries' => ['Enquiries', 'enquiries.php'],
    'bookings' => ['Bookings', 'bookings.php'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">VC</div>
        <div><strong>Venue Admin</strong><span>Venutai Chavan Hall</span></div>
    </div>
    <div class="sidebar-scroll">
        <nav aria-label="Admin navigation">
            <?php foreach ($sidebarMain as $key => [$label, $route]): ?>
                <a class="<?= $activeAdminSection === $key ? 'active' : '' ?>" href="<?= $sidebarPrefix . $route ?>"<?= $activeAdminSection === $key ? ' aria-current="page"' : '' ?>><?= $sidebarIcon($key) ?><span class="nav-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><?php if ($key === 'inbox'): ?><span class="nav-badge" data-inbox-badge<?= $sidebarUnread ? '' : ' hidden' ?>><?= $sidebarUnread > 99 ? '99+' : $sidebarUnread ?></span><?php endif; ?></a>
            <?php endforeach; ?>
            <div class="sidebar-section-heading"><?= $sidebarIcon('settings') ?>Settings</div>
            <div class="sidebar-settings-links">
                <?php foreach ($settingsLinks as $key => [$label, $route]): ?>
                    <a class="<?= $activeSettingsPage === $key ? 'active' : '' ?>" href="<?= $sidebarPrefix ?>settings/<?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>"<?= $activeSettingsPage === $key ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        </nav>
    </div>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr((string) ($_SESSION['admin_username'] ?? 'A'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
            <div><strong><?= htmlspecialchars((string) ($_SESSION['admin_username'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?></strong><small>Signed in</small></div>
        </div>
        <form method="post" action="<?= $sidebarPrefix ?>index.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($sidebarCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" name="action" value="logout" aria-label="Log out" title="Log out"><?= $sidebarIcon('logout') ?></button>
        </form>
    </div>
</aside>
