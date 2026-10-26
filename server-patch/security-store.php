<?php
declare(strict_types=1);

// Database-backed rate limiting (login attempts, public form submissions).
// Keys are stored as SHA-256 hashes, so no raw IP addresses or usernames are kept.

function rate_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec('CREATE TABLE IF NOT EXISTS rate_hits (id INTEGER PRIMARY KEY AUTOINCREMENT, bucket TEXT NOT NULL, key_hash TEXT NOT NULL, created_at INTEGER NOT NULL)');
        $db->exec('CREATE INDEX IF NOT EXISTS rate_hits_lookup ON rate_hits(bucket, key_hash, created_at)');
    } else {
        $db->exec('CREATE TABLE IF NOT EXISTS rate_hits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bucket VARCHAR(40) NOT NULL,
            key_hash CHAR(64) NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            KEY rate_hits_lookup (bucket, key_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    $done = true;
}

function rate_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function rate_key(string $value): string
{
    return hash('sha256', 'vcmhall-rate|' . strtolower(trim($value)));
}

/** Number of hits recorded for this bucket and key within the last $windowSeconds. */
function rate_count(PDO $db, string $bucket, string $key, int $windowSeconds): int
{
    rate_ensure_schema($db);
    $query = $db->prepare('SELECT COUNT(*) FROM rate_hits WHERE bucket = ? AND key_hash = ? AND created_at > ?');
    $query->execute([$bucket, rate_key($key), time() - $windowSeconds]);
    return (int) $query->fetchColumn();
}

function rate_record(PDO $db, string $bucket, string $key): void
{
    rate_ensure_schema($db);
    $db->prepare('INSERT INTO rate_hits (bucket, key_hash, created_at) VALUES (?, ?, ?)')->execute([$bucket, rate_key($key), time()]);
    if (random_int(1, 50) === 1) {
        $db->prepare('DELETE FROM rate_hits WHERE created_at < ?')->execute([time() - 2 * 86400]);
    }
}

function rate_clear(PDO $db, string $bucket, string $key): void
{
    rate_ensure_schema($db);
    $db->prepare('DELETE FROM rate_hits WHERE bucket = ? AND key_hash = ?')->execute([$bucket, rate_key($key)]);
}
