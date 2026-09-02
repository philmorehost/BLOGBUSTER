<?php
namespace App\Security;

use PDO;
use App\Services\MailService;

class Shield {
    private PDO $pdo;
    private ?MailService $mailService;

    public function __construct(PDO $pdo, ?MailService $mailService = null) {
        $this->pdo = $pdo;
        $this->mailService = $mailService;
    }

    /**
     * Inspect every incoming HTTP request against active IP and Country Blacklists.
     */
    public function inspectRequest(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 1. Check IP Blacklist
        $stmt = $this->pdo->prepare("SELECT * FROM sec_blocked_ips WHERE ip_address = ? AND blocked_until > NOW()");
        $stmt->execute([$ip]);
        if ($stmt->fetch()) {
            http_response_code(403);
            exit("<h1 style='text-align:center;margin-top:20%;font-family:sans-serif;'>403 Access Denied: Your IP address (" . htmlspecialchars($ip) . ") has been blocked by Security Shield.</h1>");
        }

        // 2. Check Country Rule Enforcement
        $countryCode = $_SERVER['GEOIP_COUNTRY_CODE'] ?? $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'UN';
        $cStmt = $this->pdo->prepare("SELECT status FROM sec_country_rules WHERE country_code = ?");
        $cStmt->execute([$countryCode]);
        $rule = $cStmt->fetchColumn();

        if ($rule === 'blacklisted') {
            http_response_code(403);
            exit("<h1 style='text-align:center;margin-top:20%;font-family:sans-serif;'>403 Access Denied: Access from your geographic region (" . htmlspecialchars($countryCode) . ") is restricted.</h1>");
        }
    }

    /**
     * Process failed login attempt with user lockout and firewall IP blocking.
     */
    public function recordFailedLogin(string $username, string $ip): void {
        $now = date('Y-m-d H:i:s');

        // Log attempt
        $stmt = $this->pdo->prepare("INSERT INTO sec_login_logs (ip_address, username, status, attempted_at) VALUES (?, ?, 'failed', ?)");
        $stmt->execute([$ip, $username, $now]);

        // Fetch Anti-BruteForce Settings from options
        $opts = $this->getSecurityOptions();
        $maxAccountFailures = (int)($opts['sec_max_account_failures'] ?? $opts['max_account_failures'] ?? 5);
        $maxIpFailures = (int)($opts['sec_max_ip_failures'] ?? $opts['max_ip_failures'] ?? 5);
        $blockDuration = $opts['sec_ip_block_duration'] ?? $opts['ip_block_duration'] ?? '1_day'; // 1_day, 1_week, 1_month, 1_year
        $allowLockAdmin = ($opts['sec_lock_admin_users'] ?? $opts['lock_admin_users'] ?? '1') === '1';

        // 1. Username-based protection (Lock account)
        if (!empty($username)) {
            $userStmt = $this->pdo->prepare("SELECT id, username, role, failed_login_attempts FROM users WHERE username = ? OR email = ?");
            $userStmt->execute([$username, $username]);
            $user = $userStmt->fetch();

            if ($user) {
                if ($user['role'] !== 'admin' || $allowLockAdmin) {
                    $attempts = (int)$user['failed_login_attempts'] + 1;
                    if ($attempts >= $maxAccountFailures) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $upd = $this->pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ?, status = 'suspended' WHERE id = ?");
                        $upd->execute([$attempts, $lockUntil, $user['id']]);

                        // Notify admin of brute force detection
                        if ($this->mailService && ($opts['sec_notify_brute_force'] ?? '1') === '1') {
                            $adminEmail = $opts['admin_email'] ?? 'admin@example.com';
                            $this->mailService->send($adminEmail, "Security Alert: Brute Force User Lockout",
                                "Account '{$username}' has reached maximum failed login attempts ({$attempts}) and has been locked until {$lockUntil} from IP {$ip}."
                            );
                        }
                    } else {
                        $upd = $this->pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
                        $upd->execute([$attempts, $user['id']]);
                    }
                }
            } else {
                // If user does not exist, trigger IP block check immediately
                $this->applyIpBlock($ip, "Unknown Username Failed Attempt: {$username}", '1_day');
            }
        }

