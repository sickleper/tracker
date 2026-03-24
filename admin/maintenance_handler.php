<?php
require_once '../config.php';
require_once '../tracker_data.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

if (!function_exists('isTrackerAdminUser') || !isTrackerAdminUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit();
}

if (!function_exists('trackerIsPrimaryApp') || !trackerIsPrimaryApp()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Shared maintenance tools are available on the primary app only.',
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $requestedTenantSlug = trim((string) ($_POST['tenant_slug'] ?? ''));
    $resolvedTenantSlug = trackerTenantSlug();

    $respond = static function (bool $success, string $message, int $statusCode = 200) use ($resolvedTenantSlug): void {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'tenant_slug' => $resolvedTenantSlug,
        ]);
    };

    if ($requestedTenantSlug !== '' && $resolvedTenantSlug !== '' && !hash_equals($resolvedTenantSlug, $requestedTenantSlug)) {
        $respond(false, 'Tenant override is not allowed. Maintenance runs in the current runtime tenant context only.', 422);
        exit();
    }

    switch ($action) {
        case 'clear_cache':
            $response = makeApiCall('/api/maintenance/clear-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'Application cache cleared.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear cache.', 422);
            }
            break;

        case 'clear_logs':
            $response = makeApiCall('/api/maintenance/clear-logs', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'Application logs cleared.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear logs.', 422);
            }
            break;

        case 'clear_sessions':
            $response = makeApiCall('/api/maintenance/clear-sessions', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, (string) ($response['message'] ?? 'Expired sessions cleared.'));
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear sessions.', 422);
            }
            break;

        case 'clear_route_cache':
            $response = makeApiCall('/api/maintenance/clear-route-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'Route cache cleared.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear route cache.', 422);
            }
            break;

        case 'clear_config_cache':
            $response = makeApiCall('/api/maintenance/clear-config-cache', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'Config cache cleared.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear config cache.', 422);
            }
            break;

        case 'optimize_clear':
            $response = makeApiCall('/api/maintenance/optimize-clear', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'All compiled files cleared.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to clear all compiled files.', 422);
            }
            break;

        case 'seed_milestones':
            $response = makeApiCall('/api/projects/seed-milestone-templates', [], 'POST');
            if ($response && ($response['success'] ?? false)) {
                $respond(true, 'Milestone templates generated for project categories.');
            } else {
                $respond(false, $response['message'] ?? 'Failed to seed milestone templates.', 422);
            }
            break;

        default:
            $respond(false, 'Unknown action.', 400);
            break;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
