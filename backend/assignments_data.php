<?php
// backend/assignments_data.php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- Dependencies ---
require_once __DIR__ . '/config/db.php'; // $conn
require_once __DIR__ . '/../vendor/autoload.php'; // <--- Make sure these are uncommented if using S3
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load(); // <---
use Aws\S3\S3Client; use Aws\Exception\AwsException; // <---
// --- End Dependencies ---

header('Content-Type: application/json; charset=utf-8');

// --- Authentication & Authorization Check ---
$userId = $_SESSION['user_id'] ?? null;
$userRoleId = $_SESSION['role_id'] ?? null;
$userStatusSession = $_SESSION['user_status'] ?? null;

// **** INITIALIZE $isAuthorized HERE ****
$isAuthorized = false;

// Check session first, then verify status against DB
if ($userId && $userRoleId === 7 && $userStatusSession === 'perm') {
    // Re-establish DB connection if needed
    if (!isset($conn) || !($conn instanceof mysqli) || (method_exists($conn, 'ping') && !$conn->ping())) {
        error_log("Re-establishing DB connection for status check in assignments_data.php");
        // Ensure db.php doesn't throw errors if included multiple times or handles it internally
        require_once __DIR__ . '/config/db.php';
    }

    // Check connection validity again
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $stmtStatus = $conn->prepare("SELECT user_status FROM users WHERE id = ?");
            if($stmtStatus instanceof mysqli_stmt) {
                $stmtStatus->bind_param("i", $userId);
                if($stmtStatus->execute()) {
                    $resultStatus = $stmtStatus->get_result();
                    if ($resultStatus->num_rows > 0) {
                        $userDb = $resultStatus->fetch_assoc();
                        if ($userDb['user_status'] === 'perm') {
                            // Set to true ONLY if ALL checks pass
                            $isAuthorized = true;
                        } else {
                            error_log("assignments_data.php: Auth failed (DB status: {$userDb['user_status']}) for user {$userId}");
                            // $isAuthorized remains false
                        }
                    } else {
                        error_log("assignments_data.php: User ID {$userId} not found in DB.");
                         // $isAuthorized remains false
                    }
                } else {
                    error_log("assignments_data.php: Status check execute failed: ".$stmtStatus->error);
                     // $isAuthorized remains false
                }
                $stmtStatus->close();
            } else {
                 error_log("assignments_data.php: Status check prepare failed: ".$conn->error);
                  // $isAuthorized remains false
            }
        } catch (Exception $e) {
            error_log("assignments_data.php: Exception during status check: ".$e->getMessage());
             // $isAuthorized remains false
        }
        // Optional: close connection here if ONLY used for auth check
        // if (method_exists($conn, 'close')) { $conn->close(); }
    } else {
        error_log("assignments_data.php: DB connection not available for status check.");
         // $isAuthorized remains false
    }
} else {
     // Log why initial check failed
     error_log("assignments_data.php: Initial session check failed. UserID: " . ($userId ?? 'null') . ", RoleID: " . ($userRoleId ?? 'null') . ", SessionStatus: " . ($userStatusSession ?? 'null'));
     // $isAuthorized remains false
}

// --- Now perform the check ---
// This should now correctly evaluate $isAuthorized which is guaranteed to be true or false
if (!$isAuthorized) {
    error_log("assignments_data.php: Authorization failed check triggered for user {$userId}.");
    // Send the specific auth failure message
    echo json_encode(['success' => false, 'message' => 'User not authorized.', 'logout' => true]);
    exit; // IMPORTANT: Stop script execution
}

// --- If authorized, proceed ---
$userDepId = $_SESSION['dep_id'] ?? null;
$userStage = $_SESSION['stage'] ?? null;
// --- End Auth Check ---


// --- S3 Client Instantiation (Make sure this section is present and correct) ---
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
$s3CourseImageDefault = 'assets/images/default-course-image.png'; // Use relative path for JS fallback
try {
     $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
     $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
     $awsRegion = $_ENV['AWS_REGION'] ?? null;
     if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
         $s3Client = new S3Client(['version' => 'latest', 'region' => $awsRegion, 'credentials' => ['key' => $s3AccessKey, 'secret' => $s3SecretKey], 'signature_version' => 'v4']);
     } else { error_log("Assignment Data: S3 config incomplete."); }
} catch (Exception $e) { error_log("Assignment Data: Failed to init S3 Client: " . $e->getMessage()); }
// --- End S3 Client ---


