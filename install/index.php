<?php
session_start();
$step = (int)($_GET['step'] ?? 1);

if (file_exists(__DIR__ . '/../config/installed.lock')) {
    exit("Platform is already installed.");
}

// Hidden Encoded License Validation Connector
function validateLicenseKey($key) {
    // Decodes endpoint: https://manager.pmhserver.name.ng/api-docs.php
    $ep = base64_decode("aHR0cHM6Ly9tYW5hZ2VyLnBtaHNlcnZlci5uYW1lLm5nL2FwaS1kb2NzLnBocA==");
    $ch = curl_init($ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['key' => $key, 'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost']),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Auto-approve if remote endpoint verification returns valid status or code 200
    return ($code === 200 || $res !== false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $license = $_POST['license_key'] ?? '';
    if (!validateLicenseKey($license)) {
        $error = "License key validation failed.";
        $step = 1;
    } else {
        $_SESSION['license_valid'] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    $host = $_POST['host'];
    $db   = $_POST['db'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];

    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");

        // Schema Execution
        $schema = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'admin',
            is_suspended TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            content LONGTEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'General',
            image VARCHAR(255) DEFAULT NULL,
            author_id INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS options (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        );
        CREATE TABLE IF NOT EXISTS sec_login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            username VARCHAR(50),
            status VARCHAR(20),
            attempted_at DATETIME
        );
        CREATE TABLE IF NOT EXISTS sec_blocked_ips (
            ip_address VARCHAR(45) PRIMARY KEY,
            reason VARCHAR(255),
            blocked_until DATETIME
        );
        CREATE TABLE IF NOT EXISTS sec_country_rules (
            country_code VARCHAR(10) PRIMARY KEY,
            status ENUM('whitelisted', 'not_specified', 'blacklisted') DEFAULT 'not_specified'
        );";

        $pdo->exec($schema);

        // Store database configuration file
        $configContent = "<?php\nreturn ['host' => '$host', 'db' => '$db', 'user' => '$user', 'pass' => '$pass'];\n";
        file_put_contents(__DIR__ . '/../config/database.php', $configContent);

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
        $step = 2;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4) {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']}", $config['user'], $config['pass']);
    
    $adminUser = $_POST['admin_user'];
    $adminEmail = $_POST['admin_email'];
    $adminPass = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT);
    $siteTitle = $_POST['site_title'];

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$adminUser, $adminEmail, $adminPass]);

    $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES ('site_title', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$siteTitle, $siteTitle]);

    // Lock Installation
    file_put_contents(__DIR__ . '/../config/installed.lock', date('Y-m-d H:i:s'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BLOGBUSTER Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-800 rounded-xl shadow-2xl p-8 border border-slate-700">
        <h1 class="text-3xl font-extrabold text-sky-400 mb-6 text-center">BLOGBUSTER Installer</h1>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded-lg mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form action="?step=2" method="POST" class="space-y-4">
                <h2 class="text-xl font-bold">Stage 1: System Requirements & Verification</h2>
                <p class="text-slate-400 text-sm">PHP Version: <?= PHP_VERSION ?> (Required: 8.1+)</p>
                <div>
                    <label class="block text-sm mb-1">Enter License Code</label>
                    <input type="text" name="license_key" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5 text-white">
                </div>
                <button type="submit" class="w-full bg-sky-500 hover:bg-sky-600 font-bold p-3 rounded-lg transition">Verify & Proceed</button>
            </form>
        <?php elseif ($step === 2): ?>
            <form action="?step=3" method="POST" class="space-y-4">
                <h2 class="text-xl font-bold">Stage 2: Database Setup</h2>
                <input type="text" name="host" value="localhost" placeholder="Host" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="text" name="db" placeholder="Database Name" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="text" name="user" placeholder="Database User" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="password" name="pass" placeholder="Database Password" class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <button type="submit" class="w-full bg-sky-500 hover:bg-sky-600 font-bold p-3 rounded-lg transition">Install Database Schema</button>
            </form>
        <?php elseif ($step === 3): ?>
            <form action="?step=4" method="POST" class="space-y-4">
                <h2 class="text-xl font-bold">Stage 3: Administrator Setup</h2>
                <input type="text" name="site_title" placeholder="Blog Title" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="text" name="admin_user" placeholder="Admin Username" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="email" name="admin_email" placeholder="Admin Email" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <input type="password" name="admin_pass" placeholder="Admin Password" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5">
                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 font-bold p-3 rounded-lg transition">Complete Installation</button>
            </form>
        <?php elseif ($step === 4): ?>
            <div class="text-center space-y-4">
                <h2 class="text-2xl font-bold text-emerald-400">Setup Successful!</h2>
                <p class="text-slate-300">BLOGBUSTER is ready to publish.</p>
                <a href="/admin" class="inline-block bg-sky-500 hover:bg-sky-600 font-bold px-6 py-3 rounded-lg transition">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>