<?php
declare(strict_types=1);

namespace App\Modules\Media;

use PDO;
use RuntimeException;

class MediaManager {
    private PDO $pdo;
    private string $uploadDir;

    public function __construct(PDO $pdo, string $uploadDir) {
        $this->pdo = $pdo;
        $this->uploadDir = rtrim($uploadDir, '/\\');
    }

    public function upload(array $file): string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload error code: " . $file['error']);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes, true)) {
            throw new RuntimeException("Unsupported image format.");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;
        $targetPath = $this->uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException("Failed to move uploaded file.");
        }

        // Save entry in DB
        $stmt = $this->pdo->prepare("INSERT INTO media (filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?)");
        $relativePath = '/uploads/' . $filename;
        $stmt->execute([$filename, $relativePath, $file['type'], $file['size']]);

        return $relativePath;
    }
}