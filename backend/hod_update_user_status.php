<?php
// backend/hod_update_user_status.php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

// Make sure we're using UTF-8 encoding
mysqli_set_charset($conn, "utf8mb4");

// Auth Check (HoD)
$hodUserId = $_SESSION['user_id'] ?? null; $hodRoleId = $_SESSION['role_id'] ?? null; $hodDepId = $_SESSION['dep_id'] ?? null;
if (!$hodUserId || $hodRoleId !== 5 || !$hodDepId) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Access denied.']); exit; }

// Get Input
$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = filter_var($input['userId'] ?? null, FILTER_VALIDATE_INT);
$newStatus = filter_var($input['status'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS); // 'perm' or 'frozen'

if (!$targetUserId || !$newStatus || !in_array($newStatus, ['perm', 'frozen'])) {
    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid input data (userId/status).']); exit;
}

$response = ['success' => false, 'message' => 'Failed to update status.'];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) { throw new Exception("DB connection error."); }

    $conn->begin_transaction();

    // Verify target user is in HoD's department and status is valid to change
    // Can only freeze 'perm' or unfreeze 'frozen'
    $stmtCheck = $conn->prepare("SELECT user_status FROM users WHERE id = ? AND dep_id = ?");
     if(!$stmtCheck) throw new Exception("DB Check Prepare Error: ".$conn->error);
    $stmtCheck->bind_param("ii", $targetUserId, $hodDepId); $stmtCheck->execute(); $resultCheck = $stmtCheck->get_result(); $targetUser = $resultCheck->fetch_assoc(); $stmtCheck->close();

    if (!$targetUser) { throw new Exception("User not found or not in your department."); }

    $currentStatus = $targetUser['user_status'];
    if ($newStatus === 'frozen' && $currentStatus !== 'perm') { throw new Exception("Only 'active' (perm) users can be frozen."); }
    if ($newStatus === 'perm' && $currentStatus !== 'frozen') { throw new Exception("Only 'frozen' users can be un-frozen (activated)."); }

    // Update user status
    $stmtUpdate = $conn->prepare("UPDATE users SET user_status = ? WHERE id = ?");
     if(!$stmtUpdate) throw new Exception("DB Update Prepare Error: ".$conn->error);
    $stmtUpdate->bind_param("si", $newStatus, $targetUserId);
    if (!$stmtUpdate->execute()) { throw new Exception("DB Update Execute Error: ".$stmtUpdate->error); }

    if ($stmtUpdate->affected_rows === 0 && $currentStatus !== $newStatus) { // Check if status actually needed changing
        throw new Exception("User status was not updated (no change needed or error).");
    }
    $stmtUpdate->close();

    $conn->commit();
    $actionText = ($newStatus === 'frozen') ? 'frozen' : 'activated';
    $response = ['success' => true, 'message' => "User successfully {$actionText}."];

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating status for user {$targetUserId} to {$newStatus} by HOD {$hodUserId}: " . $e->getMessage());
    $response['message'] = $e->getMessage();
    http_response_code(500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>