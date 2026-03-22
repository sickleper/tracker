<?php
/**
 * Tracker Configuration and Initialization
 */

ini_set('session.save_path', '/home/workorders/tmp');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Log incoming request for debugging (sanitized)
// error_log("Tracker Handler Request: " . $_SERVER['REQUEST_METHOD'] . " - Action: " . ($_REQUEST['action'] ?? 'none')); // Removed

// Check if POST was discarded because it was too large
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $maxSize = ini_get('post_max_size');
    $errMsg = "POST data empty. Upload size (" . $_SERVER['CONTENT_LENGTH'] . " bytes) exceeded server limit ($maxSize).";
    error_log("Tracker Handler Error: $errMsg");
    echo json_encode(['success' => false, 'message' => "Files too large. Total size exceeds server limit of $maxSize. Please upload fewer or smaller photos."]);
    exit;
}

// Helper to convert empty strings to NULL
function nullify($value) {
    $trimmed = trim($value);
    return ($trimmed === '') ? null : $trimmed;
}



