<?php
/**
 * Database-backed request limits for SSMS.
 * Uses the existing rate_limits table without changing its schema.
 */

function rate_limit_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }
    return $ip;
}

/** Hashes a bucket identity; only the digest is stored in rate_limits. */
function rate_limit_identifier_hash(?string $identifier = null): string
{
    $value = $identifier === null ? rate_limit_client_ip() : $identifier;
    return hash('sha256', $value);
}

function rate_limit_lock_name(string $action, ?string $identifier = null): string
{
    return 'ssms_rl_' . substr(hash('sha256', $action . "\0" . rate_limit_identifier_hash($identifier)), 0, 56);
}

function rate_limit_acquire_locks(mysqli $conn, array $buckets)
{
    // Sort lock names so concurrent requests acquire shared buckets in a consistent order.
    $names = [];
    foreach ($buckets as $bucket) {
        $names[] = rate_limit_lock_name($bucket[0], $bucket[1] ?? null);
    }
    $names = array_values(array_unique($names));
    sort($names, SORT_STRING);
    $acquired = [];
    foreach ($names as $name) {
        $lock = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $lock->bind_param('s', $name);
        $lock->execute();
        $lockResult = rate_limit_fetch_one($lock);
        if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
            rate_limit_release_locks($conn, $acquired);
            return false;
        }
        $acquired[] = $name;
    }
    return $acquired;
}

function rate_limit_release_locks(mysqli $conn, array $locks): void
{
    foreach (array_reverse($locks) as $name) {
        $release = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $name);
        $release->execute();
        $result = $release->get_result();
        if ($result) {
            $result->fetch_row();
            $result->free();
        }
        $release->close();
    }
}

function rate_limit_fetch_one(mysqli_stmt $stmt): ?array
{
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $row ?: null;
}

function rate_limit_columns(mysqli $conn): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    // Map supported legacy column names so this helper can use the existing table schema.
    $result = $conn->query('SHOW COLUMNS FROM rate_limits');
    $available = [];
    while ($row = $result->fetch_assoc()) {
        $available[strtolower($row['Field'])] = $row['Field'];
    }
    $result->free();

    $pick = static function (array $choices) use ($available) {
        foreach ($choices as $choice) {
            if (isset($available[$choice])) {
                return $available[$choice];
            }
        }
        return null;
    };

    $columns = [
        'ip' => $pick(['ip_address', 'ip', 'client_ip']),
        'type' => $pick(['limit_type', 'action', 'action_name', 'endpoint', 'purpose']),
        'identifier' => $pick(['identifier_hash']),
        'attempts' => $pick(['attempts', 'attempt_count', 'request_count']),
        'window' => $pick(['window_start', 'window_started_at', 'first_attempt', 'created_at']),
        'blocked' => $pick(['blocked_until', 'block_expires_at', 'locked_until']),
    ];

    foreach ($columns as $name => $column) {
        if ($column === null) {
            throw new RuntimeException('The rate_limits table is missing a supported ' . $name . ' column.');
        }
    }
    return $columns;
}

/** Returns true when the client may proceed. Expired windows are reset atomically. */
function rate_limit_check(mysqli $conn, string $action, int $limit, int $windowSeconds, ?string $identifier = null, bool $lockHeld = false): bool
{
    $columns = rate_limit_columns($conn);
    $ip = rate_limit_client_ip();
    $identifierHash = rate_limit_identifier_hash($identifier);
    $lockName = rate_limit_lock_name($action, $identifier);
    if (!$lockHeld) {
        $lock = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $lock->bind_param('s', $lockName);
        $lock->execute();
        $lockResult = rate_limit_fetch_one($lock);
        if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
            return false;
        }
    }

    try {
        $ipCol = '`' . $columns['ip'] . '`';
        $typeCol = '`' . $columns['type'] . '`';
        $identifierCol = '`' . $columns['identifier'] . '`';
        $attemptCol = '`' . $columns['attempts'] . '`';
        $windowCol = '`' . $columns['window'] . '`';
        $blockedCol = '`' . $columns['blocked'] . '`';
        $stmt = $conn->prepare("SELECT {$attemptCol} AS attempts, {$windowCol} AS window_start, {$blockedCol} AS blocked_until FROM rate_limits WHERE {$typeCol} = ? AND {$identifierCol} = ? LIMIT 1");
        $stmt->bind_param('ss', $action, $identifierHash);
        $stmt->execute();
        $row = rate_limit_fetch_one($stmt);
        if (!$row) {
            return true;
        }

        $now = time();
        $blockedUntil = !empty($row['blocked_until']) ? strtotime($row['blocked_until']) : false;
        if ($blockedUntil !== false && $blockedUntil > $now) {
            return false;
        }

        $windowStart = !empty($row['window_start']) ? strtotime($row['window_start']) : false;
        if ($windowStart === false || $windowStart + $windowSeconds <= $now) {
            $reset = $conn->prepare("UPDATE rate_limits SET {$attemptCol} = 0, {$windowCol} = NOW(), {$blockedCol} = NULL WHERE {$typeCol} = ? AND {$identifierCol} = ?");
            $reset->bind_param('ss', $action, $identifierHash);
            $reset->execute();
            $reset->close();
            return true;
        }
        return (int) $row['attempts'] < $limit;
    } finally {
        if (!$lockHeld) {
            rate_limit_release_locks($conn, [$lockName]);
        }
    }
}

