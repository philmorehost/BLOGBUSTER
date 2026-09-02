<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Posts - BLOGBUSTER</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e293b; height: 100vh; padding: 1.5rem; box-sizing: border-box; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 0.75rem 0; border-bottom: 1px solid #334155; }
        .main { flex-grow: 1; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #38bdf8; }
        .btn { background: #0284c7; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:#38bdf8;">BLOGBUSTER</h2>
        <a href="/admin?action=dashboard">Dashboard</a>
        <a href="/admin?action=posts">Posts & Articles</a>
        <a href="/admin?action=edit_post">+ Add New Post</a>
        <a href="/admin?action=settings">System Settings</a>
        <a href="/admin?action=logout" style="color:#ef4444;">Logout</a>
    </div>
    <div class="main">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1>Posts & Articles</h1>
            <a href="/admin?action=edit_post" class="btn">+ Create Post</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td><?= htmlspecialchars($p['author_name'] ?? 'Admin') ?></td>
                    <td><?= htmlspecialchars($p['category_slug']) ?></td>
                    <td><?= ucfirst($p['status']) ?></td>
                    <td><a href="/admin?action=edit_post&id=<?= $p['id'] ?>" style="color:#38bdf8;">Edit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
