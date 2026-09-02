-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'editor', 'author', 'subscriber') DEFAULT 'author',
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `job_title` VARCHAR(100) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `social_urls` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Options / Settings Table
CREATE TABLE IF NOT EXISTS `options` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` LONGTEXT NULL,
    `autoload` ENUM('yes', 'no') DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Posts Table
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `category_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `content` LONGTEXT NOT NULL,
    `excerpt` TEXT DEFAULT NULL,
    `image_webp` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('published', 'draft', 'scheduled') DEFAULT 'draft',
    `views_count` INT DEFAULT 0,
    `publish_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_status_publish` (`status`, `publish_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tags Table (ADDITION 1)
CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Post-Tag Pivot Table (ADDITION 2)
CREATE TABLE IF NOT EXISTS `post_tags` (
    `post_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`),
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Media Table
CREATE TABLE IF NOT EXISTS `media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL,
    `alt_text` VARCHAR(255) DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Social Accounts Table
CREATE TABLE IF NOT EXISTS `social_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `platform` VARCHAR(50) NOT NULL,
    `account_name` VARCHAR(150) NOT NULL,
    `access_token` TEXT NOT NULL,
    `refresh_token` TEXT NULL,
    `expires_at` DATETIME NULL,
    `account_id` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'expired', 'disabled') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Social Post Queue Table
CREATE TABLE IF NOT EXISTS `social_post_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `platform` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `media_url` VARCHAR(255) NULL,
    `scheduled_at` DATETIME NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `error_log` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    INDEX `idx_queue_execution` (`scheduled_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Custom Pages Table
CREATE TABLE IF NOT EXISTS `custom_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(150) UNIQUE NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `page_data` JSON NOT NULL,
    `status` ENUM('published', 'draft') DEFAULT 'published',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Google Site Kit Tokens & Cache Table
CREATE TABLE IF NOT EXISTS `google_sitekit_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `access_token` TEXT NOT NULL,
    `refresh_token` TEXT NOT NULL,
    `property_id` VARCHAR(100) NULL,
    `expires_at` DATETIME NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Licenses Table (Paystack Integration)
CREATE TABLE IF NOT EXISTS `licenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_key` VARCHAR(64) UNIQUE NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `tier` ENUM('standard', 'developer', 'agency', 'enterprise') DEFAULT 'standard',
    `paystack_reference` VARCHAR(100) NULL,
    `status` ENUM('active', 'expired', 'revoked') DEFAULT 'active',
    `expires_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Package Builds Audit Table (ADDITION 3 - For PackageBuilderService)
CREATE TABLE IF NOT EXISTS `package_builds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(20) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `checksum` VARCHAR(64) NULL,
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Server Logs Table (ADDITION 4 - For ServerManagerService)
CREATE TABLE IF NOT EXISTS `server_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(100) NOT NULL,
    `status` ENUM('success', 'failed', 'warning') NOT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;