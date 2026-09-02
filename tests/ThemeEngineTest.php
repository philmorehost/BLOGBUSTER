<?php
declare(strict_types=1);

namespace Tests;

PHPUnit\Framework\TestCase;
use App\Modules\Theme\ThemeEngine;
use PDO;

final class ThemeEngineTest extends TestCase {
    private PDO $pdo;
    private string $tempThemesDir;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Setup Options table
        $this->pdo->exec("
            CREATE TABLE options (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT
            );
        ");

        // Create temporary themes folder structure
        $this->tempThemesDir = sys_get_temp_dir() . '/blogbuster_tests_' . uniqid();
        mkdir($this->tempThemesDir . '/blogbuster-default', 0777, true);
        mkdir($this->tempThemesDir . '/blogbuster-child', 0777, true);

        // Write Parent Theme Manifest & Files
        file_put_contents($this->tempThemesDir . '/blogbuster-default/theme.json', json_encode([
            'name' => 'Default Parent',
            'version' => '1.0.0'
        ]));
        file_put_contents($this->tempThemesDir . '/blogbuster-default/single.php', '<?php echo "Parent Single: " . $title;');
        file_put_contents($this->tempThemesDir . '/blogbuster-default/footer.php', '<?php echo "Parent Footer";');

        // Write Child Theme Manifest & Overrides
        file_put_contents($this->tempThemesDir . '/blogbuster-child/theme.json', json_encode([
            'name' => 'Custom Child',
            'parent' => 'blogbuster-default'
        ]));
        file_put_contents($this->tempThemesDir . '/blogbuster-child/single.php', '<?php echo "Child Single Override: " . $title;');
    }

    protected function tearDown(): void {
        // Clean up temporary files
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempThemesDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($this->tempThemesDir);
    }

    public function testChildThemeOverridesParentTemplate(): void {
        $stmt = $this->pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES ('active_theme', 'blogbuster-child')");
        $stmt->execute();

        $engine = new ThemeEngine($this->pdo, $this->tempThemesDir);
        $output = $engine->render('single.php', ['title' => 'Test Post']);

        $this->assertEquals('Child Single Override: Test Post', $output);
    }

    public function testChildThemeFallsBackToParentTemplate(): void {
        $stmt = $this->pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES ('active_theme', 'blogbuster-child')");
        $stmt->execute();

        $engine = new ThemeEngine($this->pdo, $this->tempThemesDir);
        $output = $engine->render('footer.php');

        $this->assertEquals('Parent Footer', $output);
    }
}