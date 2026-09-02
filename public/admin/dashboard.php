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

use App\Modules\Theme\ThemeEngine;
use App\Modules\Addons\WPFormsEngine;
use App\Modules\Addons\WooCommerceEngine;

$themeEngine = new ThemeEngine($pdo, __DIR__ . '/../../themes');
$wpFormsEngine = new WPFormsEngine($pdo);
$wooEngine = new WooCommerceEngine($pdo);

$msg = '';
$activeTab = $_GET['tab'] ?? 'dashboard';

// Handle POST actions across admin tabs
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

    // Save Anti-BruteForce Security Settings
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
        $msg = "Anti-Brute Force security parameters updated successfully!";
    }

    // Add Post
    if ($action === 'create_post') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($title));

        $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, slug, content, status) VALUES (?, ?, ?, ?, 'published')");
        $stmt->execute([$_SESSION['user_id'], $title, $slug, $content]);
        $msg = "Post '{$title}' published successfully!";
    }

    // Add WooCommerce Product
    if ($action === 'create_product') {
        try {
            $wooEngine->createProduct([
                'title' => $_POST['title'],
                'price' => $_POST['price'],
                'description' => $_POST['description'],
                'stock_quantity' => $_POST['stock']
            ]);
            $msg = "Store product '{$_POST['title']}' created successfully!";
        } catch (Exception $e) {
            $msg = "Error adding product: " . $e->getMessage();
        }
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
                    <span>Posts & Articles</span>
                </a>
                <a href="dashboard?tab=media" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'media' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="image" class="w-4 h-4"></i>
                    <span>Media Library & WebP</span>
                </a>
                <a href="dashboard?tab=pages" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'pages' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="pen-tool" class="w-4 h-4"></i>
                    <span>Elementor Page Builder</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Themes & Appearance</div>
                <a href="dashboard?tab=themes" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'themes' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="palette" class="w-4 h-4"></i>
                    <span>3 Themes & Child Themes</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Plugins & Extensions</div>
                <a href="dashboard?tab=security" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'security' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>Anti-Brute Force Shield</span>
                </a>
                <a href="dashboard?tab=cache" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'cache' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="zap" class="w-4 h-4"></i>
                    <span>WP Fastest Cache & Speed</span>
                </a>
                <a href="dashboard?tab=sitekit" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'sitekit' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>Google SiteKit Analytics</span>
                </a>
                <a href="dashboard?tab=woocommerce" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'woocommerce' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    <span>WooCommerce Store</span>
                </a>
                <a href="dashboard?tab=wpforms" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'wpforms' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="check-square" class="w-4 h-4"></i>
                    <span>WPForms Builder</span>
                </a>
                <a href="dashboard?tab=social" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl transition <?= $activeTab === 'social' ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'; ?>">
                    <i data-lucide="share-2" class="w-4 h-4"></i>
                    <span>Jetpack Social Auto-Post</span>
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
                            <span>cPHulk & Imunify360 Anti-BruteForce Status</span>
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
                            Switch seamlessly between 3 responsive themes (Default, Magazine Pro, Minimal News) or design custom child themes with Elementor page builder.
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
                        <h3 class="text-base font-bold text-white">Anti-Brute Force Throttling & Lockout Rules</h3>
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
                        <h3 class="text-base font-bold text-white">Login Audit Trail</h3>
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
                        <h3 class="text-base font-bold text-white">Publish New Article</h3>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Title</label>
                            <input type="text" name="title" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Content</label>
                            <textarea name="content" rows="4" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">Publish Post</button>
                    </form>
                </div>

            <?php elseif ($activeTab === 'woocommerce'): ?>
                <div class="space-y-6">
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
                        <input type="hidden" name="admin_action" value="create_product">
                        <h3 class="text-base font-bold text-white">Add WooCommerce Product</h3>
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
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition">Add Product</button>
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
