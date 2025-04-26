<?php
// backend/get_user_profile.php
// Fetches the profile data for the currently logged-in user

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Dependencies & Config ---
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv; 
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); 
$dotenv->load();

header('Content-Type: application/json; charset=utf-8');

// --- Auth Check ---
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated',
        'logout' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = ['success' => false, 'message' => 'Failed to load profile data.'];
$s3ProfilePicDefault = '../assets/images/3.jpg'; // Default profile pic path

// --- S3 Setup ---
$s3Client = null; 
$s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
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
        error_log("get_user_profile: S3 config incomplete.");
    }
} catch (Exception $e) { 
    error_log("get_user_profile: S3 init error: " . $e->getMessage());
}

// --- Helper Function for S3 URLs ---
if (!function_exists('get_presigned_s3_url')) {
    function get_presigned_s3_url($s3Client, $bucket, $key, $defaultPath, $expiryString = '+15 minutes') {
        if (!$s3Client || !$key || !$bucket || !str_starts_with($key, 'profile_pictures/')) {
            return $defaultPath; // Return default path if cannot generate
        }
        try {
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket, 
                'Key' => $key
            ]);
            $request = $s3Client->createPresignedRequest($cmd, $expiryString);
            return (string) $request->getUri();
        } catch (AwsException $e) { 
            error_log("S3 Presign URL Error for key {$key}: " . $e->getMessage()); 
            return $defaultPath; 
        }
    }
}

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/config/db.php';
    }
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection unavailable.");
    }

    // Fetch user profile data
    $stmt = $conn->prepare("
        SELECT u.id, u.first_name, u.last_name, u.third_name, u.academic_title, 
               u.gender, u.birthday, u.city, u.dep_id, u.profile_pic, d.dep_name, uc.Email
        FROM users u
        JOIN user_credentials uc ON u.id = uc.u_id
        LEFT JOIN departments d ON u.dep_id = d.dep_id
        WHERE u.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database query preparation error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        
        // Generate presigned URL for profile picture if it's an S3 key
        if (!empty($profile['profile_pic'])) {
            // Always treat the profile_pic as an S3 key and generate a presigned URL
            try {
                if ($s3Client && $s3BucketName) {
                    $cmd = $s3Client->getCommand('GetObject', [
                        'Bucket' => $s3BucketName,
                        'Key' => $profile['profile_pic']
                    ]);
                    $presignedUrl = (string) $s3Client->createPresignedRequest($cmd, '+15 minutes')->getUri();
                    $profile['profile_pic'] = $presignedUrl;
                    error_log("Generated presigned URL for profile pic: " . $profile['profile_pic']);
                } else {
                    error_log("S3 client or bucket name not available for profile pic: " . $profile['profile_pic']);
                    $profile['profile_pic'] = $s3ProfilePicDefault;
                }
            } catch (Exception $e) {
                error_log("Error generating presigned URL for profile pic: " . $e->getMessage());
                $profile['profile_pic'] = $s3ProfilePicDefault;
            }
        } else {
            $profile['profile_pic'] = $s3ProfilePicDefault;
        }
        
        $response = [
            'success' => true,
            'profile' => $profile
        ];
    } else {
        $response['message'] = 'User profile not found.';
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Error in get_user_profile.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
