<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../fuel/daily_checks_repository.php';
require_once __DIR__ . '/../fuel/safety_repository.php';
require_once __DIR__ . '/link_helper.php';

$receiptScannerPublicDriverContext = $GLOBALS['receipt_scanner_public_driver_context'] ?? null;
$receiptScannerAllowsPublicDriverUpload = defined('RECEIPT_SCANNER_ALLOW_PUBLIC_DRIVER_UPLOAD')
    && RECEIPT_SCANNER_ALLOW_PUBLIC_DRIVER_UPLOAD
    && is_array($receiptScannerPublicDriverContext);

if (!isTrackerAuthenticated() && !$receiptScannerAllowsPublicDriverUpload) {
    header('Location: ../oauth2callback.php');
    exit();
}

$localReceiptScannerAutoload = __DIR__ . '/vendor/ai-receipt-scanner/autoload.php';
if (!class_exists('Google\\Cloud\\DocumentAI\\V1\\Client\\DocumentProcessorServiceClient')) {
    if (!is_file($localReceiptScannerAutoload)) {
        throw new RuntimeException('Receipt scanner vendor bundle is missing.');
    }

    require_once $localReceiptScannerAutoload;
}

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\AnnotateImageRequest;

if (!function_exists('loadReceiptScannerLegacyEnv')) {
    function loadReceiptScannerLegacyEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $legacyEnv = @parse_ini_file($path, false, INI_SCANNER_RAW);
        if (!is_array($legacyEnv)) {
            return;
        }

        foreach (['OPENAI_API_KEY', 'GOOGLE_APPLICATION_CREDENTIALS', 'DOCUMENT_AI_PROCESSOR'] as $key) {
            $value = trim((string) ($legacyEnv[$key] ?? ''));
            $currentValue = trim((string) ($_ENV[$key] ?? ''));
            $currentValueLower = strtolower($currentValue);
            $isPlaceholder = $currentValueLower === ''
                || str_contains($currentValueLower, 'your_openai')
                || str_contains($currentValueLower, 'your-openai')
                || str_contains($currentValueLower, 'your_api_key')
                || str_contains($currentValueLower, 'placeholder')
                || $currentValueLower === 'changeme';

            if ($value === '' || (!$isPlaceholder && $currentValue !== '')) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

$localReceiptScannerEnv = dirname(__DIR__) . '/storage/receipt_scanner/.env';
loadReceiptScannerLegacyEnv($localReceiptScannerEnv);

$openai_key = $_ENV['OPENAI_API_KEY'] ?? '';
$google_credentials = $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? '';
$document_ai_processor = $_ENV['DOCUMENT_AI_PROCESSOR'] ?? '';

// Remove quotes from values if present
$openai_key = trim($openai_key, '\'"');
$google_credentials = trim($google_credentials, '\'"');
$document_ai_processor = trim($document_ai_processor, '\'"');

$storage_dir = dirname(__DIR__) . '/storage/receipt_scanner';
$upload_dir = __DIR__ . '/uploads/';
$jsonl_file = $storage_dir . '/receipts.jsonl';
$mileage_jsonl_file = $storage_dir . '/mileage.jsonl';
$receipt_api_base = '/api/fuel/receipt-scanner/receipts';
$mileage_api_base = '/api/fuel/receipt-scanner/mileage';
$fuel_logs_api_base = '/api/fuel/logs';
$fuel_vehicles_api_base = '/api/fuel/vehicles';

if (!is_dir($storage_dir)) {
    mkdir($storage_dir, 0775, true);
}

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true);
}

$fuelVehicles = [];
$scannerSessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$scannerAssignedVehicle = null;
if (isTrackerAuthenticated()) {
    $fuelVehiclesResponse = makeApiCall($fuel_vehicles_api_base);
    $fuelVehicles = is_array($fuelVehiclesResponse) && ($fuelVehiclesResponse['success'] ?? false)
        ? ($fuelVehiclesResponse['vehicles'] ?? [])
        : [];
    foreach ($fuelVehicles as $scannerVehicleOption) {
        if ((int) ($scannerVehicleOption['user_id'] ?? 0) === $scannerSessionUserId) {
            $scannerAssignedVehicle = $scannerVehicleOption;
            break;
        }
    }
}

// Get user email - checks session, then defaults to admin
function getUserEmail() {
    // Check if user is logged in via session
    if (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
        return $_SESSION['user_email'];
    }

    if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
        return $_SESSION['email'];
    }
    
    // Default to admin email
    return trackerSuperAdminEmail() ?: 'john@example.ie';
}

function getUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function receiptScannerApiFailure(string $message = 'API request failed'): void {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function receiptScannerCredentialFileIsUsable(string $path): bool
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return false;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return false;
    }

    $privateKey = (string) ($decoded['private_key'] ?? '');
    if ($privateKey === '') {
        return false;
    }

    $key = @openssl_pkey_get_private($privateKey);
    if ($key === false) {
        return false;
    }

    openssl_free_key($key);
    return true;
}

$receiptScannerGoogleCredentialCandidates = [
    $google_credentials,
    '/home/workorders/spheric-temple-356620-d73418aaa9fc.json',
    '/home/workorders/cred.json',
];

foreach ($receiptScannerGoogleCredentialCandidates as $receiptScannerGoogleCredentialCandidate) {
    $receiptScannerGoogleCredentialCandidate = trim((string) $receiptScannerGoogleCredentialCandidate);
    if ($receiptScannerGoogleCredentialCandidate !== '' && receiptScannerCredentialFileIsUsable($receiptScannerGoogleCredentialCandidate)) {
        $google_credentials = $receiptScannerGoogleCredentialCandidate;
        break;
    }
}

function receiptScannerConfigWarnings(string $openAiKey, string $googleCredentials, string $documentAiProcessor): array
{
    $warnings = [];

    if ($openAiKey === '') {
        $warnings[] = 'OPENAI_API_KEY is missing.';
    }

    if ($googleCredentials === '') {
        $warnings[] = 'GOOGLE_APPLICATION_CREDENTIALS is missing.';
    } elseif (!receiptScannerCredentialFileIsUsable($googleCredentials)) {
        $warnings[] = 'Google credentials file is missing or contains an unusable private key.';
    }

    if ($documentAiProcessor === '') {
        $warnings[] = 'DOCUMENT_AI_PROCESSOR is missing.';
    }

    return $warnings;
}

function receiptScannerRequireReceiptAiConfig(string $openAiKey, string $googleCredentials, string $documentAiProcessor): void
{
    if ($openAiKey === '') {
        throw new Exception('Receipt scanner is not configured: OPENAI_API_KEY is missing.');
    }

    if (!receiptScannerCredentialFileIsUsable($googleCredentials)) {
        throw new Exception('Receipt scanner is not configured: Google credentials file is missing or invalid.');
    }

    if ($documentAiProcessor === '') {
        throw new Exception('Receipt scanner is not configured: DOCUMENT_AI_PROCESSOR is missing.');
    }
}

function receiptScannerRequireMileageAiConfig(string $googleCredentials): void
{
    if (!receiptScannerCredentialFileIsUsable($googleCredentials)) {
        throw new Exception('Mileage scanner is not configured: Google credentials file is missing or invalid.');
    }
}

function receiptScannerApiWarnings(): array
{
    $warnings = [];

    $baseUrl = trim((string) ($_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? ''));
    if ($baseUrl === '') {
        $warnings[] = 'LARAVEL_API_URL is missing.';
    }

    if (!function_exists('getTrackerApiToken') || !getTrackerApiToken()) {
        $warnings[] = 'Tracker API token is missing or expired.';
    }

    return $warnings;
}

function receiptScannerRequireApiAccess(): void
{
    $baseUrl = trim((string) ($_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? ''));
    if ($baseUrl === '') {
        throw new Exception('Scanner API is not configured: LARAVEL_API_URL is missing.');
    }

    if (!function_exists('getTrackerApiToken') || !getTrackerApiToken()) {
        throw new Exception('Scanner API access is unavailable: tracker API token is missing or expired.');
    }
}

function receiptScannerMoveUploadedFile(array $file, string $prefix = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed.');
    }

    $originalName = (string) ($file['name'] ?? 'upload');
    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $fileName = $prefix . time() . '_' . $safeBase;
    $targetFile = ($GLOBALS['upload_dir'] ?? (__DIR__ . '/uploads/')) . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        throw new Exception('Failed to store uploaded file.');
    }

    return [$fileName, $targetFile];
}

function receiptScannerValidateUploadedFile(array $file, array $allowedMimeTypes, int $maxBytes = 10485760): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum size allowed by server.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum size allowed by form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file.',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension.',
        ];

        throw new Exception($errorMessages[$file['error']] ?? 'Upload failed.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new Exception('File size exceeds 10MB limit.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new Exception('Uploaded file is invalid.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === false || !in_array($mimeType, $allowedMimeTypes, true)) {
        throw new Exception('Invalid file type uploaded.');
    }
}

