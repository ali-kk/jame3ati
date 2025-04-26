<?php
// backend/get_profile_pic.php
// This endpoint generates a presigned URL for profile pictures stored in S3 and redirects to it

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Include dependencies
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../assets/images/3.jpg');
    exit;
}

// Validate CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    header('Location: ../assets/images/3.jpg');
    exit;
}

// Get the S3 key from the query parameter
$s3Key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
if (!$s3Key) {
    header('Location: ../assets/images/3.jpg');
    exit;
}

// Verify that the key is a profile picture path
if (!str_starts_with($s3Key, 'profile_pictures/')) {
    header('Location: ../assets/images/3.jpg');
    exit;
}

// Set up S3 client
try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? null;
    $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? null;
    $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
    $awsRegion = $_ENV['AWS_REGION'] ?? null;
    
    if (empty($s3AccessKey) || empty($s3SecretKey) || empty($s3BucketName) || empty($awsRegion)) {
        throw new Exception("S3 configuration is incomplete.");
    }
    
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => $awsRegion,
        'credentials' => [
            'key' => $s3AccessKey,
            'secret' => $s3SecretKey
        ]
    ]);
    
    // Generate a presigned URL for the profile picture
    $cmd = $s3Client->getCommand('GetObject', [
        'Bucket' => $s3BucketName,
        'Key' => $s3Key
    ]);
    
    // Set expiration time to 1 hour
    $presignedUrl = (string) $s3Client->createPresignedRequest($cmd, '+1 hour')->getUri();
    
    // Redirect to the presigned URL
    header('Location: ' . $presignedUrl);
    exit;
    
} catch (Exception $e) {
    error_log("Error in get_profile_pic.php: " . $e->getMessage());
    
    // Redirect to default image on error
    header('Location: ../assets/images/3.jpg');
    exit;
}
?>
