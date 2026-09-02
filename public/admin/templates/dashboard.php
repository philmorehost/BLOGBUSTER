<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BLOGBUSTER Dashboard</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e293b; height: 100vh; padding: 1.5rem; box-sizing: border-box; }
        .sidebar h2 { color: #38bdf8; margin-top: 0; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 0.75rem 0; font-size: 1rem; border-bottom: 1px solid #334155; }
        .sidebar a:hover { color: #38bdf8; }
        .main { flex-grow: 1; padding: 2rem; overflow-y: auto; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: #1e293b; padding: 1.5rem; border-radius: 8px; text-align: center; }
        .card h3 { font-size: 2.5rem; margin: 0.5rem 0; color: #38bdf8; }
        .card p { color: #94a3b8; margin: 0; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #38bdf8; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>BLOGBUSTER</h2>
        <a href="/admin?action=dashboard">Dashboard</a>
        <a href="/admin?action=posts">Posts & Articles</a>
        <a href="/admin?action=edit_post">+ Add New Post</a>
        <a href="/admin?action=orders">Orders</a>
        <a href="/admin?action=form_entries">Form Entries</a>
        <a href="/admin?action=settings">System Settings</a>
        <a href="/admin?action=logout" style="color:#ef4444;">Logout</a>
    </div>
    <div class="main">
        <h1>Dashboard Overview</h1>
        <div class="grid">
            <div class="card">
                <h3><?= $postCount ?></h3>
                <p>Total Articles</p>
            </div>
            <div class="card">
                <h3><?= $orderCount ?></h3>
                <p>Orders Processed</p>
            </div>
            <div class="card">
                <h3><?= $entryCount ?></h3>
                <p>Form Submissions</p>
            </div>
        </div>

        <h2>Recent Articles</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPosts as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><a href="/admin?action=edit_post&id=<?= $p['id'] ?>" style="color:#38bdf8; text-decoration:none;"><?= htmlspecialchars($p['title']) ?></a></td>
                    <td><?= ucfirst($p['status']) ?></td>
                    <td><?= $p['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