function receiptScannerStoreReceiptRecord(array $record): void
{
    $response = makeApiCall($GLOBALS['receipt_api_base'] ?? '/api/fuel/receipt-scanner/receipts', $record, 'POST');
    if (!is_array($response) || !($response['success'] ?? false)) {
        throw new Exception($response['message'] ?? 'Failed to store receipt record.');
    }
}

function receiptScannerStoreMileageRecord(array $record): void
{
    $response = makeApiCall($GLOBALS['mileage_api_base'] ?? '/api/fuel/receipt-scanner/mileage', $record, 'POST');
    if (!is_array($response) || !($response['success'] ?? false)) {
        throw new Exception($response['message'] ?? 'Failed to store mileage record.');
    }
}

function receiptScannerAppendJsonlRecord(string $path, array $record): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new Exception('Unable to create receipt scanner storage directory.');
    }

    $json = json_encode($record, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new Exception('Unable to encode mileage record.');
    }

    $result = file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    if ($result === false) {
        throw new Exception('Failed to store mileage record.');
    }
}

function receiptScannerCreatePublicDriverFuelLog(array $payload): array
{
    $response = makeApiCall('/api/public/fuel/driver-mileage-log', $payload, 'POST');
    if (!is_array($response) || !($response['success'] ?? false)) {
        throw new Exception($response['message'] ?? 'Failed to create driver mileage log.');
    }

    return $response['log'] ?? [];
}

function receiptScannerResolvePublicUserByHash(string $userHash): array
{
    $response = makeApiCall('/api/public/users/by-hash/' . rawurlencode($userHash));
    if (!is_array($response) || !($response['success'] ?? false) || !is_array($response['user'] ?? null)) {
        throw new Exception($response['message'] ?? 'Driver not found.');
    }

    return $response['user'];
}

function receiptScannerResolvePublicUserById(int $userId): array
{
    $response = makeApiCall('/api/public/users/by-id/' . $userId);
    if (!is_array($response) || !($response['success'] ?? false) || !is_array($response['user'] ?? null)) {
        throw new Exception($response['message'] ?? 'Driver not found.');
    }

    return $response['user'];
}

function receiptScannerFuelUploadsDir(): string
{
    return dirname(__DIR__) . '/fuel/uploads/';
}

function receiptScannerCopyToFuelUploads(string $sourcePath, string $originalName): string
{
    $targetDir = receiptScannerFuelUploadsDir();
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new Exception('Unable to create fuel upload directory.');
    }

    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $targetName = time() . '_' . $safeBase;
    $targetPath = $targetDir . $targetName;

    if (!copy($sourcePath, $targetPath)) {
        throw new Exception('Failed to copy receipt image to fuel uploads.');
    }

    return $targetName;
}

function receiptScannerVehicleLookup(int $vehicleId): array
{
    $response = makeApiCall(($GLOBALS['fuel_vehicles_api_base'] ?? '/api/fuel/vehicles') . '/' . $vehicleId);
    if (!is_array($response) || !($response['success'] ?? false) || empty($response['vehicle'])) {
        throw new Exception($response['message'] ?? 'Vehicle not found.');
    }

    return $response['vehicle'];
}

function receiptScannerFindAssignedVehicleForUser(int $userId, array $vehicles): ?array
{
    foreach ($vehicles as $vehicle) {
        if ((int) ($vehicle['user_id'] ?? 0) === $userId) {
            return $vehicle;
        }
    }

    return null;
}

