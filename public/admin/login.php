<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Updated database connection file path
require_once __DIR__ . '/../../app/Config/database.php';

// Fetch settings
$opts = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM options");
while ($row = $stmt->fetch()) {
    $opts[$row['setting_key']] = $row['setting_value'];
}
$enablePinLogin = ($opts['enable_pin_login'] ?? '0') === '1';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $enteredPin = trim($_POST['security_pin'] ?? '');

    if (!empty($usernameOrEmail) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if ($user) {
            $lockedUntil = $user['locked_until'] ?? null;
            $failedAttempts = (int)($user['failed_login_attempts'] ?? 0);
            $userPin = $user['security_pin'] ?? null;

            // Check Brute Force Lockout
            if ($lockedUntil && strtotime($lockedUntil) > time()) {
                $error = 'Account locked due to multiple failed attempts. Please use your Security PIN to recover or unlock.';
            } else {
                // Verify Security PIN if enabled
                $pinValid = !$enablePinLogin || ($userPin && password_verify($enteredPin, $userPin));

                if (password_verify($password, $user['password_hash']) && $pinValid) {
                    // Reset failed attempts on success
                    $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    header('Location: dashboard.php');
                    exit();
                } else {
                    // Increment failed attempts
                    $attempts = $failedAttempts + 1;
                    $lockoutQuery = "";
                    if ($attempts >= 5) {
                        $lockoutQuery = ", locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)";
                    }
                    $pdo->prepare("UPDATE users SET failed_login_attempts = ? {$lockoutQuery} WHERE id = ?")->execute([$attempts, $user['id']]);
                    
                    $error = 'Invalid credentials, PIN, or locked account.';
                }
            }
        } else {
            $error = 'Invalid credentials or inactive account.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <title>BLOGBUSTER — Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800 border border-slate-700/60 rounded-2xl p-8 shadow-2xl space-y-6">
        <div class="text-center space-y-2">
            <h1 class="text-xl font-bold text-white">Admin Control Panel</h1>
            <p class="text-xs text-slate-400">Sign in with credentials and security PIN</p>
        </div>

        <?php if ($error): ?>
            <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs text-center">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Username or Email</label>
                <input type="text" name="username" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 transition">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 transition">
            </div>
            <?php if ($enablePinLogin): ?>
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Permanent Security PIN</label>
                    <input type="password" name="security_pin" maxlength="6" required class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 transition">
                </div>
            <?php endif; ?>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition">Sign In</button>
            <div class="text-center">
                <a href="forgot-password.php" class="text-xs text-blue-400 hover:underline">Forgot password or locked out?</a>
            </div>
        </form>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
