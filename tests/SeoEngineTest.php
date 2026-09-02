<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Modules\SEO\SeoEngine;
use PDO;

final class SeoEngineTest extends TestCase {
    private PDO $pdo;
    private SeoEngine $seoEngine;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE options (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT
            );
        ");

        $stmt = $this->pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['site_title', 'BLOGBUSTER Tech']);
        $stmt->execute(['site_tagline', 'Modern CMS Platform']);

        $this->seoEngine = new SeoEngine($this->pdo);
    }

    public function testRenderHeadTagsForHomepage(): void {
        $html = $this->seoEngine->renderHeadTags();

        $this->assertStringContainsString('<title>BLOGBUSTER Tech - Modern CMS Platform</title>', $html);
        $this->assertStringContainsString('<meta name="ai-content-signal" content="verified-human-authoritative">', $html);
        $this->assertStringContainsString('"@type": "Organization"', $html);
    }

    public function testRenderHeadTagsForArticleWithAuthorEEAT(): void {
        $post = [
            'title' => 'High-Performance PHP CMS',
            'content' => 'An in-depth guide on building high-performance PHP engines.',
            'image_webp' => '/uploads/banner.webp',
            'created_at' => '2026-01-15 10:00:00',
            'updated_at' => '2026-01-16 12:00:00',
            'category' => 'Engineering',
            'category_slug' => 'engineering'
        ];

        $author = [
            'username' => 'ebenezer',
            'job_title' => 'Principal Architect',
            'bio' => 'Software professional and system architect.',
            'social_urls' => 'https://twitter.com/ebenezer, https://github.com/ebenezer'
        ];

        $html = $this->seoEngine->renderHeadTags($post, $author);

        $this->assertStringContainsString('High-Performance PHP CMS', $html);
        $this->assertStringContainsString('"@type": "NewsArticle"', $html);
        $this->assertStringContainsString('"name": "ebenezer"', $html);
        $this->assertStringContainsString('"jobTitle": "Principal Architect"', $html);
        $this->assertStringContainsString('"sameAs": [', $html);
        $this->assertStringContainsString('"https://twitter.com/ebenezer"', $html);
    }
}