$scannerConfigWarnings = array_merge(
    receiptScannerConfigWarnings($openai_key, $google_credentials, $document_ai_processor),
    receiptScannerApiWarnings()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capture_fuel_log'])) {
    header('Content-Type: application/json');

    $receiptFileName = null;
    $receiptPath = null;
    $mileageFileName = null;
    $mileagePath = null;

    try {
        $workerUserId = getUserId();
        if ($workerUserId <= 0) {
            throw new Exception('Worker session is missing a user id.');
        }

        $assignedVehicle = receiptScannerFindAssignedVehicleForUser($workerUserId, $GLOBALS['fuelVehicles'] ?? []);
        if (!$assignedVehicle) {
            throw new Exception('No fleet vehicle is assigned to this worker.');
        }
        $vehicleId = (int) ($assignedVehicle['vehicle_id'] ?? 0);

        $logDate = trim((string) ($_POST['fuel_log_date'] ?? date('Y-m-d')));
        $today = date('Y-m-d');

        $safetyState = fuelLoadSafetyState();
        $vehicleFlag = $safetyState['vehicle_flags'][(string) $vehicleId] ?? null;
        if (!empty($vehicleFlag['off_road'])) {
            throw new Exception(
                !empty($vehicleFlag['reason'])
                    ? 'Vehicle is off road: ' . $vehicleFlag['reason']
                    : 'This vehicle is currently marked off road and cannot be used.'
            );
        }

        if ($logDate === $today) {
            $checks = fuelLoadDailyChecks();
            $checkIndex = fuelFindDailyCheckIndex($checks, $vehicleId, $today);
            $dailyCheck = $checkIndex >= 0 ? $checks[$checkIndex] : null;

            if (!$dailyCheck) {
                throw new Exception('Complete today\'s daily vehicle check before logging fuel or mileage.');
            }

            if ((string) ($dailyCheck['status'] ?? '') !== 'pass') {
                throw new Exception('This vehicle has a failed daily check today. Review the check before logging use.');
            }
        }

        if (!isset($_FILES['fuel_receipt']) || !isset($_FILES['fuel_mileage_photo'])) {
            throw new Exception('Fuel receipt and mileage photo are required.');
        }

        receiptScannerRequireApiAccess();
        receiptScannerRequireReceiptAiConfig($openai_key, $google_credentials, $document_ai_processor);
        receiptScannerRequireMileageAiConfig($google_credentials);
        receiptScannerValidateUploadedFile($_FILES['fuel_receipt'], ['image/jpeg', 'image/png', 'application/pdf']);
        receiptScannerValidateUploadedFile($_FILES['fuel_mileage_photo'], ['image/jpeg', 'image/png']);

        [$receiptFileName, $receiptPath] = receiptScannerMoveUploadedFile($_FILES['fuel_receipt'], 'fuel_receipt_');
        [$mileageFileName, $mileagePath] = receiptScannerMoveUploadedFile($_FILES['fuel_mileage_photo'], 'fuel_mileage_');

        $receiptData = extractReceiptData($receiptPath, $openai_key, $google_credentials, $document_ai_processor);
        $mileageData = extractMileageData($mileagePath, $google_credentials, $openai_key);
        $vehicle = receiptScannerVehicleLookup($vehicleId);

        $fuelAmount = isset($receiptData['litres']) ? (float) $receiptData['litres'] : 0.0;
        if ($fuelAmount <= 0) {
            throw new Exception('Could not detect litres from the fuel receipt.');
        }

        $startMileage = isset($vehicle['last_mileage']) ? (float) $vehicle['last_mileage'] : 0.0;
        $finishMileage = (float) ($mileageData['mileage'] ?? 0);
        if ($finishMileage < $startMileage) {
            throw new Exception('Detected odometer is lower than the vehicle start mileage.');
        }

        $fuelImageFileName = receiptScannerCopyToFuelUploads($receiptPath, $receiptFileName);

        $fuelLogPayload = [
            'vehicle_id' => $vehicleId,
            'user_id' => $workerUserId,
            'date' => $logDate ?: ($receiptData['date'] ?? date('Y-m-d')),
            'start_mileage' => $startMileage,
            'finish_mileage' => $finishMileage,
            'fuel_amount' => $fuelAmount,
            'image_file' => $fuelImageFileName,
        ];

        $fuelLogResponse = makeApiCall($fuel_logs_api_base, $fuelLogPayload, 'POST');
        if (!is_array($fuelLogResponse) || !($fuelLogResponse['success'] ?? false)) {
            throw new Exception($fuelLogResponse['message'] ?? 'Failed to create fuel log.');
        }

        $receiptRecord = [
            'id' => time() . '_' . rand(1000, 9999),
            'user_id' => $workerUserId,
            'merchant_name' => $receiptData['merchant'],
            'transaction_date' => $receiptData['date'],
            'total_amount' => $receiptData['total'],
            'subtotal' => $receiptData['subtotal'],
            'tax_amount' => $receiptData['tax'],
            'tip_amount' => $receiptData['tip'],
            'discount_amount' => $receiptData['discount'],
            'currency' => $receiptData['currency'],
            'receipt_number' => $receiptData['receipt_number'],
            'location' => $receiptData['location'],
            'phone' => $receiptData['phone'],
            'category' => $receiptData['category'],
            'payment_method' => $receiptData['payment'],
            'items' => json_decode($receiptData['items']),
            'receipt_image' => $receiptFileName,
            'confidence_score' => $receiptData['confidence'],
            'processing_method' => $receiptData['processing_method'],
            'raw_ocr_text' => $receiptData['ocr_text'],
            'extracted_json' => $receiptData['extracted_json'],
            'uploaded_by' => getUserEmail(),
            'project_address' => $receiptData['project_address'] ?? null,
            'gps_latitude' => $receiptData['gps_latitude'] ?? null,
            'gps_longitude' => $receiptData['gps_longitude'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        receiptScannerStoreReceiptRecord($receiptRecord);

        $mileageRecord = [
            'id' => time() . '_' . rand(1000, 9999),
            'user_id' => $workerUserId,
            'vehicle_reg' => $vehicle['license_plate'] ?? '',
            'mileage' => $finishMileage,
            'mileage_image' => $mileageFileName,
            'raw_ocr_text' => $mileageData['ocr_text'],
            'uploaded_by' => getUserEmail(),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        receiptScannerStoreMileageRecord($mileageRecord);

        echo json_encode([
            'success' => true,
            'message' => 'Fuel log created successfully.',
            'fuel_log' => $fuelLogResponse['log'] ?? null,
        ]);
    } catch (Exception $e) {
        if ($receiptPath && is_file($receiptPath)) {
            @unlink($receiptPath);
        }
        if ($mileagePath && is_file($mileagePath)) {
            @unlink($mileagePath);
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    header('Content-Type: application/json');
    
    // Validate file upload
    if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum size allowed by server',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum size from form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension'
        ];
        $error = $error_messages[$_FILES['receipt']['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'error' => 'Upload error: ' . $error]);
        exit;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES['receipt']['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and PDF allowed. Got: ' . $mime_type]);
        exit;
    }
    
    if ($_FILES['receipt']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds 10MB limit']);
        exit;
    }
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory']);
            exit;
        }
    }
    
    $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['receipt']['name']));
    $target_file = $upload_dir . $file_name;
    
    $project_address_input = $_POST['project_address'] ?? null; // Get project address from form

    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
        try {
            receiptScannerRequireApiAccess();
            receiptScannerRequireReceiptAiConfig($openai_key, $google_credentials, $document_ai_processor);
            $extracted_data = extractReceiptData($target_file, $openai_key, $google_credentials, $document_ai_processor);
            
            // Override AI extracted project_address if user provided one
            if ($project_address_input) {
                $extracted_data['project_address'] = trim((string) $project_address_input);
            }
            
            // Get user email
            $user_email = getUserEmail();
            $user_id = getUserId();
            
            $new_receipt = [
                'id' => time() . '_' . rand(1000, 9999),
                'user_id' => $user_id,
                'merchant_name' => $extracted_data['merchant'],
                'transaction_date' => $extracted_data['date'],
                'total_amount' => $extracted_data['total'],
                'subtotal' => $extracted_data['subtotal'],
                'tax_amount' => $extracted_data['tax'],
                'tip_amount' => $extracted_data['tip'],
                'discount_amount' => $extracted_data['discount'],
                'currency' => $extracted_data['currency'],
                'receipt_number' => $extracted_data['receipt_number'],
                'location' => $extracted_data['location'],
                'phone' => $extracted_data['phone'],
                'category' => $extracted_data['category'],
                'payment_method' => $extracted_data['payment'],
                'items' => json_decode($extracted_data['items']),
                'receipt_image' => $file_name,
                'confidence_score' => $extracted_data['confidence'],
                'processing_method' => $extracted_data['processing_method'],
                'raw_ocr_text' => $extracted_data['ocr_text'],
                'extracted_json' => $extracted_data['extracted_json'],
                'uploaded_by' => $user_email,
                'project_address' => $extracted_data['project_address'] ?? null,
                'gps_latitude' => $extracted_data['gps_latitude'] ?? null, // New field
                'gps_longitude' => $extracted_data['gps_longitude'] ?? null, // New field
                'created_at' => date('Y-m-d H:i:s')
            ];

            $json_data = json_encode($new_receipt) . PHP_EOL;
            
            $api_response = makeApiCall($GLOBALS['receipt_api_base'] ?? '/api/fuel/receipt-scanner/receipts', $new_receipt, 'POST');
            if (!is_array($api_response) || !($api_response['success'] ?? false)) {
                throw new Exception($api_response['message'] ?? 'Failed to save receipt via API.');
            }

            echo json_encode(['success' => true, 'data' => $extracted_data, 'message' => 'Receipt processed successfully']);
        } catch (Exception $e) {
            if (file_exists($target_file)) {
                unlink($target_file);
            }
            echo json_encode(['success' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }
}

// Fetch recent receipts
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    header('Content-Type: application/json');
    receiptScannerRequireApiAccess();
    $api_response = makeApiCall($receipt_api_base);
    if (!is_array($api_response)) {
        receiptScannerApiFailure('Failed to fetch receipts from API.');
    }
    echo json_encode($api_response);
    exit;
}

// Delete receipt
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    receiptScannerRequireApiAccess();
    $id = $_GET['id'];
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid receipt ID']);
        exit;
    }

    $receipt_response = makeApiCall($receipt_api_base . '/' . rawurlencode($id));
    if (!is_array($receipt_response) || !($receipt_response['success'] ?? false) || empty($receipt_response['receipt'])) {
        echo json_encode(['success' => false, 'error' => $receipt_response['message'] ?? 'Receipt not found']);
        exit;
    }

    $deleted_receipt = (object) $receipt_response['receipt'];
    $delete_response = makeApiCall($receipt_api_base . '/' . rawurlencode($id), [], 'DELETE');
    if (!is_array($delete_response) || !($delete_response['success'] ?? false)) {
        echo json_encode(['success' => false, 'error' => $delete_response['message'] ?? 'Failed to delete receipt']);
        exit;
    }

    if ($deleted_receipt) {
        $image_file = $upload_dir . ($deleted_receipt->receipt_image ?? '');
        if (file_exists($image_file)) {
            unlink($image_file);
        }

        echo json_encode(['success' => true, 'message' => 'Receipt deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Receipt not found']);
    }
    exit;
}

// Fetch single receipt
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    receiptScannerRequireApiAccess();
    $id = $_GET['id'];

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid receipt ID']);
        exit;
    }

    $api_response = makeApiCall($receipt_api_base . '/' . rawurlencode($id));
    if (!is_array($api_response)) {
        receiptScannerApiFailure('Failed to load receipt from API.');
    }
    echo json_encode($api_response);
    exit;
}

