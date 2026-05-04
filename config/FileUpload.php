<?php
/**
 * File Upload Handler
 * Securely handles COR (Certificate of Registration) file uploads
 * Works on XAMPP and Hostinger
 */

require_once __DIR__ . '/../config/Environment.php';

class FileUpload {
    private string $uploadDir;
    private int $maxFileSize;
    private array $allowedExtensions;
    private array $allowedMimeTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    public function __construct() {
        $this->uploadDir = Environment::get('UPLOAD_DIR', 'uploads');
        $this->maxFileSize = (int) Environment::get('MAX_FILE_SIZE', 5242880); // 5MB
        
        $extensions = Environment::get('ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png');
        $this->allowedExtensions = array_map('trim', explode(',', $extensions));

        // Create upload directory if doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Create user-specific subdirectory for organization
        $this->uploadDir = $this->uploadDir . '/cors';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Validate uploaded file
     */
    public function validate(array $file): array {
        $errors = [];

        // Check if file was uploaded
        if (empty($file['tmp_name'])) {
            $errors[] = 'No file uploaded';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed (' . $this->formatBytes($this->maxFileSize) . ')';
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = 'File type not allowed. Allowed: ' . implode(', ', $this->allowedExtensions);
        }

        // Check MIME type
        $mimeType = mime_content_type($file['tmp_name']);
        if (!array_key_exists($mimeType, $this->allowedMimeTypes)) {
            $errors[] = 'Invalid file MIME type: ' . $mimeType;
        }

        // Validate file integrity
        if (!$this->isValidFile($file['tmp_name'])) {
            $errors[] = 'File appears to be corrupted or invalid';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType,
            'extension' => $extension
        ];
    }

    /**
     * Upload and store file
     */
    public function upload(array $file, int $studentId): array {
        // Validate first
        $validation = $this->validate($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        try {
            // Generate secure filename
            $filename = $this->generateSecureFilename($studentId, $file['name']);
            $filepath = $this->uploadDir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return [
                    'success' => false,
                    'errors' => ['Failed to move uploaded file']
                ];
            }

            // Set proper permissions
            chmod($filepath, 0644);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'mime_type' => $validation['mime_type']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Generate secure filename with student ID prefix
     */
    private function generateSecureFilename(int $studentId, string $originalName): string {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "{$studentId}_COR_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Check if file is valid
     */
    private function isValidFile(string $filepath): bool {
        // Check if file is readable
        if (!is_readable($filepath)) {
            return false;
        }

        // For PDFs, check PDF signature
        $handle = fopen($filepath, 'rb');
        $header = fread($handle, 4);
        fclose($handle);

        // Check for PDF magic number
        if ($header === '%PDF') {
            return true;
        }

        // For images, validate using getimagesize
        if (getimagesize($filepath) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Delete file
     */
    public function delete(string $filepath): bool {
        if (file_exists($filepath) && is_readable($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Get file content as base64 (for API transmission)
     */
    public function getBase64(string $filepath): string {
        if (!file_exists($filepath)) {
            throw new Exception("File not found: {$filepath}");
        }
        return base64_encode(file_get_contents($filepath));
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get upload directory
     */
    public function getUploadDir(): string {
        return $this->uploadDir;
    }
}
