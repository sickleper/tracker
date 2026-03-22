<?php
require_once __DIR__ . '/config.php';

class EircodeAPI {
    private $cookieFile;
    private $debugMode = false;

    public function __construct($debugMode = false) {
        $this->cookieFile = sys_get_temp_dir() . '/eircode_session_' . md5(__FILE__) . '.txt';
        $this->debugMode = $debugMode;

        // Clean up old cookie file if it exists
        if (file_exists($this->cookieFile) && (time() - filemtime($this->cookieFile)) > 3600) {
            unlink($this->cookieFile);
        }
    }

    private function debug($message, $data = null) {
        if ($this->debugMode) {
            error_log("EircodeAPI Debug: " . $message . ($data ? " - " . json_encode($data) : ""));
        }
    }

    /**
     * Method 1: Try with your original static token (most likely to work)
     */
    private function tryStaticToken($eircode) {
        global $eircode_api_key;
        $this->debug("Trying static token method");

        $staticToken = $eircode_api_key ?? '';
        if ($staticToken === '') {
            return ['error' => 'Eircode API token is not configured'];
        }

        $params = [
            'address' => $eircode,
            'language' => 'en',
            'country' => 'IE',
            'token' => $staticToken,
            'version' => '3.1.71'
        ];

        $url = "https://api-finder3.eircode.ie/search?" . http_build_query($params);

        $result = $this->makeRequest($url, [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://finder.eircode.ie/',
            'Origin: https://finder.eircode.ie'
        ]);

        if ($result['success']) {
            $this->debug("Static token successful");
            return [
                'method' => 'static_token',
                'data' => $result['data'],
                'raw_response' => $this->debugMode ? $result['response'] : null
            ];
        }

        $this->debug("Static token failed", $result);
        return ['error' => 'Static token failed: HTTP ' . $result['http_code']];
    }

    /**
     * Method 2: Try to get a fresh token and use it with proper headers
     */
    private function tryFreshToken($eircode) {
        $this->debug("Trying fresh token method");

        // First get a token
        $tokenResult = $this->getFreshToken();
        if (isset($tokenResult['error'])) {
            $this->debug("Fresh token generation failed", $tokenResult);
            return $tokenResult;
        }

        $this->debug("Got fresh token", ['token_preview' => substr($tokenResult, 0, 20) . '...']);

        // Try multiple approaches with the fresh token
        $approaches = [
            [
                'name' => 'Bearer header method',
                'url' => 'https://api-finder3.eircode.ie/search',
                'params' => [
                    'address' => $eircode,
                    'language' => 'en',
                    'country' => 'IE'
                ],
                'headers' => [
                    'Accept: application/json, text/plain, */*',
                    'Authorization: Bearer ' . $tokenResult,
                    'Referer: https://finder.eircode.ie/',
                    'Origin: https://finder.eircode.ie'
                ]
            ],
            [
                'name' => 'Token parameter method',
                'url' => 'https://api-finder3.eircode.ie/search',
                'params' => [
                    'address' => $eircode,
                    'language' => 'en',
                    'country' => 'IE',
                    'token' => $tokenResult,
                    'version' => '3.1.71'
                ],
                'headers' => [
                    'Accept: application/json, text/plain, */*',
                    'Referer: https://finder.eircode.ie/',
                    'Origin: https://finder.eircode.ie'
                ]
            ]
        ];

        foreach ($approaches as $approach) {
            $this->debug("Trying " . $approach['name']);

            if (isset($approach['params'])) {
                $url = $approach['url'] . '?' . http_build_query($approach['params']);
            } else {
                $url = $approach['url'];
            }

            $result = $this->makeRequest($url, $approach['headers']);

            if ($result['success']) {
                $this->debug("Fresh token successful with " . $approach['name']);
                return [
                    'method' => 'fresh_token_' . str_replace(' ', '_', strtolower($approach['name'])),
                    'data' => $result['data'],
                    'raw_response' => $this->debugMode ? $result['response'] : null
                ];
            }

            $this->debug($approach['name'] . " failed", $result);
        }

        return ['error' => 'Fresh token failed with all approaches'];
    }

