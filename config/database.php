<?php
/**
 * BLOGBUSTER Production Database Configuration
 */

return [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'db'   => $_ENV['DB_NAME'] ?? 'blogbuster_db',
    'user' => $_ENV['DB_USER'] ?? 'blogbuster_user',
    'pass' => $_ENV['DB_PASS'] ?? 'SecurePassword123!',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];