<?php
session_start();

// Redirect to installer if lock file missing
if (!file_exists(__DIR__ . '/config/installed.lock')) {
    header("Location: /install/");
    exit;
}

$config = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Router logic
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

// Administrative Route Handler
if ($route === 'admin' || strpos($route, 'admin/') === 0) {
    require __DIR__ . '/app/Admin/dashboard.php';
    exit;
}

// Security Check: Brute Force & IP Shield
require_once __DIR__ . '/app/Security/Shield.php';
$shield = new SecurityShield($pdo);
$shield->inspectRequest();

// Fetch Site Configuration
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM options");
$opts = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$siteTitle = $opts['site_title'] ?? 'BLOGBUSTER';
$siteFaviconChar = strtoupper(substr($siteTitle, 0, 1));

// Fetch News Articles
if ($route === '' || $route === 'home') {
    $stmt = $pdo->query("SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.author_id = u.id ORDER BY p.created_at DESC");
    $posts = $stmt->fetchAll();
    require __DIR__ . '/app/Views/news_template.php';
    exit;
}

// Article Detail View
if (strpos($route, 'article/') === 0) {
    $slug = str_replace('article/', '', $route);
    $stmt = $pdo->prepare("SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.author_id = u.id WHERE p.slug = ?");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    
    if (!$post) {
        http_response_code(404);
        echo "404 - Article Not Found";
        exit;
    }
    require __DIR__ . '/app/Views/article_single.php';
    exit;
}

// Fallback Page Viewer
http_response_code(404);
echo "404 Page Not Found";