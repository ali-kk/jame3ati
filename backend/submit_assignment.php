<?php
// backend/submit_assignment.php
// Handles student assignment submissions (Corrected for separate submission_files table)

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Dependencies ---
require_once __DIR__ . '/config/db.php'; // $conn
require_once __DIR__ . '/../vendor/autoload.php'; // AWS SDK & Dotenv
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load();
use Aws\S3\S3Client; use Aws\Exception\AwsException;
// --- End Dependencies ---

header('Content-Type: application/json; charset=utf-8');

// --- Constants and Configuration ---
const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];
const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB limit
const S3_SUBMISSIONS_FOLDER = 'submissions/';
const ALLOW_RESUBMISSION = false;
// --- End Configuration ---

// --- Authentication & Authorization Check ---
$userId = $_SESSION['user_id'] ?? null;
$userRoleId = $_SESSION['role_id'] ?? null;
$userStatusSession = $_SESSION['user_status'] ?? null;
$isAuthorized = false;
if ($userId && $userRoleId === 7 && $userStatusSession === 'perm') {
    $isAuthorized = true;
}
if (!$isAuthorized) { echo json_encode(['success' => false, 'message' => 'User not authorized to submit.']); exit; }
// --- End Auth Check ---

// --- Input Validation ---
$assignmentId = filter_input(INPUT_POST, 'assignmentId', FILTER_VALIDATE_INT);
$uploadedFile = $_FILES['submissionFile'] ?? null;
if (!$assignmentId || $assignmentId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']); exit; }
if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success' => false, 'message' => 'File upload error (' . ($uploadedFile['error'] ?? 'No File') . ').']); exit; }
// --- End Input Validation ---

// --- Database Connection Check ---
// Re-establish connection if needed (assuming db.php handles singletons or is safe to include again)
if (!isset($conn) || !($conn instanceof mysqli) || (method_exists($conn, 'ping') && !$conn->ping())) { require_once __DIR__ . '/config/db.php'; }
if (!isset($conn) || !($conn instanceof mysqli)) { error_log("submit_assignment.php: DB connection failed."); echo json_encode(['success' => false, 'message' => 'Database connection error.']); exit; }
// --- End DB Check ---

// --- S3 Client Instantiation ---
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? null; $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? null; $awsRegion = $_ENV['AWS_REGION'] ?? null;
    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
        $s3Client = new S3Client(['version' => 'latest', 'region' => $awsRegion, 'credentials' => ['key' => $s3AccessKey, 'secret' => $s3SecretKey], 'signature_version' => 'v4']);
    } else { throw new Exception("S3 configuration incomplete."); }
} catch (Exception $e) { error_log("Submit Assignment: Failed to init S3 Client - " . $e->getMessage()); echo json_encode(['success' => false, 'message' => 'Server configuration error [S3].']); exit; }
// --- End S3 Client ---


// --- Main Submission Logic ---
$conn->begin_transaction(); // Start transaction
$existingSubmissionId = null;
$oldS3KeysToDelete = []; // Store old keys here

