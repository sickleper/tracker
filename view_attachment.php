<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    exit('Unauthorized');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!$id) {
    http_response_code(400);
    exit('Missing parameter: id');
}

try {
    $response = makeApiCall("/api/attachments/{$id}");
    if (!$response || !($response['success'] ?? false) || !isset($response['attachment'])) {
        throw new Exception('Attachment not found via API.');
    }

    $attachment = $response['attachment'];
    $filePath = (string) ($attachment['file_path'] ?? '');
    $documentName = (string) ($attachment['document_name'] ?? ('attachment-' . $id));
    $mimeType = (string) ($attachment['mime_type'] ?? 'application/octet-stream');

    if (str_starts_with($filePath, 'DRIVE:')) {
        $fileId = substr($filePath, 6);
        header("Location: https://drive.google.com/file/d/{$fileId}/view", true, 302);
        exit;
    }

    $realPath = $filePath;
    if (str_starts_with($filePath, 'LOCAL:')) {
        $cleanPath = substr($filePath, 6);
        if (str_contains($cleanPath, 'uploads/pdfs/')) {
            $parts = explode('uploads/pdfs/', $cleanPath, 2);
            $realPath = rtrim($_ENV['LOCAL_STORAGE_UPLOAD_PATH'] ?? '/home/workorders/uploads/pdfs', '/') . '/' . $parts[1];
        } else {
            $realPath = $cleanPath;
        }
    } elseif (str_starts_with($filePath, 'file://')) {
        $realPath = str_replace('file://', '', $filePath);
    }

    if (!is_string($realPath) || $realPath === '' || !file_exists($realPath)) {
        throw new Exception('File not found on server.');
    }

    if (strtolower(pathinfo($documentName, PATHINFO_EXTENSION)) === 'pdf') {
        $mimeType = 'application/pdf';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($documentName) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($realPath));

    readfile($realPath);
    exit;
} catch (Exception $e) {
    error_log('view_attachment.php error: ' . $e->getMessage());
    http_response_code(404);
    exit('Error: ' . $e->getMessage());
}
