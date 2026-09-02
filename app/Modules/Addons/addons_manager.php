<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin');
    exit;
}

$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4", $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

require_once __DIR__ . '/../Modules/Addons/WooCommerceEngine.php';
require_once __DIR__ . '/../Modules/Addons/WPFormsEngine.php';

use App\Modules\Addons\WooCommerceEngine;
use App\Modules\Addons\WPFormsEngine;

$woo = new WooCommerceEngine($pdo);
$wpforms = new WPFormsEngine($pdo);

$msg = '';
$error = '';

// Handle WooCommerce Product Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_product'])) {
    try {
        $woo->createProduct([
            'title' => trim($_POST['title']),
            'price' => (float)$_POST['price'],
            'description' => trim($_POST['description']),
            'stock_quantity' => (int)$_POST['stock']
        ]);
        $msg = "Product successfully added to WooCommerce Catalog!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle WPForms Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_wpform'])) {
    try {
        $title = trim($_POST['form_title']);
        $fieldsJson = trim($_POST['fields_json']);
        $fields = json_decode($fieldsJson, true);

        if (!$fields) {
            throw new Exception("Invalid JSON structure for WPForms fields definition.");
        }

        $formId = $wpforms->createForm($title, $fields);
        $msg = "WPForm #{$formId} created successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$products = $woo->getProducts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Modular Add-ons Suite - BLOGBUSTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans p-6">

    <div class="max-w-6xl mx-auto space-y-8">
        
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-3xl font-black text-indigo-400">Modular Add-ons Suite</h1>
                <p class="text-slate-400 text-sm">WooCommerce Catalog Engine & WPForms Drag-and-Drop Processor</p>
            </div>
            <a href="/admin" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition">← Return to Dashboard</a>
        </header>

        <?php if ($msg): ?>
            <div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-500/20 border border-rose-500 text-rose-300 p-4 rounded-xl font-medium"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Section 1: WooCommerce Product Creation -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-indigo-400 border-b border-slate-800 pb-2">WooCommerce: Quick Product Creator</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="create_product" value="1">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Product Title</label>
                        <input type="text" name="title" required placeholder="e.g. Premium Theme License" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Price ($)</label>
                            <input type="number" step="0.01" name="price" required placeholder="49.99" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Stock Quantity</label>
                            <input type="number" name="stock" value="100" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white text-sm" placeholder="Product details..."></textarea>
                    </div>

                    <button type="submit" class="w-full bg-indigo-500 hover:bg-indigo-600 font-bold py-2.5 rounded-lg transition text-white">Add Product to Store</button>
                </form>

                <div class="mt-6 border-t border-slate-800 pt-4">
                    <h3 class="text-sm font-bold text-slate-400 mb-3">Active Catalog Items (<?= count($products) ?>)</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <?php foreach ($products as $p): ?>
                            <div class="flex justify-between items-center bg-slate-800 p-2.5 rounded border border-slate-700/50 text-xs">
                                <span class="font-bold text-white"><?= htmlspecialchars($p['title']) ?></span>
                                <span class="text-emerald-400 font-mono">$<?= number_format($p['price'], 2) ?> (Stock: <?= $p['stock_quantity'] ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Section 2: WPForms Builder -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4 shadow-lg">
                <h2 class="text-xl font-bold text-indigo-400 border-b border-slate-800 pb-2">WPForms: Visual Form Generator</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="create_wpform" value="1">

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Form Name</label>
                        <input type="text" name="form_title" required placeholder="e.g. Lead Generation Form" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Form Fields Schema (JSON)</label>
                        <textarea name="fields_json" rows="8" class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-indigo-300 font-mono text-xs">[
  { "name": "full_name", "label": "Full Name", "type": "text", "required": true },
  { "name": "user_email", "label": "Email Address", "type": "email", "required": true },
  { "name": "message", "label": "Your Inquiry", "type": "textarea", "required": false }
]</textarea>
                    </div>

                    <button type="submit" class="w-full bg-indigo-500 hover:bg-indigo-600 font-bold py-2.5 rounded-lg transition text-white">Save Form Definition</button>
                </form>
            </div>

        </div>

    </div>

</body>
</html>