function extractReceiptData($image_path, $openai_key, $google_credentials, $processor_name) {
    try {
        // Resize image if it\'s a JPG or PNG
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $image_path);
        finfo_close($finfo);

        if (in_array($mimeType, ['image/jpeg', 'image/png'])) {
            resizeImage($image_path);
        }

        $gps_latitude = null;
        $gps_longitude = null;

        error_log("Attempting EXIF read for: " . $image_path . " (MIME: " . $mimeType . ")");

        // Attempt to read EXIF data for GPS coordinates
        if (function_exists('exif_read_data') && in_array($mimeType, ['image/jpeg', 'image/tiff'])) { // EXIF typically for JPEG/TIFF
            $exif = @exif_read_data($image_path);
            error_log("EXIF data found: " . print_r($exif, true)); // Log all EXIF data

            if ($exif && isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
                $gps_latitude = getGps($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                $gps_longitude = getGps($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
                error_log("Extracted GPS: Lat=" . $gps_latitude . ", Lon=" . $gps_longitude);
            } else {
                error_log("No GPS data found in EXIF or EXIF data is incomplete.");
            }
        } else {
            error_log("exif_read_data function not available or MIME type not JPEG/TIFF.");
        }

        putenv("GOOGLE_APPLICATION_CREDENTIALS=$google_credentials");
        
        if (!file_exists($google_credentials)) {
            throw new Exception('Google credentials file not found: ' . $google_credentials);
        }
        
        $client = new DocumentProcessorServiceClient();
        $imageContent = file_get_contents($image_path);
        
        if ($imageContent === false) {
            throw new Exception('Failed to read image file: ' . $image_path);
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $image_path);
        finfo_close($finfo);
        
        $rawDocument = new RawDocument([
            'content' => $imageContent,
            'mime_type' => $mimeType
        ]);
        
        $request = new ProcessRequest([
            'name' => $processor_name,
            'raw_document' => $rawDocument
        ]);
        
        $response = $client->processDocument($request);
        $document = $response->getDocument();
        $ocr_text = $document->getText();
        $client->close();
        
        if (empty($ocr_text)) {
            throw new Exception('Document AI failed to extract text from the image');
        }
        
    } catch (Exception $e) {
        throw new Exception('Google Document AI Error: ' . $e->getMessage());
    }
    
    $prompt = "This is an Irish construction materials or fuel receipt. Extract all available information from this receipt text. Return ONLY a valid JSON object with no markdown or additional text.\n\n" . $ocr_text . "\n\n";
    if ($gps_latitude !== null && $gps_longitude !== null) {
        $prompt .= "The photo was taken at approximately Latitude: $gps_latitude, Longitude: $gps_longitude. Use this as additional context for location if no specific address is found on the receipt.\n\n";
    }
    $prompt .= "Return JSON with these keys (use null for missing values):\n" .
    "{\n" .
    "  \"merchant_name\": \"string (supplier name, e.g., builder merchant, petrol station, fuel supplier)\",\n" .
    "  \"date\": \"YYYY-MM-DD format or null\",\n" .
    "  \"total\": number (the final amount paid, including VAT if present),\n" .
    "  \"subtotal\": number or null (amount before VAT),\n" .
    "  \"vat\": number or null (VAT amount - standard 23% for most construction items),\n" .
    "  \"vat_rate\": \"23%|13.5%|9%|0%|null\" (Irish VAT rates),\n" .
    "  \"tip\": number or null,\n" .
    "  \"discount\": number or null,\n" .
    "  \"currency\": \"EUR\",\n" .
    "  \"receipt_number\": \"string or null (transaction/receipt ID)\",\n" .
    "  \"location\": \"store address or location or null\",\n" .
    "  \"phone\": \"string or null\",\n" .
    "  \"category\": \"Construction|Fuel|Other\",\n" .
    "  \"payment_method\": \"Credit Card|Debit Card|Cash|Mobile Payment|Other\",\n" .
    "  \"items\": [\n" .
    "    {\"name\": \"product name or description\", \"quantity\": number, \"unit\": \"unit of measurement (e.g., m, kg, L, m2, pcs)\", \"price\": number},\n" .
    "    ...\n" .
    "  ],\n" .
    "  \"fuel_type\": \"Petrol|Diesel|Kerosene|null (only for fuel receipts)\",\n" .
    "  \"litres\": number or null (only for fuel receipts),\n" .
    "  \"price_per_litre\": number or null (only for fuel receipts),\n" .
    "  \"confidence\": 0.0 to 1.0,\n" .
    "  \"project_address\": \"string or null (address of the project site if available)\",\n" .
    "  \"gps_latitude\": number or null,\n" .
    "  \"gps_longitude\": number or null\n" .
    "}";
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert receipt parser. Extract all available information accurately. Return only valid JSON. Use null for missing fields.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1,
        'max_tokens' => 1000
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('OpenAI API connection error: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        $error_body = json_decode($response, true);
        $error_msg = $error_body['error']['message'] ?? 'Unknown error';
        throw new Exception('OpenAI API Error (HTTP ' . $http_code . '): ' . $error_msg);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Unexpected OpenAI response format');
    }
    
    $content = trim($result['choices'][0]['message']['content']);
    $parsed = decodeReceiptJsonResponse($content);
    
    if ($parsed) {
        $items_json = json_encode($parsed['items'] ?? []);
        $fallbackDate = extractReceiptDateFromText($ocr_text);
        $fallbackTotal = extractReceiptTotalFromText($ocr_text);
        $fallbackLitres = extractReceiptLitresFromText($ocr_text);
        $fallbackFuelType = inferFuelTypeFromText($ocr_text);
        $fallbackPricePerLitre = extractPricePerLitreFromText($ocr_text);
        $normalizedTotal = normalizeReceiptNumberValue($parsed['total'] ?? null);
        $normalizedSubtotal = normalizeReceiptNumberValue($parsed['subtotal'] ?? null);
        $normalizedVat = normalizeReceiptNumberValue($parsed['vat'] ?? ($parsed['tax'] ?? null));
        $normalizedTip = normalizeReceiptNumberValue($parsed['tip'] ?? null);
        $normalizedDiscount = normalizeReceiptNumberValue($parsed['discount'] ?? null);
        $normalizedConfidence = normalizeReceiptNumberValue($parsed['confidence'] ?? null);
        $normalizedLitres = normalizeReceiptNumberValue($parsed['litres'] ?? null);
        $normalizedPricePerLitre = normalizeReceiptNumberValue($parsed['price_per_litre'] ?? null);
        
        return [
            'merchant' => $parsed['merchant_name'] ?? 'Unknown Merchant',
            'date' => validateDate($parsed['date'] ?? null) ?? $fallbackDate ?? date('Y-m-d'),
            'total' => $normalizedTotal ?? $fallbackTotal ?? 0,
            'subtotal' => $normalizedSubtotal,
            'tax' => $normalizedVat,
            'tip' => $normalizedTip,
            'discount' => $normalizedDiscount,
            'currency' => 'EUR',
            'receipt_number' => $parsed['receipt_number'] ?? null,
            'location' => $parsed['location'] ?? null,
            'phone' => $parsed['phone'] ?? null,
            'category' => $parsed['category'] ?? inferReceiptCategoryFromText($ocr_text),
            'payment' => $parsed['payment_method'] ?? 'Unknown',
            'items' => $items_json,
            'confidence' => $normalizedConfidence ?? 0.7,
            'extracted_json' => $content,
            'ocr_text' => $ocr_text,
            'processing_method' => 'google_docai_gpt4o',
            'project_address' => $parsed['project_address'] ?? null, // New field
            'gps_latitude' => $parsed['gps_latitude'] ?? $gps_latitude, // Prioritize AI, then EXIF
            'gps_longitude' => $parsed['gps_longitude'] ?? $gps_longitude,
            'litres' => $normalizedLitres ?? $fallbackLitres,
            'fuel_type' => $parsed['fuel_type'] ?? $fallbackFuelType,
            'price_per_litre' => $normalizedPricePerLitre ?? $fallbackPricePerLitre,
        ];
    }
    
    return [
        'merchant' => 'Unknown Merchant',
        'date' => extractReceiptDateFromText($ocr_text) ?? date('Y-m-d'),
        'total' => extractReceiptTotalFromText($ocr_text) ?? 0,
        'subtotal' => null,
        'tax' => null,
        'tip' => null,
        'discount' => null,
        'currency' => 'EUR',
        'receipt_number' => null,
        'location' => null,
        'phone' => null,
        'category' => inferReceiptCategoryFromText($ocr_text),
        'payment' => 'Unknown',
        'items' => '[]',
        'confidence' => 0.3,
        'extracted_json' => null,
        'ocr_text' => $ocr_text,
        'processing_method' => 'google_docai_gpt4o',
        'project_address' => null,
        'gps_latitude' => $gps_latitude,
        'gps_longitude' => $gps_longitude,
        'litres' => extractReceiptLitresFromText($ocr_text),
        'fuel_type' => inferFuelTypeFromText($ocr_text),
        'price_per_litre' => extractPricePerLitreFromText($ocr_text),
    ];
}

function validateDate($date_string) {
    if (!$date_string) return null;
    
    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];
    foreach ($formats as $format) {
        $parsed = DateTime::createFromFormat($format, $date_string);
        if ($parsed !== false) {
            return $parsed->format('Y-m-d');
        }
    }
    
    return null;
}

function extractFirstJsonObject(string $content): ?string
{
    $start = strpos($content, '{');
    if ($start === false) {
        return null;
    }

    $depth = 0;
    $inString = false;
    $escape = false;
    $length = strlen($content);

    for ($i = $start; $i < $length; $i++) {
        $char = $content[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $inString = false;
            }
            continue;
        }

        if ($char === '"') {
            $inString = true;
            continue;
        }

        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($content, $start, $i - $start + 1);
            }
        }
    }

    return null;
}

function decodeReceiptJsonResponse(string $content): ?array
{
    $clean = trim(preg_replace('/```json\s*|\s*```/i', '', $content));
    $decoded = json_decode($clean, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $jsonObject = extractFirstJsonObject($clean);
    if ($jsonObject === null) {
        return null;
    }

    $decoded = json_decode($jsonObject, true);
    return is_array($decoded) ? $decoded : null;
}

function normalizeReceiptNumberValue($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $value = str_replace(['EUR', '€', ','], ['', '', '.'], strtoupper((string) $value));
    if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
        return (float) $matches[0];
    }

    return null;
}

