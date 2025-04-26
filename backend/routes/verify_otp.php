<?php
// backend/routes/verify_otp.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Baghdad');

require_once __DIR__ . '/../config/db.php';

$otp_input = trim($_POST['otp'] ?? '');
if (!$otp_input || !ctype_digit($otp_input)) {
    echo json_encode(["success" => false, "message" => "يرجى إدخال رمز تحقق صالح."]);
    exit;
}

$email = $_SESSION['otp_email'] ?? '';
if (!$email) {
    echo json_encode(["success" => false, "message" => "لا يوجد بريد إلكتروني مرتبط بطلب الرمز."]);
    exit;
}

// Retrieve the most recent OTP record for this email from the otp_codes table.
$stmt = $conn->prepare("SELECT otp, expires_at FROM otp_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "لم يتم العثور على رمز تحقق مسجل لهذا البريد."]);
    $stmt->close();
    exit;
}
$stmt->bind_result($storedOtp, $expires_at);
$stmt->fetch();
$stmt->close();

if (time() > $expires_at) {
    echo json_encode(["success" => false, "message" => "رمز التحقق منتهي الصلاحية. يرجى إعادة إرسال رمز جديد."]);
    exit;
}

if ($otp_input != $storedOtp) {
    echo json_encode(["success" => false, "message" => "رمز التحقق غير صحيح."]);
    exit;
}

$_SESSION['verified'] = true;
echo json_encode(["success" => true, "message" => "تم التحقق بنجاح."]);
?>
