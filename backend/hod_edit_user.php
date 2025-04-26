<?php
// backend/hod_edit_user.php
// Handles editing student and teacher information by HOD

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/security.php';

// CSRF Protection
$requestHeaders = getallheaders();
$csrfToken = $requestHeaders['X-CSRF-Token'] ?? '';
if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Auth Check (HoD)
$hodUserId = $_SESSION['user_id'] ?? null;
$hodRoleId = $_SESSION['role_id'] ?? null;
$hodDepId = $_SESSION['dep_id'] ?? null;

if (!$hodUserId || $hodRoleId !== 5 || !$hodDepId || ($_SESSION['user_status'] ?? '') !== 'perm') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Get Input & Action
$input = json_decode(file_get_contents('php://input'), true);
$action = filter_var($input['action'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$userId = filter_var($input['user_id'] ?? 0, FILTER_VALIDATE_INT);

// Ensure proper character encoding for the response
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Invalid request.'];

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("DB connection error.");
    }

    // Verify the user belongs to HOD's department
    $stmt = $conn->prepare("SELECT u.*, uc.role_id, uc.Email 
                           FROM users u 
                           JOIN user_credentials uc ON u.id = uc.u_id 
                           WHERE u.id = ? AND u.dep_id = ?");
    $stmt->bind_param("ii", $userId, $hodDepId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User not found or not in your department.");
    }
    
    $userData = $result->fetch_assoc();
    $stmt->close();
    
    $userRole = $userData['role_id'];
    
    if ($action === 'get_user') {
        // Return user data for editing
        $response = [
            'success' => true,
            'user' => [
                'id' => $userData['id'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'third_name' => $userData['third_name'],
                'mother_first_name' => $userData['mother_first_name'],
                'mother_second_name' => $userData['mother_second_name'],
                'birthday' => $userData['birthday'],
                'gender' => $userData['gender'],
                'city' => $userData['city'],
                'nationality' => $userData['nationality'],
                'email' => $userData['Email'],
                'role_id' => $userData['role_id']
            ]
        ];
        
        // Add role-specific fields
        if ($userRole == 7) { // Student
            $response['user']['stage'] = $userData['stage'];
            $response['user']['degree'] = $userData['degree'];
            $response['user']['study_mode'] = $userData['study_mode'];
        } else if ($userRole == 6) { // Teacher
            $response['user']['academic_title'] = $userData['academic_title'];
        }
    } 
    else if ($action === 'update_user') {
        // Validate input fields
        $firstName = escapeHtml(trim($input['first_name'] ?? ''));
        $lastName = escapeHtml(trim($input['last_name'] ?? ''));
        $thirdName = escapeHtml(trim($input['third_name'] ?? ''));
        $motherFirstName = escapeHtml(trim($input['mother_first_name'] ?? ''));
        $motherSecondName = escapeHtml(trim($input['mother_second_name'] ?? ''));
        $birthday = escapeHtml(trim($input['birthday'] ?? ''));
        $gender = escapeHtml(trim($input['gender'] ?? ''));
        $city = escapeHtml(trim($input['city'] ?? ''));
        $nationality = escapeHtml(trim($input['nationality'] ?? 'Iraq'));
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if (empty($firstName) || empty($lastName) || empty($thirdName) || empty($birthday) || 
            empty($gender) || empty($city) || empty($email)) {
            throw new Exception("All required fields must be filled.");
        }
        
        // Check if email is already in use by another user
        if ($email !== $userData['Email']) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM user_credentials WHERE Email = ? AND u_id != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $emailCount = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
            
            if ($emailCount > 0) {
                throw new Exception("Email is already in use by another account.");
            }
        }
        
        $conn->begin_transaction();
        
        // Update basic user information
        $stmt = $conn->prepare("UPDATE users SET 
                               first_name = ?, 
                               last_name = ?, 
                               third_name = ?, 
                               mother_first_name = ?, 
                               mother_second_name = ?, 
                               birthday = ?, 
                               gender = ?, 
                               city = ?, 
                               nationality = ? 
                               WHERE id = ?");
        
        $stmt->bind_param("sssssssssi", 
            $firstName, 
            $lastName, 
            $thirdName, 
            $motherFirstName, 
            $motherSecondName, 
            $birthday, 
            $gender, 
            $city, 
            $nationality, 
            $userId
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user information: " . $stmt->error);
        }
        $stmt->close();
        
        // Update email in user_credentials
        $stmt = $conn->prepare("UPDATE user_credentials SET Email = ? WHERE u_id = ?");
        $stmt->bind_param("si", $email, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user email: " . $stmt->error);
        }
        $stmt->close();
        
        // Update role-specific information
        if ($userRole == 7) { // Student
            $stage = filter_var($input['stage'] ?? 0, FILTER_VALIDATE_INT);
            $degree = escapeHtml(trim($input['degree'] ?? ''));
            $studyMode = escapeHtml(trim($input['study_mode'] ?? ''));
            
            if (!$stage || empty($degree) || empty($studyMode)) {
                throw new Exception("All student-specific fields are required.");
            }
            
            $stmt = $conn->prepare("UPDATE users SET 
                                   stage = ?, 
                                   degree = ?, 
                                   study_mode = ? 
                                   WHERE id = ?");
            
            $stmt->bind_param("issi", $stage, $degree, $studyMode, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update student information: " . $stmt->error);
            }
            $stmt->close();
        } 
        else if ($userRole == 6) { // Teacher
            $academicTitle = escapeHtml(trim($input['academic_title'] ?? ''));
            
            $stmt = $conn->prepare("UPDATE users SET academic_title = ? WHERE id = ?");
            $stmt->bind_param("si", $academicTitle, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update teacher information: " . $stmt->error);
            }
            $stmt->close();
        }
        
        $conn->commit();
        $response = ['success' => true, 'message' => 'User information updated successfully.'];
    }
    else {
        throw new Exception("Invalid action specified.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    
    error_log("Error editing user by HOD {$hodUserId}: " . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
    
    if (!headers_sent()) {
        http_response_code(500);
    }
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;

/**
 * Escape HTML special characters in a string
 * 
 * @param string $str The input string
 * @return string The escaped string
 */
function escapeHtml($str) {
    if ($str === null || $str === '') return '';
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
