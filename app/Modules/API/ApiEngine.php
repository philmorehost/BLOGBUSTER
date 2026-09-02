<?php
declare(strict_types=1);

namespace App\Modules\API;

use PDO;
use Exception;

class ApiEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest(string $endpoint, string $method, array $queryParams = [], array $bodyParams = []): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        try {
            $endpoint = trim($endpoint, '/');
            switch ($endpoint) {
                case 'posts':
                    if ($method === 'GET') {
                        $this->getPosts($queryParams);
                    } else {
                        $this->sendJson(['error' => 'Method not allowed'], 405);
                    }
                    break;

                case 'post':
                    if ($method === 'GET') {
                        $this->getSinglePost($queryParams);
                    } else {
                        $this->sendJson(['error' => 'Method not allowed'], 405);
                    }
                    break;

                case 'categories':
                    if ($method === 'GET') {
                        $this->getCategories();
                    } else {
                        $this->sendJson(['error' => 'Method not allowed'], 405);
                    }
                    break;

                case 'settings':
                    if ($method === 'GET') {
                        $this->getPublicSettings();
                    } else {
                        $this->sendJson(['error' => 'Method not allowed'], 405);
                    }
                    break;

                default:
                    $this->sendJson(['error' => 'Endpoint not found'], 404);
                    break;
            }
        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }

    private function getPosts(array $params): void {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $category = $params['category'] ?? null;

        if ($category) {
            $stmt = $this->pdo->prepare("SELECT id, title, slug, content, category_slug, image_webp, views_count, created_at FROM posts WHERE status = 'published' AND category_slug = ? ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $category, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("SELECT id, title, slug, content, category_slug, image_webp, views_count, created_at FROM posts WHERE status = 'published' ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
        }

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $this->pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");
        $total = (int)$countStmt->fetchColumn();

        $this->sendJson([
            'status' => 'success',
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_posts' => $total,
                'total_pages' => ceil($total / $limit)
            ],
            'data' => $posts
        ]);
    }

    private function getSinglePost(array $params): void {
        $slug = $params['slug'] ?? null;
        if (!$slug) {
            $this->sendJson(['error' => 'Parameter "slug" is required'], 400);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT p.*, u.username as author_name, u.job_title as author_job_title FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.slug = ? AND p.status = 'published'");
        $stmt->execute([$slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            $this->sendJson(['error' => 'Post not found'], 404);
            return;
        }

        // Increment view counter
        $updateStmt = $this->pdo->prepare("UPDATE posts SET views_count = views_count + 1 WHERE id = ?");
        $updateStmt->execute([$post['id']]);

        $this->sendJson([
            'status' => 'success',
            'data' => $post
        ]);
    }

    private function getCategories(): void {
        $stmt = $this->pdo->query("SELECT DISTINCT category_slug, COUNT(*) as post_count FROM posts WHERE status = 'published' GROUP BY category_slug");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->sendJson([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    private function getPublicSettings(): void {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM options WHERE setting_key IN ('site_title', 'site_tagline', 'active_theme')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->sendJson([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    private function sendJson(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
