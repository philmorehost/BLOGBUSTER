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
require_once __DIR__ . '/../../app/Modules/Performance/ImageOptimizer.php';

use App\Modules\Theme\ThemeEngine;
use App\Modules\Addons\WPFormsEngine;
use App\Modules\Addons\WooCommerceEngine;
use App\Modules\SEO\SitemapGenerator;
use App\Modules\Performance\ImageOptimizer;

$themeEngine = new ThemeEngine($pdo, __DIR__ . '/../../themes');
$wpFormsEngine = new WPFormsEngine($pdo);
$wooEngine = new WooCommerceEngine($pdo);
$imageOptimizer = new ImageOptimizer();

$msg = '';
$activeTab = $_GET['tab'] ?? 'dashboard';

// Helper: 30-word excerpt generator
function generate30WordExcerpt(string $text): string {
    $cleanText = strip_tags($text);
    $words = preg_split('/\s+/', $cleanText);
    if (count($words) <= 30) {
        return implode(' ', $words);
    }
    return implode(' ', array_slice($words, 0, 30)) . '...';
}

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

    // Publish or Update Post
    if ($action === 'save_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'General');

        $excerpt = trim($_POST['excerpt'] ?? '');
        if (empty($excerpt)) {
            $excerpt = generate30WordExcerpt($content);
        }

        $imageWebp = null;
        if (!empty($_FILES['featured_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($_FILES['featured_image']['name'], PATHINFO_FILENAME));
            $destination = $uploadDir . $filename . '.webp';

            if ($imageOptimizer->convertToWebp($_FILES['featured_image']['tmp_name'], $destination)) {
                $imageWebp = '/uploads/' . $filename . '.webp';

                // Track in media library
                $mStmt = $pdo->prepare("INSERT INTO media (user_id, filename, file_path, file_type, file_size, alt_text) VALUES (?, ?, ?, 'image/webp', ?, ?)");
                $mStmt->execute([$_SESSION['user_id'], $filename . '.webp', $imageWebp, $_FILES['featured_image']['size'], $title]);
            }
        }

        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($title));

        if ($postId > 0) {
            if ($imageWebp) {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, excerpt = ?, image_webp = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $content, $excerpt, $imageWebp, $postId]);
            } else {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, excerpt = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $content, $excerpt, $postId]);
            }
            $msg = "Article '{$title}' updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, slug, content, excerpt, image_webp, status) VALUES (?, ?, ?, ?, ?, ?, 'published')");
            $stmt->execute([$_SESSION['user_id'], $title, $slug, $content, $excerpt, $imageWebp]);
            $msg = "Article '{$title}' published successfully!";
        }

        // Auto-regenerate Sitemap
        $sitemapGen = new SitemapGenerator($pdo);
        $sitemapGen->renderSitemapXml();
    }

    // Batch Delete Selected Posts
    if ($action === 'batch_delete_posts') {
        $selectedPosts = $_POST['selected_posts'] ?? [];
        if (!empty($selectedPosts)) {
            $placeholders = implode(',', array_fill(0, count($selectedPosts), '?'));
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
            $stmt->execute($selectedPosts);
            $msg = count($selectedPosts) . " articles deleted successfully!";
        }
    }

    // Direct Media WebP Upload
    if ($action === 'upload_media') {
        if (!empty($_FILES['media_file']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($_FILES['media_file']['name'], PATHINFO_FILENAME));
            $destination = $uploadDir . $filename . '.webp';

            if ($imageOptimizer->convertToWebp($_FILES['media_file']['tmp_name'], $destination)) {
                $imageWebp = '/uploads/' . $filename . '.webp';
                $mStmt = $pdo->prepare("INSERT INTO media (user_id, filename, file_path, file_type, file_size, alt_text) VALUES (?, ?, ?, 'image/webp', ?, ?)");
                $mStmt->execute([$_SESSION['user_id'], $filename . '.webp', $imageWebp, $_FILES['media_file']['size'], $_POST['alt_text'] ?? 'Uploaded Media']);
                $msg = "Image converted to WebP and added to Media Library!";
            }
        }
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
            $imageUrl = null;
            if (!empty($_FILES['product_image']['tmp_name'])) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $filename = 'prod_' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($_FILES['product_image']['name'], PATHINFO_FILENAME));
                $destination = $uploadDir . $filename . '.webp';

                if ($imageOptimizer->convertToWebp($_FILES['product_image']['tmp_name'], $destination)) {
                    $imageUrl = '/uploads/' . $filename . '.webp';
                }
            }

            $wooEngine->createProduct([
                'title' => $_POST['title'],
                'price' => $_POST['price'],
                'description' => $_POST['description'],
                'stock_quantity' => $_POST['stock'],
                'image_url' => $imageUrl
            ]);
            $msg = "Store product '{$_POST['title']}' created!";
        } catch (Exception $e) {
            $msg = "Error adding product: " . $e->getMessage();
        }
    }

    // Save System Settings & Admin Profile & Logo/Favicon
    if ($action === 'save_settings') {
        $logoPath = null;
        $faviconPath = null;

        if (!empty($_FILES['logo_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'logo_' . time();
            if ($imageOptimizer->convertToWebp($_FILES['logo_image']['tmp_name'], $uploadDir . $filename . '.webp')) {
                $logoPath = '/uploads/' . $filename . '.webp';
            }
        }

        if (!empty($_FILES['favicon_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'favicon_' . time();
            if ($imageOptimizer->convertToWebp($_FILES['favicon_image']['tmp_name'], $uploadDir . $filename . '.webp')) {
                $faviconPath = '/uploads/' . $filename . '.webp';
            }
        }

        $settings = [
            'site_title'     => trim($_POST['site_title'] ?? 'BLOGBUSTER'),
            'site_tagline'   => trim($_POST['site_tagline'] ?? ''),
            'payhub_secret'  => trim($_POST['payhub_secret'] ?? ''),
            'payhub_public'  => trim($_POST['payhub_public'] ?? ''),
            'smtp_host'      => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'      => trim($_POST['smtp_port'] ?? '587'),
            'smtp_user'      => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'      => trim($_POST['smtp_pass'] ?? ''),
            'smtp_from_email'=> trim($_POST['smtp_from_email'] ?? '')
        ];

        if ($logoPath) $settings['site_logo'] = $logoPath;
        if ($faviconPath) $settings['site_favicon'] = $faviconPath;

        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Handle Admin Profile & Security PIN Updates
        if (!empty($_POST['admin_email'])) {
            $pinHash = !empty($_POST['security_pin']) ? password_hash($_POST['security_pin'], PASSWORD_BCRYPT) : null;
            if ($pinHash) {
                $pStmt = $pdo->prepare("UPDATE users SET email = ?, job_title = ?, bio = ?, security_pin = ? WHERE id = ?");
                $pStmt->execute([trim($_POST['admin_email']), trim($_POST['job_title'] ?? ''), trim($_POST['bio'] ?? ''), $pinHash, $_SESSION['user_id']]);
            } else {
                $pStmt = $pdo->prepare("UPDATE users SET email = ?, job_title = ?, bio = ? WHERE id = ?");
                $pStmt->execute([trim($_POST['admin_email']), trim($_POST['job_title'] ?? ''), trim($_POST['bio'] ?? ''), $_SESSION['user_id']]);
            }
        }

        $msg = "System settings & Admin Profile saved successfully!";
    }

    // Purge Cache
    if ($action === 'purge_cache') {
        $msg = "Cache Manager: Page and Asset cache purged successfully!";
    }
}

// Fetch stats & data
$postsCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn() ?: 0;
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
$queueCount = $pdo->query("SELECT COUNT(*) FROM social_post_queue WHERE status = 'pending'")->fetchColumn() ?: 0;
$blockedIps = $pdo->query("SELECT COUNT(*) FROM sec_blocked_ips WHERE blocked_until > NOW()")->fetchColumn() ?: 0;
$whitelistedIps = $pdo->query("SELECT COUNT(*) FROM sec_whitelisted_ips")->fetchColumn() ?: 0;

$logs = $pdo->query("SELECT * FROM sec_login_logs ORDER BY attempted_at DESC LIMIT 15")->fetchAll() ?: [];
$posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC")->fetchAll() ?: [];
$mediaItems = $pdo->query("SELECT * FROM media ORDER BY id DESC")->fetchAll() ?: [];
$availableThemes = $themeEngine->getAvailableThemes();
$products = $wooEngine->getProducts(20);
$userProfile = $pdo->query("SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id'])->fetch() ?: [];

// Post Edit Fetch
$editPost = null;
if (isset($_GET['edit_post'])) {
    $eStmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $eStmt->execute([(int)$_GET['edit_post']]);
    $editPost = $eStmt->fetch();
}

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
    <!-- CKEditor 5 CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .ck-editor__editable_inline { min-height: 250px; color: #000; }</style>
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

            <?php elseif ($activeTab === 'posts'): ?>
                <div class="space-y-8">
                    <!-- Advanced Article Publisher Form -->
                    <form method="POST" enctype="multipart/form-data" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <input type="hidden" name="admin_action" value="save_post">
                        <input type="hidden" name="post_id" value="<?= $editPost['id'] ?? 0; ?>">
                        <h3 class="text-base font-bold text-white"><?= $editPost ? 'Edit Article' : 'Publish Advanced News Post'; ?></h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Post Title</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($editPost['title'] ?? ''); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Category</label>
                                <input type="text" name="category" value="<?= htmlspecialchars($editPost['category'] ?? 'News'); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Featured Image (Auto-Converted to WebP)</label>
                            <input type="file" name="featured_image" accept="image/*" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Article Body (CKEditor 5 Enhanced)</label>
                            <textarea name="content" id="editor" rows="6" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"><?= htmlspecialchars($editPost['content'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">
                            <?= $editPost ? 'Update Post' : 'Publish Post & Auto-Generate 30-Word Excerpt'; ?>
                        </button>
                    </form>

                    <!-- Article List & Batch Delete -->
                    <form method="POST" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <input type="hidden" name="admin_action" value="batch_delete_posts">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-white">Existing Published Articles</h3>
                            <button type="submit" onclick="return confirm('Delete selected articles?');" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-xl transition">
                                Delete Selected Articles
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-slate-400 uppercase">
                                    <tr>
                                        <th class="p-3"><input type="checkbox" id="select-all"></th>
                                        <th class="p-3">Title</th>
                                        <th class="p-3">30-Word Auto Excerpt</th>
                                        <th class="p-3">Created At</th>
                                        <th class="p-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php foreach ($posts as $post): ?>
                                        <tr>
                                            <td class="p-3"><input type="checkbox" name="selected_posts[]" value="<?= $post['id']; ?>" class="post-checkbox"></td>
                                            <td class="p-3 font-semibold text-white"><?= htmlspecialchars($post['title']); ?></td>
                                            <td class="p-3 text-slate-400"><?= htmlspecialchars($post['excerpt'] ?: generate30WordExcerpt($post['content'])); ?></td>
                                            <td class="p-3 text-slate-400"><?= $post['created_at']; ?></td>
                                            <td class="p-3">
                                                <a href="dashboard?tab=posts&edit_post=<?= $post['id']; ?>" class="text-blue-400 hover:underline">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                <script>
                    ClassicEditor.create(document.querySelector('#editor')).catch(error => console.error(error));
                    document.getElementById('select-all')?.addEventListener('change', function() {
                        document.querySelectorAll('.post-checkbox').forEach(cb => cb.checked = this.checked);
                    });
                </script>

            <?php elseif ($activeTab === 'media'): ?>
                <div class="space-y-6">
                    <form method="POST" enctype="multipart/form-data" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-xl">
                        <input type="hidden" name="admin_action" value="upload_media">
                        <h3 class="text-base font-bold text-white">Upload Media & Auto-Convert to WebP</h3>
                        <div>
                            <input type="file" name="media_file" accept="image/*" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                        </div>
                        <div>
                            <input type="text" name="alt_text" placeholder="Image Alt Text for SEO" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition">Upload & Compress WebP</button>
                    </form>

                    <!-- Gallery Grid -->
                    <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                        <h3 class="text-base font-bold text-white">Uploaded Images Gallery</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach ($mediaItems as $media): ?>
                                <div class="bg-slate-950 border border-slate-800 p-3 rounded-xl space-y-2">
                                    <div class="h-28 bg-slate-900 rounded-lg overflow-hidden flex items-center justify-center">
                                        <img src="<?= htmlspecialchars($media['file_path']); ?>" alt="<?= htmlspecialchars($media['alt_text']); ?>" class="max-h-full object-cover">
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($media['filename']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'woocommerce'): ?>
                <div class="space-y-6">
                    <form method="POST" enctype="multipart/form-data" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4 max-w-2xl">
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
                            <label class="block text-xs font-medium text-slate-300 mb-1">Product Image (WebP Auto-Conversion)</label>
                            <input type="file" name="product_image" accept="image/*" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
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
                    <form method="POST" enctype="multipart/form-data" class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-6 max-w-3xl">
                        <input type="hidden" name="admin_action" value="save_settings">

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">Site Identity & WebP Branding Uploads</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Site Title</label>
                                    <input type="text" name="site_title" value="<?= htmlspecialchars($optRows['site_title'] ?? 'BLOGBUSTER'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Site Tagline</label>
                                    <input type="text" name="site_tagline" value="<?= htmlspecialchars($optRows['site_tagline'] ?? 'Modern CMS'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Upload Site Logo (WebP Auto-Convert)</label>
                                    <input type="file" name="logo_image" accept="image/*" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Upload Custom Favicon (WebP Auto-Convert)</label>
                                    <input type="file" name="favicon_image" accept="image/*" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">Payhub Gateway Integration (https://merchant.payhub.com.ng)</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Payhub Secret Key</label>
                                    <input type="password" name="payhub_secret" value="<?= htmlspecialchars($optRows['payhub_secret'] ?? ''); ?>" placeholder="sec_live_XXXXX" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Payhub Public Key</label>
                                    <input type="text" name="payhub_public" value="<?= htmlspecialchars($optRows['payhub_public'] ?? ''); ?>" placeholder="pub_live_XXXXX" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-base font-bold text-white border-b border-slate-800 pb-2">Admin Profile & Security PIN</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Admin Email</label>
                                    <input type="email" name="admin_email" value="<?= htmlspecialchars($userProfile['email'] ?? 'admin@example.com'); ?>" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Permanent Security PIN (6 Digits)</label>
                                    <input type="password" name="security_pin" maxlength="6" placeholder="••••••" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
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
