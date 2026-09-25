<?php
/**
 * Database Migration Script
 * Runs all SQL migrations in order against the connected PostgreSQL database.
 * 
 * Usage:
 *   php sql/migrate.php
 *   FORCE_MIGRATE=true php sql/migrate.php
 */

require_once __DIR__ . '/../app/config/database.php';

echo "=== Food Ordering System - Database Migration ===\n";

if (!isset($pdo)) {
    echo "Error: PDO connection object not found.\n";
    exit(1);
}

// Check if tables already exist
$checkStmt = $pdo->query("SELECT to_regclass('public.users') AS tbl;");
$result = $checkStmt->fetch();
$tablesExist = !empty($result['tbl']);

$force = (getenv('FORCE_MIGRATE') === 'true' || in_array('--force', $argv ?? []));

if ($tablesExist && !$force) {
    echo "Notice: Database tables already exist.\n";
    echo "Running incremental updates only (skipping schema drop).\n";
    echo "To re-create all tables from scratch, run with: FORCE_MIGRATE=true php sql/migrate.php\n\n";

    // Run safe incremental migrations
    $incrementalFiles = [
        '001_create_default_users.sql',
        '003_add_riders.sql',
        'add_user_addresses_table.sql',
    ];

    foreach ($incrementalFiles as $file) {
        $filePath = __DIR__ . '/' . $file;
        if (file_exists($filePath)) {
            echo "Running $file... ";
            try {
                $sql = file_get_contents($filePath);
                $pdo->exec($sql);
                echo "OK\n";
            } catch (PDOException $e) {
                echo "Skipped or already applied: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nIncremental migrations completed!\n";
    exit(0);
}

// Full initialization
$migrationOrder = [
    '000_schema.sql',
    '001_create_default_users.sql',
    '002_insert_sample_menu.sql',
    '003_add_riders.sql',
    'add_user_addresses_table.sql',
];

foreach ($migrationOrder as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
        echo "Warning: File $file not found, skipping.\n";
        continue;
    }

    echo "Executing $file... ";
    try {
        $sql = file_get_contents($filePath);
        $pdo->exec($sql);
        echo "SUCCESS\n";
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nAll migrations completed successfully!\n";
