<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/booking-store.php';
require $bookingStore;
require dirname(__DIR__) . '/server-patch/whatsapp-store.php';
require __DIR__ . '/dashboard-lib.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
require_once __DIR__ . '/session.php';
admin_start_session();

function ih(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

try {
    $config = booking_config();
    $db = booking_db();
} catch (Throwable $error) {
    error_log('Inbox setup: ' . $error->getMessage());
    http_response_code(503);
    exit('The admin panel is still being set up. Please contact the person who manages the website.');
}
if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) { header('Location: index.php'); exit; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$wa = wa_config($db, $config);
$connected = $wa['token'] !== '' && $wa['phone_id'] !== '' && $wa['app_secret'] !== '' && $wa['verify_token'] !== '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>WhatsApp Inbox | Venue Admin</title>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__ . '/theme.css') ?>">
<link rel="stylesheet" href="inbox.css?v=<?= filemtime(__DIR__ . '/inbox.css') ?>">
</head>
<body class="admin-page inbox-page">
<div class="dashboard-layout">
<?php $activeAdminSection = 'inbox'; require __DIR__ . '/sidebar.php'; ?>
<main class="dashboard-main inbox-main" id="inbox" data-csrf="<?= ih($_SESSION['csrf']) ?>">
    <header class="topbar">
        <button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="page-title">
            <p class="eyebrow">WHATSAPP</p>
            <h1>Inbox</h1>
            <p>Read and reply to guest WhatsApp messages in one place.</p>
        </div>
        <div class="header-actions">
            <span class="connection-pill <?= $connected ? 'is-on' : '' ?>"><?= $connected ? 'Connected' : 'Not connected' ?></span>
            <a class="secondary-button" href="whatsapp.php">WhatsApp setup</a>
        </div>
    </header>
    <?php if (!$connected): ?>
    <p class="notice">WhatsApp is not fully connected yet. Messages appear here after the access token, App Secret and webhook are set up in <a href="whatsapp.php">WhatsApp setup</a>.</p>
    <?php endif; ?>

    <section class="inbox" id="inboxShell">
        <aside class="inbox-list" aria-label="Conversations">
            <div class="inbox-search">
                <label class="sr-only" for="convSearch">Search chats</label>
                <input id="convSearch" type="search" placeholder="Search name, number or message" autocomplete="off">
            </div>
            <ul id="convList" class="conv-list" role="list"></ul>
            <p id="convEmpty" class="inbox-empty" hidden>No WhatsApp chats yet. New guest messages will appear here automatically.</p>
        </aside>

        <section class="inbox-thread" aria-live="polite">
            <div id="threadPlaceholder" class="thread-placeholder">
                <strong>Select a chat</strong>
                <span>Choose a conversation on the left to read and reply.</span>
            </div>
            <div id="threadView" class="thread-view" hidden>
                <header class="thread-header">
                    <button class="icon-button thread-back" id="threadBack" type="button" aria-label="Back to chats">‹</button>
                    <span class="avatar" id="threadAvatar" aria-hidden="true"></span>
                    <div class="thread-who"><strong id="threadName"></strong><small id="threadPhone"></small></div>
                    <a class="secondary-button" id="threadCall" href="#">Call</a>
                </header>
                <div class="thread-messages" id="threadMessages"></div>
                <footer class="composer">
                    <p class="composer-note" id="windowNote" hidden></p>
                    <div class="composer-panel" id="emojiPanel" hidden role="dialog" aria-label="Emojis"><div class="emoji-grid" id="emojiGrid"></div></div>
                    <div class="composer-panel" id="repliesPanel" hidden role="dialog" aria-label="Saved replies">
                        <div class="panel-head"><strong>Saved replies</strong><small>Click to insert. {name} becomes the guest's name.</small></div>
                        <ul class="reply-list" id="replyList" role="list"></ul>
                        <form class="reply-save" id="replySave">
                            <input id="replyTitle" maxlength="80" placeholder="Name for the message in the box, e.g. Rates" aria-label="Saved reply name">
                            <button class="secondary-button" type="submit">Save current message</button>
                        </form>
                    </div>
                    <div class="composer-panel" id="templatePanel" hidden role="dialog" aria-label="Send template">
                        <div class="panel-head"><strong>Send an approved template</strong><small>Works any time, even after 24 hours.</small></div>
                        <label>Template<select id="templateSelect"></select></label>
                        <p class="template-preview" id="templatePreview"></p>
                        <div class="template-fields" id="templateFields"></div>
                        <button class="primary-button" type="button" id="templateSend">Send template</button>
                    </div>
                    <form id="composer" class="composer-form">
                        <div class="composer-tools">
                            <button class="tool-button" type="button" id="emojiButton" aria-label="Emojis" aria-controls="emojiPanel" title="Emojis">😊</button>
                            <button class="tool-button" type="button" id="repliesButton" aria-label="Saved replies" aria-controls="repliesPanel" title="Saved replies">⚡</button>
                        </div>
                        <label class="sr-only" for="composerText">Message</label>
                        <textarea id="composerText" rows="1" maxlength="4096" placeholder="Type a reply…"></textarea>
                        <button class="primary-button" type="submit" id="sendButton">Send</button>
                    </form>
                    <button class="secondary-button" type="button" id="templateButton">📄 Send template</button>
                    <p class="composer-error" id="composerError" role="alert" hidden></p>
                </footer>
            </div>
        </section>

        <aside class="inbox-details" id="threadDetails" hidden>
            <h2>Guest records</h2>
            <div id="recordList" class="record-list"></div>
            <a class="secondary-button" id="newEnquiryLink" href="enquiries.php">+ New enquiry from chat</a>
        </aside>
    </section>
</main>
</div>
<script src="inbox.js?v=<?= filemtime(__DIR__ . '/inbox.js') ?>" defer></script>
</body>
</html>
