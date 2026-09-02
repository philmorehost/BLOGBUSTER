<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin');
    exit;
}

require_once __DIR__ . '/../Modules/Performance/PageCache.php';
require_once __DIR__ . '/../Modules/Performance/ImageOptimizer.php';

use App\Modules\Performance\PageCache;
use App\Modules\Performance\ImageOptimizer;

$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$msg = '';
$pageCache = new PageCache($pdo);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_perf_settings'])) {
        $settings = [
            'perf_cache_enabled' => $_POST['perf_cache_enabled'] ?? '0',
            'perf_cache_ttl'     => $_POST['perf_cache_ttl'] ?? '3600',
            'perf_defer_js'      => $_POST['perf_defer_js'] ?? '0',
            'perf_quality_webp'  => $_POST['perf_quality_webp'] ?? '82',
            'perf_quality_avif'  => $_POST['perf_quality_avif'] ?? '75'
        ];

        $stmt = $pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }
        $msg = "Performance settings successfully saved!";
    }

    if (isset($_POST['flush_cache'])) {
        $flushedCount = $pageCache->flushAllCache();
        $msg = "Successfully flushed {$flushedCount} cached static HTML pages!";
    }

    if (isset($_FILES['test_image'])) {
        try {
            $optimizer = new ImageOptimizer(
                __DIR__ . '/../../content/uploads/',
                (int)($_POST['perf_quality_webp'] ?? 82),
                (int)($_POST['perf_quality_avif'] ?? 75)
            );
            $result = $optimizer->processUpload($_FILES['test_image']);
            $msg = "Test Image Processed Successfully! WebP: " . ($result['webp'] ? 'Generated' : 'Failed/N/A') . " | AVIF: " . ($result['avif'] ? 'Generated' : 'Failed/N/A');
        } catch (Exception $e) {
            $msg = "Error processing image: " . $e->getMessage();
        }
    }
}

// Load Current Settings
$optsStmt = $pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key LIKE 'perf_%'");
$opts = $optsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Check System Image Library Support
$hasImagick = extension_loaded('imagick');
$hasGd = extension_loaded('gd');
$hasWebp = function_exists('imagewebp') || $hasImagick;
$hasAvif = function_exists('imageavif') || ($hasImagick && in_array('AVIF', (new \Imagick())->queryFormats()));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance & Caching Center - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

    <div class="max-w-7xl mx-auto p-6 space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-3xl font-black text-amber-400">Performance & Caching Center</h1>
                <p class="text-slate-400 text-sm">Static HTML Caching, Defer JS Optimization & GD/Imagick WebP/AVIF Pipeline</p>
            </div>
            <a href="/admin" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition">← Return to Dashboard</a>
        </header>

        <?php if ($msg): ?>
            <div class="bg-amber-500/20 border border-amber-500 text-amber-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Server Environment Health -->
        <section class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 class="text-lg font-bold text-amber-400 mb-4">Server Image Conversion Engine Status</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-slate-950 p-4 rounded-lg border border-slate-800">
                    <span class="text-xs text-slate-400 block">Imagick Extension</span>
                    <span class="text-lg font-bold <?= $hasImagick ? 'text-emerald-400' : 'text-red-400' ?>"><?= $hasImagick ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <div class="bg-slate-950 p-4 rounded-lg border border-slate-800">
                    <span class="text-xs text-slate-400 block">GD Extension</span>
                    <span class="text-lg font-bold <?= $hasGd ? 'text-emerald-400' : 'text-red-400' ?>"><?= $hasGd ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <div class="bg-slate-950 p-4 rounded-lg border border-slate-800">
                    <span class="text-xs text-slate-400 block">WebP Support</span>
                    <span class="text-lg font-bold <?= $hasWebp ? 'text-emerald-400' : 'text-red-400' ?>"><?= $hasWebp ? 'Supported' : 'Missing' ?></span>
                </div>
                <div class="bg-slate-950 p-4 rounded-lg border border-slate-800">
                    <span class="text-xs text-slate-400 block">AVIF Support</span>
                    <span class="text-lg font-bold <?= $hasAvif ? 'text-emerald-400' : 'text-red-400' ?>"><?= $hasAvif ? 'Supported' : 'Missing' ?></span>
                </div>
            </div>
        </section>

        <!-- Main Settings Form -->
        <form method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <input type="hidden" name="save_perf_settings" value="1">

            <!-- HTML Page Caching & Script Optimization -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-amber-400 border-b border-slate-800 pb-2">Page Caching & JS Optimization</h2>
                
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="perf_cache_enabled" value="1" <?= ($opts['perf_cache_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-amber-500">
                    <span class="font-semibold">Enable Disk HTML Page Caching</span>
                </label>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Cache Lifetime TTL (Seconds)</label>
                    <input type="number" name="perf_cache_ttl" value="<?= htmlspecialchars($opts['perf_cache_ttl'] ?? '3600') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    <span class="text-xs text-slate-500">3600 = 1 Hour, 86400 = 1 Day</span>
                </div>

                <label class="flex items-center gap-3 pt-2">
                    <input type="checkbox" name="perf_defer_js" value="1" <?= ($opts['perf_defer_js'] ?? '1') === '1' ? 'checked' : '' ?> class="w-5 h-5 accent-amber-500">
                    <div>
                        <span class="font-semibold block">Automatic JavaScript Deferral</span>
                        <span class="text-xs text-slate-400">Injects 'defer' attribute into all external and inline scripts to eliminate render-blocking JS</span>
                    </div>
                </label>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 font-bold py-2.5 rounded-lg transition text-slate-950">Save Performance Settings</button>
                </div>
            </div>

            <!-- Image Compression Pipeline Settings -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-amber-400 border-b border-slate-800 pb-2">WebP & AVIF Compression Pipeline</h2>
                
                <div>
                    <label class="block text-xs text-slate-400 mb-1">WebP Quality Ratio (1 - 100)</label>
                    <input type="number" name="perf_quality_webp" min="1" max="100" value="<?= htmlspecialchars($opts['perf_quality_webp'] ?? '82') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    <span class="text-xs text-slate-500">Recommended: 80 - 85 for optimal balance between speed and clarity</span>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">AVIF Quality Ratio (1 - 100)</label>
                    <input type="number" name="perf_quality_avif" min="1" max="100" value="<?= htmlspecialchars($opts['perf_quality_avif'] ?? '75') ?>" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    <span class="text-xs text-slate-500">Recommended: 70 - 78 for ultra-high compression efficiency</span>
                </div>
            </div>
        </form>

        <!-- Actions: Cache Flush & Image Optimizer Testing -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
                <h3 class="font-bold text-amber-400">Purge Static HTML Cache</h3>
                <p class="text-xs text-slate-400">Clear all generated static HTML cached pages from disk immediately.</p>
                <form method="POST">
                    <button type="submit" name="flush_cache" value="1" class="bg-red-500 hover:bg-red-600 text-white font-bold px-6 py-2 rounded-lg transition">Flush Cache Directory Now</button>
                </form>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
                <h3 class="font-bold text-amber-400">Test Image Converter Upload</h3>
                <p class="text-xs text-slate-400">Upload an image file (JPG/PNG) to test immediate WebP and AVIF generation.</p>
                <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                    <input type="file" name="test_image" required accept="image/*" class="bg-slate-950 border border-slate-700 text-xs rounded p-2 text-slate-300 flex-1">
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-lg text-sm transition">Test Conversion</button>
                </form>
            </div>
        </section>

    </div>

</body>
</html>