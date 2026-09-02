<?php
namespace App\Modules\Theme;

use PDO;
use Exception;

class ThemeEngine {
    private PDO $pdo;
    private string $themesDir;
    private string $activeTheme;
    private ?string $parentTheme = null;
    private array $activeManifest = [];

    public function __construct(PDO $pdo, string $themesDir) {
        $this->pdo = $pdo;
        $this->themesDir = rtrim($themesDir, '/\\');
        $this->ensureSchemaExists();
        $this->loadActiveThemeConfig();
    }

    /**
     * Ensure options and theme settings tables exist.
     */
    private function ensureSchemaExists(): void {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS options (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS options (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value LONGTEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }

    /**
     * Load active theme settings from options DB table.
     */
    private function loadActiveThemeConfig(): void {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM options WHERE setting_key = 'active_theme'");
        $stmt->execute();
        $this->activeTheme = $stmt->fetchColumn() ?: 'blogbuster-default';

        $manifestPath = $this->themesDir . '/' . $this->activeTheme . '/theme.json';
        if (file_exists($manifestPath)) {
            $this->activeManifest = json_decode(file_get_contents($manifestPath), true) ?: [];
            $this->parentTheme = $this->activeManifest['parent'] ?? null;
        }
    }

    /**
     * Scan available themes in the themes directory.
     */
    public function getAvailableThemes(): array {
        $themes = [];
        $dirs = glob($this->themesDir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $folderName = basename($dir);
            $manifestFile = $dir . '/theme.json';

            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
                $themes[$folderName] = [
                    'folder' => $folderName,
                    'name' => $manifest['name'] ?? $folderName,
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? 'BLOGBUSTER Team',
                    'parent' => $manifest['parent'] ?? null,
                    'is_active' => ($folderName === $this->activeTheme),
                    'screenshot' => file_exists($dir . '/screenshot.png') ? "/themes/{$folderName}/screenshot.png" : null
                ];
            }
        }

        return $themes;
    }

    /**
     * Switch active theme without losing site content or existing theme option presets.
     */
    public function switchTheme(string $themeFolder): bool {
        $targetDir = $this->themesDir . '/' . $themeFolder;
        if (!is_dir($targetDir) || !file_exists($targetDir . '/theme.json')) {
            throw new Exception("Theme '{$themeFolder}' is invalid or missing theme.json manifest.");
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO options (setting_key, setting_value) VALUES ('active_theme', ?)");
            $stmt->execute([$themeFolder]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO options (setting_key, setting_value)
                VALUES ('active_theme', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$themeFolder]);
        }

        $this->activeTheme = $themeFolder;
        $this->loadActiveThemeConfig();
        return true;
    }

    /**
     * Resolve template location using Parent-Child Override Hierarchy
     */
    public function resolveTemplate(string $templateName): string {
        $templateName = ltrim($templateName, '/');

        // Check 1: Child or standalone active theme
        $childFile = $this->themesDir . '/' . $this->activeTheme . '/' . $templateName;
        if (file_exists($childFile)) {
            return $childFile;
        }

        // Check 2: Parent theme fallback
        if ($this->parentTheme) {
            $parentFile = $this->themesDir . '/' . $this->parentTheme . '/' . $templateName;
            if (file_exists($parentFile)) {
                return $parentFile;
            }
        }

        // Check 3: System default theme fallback
        $defaultFile = $this->themesDir . '/blogbuster-default/' . $templateName;
        if (file_exists($defaultFile)) {
            return $defaultFile;
        }

        throw new Exception("Template '{$templateName}' could not be resolved in child, parent, or default theme directories.");
    }

    public function render(string $templateName, array $data = []): string {
        $templatePath = $this->resolveTemplate($templateName);
        extract($data);
        
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Resolve public CSS/JS asset paths using parent-child fallback logic.
     */
    public function getAssetUrl(string $assetPath): string {
        $assetPath = ltrim($assetPath, '/');

        if (file_exists($this->themesDir . '/' . $this->activeTheme . '/' . $assetPath)) {
            return "/themes/" . $this->activeTheme . '/' . $assetPath;
        }

        if ($this->parentTheme && file_exists($this->themesDir . '/' . $this->parentTheme . '/' . $assetPath)) {
            return "/themes/" . $this->parentTheme . '/' . $assetPath;
        }

        return "/themes/blogbuster-default/" . $assetPath;
    }

    public function getActiveTheme(): string {
        return $this->activeTheme;
    }

    public function getParentTheme(): ?string {
        return $this->parentTheme;
    }
}
