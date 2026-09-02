<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin');
    exit;
}

$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

require_once __DIR__ . '/../Modules/Theme/ThemeEngine.php';
use App\Modules\Theme\ThemeEngine;

$themesDir = __DIR__ . '/../../themes';
if (!is_dir($themesDir)) {
    mkdir($themesDir, 0755, true);
}

$themeEngine = new ThemeEngine($pdo, $themesDir);
$msg = '';
$error = '';

// Handle theme switching request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_theme'])) {
    $themeFolder = trim($_POST['theme_folder'] ?? '');
    try {
        if ($themeEngine->switchTheme($themeFolder)) {
            $msg = "Theme successfully switched to '{$themeFolder}' without data loss.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$availableThemes = $themeEngine->getAvailableThemes();
$activeThemeKey = $themeEngine->getActiveTheme();
$parentThemeKey = $themeEngine->getParentTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Theme Switcher & Child Engine - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

    <div class="max-w-6xl mx-auto p-6 space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-3xl font-black text-indigo-400">Theme Switcher & Child Engine</h1>
                <p class="text-slate-400 text-sm">Manage Active Themes, Parent-Child Overrides, and Appearance Modes</p>
            </div>
            <a href="/admin" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition">← Return to Dashboard</a>
        </header>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-500/20 border border-rose-500 text-rose-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Active Theme Info Card -->
        <div class="bg-slate-900 border border-indigo-500/40 rounded-xl p-6 shadow-lg">
            <h2 class="text-xs uppercase tracking-wider font-bold text-indigo-400 mb-2">Currently Active Theme State</h2>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold"><?= htmlspecialchars($availableThemes[$activeThemeKey]['name'] ?? $activeThemeKey) ?></h3>
                    <p class="text-slate-400 text-sm font-mono mt-1">Directory: themes/<?= htmlspecialchars($activeThemeKey) ?></p>
                </div>
                <div>
                    <?php if ($parentThemeKey): ?>
                        <span class="bg-indigo-900/60 text-indigo-300 border border-indigo-700 px-3 py-1.5 rounded-full text-xs font-semibold">
                            Child Theme (Inherits from: <?= htmlspecialchars($parentThemeKey) ?>)
                        </span>
                    <?php else: ?>
                        <span class="bg-emerald-900/60 text-emerald-300 border border-emerald-700 px-3 py-1.5 rounded-full text-xs font-semibold">
                            Standalone / Parent Theme
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Available Themes Grid -->
        <div>
            <h2 class="text-xl font-bold mb-4">Installed Theme Library</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($availableThemes as $folder => $theme): ?>
                    <div class="bg-slate-900 border <?= $theme['is_active'] ? 'border-indigo-500 ring-2 ring-indigo-500/30' : 'border-slate-800' ?> rounded-xl overflow-hidden flex flex-col justify-between p-5">
                        <div class="space-y-3">
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-bold"><?= htmlspecialchars($theme['name']) ?></h3>
                                <span class="text-xs bg-slate-800 text-slate-400 px-2 py-1 rounded">v<?= htmlspecialchars($theme['version']) ?></span>
                            </div>
                            <p class="text-xs text-slate-400">Author: <span class="text-slate-200"><?= htmlspecialchars($theme['author']) ?></span></p>
                            
                            <?php if ($theme['parent']): ?>
                                <p class="text-xs text-indigo-400 font-mono">Parent: <?= htmlspecialchars($theme['parent']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-800 flex justify-between items-center">
                            <?php if ($theme['is_active']): ?>
                                <span class="text-emerald-400 text-xs font-bold uppercase tracking-wider">Active Engine</span>
                            <?php else: ?>
                                <form method="POST" class="w-full">
                                    <input type="hidden" name="activate_theme" value="1">
                                    <input type="hidden" name="theme_folder" value="<?= htmlspecialchars($folder) ?>">
                                    <button type="submit" class="w-full bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-200 font-bold py-2 rounded-lg text-xs transition">
                                        Activate Theme
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</body>
</html>