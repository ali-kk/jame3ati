<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


// --- Load Environment Variables ---
require_once __DIR__ . '/../vendor/autoload.php'; 

use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    http_response_code(500); error_log("FATAL: Could not load .env file: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error' => 'Server configuration error.']); exit;
}


// --- Include Prompt ---
require_once 'ai_prompt.php'; // Defines $aiInstructions array

require_once 'config/db.php'; // Defines $aiInstructions array


// --- Authentication & Authorization Check ---
$userId = $_SESSION['user_id'] ?? null;
$isAuthorized = false;
$authMessage = 'Access Denied.'; // Default message

if (!$userId) {
    $authMessage = 'Authentication required.';
    http_response_code(401); // Unauthorized
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } // Close DB if opened
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $authMessage, 'logout' => true]);
    exit;
} else {
    // User ID exists in session, check status in DB
    try {
        $stmtStatus = $conn->prepare("SELECT user_status FROM users WHERE id = ?");
        if(!$stmtStatus) throw new Exception("Prepare failed: ".$conn->error);

        $stmtStatus->bind_param("i", $userId);
        if(!$stmtStatus->execute()) throw new Exception("Execute failed: ".$stmtStatus->error);

        $resultStatus = $stmtStatus->get_result();
        if ($userDb = $resultStatus->fetch_assoc()) {
            if ($userDb['user_status'] === 'perm') {
                $isAuthorized = true; // User found and status is 'perm'
            } else {
                 $authMessage = 'User account is not active or permitted.';
                 error_log("API Auth Error: User {$userId} status is '{$userDb['user_status']}'");
            }
        } else {
            // User ID from session not found in DB - treat as invalid session
            $authMessage = 'Invalid session user.';
            error_log("API Auth Error: User ID {$userId} from session not found in DB.");
        }
        $stmtStatus->close();
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Auth DB Error: " . $e->getMessage());
        $authMessage = 'Error verifying user status.';
         // Send generic error to client
         header('Content-Type: application/json; charset=utf-8');
         echo json_encode(['error' => $authMessage]);
         if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
         exit;
    }
}

if (!$isAuthorized) {
    http_response_code(403); // Forbidden
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } // Close DB
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $authMessage, 'logout' => true]);
    exit;
}
// If we reach here, the user IS authorized
// --- End Auth Check ---


// --- Define Helper Function for Allowed Origins ---
function isOriginAllowed($origin, $allowedOriginsArray) {
    return in_array($origin, $allowedOriginsArray, true);
}

// --- Load configuration from $_ENV ---
$geminiApiKey = $_ENV['GEMINI_API_KEY'] ?? null;
$allowedOriginsString = $_ENV['ALLOWED_ORIGINS'] ?? '';
$allowedOriginsArray = array_filter(array_map('trim', explode(',', $allowedOriginsString)));

// --- Origin Check ---
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Set default Content-Type header (already set earlier during error checks if they happened)
header("Content-Type: application/json; charset=utf-8");

if ($requestOrigin && isOriginAllowed($requestOrigin, $allowedOriginsArray)) {
    header("Access-Control-Allow-Origin: $requestOrigin");
} else if ($requestOrigin) {
    http_response_code(403); // Forbidden
    error_log("API Request Blocked: Origin '$requestOrigin' not in allowed list.");
    echo json_encode(['error' => 'Origin not allowed']);
     if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } // Close DB connection before exiting
    exit;
} // Allow requests with no origin header for now

// --- Allow other CORS headers ---
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
     if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    exit;
}

// --- Get Input ---
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null && json_last_error() !== JSON_ERROR_NONE) { /* ... input error handling ... */ if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit; }
if (empty($input['message'])) { /* ... input error handling ... */ if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit; }

// --- Check API Key ---
if (!$geminiApiKey) {
    http_response_code(500); error_log("API Error: GEMINI_API_KEY not found."); echo json_encode(['error' => 'API key not configured']); if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit;
}

// --- Prepare Gemini API request ---
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $geminiApiKey;
$aiInstructionsContent = $aiInstructions['content'] ?? 'Default instructions: Be helpful.';
$data = [ "contents" => [ [ "parts" => [ ["text" => $aiInstructionsContent], ["text" => $input['message']] ] ] ] ];

// --- Send cURL Request ---
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$apiResponse = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch);
curl_close($ch);

// --- Handle Gemini API response ---
if ($curlError) { /* ... curl error handling ... */ if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit; }
if ($httpCode !== 200) { /* ... http error handling ... */ if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit; }

$responseData = json_decode($apiResponse, true);
$responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
if ($responseText === null) { /* ... invalid response handling ... */ if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } exit; }

// --- Return Success Response ---
echo json_encode(['response' => $responseText]);

// --- Close DB Connection if still open ---
if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) {
    $conn->close();
}
exit; // Explicitly exit after successful response
?>