function extractReceiptDateFromText(string $ocrText): ?string
{
    $patterns = [
        '/\b(\d{4}[\/\-]\d{2}[\/\-]\d{2})\b/',
        '/\b(\d{2}[\/\-]\d{2}[\/\-]\d{4})\b/',
        '/\b(\d{2}[\/\-]\d{2}[\/\-]\d{2})\b/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ocrText, $matches)) {
            $candidate = str_replace('/', '-', $matches[1]);
            $validated = validateDate($candidate);
            if ($validated !== null) {
                return $validated;
            }
        }
    }

    return null;
}

function extractReceiptTotalFromText(string $ocrText): ?float
{
    $patterns = [
        '/\b(?:TOTAL|AMOUNT DUE|AMOUNT|BALANCE)\b[^\d]{0,12}(\d+[.,]\d{2})/i',
        '/\b(?:EUR|€)\s*(\d+[.,]\d{2})\b/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ocrText, $matches)) {
            $value = normalizeReceiptNumberValue($matches[1]);
            if ($value !== null && $value > 0) {
                return $value;
            }
        }
    }

    if (preg_match_all('/(\d+[.,]\d{2})/', $ocrText, $matches)) {
        $values = array_filter(array_map('normalizeReceiptNumberValue', $matches[1]), fn ($value) => $value !== null && $value > 0);
        if (!empty($values)) {
            return max($values);
        }
    }

    return null;
}

function extractReceiptLitresFromText(string $ocrText): ?float
{
    $patterns = [
        '/(\d+(?:[.,]\d+)?)\s*(?:LITRES|LITERS|LTS|LTR|L\b)/i',
        '/\bL\s*[:\-]?\s*(\d+(?:[.,]\d+)?)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ocrText, $matches)) {
            $value = normalizeReceiptNumberValue($matches[1]);
            if ($value !== null && $value > 0 && $value < 500) {
                return $value;
            }
        }
    }

    return null;
}

function inferFuelTypeFromText(string $ocrText): ?string
{
    if (preg_match('/\b(DIESEL|DERV|B7|B10)\b/i', $ocrText)) {
        return 'Diesel';
    }

    if (preg_match('/\b(PETROL|UNLEADED|GASOLINE|E10|E5)\b/i', $ocrText)) {
        return 'Petrol';
    }

    if (preg_match('/\b(KEROSENE|HEATING OIL)\b/i', $ocrText)) {
        return 'Kerosene';
    }

    return null;
}

function extractPricePerLitreFromText(string $ocrText): ?float
{
    $patterns = [
        '/(?:@|AT)\s*(\d+(?:[.,]\d{3})?)\s*(?:\/?\s*L|PER\s*L(?:ITRE)?)/i',
        '/(\d+(?:[.,]\d{3})?)\s*(?:€\s*)?(?:\/\s*L|PER\s*L(?:ITRE)?)/i',
        '/\b(?:PRICE\s*PER\s*L(?:ITRE)?|RATE)\b[^\d]{0,12}(\d+(?:[.,]\d{3})?)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ocrText, $matches)) {
            $value = normalizeReceiptNumberValue($matches[1]);
            if ($value !== null && $value > 0.5 && $value < 5) {
                return $value;
            }
        }
    }

    return null;
}

function inferReceiptCategoryFromText(string $ocrText): string
{
    if (preg_match('/\b(DIESEL|PETROL|FUEL|LITRES|LITERS|FORECOURT|PUMP)\b/i', $ocrText)) {
        return 'Fuel';
    }

    if (preg_match('/\b(CEMENT|TIMBER|PVC|PIPE|SCREW|INSULATION|BUILDERS?|MERCHANT|TOOL|PLASTER|BLOCK)\b/i', $ocrText)) {
        return 'Construction';
    }

    return 'Other';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mileage_photo']) && $receiptScannerAllowsPublicDriverUpload) {
    header('Content-Type: application/json');
    $fuelImageFileName = null;

    try {
        receiptScannerValidateUploadedFile($_FILES['mileage_photo'], ['image/jpeg', 'image/png']);

        $file_name = 'mileage_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $_FILES['mileage_photo']['name']));
        $target_file = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['mileage_photo']['tmp_name'], $target_file)) {
            throw new Exception('Failed to save uploaded file.');
        }

        $mileage_data = extractMileageData($target_file, $google_credentials, $openai_key);
        $publicUser = !empty($receiptScannerPublicDriverContext['user_hash'])
            ? receiptScannerResolvePublicUserByHash((string) $receiptScannerPublicDriverContext['user_hash'])
            : receiptScannerResolvePublicUserById((int) ($receiptScannerPublicDriverContext['driver_id'] ?? 0));
        $fuelImageFileName = receiptScannerCopyToFuelUploads($target_file, $file_name);
        $fuelLog = receiptScannerCreatePublicDriverFuelLog([
            'tenant_id' => (int) ($receiptScannerPublicDriverContext['tenant_id'] ?? 0),
            'user_hash' => (string) ($receiptScannerPublicDriverContext['user_hash'] ?? ''),
            'user_id' => (int) ($publicUser['id'] ?? 0),
            'vehicle_reg' => strtoupper(trim((string) ($receiptScannerPublicDriverContext['vehicle_reg'] ?? 'UNKNOWN'))),
            'date' => date('Y-m-d'),
            'finish_mileage' => $mileage_data['mileage'],
            'image_file' => $fuelImageFileName,
        ]);

        $new_mileage = [
            'id' => time() . '_' . rand(1000, 9999),
            'tenant_id' => (int) ($receiptScannerPublicDriverContext['tenant_id'] ?? 0),
            'user_id' => (int) ($publicUser['id'] ?? 0),
            'user_hash' => (string) ($receiptScannerPublicDriverContext['user_hash'] ?? ''),
            'vehicle_reg' => strtoupper(trim((string) ($receiptScannerPublicDriverContext['vehicle_reg'] ?? 'UNKNOWN'))),
            'mileage' => $mileage_data['mileage'],
            'mileage_image' => $file_name,
            'raw_ocr_text' => $mileage_data['ocr_text'],
            'uploaded_by' => trim((string) ($receiptScannerPublicDriverContext['driver_name'] ?? 'Driver Upload Link')),
            'upload_source' => 'driver_whatsapp_link',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        receiptScannerAppendJsonlRecord($mileage_jsonl_file, $new_mileage);

        echo json_encode([
            'success' => true,
            'data' => $new_mileage,
            'fuel_log' => $fuelLog,
            'message' => 'Mileage processed successfully',
        ]);
    } catch (Exception $e) {
        if (!empty($target_file) && is_file($target_file)) {
            @unlink($target_file);
        }
        if ($fuelImageFileName) {
            $fuelImagePath = receiptScannerFuelUploadsDir() . $fuelImageFileName;
            if (is_file($fuelImagePath)) {
                @unlink($fuelImagePath);
            }
        }
        echo json_encode(['success' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle mileage form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mileage_photo'])) {
    header('Content-Type: application/json');

    try {
        receiptScannerValidateUploadedFile($_FILES['mileage_photo'], ['image/jpeg', 'image/png']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    $file_name = 'mileage_' . time() . '_' . basename($_FILES['mileage_photo']['name']);
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['mileage_photo']['tmp_name'], $target_file)) {
        try {
            receiptScannerRequireApiAccess();
            receiptScannerRequireMileageAiConfig($google_credentials);
            $mileage_data = extractMileageData($target_file, $google_credentials, $openai_key);
            $vehicle_reg = isset($_POST['vehicle_reg']) ? htmlspecialchars($_POST['vehicle_reg']) : 'UNKNOWN';

            $new_mileage = [
                'id' => time() . '_' . rand(1000, 9999),
                'user_id' => getUserId(),
                'vehicle_reg' => $vehicle_reg,
                'mileage' => $mileage_data['mileage'],
                'mileage_image' => $file_name,
                'raw_ocr_text' => $mileage_data['ocr_text'],
                'uploaded_by' => getUserEmail(),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $api_response = makeApiCall($GLOBALS['mileage_api_base'] ?? '/api/fuel/receipt-scanner/mileage', $new_mileage, 'POST');
            if (!is_array($api_response) || !($api_response['success'] ?? false)) {
                throw new Exception($api_response['message'] ?? 'Failed to save mileage via API.');
            }

            echo json_encode(['success' => true, 'data' => $new_mileage, 'message' => 'Mileage processed successfully']);
        } catch (Exception $e) {
            if (file_exists($target_file)) {
                unlink($target_file);
            }
            echo json_encode(['success' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }
}

// Fetch recent mileage
if (isset($_GET['action']) && $_GET['action'] === 'fetch_mileage') {
    header('Content-Type: application/json');
    receiptScannerRequireApiAccess();
    $api_response = makeApiCall($mileage_api_base);
    if (!is_array($api_response)) {
        receiptScannerApiFailure('Failed to fetch mileage from API.');
    }
    echo json_encode($api_response);
    exit;
}

// Delete mileage
if (isset($_GET['action']) && $_GET['action'] === 'delete_mileage' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    receiptScannerRequireApiAccess();
    $id = $_GET['id'];

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid mileage ID']);
        exit;
    }

    $mileage_response = makeApiCall($mileage_api_base);
    if (!is_array($mileage_response) || !($mileage_response['success'] ?? false)) {
        echo json_encode(['success' => false, 'error' => $mileage_response['message'] ?? 'Failed to fetch mileage']);
        exit;
    }

    $deleted_mileage = null;
    foreach (($mileage_response['mileage'] ?? []) as $entry) {
        if (($entry['id'] ?? null) === $id) {
            $deleted_mileage = (object) $entry;
            break;
        }
    }

    if ($deleted_mileage) {
        $delete_response = makeApiCall($mileage_api_base . '/' . rawurlencode($id), [], 'DELETE');
        if (!is_array($delete_response) || !($delete_response['success'] ?? false)) {
            echo json_encode(['success' => false, 'error' => $delete_response['message'] ?? 'Failed to delete mileage']);
            exit;
        }

        $image_file = $upload_dir . ($deleted_mileage->mileage_image ?? '');
        if (file_exists($image_file)) {
            unlink($image_file);
        }

        echo json_encode(['success' => true, 'message' => 'Mileage deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Mileage not found']);
    }
    exit;
}




function extractMileageUsingOpenAiVision(string $imagePath, string $openAiKey): array
{
    if ($openAiKey === '') {
        throw new Exception('OpenAI key is missing for mileage fallback.');
    }

    $imageContent = file_get_contents($imagePath);
    if ($imageContent === false) {
        throw new Exception('Failed to read image file for OpenAI vision fallback.');
    }

    $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
    $base64Image = base64_encode($imageContent);

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You read odometer dashboard photos. Return only valid JSON with keys: mileage, ocr_text, confidence. mileage must be an integer or null. confidence must be between 0 and 1.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Read the odometer value from this dashboard image. Prefer the main odometer over trip/range/temperature values.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $mimeType . ';base64,' . $base64Image,
                        ],
                    ],
                ],
            ],
        ],
        'temperature' => 0,
        'max_tokens' => 300,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openAiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('OpenAI vision fallback connection error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $errorBody = json_decode((string) $response, true);
        $errorMessage = $errorBody['error']['message'] ?? 'Unknown error';
        throw new Exception('OpenAI vision fallback failed (HTTP ' . $httpCode . '): ' . $errorMessage);
    }

    $result = json_decode((string) $response, true);
    $content = trim((string) ($result['choices'][0]['message']['content'] ?? ''));
    $parsed = decodeReceiptJsonResponse($content);
    if (!is_array($parsed)) {
        throw new Exception('OpenAI vision fallback returned invalid JSON.');
    }

    $ocrText = trim((string) ($parsed['ocr_text'] ?? ''));
    $mileage = isset($parsed['mileage']) ? (int) $parsed['mileage'] : null;
    if (!$mileage && $ocrText !== '') {
        $mileage = extractMileageFromText($ocrText);
    }

    if (!$mileage) {
        throw new Exception('OpenAI vision fallback could not determine mileage.');
    }

    return [
        'mileage' => $mileage,
        'ocr_text' => $ocrText !== '' ? $ocrText : ('OpenAI vision mileage: ' . $mileage),
        'confidence' => max(0.1, min(1.0, (float) ($parsed['confidence'] ?? 0.65))),
    ];
}

