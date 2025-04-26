<?php
// backend/hod_approve_user.php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/security.php';
set_security_headers();
header('Content-Type: application/json; charset=utf-8');

// Make sure we're using UTF-8 encoding
mysqli_set_charset($conn, "utf8mb4");

// Auth Check (HoD)
$hodUserId = $_SESSION['user_id'] ?? null; 
$hodRoleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null; 
$hodDepId = isset($_SESSION['dep_id']) ? (int)$_SESSION['dep_id'] : null;
$userStatus = $_SESSION['user_status'] ?? '';

if (!$hodUserId || $hodRoleId !== 5 || !$hodDepId || $userStatus !== 'perm') { 
    http_response_code(403); 
    echo json_encode(['success' => false, 'message' => 'Access denied.']); 
    exit; 
}

// Get Input
$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = filter_var($input['userId'] ?? null, FILTER_VALIDATE_INT);
$action = filter_var($input['action'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS); // 'approve' or 'reject'
$csrfToken = $input['csrf_token'] ?? '';

// Validate CSRF token
if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token. Please refresh the page and try again.'
    ]);
    exit;
}

if (!$targetUserId || !$action || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']); 
    exit;
}

// Determine new status
$newStatus = ($action === 'approve') ? 'perm' : 'rejected';

$response = ['success' => false, 'message' => 'Failed to process request.'];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) { throw new Exception("Database connection error."); }

    $conn->begin_transaction();

    // Verify target user is in HoD's department and status is 'temp'
    $stmtCheck = $conn->prepare("SELECT user_status, role_id FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.id = ? AND u.dep_id = ?");
    if(!$stmtCheck) throw new Exception("Database error occurred.");
    $stmtCheck->bind_param("ii", $targetUserId, $hodDepId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $targetUser = $resultCheck->fetch_assoc();
    $stmtCheck->close();

    if (!$targetUser) { throw new Exception("User not found or not in your department."); }
    if ($targetUser['user_status'] !== 'temp') { throw new Exception("User status is not 'pending' (temp). Cannot change status."); }

    // Update user status
    $stmtUpdate = $conn->prepare("UPDATE users SET user_status = ? WHERE id = ? AND user_status = 'temp'"); // Add status check to prevent race conditions
    if(!$stmtUpdate) throw new Exception("Database error occurred.");
    $stmtUpdate->bind_param("si", $newStatus, $targetUserId);

    if (!$stmtUpdate->execute()) { throw new Exception("Failed to update user status."); }

    if ($stmtUpdate->affected_rows === 0) {
        // This might happen if the status was changed by another process between check and update
        throw new Exception("Could not update user status (already changed or error).");
    }
    $stmtUpdate->close();

    $conn->commit();
    
    // Log the action
    error_log("User {$targetUserId} was ".($action === 'approve' ? 'approved' : 'rejected')." by HOD {$hodUserId}");
    
    $response = ['success' => true, 'message' => "User successfully ".($action === 'approve' ? 'approved' : 'rejected')."."];

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log detailed error but return generic message
    error_log("Error {$action}ing user {$targetUserId} by HOD {$hodUserId}: " . $e->getMessage());
    $response['message'] = "An error occurred while processing your request. Please try again later.";
    http_response_code(500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>