    /**
     * Method 3: Try alternative API endpoints
     */
    private function tryAlternativeAPIs($eircode) {
        $this->debug("Trying alternative APIs");

        $endpoints = [
            [
                'name' => 'api-finder legacy',
                'url' => 'https://api-finder.eircode.ie/Latest/finderfindaddress',
                'params' => [
                    'address' => $eircode,
                    'language' => 'en',
                    'geographicAddress' => 'true'
                ]
            ],
            [
                'name' => 'direct search',
                'url' => 'https://finder.eircode.ie/api/search',
                'params' => [
                    'q' => $eircode,
                    'language' => 'en'
                ]
            ]
        ];

        foreach ($endpoints as $endpoint) {
            $this->debug("Trying " . $endpoint['name']);

            $url = $endpoint['url'] . '?' . http_build_query($endpoint['params']);

            $result = $this->makeRequest($url, [
                'Accept: application/json',
                'Referer: https://finder.eircode.ie/'
            ]);

            if ($result['success'] && !empty($result['data'])) {
                $this->debug($endpoint['name'] . " successful");
                return [
                    'method' => $endpoint['name'],
                    'data' => $result['data'],
                    'raw_response' => $this->debugMode ? $result['response'] : null
                ];
            }

            $this->debug($endpoint['name'] . " failed", $result);
        }

        return ['error' => 'All alternative APIs failed'];
    }

    /**
     * Method 4: Web scraping as last resort
     */
    private function tryWebScraping($eircode) {
        $this->debug("Trying web scraping");

        // First establish a session
        $sessionResult = $this->makeRequest('https://finder.eircode.ie/', [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5'
        ], true);

        if (!$sessionResult['success']) {
            return ['error' => 'Could not establish session for scraping'];
        }

        // Try to search
        $searchUrl = 'https://finder.eircode.ie/#/search/' . urlencode($eircode);
        $searchResult = $this->makeRequest($searchUrl, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: https://finder.eircode.ie/'
        ], true);

        if (!$searchResult['success']) {
            return ['error' => 'Web scraping request failed'];
        }

        // Look for address patterns
        $html = $searchResult['response'];

        // Try to find JSON data first
        if (preg_match('/\{"[^"]*address[^"]*"[^}]+\}/i', $html, $jsonMatch)) {
            $jsonData = json_decode($jsonMatch[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($jsonData)) {
                $this->debug("Found JSON data in HTML");
                return [
                    'method' => 'web_scraping_json',
                    'data' => $jsonData,
                    'raw_response' => $this->debugMode ? substr($html, 0, 1000) : null
                ];
            }
        }

        // Try HTML patterns
        $patterns = [
            '/<[^>]*class="[^"]*address[^"]*"[^>]*>([^<]+)</i',
            '/<[^>]*class="[^"]*result[^"]*"[^>]*>([^<]+)</i',
            '/<div[^>]*>([^<]*' . preg_quote($eircode, '/') . '[^<]*)</i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $address = trim(strip_tags($matches[1]));
                if (strlen($address) > 5) {
                    $this->debug("Found address via HTML pattern");
                    return [
                        'method' => 'web_scraping_html',
                        'address' => $address,
                        'eircode' => $eircode
                    ];
                }
            }
        }

