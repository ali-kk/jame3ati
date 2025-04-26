<?php
// backend/hod_manage_subject.php
// Handles CREATE, UPDATE, DELETE for subjects with image upload

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For S3 SDK
use Dotenv\Dotenv; 
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json; charset=utf-8');

// CSRF Protection
$requestHeaders = getallheaders();
$csrfToken = $requestHeaders['X-CSRF-Token'] ?? '';
if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
    exit;
}

// Auth Check (HoD)
$hodUserId = $_SESSION['user_id'] ?? null; 
$hodRoleId = $_SESSION['role_id'] ?? null; 
$hodDepId = $_SESSION['dep_id'] ?? null;
if (!$hodUserId || $hodRoleId !== 5 || !$hodDepId || ($_SESSION['user_status']??'') !== 'perm') { 
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Access denied.']); 
    exit; 
}

// Initialize S3 client
$s3Client = null;
$s3BucketName = null;
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    
    $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
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
            ],
            'signature_version' => 'v4'
        ]);
    } else { 
        error_log("S3 configuration incomplete for subject image upload.");
    }
} catch (Exception $e) {
    error_log("S3 Client Error: " . $e->getMessage());
}

// Get Input & Action
$response = ['success' => false, 'message' => 'Invalid request.'];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) { throw new Exception("DB connection error."); }
    
    // Check if it's a form submission with file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = filter_var($_POST['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if ($action === 'create_subject' || $action === 'update_subject') {
            // Validate input
            $courseName = trim($_POST['course_name'] ?? '');
            $stage = filter_var($_POST['stage'] ?? null, FILTER_VALIDATE_INT);
            $semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);
            $description = trim($_POST['description'] ?? '');
            $teacherUserId = filter_var($_POST['teacher_user_id'] ?? null, FILTER_VALIDATE_INT);
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT); // For update
            
            // Prepare nextCourseId for creating S3 folder
            $nextCourseIdForCreate = null;
            if ($action === 'create_subject') {
                $statusResult = $conn->query("SHOW TABLE STATUS LIKE 'courses'");
                if ($statusResult) {
                    $statusRow = $statusResult->fetch_assoc();
                    $nextCourseIdForCreate = $statusRow['Auto_increment'];
                }
            }

            if (empty($courseName) || !$stage || !$semester || !in_array($semester, [1, 2])) {
                throw new Exception("Missing or invalid subject details (Name, Stage, Semester).");
            }
            
            // Handle image upload
            $s3Key = null;
            if (isset($_FILES['subject_image']) && $_FILES['subject_image']['error'] === UPLOAD_ERR_OK) {
                if (!$s3Client || !$s3BucketName) {
                    throw new Exception("S3 configuration is missing. Cannot upload image.");
                }
                
                $file = $_FILES['subject_image'];
                $fileType = mime_content_type($file['tmp_name']);
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
                }
                
                $fileSize = $file['size'];
                if ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                    throw new Exception("File size exceeds the 5MB limit.");
                }
                
                // Generate a unique S3 key with proper structure
                $courseIdForPath = ($action === 'create_subject' && $nextCourseIdForCreate) ? $nextCourseIdForCreate : ($courseId ?? 'new');
                $originalFilename = basename($file['name']);
                $safeFilename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalFilename);
                $timestamp = time();
                $s3Key = "subject_images/{$courseIdForPath}/{$safeFilename}";
                
                try {
                    // Upload to S3 with proper configuration
                    $result = $s3Client->putObject([
                        'Bucket' => $s3BucketName,
                        'Key' => $s3Key,
                        'SourceFile' => $file['tmp_name'],
                        'ContentType' => $fileType
                    ]);
                } catch (AwsException $e) {
                    throw new Exception("Failed to upload image: " . $e->getMessage());
                }
            }
            
            $conn->begin_transaction();
            
            if ($action === 'create_subject') {
                // Insert into courses table
                $stmtCourse = $conn->prepare("INSERT INTO courses (course_name, dep_id, stage, semester, description, course_image_s3_key) VALUES (?, ?, ?, ?, ?, ?)");
                if(!$stmtCourse) throw new Exception("DB Course Prepare Error: ".$conn->error);
                $stmtCourse->bind_param("siisss", $courseName, $hodDepId, $stage, $semester, $description, $s3Key);
                if (!$stmtCourse->execute()) throw new Exception("DB Course Execute Error: ".$stmtCourse->error);
                $newCourseId = $conn->insert_id;
                $stmtCourse->close();
                
                if (!$newCourseId) { throw new Exception("Failed to create course."); }
                
                // If the image was uploaded with a temporary path, update it with the actual course ID
                if ($s3Key && strpos($s3Key, 'new') !== false) {
                    $updatedS3Key = str_replace('new', $newCourseId, $s3Key);
                    try {
                        // Copy the object to the new key
                        $s3Client->copyObject([
                            'Bucket' => $s3BucketName,
                            'CopySource' => $s3BucketName . '/' . $s3Key,
                            'Key' => $updatedS3Key,
                            'ACL' => 'public-read'
                        ]);
                        
                        // Delete the old object
                        $s3Client->deleteObject([
                            'Bucket' => $s3BucketName,
                            'Key' => $s3Key
                        ]);
                        
                        // Update the S3 key in the database
                        $stmtUpdateKey = $conn->prepare("UPDATE courses SET course_image_s3_key = ? WHERE course_id = ?");
                        $stmtUpdateKey->bind_param("si", $updatedS3Key, $newCourseId);
                        $stmtUpdateKey->execute();
                        $stmtUpdateKey->close();
                        
                        $s3Key = $updatedS3Key;
                    } catch (AwsException $e) {
                        error_log("Failed to update S3 key: " . $e->getMessage());
                        // Don't fail the transaction, just log the error
                    }
                }
                
                // Assign teacher if provided
                if ($teacherUserId && $teacherUserId > 0) {
                    $stmtAssign = $conn->prepare("INSERT INTO course_teachers (course_id, teacher_user_id) VALUES (?, ?)");
                    if(!$stmtAssign) throw new Exception("DB Assign Teacher Prepare Error: ".$conn->error);
                    $stmtAssign->bind_param("ii", $newCourseId, $teacherUserId);
                    if (!$stmtAssign->execute()) { error_log("Failed to assign teacher {$teacherUserId} to course {$newCourseId}: ".$stmtAssign->error); }
                    $stmtAssign->close();
                }
                
                $conn->commit();
                $response = [
                    'success' => true, 
                    'message' => 'Subject created successfully.',
                    'course_id' => $newCourseId,
                    'image_url' => $s3Key ? "https://{$s3BucketName}.s3.amazonaws.com/{$s3Key}" : null
                ];
            } 
            elseif ($action === 'update_subject') {
                if (!$courseId) {
                    throw new Exception("Course ID is required for updates.");
                }
                
                // Verify the course belongs to the HOD's department
                $stmtVerify = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND dep_id = ?");
                $stmtVerify->bind_param("ii", $courseId, $hodDepId);
                $stmtVerify->execute();
                $verifyResult = $stmtVerify->get_result();
                if ($verifyResult->num_rows === 0) {
                    throw new Exception("Course not found or not in your department.");
                }
                $stmtVerify->close();
                
                // Update course information
                if ($s3Key) {
                    $stmtUpdate = $conn->prepare("UPDATE courses SET course_name = ?, stage = ?, semester = ?, description = ?, course_image_s3_key = ? WHERE course_id = ?");
                    $stmtUpdate->bind_param("siissi", $courseName, $stage, $semester, $description, $s3Key, $courseId);
                } else {
                    $stmtUpdate = $conn->prepare("UPDATE courses SET course_name = ?, stage = ?, semester = ?, description = ? WHERE course_id = ?");
                    $stmtUpdate->bind_param("siisi", $courseName, $stage, $semester, $description, $courseId);
                }
                
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Failed to update course: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
                
                // Update teacher assignment if provided
                if ($teacherUserId) {
                    // First, check if there's an existing assignment
                    $stmtCheckAssign = $conn->prepare("SELECT assignment_id FROM course_teachers WHERE course_id = ?");
                    $stmtCheckAssign->bind_param("i", $courseId);
                    $stmtCheckAssign->execute();
                    $assignResult = $stmtCheckAssign->get_result();
                    $stmtCheckAssign->close();
                    
                    if ($assignResult->num_rows > 0) {
                        // Update existing assignment
                        $stmtUpdateAssign = $conn->prepare("UPDATE course_teachers SET teacher_user_id = ? WHERE course_id = ?");
                        $stmtUpdateAssign->bind_param("ii", $teacherUserId, $courseId);
                        $stmtUpdateAssign->execute();
                        $stmtUpdateAssign->close();
                    } else {
                        // Create new assignment
                        $stmtNewAssign = $conn->prepare("INSERT INTO course_teachers (course_id, teacher_user_id) VALUES (?, ?)");
                        $stmtNewAssign->bind_param("ii", $courseId, $teacherUserId);
                        $stmtNewAssign->execute();
                        $stmtNewAssign->close();
                    }
                }
                
                $conn->commit();
                $response = [
                    'success' => true, 
                    'message' => 'Subject updated successfully.',
                    'image_url' => $s3Key ? "https://{$s3BucketName}.s3.amazonaws.com/{$s3Key}" : null
                ];
            }
        }
        elseif ($action === 'delete_subject') {
            $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$courseId) throw new Exception("Missing course ID for deletion.");
            
            // Get the S3 key for the course image before deleting
            $stmtGetImage = $conn->prepare("SELECT course_image_s3_key FROM courses WHERE course_id = ? AND dep_id = ?");
            $stmtGetImage->bind_param("ii", $courseId, $hodDepId);
            $stmtGetImage->execute();
            $imageResult = $stmtGetImage->get_result();
            $s3Key = null;
            if ($imageResult->num_rows > 0) {
                $imageRow = $imageResult->fetch_assoc();
                $s3Key = $imageRow['course_image_s3_key'];
            }
            $stmtGetImage->close();
            
            // Delete the course
            $stmtDelete = $conn->prepare("DELETE FROM courses WHERE course_id = ? AND dep_id = ?");
            if (!$stmtDelete) throw new Exception("DB Delete Prepare Error: ".$conn->error);
            $stmtDelete->bind_param("ii", $courseId, $hodDepId);
            if (!$stmtDelete->execute()) throw new Exception("DB Delete Execute Error: ".$stmtDelete->error);
            
            if ($stmtDelete->affected_rows > 0) {
                // Delete the image from S3 if it exists
                if ($s3Key && $s3Client && $s3BucketName) {
                    try {
                        $s3Client->deleteObject([
                            'Bucket' => $s3BucketName,
                            'Key' => $s3Key
                        ]);
                    } catch (AwsException $e) {
                        error_log("Failed to delete S3 object: " . $e->getMessage());
                        // Don't fail the operation, just log the error
                    }
                }
                
                $response = ['success' => true, 'message' => 'Subject deleted.'];
            } else {
                throw new Exception("Subject not found or not deleted.");
            }
            $stmtDelete->close();
        }
    }
    else {
        // Handle JSON requests for operations that don't involve file uploads
        $input = json_decode(file_get_contents('php://input'), true);
        $action = filter_var($input['action'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if ($action === 'get_subject') {
            $courseId = filter_var($input['course_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$courseId) throw new Exception("Missing course ID.");
            
            $stmt = $conn->prepare("SELECT c.*, 
                                   (SELECT teacher_user_id FROM course_teachers WHERE course_id = c.course_id LIMIT 1) as teacher_id
                                   FROM courses c 
                                   WHERE c.course_id = ? AND c.dep_id = ?");
            $stmt->bind_param("ii", $courseId, $hodDepId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Subject not found or not in your department.");
            }
            
            $subjectData = $result->fetch_assoc();
            $stmt->close();
            
            // Generate a presigned URL for the image if it exists
            $imageUrl = null;
            if (!empty($subjectData['course_image_s3_key']) && $s3Client && $s3BucketName) {
                try {
                    $cmd = $s3Client->getCommand('GetObject', [
                        'Bucket' => $s3BucketName,
                        'Key' => $subjectData['course_image_s3_key']
                    ]);
                    $request = $s3Client->createPresignedRequest($cmd, '+5 minutes');
                    $imageUrl = (string) $request->getUri();
                } catch (AwsException $e) {
                    error_log("Failed to generate presigned URL: " . $e->getMessage());
                    $imageUrl = "https://{$s3BucketName}.s3.amazonaws.com/{$subjectData['course_image_s3_key']}";
                }
            }
            
            $response = [
                'success' => true,
                'subject' => [
                    'course_id' => $subjectData['course_id'],
                    'course_name' => $subjectData['course_name'],
                    'stage' => $subjectData['stage'],
                    'semester' => $subjectData['semester'],
                    'description' => $subjectData['description'],
                    'teacher_id' => $subjectData['teacher_id'],
                    'image_s3_key' => $subjectData['course_image_s3_key'],
                    'image_url' => $imageUrl
                ]
            ];
        }
        elseif ($action === 'delete_subject') {
            $courseId = filter_var($input['course_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$courseId) throw new Exception("Missing course ID for deletion.");
            
            // Get the S3 key for the course image before deleting
            $stmtGetImage = $conn->prepare("SELECT course_image_s3_key FROM courses WHERE course_id = ? AND dep_id = ?");
            $stmtGetImage->bind_param("ii", $courseId, $hodDepId);
            $stmtGetImage->execute();
            $imageResult = $stmtGetImage->get_result();
            $s3Key = null;
            if ($imageResult->num_rows > 0) {
                $imageRow = $imageResult->fetch_assoc();
                $s3Key = $imageRow['course_image_s3_key'];
            }
            $stmtGetImage->close();
            
            // Delete the course
            $stmtDelete = $conn->prepare("DELETE FROM courses WHERE course_id = ? AND dep_id = ?");
            if (!$stmtDelete) throw new Exception("DB Delete Prepare Error: ".$conn->error);
            $stmtDelete->bind_param("ii", $courseId, $hodDepId);
            if (!$stmtDelete->execute()) throw new Exception("DB Delete Execute Error: ".$stmtDelete->error);
            
            if ($stmtDelete->affected_rows > 0) {
                // Delete the image from S3 if it exists
                if ($s3Key && $s3Client && $s3BucketName) {
                    try {
                        $s3Client->deleteObject([
                            'Bucket' => $s3BucketName,
                            'Key' => $s3Key
                        ]);
                    } catch (AwsException $e) {
                        error_log("Failed to delete S3 object: " . $e->getMessage());
                        // Don't fail the operation, just log the error
                    }
                }
                
                $response = ['success' => true, 'message' => 'Subject deleted.'];
            } else {
                throw new Exception("Subject not found or not deleted.");
            }
            $stmtDelete->close();
        }
        else {
            throw new Exception("Invalid subject action.");
        }
    }

} catch (Exception $e) {
    // even if no BEGIN_TRANSACTION was called, rollback() is a no‑op
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    error_log("Error managing subject by HOD {$hodUserId}: " . $e->getMessage());
    $response['message'] = $e->getMessage();
    if (! headers_sent()) {
        http_response_code(500);
    }
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>