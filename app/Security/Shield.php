<?php
class SecurityShield {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function inspectRequest() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // 1. Check IP Blacklist
        $stmt = $this->pdo->prepare("SELECT * FROM sec_blocked_ips WHERE ip_address = ? AND blocked_until > NOW()");
        $stmt->execute([$ip]);
        if ($stmt->fetch()) {
            http_response_code(403);
            exit("<h1 style='text-align:center;margin-top:20%'>403 Forbidden: Your IP has been blocked by Security Shield.</h1>");
        }

        // 2. Country Blacklist Enforcement
        $country = $_SERVER['GEOIP_COUNTRY_CODE'] ?? 'UN';
        $cStmt = $this->pdo->prepare("SELECT status FROM sec_country_rules WHERE country_code = ?");
        $cStmt->execute([$country]);
        $rule = $cStmt->fetchColumn();
        if ($rule === 'blacklisted') {
            http_response_code(403);
            exit("Access Denied: Geographic region blocked.");
        }
    }

    public function recordFailedLogin($username, $ip) {
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare("INSERT INTO sec_login_logs (ip_address, username, status, attempted_at) VALUES (?, ?, 'failed', ?)")->execute([$ip, $username, $now]);

        // Evaluate limits
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sec_login_logs WHERE ip_address = ? AND status = 'failed' AND attempted_at >= NOW() - INTERVAL 15 MINUTE");
        $stmt->execute([$ip]);
        if ($stmt->fetchColumn() >= 5) {
            // Apply 1-Day Block
            $this->pdo->prepare("INSERT INTO sec_blocked_ips (ip_address, reason, blocked_until) VALUES (?, 'Exceeded Max Failures', NOW() + INTERVAL 1 DAY)")->execute([$ip]);
        }
    }
}