        return [
            'error' => 'Web scraping found no results',
            'html_length' => strlen($html),
            'html_preview' => $this->debugMode ? substr($html, 0, 500) : null
        ];
    }

    /**
     * Get fresh token using the correct endpoint from the JS code
     */
    public function getFreshToken() {
        // Try the createtoken endpoint first (from the JS code)
        $endpoints = [
            'https://api-finder3.eircode.ie/createtoken',
            'https://api-finder.eircode.ie/Latest/findergetidentity',
            'https://finder.eircode.ie/api/createtoken'
        ];

        foreach ($endpoints as $endpoint) {
            $result = $this->makeRequest($endpoint, [
                'Accept: application/json',
                'Referer: https://finder.eircode.ie/',
                'Origin: https://finder.eircode.ie'
            ]);

            if ($result['success'] && isset($result['data'])) {
                $tokenData = $result['data'];

                // Handle different response formats
                if (isset($tokenData['token'])) {
                    return $tokenData['token'];
                } elseif (isset($tokenData['key'])) {
                    return $tokenData['key'];
                } elseif (is_string($tokenData)) {
                    return $tokenData;
                }
            }
        }

        return ['error' => 'All token endpoints failed'];
    }

    /**
     * Make HTTP request with proper error handling
     */
    private function makeRequest($url, $headers = [], $includeSession = false) {
        $ch = curl_init();

        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive'
        ];

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_ENCODING => '', // Handle gzip automatically
            CURLOPT_FOLLOWLOCATION => true
        ];

        if ($includeSession) {
            $curlOptions[CURLOPT_COOKIEJAR] = $this->cookieFile;
            $curlOptions[CURLOPT_COOKIEFILE] = $this->cookieFile;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'http_code' => 0,
                'error' => $error,
                'response' => null,
                'data' => null
            ];
        }

        $data = null;
        if ($httpCode === 200) {
            $jsonData = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $jsonData;
            }
        }

        return [
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'error' => $httpCode !== 200 ? "HTTP $httpCode" : null,
            'response' => $response,
            'data' => $data
        ];
    }

    /**
     * Process the search result into a consistent format
     */
    private function processResult($result, $eircode) {
        if (isset($result['error'])) {
            return $result;
        }

        $data = $result['data'];

        // Handle different response formats
        if (isset($data['options']) && !empty($data['options'])) {
            $option = $data['options'][0];

            $processed = [
                'address' => $option['value'] ?? 'Address found',
                'eircode' => $eircode,
                'method' => $result['method'],
                'source' => 'Eircode API'
            ];

            // Try to get more details if available
            if (isset($option['link']['href'])) {
                $detailsResult = $this->makeRequest($option['link']['href'], [
                    'Accept: application/json',
                    'Referer: https://finder.eircode.ie/'
                ]);

                if ($detailsResult['success'] && isset($detailsResult['data']['address'])) {
                    $details = $detailsResult['data'];
                    $processed['address'] = implode(", ", $details['address']['label']);

                    if (isset($details['data']['location'])) {
                        $processed['coordinates'] = [
                            'lat' => $details['data']['location']['latitude'],
                            'lng' => $details['data']['location']['longitude']
                        ];
                    }

                    if (isset($details['address']['postcode'])) {
                        $processed['postcode'] = $details['address']['postcode']['value'];
                    }

                    if (isset($details['address']['city'])) {
                        $processed['city'] = $details['address']['city']['value'];
                    }
                }
            }

            return $processed;
        }

        // Handle direct address response
        if (isset($data['address'])) {
            return [
                'address' => is_array($data['address']) ? implode(", ", $data['address']) : $data['address'],
                'eircode' => $eircode,
                'method' => $result['method'],
                'source' => 'Eircode API Direct'
            ];
        }

        // Handle web scraping result
        if (isset($result['address'])) {
            return [
                'address' => $result['address'],
                'eircode' => $eircode,
                'method' => $result['method'],
                'source' => 'Web Scraping'
            ];
        }

        return [
            'error' => 'Could not process result',
            'method' => $result['method'],
            'raw_data' => $this->debugMode ? $data : null
        ];
    }

    /**
     * Main search function - tries all methods in order
     */
    public function searchAddress($eircode) {
        $eircode = strtoupper(trim($eircode));
        $this->debug("Starting search for: " . $eircode);

        $methods = [
            'tryStaticToken',
            'tryFreshToken',
            'tryAlternativeAPIs',
            'tryWebScraping'
        ];

        $allErrors = [];

        foreach ($methods as $method) {
            $this->debug("Attempting method: " . $method);

            try {
                $result = $this->$method($eircode);

                if (!isset($result['error'])) {
                    $processed = $this->processResult($result, $eircode);
                    if (!isset($processed['error'])) {
                        $this->debug("Success with method: " . $method);
                        return $processed;
                    }
                    $allErrors[$method] = $processed['error'];
                } else {
                    $allErrors[$method] = $result['error'];
                }
            } catch (Exception $e) {
                $allErrors[$method] = 'Exception: ' . $e->getMessage();
                $this->debug("Exception in $method", $e->getMessage());
            }
        }

        return [
            'error' => 'All methods failed',
            'eircode' => $eircode,
            'method_errors' => $allErrors,
            'note' => 'Lookup failed. Contact support if the problem persists.'
        ];
    }

    /**
     * Clean up session files
     */
    public function cleanup() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }
}

// Handle the request
if (isset($_GET['eircode'])) {
    $eircode = strtoupper(trim($_GET['eircode']));

    if (empty($eircode)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Eircode cannot be empty']);
        exit;
    }

    $debugMode = isset($_GET['debug']);
    $superAdminEmail = trackerSuperAdminEmail();
    $isAdmin = isTrackerAuthenticated() && $superAdminEmail !== '' && (($_SESSION['email'] ?? '') === $superAdminEmail);

    if ($debugMode && !$isAdmin) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Debug mode is restricted']);
        exit;
    }

    $api = new EircodeAPI($debugMode);

    if ($debugMode) {
        // Enable error reporting for debug mode
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $tokenResult = $api->getFreshToken();
        $result = [
            'debug' => true,
            'eircode' => $eircode,
            'fresh_token_test' => $tokenResult,
            'search_result' => $api->searchAddress($eircode)
        ];
    } else {
        $result = $api->searchAddress($eircode);
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);

    // Cleanup
    $api->cleanup();

} else {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Eircode parameter is required',
        'usage' => [
            'normal' => '?eircode=D24WF60'
        ],
        'methods' => [
            '1. Static token (from your working example)',
            '2. Fresh token from identity endpoint',
            '3. Alternative API endpoints',
            '4. Web scraping fallback'
        ],
        'note' => 'Methods are tried in order until one succeeds. Debug mode is restricted to authenticated admins.'
    ]);
}

?>
