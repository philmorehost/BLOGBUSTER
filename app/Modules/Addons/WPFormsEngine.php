<?php
namespace App\Modules\Addons;

use PDO;
use Exception;

class WPFormsEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS wp_forms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    form_fields TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS wp_form_entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    form_id INTEGER NOT NULL,
                    entry_data TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS wp_forms (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    form_fields JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS wp_form_entries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    form_id INT NOT NULL,
                    entry_data JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (form_id) REFERENCES wp_forms(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }

    public function createForm(string $title, array $fields): int {
        $stmt = $this->pdo->prepare("INSERT INTO wp_forms (title, form_fields) VALUES (?, ?)");
        $stmt->execute([$title, json_encode($fields)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getForm(int $formId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM wp_forms WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($form) {
            $form['fields'] = json_decode($form['form_fields'], true) ?: [];
        }

        return $form ?: null;
    }

    public function renderFormHtml(int $formId): string {
        $form = $this->getForm($formId);
        if (!$form) return "<p class=\"text-rose-500\">Form ID {$formId} not found.</p>";

        $html = "<form method=\"POST\" action=\"\" class=\"space-y-4 bg-slate-900 border border-slate-800 p-6 rounded-xl\">\n";
        $html .= "  <input type=\"hidden\" name=\"wpforms_submit_id\" value=\"{$form['id']}\">\n";
        $html .= "  <h3 class=\"text-xl font-bold text-indigo-400 mb-4\">" . htmlspecialchars($form['title']) . "</h3>\n";

        foreach ($form['fields'] as $field) {
            $label = htmlspecialchars($field['label']);
            $name = htmlspecialchars($field['name']);
            $type = $field['type'] ?? 'text';
            $required = !empty($field['required']) ? 'required' : '';

            $html .= "  <div>\n";
            $html .= "    <label class=\"block text-xs font-semibold text-slate-300 mb-1\">{$label}</label>\n";

            if ($type === 'textarea') {
                $html .= "    <textarea name=\"data[{$name}]\" {$required} rows=\"4\" class=\"w-full bg-slate-800 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-indigo-500 outline-none\"></textarea>\n";
            } else {
                $html .= "    <input type=\"{$type}\" name=\"data[{$name}]\" {$required} class=\"w-full bg-slate-800 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-indigo-500 outline-none\">\n";
            }

            $html .= "  </div>\n";
        }

        $html .= "  <button type=\"submit\" class=\"w-full bg-indigo-500 hover:bg-indigo-600 font-bold py-2.5 rounded-lg text-white transition\">Submit Form</button>\n";
        $html .= "</form>\n";

        return $html;
    }

    public function processSubmission(int $formId, array $submittedData): bool {
        $form = $this->getForm($formId);
        if (!$form) throw new Exception("Form definition does not exist.");

        $stmt = $this->pdo->prepare("INSERT INTO wp_form_entries (form_id, entry_data) VALUES (?, ?)");
        return $stmt->execute([$formId, json_encode($submittedData)]);
    }
}
