<?php
/**
 * Tracker Handler - New Entry Point
 * 
 * This is the new, refactored entry point for the tracker system.
 * It replaces the original 1847-line tracker_handler.php file.
 * 
 * Usage:
 * 1. Keep tracker_handler.php as backup
 * 2. Test this file thoroughly
 * 3. When ready, rename tracker_handler.php to tracker_handler_old.php
 * 4. Rename this file to tracker_handler.php
 */

// Load configuration and dependencies
require_once __DIR__ . '/config/tracker_config.php';
require_once __DIR__ . '/handlers/TrackerRequestHandler.php';
require_once __DIR__ . '/config.php';

// Ensure session is started for access to $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SECURITY: Authentication check
$authenticated = isTrackerAuthenticated();

if (!$authenticated) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please log in via Google'
    ]);
    exit;
}

// Get the requested action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Process the request
if ($action) {
    try {
        $handler = new TrackerRequestHandler();
        $handler->handle($action);
    } catch (Exception $e) {
        error_log("Tracker Handler Exception (Action: $action): " . $e->getMessage());
        
        // Return JSON error for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'An error occurred. Please try again.'
            ]);
        } else {
            // For non-AJAX requests, the handler methods handle their own output
            echo json_encode([
                'success' => false, 
                'message' => 'An error occurred. Please try again.'
            ]);
        }
    }
}
