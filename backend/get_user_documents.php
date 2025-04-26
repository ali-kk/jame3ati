<?php
// backend/get_user_documents.php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load();
use Aws\S3\S3Client; use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

// --- Auth Check (HoD only) ---
$hodUserId = $_SESSION['user_id'] ?? null; $hodRoleId = $_SESSION['role_id'] ?? null; $hodDepId = $_SESSION['dep_id'] ?? null;
if (!$hodUserId || $hodRoleId !== 5 || !$hodDepId || ($_SESSION['user_status']??'') !== 'perm') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Access denied.']); exit; }

// --- Input Validation ---
$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$targetUserId) {
    // If no user_id is provided, use the current user's ID (for personal profile)
    $targetUserId = $hodUserId;
}
// doc_type=all is assumed by default now

// --- S3 Setup ---
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
try {
    $s3AccessKey= $_ENV['AWS_S3_ACCESS_KEY_ID']??null; $s3SecretKey= $_ENV['AWS_S3_SECRET_ACCESS_KEY']??null; $awsRegion= $_ENV['AWS_REGION']??null;
    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
        $s3Client = new S3Client(['version'=>'latest', 'region'=>$awsRegion, 'credentials'=>['key'=>$s3AccessKey, 'secret'=>$s3SecretKey], 'signature_version'=>'v4']);
    } else { throw new Exception("S3 config incomplete."); }
} catch (Exception $e) { error_log("get_user_documents: S3 init error: ".$e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'message' => 'S3 Service unavailable.']); exit; }

// --- S3 Helper Function ---
if (!function_exists('get_presigned_s3_url')) { function get_presigned_s3_url($s3Client, $bucket, $key, $defaultPath, $downloadFilename = null, $expiryString = '+5 minutes') { if (!$s3Client || !$key || !$bucket) return $defaultPath; try { $cmd = $s3Client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]); return (string) $s3Client->createPresignedRequest($cmd, $expiryString)->getUri(); } catch (AwsException $e) { error_log("S3 Presign URL Error {$key}: " . $e->getMessage()); return null; } } } // Return null on error

$response = ['success' => false, 'message' => 'Failed to retrieve documents.'];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) { require_once __DIR__ . '/config/db.php'; }
    if (!isset($conn) || !($conn instanceof mysqli)) { throw new Exception("DB connection unavailable."); }

    // Verify target user is in HoD's department (only if HOD is accessing another user's documents)
    if ($targetUserId != $hodUserId) {
        $stmtCheckDep = $conn->prepare("SELECT dep_id FROM users WHERE id = ?");
        if (!$stmtCheckDep) throw new Exception("DB Check Prepare Error: ".$conn->error);
        $stmtCheckDep->bind_param("i", $targetUserId); $stmtCheckDep->execute(); $resultCheckDep = $stmtCheckDep->get_result(); $targetUser = $resultCheckDep->fetch_assoc(); $stmtCheckDep->close();
        if (!$targetUser || $targetUser['dep_id'] != $hodDepId) { http_response_code(403); throw new Exception("Cannot access documents for users outside your department."); }
    }

    // Fetch document keys
    $stmtDocs = $conn->prepare("SELECT uni_id_front, uni_id_back, iraqi_id_front, iraqi_id_back FROM user_documents WHERE user_id = ? ORDER BY doc_id DESC LIMIT 1");
    if (!$stmtDocs) throw new Exception("DB Docs Prepare Error: ".$conn->error);
    $stmtDocs->bind_param("i", $targetUserId); $stmtDocs->execute(); $resultDocs = $stmtDocs->get_result(); $docKeys = $resultDocs->fetch_assoc(); $stmtDocs->close();

    if (!$docKeys) { throw new Exception("No documents found for this user."); }

    $documentsUrls = [];
    $docTypesToFetch = ['uni_id_front', 'uni_id_back', 'iraqi_id_front', 'iraqi_id_back'];
    foreach($docTypesToFetch as $type) {
        if (!empty($docKeys[$type])) {
            // Generate URL with short expiry as they are viewed immediately
            $documentsUrls[$type] = get_presigned_s3_url($s3Client, $s3BucketName, $docKeys[$type], null, null, '+5 minutes');
        } else { $documentsUrls[$type] = null; }
    }

    $response = ['success' => true, 'documents' => $documentsUrls];

} catch (Exception $e) { $response['message'] = $e->getMessage(); if(!headers_sent()){http_response_code(500);} error_log("Error get_user_documents: ".$e->getMessage()); }
finally { if (isset($conn) && $conn instanceof mysqli) { $conn->close(); } }

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>