<?php
namespace App\Modules\Security;

use PDO;
use Exception;

class BruteForceShield {
    private PDO $pdo;
    private array $config = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadConfiguration();
    }

    /**
     * Fetch security settings from options table with fallback defaults.
     */
    private function loadConfiguration(): void {
        $defaults = [
            'sec_user_protection_enabled' => '1',
            'sec_user_period_mins'         => '15',
            'sec_user_max_failures'        => '5',
            'sec_user_lock_admin'          => '1',
            
            'sec_ip_protection_enabled'   => '1',
            'sec_ip_period_mins'           => '15',
            'sec_ip_max_failures'          => '5',
            'sec_ip_block_duration'        => '1_day', // 1_day, 1_week, 1_month, 1_year
            
            'sec_apply_scope'              => 'remote_local', // local_only, remote_local
            'sec_history_retention_mins'   => '15',
            
            'sec_notify_unwhitelisted_ip'  => '1',
            'sec_notify_known_netblock'    => '0',
            'sec_notify_bruteforce_user'   => '1',
            'sec_admin_email'              => 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        ];

        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key LIKE 'sec_%'");
            $saved = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            $this->config = array_merge($defaults, $saved);
        } catch (Exception $e) {
            $this->config = $defaults;
        }
    }

    /**
     * Inspect incoming request for active IP or Country blocks.
     */
    public function inspectRequest(): void {
        $ip = $this->getClientIP();

        // Check Scope (Local vs Remote)
        if ($this->config['sec_apply_scope'] === 'local_only' && !$this->isLocalIP($ip)) {
            return;
        }

        // 1. Check Country Blacklist
        $countryCode = $this->getCountryFromIP($ip);
        if ($this->isCountryBlacklisted($countryCode)) {
            http_response_code(403);
            exit("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h1>403 Access Forbidden</h1><p>Access from your geographic region ({$countryCode}) is restricted by system policy.</p></div>");
        }

        // 2. Check Whitelist Exemption
        if ($this->isIPWhitelisted($ip)) {
            return;
        }

        // 3. Check Active Firewall Block
        if ($this->isIPBlocked($ip)) {
            http_response_code(429);
            exit("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h1>429 Too Many Requests</h1><p>Your IP ({$ip}) has been temporarily blocked due to repeated security failures.</p></div>");
        }
    }

    /**
     * Process a login attempt (Success or Failure).
     */
    public function recordLoginAttempt(string $username, bool $success): void {
        $ip = $this->getClientIP();
        $now = date('Y-m-d H:i:s');

        if ($success) {
            // Log successful attempt
            $stmt = $this->pdo->prepare("INSERT INTO sec_login_logs (ip_address, username, status, attempted_at) VALUES (?, ?, 'success', ?)");
            $stmt->execute([$ip, $username, $now]);

            // Track unique session for King IP progression
            $this->trackSessionForWhitelisting($ip);

            // Handle Un-whitelisted Admin Login Email Notification
            if ($this->config['sec_notify_unwhitelisted_ip'] === '1' && !$this->isIPWhitelisted($ip)) {
                $this->sendNotification(
                    "Security Notice: Successful Admin Login from Non-Whitelisted IP",
                    "A successful admin login was recorded for user '{$username}' from IP address: {$ip} at {$now}."
                );
            }
            return;
        }

        // Log failed attempt
        $stmt = $this->pdo->prepare("INSERT INTO sec_login_logs (ip_address, username, status, attempted_at) VALUES (?, ?, 'failed', ?)");
        $stmt->execute([$ip, $username, $now]);

        // Evaluate Protection Policies
        $this->evaluateUserProtection($username, $ip);
        $this->evaluateIPProtection($ip, $username);
        $this->cleanupOldLogs();
    }

    /**
     * User-based Brute Force Evaluation
     */
    private function evaluateUserProtection(string $username, string $ip): void {
        if ($this->config['sec_user_protection_enabled'] !== '1') {
            return;
        }

        // Skip protection for admin/administrator if explicitly configured
        if ($this->config['sec_user_lock_admin'] !== '1' && in_array(strtolower($username), ['admin', 'administrator'])) {
            return;
        }

        $mins = (int)$this->config['sec_user_period_mins'];
        $maxFailures = (int)$this->config['sec_user_max_failures'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_login_logs WHERE username = ? AND status = 'failed' AND attempted_at >= NOW() - INTERVAL ? MINUTE");
        $stmt->execute([$username, $mins]);
        $failures = (int)$stmt->fetchColumn();

        if ($failures >= $maxFailures) {
            // Check if account exists
            $userStmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $userStmt->execute([$username]);
            $userExists = $userStmt->fetchColumn();

            if ($userExists) {
                // Suspend User Account
                $this->pdo->prepare("UPDATE users SET is_suspended = 1 WHERE username = ?")->execute([$username]);
            } else {
                // User does not exist -> Automatically block IP at Firewall level
                $this->applyIPBlock($ip, '1_day', "Brute force attack on non-existent account: {$username}");
            }

            if ($this->config['sec_notify_bruteforce_user'] === '1') {
                $this->sendNotification(
                    "ALERT: Brute Force Detected on Account '{$username}'",
                    "System detected {$failures} failed login attempts for username '{$username}' within {$mins} minutes. Source IP: {$ip}."
                );
            }
        }
    }

    /**
     * IP-based Brute Force Evaluation
     */
    private function evaluateIPProtection(string $ip, string $username): void {
        if ($this->config['sec_ip_protection_enabled'] !== '1' || $this->isIPWhitelisted($ip)) {
            return;
        }

        $mins = (int)$this->config['sec_ip_period_mins'];
        $maxFailures = (int)$this->config['sec_ip_max_failures'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_login_logs WHERE ip_address = ? AND status = 'failed' AND attempted_at >= NOW() - INTERVAL ? MINUTE");
        $stmt->execute([$ip, $mins]);
        $failures = (int)$stmt->fetchColumn();

        if ($failures >= $maxFailures) {
            $duration = $this->config['sec_ip_block_duration'];
            $this->applyIPBlock($ip, $duration, "Exceeded max IP failures ({$failures} failures targeting '{$username}')");
        }
    }

    /**
     * Applies Firewall block to an IP for a specific duration.
     */
    public function applyIPBlock(string $ip, string $duration, string $reason): void {
        $intervals = [
            '1_day'   => '1 DAY',
            '1_week'  => '1 WEEK',
            '1_month' => '1 MONTH',
            '1_year'  => '1 YEAR'
        ];
        $intervalSql = $intervals[$duration] ?? '1 DAY';

        $stmt = $this->pdo->prepare("INSERT INTO sec_blocked_ips (ip_address, reason, blocked_until) 
            VALUES (?, ?, NOW() + INTERVAL {$intervalSql}) 
            ON DUPLICATE KEY UPDATE blocked_until = NOW() + INTERVAL {$intervalSql}, reason = ?");
        $stmt->execute([$ip, $reason, $reason]);
    }

    /**
     * Tracks successful logins per session to grant "King Whitelist" status (5+ distinct sessions).
     */
    private function trackSessionForWhitelisting(string $ip): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionId = session_id();

        // Register session
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO sec_successful_sessions (ip_address, session_id, logged_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $sessionId]);

        // Count unique successful sessions
        $countStmt = $this->pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM sec_successful_sessions WHERE ip_address = ?");
        $countStmt->execute([$ip]);
        $distinctSessions = (int)$countStmt->fetchColumn();

        if ($distinctSessions >= 5) {
            // Auto-whitelist IP with King status
            $this->pdo->prepare("INSERT INTO sec_whitelisted_ips (ip_address, is_king) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_king = 1")->execute([$ip]);
        }
    }

    /**
     * Clean up expired login logs based on retention window.
     */
    private function cleanupOldLogs(): void {
        $mins = (int)($this->config['sec_history_retention_mins'] ?? 15);
        $stmt = $this->pdo->prepare("DELETE FROM sec_login_logs WHERE attempted_at < NOW() - INTERVAL ? MINUTE");
        $stmt->execute([$mins]);
    }

    public function isIPBlocked(string $ip): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_blocked_ips WHERE ip_address = ? AND blocked_until > NOW()");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() > 0;
    }

    public function isIPWhitelisted(string $ip): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_whitelisted_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() > 0;
    }

    public function isCountryBlacklisted(string $countryCode): bool {
        $stmt = $this->pdo->prepare("SELECT status FROM sec_country_rules WHERE country_code = ?");
        $stmt->execute([$countryCode]);
        return $stmt->fetchColumn() === 'blacklisted';
    }

    public function getClientIP(): string {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getCountryFromIP(string $ip): string {
        return $_SERVER['GEOIP_COUNTRY_CODE'] ?? 'UN';
    }

    private function isLocalIP(string $ip): bool {
        return in_array($ip, ['127.0.0.1', '::1']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0;
    }

    private function sendNotification(string $subject, string $message): void {
        $to = $this->config['sec_admin_email'];
        $headers = "From: BLOGBUSTER Security <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($to, $subject, $message, $headers);
    }
}