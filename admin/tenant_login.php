<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!function_exists('setTrackerImpersonationCookie')) {
    function setTrackerImpersonationCookie(string $token): void
    {
        setcookie('apitoken', $token, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('extractImpersonationAuthPayload')) {
    function extractImpersonationAuthPayload(array $response): array
    {
        $candidates = [
            $response['data'] ?? null,
            $response['user'] ?? null,
            $response,
        ];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $token = $candidate['token'] ?? $candidate['api_token'] ?? null;
            $email = $candidate['email'] ?? $candidate['user_email'] ?? null;
            if (is_string($token) && $token !== '' && is_string($email) && $email !== '') {
                return $candidate;
            }
        }

        return [];
    }
}

if (!function_exists('hydrateTrackerImpersonationSession')) {
    function hydrateTrackerImpersonationSession(array $data): void
    {
        $_SESSION['api_token'] = $data['token'] ?? $data['api_token'] ?? null;
        $_SESSION['user_id'] = $data['user_id'] ?? $data['id'] ?? null;
        $_SESSION['tenant_id'] = $data['tenant_id'] ?? null;
        $_SESSION['tenant_slug'] = $data['tenant_slug'] ?? null;
        $_SESSION['user_auth_id'] = $data['user_auth_id'] ?? null;
        $_SESSION['role_id'] = $data['role_id'] ?? null;
        $_SESSION['is_office'] = !empty($data['is_office']);
        $_SESSION['user_name'] = $data['name'] ?? 'User';
        $_SESSION['user_email'] = $data['email'] ?? '';
        $_SESSION['email'] = $data['email'] ?? '';

        if (isset($data['google_id'])) {
            $_SESSION['google_id'] = $data['google_id'];
        } else {
            unset($_SESSION['google_id']);
        }
    }
}

if (!function_exists('callTrackerImpersonationEndpoint')) {
    function callTrackerImpersonationEndpoint(string $endpoint, array $payload = []): array
    {
        $baseUrl = rtrim((string) ($_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? ''), '/');
        $apiToken = getTrackerApiToken();

        if ($baseUrl === '' || !is_string($apiToken) || $apiToken === '') {
            return ['success' => false, 'message' => 'Laravel API connection is not available.'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiToken,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Impersonation request failed: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => 'Invalid impersonation response from API.'];
        }

        if ($httpCode >= 400 || !($decoded['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $decoded['message'] ?? 'Impersonation request was rejected.',
                'status' => $httpCode,
                'data' => $decoded,
            ];
        }

        return $decoded;
    }
}

if (!function_exists('revokeCurrentImpersonationToken')) {
    function revokeCurrentImpersonationToken(): void
    {
        $response = callTrackerImpersonationEndpoint('/api/admin/stop-impersonation');
        if (!($response['success'] ?? false)) {
            error_log('Failed to revoke impersonation token before session restore: ' . ($response['message'] ?? 'unknown error'));
        }
    }
}

if (!function_exists('trackerRequestWantsJson')) {
    function trackerRequestWantsJson(): bool
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        return $xhr || str_contains($accept, 'application/json');
    }
}

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
    ]);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit();
}

$input = $_POST;
if ($input === []) {
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody ?: '', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = trim((string) ($input['action'] ?? 'switch'));
$allowRestore = ($action === 'restore' && !empty($_SESSION['impersonation_active']));

if (!isTrackerSuperAdmin() && !$allowRestore) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Superadmin access required.',
    ]);
    exit();
}

if ($action === 'restore') {
    if (!empty($_SESSION['impersonation_active']) && isset($_SESSION['impersonation_original']) && is_array($_SESSION['impersonation_original'])) {
        revokeCurrentImpersonationToken();
        $original = $_SESSION['impersonation_original'];
        hydrateTrackerImpersonationSession($original);
        setTrackerImpersonationCookie((string) ($original['token'] ?? $original['api_token'] ?? ''));
    } else {
        if (array_key_exists('original_tenant_id', $_SESSION)) {
            $_SESSION['tenant_id'] = $_SESSION['original_tenant_id'];
        } else {
            unset($_SESSION['tenant_id']);
        }

        if (array_key_exists('original_tenant_slug', $_SESSION)) {
            $_SESSION['tenant_slug'] = $_SESSION['original_tenant_slug'];
        } else {
            unset($_SESSION['tenant_slug']);
        }
    }

    unset(
        $_SESSION['impersonation_active'],
        $_SESSION['impersonation_original'],
        $_SESSION['impersonated_by_email'],
        $_SESSION['impersonated_tenant_name'],
        $_SESSION['impersonated_tenant_slug'],
        $_SESSION['impersonated_user_name'],
        $_SESSION['impersonated_user_email'],
        $_SESSION['tenant_override_active'],
        $_SESSION['tenant_override_name'],
        $_SESSION['tenant_override_slug'],
        $_SESSION['original_tenant_id'],
        $_SESSION['original_tenant_slug']
    );

    $finalTenant = trackerTenantSlug(true);
    $redirectUrl = '../admin/index.php';
    if (!trackerRequestWantsJson()) {
        header('Location: ' . $redirectUrl);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Returned to the original tenant context.',
        'tenant_slug' => $finalTenant,
        'redirect' => '../admin/index.php',
    ]);
    exit();
}

