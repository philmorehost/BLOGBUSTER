<?php
declare(strict_types=1);

/**
 * BLOGBUSTER Core Front Controller & Request Router
 */

use App\Modules\SEO\SeoEngine;
use App\Modules\SEO\SitemapGenerator;
use App\Modules\Theme\ThemeEngine;
use App\Modules\Addons\WPFormsEngine;
use App\Modules\Addons\WooCommerceEngine;

// Autoload dependencies (Composer PSR-4)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual PSR-4 Fallback Autoloader
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../app/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

// Global Exception Handler for Production Safety
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
});

// Database Connection Factory
$config = require __DIR__ . '/../config/database.php';
$dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['user'], $config['pass'], $config['options']);

// Parse Request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');
if (empty($requestUri)) {
    $requestUri = '/';
}

// Initialize Engines
$seoEngine = new SeoEngine($pdo);
$themeEngine = new ThemeEngine($pdo, __DIR__ . '/../themes');
$wpFormsEngine = new WPFormsEngine($pdo);
$wooEngine = new WooCommerceEngine($pdo);

// Handle POST Submissions (WPForms Processor)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpforms_submit_id'])) {
    $formId = (int)$_POST['wpforms_submit_id'];
    $submittedData = $_POST['data'] ?? [];
    try {
        $wpFormsEngine->processSubmission($formId, $submittedData);
        $_SESSION['form_success'] = "Thank you! Your submission has been received.";
    } catch (Exception $e) {
        $_SESSION['form_error'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// -----------------------------------------------------------------------------
// ROUTING ENGINE
// -----------------------------------------------------------------------------

// Route 1: Dynamic Sitemap.xml
if ($requestUri === '/sitemap.xml') {
    $sitemap = new SitemapGenerator($pdo);
    $sitemap->renderSitemapXml();
}

// Route 2: Homepage
if ($requestUri === '/') {
    $headTags = $seoEngine->renderHeadTags();
    
    // Fetch recent published posts
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC LIMIT 10");
    $posts = $stmt->fetchAll();

    echo $themeEngine->render('index.php', [
        'headTags' => $headTags,
        'posts'    => $posts,
        'woo'      => $wooEngine,
        'wpforms'  => $wpFormsEngine
    ]);
    exit;
}

// Route 3: Category Pages (/category/{slug})
if (preg_match('#^/category/([a-zA-Z0-9-]+)$#', $requestUri, $matches)) {
    $categorySlug = $matches[1];
    
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE category_slug = ? ORDER BY id DESC");
    $stmt->execute([$categorySlug]);
    $posts = $stmt->fetchAll();

    $headTags = $seoEngine->renderHeadTags();

    echo $themeEngine->render('category.php', [
        'headTags'     => $headTags,
        'categorySlug' => $categorySlug,
        'posts'        => $posts
    ]);
    exit;
}

// Route 4: Author Pages (/author/{username})
if (preg_match('#^/author/([a-zA-Z0-9_-]+)$#', $requestUri, $matches)) {
    $username = $matches[1];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $author = $stmt->fetch();

    if (!$author) {
        http_response_code(404);
        echo $themeEngine->render('404.php', ['headTags' => $seoEngine->renderHeadTags()]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$author['id']]);
    $posts = $stmt->fetchAll();

    $headTags = $seoEngine->renderHeadTags(null, $author);

    echo $themeEngine->render('author.php', [
        'headTags' => $headTags,
        'author'   => $author,
        'posts'    => $posts
    ]);
    exit;
}

// Route 5: Single Post / Custom Page Slug Matching
$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ?");
$stmt->execute([ltrim($requestUri, '/')]);
$post = $stmt->fetch();

if ($post) {
    // Fetch post author for E-E-A-T metadata
    $author = null;
    if (!empty($post['user_id'])) {
        $authStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $authStmt->execute([$post['user_id']]);
        $author = $authStmt->fetch() ?: null;
    }

    $headTags = $seoEngine->renderHeadTags($post, $author);

    echo $themeEngine->render('single.php', [
        'headTags' => $headTags,
        'post'     => $post,
        'author'   => $author,
        'wpforms'  => $wpFormsEngine
    ]);
    exit;
}

// Route 6: 404 Fallback
http_response_code(404);
echo $themeEngine->render('404.php', [
    'headTags' => $seoEngine->renderHeadTags()
]);