function extractMileageData($image_path, $google_credentials, string $openAiKey = '') {
    try {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $image_path);
        finfo_close($finfo);

        if (in_array($mimeType, ['image/jpeg', 'image/png'])) {
            resizeImage($image_path);
        }

        if (!file_exists($image_path)) {
            throw new Exception('Image file not found: ' . $image_path);
        }

        try {
            putenv("GOOGLE_APPLICATION_CREDENTIALS=$google_credentials");

            if (!file_exists($google_credentials)) {
                throw new Exception('Google credentials file not found: ' . $google_credentials);
            }

            $client = new ImageAnnotatorClient();

            $imageContent = file_get_contents($image_path);
            if ($imageContent === false) {
                throw new Exception('Failed to read image file: ' . $image_path);
            }

            $image = new Image();
            $image->setContent($imageContent);

            $feature = new Feature();
            $feature->setType(Feature\Type::TEXT_DETECTION);

            $annotateRequest = new AnnotateImageRequest();
            $annotateRequest->setImage($image);
            $annotateRequest->setFeatures([$feature]);

            $batchRequest = new BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$annotateRequest]);

            $response = $client->batchAnnotateImages($batchRequest);
            $responseAnnotations = $response->getResponses();
            if (empty($responseAnnotations)) {
                throw new Exception('Vision API returned empty response');
            }

            $annotations = $responseAnnotations[0];
            if ($annotations->getError() && $annotations->getError()->getMessage()) {
                throw new Exception('Vision API error: ' . $annotations->getError()->getMessage());
            }

            $textAnnotations = $annotations->getTextAnnotations();
            $ocr_text = '';

            if ($textAnnotations && count($textAnnotations) > 0) {
                $ocr_text = $textAnnotations[0]->getDescription();
            }

            $client->close();

            if (empty($ocr_text)) {
                throw new Exception('Vision API returned empty text - dashboard may be unclear or too dark');
            }

            $mileage = extractMileageFromText($ocr_text);
            if ($mileage === null) {
                $preview = substr(str_replace(["\n", "\r"], ' ', $ocr_text), 0, 200);
                throw new Exception('Could not find valid mileage reading. Detected text: "' . $preview . '..."');
            }

            return [
                'mileage' => $mileage,
                'ocr_text' => $ocr_text,
                'confidence' => 0.85
            ];
        } catch (Exception $visionException) {
            error_log('Mileage Vision OCR failed, falling back to OpenAI vision: ' . $visionException->getMessage());
            return extractMileageUsingOpenAiVision($image_path, $openAiKey);
        }
    } catch (Exception $e) {
        throw new Exception('Mileage extraction error: ' . $e->getMessage());
    }
}

function normalizeMileageOcrLine(string $line): string
{
    $line = strtoupper($line);
    $line = preg_replace('/(?<=\d)[\s,._-]+(?=\d)/', '', $line);

    return strtr($line, [
        'O' => '0',
        'Q' => '0',
        'D' => '0',
        'I' => '1',
        'L' => '1',
        '|' => '1',
        'S' => '5',
        'B' => '8',
    ]);
}

function mileageCandidateScore(int $value, string $line, string $normalizedLine): int
{
    $score = 0;

    if ($value >= 20000 && $value <= 500000) {
        $score += 6;
    } elseif ($value >= 10000 && $value <= 999999) {
        $score += 3;
    } else {
        $score -= 8;
    }

    if (preg_match('/\b(?:ODO|ODOMETER|MILEAGE|KM|KMS|MILES?)\b/i', $line)) {
        $score += 8;
    }

    if (preg_match('/\b(?:TRIP|RANGE|TEMP|RPM|AVG|CLOCK|TIME)\b/i', $line)) {
        $score -= 5;
    }

    if (preg_match('/\b' . preg_quote((string) $value, '/') . '\s*(?:KM|KMS|MILES?)\b/i', $normalizedLine)) {
        $score += 4;
    }

    $digitCount = strlen((string) $value);
    if ($digitCount === 6) {
        $score += 4;
    } elseif ($digitCount === 5 || $digitCount === 7) {
        $score += 2;
    }

    if ($value % 10000 === 0) {
        $score -= 4;
    }

    if ($value >= 2020 && $value <= 2035) {
        $score -= 10;
    }

    return $score;
}

function extractMileageFromText($text) {
    error_log("Extracting mileage from: " . substr($text, 0, 300));

    $lines = preg_split('/[\r\n]+/', $text);
    $candidates = [];

    foreach ($lines as $index => $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $normalizedLine = normalizeMileageOcrLine($line);

        if (preg_match_all('/(?<!\d)(\d{5,7})(?!\d)/', $normalizedLine, $matches)) {
            foreach ($matches[1] as $match) {
                $value = (int) $match;
                if ($value < 10000 || $value > 9999999) {
                    continue;
                }

                $candidates[] = [
                    'value' => $value,
                    'score' => mileageCandidateScore($value, $line, $normalizedLine),
                    'line' => $line,
                    'index' => $index,
                ];
            }
        }
    }

    if (empty($candidates)) {
        $normalizedText = normalizeMileageOcrLine($text);
        if (preg_match_all('/(?<!\d)(\d{5,7})(?!\d)/', $normalizedText, $matches)) {
            foreach ($matches[1] as $match) {
                $value = (int) $match;
                if ($value < 10000 || $value > 9999999) {
                    continue;
                }

                $candidates[] = [
                    'value' => $value,
                    'score' => mileageCandidateScore($value, $text, $normalizedText),
                    'line' => $text,
                    'index' => 9999,
                ];
            }
        }
    }

    if (empty($candidates)) {
        error_log("No mileage found in text");
        return null;
    }

    usort($candidates, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            if ($a['index'] === $b['index']) {
                return $b['value'] <=> $a['value'];
            }

            return $a['index'] <=> $b['index'];
        }

        return $b['score'] <=> $a['score'];
    });

    $best = $candidates[0];
    error_log(sprintf('Selected odometer candidate: %d (score %d) from line: %s', $best['value'], $best['score'], substr($best['line'], 0, 120)));

    return $best['value'];
}



