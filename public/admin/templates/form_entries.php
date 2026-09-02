<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form Submissions - BLOGBUSTER</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e293b; height: 100vh; padding: 1.5rem; box-sizing: border-box; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 0.75rem 0; border-bottom: 1px solid #334155; }
        .main { flex-grow: 1; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #38bdf8; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:#38bdf8;">BLOGBUSTER</h2>
        <a href="/admin?action=dashboard">Dashboard</a>
        <a href="/admin?action=form_entries">Form Entries</a>
        <a href="/admin?action=settings">System Settings</a>
    </div>
    <div class="main">
        <h1>Form Submissions</h1>
        <table>
            <thead>
                <tr>
                    <th>Entry ID</th>
                    <th>Form Name</th>
                    <th>Submission Data</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td>#<?= $e['id'] ?></td>
                    <td><?= htmlspecialchars($e['form_title'] ?? 'Form #'.$e['form_id']) ?></td>
                    <td><code><?= htmlspecialchars($e['entry_data']) ?></code></td>
                    <td><?= $e['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
