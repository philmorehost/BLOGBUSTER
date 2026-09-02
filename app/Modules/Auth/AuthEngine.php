<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use PDO;

class AuthEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->pdo = $pdo;
    }

    public function login(string $username, string $password): bool {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            return true;
        }

        return false;
    }

    public function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public function logout(): void {
        unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username']);
        session_destroy();
    }
}