// --- S3 Helper Function (Make sure this is present) ---
if (!function_exists('get_presigned_s3_url')) {
    function get_presigned_s3_url($s3Client, $bucket, $key, $defaultPath, $downloadFilename = null, $expiryString = '+15 minutes') {
        if (!$s3Client || !$key || !$bucket) return $defaultPath;
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


// --- Get Optional Course ID ---
$courseId = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
// --- End Get Course ID ---

// --- Data Fetching ---
// Default response (will be overwritten on success)
$response = ['success' => false, 'message' => 'Failed to fetch assignments.'];
$assignments = [];

try {
    $sql = ""; $params = []; $types = "";

    // Base query includes course_image_s3_key
    $baseSql = "SELECT a.assignment_id, a.course_id, a.title, a.description,
                       a.assignment_type, a.deadline_at, c.course_name,
                       c.course_image_s3_key, -- Needed for image URL
                       sub.submission_id, sub.submitted_at
                FROM assignments a
                JOIN courses c ON a.course_id = c.course_id
                LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id AND sub.student_user_id = ?";
    $params[] = $userId; $types .= "i";

    // Filter by course or department/stage
    if ($courseId && $courseId > 0) {
        // Fetch assignments for a specific course
        $sql = $baseSql . " WHERE a.course_id = ? ORDER BY a.deadline_at ASC";
        $params[] = $courseId;
        $types .= "i";
    } else {
        // Fetch assignments for all courses relevant to the student's dep and stage
        if (!$userDepId || !$userStage) {
            // This should ideally not happen if the initial auth check passed and requires these
            throw new Exception("User department or stage not found in session after authorization.");
        }
        $sql = $baseSql . " WHERE c.dep_id = ? AND c.stage = ? ORDER BY a.deadline_at ASC";
        $params[] = $userDepId;
        $params[] = $userStage;
        $types .= "ii";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("DB Prepare Error (A1): " . $conn->error);

    $stmt->bind_param($types, ...$params); // Use argument unpacking

    if (!$stmt->execute()) throw new Exception("DB Execute Error (AE1): " . $stmt->error);
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Generate Course Image URL
        $row['courseImageUrl'] = get_presigned_s3_url(
            $s3Client, $s3BucketName, $row['course_image_s3_key'], $s3CourseImageDefault, null, '+60 minutes'
        );
        unset($row['course_image_s3_key']); // Don't send raw key

        // Submission status
        $row['has_submitted'] = ($row['submission_id'] !== null);

        // Format deadline (using PHP IntlDateFormatter recommended if available)
        $deadlineTimestamp = strtotime($row['deadline_at']);
        if ($deadlineTimestamp && extension_loaded('intl')) {
             $fmt = new IntlDateFormatter('ar_SA', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, 'Asia/Baghdad', IntlDateFormatter::GREGORIAN);
             $row['deadline_formatted'] = $fmt->format($deadlineTimestamp);
        } elseif($deadlineTimestamp) {
            // Fallback basic format if intl not loaded
             $row['deadline_formatted'] = date('Y/m/d g:i A', $deadlineTimestamp);
        }
        else {
            $row['deadline_formatted'] = 'غير محدد';
        }

        $assignments[] = $row;
    }
    $stmt->close();

    // If successful, overwrite the default response
    $response = [
        'success' => true,
        'assignments' => $assignments
    ];

} catch (Exception $e) {
    error_log("Error fetching assignments for UserID {$userId}, CourseID " . ($courseId ?? 'All') . ": " . $e->getMessage());
    // Keep the default $response which has success=false
    $response['message'] = 'Error processing assignment data: ' . $e->getMessage(); // More specific error for logging/debug
}

// Close DB connection (ensure it wasn't closed earlier if only used for auth)
if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) {
    $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>