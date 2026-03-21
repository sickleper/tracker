<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// SECURITY: Authentication check
if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Clear both the legacy tracker cache and the Laravel OpenAI cache.
$cacheFiles = [
    __DIR__ . '/../cache/ai_email_cache.json',
    '/home/api/laravel_api/storage/app/ai_email_cache.json',
];

try {
    $cleared = [];
    foreach ($cacheFiles as $cache_file) {
        $cacheDir = dirname($cache_file);
        if (!is_dir($cacheDir)) {
            continue;
        }
        if (file_put_contents($cache_file, '{}') === false) {
            throw new Exception("Failed to clear cache file: {$cache_file}");
        }
        $cleared[] = basename($cache_file);
    }

    if (empty($cleared)) {
        echo json_encode(['success' => true, 'message' => 'No cache files were found to clear.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Cache cleared successfully.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
