<?php
require_once __DIR__ . '/../config.php';

ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/debug.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../tracker_data.php';

$actionInput = $_POST['action'] ?? $_GET['action'] ?? '';
$action = is_string($actionInput) ? trim($actionInput) : '';

// Allow specific actions without internal login if needed for public form
$publicActions = ['get_public_categories', 'submit_public_booking', 'get_public_slots'];
$isPublic = in_array($action, $publicActions);

if (!$isPublic && !isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

function requireLeadHandlerId($value, string $label = 'ID'): ?int {
    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validated === false || $validated === null) {
        echo json_encode(['success' => false, 'message' => "Invalid {$label}"]);
        return null;
    }
    return (int) $validated;
}

try {
    switch ($action) {
        case 'get_public_categories':
            $response = makeApiCall('/api/public/leads/categories');
            echo json_encode($response);
            break;

        case 'submit_public_booking':
            $data = $_POST;
            unset($data['action']);
            $response = makeApiCall('/api/public/leads', $data, 'POST');
            echo json_encode($response);
            break;

        case 'get_public_slots':
            $params = [
                'date' => $_GET['date'] ?? '',
                'latlng' => $_GET['latlng'] ?? '',
                'show_all' => 0
            ];
            // Bridge to the existing internal slots logic via PUBLIC API
            $response = makeApiCall('/api/public/booking/slots', $params);
            echo json_encode($response);
            break;

        case 'list_email_leads':
            $params = [
                'page' => $_GET['page'] ?? 1,
                'status' => $_GET['status'] ?? null,
                'category_id' => $_GET['category_id'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $response = makeApiCall('/api/email-leads', $params);
            echo json_encode($response);
            break;

        case 'fetch_email_leads':
            $params = [
                'category_id' => $_POST['category_id'] ?? null
            ];
            $response = makeApiCall('/api/email-leads/fetch', $params, 'POST');
            echo json_encode($response);
            break;

        case 'extract_email_lead':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'email lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/email-leads/{$id}/extract", [], 'POST');
            echo json_encode($response);
            break;

        case 'convert_email_lead':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'email lead ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action']);
            $response = makeApiCall("/api/email-leads/{$id}/convert", $data, 'POST');
            echo json_encode($response);
            break;

        case 'generate_ai_reply':
            $params = [
                'email_id' => $_POST['email_id'] ?? null,
                'lead_id' => $_POST['lead_id'] ?? null
            ];
            $response = makeApiCall('/api/ai/generate-reply', $params, 'POST');
            echo json_encode($response);
            break;

        case 'send_email':
            $params = [
                'email_id' => $_POST['email_id'] ?? null,
                'recipient' => $_POST['recipient'] ?? null,
                'subject' => $_POST['subject'] ?? null,
                'message' => $_POST['message'] ?? null
            ];
            $response = makeApiCall('/api/ai/send-reply', $params, 'POST');
            echo json_encode($response);
            break;

        case 'send_confirmation_email':
            $params = [
                'lead_id' => $_POST['lead_id'] ?? null,
                'followup_id' => $_POST['followup_id'] ?? null,
                'recipient' => $_POST['recipient'] ?? null,
                'subject' => $_POST['subject'] ?? null,
                'message' => $_POST['message'] ?? null
            ];
            $response = makeApiCall('/api/ai/send-reply', $params, 'POST');
            echo json_encode($response);
            break;

        case 'update_booking_slot':
            $params = [
                'lead_id' => $_POST['lead_id'] ?? null,
                'date' => $_POST['date'] ?? null,
                'time' => $_POST['time'] ?? null
            ];
            $response = makeApiCall('/api/booking/update-slot', $params, 'POST');
            echo json_encode($response);
            break;

        case 'append_followup_remark':
            $id = requireLeadHandlerId($_POST['followup_id'] ?? null, 'follow-up ID');
            if ($id === null) {
                break;
            }
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($note === '') {
                echo json_encode(['success' => false, 'message' => 'Note is required']);
                break;
            }
            $response = makeApiCall("/api/leads/followups/{$id}/append-remark", ['note' => $note], 'POST');
            echo json_encode($response);
            break;

        case 'update_email_lead_status':
            $id = $_POST['id'] ?? null;
            $status = $_POST['status'] ?? null;
            if (!$id || !$status) {
                echo json_encode(['success' => false, 'message' => 'Missing data']);
                break;
            }
            $response = makeApiCall("/api/email-leads/{$id}/status", ['status' => $status], 'PATCH');
            echo json_encode($response);
            break;

        case 'get_categories_full':
            $params = [];
            if (isset($_GET['all'])) $params['all'] = $_GET['all'];
            $response = makeApiCall('/api/leads/categories', $params);
            echo json_encode($response);
            break;

        case 'get_clients_full':
            $response = makeApiCall('/api/clients/full-list');
            echo json_encode($response);
            break;

        case 'get_templates':
            $response = makeApiCall('/api/proposals/templates');
            echo json_encode($response);
            break;

        case 'update_client_portal':
            $id = requireLeadHandlerId($_POST['client_id'] ?? null, 'client ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action'], $data['client_id']);
            // Ensure boolean fields are handled correctly (checkboxes)
            $data['is_wo_form_enabled'] = isset($_POST['is_wo_form_enabled']) ? 1 : 0;
            $response = makeApiCall("/api/clients/{$id}/update-details", $data, 'POST');
            echo json_encode($response);
            break;

        case 'get_category_details':
            $id = requireLeadHandlerId($_GET['id'] ?? null, 'category ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/categories/{$id}");
            echo json_encode($response);
            break;

        case 'get_global_settings':
            $response = makeApiCall('/api/settings');
            echo json_encode($response);
            break;

        case 'update_global_settings':
            $data = $_POST;
            unset($data['action']);
            $response = makeApiCall('/api/settings/bulk', ['settings' => $data], 'POST');
            echo json_encode($response);
            break;

        case 'create_category':
            $data = $_POST;
            unset($data['action']);
            $response = makeApiCall("/api/leads/categories", $data, 'POST');
            echo json_encode($response);
            break;

        case 'update_category':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'category ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action'], $data['id']);
            $response = makeApiCall("/api/leads/categories/{$id}", $data, 'PATCH');
            echo json_encode($response);
            break;

        case 'delete_category':
            $id = requireLeadHandlerId($_POST['id'] ?? $_GET['id'] ?? null, 'category ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action'], $data['id']);
            $response = makeApiCall("/api/leads/categories/{$id}", $data, 'DELETE');
            echo json_encode($response);
            break;

        case 'list_leads':
            $params = [
                'category_id' => $_POST['category_id'] ?? '',
                'search' => $_POST['search_val'] ?? ''
            ];
            $response = makeApiCall('/api/leads', $params);
            echo json_encode($response);
            break;

        case 'get_lead':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/{$id}");
            echo json_encode($response);
            break;

        case 'create_lead':
            $data = $_POST;
            unset($data['action']);
            if (!isset($data['next_follow_up'])) $data['next_follow_up'] = 'no';
            $response = makeApiCall('/api/leads', $data, 'POST');
            echo json_encode($response);
            break;

        case 'update_lead':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action'], $data['id']);
            if (!isset($data['next_follow_up'])) $data['next_follow_up'] = 'no';
            $response = makeApiCall("/api/leads/{$id}", $data, 'PATCH');
            echo json_encode($response);
            break;

        case 'delete_lead':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/{$id}", [], 'DELETE');
            echo json_encode($response);
            break;

        case 'convert_lead_to_client':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/{$id}/convert", [], 'POST');
            echo json_encode($response);
            break;

        case 'convert_lead_to_project':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/{$id}/convert-to-project", [], 'POST');
            echo json_encode($response);
            break;

        case 'save_followup':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $data = $_POST;
            unset($data['action'], $data['id']);
            $response = makeApiCall("/api/leads/{$id}/followup", $data, 'POST');
            echo json_encode($response);
            break;

        case 'delete_followup':
            $id = requireLeadHandlerId($_POST['id'] ?? null, 'lead ID');
            if ($id === null) {
                break;
            }
            $response = makeApiCall("/api/leads/{$id}/followup", [], 'DELETE');
            echo json_encode($response);
            break;

        case 'get_slots':
            $params = [
                'date' => $_GET['date'] ?? '',
                'latlng' => $_GET['latlng'] ?? '',
                'show_all' => $_GET['show_all'] ?? '0'
            ];
            $response = makeApiCall('/api/booking/slots', $params);
            echo json_encode($response);
            break;

        case 'submit_booking':
            $data = $_POST;
            unset($data['action']);
            $response = makeApiCall('/api/booking/submit', $data, 'POST');
            echo json_encode($response);
            break;

        case 'map_data':
            $period = $_POST['period'] ?? 'this';
            
            // Calculate date ranges
            switch ($period) {
                case 'two_weeks_ago':
                    $start = strtotime('monday -2 weeks');
                    $end = strtotime('sunday', $start);
                    break;
                case 'last':
                    $start = strtotime('monday last week');
                    $end = strtotime('sunday last week');
                    break;
                case 'next':
                    $start = strtotime('monday next week');
                    $end = strtotime('sunday next week');
                    break;
                default: // this
                    $start = strtotime('monday this week');
                    $end = strtotime('sunday this week');
                    break;
            }
            
            $startDate = date('Y-m-d 00:00:00', $start);
            $endDate = date('Y-m-d 23:59:59', $end);
            
            // Fetch all leads (API currently returns all, we filter here)
            $apiRes = makeApiCall('/api/leads');
            
            $mapData = [];
            if ($apiRes && ($apiRes['success'] ?? false)) {
                foreach ($apiRes['data'] as $lead) {
                    // Check if lead has a follow-up in the range
                    if (!empty($lead['follow_ups'])) {
                        $fu = $lead['follow_ups'][0]; // Latest/Active follow-up
                        $fuDate = $fu['next_follow_up_date'];
                        
                        if ($fuDate >= $startDate && $fuDate <= $endDate) {
                            // Valid for map?
                            if (!empty($lead['latlng']) && strpos($lead['latlng'], ',') !== false) {
                                $parts = explode(',', $lead['latlng']);
                                $mapData[] = [
                                    'id' => $lead['id'],
                                    'name' => $lead['client_name'],
                                    'address' => $lead['address'] ?? 'No address provided',
                                    'lat' => floatval($parts[0]),
                                    'lng' => floatval($parts[1]),
                                    'date' => $fuDate, // Send raw ISO date for Moment.js
                                    'cat' => $lead['category']['category_name'] ?? 'General',
                                    'mobile' => $lead['mobile'] ?? 'N/A',
                                    'status' => $lead['status'] ?? 'New',
                                    'remark' => $fu['remark'] ?? ''
                                ];
                            }
                        }
                    }
                }
            }
            
            echo json_encode(['success' => true, 'data' => $mapData, 'period' => $period, 'range' => "$startDate to $endDate"]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
