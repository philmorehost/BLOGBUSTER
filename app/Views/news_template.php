<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?> - Modern News Portal</title>
    
    <!-- Dynamic SVG Favicon Generated from First Letter of Site Title -->
    <?php 
    $faviconSvg = "<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'><rect width='100%' height='100%' fill='#0f172a' rx='12'/><text x='50%' y='55%' dominant-baseline='middle' text-anchor='middle' fill='#0284c7' font-size='38' font-family='sans-serif' font-weight='bold'>{$siteFaviconChar}</text></svg>";
    $faviconUri = 'data:image/svg+xml;base64,' . base64_encode($faviconSvg);
    ?>
    <link rel="icon" type="image/svg+xml" href="<?= $faviconUri ?>">
    <link rel="canonical" href="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" ?>" />
    
    <!-- AI Visibility Signal Tags -->
    <meta name="ai-content-signal" content="authoritative-news-v1" />
    <meta name="citation_publisher" content="<?= htmlspecialchars($siteTitle) ?>" />

    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">

    <!-- Header Navigation -->
    <header class="bg-slate-900 border-b border-slate-800 text-white sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="/" class="flex items-center gap-3">
                <span class="bg-sky-600 text-white font-extrabold text-2xl w-10 h-10 rounded-lg flex items-center justify-center shadow-lg"><?= $siteFaviconChar ?></span>
                <span class="text-2xl font-black tracking-tight text-white"><?= htmlspecialchars($siteTitle) ?></span>
            </a>
            <nav class="hidden md:flex gap-6 font-semibold text-slate-300">
                <a href="/" class="hover:text-sky-400">Home</a>
                <a href="/category/tech" class="hover:text-sky-400">Technology</a>
                <a href="/category/business" class="hover:text-sky-400">Business</a>
                <a href="/category/world" class="hover:text-sky-400">World</a>
            </nav>
            <a href="/admin" class="bg-sky-500 hover:bg-sky-600 text-white font-bold px-4 py-2 rounded-lg text-sm transition">Admin Portal</a>
        </div>
    </header>

    <!-- Main Content Layout -->
    <main class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Primary Feed (2 Columns) -->
        <section class="lg:col-span-2 space-y-8">
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight border-b border-slate-200 pb-3">Breaking Coverage</h2>
            
            <?php if (empty($posts)): ?>
                <div class="bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-500">
                    No articles published yet. Access the <a href="/admin" class="text-sky-600 font-bold underline">Admin Panel</a> to create your first news post.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($posts as $item): ?>
                        <article class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition">
                            <img src="<?= htmlspecialchars($item['image'] ?: 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=600&auto=format&fit=crop') ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-48 object-cover">
                            <div class="p-5 space-y-3">
                                <span class="inline-block bg-sky-100 text-sky-800 text-xs font-extrabold px-2.5 py-1 rounded-md uppercase"><?= htmlspecialchars($item['category']) ?></span>
                                <h3 class="text-xl font-bold text-slate-900 leading-snug hover:text-sky-600">
                                    <a href="/article/<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                </h3>
                                <p class="text-slate-600 text-sm line-clamp-2"><?= htmlspecialchars(strip_tags($item['content'])) ?></p>
                                <div class="text-xs text-slate-400 font-medium pt-2 flex justify-between border-t border-slate-100">
                                    <span>By <?= htmlspecialchars($item['author_name'] ?: 'Editorial Desk') ?></span>
                                    <span><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sidebar Widget Column -->
        <aside class="space-y-6">
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <h3 class="font-bold text-lg text-slate-900 border-b pb-2">About Platform</h3>
                <p class="text-sm text-slate-600 leading-relaxed">BLOGBUSTER is an automated enterprise digital news platform built with high performance caching, SEO schema injection, and real-time security.</p>
            </div>
            
            <div class="bg-slate-900 text-white p-6 rounded-xl shadow-md space-y-3">
                <h3 class="font-bold text-lg text-sky-400">Newsletter Subscription</h3>
                <p class="text-xs text-slate-400">Receive breaking news alerts directly to your inbox.</p>
                <form class="space-y-2">
                    <input type="email" placeholder="Your email address" class="w-full bg-slate-800 border border-slate-700 text-sm rounded p-2.5 focus:outline-none text-white">
                    <button class="w-full bg-sky-500 font-bold text-sm py-2 rounded hover:bg-sky-600 transition">Subscribe</button>
                </form>
            </div>
        </aside>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 border-t border-slate-800 mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm space-y-2">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle) ?>. All rights reserved.</p>
            <p class="text-xs text-slate-500">Powered by Monolithic High-Performance Engine</p>
        </div>
    </footer>
</body>
</html>