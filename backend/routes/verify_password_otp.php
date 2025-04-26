<?php
// backend/routes/verify_password_otp.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/OtpController.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->load();

use Aws\Ses\SesClient;

$sesClient = new SesClient([
    'version' => 'latest',
    'region'  => $_ENV['AWS_REGION'],
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    ],
]);

$otpController = new OtpController($conn, $sesClient);

// Get JSON data from request
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Validate input
if (!isset($input['otp_code']) || !isset($input['request_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];
$otpCode = $input['otp_code'];
$requestId = $input['request_id'];

$result = $otpController->verifyPasswordOtp($userId, $requestId, $otpCode);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
