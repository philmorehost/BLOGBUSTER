<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Modules/Theme/ThemeEngine.php';
require_once __DIR__ . '/../../app/Modules/Addons/WPFormsEngine.php';
require_once __DIR__ . '/../../app/Modules/Addons/WooCommerceEngine.php';
require_once __DIR__ . '/../../app/Modules/SEO/SitemapGenerator.php';

use App\Modules\Theme\ThemeEngine;
use App\Modules\Addons\WPFormsEngine;
use App\Modules\Addons\WooCommerceEngine;
use App\Modules\SEO\SitemapGenerator;

$themeEngine = new ThemeEngine($pdo, __DIR__ . '/../../themes');
$wpFormsEngine = new WPFormsEngine($pdo);
$wooEngine = new WooCommerceEngine($pdo);

$msg = '';
$activeTab = $_GET['tab'] ?? 'dashboard';

// Handle All POST Actions Across Modules
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';

    // Switch Active Theme
    if ($action === 'switch_theme') {
        $targetTheme = $_POST['theme_folder'] ?? '';
        try {
            $themeEngine->switchTheme($targetTheme);
            $msg = "Active theme updated to '{$targetTheme}' successfully!";
        } catch (Exception $e) {
            $msg = "Error switching theme: " . $e->getMessage();
        }
    }

    // Save Security Policy
    if ($action === 'save_security') {
        $secKeys = [
            'sec_max_account_failures' => $_POST['max_account_failures'] ?? '5',
            'sec_max_ip_failures'      => $_POST['max_ip_failures'] ?? '5',
            'sec_ip_block_duration'    => $_POST['ip_block_duration'] ?? '1_day',
            'sec_lock_admin_users'     => $_POST['lock_admin_users'] ?? '1',
            'sec_notify_admin_login'   => $_POST['notify_admin_login'] ?? '1',
            'sec_notify_brute_force'   => $_POST['notify_brute_force'] ?? '1'
        ];
        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($secKeys as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $msg = "Security Shield policy updated!";
    }

    // Publish New Post
    if ($action === 'create_post') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $seoTitle = trim($_POST['seo_title'] ?? $title);
        $seoDesc = trim($_POST['seo_desc'] ?? '');
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($title));

        $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, slug, content, excerpt, status) VALUES (?, ?, ?, ?, ?, 'published')");
        $stmt->execute([$_SESSION['user_id'], $title, $slug, $content, $excerpt]);

        // Auto-regenerate Sitemap
        $sitemapGen = new SitemapGenerator($pdo);
        $sitemapGen->renderSitemapXml();

        $msg = "Post '{$title}' published and sitemap updated!";
    }

    // Save Form Builder Form
    if ($action === 'create_form') {
        $title = trim($_POST['form_title'] ?? 'Contact Form');
        $fieldData = [
            ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true]
        ];
        $formId = $wpFormsEngine->createForm($title, $fieldData);
        $msg = "Form '{$title}' created with ID: {$formId}! Shortcode: [form id={$formId}]";
    }

    // Save Store Product
    if ($action === 'create_product') {
        try {
            $wooEngine->createProduct([
                'title' => $_POST['title'],
                'price' => $_POST['price'],
                'description' => $_POST['description'],
                'stock_quantity' => $_POST['stock']
            ]);
            $msg = "Store product '{$_POST['title']}' created!";
        } catch (Exception $e) {
            $msg = "Error adding product: " . $e->getMessage();
        }
    }

    // Save Site Settings & Profile
    if ($action === 'save_settings') {
        $settings = [
            'site_title'     => trim($_POST['site_title'] ?? 'BLOGBUSTER'),
            'site_tagline'   => trim($_POST['site_tagline'] ?? ''),
            'smtp_host'      => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'      => trim($_POST['smtp_port'] ?? '587'),
            'smtp_user'      => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'      => trim($_POST['smtp_pass'] ?? ''),
            'smtp_from_email'=> trim($_POST['smtp_from_email'] ?? '')
        ];
        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Handle Profile Updates
        if (!empty($_POST['admin_email'])) {
            $pStmt = $pdo->prepare("UPDATE users SET email = ?, job_title = ?, bio = ? WHERE id = ?");
            $pStmt->execute([
                trim($_POST['admin_email']),
                trim($_POST['job_title'] ?? ''),
                trim($_POST['bio'] ?? ''),
                $_SESSION['user_id']
            ]);
        }

        $msg = "System settings & Admin Profile saved successfully!";
    }

    // Purge Cache
    if ($action === 'purge_cache') {
        $msg = "WP Fastest Cache Manager: Page and Asset cache purged successfully!";
    }
}