function resizeImage($source_path, $max_width = 1200, $max_height = 1200, $quality = 85) {
    list($width, $height, $type) = getimagesize($source_path);
    $mime = image_type_to_mime_type($type);

    $source_image = null;

    switch ($mime) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            // Check for EXIF orientation data
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($source_path);
                if ($exif && isset($exif['Orientation'])) {
                    $orientation = $exif['Orientation'];
                    switch ($orientation) {
                        case 3:
                            $source_image = imagerotate($source_image, 180, 0);
                            break;
                        case 6:
                            $source_image = imagerotate($source_image, -90, 0);
                            break;
                        case 8:
                            $source_image = imagerotate($source_image, 90, 0);
                            break;
                    }
                    // After rotating, get the new width and height
                    $width = imagesx($source_image);
                    $height = imagesy($source_image);
                }
            }
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        default:
            return $source_path; // Unsupported type
    }

    if (!$source_image) {
        return $source_path; // Failed to create image
    }

    if ($width <= $max_width && $height <= $max_height) {
        // Even if no resize is needed, we might have rotated, so we save the image
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($source_image, $source_path, $quality);
                break;
            case 'image/png':
                imagepng($source_image, $source_path, 9);
                break;
        }
        imagedestroy($source_image);
        return $source_path;
    }

    $ratio = $width / $height;

    if (($width / $max_width) > ($height / $max_height)) {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    } else {
        $new_height = $max_height;
        $new_width = $max_height * $ratio;
    }

    $new_image = imagecreatetruecolor($new_width, $new_height);

    if ($mime == 'image/png') {
        // Preserve transparency
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save the resized image back to the original path
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($new_image, $source_path, $quality);
            break;
        case 'image/png':
            imagepng($new_image, $source_path, 9); // PNG compression level
            break;
    }

    imagedestroy($source_image);
    imagedestroy($new_image);

    return $source_path;
}

