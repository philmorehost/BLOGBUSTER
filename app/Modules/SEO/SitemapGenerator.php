<?php
declare(strict_types=1);

namespace App\Modules\SEO;

use PDO;

class SitemapGenerator {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function renderSitemapXml(): void {
        header('Content-Type: application/xml; charset=utf-8');

        $stmt = $this->pdo->query("SELECT slug, updated_at FROM posts ORDER BY id DESC");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($posts as $post) {
            $slug = htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8');
            $date = date('Y-m-d', strtotime($post['updated_at'] ?? 'now'));
            echo "  <url>\n";
            echo "    <loc>/" . $slug . "</loc>\n";
            echo "    <lastmod>" . $date . "</lastmod>\n";
            echo "  </url>\n";
        }

        echo '</urlset>';
        exit;
    }
}