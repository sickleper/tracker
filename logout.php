<?php
require_once __DIR__ . '/config.php';

$apiToken = getTrackerApiToken();
$baseUrl = $_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? '';

if ($apiToken && $baseUrl !== '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($baseUrl, '/') . '/api/logout');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $apiToken,
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
}

$_SESSION = [];

setcookie('apitoken', '', [
    'expires' => time() - 42000,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
}

session_regenerate_id(true);
$_SESSION['logged_out'] = true;

header('Location: oauth2callback.php');
exit();
