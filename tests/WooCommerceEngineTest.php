<?php
declare(strict_types=1);

namespace Tests;

PHPUnit\Framework\TestCase;
use App\Modules\Addons\WooCommerceEngine;
use PDO;
use Exception;

final class WooCommerceEngineTest extends TestCase {
    private PDO $pdo;
    private WooCommerceEngine $woo;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->woo = new WooCommerceEngine($this->pdo);
    }

    public function testCreateProductAndProcessCheckout(): void {
        $productId = $this->woo->createProduct([
            'title' => 'Enterprise License',
            'price' => 199.99,
            'description' => 'Full access license key.',
            'stock_quantity' => 5
        ]);

        $customer = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'address' => '123 Tech Lane'
        ];

        $cart = [
            ['product_id' => $productId, 'quantity' => 2]
        ];

        $result = $this->woo->processCheckout($customer, $cart);

        $this->assertTrue($result['success']);
        $this->assertEquals(399.98, $result['total']);

        // Verify stock was updated
        $stmt = $this->pdo->prepare("SELECT stock_quantity FROM shop_products WHERE id = ?");
        $stmt->execute([$productId]);
        $remainingStock = $stmt->fetchColumn();

        $this->assertEquals(3, (int)$remainingStock);
    }

    public function testCheckoutFailsOnInsufficientStock(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $productId = $this->woo->createProduct([
            'title' => 'Limited Edition Item',
            'price' => 50.00,
            'stock_quantity' => 1
        ]);

        $this->woo->processCheckout([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'address' => 'Main St'
        ], [
            ['product_id' => $productId, 'quantity' => 5]
        ]);
    }
}