<?php

if (!function_exists('receiptScannerLinkSecret')) {
    function receiptScannerLinkSecret(): string
    {
        $secret = trim((string) ($_ENV['RECEIPT_SCANNER_LINK_SECRET'] ?? getenv('RECEIPT_SCANNER_LINK_SECRET') ?? ''));
        if ($secret !== '') {
            return $secret;
        }

        $appKey = trim((string) ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? ''));
        if ($appKey !== '') {
            return $appKey;
        }

        $fallback = implode('|', [
            (string) ($_ENV['DOCUMENT_AI_PROCESSOR'] ?? ''),
            (string) ($_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? ''),
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
        ]);

        return hash('sha256', $fallback !== '' ? $fallback : __FILE__);
    }
}

if (!function_exists('receiptScannerDriverMileagePayload')) {
    function receiptScannerDriverMileagePayload(
        string $userHash,
        string $vehicleReg,
        int $expires,
        int $tenantId = 0,
        string $driverName = ''
    ): array {
        return [
            'user_hash' => trim($userHash),
            'vehicle_reg' => strtoupper(trim($vehicleReg)),
            'tenant_id' => max(0, $tenantId),
            'driver_name' => trim($driverName),
            'expires' => $expires,
        ];
    }
}

if (!function_exists('receiptScannerSignDriverMileagePayload')) {
    function receiptScannerSignDriverMileagePayload(array $payload): string
    {
        $signingData = implode('|', [
            (string) ($payload['user_hash'] ?? ''),
            (string) ($payload['vehicle_reg'] ?? ''),
            (string) ($payload['tenant_id'] ?? 0),
            (string) ($payload['driver_name'] ?? ''),
            (string) ($payload['expires'] ?? 0),
        ]);

        return hash_hmac('sha256', $signingData, receiptScannerLinkSecret());
    }
}

if (!function_exists('receiptScannerBuildDriverMileageLink')) {
    function receiptScannerBuildDriverMileageLink(
        string $baseUrl,
        string $userHash,
        string $vehicleReg,
        int $expires,
        int $tenantId = 0,
        string $driverName = ''
    ): string {
        $payload = receiptScannerDriverMileagePayload($userHash, $vehicleReg, $expires, $tenantId, $driverName);
        $payload['sig'] = receiptScannerSignDriverMileagePayload($payload);

        return rtrim($baseUrl, '?') . '?' . http_build_query($payload);
    }
}

if (!function_exists('receiptScannerResolveDriverMileageRequest')) {
    function receiptScannerResolveDriverMileageRequest(array $source): ?array
    {
        $userHash = trim((string) ($source['user_hash'] ?? ''));
        $driverId = (int) ($source['driver_id'] ?? 0);
        $vehicleReg = strtoupper(trim((string) ($source['vehicle_reg'] ?? '')));
        $tenantId = (int) ($source['tenant_id'] ?? 0);
        $driverName = trim((string) ($source['driver_name'] ?? ''));
        $expires = (int) ($source['expires'] ?? 0);
        $signature = trim((string) ($source['sig'] ?? ''));

        if (($userHash === '' && $driverId <= 0) || $vehicleReg === '' || $expires <= 0 || $signature === '') {
            return null;
        }

        if ($expires < time()) {
            return null;
        }

        if ($userHash !== '') {
            $payload = receiptScannerDriverMileagePayload($userHash, $vehicleReg, $expires, $tenantId, $driverName);
            $expectedSignature = receiptScannerSignDriverMileagePayload($payload);
            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            $payload['sig'] = $signature;
            return $payload;
        }

        $legacySigningData = implode('|', [
            (string) $driverId,
            (string) $vehicleReg,
            (string) $tenantId,
            (string) $driverName,
            (string) $expires,
        ]);
        $legacySignature = hash_hmac('sha256', $legacySigningData, receiptScannerLinkSecret());
        if (!hash_equals($legacySignature, $signature)) {
            return null;
        }

        return [
            'driver_id' => $driverId,
            'vehicle_reg' => $vehicleReg,
            'tenant_id' => $tenantId,
            'driver_name' => $driverName,
            'expires' => $expires,
            'sig' => $signature,
            'legacy_driver_id' => true,
        ];
    }
}
