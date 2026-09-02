<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Post - BLOGBUSTER</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e293b; height: 100vh; padding: 1.5rem; box-sizing: border-box; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 0.75rem 0; border-bottom: 1px solid #334155; }
        .main { flex-grow: 1; padding: 2rem; max-width: 900px; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; color: #94a3b8; }
        input, select, textarea { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid #334155; background: #1e293b; color: #fff; box-sizing: border-box; }
        textarea { height: 250px; }
        .btn { padding: 0.75rem 1.5rem; background: #0284c7; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-ai { background: #10b981; margin-left: 0.5rem; }
        .alert { background: #065f46; color: #a7f3d0; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        .ai-box { background: #111827; border: 1px solid #10b981; padding: 1rem; border-radius: 6px; margin-top: 1rem; display: none; }
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
        <h1><?= $post ? 'Edit Post' : 'Create New Post' ?></h1>
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <form method="POST" action="/admin?action=edit_post<?= $post ? '&id='.$post['id'] : '' ?>">
            <div class="form-group">
                <label>Post Title</label>
                <input type="text" id="post_title" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Slug (Leave empty for auto-generated)</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($post['slug'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category_slug" value="<?= htmlspecialchars($post['category_slug'] ?? 'general') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
            </div>
            <div class="form-group">
                <label>Content</label>
                <textarea id="post_content" name="content" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
            </div>
            <div>
                <button type="submit" class="btn">Save Post</button>
                <button type="button" id="btnAiSeo" class="btn btn-ai">✨ DeepSeek AI SEO Analysis</button>
            </div>
        </form>

        <div id="aiBox" class="ai-box">
            <h3 style="color:#10b981; margin-top:0;">🤖 DeepSeek SEO Suggestions</h3>
            <p><strong>Suggested Meta Title:</strong> <span id="metaTitle"></span></p>
            <p><strong>Meta Description:</strong> <span id="metaDesc"></span></p>
            <p><strong>Focus Keywords:</strong> <span id="focusKeywords"></span></p>
            <p><strong>AI Excerpt:</strong> <span id="aiExcerpt"></span></p>
        </div>
    </div>

    <script>
        document.getElementById('btnAiSeo').addEventListener('click', async () => {
            const title = document.getElementById('post_title').value;
            const content = document.getElementById('post_content').value;
            const aiBox = document.getElementById('aiBox');

            if (!title || !content) {
                alert('Please enter both Title and Content first.');
                return;
            }

            document.getElementById('btnAiSeo').innerText = 'Analyzing with DeepSeek...';

            try {
                const response = await fetch('/admin?action=ai_generate_seo', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({title, content})
                });
                const res = await response.json();

                if (res.success) {
                    document.getElementById('metaTitle').innerText = res.data.meta_title;
                    document.getElementById('metaDesc').innerText = res.data.meta_description;
                    document.getElementById('focusKeywords').innerText = res.data.focus_keywords;
                    document.getElementById('aiExcerpt').innerText = res.data.excerpt;
                    aiBox.style.display = 'block';
                } else {
                    alert('AI Error: ' + res.error);
                }
            } catch (err) {
                alert('Failed to connect to DeepSeek API endpoint.');
            } finally {
                document.getElementById('btnAiSeo').innerText = '✨ DeepSeek AI SEO Analysis';
            }
        });
    </script>
</body>
</html>
