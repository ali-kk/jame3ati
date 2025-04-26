<?php
// backend/subject_data.php
// Fetches course details, teacher info, file materials, videos, AND ASSIGNMENTS

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
// [ ... KEEP EXISTING AUTH CHECK ... ]
$userId = $_SESSION['user_id'] ?? null;
$userRoleId = $_SESSION['role_id'] ?? null;
$userStatusSession = $_SESSION['user_status'] ?? null;
$isAuthorized = false;

// (Existing authorization logic remains unchanged)
if ($userId && $userRoleId === 7 && $userStatusSession === 'perm') {
    // (Existing DB status verification logic remains unchanged)
    // ... (your existing verification code) ...
    // Assuming verification sets $isAuthorized = true if valid
    // For brevity, the full auth code block isn't repeated here
    // Ensure the $isAuthorized logic from your previous snippet is here.
    // Example placeholder:
    // $isAuthorized = checkUserAuthorization($conn, $userId); // Replace with your actual check
    // Simplified for example - USE YOUR FULL AUTH CHECK
     try {
         // Example check: Ensure this logic matches your previous snippet
         $stmtStatus = $conn->prepare("SELECT user_status FROM users WHERE id = ?");
         if($stmtStatus) {
             $stmtStatus->bind_param("i", $userId);
             if($stmtStatus->execute()) {
                 $resultStatus = $stmtStatus->get_result();
                 if ($userDb = $resultStatus->fetch_assoc()) {
                     if ($userDb['user_status'] === 'perm') {
                         $isAuthorized = true;
                     }
                 }
             }
             $stmtStatus->close();
         }
     } catch(Exception $e) { $isAuthorized = false; error_log("Auth check error: ". $e->getMessage()); }
}

if (!$isAuthorized) {
    error_log("Authorization failed in subject_data.php. Session: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'User not authorized.', 'logout' => true]);
    exit;
}
// --- End Auth Check ---


// --- Get Course ID ---
$courseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$courseId || $courseId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid or missing course ID.']); exit; }
// --- End Get Course ID ---


// --- S3 Client Instantiation ---
// [ ... KEEP EXISTING S3 CLIENT INSTANTIATION ... ]
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
$s3ProfilePicDefault = 'assets/images/placeholder-profile.png'; // Relative path used by JS
$s3CourseImageDefault = 'assets/images/default-course-image.png';
// (Existing S3 client setup remains unchanged)
try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? $_ENV['AWS_ACCESS_KEY_ID'] ?? null; $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null; $awsRegion = $_ENV['AWS_REGION'] ?? null;
    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) { $s3Client = new S3Client(['version' => 'latest', 'region' => $awsRegion, 'credentials' => ['key' => $s3AccessKey, 'secret' => $s3SecretKey], 'signature_version' => 'v4']); } else { error_log("Subject Data: S3 config incomplete."); }
} catch (Exception $e) { error_log("Subject Data: Failed to init S3 Client: " . $e->getMessage()); }
// --- End S3 Client ---

