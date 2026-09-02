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
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key IN ('site_title', 'site_tagline', 'site_url')");
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $siteTitle = $options['site_title'] ?? 'BLOGBUSTER';
        $siteTagline = $options['site_tagline'] ?? 'CMS Platform';
        $siteUrl = rtrim($options['site_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
        $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $canonicalUrl = $siteUrl . $currentUri;

        // Dynamic Favicon using Site Title First Letter
        $firstChar = strtoupper(substr($siteTitle, 0, 1));
        $svgFavicon = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232563eb'/><text x='50%' y='65%' font-size='60' font-weight='bold' text-anchor='middle' fill='%23ffffff' font-family='sans-serif'>{$firstChar}</text></svg>";

        if ($post) {
            $title = htmlspecialchars($post['title'] . ' - ' . $siteTitle, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars(substr(strip_tags($post['content'] ?? ''), 0, 160), ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars($post['image_webp'] ?? ($siteUrl . '/public/uploads/default.jpg'), ENT_QUOTES, 'UTF-8');

            $newsArticleSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $post['title'],
                'image' => [$image],
                'datePublished' => $post['created_at'] ?? '',
                'dateModified' => $post['updated_at'] ?? ($post['created_at'] ?? ''),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl
                ]
            ];

            if ($author) {
                $newsArticleSchema['author'] = [
                    '@type' => 'Person',
                    'name' => $author['username'],
                    'jobTitle' => $author['job_title'] ?? 'Author',
                    'description' => $author['bio'] ?? '',
                    'sameAs' => array_values(array_filter(array_map('trim', explode(',', $author['social_urls'] ?? ''))))
                ];
            }

            $breadcrumbSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $siteUrl
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $post['category'] ?? 'Blog',
                        'item' => $siteUrl . '/category/' . ($post['category_slug'] ?? 'blog')
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $post['title'],
                        'item' => $canonicalUrl
                    ]
                ]
            ];

            $schemaJson = json_encode($newsArticleSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $breadcrumbJson = json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return <<<HTML
<title>{$title}</title>
<link rel="canonical" href="{$canonicalUrl}">
<link rel="icon" type="image/svg+xml" href="{$svgFavicon}">
<meta name="description" content="{$description}">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$description}">
<meta property="og:image" content="{$image}">
<meta name="ai-content-signal" content="verified-human-authoritative">
<meta name="ai-search-visibility" content="index,follow,ai-synthesize">
<script type="application/ld+json">
{$schemaJson}
</script>
<script type="application/ld+json">
{$breadcrumbJson}
</script>
HTML;
        }

        $title = htmlspecialchars($siteTitle . ' - ' . $siteTagline, ENT_QUOTES, 'UTF-8');
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteTitle,
            'url' => $siteUrl
        ];
        $schemaJson = json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<HTML
<title>{$title}</title>
<link rel="canonical" href="{$canonicalUrl}">
<link rel="icon" type="image/svg+xml" href="{$svgFavicon}">
<meta name="ai-content-signal" content="verified-human-authoritative">
<meta name="ai-search-visibility" content="index,follow,ai-synthesize">
<script type="application/ld+json">
{$schemaJson}
</script>
HTML;
    }
}
