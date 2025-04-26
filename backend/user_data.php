<?php
// backend/user_data.php
// Fetches user profile AND courses filtered by user's department and stage

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Dependencies ---
require_once __DIR__ . '/config/db.php'; // $conn
require_once __DIR__ . '/../vendor/autoload.php'; // AWS SDK
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load();
use Aws\S3\S3Client; use Aws\Exception\AwsException;
// --- End Dependencies ---

header('Content-Type: application/json; charset=utf-8');

// --- Authentication & Authorization Check ---
// Check if user is logged in, has 'perm' status, and has department/stage info

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_status']) || $_SESSION['user_status'] !== 'perm' || !isset($_SESSION['dep_id'])) {
    error_log("Unauthorized access or missing session data for user_data.php. Session dump: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'User not authorized or session incomplete.', 'logout' => true]); exit;
}
$userId = $_SESSION['user_id'];
$userDepId = $_SESSION['dep_id'];
// if($_SESSION['role_id']==7)
// {
$userStage = $_SESSION['stage'];
// }

// --- End Auth Check ---

// --- S3 Client Instantiation ---
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
$s3ProfilePicDefault = 'assets/images/placeholder-profile.png'; // Relative path for JS is fine here
$s3CourseImageDefault = 'assets/images/default-course-image.png';
try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? $_ENV['AWS_ACCESS_KEY_ID'] ?? null; $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null; $awsRegion = $_ENV['AWS_REGION'] ?? null;
    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
        $s3Client = new S3Client(['version' => 'latest', 'region' => $awsRegion, 'credentials' => ['key' => $s3AccessKey, 'secret' => $s3SecretKey], 'signature_version' => 'v4']);
    } else { error_log("User Data: S3 config incomplete."); }
} catch (Exception $e) { error_log("User Data: Failed to init S3 Client: " . $e->getMessage()); }
// --- End S3 Client ---

// --- Helper Function for S3 URLs (Using Relative Expiry String) ---
// Define only if not already defined (e.g., in a shared include file)
if (!function_exists('get_presigned_s3_url')) {
    function get_presigned_s3_url($s3Client, $bucket, $key, $defaultPath, $downloadFilename = null, $expiryString = '+15 minutes') {
        if (!$s3Client || !$key || !$bucket) return $defaultPath; // Return default path if cannot generate
        try {
            $commandParams = ['Bucket' => $bucket, 'Key' => $key];
            if ($downloadFilename) { $asciiFilename = preg_replace('/[^\x20-\x7E]/', '_', $downloadFilename); $commandParams['ResponseContentDisposition'] = 'attachment; filename="' . $asciiFilename . '"'; }
            $cmd = $s3Client->getCommand('GetObject', $commandParams);
            $request = $s3Client->createPresignedRequest($cmd, $expiryString);
            return (string) $request->getUri();
        } catch (AwsException $e) { error_log("S3 Presign URL Error for key {$key}: " . $e->getMessage()); return $defaultPath; }
    }
}
// --- End Helper ---

// --- Data Fetching ---
$response = ['success' => false, 'message' => 'Failed to fetch initial data.'];
$profilePicUrl = $s3ProfilePicDefault;
$userData = null;
$courses = [];

try {
    // 1. Fetch User Profile Data
    $stmtUser = $conn->prepare("SELECT first_name, last_name, profile_pic FROM users WHERE id = ? LIMIT 1");
    if (!$stmtUser) throw new Exception("DB Error (UP1): " . $conn->error);
    $stmtUser->bind_param("i", $userId);
    if (!$stmtUser->execute()) throw new Exception("DB Error (UE1): " . $stmtUser->error);
    $resultUser = $stmtUser->get_result();
    if ($resultUser->num_rows > 0) {
        $userData = $resultUser->fetch_assoc();
        $profilePicUrl = get_presigned_s3_url($s3Client, $s3BucketName, $userData['profile_pic'], $s3ProfilePicDefault);
    } else {
        // If user data not found despite session, force logout
        throw new Exception("User data not found in DB.");
    }
    $stmtUser->close();

    // 2. Fetch Courses for the user's department AND stage with ONE teacher's info
    $sqlCourses = "
        SELECT
            c.course_id, c.course_name, c.course_image_s3_key,
            MIN(t.first_name) as teacher_fname,
            MIN(t.last_name) as teacher_lname,
            MIN(t.academic_title) as teacher_title
        FROM courses c
        LEFT JOIN course_teachers ct ON c.course_id = ct.course_id
        LEFT JOIN users t ON ct.teacher_user_id = t.id
        WHERE c.dep_id = ? AND c.stage = ?
        GROUP BY c.course_id, c.course_name, c.course_image_s3_key
        ORDER BY c.course_name
    ";
    $stmtCourses = $conn->prepare($sqlCourses);
    if (!$stmtCourses) throw new Exception("DB Error (C1): " . $conn->error);
    $stmtCourses->bind_param("ii", $userDepId, $userStage); // Bind dep and stage
    if (!$stmtCourses->execute()) throw new Exception("DB Error (CE1): " . $stmtCourses->error);
    $resultCourses = $stmtCourses->get_result();

    while ($row = $resultCourses->fetch_assoc()) {
        // Generate pre-signed URL for the course image
        $row['courseImageUrl'] = get_presigned_s3_url($s3Client, $s3BucketName, $row['course_image_s3_key'], $s3CourseImageDefault);
        unset($row['course_image_s3_key']); // Don't send raw key to frontend
        $courses[] = $row;
    }
    $stmtCourses->close();
    error_log("Fetched " . count($courses) . " courses for user {$userId}, dep {$userDepId}, stage {$userStage}");

    // Prepare successful response
    $response = [
        'success'       => true,
        'firstName'     => $userData['first_name'] ?? '',
        'lastName'      => $userData['last_name'] ?? '',
        'profilePicUrl' => $profilePicUrl,
        'courses'       => $courses // Include courses array
    ];

} catch (Exception $e) {
    error_log("Error fetching user/course data for UserID {$userId}: " . $e->getMessage());
    $response['message'] = 'Error fetching page data.';
    if (strpos($e->getMessage(), "User data not found") !== false) {
        $response['logout'] = true; // Force logout if user DB record is missing
    }
}

// Close DB connection
if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) {
    $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>
