<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Enforce Admin Authentication
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_suspended']) {
                $loginError = "Account is suspended due to security violations.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: /admin");
                exit;
            }
        } else {
            $shield->recordFailedLogin($username, $_SERVER['REMOTE_ADDR']);
            $loginError = "Invalid credentials. Attempt logged.";
        }
    }
    
    // Login Screen Render
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>BLOGBUSTER Admin Login</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-2xl">
            <h2 class="text-2xl font-extrabold text-sky-400 mb-6 text-center">CMS Login</h2>
            <?php if (!empty($loginError)): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-sm"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="login_action" value="1">
                <div>
                    <label class="block text-sm mb-1 text-slate-300">Username</label>
                    <input type="text" name="username" required class="w-full bg-slate-800 border border-slate-700 rounded p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-sm mb-1 text-slate-300">Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-800 border border-slate-700 rounded p-2.5 text-white">
                </div>
                <button type="submit" class="w-full bg-sky-500 hover:bg-sky-600 font-bold p-3 rounded-lg transition">Log In</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Action Processor: Create Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $title = $_POST['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $content = $_POST['content'];
    $category = $_POST['category'];
    
    $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, category, author_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $content, $category, $_SESSION['user_id']]);
    $msg = "Article published successfully!";
}

// Fetch Security Logs
$logsStmt = $pdo->query("SELECT * FROM sec_login_logs ORDER BY attempted_at DESC LIMIT 10");
$logs = $logsStmt->fetchAll();

// Fetch Posts
$postsCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-950 border-r border-slate-800 flex flex-col justify-between">
        <div class="p-6">
            <h1 class="text-xl font-extrabold text-sky-400 tracking-wider mb-8">BLOGBUSTER</h1>
            <nav class="space-y-3 font-semibold text-sm">
                <a href="/admin" class="block bg-sky-600/20 text-sky-400 p-2.5 rounded-lg border border-sky-500/30">Dashboard</a>
                <a href="#posts" class="block text-slate-400 hover:text-white p-2.5 rounded-lg transition">Posts (<?= $postsCount ?>)</a>
                <a href="#security" class="block text-slate-400 hover:text-white p-2.5 rounded-lg transition">Security Logs</a>
                <a href="/" target="_blank" class="block text-slate-400 hover:text-white p-2.5 rounded-lg transition">View Website ↗</a>
            </nav>
        </div>
        <div class="p-6 border-t border-slate-800">
            <div class="text-xs text-slate-500 mb-2">Logged in as: <span class="text-white font-bold"><?= htmlspecialchars($_SESSION['username']) ?></span></div>
            <a href="?logout=1" class="block text-center bg-red-500/20 hover:bg-red-500/30 text-red-300 font-bold p-2 rounded text-xs transition">Sign Out</a>
        </div>
    </aside>

    <!-- Main Administrative Workspace -->
    <main class="flex-1 p-8 overflow-y-auto space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <h2 class="text-2xl font-bold">Control Center</h2>
            <span class="bg-emerald-500/20 text-emerald-400 text-xs px-3 py-1.5 rounded-full border border-emerald-500/30 font-semibold">Security Shield Active</span>
        </header>

        <?php if (!empty($msg)): ?>
            <div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-4 rounded-lg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Post Creator Form -->
        <section id="posts" class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-md space-y-4">
            <h3 class="text-lg font-bold text-sky-400">Publish New Article</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="create_post" value="1">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Article Title</label>
                        <input type="text" name="title" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5 text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Category</label>
                        <select name="category" class="w-full bg-slate-900 border border-slate-700 rounded p-2.5 text-white">
                            <option value="Technology">Technology</option>
                            <option value="Business">Business</option>
                            <option value="World">World</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Article Content (HTML Supported)</label>
                    <textarea name="content" rows="5" required class="w-full bg-slate-900 border border-slate-700 rounded p-2.5 text-white font-mono text-sm"></textarea>
                </div>
                <button type="submit" class="bg-sky-500 hover:bg-sky-600 font-bold px-6 py-2.5 rounded-lg transition text-white">Publish Article</button>
            </form>
        </section>

        <!-- Security Inspector Table -->
        <section id="security" class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-md space-y-4">
            <h3 class="text-lg font-bold text-sky-400">cPHulk & Imunify360 Protection Audit Logs</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="p-3">IP Address</th>
                            <th class="p-3">Target Username</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="p-3 font-mono"><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($log['username']) ?></td>
                                <td class="p-3">
                                    <span class="bg-red-500/20 text-red-400 px-2 py-0.5 rounded text-xs font-bold border border-red-500/30"><?= strtoupper($log['status']) ?></span>
                                </td>
                                <td class="p-3 text-xs text-slate-400"><?= $log['attempted_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</body>
</html>