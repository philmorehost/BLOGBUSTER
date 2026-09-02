<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use PDO;
use App\Modules\Auth\AuthEngine;
use App\Modules\AI\DeepSeekEngine;
use App\Modules\Addons\WooCommerceEngine;
use App\Modules\Addons\WPFormsEngine;
use Exception;

class AdminController {
    private PDO $pdo;
    private AuthEngine $auth;
    private DeepSeekEngine $deepSeek;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->auth = new AuthEngine($pdo);
        $this->deepSeek = new DeepSeekEngine($pdo);
    }

    public function handleRequest(string $action, array $request, array $session): void {
        if ($action === 'login') {
            $this->renderLogin($request);
            return;
        }

        // Require Auth for all other actions
        if (!$this->auth->check()) {
            header('Location: /admin?action=login');
            exit;
        }

        if ($action === 'logout') {
            $this->auth->logout();
            header('Location: /admin?action=login');
            exit;
        }

        switch ($action) {
            case 'dashboard':
            default:
                $this->renderDashboard();
                break;

            case 'posts':
                $this->renderPostsList();
                break;

            case 'edit_post':
                $this->handlePostEdit($request);
                break;

            case 'ai_generate_seo':
                $this->handleAiSeo($request);
                break;

            case 'orders':
                $this->renderOrders();
                break;

            case 'form_entries':
                $this->renderFormEntries();
                break;

            case 'settings':
                $this->handleSettings($request);
                break;
        }
    }

    private function renderLogin(array $request): void {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = $request['username'] ?? '';
            $pass = $request['password'] ?? '';
            if ($this->auth->login($user, $pass)) {
                header('Location: /admin?action=dashboard');
                exit;
            } else {
                $error = "Invalid credentials. Please try again.";
            }
        }

        include __DIR__ . '/../../../public/admin/templates/login.php';
    }

    private function renderDashboard(): void {
        $postCount = $this->pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $orderCount = $this->pdo->query("SELECT COUNT(*) FROM shop_orders")->fetchColumn();
        $entryCount = $this->pdo->query("SELECT COUNT(*) FROM wp_form_entries")->fetchColumn();

        $recentPosts = $this->pdo->query("SELECT id, title, status, created_at FROM posts ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../../../public/admin/templates/dashboard.php';
    }

    private function renderPostsList(): void {
        $posts = $this->pdo->query("SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/../../../public/admin/templates/posts_list.php';
    }

    private function handlePostEdit(array $request): void {
        $id = isset($request['id']) ? (int)$request['id'] : null;
        $post = null;
        $message = null;

        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($request['title'] ?? '');
            $slug = trim($request['slug'] ?? '') ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $content = $request['content'] ?? '';
            $status = $request['status'] ?? 'draft';
            $category = $request['category_slug'] ?? 'uncategorized';

            if ($id) {
                $stmt = $this->pdo->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, status = ?, category_slug = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $content, $status, $category, $id]);
                $message = "Post updated successfully!";
            } else {
                $userId = $_SESSION['user_id'] ?? 1;
                $stmt = $this->pdo->prepare("INSERT INTO posts (user_id, title, slug, content, status, category_slug) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $title, $slug, $content, $status, $category]);
                $id = (int)$this->pdo->lastInsertId();
                $message = "Post created successfully!";
            }

            $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        include __DIR__ . '/../../../public/admin/templates/post_edit.php';
    }

    private function handleAiSeo(array $request): void {
        header('Content-Type: application/json');
        try {
            $title = $request['title'] ?? '';
            $content = $request['content'] ?? '';

            if (empty($title) || empty($content)) {
                echo json_encode(['error' => 'Title and Content are required for AI analysis']);
                exit;
            }

            $seoData = $this->deepSeek->generateSeoMetadata($title, $content);
            echo json_encode(['success' => true, 'data' => $seoData]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderOrders(): void {
        $stmt = $this->pdo->query("SELECT * FROM shop_orders ORDER BY id DESC");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/../../../public/admin/templates/orders.php';
    }

    private function renderFormEntries(): void {
        $stmt = $this->pdo->query("SELECT e.*, f.title as form_title FROM wp_form_entries e LEFT JOIN wp_forms f ON e.form_id = f.id ORDER BY e.id DESC");
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/../../../public/admin/templates/form_entries.php';
    }

    private function handleSettings(array $request): void {
        $message = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'site_title' => $request['site_title'] ?? '',
                'site_tagline' => $request['site_tagline'] ?? '',
                'site_url' => $request['site_url'] ?? '',
                'active_theme' => $request['active_theme'] ?? 'blogbuster-default',
                'deepseek_api_key' => $request['deepseek_api_key'] ?? '',
                'deepseek_model' => $request['deepseek_model'] ?? 'deepseek-chat',
                'redis_host' => $request['redis_host'] ?? '127.0.0.1',
                'redis_port' => $request['redis_port'] ?? '6379'
            ];

            foreach ($settings as $key => $val) {
                $stmt = $this->pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                    $stmt = $this->pdo->prepare("INSERT INTO options (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
                }
                $stmt->execute([$key, $val]);
            }
            $message = "Settings updated successfully!";
        }

        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options");
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        include __DIR__ . '/../../../public/admin/templates/settings.php';
    }
}
