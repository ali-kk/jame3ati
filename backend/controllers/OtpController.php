<?php
// backend/controllers/OtpController.php

// No namespace declared so that it works like your original system.
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

class OtpController {
    protected $conn;
    protected $sesClient;
    private $timezone;

    // Constructor expects a valid MySQLi connection and an AWS SES client.
    public function __construct($conn, SesClient $sesClient) {
        $this->conn = $conn;
        $this->sesClient = $sesClient;
        $this->timezone = new DateTimeZone('Asia/Baghdad');
        // Optional: Keep MySQLi reporting enabled for production robustness
        // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    /**
     * Checks if email exists in user_credentials. If so, skips OTP send but returns success.
     * Otherwise, checks rate limit, generates OTP, inserts, and sends email.
     *
     * @param string $email
     * @return array
     */
    public function processSendOtp($email) {
        // Log entry with the email received
        error_log("processSendOtp called for email: " . $email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("processSendOtp: Invalid email format - " . $email);
            return ["success" => false, "message" => "بريد إلكتروني غير صالح."];
        }

        // *** NEW: Check if email exists in user_credentials first ***
        $stmt_user_check = null;
        try {
            $sql_user_check = "SELECT 1 FROM user_credentials WHERE Email = ? LIMIT 1";
            $stmt_user_check = $this->conn->prepare($sql_user_check);
            if (!$stmt_user_check) {
                throw new Exception("DB Prepare Error (User Check): " . $this->conn->error);
            }
            $stmt_user_check->bind_param("s", $email);
            $stmt_user_check->execute();
            $stmt_user_check->store_result();

            $emailExists = ($stmt_user_check->num_rows > 0);
            $stmt_user_check->close();

            // If email EXISTS, simulate success and prepare for verification step
            if ($emailExists) {
                error_log("Email '{$email}' already registered. Skipping OTP generation/sending.");
                // Set session email so verify_otp.php can work
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $_SESSION['otp_email'] = $email;

                // Return a success message identical to the one used when OTP *is* sent.
                return [
                    "success" => true,
                    "message" => "تم إرسال رمز التحقق إلى بريدك الإلكتروني ($email). صالح لمدة 10 دقائق."
                ];
            }

        } catch (Exception $e) {
             error_log("DB Error during User Email Check for email {$email}: " . $e->getMessage());
             if ($stmt_user_check instanceof mysqli_stmt) $stmt_user_check->close();
             return ["success" => false, "message" => "خطأ أثناء التحقق من البريد الإلكتروني."];
        }
        // *** END: Email Existence Check ***


        // --- If email DOES NOT exist, proceed with the rest of the logic from the code you provided ---
        error_log("Email '{$email}' not found in user_credentials. Proceeding with OTP generation.");

        try {
            // Get current time (still needed for expires_at)
            $nowDateTime = new DateTime('now', $this->timezone);
            $minIntervalSeconds = 120; // 2 minutes

            // --- Rate Limit Check (As per the code you sent) ---
            $stmt_check = null;
            try {
                // Query checks if an entry exists for this email newer than 2 minutes ago
                $sql = "SELECT 1 FROM otp_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1";

                error_log("Rate Limit Check Query for email '{$email}': {$sql} with interval {$minIntervalSeconds} seconds"); // Log the query

                $stmt_check = $this->conn->prepare($sql);
                if (!$stmt_check) {
                    throw new Exception("DB Prepare Error (Rate Limit Check): " . $this->conn->error);
                }

                $stmt_check->bind_param("si", $email, $minIntervalSeconds); // Bind email and interval (as integer)
                $stmt_check->execute();
                $stmt_check->store_result(); // Important to store result before checking num_rows

                $recentOtpExists = ($stmt_check->num_rows > 0); // Check if any row was found

                error_log("Rate Limit Check Result for '{$email}': Found recent OTP = " . ($recentOtpExists ? 'Yes' : 'No')); // Log the result

                $stmt_check->close();

                // If a recent OTP was found by the query, return the rate limit message
                if ($recentOtpExists) {
                    error_log("Rate limit triggered for email: " . $email);
                    return ["success" => false, "message" => "الرجاء الانتظار دقيقتين قبل إعادة إرسال رمز التحقق."];
                }

            } catch (Exception $e) {
                // Handle rate limit check DB errors
                error_log("DB Error during Rate Limit Check for email {$email}: " . $e->getMessage());
                if ($stmt_check instanceof mysqli_stmt) $stmt_check->close();
                return ["success" => false, "message" => "خطأ أثناء التحقق من حدود الإرسال."];
            }
            // --- End Rate Limit Check ---


            // Generate OTP (if rate limit not hit)
            try {
                $otp = random_int(100000, 999999);
            } catch (Exception $e) {
                error_log("Error generating random int for email {$email}: " . $e->getMessage());
                return ["success" => false, "message" => "حدث خطأ أثناء توليد رمز التحقق."];
            }
            $otpStr = str_pad((string)$otp, 6, '0', STR_PAD_LEFT);

            // Format timestamps for insert
            $expiresDateTime = (clone $nowDateTime)->modify('+10 minutes');
            $nowFormatted = $nowDateTime->format('Y-m-d H:i:s'); // For TIMESTAMP column
            $expiresFormatted = $expiresDateTime->format('Y-m-d H:i:s'); // For DATETIME column
            error_log("Proceeding to insert OTP {$otpStr} for {$email}. Now: {$nowFormatted}, Expires: {$expiresFormatted}");


            // Insert OTP
            $stmt_insert = null;
            try {
                $stmt_insert = $this->conn->prepare("INSERT INTO otp_codes (email, otp, created_at, expires_at) VALUES (?, ?, ?, ?)");
                if (!$stmt_insert) {
                    throw new Exception("DB Prepare Error (INSERT): " . $this->conn->error);
                }
                $stmt_insert->bind_param("ssss", $email, $otpStr, $nowFormatted, $expiresFormatted);
                if (!$stmt_insert->execute()) {
                    throw new Exception("DB Execute Error (INSERT): " . $stmt_insert->error);
                }
                error_log("Successfully inserted OTP {$otpStr} for {$email}"); // Log success
                $stmt_insert->close();

            } catch (Exception $e) {
                error_log("DB Error during INSERT OTP operation for email {$email}: " . $e->getMessage());
                if ($stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
                return ["success" => false, "message" => "خطأ أثناء تسجيل الرمز في قاعدة البيانات."];
            }

            // Session and Email Sending
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['otp_email'] = $email;

            if ($this->sendOtpEmail($email, $otpStr)) {
                return ["success" => true, "message" => "تم إرسال رمز التحقق إلى بريدك الإلكتروني ($email). صالح لمدة 10 دقائق."];
            } else {
                return ["success" => false, "message" => "حدث خطأ أثناء إرسال رمز التحقق عبر البريد الإلكتروني."];
            }

        } catch (Exception $e) {
            error_log("General Exception in processSendOtp for {$email}: " . $e->getMessage());
            return ["success" => false, "message" => "حدث خطأ عام غير متوقع."];
        }
    } // End processSendOtp


    // --- sendOtpEmail function (copied from your provided code, assumed correct) ---
    public function sendOtpEmail($email, $otpStr) {
     $subject = 'رمز التحقق من منصة جامعتي'; // University Verification Code

     $digits = str_split($otpStr); // e.g., ['2', '7', '5', '7', '9', '0']

     // --- Modern OTP Display using Spans ---
     $otpBoxes = '<div style="direction: ltr; text-align: center; margin: 30px 0;">'; // LTR container for boxes
     foreach ($digits as $digit) {
         $otpBoxes .= '<span style="display: inline-block; border: 1px solid #dfe1e5; background-color: #f8f9fa; color: #333; font-size: 26px; font-weight: bold; padding: 12px 16px; margin: 0 4px; border-radius: 6px; min-width: 24px; text-align: center; line-height: 1;">'
                    . htmlspecialchars($digit) . '</span>';
     }
     $otpBoxes .= '</div>';
     // --- End OTP Display ---

     // Header image URL – update this URL or use environment variable
     $headerImageUrl = $_ENV['HEADER_IMAGE_URL'] ?? 'https://drive.google.com/uc?export=download&id=1QwEBEddkw53vsLJTFuR4nBPYIylZOB7p'; // Placeholder URL

     // --- Updated HTML Email Body ---
     $bodyHtml = '
     <!DOCTYPE html>
     <html lang="ar">
       <head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>' . htmlspecialchars($subject) . '</title>
         <style>
           /* Basic CSS reset for email */
           body, div, p, h2 { margin: 0; padding: 0; }
           img { border: 0; -ms-interpolation-mode: bicubic; max-width: 100%; }
           body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
         </style>
       </head>
       <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; direction: rtl; text-align: center; margin: 0; padding: 0; background-color: #f4f7f6;">
         <div style="max-width: 600px; width: 90%; margin: 40px auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">

           <div style="padding: 25px 30px; text-align: center; background-color: #f8f9fa; border-bottom: 1px solid #e0e0e0;">
             <img src="' . htmlspecialchars($headerImageUrl) . '" alt="شعار منصة جامعتي" style="max-width: 120px; height: auto; margin-bottom: 15px;">
             <h2 style="color: #0d2d5e; /* Darker blue */ font-size: 22px; font-weight: 600; margin: 0;">رمز التحقق الخاص بك</h2>
           </div>

           <div style="padding: 30px 30px 40px 30px;">
             <p style="font-size: 16px; color: #333333; line-height: 1.6; margin-bottom: 25px;">
               لاستكمال عملية التسجيل أو تسجيل الدخول، يرجى استخدام رمز التحقق التالي:
             </p>

             ' . $otpBoxes . '

             <p style="font-size: 15px; color: #555555; line-height: 1.5; margin-bottom: 15px;">
               هذا الرمز صالح لمدة 10 دقائق فقط.
             </p>
             <p style="font-size: 13px; color: #777777; line-height: 1.5;">
               إذا لم تطلب هذا الرمز، يمكنك تجاهل هذا البريد الإلكتروني بأمان. لم يتم إجراء أي تغييرات على حسابك.
             </p>
           </div>

           <div style="padding: 20px 30px; background-color: #f8f9fa; border-top: 1px solid #e0e0e0; text-align: center;">
            <p style="font-size: 12px; color: #888888;">
              &copy; ' . date('Y') . '  منصة جامعتي. جميع الحقوق محفوظة.
              <br>
              </p>

         </div>
       </body>
     </html>';
     // --- End Updated HTML ---

     $bodyText = "رمز التحقق الخاص بك هو: $otpStr.\nهذا الرمز صالح لمدة 10 دقائق فقط.\nإذا لم تطلب هذا الرمز، يمكنك تجاهل هذا البريد الإلكتروني بأمان.\n\n&copy; " . date('Y') . " جامعتي.";
     $senderEmail = $_ENV['SENDER_EMAIL'] ?? 'no-reply@my-uni.xyz';

     try {
         $result = $this->sesClient->sendEmail([
             'Destination' => ['ToAddresses' => [$email]],
             'Message' => [
                 'Body' => [
                     'Html' => ['Charset' => 'UTF-8', 'Data' => $bodyHtml],
                     'Text' => ['Charset' => 'UTF-8', 'Data' => $bodyText],
                 ],
                 'Subject' => ['Charset' => 'UTF-8', 'Data' => $subject],
             ], 'Source' => $senderEmail,
         ]);
         return true;
     } catch (AwsException $e) {
         error_log("AWS SES Error in sendOtpEmail to {$email}: " . $e->getMessage() . " | AWS Error Code: " . $e->getAwsErrorCode() . " | AWS Error Type: " . $e->getAwsErrorType());
         return false;
     } catch (Exception $e) {
          error_log("General Error in sendOtpEmail to {$email}: " . $e->getMessage());
          return false;
     }
 } // End sendOtpEmail


    /**
     * Process email change request with OTP verification
     * 
     * @param int $userId User ID
     * @param string $newEmail New email address
     * @param string $password Current password for verification
     * @param bool $resend Whether this is a resend request
     * @return array Response with success status and message
     */
    public function processEmailChange($userId, $newEmail, $password, $resend = false) {
        // Validate email format
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Invalid email format"];
        }

        try {
            // Check rate limiting for OTP requests
            if (!$resend && !$this->checkRateLimit($userId, 'email_change')) {
                return ["success" => false, "message" => "Please wait before requesting another verification code"];
            }

            // Check if the new email already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $newEmail, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return ["success" => false, "message" => "This email is already registered with another account"];
            }
            
            // Get current user data
            $stmt = $this->conn->prepare("SELECT email, password FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ["success" => false, "message" => "User not found"];
            }
            
            $user = $result->fetch_assoc();
            $currentEmail = $user['email'];
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ["success" => false, "message" => "Incorrect password"];
            }
            
            // Check if the new email is different from the current email
            if ($newEmail === $currentEmail) {
                return ["success" => false, "message" => "The new email must be different from the current email"];
            }
            
            // Generate OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Generate a unique request ID
            $requestId = bin2hex(random_bytes(16));
            
            // Store OTP in the database
            if ($resend) {
                // Delete any existing OTP requests for this user
                $stmt = $this->conn->prepare("DELETE FROM email_change_requests WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
            }
            
            $stmt = $this->conn->prepare("INSERT INTO email_change_requests (user_id, new_email, otp, otp_expiry, request_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("issss", $userId, $newEmail, $otp, $otpExpiry, $requestId);
            $stmt->execute();
            
            // Send OTP to current email
            $subject = "Verification Code for Email Change";
            $message = "Your verification code for changing email is: $otp\n\n";
            $message .= "This code will expire in 10 minutes.\n\n";
            $message .= "If you did not request this change, please ignore this email or contact support.";
            
            if (!$this->sendEmail($currentEmail, $subject, $message)) {
                return ["success" => false, "message" => "Failed to send verification code. Please try again later."];
            }
            
            // Return success response
            return [
                "success" => true,
                "message" => "Verification code sent to your current email",
                "request_id" => $requestId
            ];
            
        } catch (Exception $e) {
            error_log('Error in processEmailChange: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Verify email OTP and send second OTP to new email
     * 
     * @param int $userId User ID
     * @param string $requestId Request ID
     * @param string $otpCode OTP code
     * @param bool $resendNew Whether to resend OTP to new email
     * @return array Response with success status and message
     */
    public function verifyEmailOtp($userId, $requestId, $otpCode, $resendNew = false) {
        try {
            // Get the email change request
            $stmt = $this->conn->prepare("SELECT id, new_email, otp, otp_expiry, status FROM email_change_requests WHERE user_id = ? AND request_id = ?");
            $stmt->bind_param("is", $userId, $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ["success" => false, "message" => "Invalid request or request expired"];
            }
            
            $request = $result->fetch_assoc();
            
            // Check if the request is still pending
            if ($request['status'] !== 'pending') {
                return ["success" => false, "message" => "This request has already been processed"];
            }
            
            // If resending OTP to new email
            if ($resendNew) {
                // Check rate limiting
                if (!$this->checkRateLimit($userId, 'email_change_new')) {
                    return ["success" => false, "message" => "Please wait before requesting another verification code"];
                }
                
                // Generate new OTP for the new email
                $newOtp = sprintf("%06d", mt_rand(100000, 999999));
                $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Update the request with the new OTP
                $stmt = $this->conn->prepare("UPDATE email_change_requests SET new_email_otp = ?, new_email_otp_expiry = ? WHERE id = ?");
                $stmt->bind_param("ssi", $newOtp, $otpExpiry, $request['id']);
                $stmt->execute();
                
                // Send OTP to new email
                $newEmail = $request['new_email'];
                $subject = "Verification Code for Email Change";
                $message = "Your verification code for confirming your new email is: $newOtp\n\n";
                $message .= "This code will expire in 10 minutes.\n\n";
                $message .= "If you did not request this change, please ignore this email or contact support.";
                
                if (!$this->sendEmail($newEmail, $subject, $message)) {
                    return ["success" => false, "message" => "Failed to send verification code to new email. Please try again later."];
                }
                
                return ["success" => true, "message" => "Verification code sent to your new email"];
            }
            
            // Check if OTP is valid
            if ($otpCode !== $request['otp']) {
                return ["success" => false, "message" => "Invalid verification code"];
            }
            
            // Check if OTP has expired
            if (strtotime($request['otp_expiry']) < time()) {
                return ["success" => false, "message" => "Verification code has expired"];
            }
            
            // Generate OTP for new email
            $newOtp = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Update the request status and add new email OTP
            $stmt = $this->conn->prepare("UPDATE email_change_requests SET status = 'verified', new_email_otp = ?, new_email_otp_expiry = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newOtp, $otpExpiry, $request['id']);
            $stmt->execute();
            
            // Send OTP to new email
            $newEmail = $request['new_email'];
            $subject = "Verification Code for Email Change";
            $message = "Your verification code for confirming your new email is: $newOtp\n\n";
            $message .= "This code will expire in 10 minutes.\n\n";
            $message .= "If you did not request this change, please ignore this email or contact support.";
            
            if (!$this->sendEmail($newEmail, $subject, $message)) {
                return ["success" => false, "message" => "Failed to send verification code to new email. Please try again later."];
            }
            
            return ["success" => true, "message" => "Verification successful. A code has been sent to your new email."];
            
        } catch (Exception $e) {
            error_log('Error in verifyEmailOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Verify new email OTP and complete email change
     * 
     * @param int $userId User ID
     * @param string $requestId Request ID
     * @param string $otpCode OTP code
     * @return array Response with success status and message
     */
    public function verifyNewEmailOtp($userId, $requestId, $otpCode) {
        try {
            // Check if new email and request ID are stored in session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            if (!isset($_SESSION['new_email']) || !isset($_SESSION['email_change_request_id']) || 
                $_SESSION['email_change_request_id'] !== $requestId || !isset($_SESSION['new_email_otp']) || 
                $_SESSION['current_email_verified'] !== true) {
                return ["success" => false, "message" => "Email change session expired or invalid. Please start over."];
            }
            
            $newEmail = $_SESSION['new_email'];
            $storedOtp = $_SESSION['new_email_otp'];
            
            // Check if OTP is valid
            if ($otpCode !== $storedOtp) {
                return ["success" => false, "message" => "Invalid verification code for new email"];
            }
            
            // Only now update the user's email after both verifications are complete
            $stmt = $this->conn->prepare("UPDATE user_credentials SET Email = ? WHERE u_id = ?");
            $stmt->bind_param("si", $newEmail, $userId);
            $stmt->execute();
            
            // Clear the session variables
            unset($_SESSION['new_email']);
            unset($_SESSION['email_change_request_id']);
            unset($_SESSION['new_email_otp']);
            unset($_SESSION['current_email_verified']);
            unset($_SESSION['new_email_verified']);
            
            return ["success" => true, "message" => "Email changed successfully", "new_email" => $newEmail];
            
        } catch (Exception $e) {
            error_log('Error in verifyNewEmailOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Process password change request with OTP verification
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @param bool $resend Whether this is a resend request
     * @return array Response with success status and message
     */
    public function sendPasswordChangeOtp($userId, $email, $currentPassword, $newPassword, $resend = false) {
        // Validate password strength
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
            return [
                "success" => false,
                "message" => "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character"
            ];
        }

        try {
            // Check rate limiting for OTP requests
            if (!$resend && !$this->checkRateLimit($userId, 'password_change')) {
                return ["success" => false, "message" => "Please wait before requesting another verification code"];
            }
            
            // Get current user data
            $stmt = $this->conn->prepare("SELECT u_id, Password FROM user_credentials WHERE u_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ["success" => false, "message" => "User not found"];
            }
            
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (!password_verify($currentPassword, $user['Password'])) {
                return ["success" => false, "message" => "Current password is incorrect"];
            }
            
            // Check if new password is the same as current password
            if ($newPassword === $currentPassword) {
                return ["success" => false, "message" => "New password must be different from current password"];
            }
            
            // Generate OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Generate a unique request ID
            $requestId = bin2hex(random_bytes(16));
            
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Store OTP in the database
            $stmt = $this->conn->prepare("INSERT INTO otp_codes (email, otp, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
            $stmt->bind_param("sss", $email, $otp, $otpExpiry);
            $stmt->execute();
            
            // Store password change data in session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['password_change_request_id'] = $requestId;
            $_SESSION['new_password_hash'] = $hashedPassword;
            $_SESSION['password_otp'] = $otp;
            
            // Send OTP to user's email with improved styling
            $subject = "Verification Code for Password Change";
            $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Change Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background-color: #f9f9f9; border-radius: 5px; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; height: auto; }
        .code { font-size: 24px; font-weight: bold; text-align: center; color: #4a6ee0; background-color: #e8eeff; padding: 10px; border-radius: 5px; margin: 20px 0; letter-spacing: 5px; }
        .footer { font-size: 12px; color: #777; margin-top: 30px; text-align: center; }
        .button { display: inline-block; background-color: #4a6ee0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Password Change Verification</h2>
        </div>
        <p>Dear User,</p>
        <p>You have requested to change your password. Please use the following verification code:</p>
        <div class='code'>$otp</div>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this change, please ignore this email or contact support immediately as your account may be compromised.</p>
        <p>Thank you,<br>Jame3ati Team</p>
        <div class='footer'>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>";
            
            $plainText = "Your verification code for changing password is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you did not request this change, please ignore this email or contact support immediately as your account may be compromised.";
            
            if (!$this->sendEmail($email, $subject, $plainText, $message)) {
                return ["success" => false, "message" => "Failed to send verification code. Please try again later."];
            }
            
            return [
                "success" => true,
                "message" => "Verification code sent to your email",
                "request_id" => $requestId
            ];
            
        } catch (Exception $e) {
            error_log('Error in sendPasswordChangeOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Verify password change OTP and complete password change
     * 
     * @param int $userId User ID
     * @param string $requestId Request ID
     * @param string $otpCode OTP code
     * @return array Response with success status and message
     */
    public function verifyPasswordOtp($userId, $requestId, $otpCode) {
        try {
            // Check if password change data is stored in session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            if (!isset($_SESSION['password_change_request_id']) || 
                $_SESSION['password_change_request_id'] !== $requestId || 
                !isset($_SESSION['new_password_hash']) || 
                !isset($_SESSION['password_otp'])) {
                return ["success" => false, "message" => "Password change session expired or invalid. Please start over."];
            }
            
            $storedOtp = $_SESSION['password_otp'];
            $newPasswordHash = $_SESSION['new_password_hash'];
            
            // Check if OTP is valid
            if ($otpCode !== $storedOtp) {
                return ["success" => false, "message" => "Invalid verification code"];
            }
            
            // Update the user's password
            $stmt = $this->conn->prepare("UPDATE user_credentials SET Password = ? WHERE u_id = ?");
            $stmt->bind_param("si", $newPasswordHash, $userId);
            $stmt->execute();
            
            // Clear the session variables
            unset($_SESSION['password_change_request_id']);
            unset($_SESSION['new_password_hash']);
            unset($_SESSION['password_otp']);
            
            return ["success" => true, "message" => "Password changed successfully"];
            
        } catch (Exception $e) {
            error_log('Error in verifyPasswordOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Check rate limiting for OTP requests
     * 
     * @param int $userId User ID
     * @param string $type Type of OTP request
     * @return bool True if rate limit not exceeded, false otherwise
     */
    private function checkRateLimit($userId, $type) {
        $minIntervalSeconds = 120; // 2 minutes
        
        try {
            $sql = "SELECT 1 FROM otp_requests WHERE user_id = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("isi", $userId, $type, $minIntervalSeconds);
            $stmt->execute();
            $stmt->store_result();
            
            $recentRequestExists = ($stmt->num_rows > 0);
            $stmt->close();
            
            if ($recentRequestExists) {
                return false;
            }
            
            // Log this request for rate limiting
            $stmt = $this->conn->prepare("INSERT INTO otp_requests (user_id, type, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $userId, $type);
            $stmt->execute();
            $stmt->close();
            
            return true;
        } catch (Exception $e) {
            error_log('Error in checkRateLimit: ' . $e->getMessage());
            return true; // Default to allowing the request if there's an error
        }
    }

    /**
     * Send email using AWS SES
     * 
     * @param string $email Recipient email
     * @param string $subject Email subject
     * @param string $messageText Plain text message
     * @param string $messageHtml HTML message (optional)
     * @return bool True if email sent successfully, false otherwise
     */
    private function sendEmail($email, $subject, $messageText, $messageHtml = null) {
        if ($messageHtml === null) {
            // Convert plain text to simple HTML
            $messageHtml = nl2br(htmlspecialchars($messageText));
        }
        
        $senderEmail = $_ENV['SENDER_EMAIL'] ?? 'no-reply@my-uni.xyz';
        
        try {
            $this->sesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => [$email]
                ],
                'Message' => [
                    'Body' => [
                        'Html' => [
                            'Charset' => 'UTF-8',
                            'Data' => $messageHtml
                        ],
                        'Text' => [
                            'Charset' => 'UTF-8',
                            'Data' => $messageText
                        ]
                    ],
                    'Subject' => [
                        'Charset' => 'UTF-8',
                        'Data' => $subject
                    ]
                ], 'Source' => $senderEmail,
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Error sending email to ' . $email . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP for email change
     * 
     * @param int $userId User ID
     * @param string $currentEmail Current email address
     * @param string $newEmail New email address
     * @return array Response with success status and message
     */
    public function sendEmailChangeOtp($userId, $currentEmail, $newEmail) {
        // Validate email format
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Invalid email format"];
        }

        try {
            // Check if the new email already exists
            $stmt = $this->conn->prepare("SELECT u_id FROM user_credentials WHERE Email = ? AND u_id != ?");
            $stmt->bind_param("si", $newEmail, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return ["success" => false, "message" => "This email is already registered with another account"];
            }
            
            // Check if the new email is different from the current email
            if ($newEmail === $currentEmail) {
                return ["success" => false, "message" => "The new email must be different from the current email"];
            }
            
            // Get current time
            $nowDateTime = new DateTime('now', $this->timezone);
            $minIntervalSeconds = 120; // 2 minutes

            // Rate Limit Check
            $stmt_check = $this->conn->prepare("SELECT 1 FROM otp_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1");
            $stmt_check->bind_param("si", $currentEmail, $minIntervalSeconds);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            $recentOtpExists = ($stmt_check->num_rows > 0);
            $stmt_check->close();
            
            if ($recentOtpExists) {
                return ["success" => false, "message" => "Please wait before requesting another verification code"];
            }
            
            // Generate OTP for current email
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store the new email in the session for verification
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['new_email'] = $newEmail;
            $_SESSION['email_change_request_id'] = bin2hex(random_bytes(16));
            $_SESSION['current_email_verified'] = false;
            $_SESSION['new_email_verified'] = false;
            
            // Insert OTP for current email
            $stmt_insert = $this->conn->prepare("INSERT INTO otp_codes (email, otp, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
            $stmt_insert->bind_param("sss", $currentEmail, $otp, $otpExpiry);
            $stmt_insert->execute();
            
            // Send OTP to current email with improved styling
            $subject = "Verification Code for Email Change";
            $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background-color: #f9f9f9; border-radius: 5px; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; height: auto; }
        .code { font-size: 24px; font-weight: bold; text-align: center; color: #4a6ee0; background-color: #e8eeff; padding: 10px; border-radius: 5px; margin: 20px 0; letter-spacing: 5px; }
        .footer { font-size: 12px; color: #777; margin-top: 30px; text-align: center; }
        .button { display: inline-block; background-color: #4a6ee0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Email Change Verification</h2>
        </div>
        <p>Dear User,</p>
        <p>You have requested to change your email address. To verify your current email, please use the following verification code:</p>
        <div class='code'>$otp</div>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this change, please ignore this email or contact support.</p>
        <p>Thank you,<br>Jame3ati Team</p>
        <div class='footer'>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>";
            
            $plainText = "Your verification code for changing email is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you did not request this change, please ignore this email or contact support.";
            
            if (!$this->sendEmail($currentEmail, $subject, $plainText, $message)) {
                return ["success" => false, "message" => "Failed to send verification code. Please try again later."];
            }
            
            return [
                "success" => true, 
                "message" => "Verification code sent to your current email address",
                "request_id" => $_SESSION['email_change_request_id']
            ];
            
        } catch (Exception $e) {
            error_log('Error in sendEmailChangeOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }

    /**
     * Send OTP to the new email address
     * 
     * @param int $userId User ID
     * @param string $newEmail New email address
     * @return bool True if successful, false otherwise
     */
    private function sendNewEmailOtp($userId, $newEmail) {
        try {
            // Generate OTP for new email
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP for new email
            $stmt_insert = $this->conn->prepare("INSERT INTO otp_codes (email, otp, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
            $stmt_insert->bind_param("sss", $newEmail, $otp, $otpExpiry);
            $stmt_insert->execute();
            
            // Store the new email OTP in session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['new_email_otp'] = $otp;
            
            // Send OTP to new email with improved styling
            $subject = "Verification Code for New Email";
            $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>New Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background-color: #f9f9f9; border-radius: 5px; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; height: auto; }
        .code { font-size: 24px; font-weight: bold; text-align: center; color: #4a6ee0; background-color: #e8eeff; padding: 10px; border-radius: 5px; margin: 20px 0; letter-spacing: 5px; }
        .footer { font-size: 12px; color: #777; margin-top: 30px; text-align: center; }
        .button { display: inline-block; background-color: #4a6ee0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Email Verification</h2>
        </div>
        <p>Dear User,</p>
        <p>Someone is trying to add this email address to their Jame3ati account. To verify this new email address, please use the following verification code:</p>
        <div class='code'>$otp</div>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this change, please ignore this email.</p>
        <p>Thank you,<br>Jame3ati Team</p>
        <div class='footer'>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>";
            
            $plainText = "Your verification code for new email is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you did not request this change, please ignore this email.";
            
            return $this->sendEmail($newEmail, $subject, $plainText, $message);
            
        } catch (Exception $e) {
            error_log('Error in sendNewEmailOtp: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify OTP for the current email address (first step of email change)
     * 
     * @param int $userId User ID
     * @param string $currentEmail Current email address
     * @param string $requestId Request ID
     * @param string $otpCode OTP code
     * @return array Response with success status and message
     */
    public function verifyEmailChangeOtp($userId, $currentEmail, $requestId, $otpCode) {
        try {
            // Check if new email is stored in session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            
            if (!isset($_SESSION['new_email']) || !isset($_SESSION['email_change_request_id']) || 
                $_SESSION['email_change_request_id'] !== $requestId) {
                return ["success" => false, "message" => "Email change session expired or invalid. Please start over."];
            }
            
            $newEmail = $_SESSION['new_email'];
            
            // Get the most recent OTP
            $stmt = $this->conn->prepare("SELECT otp, expires_at FROM otp_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("s", $currentEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ["success" => false, "message" => "No verification code found"];
            }
            
            $otpData = $result->fetch_assoc();
            
            // Check if OTP is valid
            if ($otpCode !== $otpData['otp']) {
                return ["success" => false, "message" => "Invalid verification code"];
            }
            
            // Check if OTP has expired
            if (strtotime($otpData['expires_at']) < time()) {
                return ["success" => false, "message" => "Verification code has expired"];
            }
            
            // Mark current email as verified
            $_SESSION['current_email_verified'] = true;
            
            // Now send OTP to the new email
            if (!$this->sendNewEmailOtp($userId, $newEmail)) {
                return ["success" => false, "message" => "Failed to send verification code to new email. Please try again."];
            }
            
            return [
                "success" => true, 
                "message" => "Current email verified successfully. A verification code has been sent to your new email address.",
                "request_id" => $requestId
            ];
            
        } catch (Exception $e) {
            error_log('Error in verifyEmailChangeOtp: ' . $e->getMessage());
            return ["success" => false, "message" => "An unexpected error occurred"];
        }
    }


} // End class OtpController
?>