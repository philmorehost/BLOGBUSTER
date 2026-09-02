<?php
// Increase execution timeout and memory for migration
set_time_limit(300);
ini_set('memory_limit', '256M');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$dbHost = trim($_POST['db_host'] ?? '');
$dbName = trim($_POST['db_name'] ?? '');
$dbUser = trim($_POST['db_user'] ?? '');
$dbPass = $_POST['db_pass'] ?? '';

$adminUser  = trim($_POST['admin_user'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');
$adminPass  = $_POST['admin_pass'] ?? '';

$schemaFile = __DIR__ . '/../schema.sql';
$configDir  = __DIR__ . '/../app/Config';
$configFile = $configDir . '/database.php';

function renderError($message) {
    echo "<!DOCTYPE html><html class='h-full bg-slate-900'><head><script src='https://cdn.tailwindcss.com'></script></head><body class='h-full flex items-center justify-center p-4'>";
    echo "<div class='max-w-md w-full bg-slate-800 border border-slate-700/60 rounded-2xl p-6 text-center space-y-4 shadow-2xl'>";
    echo "<div class='w-12 h-12 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl flex items-center justify-center mx-auto'><svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'></path></svg></div>";
    echo "<h2 class='text-lg font-bold text-white'>Installation Failed</h2>";
    echo "<p class='text-slate-300 text-xs'>" . htmlspecialchars($message) . "</p>";
    echo "<a href='index.php' class='inline-block px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-medium text-sm rounded-xl transition'>Try Again</a>";
    echo "</div></body></html>";
    exit();
}

if (!file_exists($schemaFile)) {
    renderError("Database migration file (schema.sql) is missing from project root.");
}

try {
    // 1. Establish MySQL PDO Connection
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30
    ]);

    // 2. Read schema.sql and split into individual queries to prevent 503 timeouts
    $rawSql = file_get_contents($schemaFile);
    
    // Remove comments
    $rawSql = preg_replace('/--.*$/m', '', $rawSql);
    $rawSql = preg_replace('/\/\*.*?\*\//s', '', $rawSql);

    // Split queries by semicolon
    $queries = array_filter(array_map('trim', explode(';', $rawSql)));

    // Run each query individually
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }

    // 3. Create Administrator User Account (Check if user exists first to prevent duplicate key error)
    $passwordHash = password_hash($adminPass, PASSWORD_BCRYPT);
    
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $checkUser->execute([$adminEmail, $adminUser]);
    
    if ($checkUser->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute([$adminUser, $adminEmail, $passwordHash]);
    }

    // 4. Create App Config Directory
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    // 5. Generate app/Config/database.php File
    $configContent = "<?php\n"
        . "// Auto-generated configuration file via Web Installer\n"
        . "\$dbHost = " . var_export($dbHost, true) . ";\n"
        . "\$dbName = " . var_export($dbName, true) . ";\n"
        . "\$dbUser = " . var_export($dbUser, true) . ";\n"
        . "\$dbPass = " . var_export($dbPass, true) . ";\n\n"
        . "try {\n"
        . "    \$pdo = new PDO(\"mysql:host=\$dbHost;dbname=\$dbName;charset=utf8mb4\", \$dbUser, \$dbPass, [\n"
        . "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
        . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC\n"
        . "    ]);\n"
        . "} catch (PDOException \$e) {\n"
        . "    die('Database connection failed: ' . \$e->getMessage());\n"
        . "}\n";

    file_put_contents($configFile, $configContent);

} catch (Exception $e) {
    renderError($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOGBUSTER — Setup Complete</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800/90 border border-slate-700/60 rounded-2xl shadow-2xl backdrop-blur-xl p-8 text-center space-y-6">
        <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center mx-auto">
            <i data-lucide="check-circle-2" class="w-8 h-8"></i>
        </div>
        
        <div>
            <h1 class="text-xl font-bold text-white">Installation Complete!</h1>
            <p class="text-xs text-slate-400 mt-1">BLOGBUSTER is successfully configured and ready to use.</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-700/40 text-left text-xs space-y-2">
            <div class="flex items-center justify-between text-slate-300">
                <span>Database Status:</span>
                <span class="font-bold text-emerald-400">Tables Migrated</span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
                <span>Admin Username:</span>
                <span class="font-bold text-white"><?= htmlspecialchars($adminUser); ?></span>
            </div>
            <div class="flex items-center justify-between text-slate-300">
                <span>Config File:</span>
                <span class="font-bold text-blue-400">app/Config/database.php</span>
            </div>
        </div>

        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-amber-300 text-xs flex items-center space-x-2 text-left">
            <i data-lucide="alert-triangle" class="w-6 h-6 flex-shrink-0"></i>
            <span><strong>Security Action:</strong> Delete or rename the <code>/install</code> folder immediately.</span>
        </div>

        <a href="../public/admin/dashboard.php" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition flex items-center justify-center space-x-2">
            <span>Go to Admin Control Panel</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>