try {
    // 1. Fetch Assignment Details (Type and Deadline)
    $stmtAssign = $conn->prepare("SELECT assignment_type, deadline_at FROM assignments WHERE assignment_id = ?");
    if (!$stmtAssign) throw new Exception("DB Error (SA1): Prepare failed - " . $conn->error);
    $stmtAssign->bind_param("i", $assignmentId);
    if (!$stmtAssign->execute()) throw new Exception("DB Error (SAE1): Execute failed - " . $stmtAssign->error);
    $resultAssign = $stmtAssign->get_result();
    if ($resultAssign->num_rows === 0) { throw new Exception("Assignment not found."); }
    $assignment = $resultAssign->fetch_assoc();
    $stmtAssign->close();

    // 2. Verify Assignment Type
    if ($assignment['assignment_type'] !== 'electronic') { throw new Exception("This assignment does not accept electronic submissions."); }

    // 3. Verify Deadline (SERVER-SIDE CHECK)
    $now = new DateTime('now', new DateTimeZone('Asia/Baghdad'));
    $deadline = new DateTime($assignment['deadline_at'], new DateTimeZone('Asia/Baghdad'));
    if ($now > $deadline) { throw new Exception("The deadline for this assignment has passed."); }

     // 4. Check for Existing Submission
     $stmtCheck = $conn->prepare("SELECT submission_id FROM assignment_submissions WHERE assignment_id = ? AND student_user_id = ?");
     if (!$stmtCheck) {
         // Log detailed error if prepare fails
         error_log("DB Error (SC1) Prepare Failed: " . $conn->error . " - UserID: {$userId}, AssignID: {$assignmentId}");
         throw new Exception("Database error checking submission status."); // Generic message to user
     }
 
     $stmtCheck->bind_param("ii", $assignmentId, $userId);
 
     if (!$stmtCheck->execute()) {
         // Log detailed error if execute fails
          error_log("DB Error (SCE1) Execute Failed: " . $stmtCheck->error . " - UserID: {$userId}, AssignID: {$assignmentId}");
         throw new Exception("Database error checking submission status."); // Generic message to user
     }
 
     $resultCheck = $stmtCheck->get_result();
     $existingSubmission = $resultCheck->fetch_assoc(); // Fetches the first row or null
     $stmtCheck->close();
 
     $existingSubmissionId = null; // Initialize variable to store ID if resubmission is allowed
 
     if ($existingSubmission) { // An existing submission record was found
         // **** MODIFIED LOGIC TO PREVENT RESUBMISSION ****
         if (!ALLOW_RESUBMISSION) {
             // If resubmission is NOT allowed, throw the error immediately
             throw new Exception("You have already submitted this assignment."); // This message will be sent to the user
         } else {
             // This block only runs if ALLOW_RESUBMISSION = true (Optional Resubmission Logic)
             // If you *were* allowing resubmission, you'd get the ID and handle old file cleanup here
             $existingSubmissionId = $existingSubmission['submission_id'];
             error_log("User {$userId} is resubmitting assignment {$assignmentId} (Submission ID: {$existingSubmissionId}). Note: Resubmission is currently ALLOWED by config.");
 
             // Find and collect old S3 keys to delete later
             $stmtOldFiles = $conn->prepare("SELECT file_id, s3_key FROM submission_files WHERE submission_id = ?");
             if ($stmtOldFiles) {
                 $stmtOldFiles->bind_param("i", $existingSubmissionId);
                 if ($stmtOldFiles->execute()) {
                     $resultOldFiles = $stmtOldFiles->get_result();
                     while($oldFile = $resultOldFiles->fetch_assoc()){
                         $oldS3KeysToDelete[] = $oldFile['s3_key']; // Collect keys for S3 deletion
                     }
                 } else { error_log("DB Error (SOFE1): Failed to execute fetch for old files - Submission ID: {$existingSubmissionId}"); }
                 $stmtOldFiles->close();
             } else { error_log("DB Error (SOF1): Failed to prepare fetch for old files - Submission ID: {$existingSubmissionId}"); }
 
 
             // Delete old file records from DB before inserting new one
             if (!empty($oldS3KeysToDelete)) { // Only delete if we found old keys
                 $stmtDeleteFiles = $conn->prepare("DELETE FROM submission_files WHERE submission_id = ?");
                 if ($stmtDeleteFiles) {
                     $stmtDeleteFiles->bind_param("i", $existingSubmissionId);
                     if ($stmtDeleteFiles->execute()) {
                         error_log("Deleted old file records from DB for submission ID: {$existingSubmissionId}");
                     } else { error_log("DB Error (SDFE1): Failed to execute delete for old files - Submission ID: {$existingSubmissionId}"); }
                     $stmtDeleteFiles->close();
                 } else { error_log("DB Error (SDF1): Failed to prepare delete for old files - Submission ID: {$existingSubmissionId}"); }
             }
             // Note: The actual S3 deletion happens later after the new file is successfully uploaded & DB updated
         }
         // **** END MODIFIED LOGIC ****
     }
 
     // --- End of Step 4 ---

    // 5. Validate Uploaded File (Size, Type)
    if ($uploadedFile['size'] > MAX_FILE_SIZE_BYTES) { throw new Exception("File is too large (Max " . (MAX_FILE_SIZE_BYTES / 1024 / 1024) . " MB)."); }
    $fileMimeType = mime_content_type($uploadedFile['tmp_name']);
    if (!in_array($fileMimeType, ALLOWED_MIME_TYPES)) { throw new Exception("Invalid file type."); }

    // 6. Prepare S3 Upload Details
    $originalFilename = basename($uploadedFile['name']);
    $safeFilename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalFilename);
    $timestamp = time();
    $s3Key = S3_SUBMISSIONS_FOLDER . "user_{$userId}/assign_{$assignmentId}/{$timestamp}_{$safeFilename}";

    // 7. Upload to S3
    try {
        $resultS3 = $s3Client->putObject([
            'Bucket' => $s3BucketName,
            'Key'    => $s3Key,
            'SourceFile' => $uploadedFile['tmp_name'],
            'ContentType' => $fileMimeType // Store MIME type in S3
        ]);
    } catch (AwsException | Exception $e) { throw new Exception("Failed to upload file to storage. " . $e->getMessage()); }

    // 8. Update/Insert Database Records
    $submittedAt = date('Y-m-d H:i:s');
    $currentSubmissionId = null;

    if ($existingSubmissionId) { // Resubmission: Update timestamp
        $stmtUpdate = $conn->prepare("UPDATE assignment_submissions SET submitted_at = ? WHERE submission_id = ?");
        if (!$stmtUpdate) throw new Exception("DB Error (SU1): " . $conn->error);
        $stmtUpdate->bind_param("si", $submittedAt, $existingSubmissionId);
        if (!$stmtUpdate->execute()) throw new Exception("DB Error (SUE1): " . $stmtUpdate->error);
        $stmtUpdate->close();
        $currentSubmissionId = $existingSubmissionId; // Use existing ID
        error_log("Updated submission timestamp for submission ID: {$currentSubmissionId}");
    } else { // New Submission: Insert record
        $stmtInsertSub = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_user_id, submitted_at) VALUES (?, ?, ?)");
        if (!$stmtInsertSub) throw new Exception("DB Error (SIS1): " . $conn->error);
        $stmtInsertSub->bind_param("iis", $assignmentId, $userId, $submittedAt);
        if (!$stmtInsertSub->execute()) throw new Exception("DB Error (SISE1): " . $stmtInsertSub->error);
        $currentSubmissionId = $conn->insert_id; // Get the new submission ID
        $stmtInsertSub->close();
        if (!$currentSubmissionId) { throw new Exception("Failed to get new submission ID."); }
        error_log("Inserted new submission record with ID: {$currentSubmissionId}");
    }

    // Insert file details into submission_files table
    $stmtInsertFile = $conn->prepare("INSERT INTO submission_files (submission_id, s3_key, original_filename, file_type, file_size_bytes) VALUES (?, ?, ?, ?, ?)");
    if (!$stmtInsertFile) throw new Exception("DB Error (SIF1): " . $conn->error);
    $fileSizeBytes = $uploadedFile['size']; // Get file size
    $stmtInsertFile->bind_param("isssi", $currentSubmissionId, $s3Key, $originalFilename, $fileMimeType, $fileSizeBytes);
    if (!$stmtInsertFile->execute()) throw new Exception("DB Error (SIFE1): " . $stmtInsertFile->error);
    $stmtInsertFile->close();
    error_log("Inserted file record for submission ID: {$currentSubmissionId}, S3 Key: {$s3Key}");


     // 9. Delete Old S3 File(s) (if resubmitting and DB/S3 upload successful)
     if (!empty($oldS3KeysToDelete) && $s3Client) {
         foreach ($oldS3KeysToDelete as $oldKey) {
             try {
                 error_log("Attempting to delete old S3 object: {$oldKey}");
                 $s3Client->deleteObject([ 'Bucket' => $s3BucketName, 'Key' => $oldKey ]);
                 error_log("Successfully deleted old S3 object: {$oldKey}");
             } catch (AwsException | Exception $e) {
                 error_log("Could not delete old S3 object '{$oldKey}': " . $e->getMessage()); // Log but continue
             }
         }
     }

    // 10. Commit Transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Assignment submitted successfully!', 'filename' => $originalFilename]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Submission Error for UserID {$userId}, AssignmentID {$assignmentId}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); // Send specific error
} finally {
    if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) { $conn->close(); }
}
exit;
?>