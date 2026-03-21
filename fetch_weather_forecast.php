<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Function to parse .env file
function get_env_variable($key) {
    $env_path = __DIR__ . '/../.env';
    if (!file_exists($env_path)) {
        return null;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === $key) {
            // Remove quotes from value
            return trim($value, '"');
        }
    }

    return null;
}

// Get OpenAI API Key
$openai_api_key = get_env_variable('OPENAI_API_KEY');

// Dublin coordinates
$latitude = 53.3498;
$longitude = -6.2603;
$timezone = 'Europe/Dublin';
$forecastDays = 14; // 14 days (2 weeks)
// Hourly thresholds for safe roof work
$hourlyPrecipitationProbabilityThreshold = 30; // % chance of any precipitation (increased from 20)
$hourlyPrecipitationSumThreshold = 0.3; // mm, maximum acceptable precipitation sum per hour (increased from 0.1)
$hourlySnowfallThreshold = 0.0; // cm, maximum acceptable snowfall per hour
$hourlyWindSpeedThreshold = 25.0; // km/h, maximum acceptable wind speed per hour (increased from 20)

// --- AI Suggestion Parameters ---
$optimalTempMin = 10.0; // Celsius
$optimalTempMax = 20.0; // Celsius
// --- End AI Suggestion Parameters ---

$apiUrl = "https://api.open-meteo.com/v1/forecast?" .
          "latitude=$latitude&longitude=$longitude&hourly=temperature_2m,precipitation_probability,precipitation,snowfall,wind_speed_10m&" .
          "timezone=" . urlencode($timezone) . "&forecast_days=$forecastDays";

// Function to calculate a score for a weather slot
function calculateScore($slot) {
    global $optimalTempMin, $optimalTempMax, $hourlyPrecipitationProbabilityThreshold, $hourlyWindSpeedThreshold;

    $temp = $slot['temperature'];
    $precipProb = $slot['precipitation_probability'];
    $wind = $slot['wind_speed'];

    // Temperature Score (0-100)
    $tempScore = 0;
    if ($temp >= $optimalTempMin && $temp <= $optimalTempMax) {
        // Higher score for temps in the middle of the optimal range
        $midOptimal = ($optimalTempMin + $optimalTempMax) / 2;
        $tempScore = 100 - (abs($temp - $midOptimal) / (($optimalTempMax - $optimalTempMin) / 2)) * 50;
    } else {
        // Penalize temps outside the range
        $penalty = min(abs($temp - $optimalTempMin), abs($temp - $optimalTempMax));
        $tempScore = max(0, 50 - $penalty * 5); // Decrease score faster outside range
    }

    // Precipitation Score (0-100) - Lower is better
    $precipScore = 100 - ($precipProb / $hourlyPrecipitationProbabilityThreshold) * 100;

    // Wind Score (0-100) - Lower is better
    $windScore = 100 - ($wind / $hourlyWindSpeedThreshold) * 100;

    // Weighted final score
    $finalScore = ($tempScore * 0.3) + ($precipScore * 0.4) + ($windScore * 0.3);
    
    return $finalScore;
}

