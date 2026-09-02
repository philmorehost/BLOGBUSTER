<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../../app/Config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => trim($_POST['smtp_port'] ?? ''),
        'smtp_user' => trim($_POST['smtp_user'] ?? ''),
        'smtp_pass' => trim($_POST['smtp_pass'] ?? ''),
        'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
        'smtp_encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
        'enable_otp' => isset($_POST['enable_otp']) ? '1' : '0',
        'enable_pin_login' => isset($_POST['enable_pin_login']) ? '1' : '0',
        'enable_pin_reset' => isset($_POST['enable_pin_reset']) ? '1' : '0',
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    $success = "Settings updated successfully.";
}

// Fetch current options
$stmt = $pdo->query("SELECT setting_key, setting_value FROM options");
$opts = [];
while ($row = $stmt->fetch()) {
    $opts[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <title>BLOGBUSTER — Security & SMTP Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100 p-8">
    <div class="max-w-3xl mx-auto bg-slate-800 border border-slate-700/60 rounded-2xl p-8 shadow-2xl space-y-6">
        <div class="flex items-center justify-between border-b border-slate-700/60 pb-4">
            <h1 class="text-xl font-bold text-white flex items-center space-x-2">
                <i data-lucide="shield-alert" class="w-6 h-6 text-blue-400"></i>
                <span>Security, OTP & SMTP Configuration</span>
            </h1>
            <a href="dashboard.php" class="text-xs text-slate-400 hover:text-white">Back to Dashboard</a>
        </div>

        <?php if ($success): ?><div class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs"><?= $success; ?></div><?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- Security Toggles -->
            <div class="space-y-4">
                <h2 class="text-sm font-bold text-blue-400 uppercase tracking-wider">Authentication Security Rules</h2>
                <div class="grid grid-cols-1 gap-3">
                    <label class="flex items-center space-x-3 p-3 bg-slate-900/60 border border-slate-700/40 rounded-xl cursor-pointer">
                        <input type="checkbox" name="enable_otp" value="1" <?= ($opts['enable_otp'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 rounded text-blue-600">
                        <div>
                            <span class="text-sm font-medium text-white">Enable OTP for Password Reset</span>
                            <p class="text-xs text-slate-400">Sends a verification OTP via SMTP during password recovery.</p>
                        </div>
                    </label>

                    <label class="flex items-center space-x-3 p-3 bg-slate-900/60 border border-slate-700/40 rounded-xl cursor-pointer">
                        <input type="checkbox" name="enable_pin_login" value="1" <?= ($opts['enable_pin_login'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 rounded text-blue-600">
                        <div>
                            <span class="text-sm font-medium text-white">Require Security PIN on Login</span>
                            <p class="text-xs text-slate-400">Prompts admin for their permanent security PIN during sign in.</p>
                        </div>
                    </label>

                    <label class="flex items-center space-x-3 p-3 bg-slate-900/60 border border-slate-700/40 rounded-xl cursor-pointer">
                        <input type="checkbox" name="enable_pin_reset" value="1" <?= ($opts['enable_pin_reset'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 rounded text-blue-600">
                        <div>
                            <span class="text-sm font-medium text-white">Require Security PIN for Password Reset / Unlock</span>
                            <p class="text-xs text-slate-400">Ensures the permanent security PIN is required to complete password reset or unlock brute-force blocks.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- SMTP Settings -->
            <div class="space-y-4 pt-4 border-t border-slate-700/60">
                <h2 class="text-sm font-bold text-blue-400 uppercase tracking-wider">SMTP Server Settings</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($opts['smtp_host'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Port</label>
                        <input type="text" name="smtp_port" value="<?= htmlspecialchars($opts['smtp_port'] ?? '587'); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?= htmlspecialchars($opts['smtp_user'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Password</label>
                        <input type="password" name="smtp_pass" value="<?= htmlspecialchars($opts['smtp_pass'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Sender Email</label>
                        <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($opts['smtp_from_email'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Encryption Protocol</label>
                        <select name="smtp_encryption" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white">
                            <option value="tls" <?= ($opts['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?= ($opts['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?= ($opts['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium text-sm rounded-xl transition">Save Settings</button>
        </form>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>