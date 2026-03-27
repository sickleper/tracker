<?php

require_once __DIR__ . '/../config.php';

function fuelDailyChecksPath(): string
{
    return TRACKER_REPO_DIR . '/storage/fuel_daily_checks.json';
}

function fuelDailyCheckItems(): array
{
    return [
        'tyres' => 'Tyres',
        'lights' => 'Lights',
        'mirrors' => 'Mirrors',
        'brakes' => 'Brakes',
        'fluids' => 'Fluids',
        'bodywork' => 'Bodywork',
        'safety_kit' => 'Safety Kit',
    ];
}

function fuelLoadDailyChecks(): array
{
    $path = fuelDailyChecksPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '', true);

    return is_array($decoded) ? $decoded : [];
}

function fuelSaveDailyChecks(array $checks): void
{
    $path = fuelDailyChecksPath();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create daily check storage directory.');
    }

    $json = json_encode(array_values($checks), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode daily checks.');
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write daily checks.');
    }
}

function fuelFindDailyCheckIndex(array $checks, int $vehicleId, string $date): int
{
    foreach ($checks as $index => $check) {
        if ((int) ($check['vehicle_id'] ?? 0) === $vehicleId && (string) ($check['date'] ?? '') === $date) {
            return $index;
        }
    }

    return -1;
}

function fuelDailyChecksForDate(array $checks, string $date): array
{
    return array_values(array_filter($checks, static function(array $check) use ($date): bool {
        return (string) ($check['date'] ?? '') === $date;
    }));
}
