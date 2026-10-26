<?php
declare(strict_types=1);

if (!defined('ADMIN_SETTINGS_PAGE')) {
    $legacyTab = (string) ($_GET['tab'] ?? 'venue');
    if ($legacyTab === 'whatsapp') { header('Location: whatsapp.php'); exit; }
    $routes = ['venue' => 'contact-details', 'payments' => 'payments', 'email' => 'email', 'system' => 'page-display', 'popup' => 'website-popup'];
    $route = $routes[$legacyTab] ?? 'contact-details';
    header('Location: settings/' . $route . '.php');
    exit;
}
