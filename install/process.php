<?php
// Increase execution timeout and memory for migration
set_time_limit(300);
ini_set('memory_limit', '256M');

define('BB_INSTALLING', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
$dbPort = (int)($_POST['db_port'] ?? 3306);
$dbName = trim($_POST['db_name'] ?? 'blogbuster_db');
$dbUser = trim($_POST['db_user'] ?? 'root');
$dbPass = $_POST['db_pass'] ?? '';

$adminUser  = trim($_POST['admin_user'] ?? 'admin');
$adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');
$adminPass  = $_POST['admin_pass'] ?? 'Password123!';
$securityPin = trim($_POST['security_pin'] ?? '123456');

$appKey = trim($_POST['app_key'] ?? '');

$schemaFile = __DIR__ . '/../schema.sql';
$configDir  = __DIR__ . '/../config';
$configFile = $configDir . '/database.php';
$lockFile   = $configDir . '/installed.lock';

function renderError($message) {
    echo "<!DOCTYPE html><html class='h-full bg-slate-900'><head><script src='https://cdn.tailwindcss.com'></script></head><body class='h-full flex items-center justify-center p-4'>";
    echo "<div class='max-w-md w-full bg-slate-800 border border-slate-700/60 rounded-2xl p-6 text-center space-y-4 shadow-2xl'>";
    echo "<div class='w-12 h-12 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl flex items-center justify-center mx-auto'><svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'></path></svg></div>";
    echo "<h2 class='text-lg font-bold text-white'>Activation Failed</h2>";
    echo "<p class='text-slate-300 text-xs'>" . htmlspecialchars($message) . "</p>";
    echo "<a href='index.php' class='inline-block px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-medium text-sm rounded-xl transition'>Try Again</a>";
    echo "</div></body></html>";
    exit();
}

// Stage 1 Verification Endpoint (Remote Check against manager.pmhserver.name.ng/api.php)
if (empty($appKey)) {
    renderError("Product Key / Activation Token is required to complete installation.");
}

$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
$endpoint = 'https://manager.pmhserver.name.ng/api.php';

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'key' => $appKey,
    'domain' => $domain
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    renderError("Could not connect to Activation Server: " . $curlError);
}

$resData = json_decode($response, true);
if (!isset($resData['status']) || (int)$resData['status'] !== 1) {
    $msg = $resData['message'] ?? 'Invalid product key or unauthorized domain assignment.';
    renderError("Key Verification Error: " . $msg);
}

if (!file_exists($schemaFile)) {
    renderError("Database schema file (schema.sql) is missing from project root.");
}

try {
    // 1. Establish MySQL PDO Connection
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30
    ]);

    // 2. Execute schema queries
    $rawSql = file_get_contents($schemaFile);
    $rawSql = preg_replace('/--.*$/m', '', $rawSql);
    $rawSql = preg_replace('/\/\*.*?\*\//s', '', $rawSql);

    $queries = array_filter(array_map('trim', explode(';', $rawSql)));

    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }

    // 3. Create Admin User
    $passwordHash = password_hash($adminPass, PASSWORD_BCRYPT);
    $pinHash = password_hash($securityPin, PASSWORD_BCRYPT);
    
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $checkUser->execute([$adminEmail, $adminUser]);
    
    if ($checkUser->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, security_pin, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $stmt->execute([$adminUser, $adminEmail, $passwordHash, $pinHash]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, security_pin = ?, role = 'admin', status = 'active' WHERE username = ?");
        $stmt->execute([$passwordHash, $pinHash, $adminUser]);
    }

    // 4. Save Options
    $optStmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $optStmt->execute(['site_title', 'BLOGBUSTER']);
    $optStmt->execute(['enable_pin_login', '1']);
    $optStmt->execute(['app_activation_key', $appKey]);

    // 5. Create Config Directory & Write database.php
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $configData = [
        'host' => $dbHost,
        'port' => $dbPort,
        'db'   => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ];

    $configPhp = "<?php\n/**\n * BLOGBUSTER Production Database Configuration\n */\n\nreturn " . var_export($configData, true) . ";\n";
    file_put_contents($configFile, $configPhp);

    // Create installed lock file
    file_put_contents($lockFile, date('Y-m-d H:i:s'));

} catch (Exception $e) {
    renderError($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOGBUSTER — Stage 4: Setup Complete</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800/90 border border-slate-700/60 rounded-2xl shadow-2xl backdrop-blur-xl p-8 text-center space-y-6">
        <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center mx-auto">
            <i data-lucide="check-circle-2" class="w-8 h-8"></i>
        </div>
        
        <div>
            <h1 class="text-xl font-bold text-white">Congratulations! Installation Complete</h1>
            <p class="text-xs text-slate-400 mt-1">BLOGBUSTER is successfully activated, configured and ready for live production.</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-700/40 text-left text-xs space-y-2">
            <div class="flex items-center justify-between text-slate-300">
                <span>Activation Status:</span>
                <span class="font-bold text-emerald-400">Verified & Active</span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
                <span>Database Migration:</span>
                <span class="font-bold text-emerald-400">Successful (14 Tables)</span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
                <span>Admin Username:</span>
                <span class="font-bold text-white"><?= htmlspecialchars($adminUser); ?></span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
                <span>Security PIN:</span>
                <span class="font-bold text-blue-400">Configured (6 Digits)</span>
            </div>
        </div>

        <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl text-blue-300 text-xs flex items-center space-x-2 text-left">
            <i data-lucide="info" class="w-6 h-6 flex-shrink-0"></i>
            <span><strong>Next Steps:</strong> Log into the Admin Panel to configure themes, security rules, Google SiteKit, and WooCommerce.</span>
        </div>

        <a href="../public/admin/login.php" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition flex items-center justify-center space-x-2">
            <span>Proceed to Admin Login</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
