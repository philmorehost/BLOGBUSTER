<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin');
    exit;
}

require_once __DIR__ . '/../Modules/Security/BruteForceShield.php';
use App\Modules\Security\BruteForceShield;

$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$shield = new BruteForceShield($pdo);
$msg = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $settings = [
            'sec_user_protection_enabled' => $_POST['sec_user_protection_enabled'] ?? '0',
            'sec_user_period_mins'         => $_POST['sec_user_period_mins'] ?? '15',
            'sec_user_max_failures'        => $_POST['sec_user_max_failures'] ?? '5',
            'sec_user_lock_admin'          => $_POST['sec_user_lock_admin'] ?? '0',
            'sec_ip_protection_enabled'   => $_POST['sec_ip_protection_enabled'] ?? '0',
            'sec_ip_period_mins'           => $_POST['sec_ip_period_mins'] ?? '15',
            'sec_ip_max_failures'          => $_POST['sec_ip_max_failures'] ?? '5',
            'sec_ip_block_duration'        => $_POST['sec_ip_block_duration'] ?? '1_day',
            'sec_apply_scope'              => $_POST['sec_apply_scope'] ?? 'remote_local',
            'sec_history_retention_mins'   => $_POST['sec_history_retention_mins'] ?? '15',
            'sec_notify_unwhitelisted_ip'  => $_POST['sec_notify_unwhitelisted_ip'] ?? '0',
            'sec_notify_bruteforce_user'   => $_POST['sec_notify_bruteforce_user'] ?? '0',
            'sec_admin_email'              => $_POST['sec_admin_email'] ?? 'admin@site.com'
        ];

        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }
        $msg = "Security configuration updated successfully!";
    }

    if (isset($_POST['manual_ip_action'])) {
        $targetIp = trim($_POST['target_ip']);
        $action = $_POST['action_type']; // whitelist, blacklist, unblock

        if (filter_var($targetIp, FILTER_VALIDATE_IP)) {
            if ($action === 'whitelist') {
                $pdo->prepare("INSERT INTO sec_whitelisted_ips (ip_address, is_king) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_king = 1")->execute([$targetIp]);
                $pdo->prepare("DELETE FROM sec_blocked_ips WHERE ip_address = ?")->execute([$targetIp]);
            } elseif ($action === 'blacklist') {
                $shield->applyIPBlock($targetIp, '1_year', 'Manual Admin Blacklist');
                $pdo->prepare("DELETE FROM sec_whitelisted_ips WHERE ip_address = ?")->execute([$targetIp]);
            } elseif ($action === 'unblock') {
                $pdo->prepare("DELETE FROM sec_blocked_ips WHERE ip_address = ?")->execute([$targetIp]);
                $pdo->prepare("DELETE FROM sec_whitelisted_ips WHERE ip_address = ?")->execute([$targetIp]);
            }
            $msg = "Action successfully executed for IP: {$targetIp}";
        }
    }

    if (isset($_POST['update_country_rule'])) {
        $cCode = strtoupper(trim($_POST['country_code']));
        $cStatus = $_POST['country_status'];
        $pdo->prepare("INSERT INTO sec_country_rules (country_code, country_name, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?")
            ->execute([$cCode, $_POST['country_name'] ?? $cCode, $cStatus, $cStatus]);
        $msg = "Rule updated for Country: {$cCode}";
    }
}

