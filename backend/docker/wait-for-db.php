<?php

declare(strict_types=1);

/**
 * Wartet bis PostgreSQL Verbindungen annimmt (PDO SELECT 1).
 * Nutzung: php docker/wait-for-db.php
 * Optional: WAIT_FOR_DB_ATTEMPTS (Standard 90), WAIT_FOR_DB_SLEEP (Standard 2 Sekunden).
 */

$url = getenv('DATABASE_URL');
if ($url === false || $url === '') {
    fwrite(STDERR, "[wait-for-db] DATABASE_URL is not set.\n");
    exit(1);
}

$base = preg_replace('/\?.*$/', '', $url) ?? $url;
$parts = parse_url($base);
if ($parts === false || !isset($parts['host'])) {
    fwrite(STDERR, "[wait-for-db] Invalid DATABASE_URL.\n");
    exit(1);
}

$host = $parts['host'];
$port = (int) ($parts['port'] ?? 5432);
$dbname = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : 'postgres';
$user = $parts['user'] ?? '';
$pass = $parts['pass'] ?? '';

$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $dbname);
$maxAttempts = max(1, (int) (getenv('WAIT_FOR_DB_ATTEMPTS') ?: 90));
$sleepSeconds = max(1, (int) (getenv('WAIT_FOR_DB_SLEEP') ?: 2));

fwrite(STDOUT, "[wait-for-db] Waiting for PostgreSQL at {$host}:{$port}/{$dbname} …\n");

for ($i = 1; $i <= $maxAttempts; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        fwrite(STDOUT, "[wait-for-db] OK (attempt {$i}/{$maxAttempts}).\n");
        exit(0);
    } catch (Throwable $e) {
        if ($i === 1 || $i % 15 === 0) {
            fwrite(STDERR, "[wait-for-db] attempt {$i}/{$maxAttempts}: {$e->getMessage()}\n");
        }
        sleep($sleepSeconds);
    }
}

fwrite(STDERR, "[wait-for-db] Timeout: database not reachable after {$maxAttempts} attempts.\n");
exit(1);