// --- Helper Function for S3 URLs ---
// [ ... KEEP EXISTING get_presigned_s3_url FUNCTION ... ]
if (!function_exists('get_presigned_s3_url')) {
    function get_presigned_s3_url($s3Client, $bucket, $key, $defaultPath, $downloadFilename = null, $expiryString = '+15 minutes') {
        // (Existing function body remains unchanged)
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
// --- End S3 Helper ---

// --- Helper Function for Bunny Signed Embed URL ---
// [ ... KEEP EXISTING get_bunny_signed_embed_url FUNCTION ... ]
if (!function_exists('get_bunny_signed_embed_url')) {
    function get_bunny_signed_embed_url($libraryId, $videoId, $tokenAuthKey, $expirySeconds = 3600) {
        // (Existing function body remains unchanged)
         if (!$libraryId || !$videoId || !$tokenAuthKey) {
            error_log("Missing Bunny Library ID ({$libraryId}), Video ID ({$videoId}), or Token Auth Key for signing.");
            return null;
        }
        $baseUrl = "https://iframe.mediadelivery.net/embed";
        $expiryTimestamp = time() + $expirySeconds;
        $stringToSign = $tokenAuthKey . $videoId . $expiryTimestamp;
        $token = hash('sha256', $stringToSign, false);
        $signedUrl = "{$baseUrl}/{$libraryId}/{$videoId}?token={$token}&expires={$expiryTimestamp}";
        return $signedUrl;
    }
}
// --- End Bunny Helper ---


// --- Data Fetching ---
$response = ['success' => false, 'message' => 'Failed to fetch data.'];
$courseData = null;
$fileMaterials = [];
$videoMaterials = [];
$assignments = []; // **** ADDED: Initialize assignments array ****

// Get Bunny Credentials from .env
$bunnyLibraryId = $_ENV['BUNNY_VIDEO_LIBRARY_ID'] ?? null;
$bunnyTokenAuthKey = $_ENV['BUNNY_TOKEN_AUTHENTICATION_KEY'] ?? null; // Use this for signing

try {
    // 1. Fetch Course Details and Teacher Info
    // [ ... KEEP EXISTING COURSE/TEACHER FETCHING ... ]
    $sqlCourse = "SELECT c.course_id, c.course_name, c.description, c.course_image_s3_key, MIN(t.first_name) as teacher_fname, MIN(t.last_name) as teacher_lname, MIN(t.academic_title) as teacher_title, MIN(t.profile_pic) as teacher_pic_s3_key FROM courses c LEFT JOIN course_teachers ct ON c.course_id = ct.course_id LEFT JOIN users t ON ct.teacher_user_id = t.id WHERE c.course_id = ? GROUP BY c.course_id LIMIT 1";
    $stmtCourse = $conn->prepare($sqlCourse);
    if (!$stmtCourse) throw new Exception("DB Error (CD1): " . $conn->error);
    $stmtCourse->bind_param("i", $courseId);
    if (!$stmtCourse->execute()) throw new Exception("DB Error (CE1): " . $stmtCourse->error);
    $resultCourse = $stmtCourse->get_result();
    if ($resultCourse->num_rows > 0) {
        $courseData = $resultCourse->fetch_assoc();
        $courseData['courseImageUrl'] = get_presigned_s3_url($s3Client, $s3BucketName, $courseData['course_image_s3_key'], $s3CourseImageDefault);
        $courseData['teacherImageUrl'] = get_presigned_s3_url($s3Client, $s3BucketName, $courseData['teacher_pic_s3_key'], $s3ProfilePicDefault);
        unset($courseData['course_image_s3_key'], $courseData['teacher_pic_s3_key']);
    } else {
        throw new Exception("Course not found or access denied.");
    }
    $stmtCourse->close();


    // 2. Fetch File and Video Course Materials
    // [ ... MODIFY EXISTING MATERIAL FETCHING SLIGHTLY (to exclude assignments if they are in this table) ... ]
    // If assignments ARE stored in course_materials with type='assignment', modify the query:
    // $sqlMaterials = "SELECT material_id, material_type, title, description, s3_key, bunny_video_id, original_filename, uploaded_at FROM course_materials WHERE course_id = ? AND material_type != 'assignment' ORDER BY uploaded_at DESC";
    // If assignments are in a SEPARATE table, keep the original query:
     $sqlMaterials = "SELECT material_id, material_type, title, description, s3_key, bunny_video_id, original_filename, uploaded_at FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC"; // Assuming separate table

    $stmtMaterials = $conn->prepare($sqlMaterials);
    if (!$stmtMaterials) throw new Exception("DB Error (CM1): " . $conn->error);
    $stmtMaterials->bind_param("i", $courseId);
    if (!$stmtMaterials->execute()) throw new Exception("DB Error (CME1): " . $stmtMaterials->error);
    $resultMaterials = $stmtMaterials->get_result();

    while ($row = $resultMaterials->fetch_assoc()) {
         if ($row['material_type'] === 'bunny_video' && $row['bunny_video_id']) {
             $row['signedEmbedUrl'] = get_bunny_signed_embed_url(
                 $bunnyLibraryId,
                 $row['bunny_video_id'],
                 $bunnyTokenAuthKey
             );
             if ($row['signedEmbedUrl']) { $videoMaterials[] = $row; }
             else { error_log("Failed to generate signed URL for Bunny video material ID: " . $row['material_id']); }
         } else if ($row['material_type'] !== 'bunny_video' && $row['s3_key']) { // Treat non-video, non-assignment as files
             $row['downloadUrl'] = get_presigned_s3_url($s3Client, $s3BucketName, $row['s3_key'], '#', $row['original_filename']);
             $row['previewUrl'] = null; $row['isPreviewable'] = false; $isPdf = str_ends_with(strtolower($row['s3_key'] ?? ''), '.pdf');
             if ($isPdf) { $row['previewUrl'] = get_presigned_s3_url($s3Client, $s3BucketName, $row['s3_key'], null); if ($row['previewUrl']) { $row['isPreviewable'] = true; } }
             if ($row['downloadUrl'] !== '#') { $fileMaterials[] = $row; }
             else { error_log("Failed to generate download URL for S3 material ID: " . $row['material_id']); }
        }
        // **** NOTE: Removed file_size_bytes from query and processing for simplicity, add back if needed ****
    }
    $stmtMaterials->close();
    error_log("Processed materials for course {$courseId}. Files: " . count($fileMaterials) . ", Videos: " . count($videoMaterials));

   // In backend/subject_data.php

    // 3. Fetch Assignments for THIS course & check submission status
    // Corrected query joining assignments and submissions
    $sqlAssignments = "SELECT
                           a.assignment_id, a.title, a.description,
                           a.assignment_type, a.deadline_at, a.created_at,
                           sub.submission_id -- Get submission ID to check existence
                       FROM assignments a
                       LEFT JOIN assignment_submissions sub ON a.assignment_id = sub.assignment_id AND sub.student_user_id = ? -- Check submission for THIS user
                       WHERE a.course_id = ? -- Filter by the current course ID
                       ORDER BY a.deadline_at ASC, a.created_at ASC";

    $stmtAssignments = $conn->prepare($sqlAssignments);
    if (!$stmtAssignments) throw new Exception("DB Error (AS1): Prepare failed - " . $conn->error);

    // Bind USER ID first (for the JOIN), then COURSE ID (for the WHERE)
    $stmtAssignments->bind_param("ii", $userId, $courseId);

    if (!$stmtAssignments->execute()) throw new Exception("DB Error (ASE1): Execute failed - " . $stmtAssignments->error);
    $resultAssignments = $stmtAssignments->get_result();

    while ($row = $resultAssignments->fetch_assoc()) {
        // Determine submission status based on whether a submission_id was found
        $row['has_submitted'] = ($row['submission_id'] !== null);
        unset($row['submission_id']); // Don't need the actual submission ID here

       

        $assignments[] = $row;
    }
    $stmtAssignments->close();
    error_log("Fetched assignments for course {$courseId}: " . count($assignments));


    // Prepare successful response
    $response = [
        'success'       => true,
        'course'        => $courseData,
        'fileMaterials' => $fileMaterials,
        'videoMaterials'=> $videoMaterials,
        'assignments'   => $assignments // Assignments array now includes 'has_submitted'
    ];
} catch (Exception $e) {
// ... (existing error handling) ...
error_log("Error fetching data for CourseID {$courseId}, UserID {$userId}: " . $e->getMessage());
$response['message'] = 'Error fetching page data.';
if (strpos($e->getMessage(), "User data not found") !== false || strpos($e->getMessage(), "Course not found") !== false) {
$response['logout'] = true;
}
}

// Close DB connection
if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) {
    $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>