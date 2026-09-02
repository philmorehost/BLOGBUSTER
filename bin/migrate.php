<?php
// bin/migrate.php
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass']);

echo "Initializing BLOGBUSTER Schema...\n";

// Core Options & Users
$pdo->exec("
    CREATE TABLE IF NOT EXISTS options (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        bio TEXT,
        job_title VARCHAR(100),
        social_urls TEXT,
        credentials TEXT,
        is_suspended TINYINT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Initialize Modules
new \App\Modules\Addons\WooCommerceEngine($pdo);
new \App\Modules\Addons\WPFormsEngine($pdo);

echo "Schema initialization complete.\n";