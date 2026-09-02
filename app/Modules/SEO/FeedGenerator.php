<?php
declare(strict_types=1);

namespace App\Modules\SEO;

use PDO;

class FeedGenerator {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function renderRssFeed(): void {
        header('Content-Type: application/rss+xml; charset=utf-8');

        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key IN ('site_title', 'site_tagline', 'site_url')");
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $siteTitle = htmlspecialchars($options['site_title'] ?? 'BLOGBUSTER', ENT_QUOTES, 'UTF-8');
        $siteTagline = htmlspecialchars($options['site_tagline'] ?? 'CMS Platform', ENT_QUOTES, 'UTF-8');
        $siteUrl = htmlspecialchars($options['site_url'] ?? 'http://localhost', ENT_QUOTES, 'UTF-8');

        $stmt = $this->pdo->query("SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.status = 'published' ORDER BY p.id DESC LIMIT 20");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        echo '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "
";
        echo "  <channel>
";
        echo "    <title>{$siteTitle}</title>
";
        echo "    <link>{$siteUrl}</link>
";
        echo "    <description>{$siteTagline}</description>
";
        echo "    <language>en-us</language>
";
        echo "    <atom:link href="{$siteUrl}/feed" rel="self" type="application/rss+xml" />
";

        foreach ($posts as $post) {
            $title = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
            $link = $siteUrl . '/' . htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8');
            $pubDate = date(DATE_RSS, strtotime($post['created_at']));
            $author = htmlspecialchars($post['author_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8');

            echo "    <item>
";
            echo "      <title>{$title}</title>
";
            echo "      <link>{$link}</link>
";
            echo "      <guid isPermaLink="true">{$link}</guid>
";
            echo "      <pubDate>{$pubDate}</pubDate>
";
            echo "      <dc:creator>{$author}</dc:creator>
";
            echo "      <description><![CDATA[{$content}]]></description>
";
            echo "    </item>
";
        }

        echo "  </channel>
";
        echo "</rss>";
        exit;
    }
}
