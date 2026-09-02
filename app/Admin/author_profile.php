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

$userId = $_SESSION['user_id'];
$msg = '';

// Ensure required E-E-A-T profile columns exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN job_title VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN social_urls TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN credentials TEXT DEFAULT NULL");
} catch (PDOException $e) {
    // Columns already exist
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_author_profile'])) {
    $jobTitle = trim($_POST['job_title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $socialUrls = trim($_POST['social_urls'] ?? '');
    $credentials = trim($_POST['credentials'] ?? '');

    $stmt = $pdo->prepare("UPDATE users SET bio = ?, job_title = ?, social_urls = ?, credentials = ? WHERE id = ?");
    $stmt->execute([$bio, $jobTitle, $socialUrls, $credentials, $userId]);
    $msg = "Author E-E-A-T profile successfully saved!";
}

// Fetch Current User Profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>E-E-A-T Author Profile - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

    <div class="max-w-4xl mx-auto p-6 space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-3xl font-black text-indigo-400">Author E-E-A-T Profile Manager</h1>
                <p class="text-slate-400 text-sm">Configure Google Search & AI Engine E-E-A-T Author Credentials</p>
            </div>
            <a href="/admin" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition">← Return to Dashboard</a>
        </header>

        <?php if ($msg): ?>
            <div class="bg-indigo-500/20 border border-indigo-500 text-indigo-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST" class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-6 shadow-lg">
            <input type="hidden" name="save_author_profile" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Username (Read-Only)</label>
                    <input type="text" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled class="w-full bg-slate-950 border border-slate-800 rounded p-2 text-slate-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Professional Job Title / Expertise Focus</label>
                    <input type="text" name="job_title" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>" placeholder="e.g. Senior Tech Journalist & AI Analyst" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Author Bio (Used in JSON-LD & Author Pages)</label>
                <textarea name="bio" rows="4" placeholder="Write a detailed professional bio highlighting experience and editorial authority..." class="w-full bg-slate-800 border border-slate-700 rounded p-3 text-white text-sm"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Social Profile URLs (Comma-Separated for Schema sameAs)</label>
                <input type="text" name="social_urls" value="<?= htmlspecialchars($user['social_urls'] ?? '') ?>" placeholder="https://twitter.com/username, https://linkedin.com/in/username" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white font-mono text-xs">
                <span class="text-xs text-slate-500">Injects 'sameAs' attributes into Person JSON-LD schemas for verified authority mapping.</span>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Certifications & Experience Credentials</label>
                <input type="text" name="credentials" value="<?= htmlspecialchars($user['credentials'] ?? '') ?>" placeholder="e.g. B.Sc. Computer Science, 10+ Years Web Publishing" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white text-sm">
            </div>

            <button type="submit" class="w-full bg-indigo-500 hover:bg-indigo-600 font-bold py-3 rounded-lg transition text-white">Save Author E-E-A-T Credentials</button>
        </form>

    </div>

</body>
</html>