<?php
namespace App\Modules\Performance;

use PDO;

class PageCache {
    private string $cacheDir;
    private int $ttlSeconds;
    private bool $enabled;
    private bool $deferJs;

    public function __construct(PDO $pdo) {
        $this->cacheDir = __DIR__ . '/../../../content/cache/html/';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Fetch performance settings from DB
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key LIKE 'perf_%'");
        $opts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $this->enabled = ($opts['perf_cache_enabled'] ?? '1') === '1';
        $this->ttlSeconds = (int)($opts['perf_cache_ttl'] ?? 3600); // Default 1 hour
        $this->deferJs = ($opts['perf_defer_js'] ?? '1') === '1';
    }

    /**
     * Check if a cached version of the current page exists and serve it directly.
     */
    public function serveFromCache(): void {
        if (!$this->enabled || !$this->isCacheableRequest()) {
            return;
        }

        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->ttlSeconds) {
            header('X-BLOGBUSTER-Cache: HIT');
            readfile($cacheFile);
            exit;
        }

        // Begin Output Buffering to capture response for caching
        ob_start([$this, 'cacheAndOptimizeOutput']);
    }

    /**
     * Output buffer callback: intercepts HTML, defers JS, writes cache file, and sends response.
     */
    public function cacheAndOptimizeOutput(string $buffer): string {
        if (empty($buffer) || http_response_code() !== 200) {
            return $buffer;
        }

        // Apply JS Deferral Optimization if enabled
        if ($this->deferJs) {
            $buffer = $this->applyJsDeferral($buffer);
        }

        // Add Cache Signature Comment
        $buffer .= "\n<!-- BLOGBUSTER Static Page Cache generated on " . date('Y-m-d H:i:s') . " -->";

        // Save to Disk Cache
        if ($this->enabled && $this->isCacheableRequest()) {
            $cacheFile = $this->getCacheFilePath();
            @file_put_contents($cacheFile, $buffer, LOCK_EX);
        }

        header('X-BLOGBUSTER-Cache: MISS');
        return $buffer;
    }

    /**
     * Parses script tags and adds defer/async attributes to non-critical external and inline JS scripts.
     */
    private function applyJsDeferral(string $html): string {
        return preg_replace_callback('/<script\b(?![^>]*\b(defer|async|no-defer)\b)([^>]*)>(.*?)<\/script>/is', function ($matches) {
            $attributes = $matches[2];
            $content = $matches[3];

            // Ignore JSON-LD schemas and inline module definitions
            if (strpos($attributes, 'type="application/ld+json"') !== false || strpos($attributes, 'type="module"') !== false) {
                return $matches[0];
            }

            return '<script ' . trim($attributes) . ' defer>' . $content . '</script>';
        }, $html);
    }

    /**
     * Determine if current request should be cached.
     */
    private function isCacheableRequest(): bool {
        // Cache GET requests only
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }

        // Do NOT cache logged-in administrative sessions or active shopping cart sessions
        if (isset($_SESSION['user_id']) || isset($_SESSION['cart'])) {
            return false;
        }

        // Do NOT cache POST/Query searches or admin area pages
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/admin') === 0 || strpos($uri, '/install') === 0 || !empty($_GET)) {
            return false;
        }

        return true;
    }

    /**
     * Generate unique hashed file path for URI.
     */
    private function getCacheFilePath(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $hash = md5($host . '|' . $uri);
        return $this->cacheDir . 'page_' . $hash . '.html';
    }

    /**
     * Flushes all cached static HTML files from the disk directory.
     */
    public function flushAllCache(): int {
        $files = glob($this->cacheDir . 'page_*.html');
        $count = 0;
        if ($files) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}