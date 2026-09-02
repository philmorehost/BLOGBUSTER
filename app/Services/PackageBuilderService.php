<?php
class PackageBuilderService {

    public static function createReleasePackage($sourceDir, $outputZipPath, $licenseTier = 'standard') {
        $zip = new ZipArchive();
        if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            // Exclude enterprise-only features if the license is standard
            if ($licenseTier === 'standard' && strpos($relativePath, 'modules/enterprise/') === 0) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return file_exists($outputZipPath);
    }
}