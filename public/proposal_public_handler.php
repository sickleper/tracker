<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

// Accept JSON or POST
$json = file_get_contents('php://input');
$data = json_decode($json, true) ?? $_POST;

$action = $data['action'] ?? '';

if ($action === 'accept') {
    $hash = trim((string)($data['hash'] ?? ''));
    $signerName = trim((string)($data['signer_name'] ?? ''));

    if (empty($hash) || empty($signerName)) {
        echo json_encode(['success' => false, 'message' => 'Missing required information.']);
        exit;
    }

    if (mb_strlen($signerName) > 255) {
        echo json_encode(['success' => false, 'message' => 'Signer name is too long.']);
        exit;
    }

    // 1. Update Status via API
    // Assuming we have an endpoint for public acceptance or we use a general patch
    $apiRes = makeApiCall("/api/public/proposals/by-hash/{$hash}/accept", [
        'signer_name' => $signerName
    ], 'POST');

    if ($apiRes && ($apiRes['success'] ?? false)) {
        // 2. Notify Admin
        sendAcceptanceNotification($apiRes['data'], $signerName);
        
        echo json_encode(['success' => true, 'message' => 'Proposal accepted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => $apiRes['message'] ?? 'Failed to process acceptance.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

function sendAcceptanceNotification($proposal, $signerName) {
    try {
        $safeClientName = htmlspecialchars($proposal['lead']['client_name'] ?? 'Unknown Client', ENT_QUOTES, 'UTF-8');
        $safeSignerName = htmlspecialchars($signerName, ENT_QUOTES, 'UTF-8');
        $safeClientEmail = $proposal['lead']['client_email'] ?? '';
        $safeProposalId = (int)($proposal['id'] ?? 0);
        $safeTotal = number_format((float)($proposal['total'] ?? 0), 2);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $GLOBALS['mail_host'] ?? $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $GLOBALS['mail_username'] ?? $_ENV['MAIL_USERNAME'];
        $mail->Password = $GLOBALS['mail_password'] ?? $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $GLOBALS['mail_encryption'] ?? $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $mail->Port = $GLOBALS['mail_port'] ?? $_ENV['MAIL_PORT'] ?? 587;

        $adminEmail = $GLOBALS['mail_from_address'] ?? $GLOBALS['mail_username'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? '';
        if ($adminEmail === '') {
            throw new Exception('Mail sender address is not configured.');
        }
        $mail->setFrom($adminEmail, 'System Alerts');
        $mail->addAddress($adminEmail);
        if ($safeClientEmail && filter_var($safeClientEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($safeClientEmail); // Copy to client
        }
        
        $mail->isHTML(true);
        $mail->Subject = "PROPOSAL ACCEPTED: " . ($proposal['lead']['client_name'] ?? 'Unknown Client') . " (#" . str_pad((string)$safeProposalId, 5, '0', STR_PAD_LEFT) . ")";
        
        $mail->Body = "
            <div style='font-family:sans-serif; padding:40px; border:1px solid #eee; border-radius:20px;'>
                <h2 style='color:#059669;'>Proposal Accepted!</h2>
                <p>The proposal for <strong>{$safeClientName}</strong> has been officially accepted.</p>
                <p><strong>Signed By:</strong> {$safeSignerName}</p>
                <p><strong>Total Amount:</strong> €{$safeTotal}</p>
                <p><strong>Date:</strong> " . date('d M Y H:i') . "</p>
                <hr style='border:none; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#999;'>You can now proceed to convert this lead to a work order in the admin panel.</p>
            </div>
        ";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Acceptance Notification Error: " . $e->getMessage());
        return false;
    }
}