        // 2. IP-based protection
        $ipFailStmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_login_logs WHERE ip_address = ? AND status = 'failed' AND attempted_at >= NOW() - INTERVAL 15 MINUTE");
        $ipFailStmt->execute([$ip]);
        $ipFailures = (int)$ipFailStmt->fetchColumn();

        if ($ipFailures >= $maxIpFailures) {
            $this->applyIpBlock($ip, "Exceeded Max IP Failures ({$ipFailures})", $blockDuration);
        }
    }

    /**
     * Process successful login attempt with auto-whitelisting logic after 5 distinct sessions.
     */
    public function recordSuccessfulLogin(string $username, string $ip): void {
        $now = date('Y-m-d H:i:s');

        // 1. Log success
        $stmt = $this->pdo->prepare("INSERT INTO sec_login_logs (ip_address, username, status, attempted_at) VALUES (?, ?, 'success', ?)");
        $stmt->execute([$ip, $username, $now]);

        // Reset user failed attempts counter
        $resetStmt = $this->pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = ? OR email = ?");
        $resetStmt->execute([$username, $username]);

        // 2. Track distinct successful sessions for IP auto-whitelisting (5 times across different sessions)
        $sessionCountStmt = $this->pdo->prepare("SELECT COUNT(DISTINCT DATE_FORMAT(attempted_at, '%Y-%m-%d %H')) FROM sec_login_logs WHERE ip_address = ? AND status = 'success'");
        $sessionCountStmt->execute([$ip]);
        $distinctSessions = (int)$sessionCountStmt->fetchColumn();

        if ($distinctSessions >= 5) {
            $whiteStmt = $this->pdo->prepare("INSERT INTO sec_whitelisted_ips (ip_address, is_king) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_king = 1");
            $whiteStmt->execute([$ip]);
        }

        // 3. Security Notifications on Admin Login
        $opts = $this->getSecurityOptions();
        $isWhitelisted = $this->isIpWhitelisted($ip);

        if (($opts['sec_notify_admin_login'] ?? '1') === '1' && !$isWhitelisted) {
            $adminEmail = $opts['admin_email'] ?? 'admin@example.com';
            if ($this->mailService) {
                $this->mailService->send($adminEmail, "Security Alert: Non-Whitelisted Admin Login",
                    "Successful login for '{$username}' from non-whitelisted IP address {$ip} at {$now}."
                );
            }
        }
    }

    public function isIpWhitelisted(string $ip): bool {
        $stmt = $this->pdo->prepare("SELECT id FROM sec_whitelisted_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return (bool)$stmt->fetch();
    }

    private function applyIpBlock(string $ip, string $reason, string $durationOption): void {
        $intervalSql = match($durationOption) {
            '1_week'  => 'INTERVAL 1 WEEK',
            '1_month' => 'INTERVAL 1 MONTH',
            '1_year'  => 'INTERVAL 1 YEAR',
            default   => 'INTERVAL 1 DAY'
        };

        $stmt = $this->pdo->prepare("INSERT INTO sec_blocked_ips (ip_address, reason, blocked_until) VALUES (?, ?, NOW() + {$intervalSql}) ON DUPLICATE KEY UPDATE blocked_until = NOW() + {$intervalSql}, reason = VALUES(reason)");
        $stmt->execute([$ip, $reason]);
    }

    private function getSecurityOptions(): array {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key LIKE 'sec_%' OR setting_key = 'admin_email'");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }
}

// Global Class Alias for Backwards Compatibility
if (!class_exists('SecurityShield')) {
    class_alias(\App\Security\Shield::class, 'SecurityShield');
}
