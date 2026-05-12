<?php

require_once __DIR__ . '/../config.php';

function fuelMaintenanceTenantTag(): string
{
    $tenantSlug = trim((string) (function_exists('trackerTenantSlug') ? trackerTenantSlug() : ''));
    if ($tenantSlug === '') {
        $tenantSlug = 'global';
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $tenantSlug);
    return $sanitized === '' ? 'global' : $sanitized;
}

function fuelMaintenanceHistoryPath(): string
{
    return TRACKER_REPO_DIR . '/storage/fuel_maintenance_history_' . fuelMaintenanceTenantTag() . '.json';
}

function fuelLoadMaintenanceHistory(): array
{
    $path = fuelMaintenanceHistoryPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '', true);

    return is_array($decoded) ? array_values($decoded) : [];
}

function fuelSaveMaintenanceHistory(array $entries): void
{
    $path = fuelMaintenanceHistoryPath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create maintenance history storage directory.');
    }

    $json = json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode maintenance history.');
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write maintenance history.');
    }
}

function fuelAddMaintenanceHistoryEntry(array $entry): array
{
    $entries = fuelLoadMaintenanceHistory();
    $entry['id'] = (string) ($entry['id'] ?? uniqid('svc_', true));
    $entry['completed_on'] = (string) ($entry['completed_on'] ?? date('Y-m-d'));
    $entry['created_at'] = (string) ($entry['created_at'] ?? date('c'));
    $entry['updated_at'] = date('c');

    array_unshift($entries, $entry);
    fuelSaveMaintenanceHistory($entries);

    return $entry;
}

function fuelMaintenanceHistoryForVehicle(array $entries, int $vehicleId): array
{
    return array_values(array_filter($entries, static function(array $entry) use ($vehicleId): bool {
        return (int) ($entry['vehicle_id'] ?? 0) === $vehicleId;
    }));
}
