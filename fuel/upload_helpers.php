<?php

if (!function_exists('fuelUploadTenantTag')) {
    function fuelUploadTenantTag(): string
    {
        $tenantSlug = trim((string) (function_exists('trackerTenantSlug') ? trackerTenantSlug() : ''));
        if ($tenantSlug === '') {
            $tenantSlug = 'global';
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $tenantSlug);
        return $sanitized === '' ? 'global' : $sanitized;
    }
}

if (!function_exists('fuelReceiptUploadDir')) {
    function fuelReceiptUploadDir(): string
    {
        return __DIR__ . '/uploads/' . fuelUploadTenantTag();
    }
}

if (!function_exists('fuelBuildReceiptUploadName')) {
    function fuelBuildReceiptUploadName(string $originalName): string
    {
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $originalName));
        if ($safeBase === '' || $safeBase === '.') {
            $safeBase = 'image';
        }

        $salt = '';
        try {
            $salt = bin2hex(random_bytes(8));
        } catch (Exception $exception) {
            $salt = substr(uniqid((string) mt_rand(), true), 0, 16);
        }

        return sprintf('%s/%s_%s_%s', fuelUploadTenantTag(), date('Ymd_His'), $salt, $safeBase);
    }
}

if (!function_exists('fuelReceiptUploadPath')) {
    function fuelReceiptUploadPath(string $fileName): string
    {
        $clean = ltrim((string) $fileName, '/');
        return __DIR__ . '/uploads/' . $clean;
    }
}

if (!function_exists('fuelCurrentUserId')) {
    function fuelCurrentUserId(bool $refresh = false): int
    {
        static $cached = null;

        if (!$refresh && is_int($cached)) {
            return $cached;
        }

        if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
            $cached = (int) $_SESSION['user_id'];
            return $cached;
        }

        if (!function_exists('isTrackerAuthenticated') || !isTrackerAuthenticated()) {
            return 0;
        }

        if (!function_exists('makeApiCall')) {
            return 0;
        }

        $response = makeApiCall('/api/user');
        $userId = (int) ($response['data']['id'] ?? $response['user']['id'] ?? $response['id'] ?? 0);
        if ($userId <= 0) {
            $cached = 0;
            return 0;
        }

        $_SESSION['user_id'] = $userId;
        if (!empty($response['data']['name']) || !empty($response['user']['name']) || !empty($response['name'])) {
            $_SESSION['user_name'] = (string) ($response['data']['name'] ?? $response['user']['name'] ?? $response['name']);
        }

        if (!empty($response['data']['email']) || !empty($response['user']['email']) || !empty($response['email'])) {
            $_SESSION['user_email'] = (string) ($response['data']['email'] ?? $response['user']['email'] ?? $response['email']);
            $_SESSION['email'] = $_SESSION['user_email'];
        }

        $cached = $userId;
        return $cached;
    }
}

if (!function_exists('fuelFilterLogsByUserScope')) {
    function fuelFilterLogsByUserScope(array $logs, bool $isAdmin, int $userId): array
    {
        if ($isAdmin || $userId <= 0) {
            return array_values(array_filter($logs, static function($log): bool {
                return is_array($log);
            }));
        }

        return array_values(array_filter($logs, static function(array $log) use ($userId): bool {
            return (int) ($log['user_id'] ?? 0) === $userId;
        }));
    }
}

if (!function_exists('fuelRebuildLogCountsForUser')) {
    function fuelRebuildLogCountsForUser(array $logs, int $userId, ?string $fallbackName = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $count = 0;
        $username = trim((string) $fallbackName);

        foreach ($logs as $log) {
            if (!is_array($log) || (int) ($log['user_id'] ?? 0) !== $userId) {
                continue;
            }

            $count++;
            if ($username === '' && isset($log['driver_name']) && is_string($log['driver_name']) && $log['driver_name'] !== '') {
                $username = $log['driver_name'];
            }
        }

        if ($count <= 0) {
            return [];
        }

        return [[
            'user_id' => $userId,
            'username' => $username !== '' ? $username : 'My Logs',
            'log_count' => $count,
        ]];
    }
}
