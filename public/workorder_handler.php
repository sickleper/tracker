<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall
require_once "../services/NotificationService.php";

function normalizePublicAttachments(array $files, int $clientId, string $poNumber): array
{
    if (empty($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $safePo = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($poNumber) ?: 'unassigned');
    $baseDir = rtrim($_ENV['LOCAL_STORAGE_UPLOAD_PATH'] ?? '/home/workorders/uploads/pdfs', '/')
        . '/public-workorders/' . $clientId . '/' . $safePo . '/' . uniqid('upload_', true);

    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        error_log("Public work order upload: failed to create temp directory {$baseDir}");
        return [];
    }

    $attachments = [];
    foreach ($files['name'] as $index => $name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = $files['tmp_name'][$index] ?? '';

        if ($error !== UPLOAD_ERR_OK || empty($tmpName) || !is_uploaded_file($tmpName)) {
            continue;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)$name));
        $targetPath = rtrim($baseDir, '/') . '/' . $safeName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $attachments[] = [
                'path' => $targetPath,
                'name' => $safeName,
                'type' => $files['type'][$index] ?? null,
            ];
        } else {
            error_log("Public work order upload: failed moving file {$safeName}");
        }
    }

    return $attachments;
}

function cleanupPublicAttachments(array $attachments): void
{
    foreach ($attachments as $attachment) {
        $path = $attachment['path'] ?? null;
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    
    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID.']);
        exit;
    }

    // Fetch Client Config
    $clientRes = makeApiCall("/api/clients/{$clientId}/details");
    if (!$clientRes || !($clientRes['success'] ?? false)) {
        echo json_encode(['success' => false, 'message' => 'Client configuration not found.']);
        exit;
    }
    $client = $clientRes['client'];

    // 1. Insert into Main Database via API
    $taskData = [
        'client_id'      => $clientId,
        'po_number'      => $_POST['poNumber'] ?? '',
        'heading'        => $_POST['task'] ?? '',
        'location'       => $_POST['location'] ?? '',
        'eircode'        => $_POST['eircode'] ?? '',
        'contact'        => $_POST['contact'] ?? '',
        'priority'       => $_POST['priority'] ?? 'Medium',
        'status'         => 'Open',
        'start_date'     => date('Y-m-d'),
        'invoice_email'  => $client['invoice_email'] ?? ''
    ];

    $apiRes = makeApiCall('/api/tasks', $taskData, 'POST');

    if ($apiRes && ($apiRes['success'] ?? false)) {
        $attachments = normalizePublicAttachments($_FILES['attachments'] ?? [], $clientId, (string)($taskData['po_number'] ?? ''));

        // 2. Send Notifications using the Refactored Service
        $notifService = new NotificationService();
        $sent = $notifService->sendClientWorkOrderEmail($client, $_POST, $attachments);
        
        if ($sent) {
            cleanupPublicAttachments($attachments);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Work order created successfully.',
            'attachments_sent' => count($attachments),
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $apiRes['message'] ?? 'Failed to save work order to database.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
