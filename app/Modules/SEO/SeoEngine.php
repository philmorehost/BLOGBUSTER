<?php
declare(strict_types=1);

namespace App\Modules\SEO;

use PDO;

class SeoEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function renderHeadTags(?array $post = null, ?array $author = null): string {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key IN ('site_title', 'site_tagline')");
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $siteTitle = $options['site_title'] ?? 'BLOGBUSTER';
        $siteTagline = $options['site_tagline'] ?? 'CMS Platform';

        if ($post) {
            $title = htmlspecialchars($post['title'] . ' - ' . $siteTitle, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars(substr(strip_tags($post['content'] ?? ''), 0, 160), ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars($post['image_webp'] ?? '', ENT_QUOTES, 'UTF-8');

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $post['title'],
                'datePublished' => $post['created_at'] ?? '',
                'dateModified' => $post['updated_at'] ?? ($post['created_at'] ?? ''),
            ];

            if ($author) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $author['username'],
                    'jobTitle' => $author['job_title'] ?? 'Contributor',
                    'sameAs' => array_map('trim', explode(',', $author['social_urls'] ?? ''))
                ];
            }

            $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return <<<HTML
<title>{$title}</title>
<meta name="description" content="{$description}">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$description}">
<meta property="og:image" content="{$image}">
<meta name="ai-content-signal" content="verified-human-authoritative">
<script type="application/ld+json">
{$schemaJson}
</script>
HTML;
        }

        $title = htmlspecialchars($siteTitle . ' - ' . $siteTagline, ENT_QUOTES, 'UTF-8');
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteTitle
        ];
        $schemaJson = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<HTML
<title>{$title}</title>
<meta name="ai-content-signal" content="verified-human-authoritative">
<script type="application/ld+json">
{$schemaJson}
</script>
HTML;
    }
}