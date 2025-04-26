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

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

try {
    // Check if file was uploaded
    if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $file = $_FILES['profile_pic'];
    $userId = $_SESSION['user_id'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File size exceeds the maximum limit of 2MB.');
    }
    
    // Get the old profile picture key from the database
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldProfilePic = '';
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $oldProfilePic = $row['profile_pic'];
    }
    
    // --- S3 Setup ---
    $s3Client = null; 
    $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? null; 
    $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? null; 
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
    
    // Generate a unique S3 key for the new profile picture
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $s3Key = 'profile_pictures/' . $userId . '/' . bin2hex(random_bytes(8)) . '.' . $extension;
    
    error_log("[Profile Pic] Attempting S3 upload for user {$userId} to key: {$s3Key}");
    
    // Upload the new profile picture to S3
    try {
        $result = $s3Client->putObject([
            'Bucket' => $s3BucketName,
            'Key' => $s3Key,
            'SourceFile' => $file['tmp_name'],
            'ContentType' => $fileType
        ]);
        
        error_log("[Profile Pic] S3 upload successful for user {$userId}. Key: {$s3Key}");
        
        // Update the user's profile picture in the database
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param("si", $s3Key, $userId);
        
        if (!$stmt->execute()) {
            // If database update fails, delete the uploaded file from S3
            try {
                $s3Client->deleteObject([
                    'Bucket' => $s3BucketName,
                    'Key' => $s3Key
                ]);
            } catch (AwsException $e) {
                error_log("[Profile Pic] Failed to delete new profile picture after DB update failure: " . $e->getMessage());
            }
            throw new Exception('Failed to update profile picture in the database: ' . $stmt->error);
        }
        
        error_log("[Profile Pic] Database updated successfully for user {$userId}");
        
        // Delete the old profile picture from S3 if it exists and is not the default
        if (!empty($oldProfilePic) && 
            strpos($oldProfilePic, 'profile_pictures/') === 0 && 
            $oldProfilePic !== $s3Key) {
            try {
                $s3Client->deleteObject([
                    'Bucket' => $s3BucketName,
                    'Key' => $oldProfilePic
                ]);
                error_log("[Profile Pic] Successfully deleted old profile picture: " . $oldProfilePic);
            } catch (AwsException $e) {
                // Just log the error but continue with the process
                error_log("[Profile Pic] Failed to delete old profile picture: " . $e->getMessage());
            }
        }
        
        // Generate a presigned URL for the new profile picture
        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $s3BucketName,
            'Key' => $s3Key
        ]);
        $presignedUrl = (string) $s3Client->createPresignedRequest($cmd, '+5 minutes')->getUri();
        
        error_log("[Profile Pic] Generated presigned URL for user {$userId}");
        
        // Return success response with the new profile picture URL
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'profile_pic_url' => $presignedUrl,
            's3_key' => $s3Key
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (AwsException $e) {
        error_log("[Profile Pic] S3 Upload Error for user {$userId}, key {$s3Key}: " . $e->getMessage());
        throw new Exception('Failed to upload profile picture to S3: ' . $e->getAwsErrorMessage());
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('[Profile Pic] Error in upload_profile_pic.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Close the database connection
$conn->close();
?>
