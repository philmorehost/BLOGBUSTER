<?php
namespace App\Modules\Performance;

use Exception;

class ImageOptimizer {
    private string $uploadDir;
    private int $qualityWebP;
    private int $qualityAvif;

    public function __construct(string $uploadDir = __DIR__ . '/../../../content/uploads/', int $qualityWebP = 82, int $qualityAvif = 75) {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->qualityWebP = $qualityWebP;
        $this->qualityAvif = $qualityAvif;

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Process an uploaded image file, automatically generating WebP and AVIF variants.
     * 
     * @param array $file $_FILES array element
     * @return array Relative path URLs to original, webp, and avif images
     */
    public function processUpload(array $file): array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Invalid file upload or upload error code: " . ($file['error'] ?? 'unknown'));
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extension, $allowed)) {
            throw new Exception("Unsupported image format: {$extension}. Only JPG, PNG, and GIF are supported.");
        }

        $filename = pathinfo($file['name'], PATHINFO_FILENAME);
        $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename) . '_' . time();
        
        $originalRelative = 'content/uploads/' . $cleanFilename . '.' . $extension;
        $originalDestination = $this->uploadDir . $cleanFilename . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $originalDestination)) {
            throw new Exception("Failed to move uploaded file to target upload directory.");
        }

        $webpRelative = 'content/uploads/' . $cleanFilename . '.webp';
        $avifRelative = 'content/uploads/' . $cleanFilename . '.avif';

        $this->convertToWebP($originalDestination, $this->uploadDir . $cleanFilename . '.webp');
        $this->convertToAvif($originalDestination, $this->uploadDir . $cleanFilename . '.avif');

        return [
            'original' => '/' . $originalRelative,
            'webp'     => file_exists($this->uploadDir . $cleanFilename . '.webp') ? '/' . $webpRelative : null,
            'avif'     => file_exists($this->uploadDir . $cleanFilename . '.avif') ? '/' . $avifRelative : null
        ];
    }

    /**
     * Convert source image to WebP using Imagick or GD.
     */
    private function convertToWebP(string $sourcePath, string $destPath): bool {
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($sourcePath);
                $im->setImageFormat('webp');
                $im->setImageCompressionQuality($this->qualityWebP);
                $im->stripImage();
                return $im->writeImage($destPath);
            } catch (Exception $e) {
                // Fallback to GD if Imagick fails
            }
        }

        if (function_exists('imagecreatefromjpeg') && function_exists('imagewebp')) {
            $image = $this->createGdImageFromPath($sourcePath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                $result = imagewebp($image, $destPath, $this->qualityWebP);
                imagedestroy($image);
                return $result;
            }
        }

        return false;
    }

    /**
     * Convert source image to AVIF using Imagick or GD.
     */
    private function convertToAvif(string $sourcePath, string $destPath): bool {
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($sourcePath);
                if (in_array('AVIF', $im->queryFormats())) {
                    $im->setImageFormat('avif');
                    $im->setImageCompressionQuality($this->qualityAvif);
                    $im->stripImage();
                    return $im->writeImage($destPath);
                }
            } catch (Exception $e) {
                // Fallback to GD
            }
        }

        if (function_exists('imageavif')) {
            $image = $this->createGdImageFromPath($sourcePath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                $result = imageavif($image, $destPath, $this->qualityAvif);
                imagedestroy($image);
                return $result;
            }
        }

        return false;
    }

    private function createGdImageFromPath(string $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                return @imagecreatefromjpeg($path);
            case 'png':
                return @imagecreatefrompng($path);
            case 'gif':
                return @imagecreatefromgif($path);
            default:
                return null;
        }
    }

    /**
     * Renders responsive HTML5 <picture> element with WebP and AVIF fallbacks.
     */
    public static function renderPictureHtml(string $originalSrc, ?string $webpSrc = null, ?string $avifSrc = null, string $alt = '', string $class = ''): string {
        $html = '<picture>';
        if ($avifSrc) {
            $html .= '<source srcset="' . htmlspecialchars($avifSrc) . '" type="image/avif">';
        }
        if ($webpSrc) {
            $html .= '<source srcset="' . htmlspecialchars($webpSrc) . '" type="image/webp">';
        }
        $html .= '<img src="' . htmlspecialchars($originalSrc) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '" loading="lazy" decoding="async">';
        $html .= '</picture>';
        return $html;
    }
}