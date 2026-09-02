<?php
declare(strict_types=1);

namespace Tests;

PHPUnit\Framework\TestCase;
use App\Modules\Addons\WPFormsEngine;
use PDO;

final class WPFormsEngineTest extends TestCase {
    private PDO $pdo;
    private WPFormsEngine $formsEngine;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->formsEngine = new WPFormsEngine($this->pdo);
    }

    public function testCreateFormAndRenderHtml(): void {
        $fields = [
            ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]
        ];

        $formId = $this->formsEngine->createForm('Contact Us', $fields);
        $html = $this->formsEngine->renderFormHtml($formId);

        $this->assertStringContainsString('Contact Us', $html);
        $this->assertStringContainsString('name="wpforms_submit_id" value="' . $formId . '"', $html);
        $this->assertStringContainsString('name="data[full_name]"', $html);
        $this->assertStringContainsString('name="data[email]"', $html);
    }

    public function testProcessSubmission(): void {
        $formId = $this->formsEngine->createForm('Newsletter', [
            ['name' => 'subscriber_email', 'label' => 'Email', 'type' => 'email']
        ]);

        $success = $this->formsEngine->processSubmission($formId, ['subscriber_email' => 'user@example.com']);
        $this->assertTrue($success);

        $stmt = $this->pdo->prepare("SELECT entry_data FROM wp_form_entries WHERE form_id = ?");
        $stmt->execute([$formId]);
        $entryJson = $stmt->fetchColumn();

        $this->assertJsonStringEqualsJsonString(
            json_encode(['subscriber_email' => 'user@example.com']),
            $entryJson
        );
    }
}