$isImpersonateAction = ($action === 'impersonate');
$tenantId = filter_var($input['tenant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tenantSlug = trim((string) ($input['tenant_slug'] ?? ''));
$tenantName = trim((string) ($input['tenant_name'] ?? ''));
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($tenantId === false || $tenantSlug === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid tenant ID and slug are required.',
    ]);
    exit();
}

if ($isImpersonateAction) {
    if ($userId === false) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'A valid user is required for impersonation.',
        ]);
        exit();
    }

    $impersonationResponse = callTrackerImpersonationEndpoint('/api/admin/impersonate', [
        'tenant_id' => (int) $tenantId,
        'tenant_slug' => $tenantSlug,
        'user_id' => (int) $userId,
    ]);

    if (!($impersonationResponse['success'] ?? false)) {
        $message = $impersonationResponse['message'] ?? 'Impersonation endpoint is not available.';
        if (($impersonationResponse['status'] ?? 0) === 404) {
            $message = 'Impersonation endpoint is not available on the Laravel API yet.';
        }
        http_response_code(($impersonationResponse['status'] ?? 422) >= 400 ? (int) $impersonationResponse['status'] : 422);
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        exit();
    }

    $authPayload = extractImpersonationAuthPayload($impersonationResponse);
    if ($authPayload === []) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => 'Impersonation succeeded but the API did not return a usable auth payload.',
        ]);
        exit();
    }

    if (empty($_SESSION['impersonation_active'])) {
        $_SESSION['impersonation_original'] = [
            'token' => $_SESSION['api_token'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'tenant_slug' => $_SESSION['tenant_slug'] ?? null,
            'user_auth_id' => $_SESSION['user_auth_id'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'is_office' => $_SESSION['is_office'] ?? false,
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'google_id' => $_SESSION['google_id'] ?? null,
        ];
    }

    hydrateTrackerImpersonationSession($authPayload);
    setTrackerImpersonationCookie((string) ($authPayload['token'] ?? $authPayload['api_token']));

    $_SESSION['impersonation_active'] = true;
    $_SESSION['impersonated_by_email'] = $_SESSION['impersonation_original']['email'] ?? '';
    $_SESSION['impersonated_tenant_name'] = $tenantName !== '' ? $tenantName : $tenantSlug;
    $_SESSION['impersonated_tenant_slug'] = $tenantSlug;
    $_SESSION['impersonated_user_name'] = $authPayload['name'] ?? '';
    $_SESSION['impersonated_user_email'] = $authPayload['email'] ?? '';

    echo json_encode([
        'success' => true,
        'message' => 'Now impersonating ' . ($_SESSION['impersonated_user_name'] ?: 'selected user') . ' in ' . ($_SESSION['impersonated_tenant_name'] ?: $tenantSlug) . '.',
        'tenant_slug' => trackerTenantSlug(),
        'redirect' => '../index.php',
    ]);
    exit();
}

if (empty($_SESSION['tenant_override_active'])) {
    if (array_key_exists('tenant_id', $_SESSION)) {
        $_SESSION['original_tenant_id'] = $_SESSION['tenant_id'];
    }
    if (array_key_exists('tenant_slug', $_SESSION)) {
        $_SESSION['original_tenant_slug'] = $_SESSION['tenant_slug'];
    }
}

$_SESSION['tenant_id'] = (int) $tenantId;
$_SESSION['tenant_slug'] = $tenantSlug;
$_SESSION['tenant_override_active'] = true;
$_SESSION['tenant_override_name'] = $tenantName !== '' ? $tenantName : $tenantSlug;
$_SESSION['tenant_override_slug'] = $tenantSlug;

echo json_encode([
    'success' => true,
    'message' => 'Tenant context switched to ' . ($tenantName !== '' ? $tenantName : $tenantSlug) . '.',
    'tenant_slug' => trackerTenantSlug(),
    'redirect' => '../index.php',
]);
