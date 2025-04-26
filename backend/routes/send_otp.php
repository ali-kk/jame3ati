<?php
// backend/routes/send_otp.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/OtpController.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$result = $otpController->processSendOtp($email);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
