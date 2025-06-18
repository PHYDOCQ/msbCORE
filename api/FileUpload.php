<?php
/**
 * BENGKEL MANAGEMENT PRO - FILE UPLOAD CLASS
 * Version: 3.1.0
 * Secure File Upload Management System
 */

class FileUpload {
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
        $this->maxFileSize = $_ENV['MAX_FILE_SIZE'] ?? 5242880; // 5MB default
        $this->allowedTypes = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
            'archive' => ['zip', 'rar', '7z']
        ];
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function upload($file, $type = 'image', $subDir = '') {
        try {
            // Validate file
            $this->validateFile($file, $type);
            
            // Generate unique filename
            $filename = $this->generateFilename($file['name']);
            
            // Create subdirectory if specified
            $targetDir = $this->uploadDir . $subDir;
            if ($subDir && !is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $targetPath = $targetDir . '/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Save file record to database
            $fileId = $this->saveFileRecord($filename, $file, $type, $subDir);
            
            return [
                'id' => $fileId,
                'filename' => $filename,
                'path' => $targetPath,
                'url' => $this->getFileUrl($filename, $subDir)
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function validateFile($file, $type) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $this->getUploadErrorMessage($file['error']));
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size');
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes[$type] ?? [])) {
            throw new Exception('File type not allowed');
        }
        
        // Additional security checks
        $this->securityCheck($file);
    }
    
    private function securityCheck($file) {
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'application/zip'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file type detected');
        }
        
        // Scan for malicious content (basic check)
        $content = file_get_contents($file['tmp_name']);
        $maliciousPatterns = ['<?php', '<script', 'javascript:', 'vbscript:'];
        
        foreach ($maliciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                throw new Exception('Potentially malicious file detected');
            }
        }
    }
    
    private function generateFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        
        return date('Y-m-d_H-i-s') . '_' . uniqid() . '_' . substr($basename, 0, 50) . '.' . $extension;
    }
    
    private function saveFileRecord($filename, $file, $type, $subDir) {
        return $this->db->insert('uploaded_files', [
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_type' => $type,
            'mime_type' => $file['type'],
            'file_size' => $file['size'],
            'sub_directory' => $subDir,
            'uploaded_by' => $_SESSION['user_id'] ?? null,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function getFileUrl($filename, $subDir = '') {
        $baseUrl = $_ENV['BASE_URL'] ?? '';
        return $baseUrl . '/' . $this->uploadDir . $subDir . '/' . $filename;
    }
    
    public function delete($fileId) {
        try {
            $file = $this->db->selectOne(
                "SELECT * FROM uploaded_files WHERE id = :id",
                ['id' => $fileId]
            );
            
            if (!$file) {
                throw new Exception('File not found');
            }
            
            $filePath = $this->uploadDir . $file['sub_directory'] . '/' . $file['filename'];
            
            // Delete physical file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $this->db->delete('uploaded_files', 'id = :id', ['id' => $fileId]);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
