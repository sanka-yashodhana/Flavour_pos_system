<?php
// Get database credentials from environment variables or DATABASE_URL (Railway, Render, Supabase, etc.)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL') ?: getenv('POSTGRES_URL');

if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    $host = $dbParts['host'] ?? 'localhost';
    $port = $dbParts['port'] ?? '5432';
    $user = isset($dbParts['user']) ? urldecode($dbParts['user']) : 'postgres';
    $pass = isset($dbParts['pass']) ? urldecode($dbParts['pass']) : '';
    $db   = isset($dbParts['path']) ? ltrim($dbParts['path'], '/') : 'food_app';

    $sslmode = getenv('DB_SSLMODE');
    if (!$sslmode && isset($dbParts['query'])) {
        parse_str($dbParts['query'], $queryParts);
        $sslmode = $queryParts['sslmode'] ?? null;
    }
} else {
    $host = getenv('DB_HOST') ?: getenv('PGHOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432';
    $db   = getenv('DB_NAME') ?: getenv('PGDATABASE') ?: 'food_app';
    $user = getenv('DB_USER') ?: getenv('PGUSER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: getenv('PGPASSWORD') ?: '';
    $sslmode = getenv('DB_SSLMODE');
}

// Default SSL mode: disable for local containers/localhost, require for remote/cloud
if (!$sslmode && !in_array($host, ['localhost', '127.0.0.1', 'db'])) {
    $sslmode = 'require';
}

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
if ($sslmode) {
    $dsn .= ";sslmode=$sslmode";
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET timezone = 'UTC'");
} catch (PDOException $e) {
    // If sslmode was require and failed, retry once without sslmode
    if ($sslmode === 'require') {
        try {
            $fallbackDsn = "pgsql:host=$host;port=$port;dbname=$db";
            $pdo = new PDO($fallbackDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET timezone = 'UTC'");
        } catch (PDOException $e2) {
            die("Database Connection Failed: " . $e2->getMessage());
        }
    } else {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

