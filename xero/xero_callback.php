<?php
require_once __DIR__ . '/../config.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = rtrim($_ENV['LARAVEL_API_URL'], '/') . '/api/xero/callback';
if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 302);
exit;