/** Record a request or failure. Excessive requests are blocked until the window ends. */
function rate_limit_record(mysqli $conn, string $action, int $limit, int $windowSeconds, ?string $identifier = null, bool $lockHeld = false): void
{
    $columns = rate_limit_columns($conn);
    $ip = rate_limit_client_ip();
    $identifierHash = rate_limit_identifier_hash($identifier);
    $lockName = rate_limit_lock_name($action, $identifier);
    if (!$lockHeld) {
        $lock = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $lock->bind_param('s', $lockName);
        $lock->execute();
        $lockResult = rate_limit_fetch_one($lock);
        if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
            return;
        }
    }

    try {
        $ipCol = '`' . $columns['ip'] . '`';
        $typeCol = '`' . $columns['type'] . '`';
        $identifierCol = '`' . $columns['identifier'] . '`';
        $attemptCol = '`' . $columns['attempts'] . '`';
        $windowCol = '`' . $columns['window'] . '`';
        $blockedCol = '`' . $columns['blocked'] . '`';
        $stmt = $conn->prepare("SELECT {$attemptCol} AS attempts, {$windowCol} AS window_start FROM rate_limits WHERE {$typeCol} = ? AND {$identifierCol} = ? LIMIT 1");
        $stmt->bind_param('ss', $action, $identifierHash);
        $stmt->execute();
        $row = rate_limit_fetch_one($stmt);

        if (!$row) {
            $insert = $conn->prepare("INSERT INTO rate_limits ({$typeCol}, {$identifierCol}, {$ipCol}, {$attemptCol}, {$windowCol}, {$blockedCol}) VALUES (?, ?, ?, 1, NOW(), NULL)");
            $insert->bind_param('sss', $action, $identifierHash, $ip);
            $insert->execute();
            $insert->close();
            return;
        }

        $windowStart = !empty($row['window_start']) ? strtotime($row['window_start']) : false;
        if ($windowStart === false || $windowStart + $windowSeconds <= time()) {
            $update = $conn->prepare("UPDATE rate_limits SET {$attemptCol} = 1, {$windowCol} = NOW(), {$blockedCol} = NULL WHERE {$typeCol} = ? AND {$identifierCol} = ?");
        } else {
            $nextCount = (int) $row['attempts'] + 1;
            $block = $nextCount >= $limit;
            $sql = $block
                ? "UPDATE rate_limits SET {$attemptCol} = ?, {$blockedCol} = DATE_ADD(NOW(), INTERVAL {$windowSeconds} SECOND) WHERE {$typeCol} = ? AND {$identifierCol} = ?"
                : "UPDATE rate_limits SET {$attemptCol} = ? WHERE {$typeCol} = ? AND {$identifierCol} = ?";
            $update = $conn->prepare($sql);
            $update->bind_param('iss', $nextCount, $action, $identifierHash);
            $update->execute();
            $update->close();
            return;
        }
        $update->bind_param('ss', $action, $identifierHash);
        $update->execute();
        $update->close();
    } finally {
        if (!$lockHeld) {
            rate_limit_release_locks($conn, [$lockName]);
        }
    }
}

function rate_limit_reset(mysqli $conn, string $action, ?string $identifier = null, bool $lockHeld = false): void
{
    $columns = rate_limit_columns($conn);
    $identifierHash = rate_limit_identifier_hash($identifier);
    $lockName = rate_limit_lock_name($action, $identifier);
    if (!$lockHeld) {
        $lock = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $lock->bind_param('s', $lockName);
        $lock->execute();
        $lockResult = rate_limit_fetch_one($lock);
        if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
            return;
        }
    }
    $stmt = $conn->prepare('DELETE FROM rate_limits WHERE `' . $columns['type'] . '` = ? AND `' . $columns['identifier'] . '` = ?');
    $stmt->bind_param('ss', $action, $identifierHash);
    $stmt->execute();
    $stmt->close();
    if (!$lockHeld) {
        rate_limit_release_locks($conn, [$lockName]);
    }
}

function rate_limit_message(mysqli $conn, string $action, int $limit, int $windowSeconds): string
{
    return 'Too many attempts. Please try again in ' . (int) ceil($windowSeconds / 60) . ' minutes.';
}
