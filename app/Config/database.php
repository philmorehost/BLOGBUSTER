<?php
/**
 * BLOGBUSTER Production Database Connection & Schema Auto-Migrator
 */

$pdo = null;

$configPath = __DIR__ . '/../../config/database.php';
$config = [];
if (file_exists($configPath)) {
    $config = require $configPath;
}

$host    = $config['host'] ?? $_ENV['DB_HOST'] ?? '127.0.0.1';
$port    = $config['port'] ?? $_ENV['DB_PORT'] ?? 3306;
$db      = $config['db']   ?? $_ENV['DB_NAME'] ?? 'blogbuster_db';
$user    = $config['user'] ?? $_ENV['DB_USER'] ?? 'blogbuster_user';
$pass    = $config['pass'] ?? $_ENV['DB_PASS'] ?? '';
$charset = $config['charset'] ?? 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = $config['options'] ?? [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\PDOException $e) {
    if (!defined('BB_INSTALLING')) {
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Automatically repairs missing columns and tables on existing setups.
 */
function syncDatabaseSchema(PDO $pdo): void
{
    try {
        // 1. Get existing columns from 'users' table if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->fetch()) {
            $colStmt = $pdo->query("SHOW COLUMNS FROM `users`");
            $existingColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            // Add missing security and lockout columns dynamically without position dependencies
            $columnsToAdd = [
                'security_pin'          => "ALTER TABLE `users` ADD COLUMN `security_pin` VARCHAR(255) DEFAULT NULL",
                'failed_login_attempts' => "ALTER TABLE `users` ADD COLUMN `failed_login_attempts` INT DEFAULT 0",
                'locked_until'          => "ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME NULL"
            ];

            foreach ($columnsToAdd as $column => $alterSql) {
                if (!in_array($column, $existingColumns, true)) {
                    $pdo->exec($alterSql);
                }
            }
        }

        // 2. Ensure Options table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `options` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` LONGTEXT NULL,
            `autoload` ENUM('yes', 'no') DEFAULT 'yes'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Ensure Security Tables exist with unique constraints
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sec_blocked_ips` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL UNIQUE,
            `reason` VARCHAR(255) DEFAULT NULL,
            `blocked_until` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_ip_block` (`ip_address`, `blocked_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sec_country_rules` (
            `country_code` VARCHAR(10) PRIMARY KEY,
            `country_name` VARCHAR(100) NOT NULL,
            `status` ENUM('whitelisted', 'not_specified', 'blacklisted') DEFAULT 'not_specified'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sec_login_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `status` ENUM('success', 'failed') NOT NULL,
            `attempted_at` DATETIME NOT NULL,
            INDEX `idx_log_ip` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `sec_whitelisted_ips` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL UNIQUE,
            `recognized_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `is_king` TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    } catch (\PDOException $e) {
        error_log("Schema sync warning: " . $e->getMessage());
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    syncDatabaseSchema($pdo);
}

return $pdo;
