<?php
namespace App\Modules\Addons;

use PDO;
use Exception;

class WooCommerceEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS shop_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                sku VARCHAR(100) DEFAULT NULL,
                stock_quantity INT DEFAULT 0,
                image_url VARCHAR(500) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'publish',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS shop_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_number VARCHAR(50) NOT NULL UNIQUE,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                shipping_address TEXT NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS shop_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                quantity INT NOT NULL,
                FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function getProducts(int $limit = 20, int $offset = 0): array {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_products WHERE status = 'publish' ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProduct(array $data): int {
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($data['title'])));
        $stmt = $this->pdo->prepare("
            INSERT INTO shop_products (title, slug, description, price, sku, stock_quantity, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $slug,
            $data['description'] ?? '',
            $data['price'],
            $data['sku'] ?? 'SKU-' . time(),
            $data['stock_quantity'] ?? 10,
            $data['image_url'] ?? ''
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function processCheckout(array $customer, array $cartItems): array {
        if (empty($cartItems)) {
            throw new Exception("Cannot process checkout with an empty cart.");
        }

        $this->pdo->beginTransaction();
        try {
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($cartItems as $item) {
                $stmt = $this->pdo->prepare("SELECT * FROM shop_products WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found.");
                }

                if ($product['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for product: " . $product['title']);
                }

                $lineTotal = $product['price'] * $item['quantity'];
                $totalAmount += $lineTotal;

                $itemsToInsert[] = [
                    'product_id' => $product['id'],
                    'name' => $product['title'],
                    'price' => $product['price'],
                    'quantity' => $item['quantity']
                ];

                // Deduct stock
                $updateStock = $this->pdo->prepare("UPDATE shop_products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $updateStock->execute([$item['quantity'], $product['id']]);
            }

            $orderNum = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $orderStmt = $this->pdo->prepare("
                INSERT INTO shop_orders (order_number, customer_name, customer_email, shipping_address, total_amount, status)
                VALUES (?, ?, ?, ?, ?, 'processing')
            ");
            $orderStmt->execute([
                $orderNum,
                $customer['name'],
                $customer['email'],
                $customer['address'],
                $totalAmount
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare("
                INSERT INTO shop_order_items (order_id, product_id, product_name, price, quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($itemsToInsert as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity']
                ]);
            }

            $this->pdo->commit();
            return ['success' => true, 'order_number' => $orderNum, 'total' => $totalAmount];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}