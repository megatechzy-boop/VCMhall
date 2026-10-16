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
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">VC</div>
        <div><strong>Venue Admin</strong><span>Management portal</span></div>
    </div>
    <div class="sidebar-scroll">
        <nav aria-label="Admin navigation">
            <a class="<?= $activeAdminSection === 'dashboard' ? 'active' : '' ?>" href="<?= $sidebarPrefix ?>index.php"><span>▦</span>Dashboard</a>
            <a class="<?= $activeAdminSection === 'enquiries' ? 'active' : '' ?>" href="<?= $sidebarPrefix ?>enquiries.php"><span>✉</span>Enquiries</a>
            <a class="<?= $activeAdminSection === 'bookings' ? 'active' : '' ?>" href="<?= $sidebarPrefix ?>bookings.php"><span>▣</span>Bookings</a>
            <div class="sidebar-section-heading"><span>⚙</span>Settings</div>
            <div class="sidebar-settings-links">
                <?php foreach ($settingsLinks as $key => [$label, $route]): ?>
                    <a class="<?= $activeSettingsPage === $key ? 'active' : '' ?>" href="<?= $sidebarPrefix ?>settings/<?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
        </nav>
    </div>
    <div class="sidebar-footer">
        <strong>Late Venutai Chavan</strong>
        <small>Multipurpose Hall</small>
        <form method="post" action="<?= $sidebarPrefix ?>index.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($sidebarCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" name="action" value="logout"><span aria-hidden="true">↪</span>Logout</button>
        </form>
    </div>
</aside>
