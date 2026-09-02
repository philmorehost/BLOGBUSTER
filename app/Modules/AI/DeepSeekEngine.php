<?php
declare(strict_types=1);

namespace App\Modules\AI;

use PDO;
use Exception;

class DeepSeekEngine {
    private PDO $pdo;
    private string $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getApiKey(): string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM options WHERE setting_key = 'deepseek_api_key'");
        $stmt->execute();
        return (string)($stmt->fetchColumn() ?: '');
    }

    public function getModel(): string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM options WHERE setting_key = 'deepseek_model'");
        $stmt->execute();
        return (string)($stmt->fetchColumn() ?: 'deepseek-chat');
    }

    public function generateSeoMetadata(string $title, string $content): array {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("DeepSeek API Key is missing. Configure it in Admin Settings.");
        }

        $prompt = "You are an expert SEO specialist. Analyze the following article title and content.
Return a valid JSON object strictly with the following keys:
- "meta_title": A compelling SEO title (under 60 characters).
- "meta_description": A concise, catchy SEO meta description (under 160 characters).
- "focus_keywords": A comma-separated string of top 5 relevant keywords.
- "excerpt": A 2-sentence executive summary.

Title: {$title}
Content: {$content}

Respond ONLY with raw JSON, no markdown code blocks.";

        $response = $this->callApi($prompt, $apiKey);
        $cleanJson = trim(preg_replace('/^```json|```$/m', '', $response));
        $decoded = json_decode($cleanJson, true);

        if (!$decoded || !isset($decoded['meta_title'])) {
            throw new Exception("Failed to parse JSON response from DeepSeek API.");
        }

        return $decoded;
    }

    public function generateArticleDraft(string $topic, string $tone = 'professional'): string {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("DeepSeek API Key is missing. Configure it in Admin Settings.");
        }

        $prompt = "Write a comprehensive, engaging, highly informative article on the topic: "{$topic}".
Use a {$tone} tone. Include well-structured subheadings (H2, H3) and informative paragraphs suitable for modern publishing. Return HTML formatted content.";

        return $this->callApi($prompt, $apiKey);
    }

    private function callApi(string $prompt, string $apiKey): string {
        $model = $this->getModel();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant specialized in SEO and content publishing.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("DeepSeek API HTTP Error [{$httpCode}]: " . $response);
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