// Function to get suggestion from ChatGPT
function get_chatgpt_suggestion($topSlots, $apiKey) {
    if (!$apiKey) {
        return "OpenAI API key is not configured.";
    }
    if (empty($topSlots)) {
        return "No suitable time slots were found to generate a summary.";
    }

    $prompt = "You are an expert roofing assistant. Based on the following list of the top-rated hourly weather slots for roof waterproofing in Dublin, please provide a concise summary of the best options. Briefly explain why these times are good choices (e.g., 'early next week looks promising with several slots showing low wind and ideal temperatures'). The data is already filtered for low rain, no snow, and low wind.\n\n";
    
    $prompt .= "Top 5 Available Slots (sorted by best score):\n";
    foreach ($topSlots as $slot) {
        $prompt .= sprintf("  - Date: %s, Time: %s:00, Temp: %.1f°C, Precip Prob: %d%%, Wind: %.1f km/h, Score: %d\n", $slot['date'], $slot['time'], $slot['temperature'], $slot['precipitation_probability'], $slot['wind_speed'], $slot['score']);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return 'cURL Error:' . curl_error($ch);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    return "Failed to get a suggestion from the AI.";
}


$weatherData = [];
$error = null;

try {
    $response = @file_get_contents($apiUrl);

    if ($response === FALSE) {
        $error = "Failed to fetch weather data from Open-Meteo API. Check network connection or API URL.";
    } else {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "Failed to parse weather data: " . json_last_error_msg();
        } elseif (isset($data['error'])) {
            $error = "Open-Meteo API Error: " . ($data['reason'] ?? 'Unknown error');
        } elseif (isset($data['hourly']) && isset($data['hourly']['time'])) {
            $hourlyTimes = $data['hourly']['time'];
            $hourlyTemperatures = $data['hourly']['temperature_2m'];
            $hourlyPrecipitationProbabilities = $data['hourly']['precipitation_probability'];
            $hourlyPrecipitation = $data['hourly']['precipitation'];
            $hourlySnowfall = $data['hourly']['snowfall'];
            $hourlyWindSpeed = $data['hourly']['wind_speed_10m'];

            $availableHourlySlots = [];
            foreach ($hourlyTimes as $index => $timeString) {
                $dateTime = new DateTime($timeString);
                $date = $dateTime->format('Y-m-d');
                $hour = $dateTime->format('H');

                // Filter by working hours (Monday to Saturday, 7 AM to 5 PM)
                $dayOfWeek = $dateTime->format('N'); // 1 (for Monday) through 7 (for Sunday)
                $hourInt = (int)$hour;
                if (!($dayOfWeek >= 1 && $dayOfWeek <= 6 && $hourInt >= 7 && $hourInt <= 17)) {
                    continue; // Skip non-working hours
                }

                $temp = $hourlyTemperatures[$index] ?? null;
                $precipProb = $hourlyPrecipitationProbabilities[$index] ?? null;
                $precipSum = $hourlyPrecipitation[$index] ?? null;
                $snow = $hourlySnowfall[$index] ?? null;
                $wind = $hourlyWindSpeed[$index] ?? null;

                // NEW LOGIC: Include ALL slots with no rain (low precip probability and sum)
                // This shows dry periods even if wind/temp aren't ideal
                if ($temp !== null && $precipProb !== null && $precipSum !== null && $snow !== null && $wind !== null &&
                    $precipProb <= $hourlyPrecipitationProbabilityThreshold &&
                    $precipSum <= $hourlyPrecipitationSumThreshold &&
                    $snow <= $hourlySnowfallThreshold) {
                    // Include this slot - we'll filter extreme wind later via scoring
                    
                    $slotData = [
                        'time' => $hour,
                        'temperature' => $temp,
                        'precipitation_probability' => $precipProb,
                        'precipitation_sum' => $precipSum,
                        'snowfall_sum' => $snow,
                        'wind_speed' => $wind
                    ];
                    
                    // Calculate and store score for individual slots
                    $slotData['score'] = calculateScore($slotData);

                    if (!isset($availableHourlySlots[$date])) {
                        $availableHourlySlots[$date] = [];
                    }
                    $availableHourlySlots[$date][] = $slotData;
                }
            }

            // Prepare all individual slots for AI summary (before day grouping)
            $allIndividualSlots = [];
            foreach ($availableHourlySlots as $date => $slots) {
                foreach ($slots as $slot) {
                    $allIndividualSlots[] = array_merge(['date' => $date], $slot);
                }
            }

            // Sort all individual slots from best to worst based on score for AI summary
            usort($allIndividualSlots, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Get AI summary for the top 5 individual slots
            $topSlots = array_slice($allIndividualSlots, 0, 5);
            $ai_suggestion = get_chatgpt_suggestion($topSlots, $openai_api_key);

            // Group by date and get first 10 working days
            $slotsByDate = [];
            foreach($allIndividualSlots as $slot) {
                $slotsByDate[$slot['date']][] = $slot;
            }
            
            // Get first 10 days (or all if less than 10)
            $first10Days = array_slice(array_keys($slotsByDate), 0, 10);
            
            // Collect all slots from these 10 days
            $tableSlots = [];
            foreach($first10Days as $date) {
                foreach($slotsByDate[$date] as $slot) {
                    $tableSlots[] = $slot;
                }
            }
            
            // Sort all slots by score (best first)
            usort($tableSlots, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Format slots for table display
            $formattedTableSlots = [];
            foreach($tableSlots as $slot) {
                $formattedTableSlots[] = [
                    'date' => $slot['date'],
                    'time' => $slot['time'],
                    'temperature' => round($slot['temperature'], 1),
                    'precipitation_probability' => round($slot['precipitation_probability']),
                    'wind_speed' => round($slot['wind_speed'], 1),
                    'score' => round($slot['score'])
                ];
            }

            $weatherData = [
                'slots' => $formattedTableSlots,
                'ai_suggestion' => $ai_suggestion,
                'total_days' => count($first10Days),
                'total_slots' => count($formattedTableSlots)
            ];

        } else {
            $error = "Unexpected API response format.";
        }
    }
} catch (Exception $e) {
    $error = "Exception caught: " . $e->getMessage();
}

if ($error) {
    echo json_encode(['success' => false, 'message' => $error]);
} else {
    echo json_encode(['success' => true, 'data' => $weatherData]);
}
?>