// Helper function to convert GPS coordinates from EXIF format
function getGps($exifCoord, $hemi) {
    $degrees = count($exifCoord) > 0 ? frac2dec($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? frac2dec($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? frac2dec($exifCoord[2]) : 0;

    $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;
    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

function frac2dec($val) {
    $parts = explode('/', $val);
    if (count($parts) == 1) {
        return (float) $parts[0];
    } else if (count($parts) == 2) {
        return (float) $parts[0] / (float) $parts[1];
    } else {
        return 0;
    }
}

if (defined('RECEIPT_SCANNER_BOOTSTRAP_ONLY') && RECEIPT_SCANNER_BOOTSTRAP_ONLY) {
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Receipt Scanner</title>
    <script>
        (function() {
            function applyScannerTheme(isDark) {
                document.documentElement.classList.toggle('theme-dark', !!isDark);
                document.documentElement.classList.toggle('dark', !!isDark);
            }

            function resolveDarkMode() {
                try {
                    var parentDoc = window.parent && window.parent !== window ? window.parent.document : null;
                    if (parentDoc) {
                        return parentDoc.documentElement.classList.contains('dark');
                    }
                } catch (error) {
                    // Fall back to local storage and system preference below.
                }

                try {
                    if (localStorage.getItem('theme') === 'dark') {
                        return true;
                    }
                    if (localStorage.getItem('theme') === 'light') {
                        return false;
                    }
                } catch (error) {
                    // Ignore storage access failures.
                }

                return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            }

            window.syncReceiptScannerTheme = function() {
                applyScannerTheme(resolveDarkMode());
            };

            window.syncReceiptScannerTheme();

            window.addEventListener('DOMContentLoaded', function() {
                window.syncReceiptScannerTheme();

                try {
                    var parentDoc = window.parent && window.parent !== window ? window.parent.document : null;
                    if (!parentDoc || typeof MutationObserver === 'undefined') {
                        return;
                    }

                    var observer = new MutationObserver(function() {
                        window.syncReceiptScannerTheme();
                    });

                    observer.observe(parentDoc.documentElement, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                } catch (error) {
                    // Ignore cross-document observer issues.
                }
            });

            if (window.matchMedia) {
                var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                if (mediaQuery && typeof mediaQuery.addEventListener === 'function') {
                    mediaQuery.addEventListener('change', window.syncReceiptScannerTheme);
                } else if (mediaQuery && typeof mediaQuery.addListener === 'function') {
                    mediaQuery.addListener(window.syncReceiptScannerTheme);
                }
            }
        })();
    </script>
    <link rel="stylesheet" href="../../dist/output.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="header mb-6 rounded-[28px] border border-slate-200/70 bg-white/90 px-6 py-7 text-left shadow-soft backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="mb-2 text-[11px] font-black uppercase tracking-[0.28em] text-indigo-600 dark:text-indigo-300">Fuel Module Scanner</p>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-50">AI Data Capture</h1>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400">Upload receipts, mileage photos, and worker fuel evidence inside the same workflow used by fleet logs.</p>
                </div>
            
                <!-- User Email Display/Input -->
                <div class="scanner-user-wrap">
                    <span class="scanner-user-badge ring-1 ring-slate-200/70 dark:ring-slate-700/70">
                    <i class="fas fa-user"></i> <span id="userEmailDisplay"><?php echo htmlspecialchars(getUserEmail()); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($scannerConfigWarnings)): ?>
            <div class="mb-6 rounded-[24px] border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                <div class="flex items-start gap-3">
                    <i class="fas fa-triangle-exclamation mt-0.5 text-amber-500 dark:text-amber-300"></i>
                    <div>
                        <p class="font-black uppercase tracking-[0.16em]">Scanner Configuration Warning</p>
                        <p class="mt-1">Uploads that need OCR or AI parsing may fail until configuration is completed.</p>
                        <ul class="mt-2 list-disc pl-5">
                            <?php foreach ($scannerConfigWarnings as $scannerConfigWarning): ?>
                                <li><?php echo htmlspecialchars($scannerConfigWarning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="tabs mb-6 flex flex-wrap gap-2 rounded-3xl border border-slate-200 bg-white/90 p-2 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
            <button class="tab-link active inline-flex flex-1 items-center justify-center rounded-2xl px-4 py-3 text-xs font-black uppercase tracking-[0.18em] sm:text-sm" onclick="openTab(event, 'fuel-log-capture')">Fuel Logs</button>
            <button class="tab-link inline-flex flex-1 items-center justify-center rounded-2xl px-4 py-3 text-xs font-black uppercase tracking-[0.18em] sm:text-sm" onclick="openTab(event, 'receipts')">Receipts</button>
            <button class="tab-link inline-flex flex-1 items-center justify-center rounded-2xl px-4 py-3 text-xs font-black uppercase tracking-[0.18em] sm:text-sm" onclick="openTab(event, 'mileage')">Mileage</button>
        </div>

        <div id="fuel-log-capture" class="tab-content scanner-tab-default">
            <div class="upload-section rounded-[28px] border border-slate-200/70 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                <form id="fuelLogCaptureForm" enctype="multipart/form-data">
                    <input type="hidden" name="capture_fuel_log" value="1">
                    <div class="mb-6 grid gap-4 lg:grid-cols-2">
                        <div class="form-group mb-0">
                            <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Worker</label>
                            <input class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="text" value="<?php echo htmlspecialchars(($_SESSION['user_name'] ?? 'User') . ' (ID ' . $scannerSessionUserId . ')'); ?>" readonly>
                        </div>
                        <div class="form-group mb-0">
                            <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Assigned Vehicle</label>
                        <input
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100"
                            type="text"
                            value="<?php echo htmlspecialchars($scannerAssignedVehicle ? (($scannerAssignedVehicle['license_plate'] ?? 'Unknown') . ' - ' . ($scannerAssignedVehicle['make_model'] ?? 'Vehicle')) : 'No assigned vehicle found'); ?>"
                            readonly
                        >
                    </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="form-group mb-0">
                            <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400" for="fuelLogDate">Log Date</label>
                            <input class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="date" id="fuelLogDate" name="fuel_log_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400" for="fuelReceiptInput">Fuel Receipt</label>
                            <input class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-indigo-500 dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="file" id="fuelReceiptInput" name="fuel_receipt" accept="image/*,.pdf" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400" for="fuelMileageInput">Mileage Photo</label>
                            <input class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-emerald-500 dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="file" id="fuelMileageInput" name="fuel_mileage_photo" accept="image/*" required>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <button type="submit" class="filter-btn scanner-submit-btn inline-flex items-center rounded-2xl bg-gradient-to-r from-indigo-600 to-emerald-600 px-5 py-3 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg">
                        <i class="fas fa-cloud-upload-alt"></i> Add To Fuel Logs
                    </button>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Scans create a real fuel log row plus scanner sidecar records.</p>
                    </div>
                    <?php if (!$scannerAssignedVehicle): ?>
                        <p class="scanner-warning-text mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm dark:border-red-900/60 dark:bg-red-950/30">No vehicle is assigned to this user id in fleet management yet.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div id="receipts" class="tab-content">
            <div class="stats-container mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4" id="statsContainer">
                <div class="stat-box rounded-[24px] border border-slate-200/70 bg-white/90 px-5 py-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="stat-label flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"><i class="fas fa-receipt text-indigo-500"></i> Total</div>
                    <div class="stat-number" id="statTotal">0</div>
                </div>
                <div class="stat-box rounded-[24px] border border-slate-200/70 bg-white/90 px-5 py-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="stat-label flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"><i class="fas fa-hard-hat text-amber-500"></i> Construction</div>
                    <div class="stat-number" id="statConstruction">0</div>
                </div>
                <div class="stat-box rounded-[24px] border border-slate-200/70 bg-white/90 px-5 py-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="stat-label flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"><i class="fas fa-gas-pump text-emerald-500"></i> Fuel</div>
                    <div class="stat-number" id="statFuel">0</div>
                </div>
                <div class="stat-box rounded-[24px] border border-slate-200/70 bg-white/90 px-5 py-4 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="stat-label flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"><i class="fas fa-tags text-fuchsia-500"></i> Other</div>
                    <div class="stat-number" id="statOther">0</div>
                </div>
            </div>
            <div class="mb-4 flex flex-wrap gap-3">
                <button class="filter-btn inline-flex items-center rounded-2xl bg-gradient-to-r from-indigo-600 to-emerald-600 px-5 py-3 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg" onclick="openFilterModal()"><i class="fas fa-calendar-alt"></i> Filter & Export</button>
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300" id="receipts-guide-btn" title="Receipt Guide" aria-label="Receipt Guide">
                    <i class="fas fa-circle-info text-base"></i>
                </button>
            </div>
            
            <div class="upload-section rounded-[28px] border border-slate-200/70 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="form-group mb-5">
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400" for="projectAddress">Project Address (Optional)</label>
                        <div class="input-with-clear">
                            <input class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 pr-11 text-sm font-medium text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="text" id="projectAddress" name="project_address" placeholder="e.g., 123 Main St, Dublin">
                            <button type="button" class="clear-input-btn" id="clearProjectAddressBtn" title="Clear Address"><i class="fas fa-times-circle"></i></button>
                        </div>
                    </div>
                    <div class="drop-zone rounded-[24px] border-2 border-dashed border-indigo-300 bg-indigo-50/70 px-6 py-10 dark:border-indigo-800 dark:bg-slate-950/80" id="dropZone">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <h3>Drop receipts here</h3>
                        <p>or click to browse (supports multiple files)</p>
                        <input type="file" id="fileInput" name="receipt" accept="image/*,.pdf" multiple>
                    </div>
                </form>
                
                <div class="queue-status mt-5 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/70" id="queueStatus">
                    <h3 class="scanner-section-heading">Processing Queue</h3>
                    <div id="queueList"></div>
                </div>
                
                <div class="error mt-5 rounded-[24px] border border-red-200 bg-red-50/90 p-4 dark:border-red-900/60 dark:bg-red-950/30" id="error">
                    <h3>Error</h3>
                    <p id="errorMessage"></p>
                </div>
            </div>
            
            <div class="receipts-list rounded-[28px] border border-slate-200/70 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                <h2 class="mb-4 text-lg font-black tracking-tight text-slate-900 dark:text-slate-50">Recent Receipts</h2>
                <div id="receiptsList">Loading...</div>
            </div>
        </div>

        <div id="mileage" class="tab-content">
            <div class="mb-4 flex flex-wrap gap-3">
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300" id="mileage-guide-btn" title="Mileage Guide" aria-label="Mileage Guide">
                    <i class="fas fa-circle-info text-base"></i>
                </button>
                <button class="mileage-export-btn inline-flex items-center rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-3 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg" id="exportMileageBtn" onclick="exportMileageToCSV()"><i class="fas fa-download"></i> Export Mileage to CSV</button>
            </div>
            <div class="upload-section rounded-[28px] border border-slate-200/70 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                <form id="mileageUploadForm" enctype="multipart/form-data">
                    <div class="form-group mb-5">
                        <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400" for="vehicleReg">Vehicle Registration</label>
                        <input class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100" type="text" id="vehicleReg" name="vehicle_reg" placeholder="e.g., 211D12345" value="24d1024" required>
                    </div>
                    <div class="drop-zone rounded-[24px] border-2 border-dashed border-emerald-300 bg-emerald-50/70 px-6 py-10 dark:border-emerald-800 dark:bg-slate-950/80" id="mileageDropZone">
                         <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <h3>Drop mileage photo here</h3>
                        <p>or click to browse</p>
                        <input type="file" id="mileageFileInput" name="mileage_photo" accept="image/*" >
                    </div>
                </form>
                 <div class="queue-status mt-5 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/70" id="mileageQueueStatus">
                    <h3 class="scanner-section-heading">Processing Queue</h3>
                    <div id="mileageQueueList"></div>
                </div>
            </div>
            <div class="mileage-list rounded-[28px] border border-slate-200/70 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
                <h2 class="mb-4 text-lg font-black tracking-tight text-slate-900 dark:text-slate-50">Recent Mileage Logs</h2>
                <div id="mileageList">Loading...</div>
            </div>
        </div>
    </div>
    
    <!-- Filter Modal -->
    <div class="filter-modal" id="filterModal">
        <div class="filter-content rounded-[28px] border border-slate-200 bg-white p-7 shadow-hard dark:border-slate-800 dark:bg-slate-900">
            <div class="filter-header">
                <h2>Filter & Export</h2>
                <button class="filter-close" onclick="closeFilterModal()">×</button>
            </div>
            
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" id="filterStartDate">
            </div>
            
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" id="filterEndDate">
            </div>
            
            <div class="filter-group">
                <label>Category</label>
                <select id="filterCategory">
                    <option value="">All Categories</option>
                    <option value="Construction">Construction</option>
                    <option value="Fuel">Fuel</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="filter-buttons">
                <button class="filter-apply" onclick="applyFilter()">Apply Filter</button>
                <button class="filter-reset" onclick="resetFilter()">Reset</button>
            </div>
        </div>
    </div>
    
    <!-- Summary Modal -->
    <div class="modal" id="summaryModal">
        <div class="modal-content summary-modal-content rounded-[28px] border border-slate-200 bg-white p-6 shadow-hard dark:border-slate-800 dark:bg-slate-900">
            <div class="modal-header">
                <h2>Daily Totals & Export</h2>
                <button class="close-btn" onclick="closeSummaryModal()">×</button>
            </div>
            <div class="summary-modal-scroll">
                <div id="summaryContent"></div>
            </div>
            <div class="summary-modal-actions">
                <button class="export-btn" onclick="exportToCSV()"><i class="fas fa-download"></i> Export to CSV</button>
                <button class="filter-reset summary-modal-close" onclick="closeSummaryModal()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Receipt Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content rounded-[28px] border border-slate-200 bg-white p-6 shadow-hard dark:border-slate-800 dark:bg-slate-900">
            <div class="modal-header">
                <h2>Receipt Details</h2>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be populated here -->
            </div>
        </div>
    </div>

    <!-- Instructions Modal -->
    <div class="modal" id="instructionsModal">
        <div class="modal-content rounded-[28px] border border-slate-200 bg-white p-6 shadow-hard dark:border-slate-800 dark:bg-slate-900">
            <div class="modal-header">
                <h2>Photo Guide</h2>
                <button class="close-btn" onclick="closeInstructionsModal()">×</button>
            </div>
            <div class="modal-body" id="instructionsBody">
                <!-- Instructions will be loaded here by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="main.js"></script>
</body></html>
