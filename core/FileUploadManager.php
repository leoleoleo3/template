<?php
/**
 * FileUploadManager Class
 *
 * Provides secure file upload handling with:
 * - MIME type validation using file content (not extension)
 * - File size limits
 * - Extension whitelist
 * - Filename sanitization
 * - Upload error handling
 *
 * @package TEMPLATE
 * @version 1.0.0
 */

class FileUploadManager
{
    private static ?FileUploadManager $instance = null;

    // Allowed MIME types grouped by category
    private array $allowedMimeTypes = [
        'image' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            // SVG removed: it can contain embedded JavaScript (XSS vector)
            'image/x-icon' => ['ico'],
            'image/vnd.microsoft.icon' => ['ico'],
        ],
        'document' => [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv'],
        ],
    ];

    // Magic bytes signatures for common file types
    private array $magicBytes = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1a\n"],
        'image/gif' => ["GIF87a", "GIF89a"],
        'image/webp' => ["RIFF"],
        'application/pdf' => ["%PDF"],
        'image/x-icon' => ["\x00\x00\x01\x00", "\x00\x00\x02\x00"],
        'image/vnd.microsoft.icon' => ["\x00\x00\x01\x00", "\x00\x00\x02\x00"],
    ];

    // Default max file size (5MB)
    private int $maxFileSize = 5242880;

    // Upload directory base path
    private string $uploadBasePath;

    private function __construct()
    {
        $this->uploadBasePath = dirname(__DIR__) . '/public/uploads';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Upload a file with security validation
     *
     * @param array $file The $_FILES array element
     * @param string $category Category of allowed files ('image', 'document', or specific types)
     * @param string $subDirectory Subdirectory within uploads folder
     * @param array $options Additional options (max_size, custom_name, etc.)
     * @return array ['success' => bool, 'path' => string|null, 'filename' => string|null, 'error' => string|null]
     */
    public function uploadFile(array $file, string $category = 'image', string $subDirectory = '', array $options = []): array
    {
        // Check for upload errors
        $errorCheck = $this->checkUploadError($file);
        if (!$errorCheck['success']) {
            return $errorCheck;
        }

        // Validate file size
        $maxSize = $options['max_size'] ?? $this->maxFileSize;
        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'error' => 'File size exceeds maximum allowed (' . $this->formatBytes($maxSize) . ')'
            ];
        }

        // Validate MIME type using multiple methods
        $mimeValidation = $this->validateMimeType($file['tmp_name'], $category);
        if (!$mimeValidation['success']) {
            return $mimeValidation;
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extValidation = $this->validateExtension($extension, $mimeValidation['mime_type'], $category);
        if (!$extValidation['success']) {
            return $extValidation;
        }

        // Generate secure filename
        $filename = $this->generateSecureFilename($file['name'], $options['custom_name'] ?? null);

        // Prepare upload directory
        $uploadDir = $this->prepareUploadDirectory($subDirectory);
        if (!$uploadDir['success']) {
            return $uploadDir;
        }

        // Move uploaded file
        $finalPath = $uploadDir['path'] . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }

        // Compress image if applicable
        $finalSize = $file['size'];
        $compress = $options['compress'] ?? true;
        $maxWidth = $options['max_width'] ?? 1920;
        $quality = $options['quality'] ?? 75;
        if ($compress && strpos($mimeValidation['mime_type'], 'image/') === 0) {
            $compressResult = $this->compressImage($finalPath, $mimeValidation['mime_type'], $maxWidth, $quality);
            if ($compressResult['success']) {
                $finalSize = $compressResult['size'];
            }
        }

        // Set proper permissions
        chmod($finalPath, 0644);

        // Return relative path for storage
        $relativePath = ($subDirectory ? $subDirectory . '/' : '') . $filename;

        return [
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename,
            'full_path' => $finalPath,
            'mime_type' => $mimeValidation['mime_type'],
            'size' => $finalSize,
            'original_size' => $file['size']
        ];
    }

    /**
     * Validate MIME type using multiple detection methods
     *
     * @param string $filePath Path to the file
     * @param string $category Expected category
     * @return array Validation result
     */
    public function validateMimeType(string $filePath, string $category = 'image'): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        // Method 1: Use finfo (most reliable)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($filePath);

        // Method 2: Verify with magic bytes for known types
        $magicBytesValid = $this->verifyMagicBytes($filePath, $detectedMime);

        // Method 3: For images, try getimagesize()
        if ($category === 'image' && strpos($detectedMime, 'image/') === 0) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid image file - failed image validation'
                ];
            }
        }

        // Check if detected MIME is in allowed list
        $allowedMimes = $this->getAllowedMimesForCategory($category);
        if (!isset($allowedMimes[$detectedMime])) {
            return [
                'success' => false,
                'error' => 'File type not allowed. Detected: ' . $detectedMime
            ];
        }

        return [
            'success' => true,
            'mime_type' => $detectedMime,
            'magic_bytes_verified' => $magicBytesValid
        ];
    }

    /**
     * Verify file content using magic bytes
     *
     * @param string $filePath Path to file
     * @param string $expectedMime Expected MIME type
     * @return bool True if magic bytes match
     */
    private function verifyMagicBytes(string $filePath, string $expectedMime): bool
    {
        if (!isset($this->magicBytes[$expectedMime])) {
            return true; // No magic bytes defined, skip check
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        foreach ($this->magicBytes[$expectedMime] as $signature) {
            if (strpos($header, $signature) === 0) {
                return true;
            }
        }

        // Special case for WEBP (RIFF format)
        if ($expectedMime === 'image/webp') {
            return strpos($header, 'RIFF') === 0 && strpos($header, 'WEBP') !== false;
        }

        return false;
    }

    /**
     * Validate file extension against MIME type
     *
     * @param string $extension File extension
     * @param string $mimeType Detected MIME type
     * @param string $category File category
     * @return array Validation result
     */
    private function validateExtension(string $extension, string $mimeType, string $category): array
    {
        $allowedMimes = $this->getAllowedMimesForCategory($category);

        if (!isset($allowedMimes[$mimeType])) {
            return [
                'success' => false,
                'error' => 'MIME type not allowed for this category'
            ];
        }

        $allowedExtensions = $allowedMimes[$mimeType];
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'success' => false,
                'error' => 'File extension does not match content type. Expected: ' . implode(', ', $allowedExtensions)
            ];
        }

        return ['success' => true];
    }

    /**
     * Get allowed MIME types for a category
     *
     * @param string $category Category name or 'all'
     * @return array MIME types array
     */
    private function getAllowedMimesForCategory(string $category): array
    {
        if ($category === 'all') {
            return array_merge(...array_values($this->allowedMimeTypes));
        }

        return $this->allowedMimeTypes[$category] ?? [];
    }

    /**
     * Generate a secure filename
     *
     * @param string $originalName Original filename
     * @param string|null $customName Custom name to use
     * @return string Secure filename
     */
    private function generateSecureFilename(string $originalName, ?string $customName = null): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($customName) {
            // Sanitize custom name
            $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $customName);
        } else {
            // Generate unique name
            $baseName = bin2hex(random_bytes(8)) . '_' . time();
        }

        return $baseName . '.' . $extension;
    }

    /**
     * Prepare upload directory
     *
     * @param string $subDirectory Subdirectory name
     * @return array Result with path
     */
    private function prepareUploadDirectory(string $subDirectory): array
    {
        $path = $this->uploadBasePath;

        if ($subDirectory) {
            // Sanitize subdirectory name
            $subDirectory = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $subDirectory);
            $path .= '/' . $subDirectory;
        }

        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create upload directory'
                ];
            }
        }

        // Create .htaccess to prevent PHP execution
        $htaccessPath = $path . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Prevent PHP execution\n";
            $htaccessContent .= "<FilesMatch \"\\.php$\">\n";
            $htaccessContent .= "    Order Deny,Allow\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n";
            $htaccessContent .= "\n# Disable script execution\n";
            $htaccessContent .= "Options -ExecCGI\n";
            $htaccessContent .= "AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .htm .shtml .sh .cgi\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        // Create index.php to prevent directory listing
        $indexPath = $path . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, '<?php // Silence is golden');
        }

        return [
            'success' => true,
            'path' => $path
        ];
    }

    /**
     * Check for upload errors
     *
     * @param array $file $_FILES element
     * @return array Error check result
     */
    private function checkUploadError(array $file): array
    {
        if (!isset($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file upload'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                return ['success' => true];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'File too large'];
            case UPLOAD_ERR_PARTIAL:
                return ['success' => false, 'error' => 'File was only partially uploaded'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file was uploaded'];
            case UPLOAD_ERR_NO_TMP_DIR:
                return ['success' => false, 'error' => 'Missing temporary folder'];
            case UPLOAD_ERR_CANT_WRITE:
                return ['success' => false, 'error' => 'Failed to write file to disk'];
            case UPLOAD_ERR_EXTENSION:
                return ['success' => false, 'error' => 'Upload stopped by extension'];
            default:
                return ['success' => false, 'error' => 'Unknown upload error'];
        }
    }

    /**
     * Delete a file from uploads directory
     *
     * @param string $relativePath Relative path from uploads directory
     * @return bool Success status
     */
    public function deleteFile(string $relativePath): bool
    {
        // Prevent directory traversal
        $relativePath = str_replace(['..', '\\'], '', $relativePath);
        $fullPath = $this->uploadBasePath . '/' . $relativePath;

        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Get the full URL path for an uploaded file
     *
     * @param string $relativePath Relative path from uploads directory
     * @return string Full URL path
     */
    public function getFileUrl(string $relativePath): string
    {
        return '/uploads/' . ltrim($relativePath, '/');
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Set maximum file size
     *
     * @param int $bytes Maximum size in bytes
     */
    public function setMaxFileSize(int $bytes): void
    {
        $this->maxFileSize = $bytes;
    }

    /**
     * Add custom MIME type to allowed list
     *
     * @param string $category Category name
     * @param string $mimeType MIME type
     * @param array $extensions Allowed extensions
     */
    public function addAllowedMimeType(string $category, string $mimeType, array $extensions): void
    {
        if (!isset($this->allowedMimeTypes[$category])) {
            $this->allowedMimeTypes[$category] = [];
        }
        $this->allowedMimeTypes[$category][$mimeType] = $extensions;
    }

    /**
     * Check if a file exists in uploads
     *
     * @param string $relativePath Relative path
     * @return bool Exists status
     */
    public function fileExists(string $relativePath): bool
    {
        $relativePath = str_replace(['..', '\\'], '', $relativePath);
        return file_exists($this->uploadBasePath . '/' . $relativePath);
    }

    /**
     * Compress an image file: resize if wider than maxWidth, reduce quality
     * Requires GD extension. Falls back gracefully if not available.
     *
     * @param string $filePath Absolute path to the image file
     * @param string $mimeType The image MIME type
     * @param int $maxWidth Maximum width in pixels (height scales proportionally)
     * @param int $quality JPEG/WebP quality (0-100), PNG compression derived from this
     * @return array ['success' => bool, 'size' => int, 'resized' => bool]
     */
    private function compressImage(string $filePath, string $mimeType, int $maxWidth = 1920, int $quality = 75): array
    {
        // Skip non-compressible formats
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
            return ['success' => false, 'reason' => 'Unsupported format for compression'];
        }

        // Require GD extension
        if (!extension_loaded('gd')) {
            return ['success' => false, 'reason' => 'GD extension not available'];
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            return ['success' => false, 'reason' => 'Cannot read image dimensions'];
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $resized = false;

        // Load image based on MIME type
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($filePath);
                break;
            default:
                return ['success' => false, 'reason' => 'Unsupported type'];
        }

        if (!$image) {
            return ['success' => false, 'reason' => 'Failed to load image'];
        }

        // Preserve transparency for PNG/WebP
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        // Resize if wider than maxWidth
        if ($origWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int)round($origHeight * ($maxWidth / $origWidth));
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/WebP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
            }

            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            unset($image);
            $image = $resizedImage;
            $resized = true;
        }

        // Save compressed image
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($image, $filePath, $quality);
                break;
            case 'image/png':
                // PNG compression: 0 (none) to 9 (max). Map quality 75 -> ~3
                $pngCompression = max(0, min(9, (int)round((100 - $quality) / 11)));
                imagepng($image, $filePath, $pngCompression);
                break;
            case 'image/webp':
                imagewebp($image, $filePath, $quality);
                break;
        }

        unset($image);

        $newSize = filesize($filePath);
        return [
            'success' => true,
            'size' => $newSize,
            'resized' => $resized
        ];
    }
}
