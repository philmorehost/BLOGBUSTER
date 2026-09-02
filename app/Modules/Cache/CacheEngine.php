<?php
declare(strict_types=1);

namespace App\Modules\Cache;

use Redis;
use Exception;

class CacheEngine {
    private string $cacheDir;
    private ?Redis $redis = null;
    private bool $useRedis = false;
    private int $defaultTtl;

    public function __construct(string $cacheDir, ?array $redisConfig = null, int $defaultTtl = 3600) {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }

        if ($redisConfig && class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $connected = $this->redis->connect(
                    $redisConfig['host'] ?? '127.0.0.1',
                    (int)($redisConfig['port'] ?? 6379),
                    1.5
                );
                if ($connected) {
                    if (!empty($redisConfig['auth'])) {
                        $this->redis->auth($redisConfig['auth']);
                    }
                    $this->useRedis = true;
                }
            } catch (Exception $e) {
                $this->useRedis = false;
            }
        }
    }

    public function get(string $key): ?string {
        if ($this->useRedis && $this->redis) {
            $val = $this->redis->get("bb_cache:" . $key);
            return $val !== false ? $val : null;
        }

        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $data = @file_get_contents($filePath);
        if (!$data) return null;

        $unserialized = @unserialize($data);
        if (!$unserialized || !isset($unserialized['expires_at'], $unserialized['content'])) {
            return null;
        }

        if (time() > $unserialized['expires_at']) {
            @unlink($filePath);
            return null;
        }

        return $unserialized['content'];
    }

    public function set(string $key, string $content, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->defaultTtl;

        if ($this->useRedis && $this->redis) {
            return $this->redis->setex("bb_cache:" . $key, $ttl, $content);
        }

        $filePath = $this->getFilePath($key);
        $payload = serialize([
            'expires_at' => time() + $ttl,
            'content' => $content
        ]);

        return file_put_contents($filePath, $payload, LOCK_EX) !== false;
    }

    public function delete(string $key): bool {
        if ($this->useRedis && $this->redis) {
            return $this->redis->del("bb_cache:" . $key) > 0;
        }

        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return true;
    }

    public function clear(): bool {
        if ($this->useRedis && $this->redis) {
            return $this->redis->flushDB();
        }

        $files = glob($this->cacheDir . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }

    private function getFilePath(string $key): string {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
