<?php
require_once '../config.php';
require_once '../tracker_data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'clear_cache':
            $response = makeApiCall('/api/maintenance/clear-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Application cache cleared.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear cache.']);
            }
            break;

        case 'clear_logs':
            $response = makeApiCall('/api/maintenance/clear-logs', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Application logs cleared.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear logs.']);
            }
            break;

        case 'clear_sessions':
            $response = makeApiCall('/api/maintenance/clear-sessions', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => $response['message']]);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear sessions.']);
            }
            break;

        case 'clear_route_cache':
            $response = makeApiCall('/api/maintenance/clear-route-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Route cache cleared.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear route cache.']);
            }
            break;

        case 'clear_config_cache':
            $response = makeApiCall('/api/maintenance/clear-config-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Config cache cleared.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear config cache.']);
            }
            break;

        case 'optimize_clear':
            $response = makeApiCall('/api/maintenance/optimize-clear', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'All compiled files cleared.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to clear all compiled files.']);
            }
            break;

        case 'seed_milestones':
            $response = makeApiCall('/api/projects/seed-milestone-templates', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Milestone templates generated for project categories.']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to seed milestone templates.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
