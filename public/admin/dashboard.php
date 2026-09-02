<?php
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../../app/Config/database.php';

// Fetch quick stats from database
$postsCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$queueCount = $pdo->query("SELECT COUNT(*) FROM social_post_queue WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLOGBUSTER — Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full text-slate-100">

    <!-- Top Navigation -->
    <nav class="bg-slate-950/80 border-b border-slate-800 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="p-2 bg-blue-600/10 text-blue-400 rounded-lg border border-blue-500/20">
                <i data-lucide="layers" class="w-5 h-5"></i>
            </div>
            <span class="font-bold text-white tracking-wide">BLOGBUSTER Admin</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-xs text-slate-400">Logged in as: <strong class="text-white"><?= htmlspecialchars($_SESSION['username']); ?></strong></span>
            <a href="logout.php" class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 rounded-lg text-xs font-medium transition">Logout</a>
        </div>
    </nav>

    <!-- Content Area -->
    <main class="max-w-6xl mx-auto py-8 px-6 space-y-6">
        <h1 class="text-2xl font-bold text-white">System Overview</h1>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl flex items-center space-x-4">
                <div class="p-3 bg-blue-500/10 text-blue-400 rounded-xl"><i data-lucide="file-text" class="w-6 h-6"></i></div>
                <div>
                    <div class="text-2xl font-bold text-white"><?= $postsCount; ?></div>
                    <div class="text-xs text-slate-400">Total Blog Posts</div>
                </div>
            </div>

            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl flex items-center space-x-4">
                <div class="p-3 bg-emerald-500/10 text-emerald-400 rounded-xl"><i data-lucide="users" class="w-6 h-6"></i></div>
                <div>
                    <div class="text-2xl font-bold text-white"><?= $usersCount; ?></div>
                    <div class="text-xs text-slate-400">Registered Users</div>
                </div>
            </div>

            <div class="bg-slate-800 border border-slate-700/60 p-6 rounded-2xl flex items-center space-x-4">
                <div class="p-3 bg-amber-500/10 text-amber-400 rounded-xl"><i data-lucide="send" class="w-6 h-6"></i></div>
                <div>
                    <div class="text-2xl font-bold text-white"><?= $queueCount; ?></div>
                    <div class="text-xs text-slate-400">Pending Social Queue</div>
                </div>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>