<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '../ai_scanner/driver_mileage.php' . ($query !== '' ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit();
