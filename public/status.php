<?php
// Start output buffering to catch any errors
ob_start();

// Include dependencies
require_once '../app/helpers/auth.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$userInfo = $isLoggedIn ? [
    'id' => getCurrentUserId(),
    'role' => getCurrentUserRole(),
    'username' => getCurrentUserName()
] : null;

// Initialize status checks
$checks = [];

// ============================================
// 1. PHP VERSION CHECK
// ============================================
$checks['php'] = [
    'name' => 'PHP Version',
    'status' => true,
    'message' => 'PHP ' . phpversion(),
    'icon' => '✓'
];

// ============================================
// 2. DATABASE CONNECTION CHECK
// ============================================
try {
    require_once '../app/config/database.php';
    $dbHostInfo = getenv('DB_HOST') ?: ($host ?? 'unknown');
    $dbPortInfo = getenv('DB_PORT') ?: ($port ?? '5432');
    $dbNameInfo = getenv('DB_NAME') ?: ($db ?? 'unknown');
    $dbUserInfo = getenv('DB_USER') ?: ($user ?? 'unknown');
    $dbSourceInfo = getenv('DB_HOST') ? 'docker env vars' : 'app/config/database.php';
    
    if (empty($pdo)) {
        throw new Exception($dbConnectionError ?? 'Database not connected');
    }

    $checks['database'] = [
        'name' => 'Database Connection',
        'status' => true,
        'message' => 'Connected to PostgreSQL',
        'icon' => '✓'
    ];
    
    // Get database version
    $stmt = $pdo->query('SELECT version()');
    $version = $stmt->fetchColumn();
    $checks['database']['details'] = sprintf(
        'Host: %s:%s • DB: %s • User: %s • Source: %s • Version: %s',
        $dbHostInfo,
        $dbPortInfo,
        $dbNameInfo,
        $dbUserInfo,
        $dbSourceInfo,
        substr($version, 0, 40) . '...'
    );
    
} catch (Throwable $e) {
    $checks['database'] = [
        'name' => 'Database Connection',
        'status' => false,
        'message' => 'Failed to connect',
        'icon' => '✗',
        'error' => $e->getMessage()
    ];
}

