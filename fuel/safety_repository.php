<?php

require_once __DIR__ . '/../config.php';

function fuelSafetyTenantTag(): string
{
    $tenantSlug = trim((string) (function_exists('trackerTenantSlug') ? trackerTenantSlug() : ''));
    if ($tenantSlug === '') {
        $tenantSlug = 'global';
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $tenantSlug);
    return $sanitized === '' ? 'global' : $sanitized;
}

function fuelSafetyPath(): string
{
    return TRACKER_REPO_DIR . '/storage/fuel_safety_state_' . fuelSafetyTenantTag() . '.json';
}

function fuelSafetySeverities(): array
{
    return ['low', 'medium', 'high', 'critical'];
}

function fuelLoadSafetyState(): array
{
    $path = fuelSafetyPath();
    if (!is_file($path)) {
        return [
            'defects' => [],
            'vehicle_flags' => [],
        ];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '', true);
    if (!is_array($decoded)) {
        return [
            'defects' => [],
            'vehicle_flags' => [],
        ];
    }

    return [
        'defects' => is_array($decoded['defects'] ?? null) ? array_values($decoded['defects']) : [],
        'vehicle_flags' => is_array($decoded['vehicle_flags'] ?? null) ? $decoded['vehicle_flags'] : [],
    ];
}

function fuelSaveSafetyState(array $state): void
{
    $path = fuelSafetyPath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create safety storage directory.');
    }

    $json = json_encode([
        'defects' => array_values($state['defects'] ?? []),
        'vehicle_flags' => $state['vehicle_flags'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode safety state.');
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write safety state.');
    }
}

function fuelNormalizeSeverity(string $severity = null): string
{
    $normalized = strtolower(trim((string) $severity));
    return in_array($normalized, fuelSafetySeverities(), true) ? $normalized : 'medium';
}

function fuelDefectKey(array $defect): string
{
    $vehicleId = (int) ($defect['vehicle_id'] ?? 0);
    $date = substr((string) ($defect['date'] ?? ''), 0, 10);
    $details = strtolower(trim((string) ($defect['defect_details'] ?? '')));

    return implode('|', [$vehicleId, $date, md5($details)]);
}

function fuelFindSafetyDefectIndex(array $defects, string $id): int
{
    foreach ($defects as $index => $defect) {
        if ((string) ($defect['id'] ?? '') === $id) {
            return $index;
        }
    }

    return -1;
}

function fuelUpsertSafetyDefect(array &$state, array $defect): array
{
    $defect['id'] = (string) ($defect['id'] ?? uniqid('fuel_defect_', true));
    $defect['severity'] = fuelNormalizeSeverity($defect['severity'] ?? 'medium');
    $defect['off_road'] = !empty($defect['off_road']);
    $defect['status'] = (string) ($defect['status'] ?? 'open');
    $defect['date'] = (string) ($defect['date'] ?? date('Y-m-d'));
    $defect['created_at'] = (string) ($defect['created_at'] ?? date('c'));
    $defect['updated_at'] = date('c');
    $defect['key'] = fuelDefectKey($defect);

    $index = fuelFindSafetyDefectIndex($state['defects'], $defect['id']);
    if ($index >= 0) {
        $state['defects'][$index] = array_merge($state['defects'][$index], $defect);
    } else {
        $state['defects'][] = $defect;
    }

    fuelRebuildVehicleFlag($state, (int) ($defect['vehicle_id'] ?? 0));

    return $defect;
}

function fuelOpenSafetyDefectsForVehicle(array $state, int $vehicleId): array
{
    return array_values(array_filter($state['defects'] ?? [], static function(array $defect) use ($vehicleId): bool {
        return (int) ($defect['vehicle_id'] ?? 0) === $vehicleId && (string) ($defect['status'] ?? 'open') === 'open';
    }));
}

function fuelRebuildVehicleFlag(array &$state, int $vehicleId): void
{
    if ($vehicleId <= 0) {
        return;
    }

    $openDefects = fuelOpenSafetyDefectsForVehicle($state, $vehicleId);
    $offRoadDefects = array_values(array_filter($openDefects, static function(array $defect): bool {
        return !empty($defect['off_road']);
    }));

    if (!$offRoadDefects) {
        unset($state['vehicle_flags'][(string) $vehicleId]);
        return;
    }

    usort($offRoadDefects, static function(array $a, array $b): int {
        $weight = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        return ($weight[$b['severity'] ?? 'medium'] ?? 2) <=> ($weight[$a['severity'] ?? 'medium'] ?? 2);
    });

    $primary = $offRoadDefects[0];
    $state['vehicle_flags'][(string) $vehicleId] = [
        'vehicle_id' => $vehicleId,
        'off_road' => true,
        'severity' => fuelNormalizeSeverity($primary['severity'] ?? 'medium'),
        'reason' => (string) ($primary['defect_details'] ?? 'Vehicle marked off road'),
        'defect_id' => (string) ($primary['id'] ?? ''),
        'updated_at' => date('c'),
    ];
}

function fuelResolveSafetyDefect(array &$state, string $defectId, string $rectifiedOn = null): ?array
{
    $index = fuelFindSafetyDefectIndex($state['defects'] ?? [], $defectId);
    if ($index < 0) {
        return null;
    }

    $state['defects'][$index]['status'] = 'rectified';
    $state['defects'][$index]['rectified_on'] = $rectifiedOn ?: date('Y-m-d');
    $state['defects'][$index]['updated_at'] = date('c');

    fuelRebuildVehicleFlag($state, (int) ($state['defects'][$index]['vehicle_id'] ?? 0));

    return $state['defects'][$index];
}

function fuelResolveSafetyDefectByKey(array &$state, string $defectKey, string $rectifiedOn = null): ?array
{
    foreach (($state['defects'] ?? []) as $index => $defect) {
        $key = (string) ($defect['key'] ?? fuelDefectKey($defect));
        if ($key !== $defectKey) {
            continue;
        }

        $state['defects'][$index]['status'] = 'rectified';
        $state['defects'][$index]['rectified_on'] = $rectifiedOn ?: date('Y-m-d');
        $state['defects'][$index]['updated_at'] = date('c');

        fuelRebuildVehicleFlag($state, (int) ($state['defects'][$index]['vehicle_id'] ?? 0));

        return $state['defects'][$index];
    }

    return null;
}

function fuelMergeDefects(array $apiDefects, array $localDefects): array
{
    $merged = [];

    foreach ($apiDefects as $defect) {
        $key = fuelDefectKey($defect);
        $defect['id'] = (string) ($defect['id'] ?? ('api_' . md5($key)));
        $defect['key'] = $key;
        $defect['severity'] = fuelNormalizeSeverity($defect['severity'] ?? 'medium');
        $defect['off_road'] = !empty($defect['off_road']);
        $defect['status'] = (string) ($defect['status'] ?? 'open');
        $merged[$key] = $defect;
    }

    foreach ($localDefects as $defect) {
        $key = (string) ($defect['key'] ?? fuelDefectKey($defect));
        $merged[$key] = array_merge($merged[$key] ?? [], $defect, ['key' => $key]);
    }

    $merged = array_values(array_filter($merged, static function(array $defect): bool {
        return (string) ($defect['status'] ?? 'open') === 'open';
    }));

    usort($merged, static function(array $a, array $b): int {
        return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
    });

    return $merged;
}
