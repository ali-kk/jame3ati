<?php
// Start session
session_start();

// Include database connection
require_once 'config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv; 
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); 
$dotenv->load();

// Set proper content type for JSON response
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// No need to validate CSRF token for GET requests
// if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
//     echo json_encode([
//         'success' => false,
//         'message' => 'Invalid CSRF token'
//     ], JSON_UNESCAPED_UNICODE);
//     exit;
// }

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// --- S3 Setup ---
$s3Client = null; 
$s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
$s3ProfilePicDefault = '../assets/images/3.jpg'; // Default profile pic path

try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? null; 
    $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? null; 
    $awsRegion = $_ENV['AWS_REGION'] ?? null;
    
    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
        $s3Client = new S3Client([
            'version' => 'latest', 
            'region' => $awsRegion, 
            'credentials' => [
                'key' => $s3AccessKey, 
                'secret' => $s3SecretKey
            ]
        ]);
    } else { 
        error_log("get_user_data: S3 config incomplete.");
    }
} catch (Exception $e) { 
    error_log("get_user_data: S3 init error: " . $e->getMessage());
}

try {
    $userId = $_SESSION['user_id'];

    // Determine which query to use based on role
    $sql = "SELECT uc.u_id AS u_id, uc.Email AS email, uc.role_id AS role_id, us.first_name AS first_name, us.last_name AS last_name, us.third_name AS third_name, us.gender AS gender, us.birthday AS birthday, us.city AS city, us.nationality AS nationality, us.academic_title AS academic_title, us.stage AS stage, us.degree AS degree, us.study_mode AS study_mode, dep.dep_name AS department_name, us.profile_pic AS profile_pic FROM user_credentials uc JOIN users us ON uc.u_id = us.id LEFT JOIN departments dep ON us.dep_id = dep.dep_id WHERE uc.u_id = ?";

    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = $result->fetch_assoc();

    // Handle profile picture URL
    if (!empty($user['profile_pic']) && $s3Client) {
        try {
            // Generate a pre-signed URL for the profile picture
            $command = $s3Client->getCommand('GetObject', [
                'Bucket' => $s3BucketName,
                'Key' => $user['profile_pic']
            ]);
            
            $request = $s3Client->createPresignedRequest($command, '+5 minutes');
            $user['profile_pic'] = (string) $request->getUri();
        } catch (AwsException $e) {
            // If there's an error, use default profile pic
            $user['profile_pic'] = $s3ProfilePicDefault;
        }
    } else {
        // If no profile pic or no S3 client, use default
        $user['profile_pic'] = $s3ProfilePicDefault;
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Error in get_user_data.php: " . $e->getMessage());
}

// Close the database connection
$conn->close();
?>
