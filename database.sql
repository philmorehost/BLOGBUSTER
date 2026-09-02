-- Core Users & E-E-A-T Profiles
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    bio TEXT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_suspended TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Articles & Custom Pages
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    category_slug VARCHAR(50) DEFAULT 'general',
    image VARCHAR(255) DEFAULT NULL,
    image_webp VARCHAR(255) DEFAULT NULL,
    image_avif VARCHAR(255) DEFAULT NULL,
    image_alt VARCHAR(255) DEFAULT NULL,
    author_id INT,
    post_type ENUM('post', 'page') DEFAULT 'post',
    views_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Dynamic System Options & Theme State
CREATE TABLE options (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value LONGTEXT
);

-- cPHulk / Imunify360 Login Logs
CREATE TABLE sec_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Active Blocked IPs
CREATE TABLE sec_blocked_ips (
    ip_address VARCHAR(45) PRIMARY KEY,
    reason VARCHAR(255) NOT NULL,
    blocked_until DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Whitelisted IPs & King Status Tracking
CREATE TABLE sec_whitelisted_ips (
    ip_address VARCHAR(45) PRIMARY KEY,
    is_king TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- IP Successful Sessions Log (For King Auto-Whitelisting)
CREATE TABLE sec_successful_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Country Firewall Rules
CREATE TABLE sec_country_rules (
    country_code VARCHAR(10) PRIMARY KEY,
    country_name VARCHAR(100) NOT NULL,
    status ENUM('whitelisted', 'not_specified', 'blacklisted') DEFAULT 'not_specified'
);

-- WooCommerce Equivalent Products & Orders
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);