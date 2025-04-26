<?php
// backend/routes/change_email.php
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
if (!isset($input['new_email']) || !isset($input['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$newEmail = filter_var($input['new_email'], FILTER_SANITIZE_EMAIL);
$password = $input['password'];
$userId = $_SESSION['user_id'];

// Get current email
$stmt = $conn->prepare("SELECT Email FROM user_credentials WHERE u_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$currentEmail = $user['Email'];

$result = $otpController->sendEmailChangeOtp($userId, $currentEmail, $newEmail);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