// Fetch Current Options
$optsStmt = $pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key LIKE 'sec_%'");
$opts = $optsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Security Data
$whitelisted = $pdo->query("SELECT * FROM sec_whitelisted_ips ORDER BY created_at DESC")->fetchAll();
$blocked = $pdo->query("SELECT * FROM sec_blocked_ips WHERE blocked_until > NOW() ORDER BY created_at DESC")->fetchAll();
$logs = $pdo->query("SELECT * FROM sec_login_logs ORDER BY attempted_at DESC LIMIT 20")->fetchAll();
$countries = $pdo->query("SELECT * FROM sec_country_rules ORDER BY country_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>cPHulk & Imunify360 Security Center - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .king-badge { display: inline_flex; align-items: center; gap: 4px; background: #0284c7; color: white; padding: 2px 8px; border-radius: 9999px; font-weight: bold; font-size: 11px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">
    
    <div class="max-w-7xl mx-auto p-6 space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-3xl font-black text-sky-400">Security Protection Center</h1>
                <p class="text-slate-400 text-sm">cPHulk Brute Force & Imunify360 Hybrid Protection Suite</p>
            </div>
            <a href="/admin" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition">← Return to Dashboard</a>
        </header>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Grid 1: Main Policy Configurations -->
        <form method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <input type="hidden" name="save_settings" value="1">

            <!-- Username Based Protection -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-sky-400 border-b border-slate-800 pb-2">Username-Based Protection</h2>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="sec_user_protection_enabled" value="1" <?= ($opts['sec_user_protection_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-sky-500">
                    <span class="font-semibold">Enable Username-Based Protection</span>
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Protection Period (Mins)</label>
                        <input type="number" name="sec_user_period_mins" value="<?= htmlspecialchars($opts['sec_user_period_mins'] ?? '15') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Max Failures Before Lock</label>
                        <input type="number" name="sec_user_max_failures" value="<?= htmlspecialchars($opts['sec_user_max_failures'] ?? '5') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" name="sec_user_lock_admin" value="1" <?= ($opts['sec_user_lock_admin'] ?? '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-sky-500">
                    <span>Apply protection to "admin" & "administrator" accounts</span>
                </label>
            </div>

            <!-- IP Based Protection -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-sky-400 border-b border-slate-800 pb-2">IP Address-Based Protection</h2>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="sec_ip_protection_enabled" value="1" <?= ($opts['sec_ip_protection_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-sky-500">
                    <span class="font-semibold">Enable IP Address Protection</span>
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">IP Window Period (Mins)</label>
                        <input type="number" name="sec_ip_period_mins" value="<?= htmlspecialchars($opts['sec_ip_period_mins'] ?? '15') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Max Failures per IP</label>
                        <input type="number" name="sec_ip_max_failures" value="<?= htmlspecialchars($opts['sec_ip_max_failures'] ?? '5') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Firewall Block Duration</label>
                    <select name="sec_ip_block_duration" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                        <option value="1_day" <?= ($opts['sec_ip_block_duration'] ?? '') === '1_day' ? 'selected' : '' ?>>One-Day Block</option>
                        <option value="1_week" <?= ($opts['sec_ip_block_duration'] ?? '') === '1_week' ? 'selected' : '' ?>>One-Week Block</option>
                        <option value="1_month" <?= ($opts['sec_ip_block_duration'] ?? '') === '1_month' ? 'selected' : '' ?>>One-Month Block</option>
                        <option value="1_year" <?= ($opts['sec_ip_block_duration'] ?? '') === '1_year' ? 'selected' : '' ?>>One-Year Block</option>
                    </select>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-sky-500 hover:bg-sky-600 font-bold py-2.5 rounded-lg transition text-white">Save Protection Settings</button>
                </div>
            </div>
        </form>

        <!-- Manual IP Management & Whitelist -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-6">
            <h2 class="text-xl font-bold text-sky-400">Manual IP Management & King Whitelist</h2>
            
            <form method="POST" class="flex gap-4 items-end bg-slate-950 p-4 rounded-xl border border-slate-800">
                <input type="hidden" name="manual_ip_action" value="1">
                <div class="flex-1">
                    <label class="block text-xs text-slate-400 mb-1">Target IP Address</label>
                    <input type="text" name="target_ip" required placeholder="e.g. 192.168.1.100" class="w-full bg-slate-900 border border-slate-700 rounded p-2 text-white font-mono">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Action</label>
                    <select name="action_type" class="bg-slate-900 border border-slate-700 rounded p-2 text-white">
                        <option value="whitelist">Whitelist IP</option>
                        <option value="blacklist">Blacklist IP</option>
                        <option value="unblock">Remove Restrictions</option>
                    </select>
                </div>
                <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 font-bold px-6 py-2 rounded-lg transition">Apply IP Rule</button>
            </form>

            <!-- Whitelisted IPs List -->
            <div>
                <h3 class="font-bold text-sm text-slate-300 mb-3">Whitelisted IPs (5+ Successful Sessions earn King Status 👑)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php foreach ($whitelisted as $w): ?>
                        <div class="bg-slate-950 p-3 rounded-lg border border-slate-800 flex justify-between items-center">
                            <span class="font-mono text-sm"><?= htmlspecialchars($w['ip_address']) ?></span>
                            <?php if ($w['is_king']): ?>
                                <span class="king-badge">👑 King Whitelisted</span>
                            <?php else: ?>
                                <span class="text-xs bg-slate-800 px-2 py-0.5 rounded text-slate-400">Standard</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Country Rule Management with Live Search -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-sky-400">Country Access Rules</h2>
                <input type="text" id="countrySearch" onkeyup="filterCountries()" placeholder="Search country name or code..." class="bg-slate-950 border border-slate-700 rounded p-2 text-sm w-64">
            </div>

            <div class="overflow-y-auto max-h-72 border border-slate-800 rounded-lg">
                <table class="w-full text-left text-sm" id="countryTable">
                    <thead class="bg-slate-950 text-slate-400 sticky top-0">
                        <tr>
                            <th class="p-3">Country Code</th>
                            <th class="p-3">Country Name</th>
                            <th class="p-3">Status Rule</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php 
                        $allCountries = [
                            'US' => 'United States', 'NG' => 'Nigeria', 'GB' => 'United Kingdom', 
                            'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'CN' => 'China'
                        ];
                        foreach ($allCountries as $code => $name):
                            $cRule = array_values(array_filter($countries, fn($c) => $c['country_code'] === $code))[0]['status'] ?? 'not_specified';
                        ?>
                            <tr>
                                <td class="p-3 font-mono font-bold"><?= $code ?></td>
                                <td class="p-3"><?= $name ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-bold 
                                        <?= $cRule === 'whitelisted' ? 'bg-emerald-500/20 text-emerald-400' : ($cRule === 'blacklisted' ? 'bg-red-500/20 text-red-400' : 'bg-slate-800 text-slate-400') ?>">
                                        <?= strtoupper($cRule) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <form method="POST" class="flex gap-2">
                                        <input type="hidden" name="update_country_rule" value="1">
                                        <input type="hidden" name="country_code" value="<?= $code ?>">
                                        <input type="hidden" name="country_name" value="<?= $name ?>">
                                        <select name="country_status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 rounded text-xs p-1">
                                            <option value="not_specified" <?= $cRule === 'not_specified' ? 'selected' : '' ?>>Not Specified</option>
                                            <option value="whitelisted" <?= $cRule === 'whitelisted' ? 'selected' : '' ?>>Whitelist</option>
                                            <option value="blacklisted" <?= $cRule === 'blacklisted' ? 'selected' : '' ?>>Blacklist</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <script>
        function filterCountries() {
            let input = document.getElementById("countrySearch").value.toUpperCase();
            let rows = document.querySelectorAll("#countryTable tbody tr");
            rows.forEach(row => {
                let text = row.innerText.toUpperCase();
                row.style.display = text.indexOf(input) > -1 ? "" : "none";
            });
        }
    </script>
</body>
</html>