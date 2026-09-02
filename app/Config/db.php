<?php
// app/Config/db.php

$host    = 'localhost';
$db      = 'your_database_name';
$user    = 'your_database_user';
$pass    = 'your_database_password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

/**
 * Automatically repairs missing columns and tables on existing setups.
 */
function syncDatabaseSchema(PDO $pdo): void 
{
    try {
        // 1. Get existing columns from 'users' table
        $stmt = $pdo->query("SHOW COLUMNS FROM `users`");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Add missing security and lock columns dynamically
        $columnsToAdd = [
            'security_pin'          => "ALTER TABLE `users` ADD COLUMN `security_pin` VARCHAR(255) DEFAULT NULL AFTER `password_hash`",
            'failed_login_attempts' => "ALTER TABLE `users` ADD COLUMN `failed_login_attempts` INT DEFAULT 0 AFTER `social_urls`",
            'locked_until'          => "ALTER TABLE `users` ADD COLUMN `locked_until` DATETIME NULL AFTER `failed_login_attempts`"
        ];

        foreach ($columnsToAdd as $column => $alterSql) {
            if (!in_array($column, $existingColumns, true)) {
                $pdo->exec($alterSql);
            }
        }
    } catch (\PDOException $e) {
        // Table doesn't exist yet (e.g., during fresh install), ignore error
    }
}

// Auto-run schema repair whenever db.php is required
syncDatabaseSchema($pdo);