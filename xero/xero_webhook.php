<?php
require_once __DIR__ . '/../config.php';

$payload = file_get_contents('php://input');
$target = rtrim($_ENV['LARAVEL_API_URL'], '/') . '/api/xero/webhook';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

$headers = ['Content-Type: application/json', 'Accept: application/json'];
if (isset($_SERVER['HTTP_X_XERO_SIGNATURE'])) {
    $headers[] = 'X-Xero-Signature: ' . $_SERVER['HTTP_X_XERO_SIGNATURE'];
}

$ch = curl_init($target);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('Xero webhook proxy failed: ' . $error);
    http_response_code(502);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response;
