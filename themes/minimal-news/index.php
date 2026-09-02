<!DOCTYPE html>
<html lang="en" class="bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $headTags ?? ''; ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col font-mono text-slate-200 max-w-4xl mx-auto px-4">
    <!-- Clean Minimal Header -->
    <header class="py-8 border-b border-slate-800 flex items-center justify-between">
        <a href="/" class="text-2xl font-bold tracking-tight text-white hover:text-blue-400">BLOGBUSTER // MINIMAL</a>
        <nav class="space-x-4 text-xs font-medium">
            <a href="/" class="hover:underline">Index</a>
            <a href="/admin/login" class="text-blue-400 hover:underline">Admin</a>
        </nav>
    </header>

    <!-- Clean Posts List -->
    <main class="py-10 space-y-8 flex-1">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <article class="space-y-2 border-b border-slate-800/80 pb-6">
                    <span class="text-xs text-slate-500"><?= htmlspecialchars($post['created_at'] ?? date('Y-m-d')); ?></span>
                    <h2 class="text-xl font-bold text-white hover:text-blue-400">
                        <a href="/<?= htmlspecialchars($post['slug']); ?>"><?= htmlspecialchars($post['title']); ?></a>
                    </h2>
                    <p class="text-sm text-slate-400 leading-relaxed"><?= htmlspecialchars(substr(strip_tags($post['content']), 0, 200)); ?>...</p>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-slate-500 text-sm">No entries found.</p>
        <?php endif; ?>
    </main>

    <footer class="py-6 border-t border-slate-800 text-xs text-slate-600 text-center">
        Powered by BLOGBUSTER Minimal Theme Engine
    </footer>
</body>
</html>
