<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Modules\Cache\CacheEngine;
use App\Modules\SEO\DeepSeekEngine;
use PDO;

final class AdvancedFeaturesTest extends TestCase {
    private string $cacheDir;

    protected function setUp(): void {
        $this->cacheDir = sys_get_temp_dir() . '/bb_cache_test_' . uniqid();
    }

    protected function tearDown(): void {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testCacheStoreAndRetrieve(): void {
        $cache = new CacheEngine($this->cacheDir, null, 3600);
        $cache->set('page_home', '<html>Header</html>', 10);

        $this->assertEquals('<html>Header</html>', $cache->get('page_home'));
    }

    public function testCacheExpiration(): void {
        $cache = new CacheEngine($this->cacheDir, null, -10);
        $cache->set('expired_page', '<html>Expired</html>', -10);

        $this->assertNull($cache->get('expired_page'));
    }
}
