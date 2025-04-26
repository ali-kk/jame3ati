<?php
// Start session
session_start();

// Include database connection
require_once 'config/db.php';

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
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// Get JSON data from request
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Validate input
if (!isset($input['otp_code']) || !isset($input['request_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];
$requestId = $input['request_id'];
$otpCode = $input['otp_code'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get the email change request
    $stmt = $conn->prepare("SELECT id, new_email, new_email_otp, new_email_otp_expiry, status FROM email_change_requests WHERE user_id = ? AND request_id = ?");
    $stmt->bind_param("is", $userId, $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid request or request expired');
    }
    
    $request = $result->fetch_assoc();
    
    // Check if the request has been verified with the first OTP
    if ($request['status'] !== 'verified') {
        throw new Exception('This request has not been verified with the first code');
    }
    
    // Check if new email OTP exists
    if (empty($request['new_email_otp'])) {
        throw new Exception('Verification code for new email not found');
    }
    
    // Check if OTP is valid
    if ($otpCode !== $request['new_email_otp']) {
        throw new Exception('Invalid verification code');
    }
    
    // Check if OTP has expired
    if (strtotime($request['new_email_otp_expiry']) < time()) {
        throw new Exception('Verification code has expired');
    }
    
    // Get the new email
    $newEmail = $request['new_email'];
    
    // Update the user's email
    $stmt = $conn->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newEmail, $userId);
    $stmt->execute();
    
    // Update the request status
    $stmt = $conn->prepare("UPDATE email_change_requests SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $request['id']);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Email changed successfully',
        'new_email' => $newEmail
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Log the error
    error_log('Error in verify_new_email_otp.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Close the database connection
$conn->close();
?>