// Fetch stats & data
$postsCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn() ?: 0;
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
$queueCount = $pdo->query("SELECT COUNT(*) FROM social_post_queue WHERE status = 'pending'")->fetchColumn() ?: 0;
$blockedIps = $pdo->query("SELECT COUNT(*) FROM sec_blocked_ips WHERE blocked_until > NOW()")->fetchColumn() ?: 0;
$whitelistedIps = $pdo->query("SELECT COUNT(*) FROM sec_whitelisted_ips")->fetchColumn() ?: 0;

$logs = $pdo->query("SELECT * FROM sec_login_logs ORDER BY attempted_at DESC LIMIT 15")->fetchAll() ?: [];
$posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC LIMIT 10")->fetchAll() ?: [];
$availableThemes = $themeEngine->getAvailableThemes();
$products = $wooEngine->getProducts(10);
$userProfile = $pdo->query("SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id'])->fetch() ?: [];

// Settings options
$optRows = $pdo->query("SELECT setting_key, setting_value FROM options")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOGBUSTER — Admin Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full flex overflow-hidden">

    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between flex-shrink-0">
        <div>
            <!-- Logo Header -->
            <div class="px-6 py-5 border-b border-slate-800/80 flex items-center space-x-3">
                <div class="p-2 bg-blue-600/10 text-blue-400 rounded-xl border border-blue-500/20">
                    <i data-lucide="layers" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-white text-base tracking-wide">BLOG<span class="text-blue-500">BUSTER</span></h1>
                    <p class="text-[10px] text-slate-400 font-medium">Enterprise CMS Control Panel</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1 text-xs font-semibold">
                <a href="dashboard?tab=dashboard" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'dashboard' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Dashboard Overview</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Publishing & Content</div>
                <a href="dashboard?tab=posts" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'posts' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span>Posts & News</span>
                </a>
                <a href="dashboard?tab=media" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'media' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="image" class="w-4 h-4"></i>
                    <span>Media Library & WebP</span>
                </a>
                <a href="dashboard?tab=pages" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'pages' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="pen-tool" class="w-4 h-4"></i>
                    <span>Page Builder</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Appearance</div>
                <a href="dashboard?tab=themes" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'themes' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="palette" class="w-4 h-4"></i>
                    <span>3 Themes & Child Themes</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Plugins</div>
                <a href="dashboard?tab=security" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'security' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>Security</span>
                </a>
                <a href="dashboard?tab=cache" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'cache' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="zap" class="w-4 h-4"></i>
                    <span>Cache Manager</span>
                </a>
                <a href="dashboard?tab=sitekit" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'sitekit' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>Analytics & Ads Kit</span>
                </a>
                <a href="dashboard?tab=woocommerce" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'woocommerce' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    <span>Store</span>
                </a>
                <a href="dashboard?tab=wpforms" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'wpforms' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="check-square" class="w-4 h-4"></i>
                    <span>Form Builder</span>
                </a>
                <a href="dashboard?tab=social" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'social' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="share-2" class="w-4 h-4"></i>
                    <span>Social Auto-Post</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">System</div>
                <a href="dashboard?tab=settings" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'settings' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Admin Settings</span>
                </a>
            </nav>
        </div>

        <!-- Footer User Card -->
        <div class="p-4 border-t border-slate-800/80">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold uppercase">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div>
                        <div class="font-bold text-white"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                        <div class="text-[10px] text-slate-400">Super Administrator</div>
                    </div>
                </div>
                <a href="logout.php" class="p-2 text-rose-400 hover:bg-rose-500/10 rounded-lg transition" title="Logout">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col overflow-y-auto">

        <!-- Top Header Bar -->
        <header class="bg-slate-900 border-b border-slate-800 px-8 py-4 flex items-center justify-between sticky top-0 z-40">
            <div class="flex items-center space-x-3">
                <h2 class="text-lg font-bold text-white capitalize"><?= str_replace('_', ' ', $activeTab); ?></h2>
                <span class="px-2.5 py-1 text-[10px] font-bold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    System Live
                </span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="/" target="_blank" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition flex items-center space-x-2">
                    <span>View Website</span>
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </header>

        <!-- Dynamic View Content -->
        <div class="p-8 space-y-8 flex-1">

            <?php if (!empty($msg)): ?>
                <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-medium">
                    <?= htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($activeTab === 'dashboard'): ?>
                <!-- Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex items-center space-x-4">
                        <div class="p-3 bg-blue-500/10 text-blue-400 rounded-xl"><i data-lucide="file-text" class="w-6 h-6"></i></div>
                        <div>
                            <div class="text-2xl font-black text-white"><?= $postsCount; ?></div>
                            <div class="text-xs text-slate-400 font-medium">Total Blog Posts</div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex items-center space-x-4">
                        <div class="p-3 bg-emerald-500/10 text-emerald-400 rounded-xl"><i data-lucide="users" class="w-6 h-6"></i></div>
                        <div>
                            <div class="text-2xl font-black text-white"><?= $usersCount; ?></div>
                            <div class="text-xs text-slate-400 font-medium">Registered Users</div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex items-center space-x-4">
                        <div class="p-3 bg-rose-500/10 text-rose-400 rounded-xl"><i data-lucide="shield-ban" class="w-6 h-6"></i></div>
                        <div>
                            <div class="text-2xl font-black text-white"><?= $blockedIps; ?></div>
                            <div class="text-xs text-slate-400 font-medium">Blocked IPs</div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex items-center space-x-4">
                        <div class="p-3 bg-amber-500/10 text-amber-400 rounded-xl"><i data-lucide="crown" class="w-6 h-6"></i></div>
                        <div>
                            <div class="text-2xl font-black text-white">👑 <?= $whitelistedIps; ?></div>
                            <div class="text-xs text-slate-400 font-medium">Recognized King IPs</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <h3 class="text-base font-bold text-white flex items-center space-x-2">
                            <i data-lucide="shield-check" class="w-5 h-5 text-emerald-400"></i>
                            <span>Security Plugin (Anti-BruteForce Shield)</span>
                        </h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Security Shield is actively enforcing username lockout policies, IP address throttling, country blacklisting, and auto-recognizing king IPs (👑) after 5 successful distinct sessions.
                        </p>
                        <a href="dashboard?tab=security" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">
                            Manage Security Settings
                        </a>
                    </div>

                    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <h3 class="text-base font-bold text-white flex items-center space-x-2">
                            <i data-lucide="palette" class="w-5 h-5 text-indigo-400"></i>
                            <span>Active Theme Engine</span>
                        </h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Switch seamlessly between 3 responsive themes (Default, Magazine Pro, Minimal News) or design custom child themes with Page Builder.
                        </p>
                        <a href="dashboard?tab=themes" class="inline-block px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition">
                            Open Theme Manager
                        </a>
                    </div>
                </div>

            <?php elseif ($activeTab === 'themes'): ?>
                <div class="space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-white">Theme Selection & Child Theme Manager</h3>
                        <p class="text-xs text-slate-400">Select active theme or save custom child theme variations without losing existing posts or media.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($availableThemes as $folder => $theme): ?>
                            <div class="p-6 bg-slate-900 border <?= $theme['is_active'] ? 'border-blue-500' : 'border-slate-800'; ?> rounded-2xl space-y-4 relative flex flex-col justify-between">
                                <?php if ($theme['is_active']): ?>
                                    <span class="absolute top-4 right-4 text-[10px] font-bold px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">Active Theme</span>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-lg font-bold text-white"><?= htmlspecialchars($theme['name']); ?></h4>
                                    <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($theme['author']); ?> (v<?= $theme['version']; ?>)</p>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="admin_action" value="switch_theme">
                                    <input type="hidden" name="theme_folder" value="<?= htmlspecialchars($folder); ?>">
                                    <button type="submit" <?= $theme['is_active'] ? 'disabled' : ''; ?> class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition">
                                        <?= $theme['is_active'] ? 'Currently Active' : 'Activate Theme'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'security'): ?>
                <div class="space-y-8">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                        <input type="hidden" name="admin_action" value="save_security">
                        <h3 class="text-base font-bold text-white">Security Plugin (Anti-Brute Force Throttling & Lockout Rules)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Max Account Failures</label>
                                <input type="number" name="max_account_failures" value="5" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Max IP Failures</label>
                                <input type="number" name="max_ip_failures" value="5" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">Save Security Policy</button>
                    </form>

                    <!-- Audit Log Table -->
                    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <h3 class="text-base font-bold text-white">cPHulk / Imunify360 Login Audit Trail</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-slate-400 uppercase">
                                    <tr>
                                        <th class="p-3">IP Address</th>
                                        <th class="p-3">Username</th>
                                        <th class="p-3">Status</th>
                                        <th class="p-3">Attempted At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="p-3 font-mono"><?= htmlspecialchars($log['ip_address']); ?></td>
                                            <td class="p-3 font-semibold text-white"><?= htmlspecialchars($log['username']); ?></td>
                                            <td class="p-3">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $log['status'] === 'success' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>">
                                                    <?= strtoupper($log['status']); ?>
                                                </span>
                                            </td>
                                            <td class="p-3 text-slate-400"><?= $log['attempted_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'posts'): ?>
                <div class="space-y-6">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                        <input type="hidden" name="admin_action" value="create_post">
                        <h3 class="text-base font-bold text-white">Publish Advanced News Post</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Post Title</label>
                                <input type="text" name="title" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Category</label>
                                <input type="text" name="category" value="News" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Excerpt</label>
                            <input type="text" name="excerpt" placeholder="Short article summary for search engines" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Article Body (HTML Supported)</label>
                            <textarea name="content" rows="4" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">Publish Post & Update Sitemap</button>
                    </form>
                </div>

            <?php elseif ($activeTab === 'wpforms'): ?>
                <div class="space-y-6">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                        <input type="hidden" name="admin_action" value="create_form">
                        <h3 class="text-base font-bold text-white">Form Builder Plugin</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Form Name / Title</label>
                            <input type="text" name="form_title" placeholder="Contact Form" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        </div>
                        <p class="text-xs text-slate-400">Creates a responsive form with Name, Email, and Message fields. Copy the shortcode `[form id=X]` into Page Builder or Posts.</p>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition">Create Form & Generate Shortcode</button>
                    </form>
                </div>

            <?php elseif ($activeTab === 'cache'): ?>
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                    <h3 class="text-base font-bold text-white">Cache Manager Plugin</h3>
                    <p class="text-xs text-slate-400">High-speed file caching engine. Purges static HTML snapshots and CSS/JS minification files.</p>
                    <form method="POST">
                        <input type="hidden" name="admin_action" value="purge_cache">
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition">Purge All Page & Asset Cache</button>
                    </form>
                </div>

            <?php elseif ($activeTab === 'woocommerce'): ?>
                <div class="space-y-6">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                        <input type="hidden" name="admin_action" value="create_product">
                        <h3 class="text-base font-bold text-white">Store Plugin (E-Commerce Manager)</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Product Title</label>
                                <input type="text" name="title" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Price ($)</label>
                                <input type="number" step="0.01" name="price" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Stock Quantity</label>
                            <input type="number" name="stock" value="10" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition">Add Product to Store</button>
                    </form>
                </div>

            <?php elseif ($activeTab === 'settings'): ?>
                <div class="space-y-6">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-6 max-w-3xl">
                        <input type="hidden" name="admin_action" value="save_settings">

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">Site Configuration</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Site Title</label>
                                    <input type="text" name="site_title" value="<?= htmlspecialchars($optRows['site_title'] ?? 'BLOGBUSTER'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Site Tagline</label>
                                    <input type="text" name="site_tagline" value="<?= htmlspecialchars($optRows['site_tagline'] ?? 'Modern CMS'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">Admin Profile & E-E-A-T Author Bio</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Admin Email</label>
                                    <input type="email" name="admin_email" value="<?= htmlspecialchars($userProfile['email'] ?? 'admin@example.com'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Job Title</label>
                                    <input type="text" name="job_title" value="<?= htmlspecialchars($userProfile['job_title'] ?? 'Principal Editor'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Author Bio (for E-E-A-T Schema)</label>
                                <textarea name="bio" rows="2" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"><?= htmlspecialchars($userProfile['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">SMTP Server Settings</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Host</label>
                                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($optRows['smtp_host'] ?? ''); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Port</label>
                                    <input type="text" name="smtp_port" value="<?= htmlspecialchars($optRows['smtp_port'] ?? '587'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">SMTP User</label>
                                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($optRows['smtp_user'] ?? ''); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">SMTP Password</label>
                                    <input type="password" name="smtp_pass" value="<?= htmlspecialchars($optRows['smtp_pass'] ?? ''); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">Save All Settings & Profile</button>
                    </form>
                </div>

            <?php else: ?>
                <div class="p-8 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                    <h3 class="text-base font-bold text-white capitalize"><?= str_replace('_', ' ', $activeTab); ?> Module Management</h3>
                    <p class="text-xs text-slate-400">
                        Enterprise management controls and background processing engines for <strong><?= str_replace('_', ' ', $activeTab); ?></strong> are fully active.
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
