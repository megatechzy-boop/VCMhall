<?php
declare(strict_types=1);
$bookingStore = dirname(__DIR__) . '/server-patch/booking-store.php';
if (!is_file($bookingStore)) $bookingStore = dirname(__DIR__) . '/booking-store.php';
require $bookingStore;
require __DIR__ . '/dashboard-lib.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
require_once __DIR__ . '/session.php';
admin_start_session();

function teh(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function team_finish(string $message): never { $_SESSION['admin_notice'] = $message; header('Location: team.php'); exit; }
function team_password(string $password, string $confirm): string
{
    if (admin_text_length($password) < ADMIN_MIN_PASSWORD) throw new InvalidArgumentException('Use a password of at least ' . ADMIN_MIN_PASSWORD . ' characters.');
    if (strlen($password) > 200) throw new InvalidArgumentException('That password is too long.');
    if (!hash_equals($password, $confirm)) throw new InvalidArgumentException('The two passwords do not match.');
    return password_hash($password, PASSWORD_DEFAULT);
}

try {
    $config = booking_config();
    $db = booking_db();
    if (!admin_session_authenticated($db, (string) ($config['admin_password_hash'] ?? ''))) { header('Location: index.php'); exit; }
    admin_users_ensure($db);
} catch (Throwable $error) {
    error_log('Team page setup: ' . $error->getMessage());
    http_response_code(503);
    exit('The admin panel is still being set up. Please contact the person who manages the website.');
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$notice = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);

$myId = (int) ($_SESSION['admin_user_id'] ?? 0);
$myRole = admin_role();
$canTeam = admin_can('team');
$manageable = ADMIN_ROLE_CAN_MANAGE[$myRole] ?? [];
$setupLogin = $myId === 0; // signed in with the shared setup password (no team logins yet)
$findUser = static function (int $id) use ($db): ?array {
    $query = $db->prepare('SELECT * FROM admin_users WHERE id = ?');
    $query->execute([$id]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Your session expired. Please sign in again, then repeat your last action.'); }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'my_password') {
            if ($setupLogin) throw new InvalidArgumentException('The setup login has no own password. Create your Super Admin login first.');
            $me = $findUser($myId);
            if (!$me || !password_verify((string) ($_POST['current_password'] ?? ''), (string) $me['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
            $hash = team_password((string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
            $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$hash, $myId]);
            session_regenerate_id(true);
            team_finish('Your password was changed.');
        }

        admin_require('team');

        if ($action === 'add_user') {
            $name = trim((string) ($_POST['display_name'] ?? ''));
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $role = (string) ($_POST['role'] ?? '');
            if ($name === '' || admin_text_length($name) > 120) throw new InvalidArgumentException('Enter the person\'s name.');
            if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) throw new InvalidArgumentException('Username: 3–60 characters, using letters, numbers, dot, dash or underscore.');
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) throw new InvalidArgumentException('Enter a valid email address or leave it empty.');
            if (!in_array($role, $manageable, true)) throw new InvalidArgumentException('You cannot give that role.');
            $first = admin_active_user_count($db) === 0;
            if ($first && $role !== 'super_admin') throw new InvalidArgumentException('The first login must be a Super Admin, so setup access is never lost.');
            $taken = $db->prepare('SELECT 1 FROM admin_users WHERE LOWER(username) = ? OR (? <> \'\' AND LOWER(email) = ?)');
            $taken->execute([$username, $email, $email]);
            if ($taken->fetchColumn()) throw new InvalidArgumentException('That username or email is already used.');
            $hash = team_password((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
            $db->prepare('INSERT INTO admin_users (username, email, display_name, role, password_hash, active, created_at) VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)')
                ->execute([$username, $email !== '' ? $email : null, $name, $role, $hash]);
            if ($setupLogin) {
                // The shared setup password stops working once a team login exists, so this session ends here.
                $_SESSION = ['admin_notice' => 'Super Admin login created. Sign in with username "' . $username . '". The shared setup password no longer works.'];
                session_regenerate_id(true);
                header('Location: index.php');
                exit;
            }
            team_finish($name . ' added as ' . admin_role_label($role) . '. Share the username and password with them privately.');
        }

        if ($action === 'save_user' || $action === 'reset_password') {
            $id = (int) ($_POST['id'] ?? 0);
            $target = $findUser($id);
            if (!$target) throw new InvalidArgumentException('That login no longer exists.');
            $targetRole = admin_normalize_role((string) $target['role']);
            if (!in_array($targetRole, $manageable, true)) throw new InvalidArgumentException('You cannot change a ' . admin_role_label($targetRole) . ' login.');

            if ($action === 'reset_password') {
                $hash = team_password((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
                team_finish('Password reset for ' . ($target['display_name'] ?: $target['username']) . '.');
            }

            $name = trim((string) ($_POST['display_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $role = (string) ($_POST['role'] ?? $targetRole);
            $active = isset($_POST['active']) ? 1 : 0;
            if ($name === '' || admin_text_length($name) > 120) throw new InvalidArgumentException('Enter the person\'s name.');
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) throw new InvalidArgumentException('Enter a valid email address or leave it empty.');
            if (!in_array($role, $manageable, true)) throw new InvalidArgumentException('You cannot give that role.');
            if ($id === $myId && ($role !== $targetRole || !$active)) throw new InvalidArgumentException('You cannot change your own role or switch off your own login.');
            if ($targetRole === 'super_admin' && ($role !== 'super_admin' || !$active) && admin_is_last_super_admin($db, $id)) {
                throw new InvalidArgumentException('Keep at least one active Super Admin, or nobody can reach the setup pages.');
            }
            if ($email !== '') {
                $taken = $db->prepare('SELECT 1 FROM admin_users WHERE LOWER(email) = ? AND id <> ?');
                $taken->execute([$email, $id]);
                if ($taken->fetchColumn()) throw new InvalidArgumentException('That email is already used by another login.');
            }
            $db->prepare('UPDATE admin_users SET display_name = ?, email = ?, role = ?, active = ? WHERE id = ?')
                ->execute([$name, $email !== '' ? $email : null, $role, $active, $id]);
            team_finish(($active ? 'Saved ' : 'Switched off ') . $name . ($active ? ' (' . admin_role_label($role) . ').' : '. They are signed out on their next click.'));
        }
        team_finish('Unknown action.');
    } catch (InvalidArgumentException $error) {
        team_finish($error->getMessage());
    } catch (PDOException $error) {
        error_log('Team page: ' . $error->getMessage());
        team_finish('The change could not be saved.');
    }
}

$users = $canTeam ? $db->query('SELECT id, username, email, display_name, role, active, last_login_at, created_at FROM admin_users ORDER BY active DESC, id')->fetchAll(PDO::FETCH_ASSOC) : [];
$roleOrder = array_flip(array_keys(ADMIN_ROLES));
usort($users, static fn(array $a, array $b): int => [(int) !$a['active'], $roleOrder[admin_normalize_role($a['role'])] ?? 9, $a['id']] <=> [(int) !$b['active'], $roleOrder[admin_normalize_role($b['role'])] ?? 9, $b['id']]);
$noUsersYet = $canTeam && !$users;
$roleOptions = static function (string $selected) use ($manageable, $noUsersYet): string {
    $html = '';
    foreach ($manageable as $role) {
        if ($noUsersYet && $role !== 'super_admin') continue;
        $html .= '<option value="' . teh($role) . '"' . ($role === $selected ? ' selected' : '') . '>' . teh(ADMIN_ROLES[$role]) . '</option>';
    }
    return $html;
};
$when = static fn(?string $value): string => $value ? date('d M Y, h:i A', strtotime($value)) : 'Never';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $canTeam ? 'Team' : 'My login' ?> | Venue Admin</title>
<link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
<link rel="stylesheet" href="settings.css?v=<?= filemtime(__DIR__ . '/settings.css') ?>">
<link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__ . '/theme.css') ?>">
</head>
<body class="admin-page">
<div class="dashboard-layout">
<?php $activeAdminSection = 'team'; require __DIR__ . '/sidebar.php'; ?>
<main class="dashboard-main settings-main">
    <header class="topbar">
        <button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div class="page-title">
            <p class="eyebrow">ADMINISTRATION</p>
            <h1><?= $canTeam ? 'Team & roles' : 'My login' ?></h1>
            <p><?= $canTeam ? 'Give each person their own login and only the access their work needs.' : 'Change your own password.' ?></p>
        </div>
    </header>
    <?php if ($notice !== ''): ?><p class="notice" role="status"><?= teh($notice) ?></p><?php endif; ?>
    <?php if ($setupLogin): ?><p class="notice">You are using the shared setup password. Create your own <strong>Super Admin</strong> login below; after that the shared password stops working and everyone signs in with their own login.</p><?php endif; ?>

    <section class="settings-grid alerts-grid">
        <?php if ($canTeam): ?>
        <section class="settings-card-form">
            <div class="settings-heading"><div><h2>Team logins</h2><p><?= count(array_filter($users, static fn($u) => (int) $u['active'])) ?> active</p></div></div>
            <form class="member-form" method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= teh($_SESSION['csrf']) ?>">
                <h3 class="sub-heading">Add a team member</h3>
                <div class="form-grid">
                    <label>Name<input name="display_name" required maxlength="120" placeholder="e.g. Ramesh Patil"></label>
                    <label>Role<select name="role" required><?= $roleOptions($noUsersYet ? 'super_admin' : 'sales') ?></select></label>
                    <label>Username<input name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" autocapitalize="none" spellcheck="false" placeholder="ramesh"></label>
                    <label>Email (optional)<input name="email" type="email" maxlength="254" placeholder="name@example.com"></label>
                    <label>Password<input name="password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                    <label>Repeat password<input name="confirm_password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                </div>
                <p class="helper-text">At least <?= ADMIN_MIN_PASSWORD ?> characters. Share it privately; they can change it under <strong>My login</strong>.</p>
                <button class="primary-button" name="action" value="add_user">+ Add login</button>
            </form>

            <div class="member-list">
                <?php if (!$users): ?><p class="muted">No team logins yet.</p><?php endif; ?>
                <?php foreach ($users as $u): $uRole = admin_normalize_role($u['role']); $canEdit = in_array($uRole, $manageable, true); $isMe = (int) $u['id'] === $myId; ?>
                <details class="member<?= (int) $u['active'] ? '' : ' is-paused' ?>">
                    <summary>
                        <span class="avatar" aria-hidden="true"><?= teh(mb_strtoupper(mb_substr((string) ($u['display_name'] ?: $u['username']), 0, 1))) ?></span>
                        <span class="member-who"><strong><?= teh($u['display_name'] ?: $u['username']) ?><?= $isMe ? ' (you)' : '' ?></strong><small><?= teh($u['username']) ?><?= $u['email'] ? ' · ' . teh($u['email']) : '' ?> · last sign-in: <?= teh($when($u['last_login_at'])) ?></small></span>
                        <span class="member-tags">
                            <?php if (!(int) $u['active']): ?><span class="status-badge status-blocked">Switched off</span><?php endif; ?>
                            <span class="source-badge"><?= teh(admin_role_label($uRole)) ?></span>
                        </span>
                    </summary>
                    <?php if ($canEdit): ?>
                    <form method="post" class="member-edit" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= teh($_SESSION['csrf']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <div class="form-grid">
                            <label>Name<input name="display_name" required maxlength="120" value="<?= teh($u['display_name'] ?: $u['username']) ?>"></label>
                            <label>Role<select name="role"<?= $isMe ? ' disabled' : '' ?>><?= $roleOptions($uRole) ?></select><?php if ($isMe): ?><input type="hidden" name="role" value="<?= teh($uRole) ?>"><?php endif; ?></label>
                            <label class="wide">Email<input name="email" type="email" maxlength="254" value="<?= teh($u['email'] ?? '') ?>"></label>
                        </div>
                        <div class="member-switches">
                            <?php if ($isMe): ?><input type="hidden" name="active" value="1"><?php else: ?>
                            <label class="switch-label"><input type="checkbox" name="active" value="1" <?= (int) $u['active'] ? 'checked' : '' ?>> Login switched on</label>
                            <?php endif; ?>
                            <button class="primary-button" name="action" value="save_user">Save</button>
                        </div>
                    </form>
                    <?php if (!$isMe): ?>
                    <form method="post" class="member-edit" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= teh($_SESSION['csrf']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <div class="form-grid">
                            <label>New password<input name="password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                            <label>Repeat password<input name="confirm_password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                        </div>
                        <button class="secondary-button" name="action" value="reset_password">Reset password</button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="helper-text">Only a Super Admin can change this login.</p>
                    <?php endif; ?>
                </details>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="settings-card-form">
            <div class="settings-heading"><div><h2>What each role can do</h2><p>Checked on the server for every page and action.</p></div></div>
            <ul class="mini-log">
                <?php foreach (ADMIN_ROLES as $key => $label): ?>
                <li><span class="source-badge"><?= teh($label) ?></span><span><?= teh(ADMIN_ROLE_HELP[$key]) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$setupLogin): ?>
            <form method="post" class="member-form" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= teh($_SESSION['csrf']) ?>">
                <h3 class="sub-heading">My password</h3>
                <p class="helper-text">Signed in as <strong><?= teh($_SESSION['admin_display_name'] ?: $_SESSION['admin_username']) ?></strong> · <?= teh(admin_role_label()) ?></p>
                <div class="form-grid">
                    <label class="wide">Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
                    <label>New password<input name="new_password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                    <label>Repeat new password<input name="confirm_password" type="password" required minlength="<?= ADMIN_MIN_PASSWORD ?>" autocomplete="new-password"></label>
                </div>
                <button class="secondary-button" name="action" value="my_password">Change my password</button>
            </form>
            <?php endif; ?>
        </section>
    </section>
</main>
</div>
<script src="settings.js?v=<?= filemtime(__DIR__ . '/settings.js') ?>" defer></script>
</body>
</html>
