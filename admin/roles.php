<?php
declare(strict_types=1);

// Admin team roles and what each one may do. Every page and action checks admin_can() on the server,
// so hiding a menu link is only for tidiness, never the protection itself.

const ADMIN_ROLES = [
    'super_admin' => 'Super Admin',
    'owner' => 'Owner',
    'manager' => 'Manager',
    'sales' => 'Sales / Front desk',
];

const ADMIN_ROLE_HELP = [
    'super_admin' => 'Website technical setup: WhatsApp, email, Meta and page settings, plus everything else.',
    'owner' => 'Runs the business: all enquiries, bookings, payments and reports, and manages the team.',
    'manager' => 'Day-to-day work: enquiries, bookings, payments, dates, chats and lead alerts.',
    'sales' => 'Enquiries, follow-ups, WhatsApp chats, bookings and payments. No exports, lead alerts or settings.',
];

const ADMIN_PERMISSIONS = [
    'money' => ['super_admin', 'owner', 'manager', 'sales'],      // amounts, payments, payment filters and totals
    'enquiry.convert' => ['super_admin', 'owner', 'manager', 'sales'],  // turn an enquiry into a confirmed booking
    'booking.manage' => ['super_admin', 'owner', 'manager', 'sales'],  // add/cancel bookings, block or free dates, edit amount/source
    'payment.record' => ['super_admin', 'owner', 'manager', 'sales'],
    'export' => ['super_admin', 'owner', 'manager'],           // Excel/PDF downloads of guest data
    'settings.business' => ['super_admin', 'owner', 'manager'], // contact details, website pop-up
    'settings.payments' => ['super_admin', 'owner'],
    'lead_alerts' => ['super_admin', 'owner', 'manager'],
    'wa_templates' => ['super_admin', 'owner'],
    'setup' => ['super_admin'],                                // WhatsApp/email/Meta setup, page display, usage
    'team' => ['super_admin', 'owner'],
];

/** Roles each role may create, edit or switch someone to on the Team page. */
const ADMIN_ROLE_CAN_MANAGE = [
    'super_admin' => ['super_admin', 'owner', 'manager', 'sales'],
    'owner' => ['manager', 'sales'],
];

const ADMIN_MIN_PASSWORD = 10;

/** Maps stored role values to a known role. The old single "admin" role keeps full access. */
function admin_normalize_role(?string $role): string
{
    $role = strtolower(trim((string) $role));
    if ($role === 'admin' || $role === 'superadmin') return 'super_admin';
    return isset(ADMIN_ROLES[$role]) ? $role : 'sales';
}

function admin_role(): string
{
    return admin_normalize_role((string) ($_SESSION['admin_role'] ?? ''));
}

function admin_role_label(?string $role = null): string
{
    return ADMIN_ROLES[$role ?? admin_role()] ?? 'Team member';
}

function admin_can(string $permission): bool
{
    return in_array(admin_role(), ADMIN_PERMISSIONS[$permission] ?? [], true);
}

/** Stops the request when the signed-in role lacks a permission. */
function admin_require(string $permission, bool $json = false): void
{
    if (admin_can($permission)) return;
    http_response_code(403);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Your role does not allow this action.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $back = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/settings/') ? '../index.php' : 'index.php';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Not allowed | Venue Admin</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:0 20px;color:#222"><h1 style="font-size:22px">This page is not part of your role</h1>'
        . '<p>You are signed in as <strong>' . htmlspecialchars(admin_role_label(), ENT_QUOTES, 'UTF-8') . '</strong>. Ask the owner if you need access.</p>'
        . '<p><a href="' . $back . '">Back to the dashboard</a></p></body></html>';
    exit;
}

/** Name shown in activity history, e.g. "Ramesh (Manager)". */
function admin_actor_label(): string
{
    $name = trim((string) ($_SESSION['admin_display_name'] ?? '')) ?: trim((string) ($_SESSION['admin_username'] ?? ''));
    return ($name !== '' ? $name : 'Admin') . ' (' . admin_role_label() . ')';
}

function admin_users_ensure(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($sqlite) {
        $db->exec("CREATE TABLE IF NOT EXISTS admin_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, email TEXT NULL, display_name TEXT NULL,
            role TEXT NOT NULL DEFAULT 'sales', password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, last_login_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $columns = array_column($db->query('PRAGMA table_info(admin_users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['display_name' => 'TEXT NULL', 'last_login_at' => 'TEXT NULL', 'created_at' => 'TEXT NULL'] as $column => $type) {
            if (!in_array($column, $columns, true)) $db->exec("ALTER TABLE admin_users ADD COLUMN {$column} {$type}");
        }
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL, email VARCHAR(254) NULL, display_name VARCHAR(120) NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'sales', password_hash VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY admin_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // An older admin_users table may predate these columns or use a short ENUM for role.
        $info = $db->query("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users'")->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($info as $column) $byName[strtolower((string) $column['COLUMN_NAME'])] = $column;
        foreach (['display_name' => 'VARCHAR(120) NULL', 'last_login_at' => 'DATETIME NULL', 'created_at' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP'] as $column => $type) {
            if (!isset($byName[$column])) $db->exec("ALTER TABLE admin_users ADD COLUMN {$column} {$type}");
        }
        if (isset($byName['role']) && !in_array(strtolower((string) $byName['role']['DATA_TYPE']), ['varchar', 'text'], true)) {
            $db->exec("ALTER TABLE admin_users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'sales'");
        }
        if (isset($byName['email']) && $byName['email']['IS_NULLABLE'] !== 'YES') {
            $db->exec('ALTER TABLE admin_users MODIFY email VARCHAR(254) NULL');
        }
    }
    $done = true;
}

function admin_active_user_count(PDO $db): int
{
    try {
        admin_users_ensure($db);
        return (int) $db->query('SELECT COUNT(*) FROM admin_users WHERE active = 1')->fetchColumn();
    } catch (Throwable $error) {
        error_log('Admin users table: ' . $error->getMessage());
        return 0;
    }
}

/** True when nobody else with full setup access would remain after a change to this user. */
function admin_is_last_super_admin(PDO $db, int $userId): bool
{
    $query = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE active = 1 AND role IN ('super_admin', 'admin') AND id <> ?");
    $query->execute([$userId]);
    return (int) $query->fetchColumn() === 0;
}
