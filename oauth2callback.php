<?php
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', $_SERVER['TRACKER_ERROR_LOG'] ?? $_ENV['TRACKER_ERROR_LOG'] ?? '/tmp/tracker_oauth_error.log');
ini_set('session.save_path', '/home/workorders/tmp');

require_once '../vendor/autoload.php';
// config.php will load .env and setup session path
include 'config.php'; 

// Ensure session is started after session.save_path is set by config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Ensure database connection is available


// Initialize Google Client
$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? null);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? null);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? null);
$client->addScope('email');
$client->addScope('profile');
// Optionally add other scopes as needed, e.g., for Google Drive access
// $client->addScope(Google\Service\Drive::DRIVE_METADATA_READONLY);

// Error handling helper
function handleOAuthError($message, $e = null) {
    error_log("OAuth Error: " . $message . ($e ? " Exception: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() : ""));
    // $_SESSION['oauth_error'] = $message; // No longer needed as we're not redirecting to error.php
    // header('Location: /error.php'); // Removed redirection to error.php
    exit("An OAuth error occurred. Please check server logs for details."); // Display a generic message and exit
}

function setTrackerApiCookie(string $token): void
{
    setcookie('apitoken', $token, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function renderLoginScreen(?string $error = null, bool $loggedOut = false): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tracker Login</title>
        <link rel="stylesheet" href="dist/output.css">
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-6">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-xl border border-slate-200 p-8">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl font-black">T</div>
            <h1 class="mt-6 text-center text-2xl font-black uppercase tracking-tight"><?php echo $loggedOut ? 'Logged Out' : 'Tracker Login'; ?></h1>
            <p class="mt-3 text-center text-sm text-slate-600">
                <?php echo $loggedOut ? 'Your tracker session has been closed.' : 'Choose Google sign-in or use the admin password login.'; ?>
            </p>
            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <a href="oauth2callback.php?start=google" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black uppercase tracking-widest text-white hover:bg-indigo-700 transition-colors">
                Login With Google
            </a>
            <div class="my-6 flex items-center gap-3 text-xs font-black uppercase tracking-widest text-slate-400">
                <div class="h-px flex-1 bg-slate-200"></div>
                <span>Admin</span>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>
            <form method="post" action="oauth2callback.php" class="space-y-4">
                <input type="hidden" name="login_mode" value="admin_password">
                <div>
                    <label for="email" class="mb-1 block text-[11px] font-black uppercase tracking-widest text-slate-500">Email</label>
                    <input id="email" name="email" type="email" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold outline-none focus:border-indigo-500" autocomplete="username">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-[11px] font-black uppercase tracking-widest text-slate-500">Password</label>
                    <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold outline-none focus:border-indigo-500" autocomplete="current-password">
                </div>
                <button type="submit" class="w-full rounded-2xl bg-slate-900 px-6 py-3 text-sm font-black uppercase tracking-widest text-white hover:bg-black transition-colors">
                    Admin Password Login
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['login_mode'] ?? '') === 'admin_password') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        renderLoginScreen('Email and password are required.');
    }

    $apiUrl = rtrim($_ENV['LARAVEL_API_URL'] ?? '', '/') . '/api/login';
    $payload = json_encode([
        'email' => $email,
        'password' => $password,
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($payload),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        renderLoginScreen('Unable to reach the login service. ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !isset($data['token'])) {
        renderLoginScreen($data['message'] ?? 'Invalid login details.');
    }

    $superAdminEmail = trackerSuperAdminEmail();
    if (($data['email'] ?? '') !== $superAdminEmail) {
        $revoke = curl_init();
        curl_setopt($revoke, CURLOPT_URL, rtrim($_ENV['LARAVEL_API_URL'] ?? '', '/') . '/api/logout');
        curl_setopt($revoke, CURLOPT_POST, true);
        curl_setopt($revoke, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($revoke, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $data['token'],
        ]);
        curl_exec($revoke);
        curl_close($revoke);
        renderLoginScreen('Password login is restricted to the system administrator.');
    }

    $_SESSION['api_token'] = $data['token'];
    $_SESSION['user_id'] = $data['user_id'] ?? null;
    $_SESSION['tenant_id'] = $data['tenant_id'] ?? ($_SESSION['tenant_id'] ?? null);
    $_SESSION['user_auth_id'] = $data['user_auth_id'] ?? null;
    $_SESSION['role_id'] = $data['role_id'] ?? ($_SESSION['role_id'] ?? null);
    $_SESSION['is_office'] = !empty($data['is_office']);
    $_SESSION['user_name'] = $data['name'] ?? 'Admin';
    $_SESSION['user_email'] = $data['email'] ?? $email;
    $_SESSION['email'] = $data['email'] ?? $email;
    unset($_SESSION['access_token'], $_SESSION['google_id'], $_SESSION['logged_out']);

    setTrackerApiCookie($data['token']);
    header('Location: admin/index.php');
    exit();
}

// Handle the OAuth2 callback
if (isset($_GET['code'])) {
    try {
        if (empty($_GET['state']) || empty($_SESSION['oauth2state']) || !hash_equals($_SESSION['oauth2state'], $_GET['state'])) {
            unset($_SESSION['oauth2state']);
            handleOAuthError("Invalid OAuth state.");
        }
        unset($_SESSION['oauth2state']);

        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        // Check if the token response contains an error
        if (isset($token['error'])) {
            handleOAuthError("Google API returned an error during token exchange: " . $token['error'] . " - " . ($token['error_description'] ?? 'No description provided'));
        }

        $client->setAccessToken($token);

        // Store the access token in the session for future use
        $_SESSION['access_token'] = $token;

        // Get user profile information
        $oauth2 = new Google\Service\Oauth2($client);
        $googleUser = $oauth2->userinfo->get();

        $email = $googleUser->getEmail();
        $name = $googleUser->getName();
        $googleId = $googleUser->getId();

        // Prepare data for Laravel API
        $api_data_payload = [
            'email' => $email,
            'name' => $name,
            'google_id' => $googleId
        ];
        $api_data_json = json_encode($api_data_payload);

        // Make a POST request to the Laravel API to get a Sanctum token
        $ch = curl_init();
        $api_url = $_ENV['LARAVEL_API_URL'] . '/api/google-auth-login';
        curl_setopt($ch, CURLOPT_URL, $api_url); 
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json', // Added this line
            'Content-Length: ' . strlen($api_data_json)
        ]);

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($api_response === false) {
            handleOAuthError("Failed to connect to Laravel API: " . $curl_error);
        }

        $api_data = json_decode($api_response, true);

        if ($http_code !== 200 || !isset($api_data['token'])) {
            handleOAuthError("Laravel API login failed with status {$http_code}: " . ($api_data['message'] ?? 'No token or description provided'));
        }

        $_SESSION['api_token'] = $api_data['token'];
        $_SESSION['user_id'] = $api_data['user_id'];
        $_SESSION['tenant_id'] = $api_data['tenant_id'] ?? ($_SESSION['tenant_id'] ?? null);
        $_SESSION['user_auth_id'] = $api_data['user_auth_id']; // Assuming user_auth_id is returned by the API
        $_SESSION['role_id'] = $api_data['role_id'] ?? ($_SESSION['role_id'] ?? null);
        $_SESSION['is_office'] = !empty($api_data['is_office']);
        $_SESSION['user_name'] = $api_data['name'];
        $_SESSION['user_email'] = $api_data['email'];
        $_SESSION['email'] = $api_data['email'];
        $_SESSION['google_id'] = $api_data['google_id'];
        unset($_SESSION['logged_out']);

        setTrackerApiCookie($api_data['token']);

        // Redirect to the dashboard or intended page
        header('Location: index.php');
        exit();

    } catch (Google\Service\Exception $e) {
        handleOAuthError("Google Service Error during token exchange or user info retrieval.", $e);
    } catch (Google\Exception $e) {
        handleOAuthError("Google Client Library Error during token exchange or user info retrieval.", $e);
    } catch (Exception $e) {
        handleOAuthError("An unexpected error occurred during OAuth callback.", $e);
    }

} else {
    // Check if essential client configurations are present
    if (empty($_ENV['GOOGLE_CLIENT_ID']) || empty($_ENV['GOOGLE_CLIENT_SECRET']) || empty($_ENV['GOOGLE_REDIRECT_URI'])) {
        handleOAuthError("Google OAuth environment variables are not fully configured. Please check GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in your .env file.");
    }

    if (($_GET['start'] ?? '') !== 'google') {
        renderLoginScreen(null, !empty($_SESSION['logged_out']));
    }

    // If no code, initiate the OAuth flow (redirect to Google login)
    unset($_SESSION['logged_out']);
    $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
    $client->setState($_SESSION['oauth2state']);
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}