// ============================================
// 3. REQUIRED PHP EXTENSIONS
// ============================================
$requiredExtensions = ['pdo', 'pdo_pgsql', 'session'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

$checks['extensions'] = [
    'name' => 'PHP Extensions',
    'status' => empty($missingExtensions),
    'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing: ' . implode(', ', $missingExtensions),
    'icon' => empty($missingExtensions) ? '✓' : '✗'
];

// ============================================
// 4. DATABASE TABLES CHECK
// ============================================
if ($checks['database']['status']) {
    try {
        $stmt = $pdo->query("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            ORDER BY table_name
        ");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $expectedTables = ['users', 'menu_categories', 'menu_items', 'cart', 'orders', 'order_items'];
        $missingTables = array_diff($expectedTables, $tables);
        
        $checks['tables'] = [
            'name' => 'Database Tables',
            'status' => empty($missingTables),
            'message' => empty($missingTables) ? count($tables) . ' tables found' : 'Missing tables',
            'icon' => empty($missingTables) ? '✓' : '✗',
            'details' => implode(', ', $tables)
        ];
        
        if (!empty($missingTables)) {
            $checks['tables']['error'] = 'Missing: ' . implode(', ', $missingTables);
        }
    } catch (Exception $e) {
        $checks['tables'] = [
            'name' => 'Database Tables',
            'status' => false,
            'message' => 'Error checking tables',
            'icon' => '✗',
            'error' => $e->getMessage()
        ];
    }
}

// ============================================
// 5. AUTHENTICATION SYSTEM CHECK
// ============================================
$checks['auth'] = [
    'name' => 'Authentication System',
    'status' => true,
    'message' => 'Session handling operational',
    'icon' => '✓',
    'details' => $isLoggedIn ? "User logged in: {$userInfo['username']} ({$userInfo['role']})" : 'No active session'
];

// ============================================
// 6. SAMPLE DATA CHECK
// ============================================
if ($checks['database']['status']) {
    try {
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        
        // Count menu items
        $stmt = $pdo->query("SELECT COUNT(*) FROM menu_items");
        $menuCount = $stmt->fetchColumn();
        
        // Count categories
        $stmt = $pdo->query("SELECT COUNT(*) FROM menu_categories");
        $categoryCount = $stmt->fetchColumn();
        
        // Count orders
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $orderCount = $stmt->fetchColumn();
        
        $checks['data'] = [
            'name' => 'Sample Data',
            'status' => $userCount > 0 && $menuCount > 0,
            'message' => "Users: {$userCount}, Menu Items: {$menuCount}, Categories: {$categoryCount}, Orders: {$orderCount}",
            'icon' => ($userCount > 0 && $menuCount > 0) ? '✓' : '⚠'
        ];
    } catch (Exception $e) {
        $checks['data'] = [
            'name' => 'Sample Data',
            'status' => false,
            'message' => 'Error checking data',
            'icon' => '✗',
            'error' => $e->getMessage()
        ];
    }
}

// ============================================
// 7. FILE STRUCTURE CHECK
// ============================================
$requiredPaths = [
    '../app/config/database.php',
    '../app/models/User.php',
    '../app/controllers/AuthController.php',
    '../app/helpers/auth.php'
];

$missingFiles = [];
foreach ($requiredPaths as $path) {
    if (!file_exists($path)) {
        $missingFiles[] = basename($path);
    }
}

$checks['files'] = [
    'name' => 'Core Files',
    'status' => empty($missingFiles),
    'message' => empty($missingFiles) ? 'All core files present' : 'Missing files',
    'icon' => empty($missingFiles) ? '✓' : '✗'
];

if (!empty($missingFiles)) {
    $checks['files']['error'] = 'Missing: ' . implode(', ', $missingFiles);
}

// ============================================
// 8. DOCKER ENVIRONMENT CHECK
// ============================================
$isDocker = file_exists('/.dockerenv');
$checks['environment'] = [
    'name' => 'Environment',
    'status' => true,
    'message' => $isDocker ? 'Running in Docker' : 'Running locally',
    'icon' => '✓',
    'details' => 'Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')
];

// Calculate overall system status
$overallStatus = true;
foreach ($checks as $check) {
    if (!$check['status']) {
        $overallStatus = false;
        break;
    }
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Food Ordering System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .status-badge.success {
            background: #10b981;
            color: white;
        }
        
        .status-badge.error {
            background: #ef4444;
            color: white;
        }
        
        .checks-grid {
            display: grid;
            gap: 15px;
        }
        
        .check-item {
            background: #1e293b;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid transparent;
            transition: transform 0.2s;
        }
        
        .check-item:hover {
            transform: translateX(5px);
        }
        
        .check-item.success {
            border-left-color: #10b981;
        }
        
        .check-item.error {
            border-left-color: #ef4444;
        }
        
        .check-item.warning {
            border-left-color: #f59e0b;
        }
        
        .check-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .check-icon {
            font-size: 24px;
            width: 30px;
            text-align: center;
        }
        
        .check-icon.success {
            color: #10b981;
        }
        
        .check-icon.error {
            color: #ef4444;
        }
        
        .check-icon.warning {
            color: #f59e0b;
        }
        
        .check-name {
            font-size: 18px;
            font-weight: 600;
            flex: 1;
        }
        
        .check-message {
            color: #94a3b8;
            margin-left: 45px;
        }
        
        .check-details {
            margin-left: 45px;
            margin-top: 8px;
            padding: 10px;
            background: #0f172a;
            border-radius: 5px;
            font-size: 13px;
            color: #64748b;
            font-family: 'Courier New', monospace;
        }
        
        .check-error {
            margin-left: 45px;
            margin-top: 8px;
            padding: 10px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 5px;
            font-size: 13px;
            color: #fca5a5;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #64748b;
            font-size: 14px;
        }
        
        .auto-refresh {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⚙️ System Status Dashboard</h1>
            <span class="status-badge <?php echo $overallStatus ? 'success' : 'error'; ?>">
                <?php echo $overallStatus ? '● All Systems Operational' : '● Some Issues Detected'; ?>
            </span>
        </div>
        
        <!-- Status Checks -->
        <div class="checks-grid">
            <?php foreach ($checks as $key => $check): ?>
                <div class="check-item <?php echo $check['status'] ? 'success' : 'error'; ?>">
                    <div class="check-header">
                        <div class="check-icon <?php echo $check['status'] ? 'success' : 'error'; ?>">
                            <?php echo $check['icon']; ?>
                        </div>
                        <div class="check-name"><?php echo htmlspecialchars($check['name']); ?></div>
                    </div>
                    <div class="check-message">
                        <?php echo htmlspecialchars($check['message']); ?>
                    </div>
                    
                    <?php if (isset($check['details'])): ?>
                        <div class="check-details">
                            <?php echo htmlspecialchars($check['details']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($check['error'])): ?>
                        <div class="check-error">
                            ⚠️ <?php echo htmlspecialchars($check['error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Actions -->
        <div class="actions">
            <a href="index.php" class="btn btn-primary">← Back to Home</a>
            <?php if ($isLoggedIn && $userInfo['role'] === 'admin'): ?>
                <a href="/admin/dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
            <?php endif; ?>
            <a href="status.php" class="btn btn-secondary">🔄 Refresh</a>
        </div>
        
        <!-- Auto-refresh notice -->
        <div class="auto-refresh">
            Last checked: <?php echo date('Y-m-d H:i:s'); ?> | 
            <a href="?auto=1" style="color: #667eea;">Enable Auto-refresh (30s)</a>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Food Ordering System v1.0 | Built with PHP & PostgreSQL
        </div>
    </div>
    
    <?php if (isset($_GET['auto'])): ?>
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => window.location.reload(), 30000);
    </script>
    <?php endif; ?>
</body>
</html>
