<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - BLOGBUSTER</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e293b; height: 100vh; padding: 1.5rem; box-sizing: border-box; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 0.75rem 0; border-bottom: 1px solid #334155; }
        .main { flex-grow: 1; padding: 2rem; max-width: 800px; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; color: #94a3b8; }
        input, select { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid #334155; background: #1e293b; color: #fff; box-sizing: border-box; }
        .btn { padding: 0.75rem 1.5rem; background: #0284c7; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .alert { background: #065f46; color: #a7f3d0; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        h2 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 0.5rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:#38bdf8;">BLOGBUSTER</h2>
        <a href="/admin?action=dashboard">Dashboard</a>
        <a href="/admin?action=posts">Posts & Articles</a>
        <a href="/admin?action=settings">System Settings</a>
    </div>
    <div class="main">
        <h1>System Settings</h1>
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <form method="POST" action="/admin?action=settings">
            <h2>General Site Settings</h2>
            <div class="form-group">
                <label>Site Title</label>
                <input type="text" name="site_title" value="<?= htmlspecialchars($options['site_title'] ?? 'BLOGBUSTER') ?>" required>
            </div>
            <div class="form-group">
                <label>Site Tagline</label>
                <input type="text" name="site_tagline" value="<?= htmlspecialchars($options['site_tagline'] ?? 'CMS Platform') ?>">
            </div>
            <div class="form-group">
                <label>Site URL</label>
                <input type="url" name="site_url" value="<?= htmlspecialchars($options['site_url'] ?? 'http://localhost') ?>" required>
            </div>
            <div class="form-group">
                <label>Active Theme</label>
                <input type="text" name="active_theme" value="<?= htmlspecialchars($options['active_theme'] ?? 'blogbuster-default') ?>">
            </div>

            <h2>DeepSeek AI Intelligence Integration</h2>
            <div class="form-group">
                <label>DeepSeek API Key</label>
                <input type="password" name="deepseek_api_key" value="<?= htmlspecialchars($options['deepseek_api_key'] ?? '') ?>" placeholder="sk-****************">
            </div>
            <div class="form-group">
                <label>DeepSeek Model</label>
                <select name="deepseek_model">
                    <option value="deepseek-chat" <?= ($options['deepseek_model'] ?? '') === 'deepseek-chat' ? 'selected' : '' ?>>deepseek-chat</option>
                    <option value="deepseek-coder" <?= ($options['deepseek_model'] ?? '') === 'deepseek-coder' ? 'selected' : '' ?>>deepseek-coder</option>
                </select>
            </div>

            <h2>Caching & Performance (Redis)</h2>
            <div class="form-group">
                <label>Redis Host</label>
                <input type="text" name="redis_host" value="<?= htmlspecialchars($options['redis_host'] ?? '127.0.0.1') ?>">
            </div>
            <div class="form-group">
                <label>Redis Port</label>
                <input type="number" name="redis_port" value="<?= htmlspecialchars($options['redis_port'] ?? '6379') ?>">
            </div>

            <button type="submit" class="btn">Save All Settings</button>
        </form>
    </div>
</body>
</html>
