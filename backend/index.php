<?php
// Ensure session is started if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/db.php'; // Establishes $conn
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// --- AWS SDK Includes ---
use Aws\Ses\SesClient;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
// --- End AWS SDK Includes ---

// --- AWS Client Instantiation ---
$sesClient = null; // Initialize
try {
    // Make sure the required SES env vars exist
    $sesAccessKey = $_ENV['AWS_ACCESS_KEY_ID'] ?? null; // Use the general key for SES
    $sesSecretKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null; // Use the general secret for SES
    $awsRegion    = $_ENV['AWS_REGION'] ?? null;

    if (!empty($sesAccessKey) && !empty($sesSecretKey) && !empty($awsRegion)) {
        $sesClient = new SesClient([
            'version' => 'latest',
            'region'  => $awsRegion,
            'credentials' => [
                'key'    => $sesAccessKey,
                'secret' => $sesSecretKey,
            ],
        ]);
         error_log("SES Client Initialized successfully.");
    } else {
        error_log("SES credentials or region missing in .env file. SES client not initialized.");
        throw new Exception("SES configuration missing.");
    }

} catch (Exception $e) {
     error_log("Failed to initialize SES Client: " . $e->getMessage());
}

$s3Client = null; // Initialize
$s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
try {
    $s3AccessKey = $_ENV['AWS_S3_ACCESS_KEY_ID'] ?? $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
    $s3SecretKey = $_ENV['AWS_S3_SECRET_ACCESS_KEY'] ?? $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
    $awsRegion   = $_ENV['AWS_REGION'] ?? null;

    if (!empty($s3AccessKey) && !empty($s3SecretKey) && !empty($s3BucketName) && !empty($awsRegion)) {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $awsRegion,
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'signature_version' => 'v4'
        ]);
         error_log("S3 Client Initialized successfully for bucket: " . $s3BucketName);
    } else {
         error_log("S3 credentials, bucket name, or region missing in .env file. S3 uploads will be disabled.");
    }
} catch (Exception $e) {
    error_log("Failed to initialize S3 Client: " . $e->getMessage());
}
// --- End AWS Client Instantiation ---


// --- Controllers ---
require_once __DIR__ . '/controllers/OtpController.php';
if (isset($conn) && $conn instanceof mysqli && $sesClient instanceof SesClient) {
    $otpController = new OtpController($conn, $sesClient);
} else {
    $missingDeps = [];
    if (!isset($conn) || !$conn instanceof mysqli) $missingDeps[] = "Database connection";
    if (!isset($sesClient) || !$sesClient instanceof SesClient) $missingDeps[] = "SES client";
    error_log("Critical Error: Cannot initialize OtpController. Missing: " . implode(', ', $missingDeps));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "message" => "خطأ فادح في إعداد الخادم."], JSON_UNESCAPED_UNICODE);
    exit;
}
// --- End Controllers ---


// --- Main Routing ---
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'send_otp':
         if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
         $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
         $password = $_POST['password'] ?? '';
         if (!$email) { echo json_encode(["success" => false, "message" => "الرجاء إدخال بريد إلكتروني صالح."]); exit; }
         $passRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_\d]).{8,}$/';
         if (!preg_match($passRegex, $password)) { echo json_encode(["success" => false, "message" => "كلمة المرور لا تلبي الشروط."]); exit; }
         $_SESSION['temp_password'] = $password;
         $result = $otpController->processSendOtp($email); // OtpController handles session 'otp_email'
         if ($result['success']) { $_SESSION['email'] = $email; unset($_SESSION['verified']); } // Keep 'email' for potential fallback?
         echo json_encode($result, JSON_UNESCAPED_UNICODE); exit;

    case 'verify_otp':
         if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
         // Use 'otp_email' primarily, fallback to 'email'
         $email = $_SESSION['otp_email'] ?? $_SESSION['email'] ?? '';
         if (!$email) { echo json_encode(["success" => false, "message" => "انتهت الجلسة."]); exit; }
         // Ensure verify_otp.php uses $email correctly
         require_once __DIR__ . '/routes/verify_otp.php'; exit;

    case 'check_registration':
         if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
         $response = ["success" => false, "message" => "لم يتم التحقق أو الجلسة مفقودة."];
         // Check verified flag and presence of EITHER email session var, plus password
         if (isset($_SESSION['verified']) && $_SESSION['verified'] === true && (!empty($_SESSION['otp_email']) || !empty($_SESSION['email'])) && !empty($_SESSION['temp_password'])) {
             $response = ["success" => true, "message" => "يمكنك إكمال التسجيل."];
         } else {
              // Log why check failed for debugging
              $log_reason = [];
              if (!isset($_SESSION['verified']) || $_SESSION['verified'] !== true) $log_reason[] = "not verified";
              if (empty($_SESSION['otp_email']) && empty($_SESSION['email'])) $log_reason[] = "email missing";
              if (empty($_SESSION['temp_password'])) $log_reason[] = "password missing";
              error_log("check_registration failed: " . implode(', ', $log_reason));
         }
         echo json_encode($response, JSON_UNESCAPED_UNICODE); exit;

    case 'load_universities':
         $data = []; $success = false; $stmt = $conn->query("SELECT uni_id, uni_name FROM universities ORDER BY uni_name");
         if ($stmt) { $data = $stmt->fetch_all(MYSQLI_ASSOC); $success = true; } else { error_log("DB Error loading universities: ".$conn->error); }
         echo json_encode(["success" => $success, "data" => $data], JSON_UNESCAPED_UNICODE); exit;

    case 'load_colleges':
          $uni_id = filter_input(INPUT_GET, 'uni_id', FILTER_VALIDATE_INT); $data = []; $success = false;
          if ($uni_id > 0) { $stmt = $conn->prepare("SELECT col_id, col_name FROM colleges WHERE uni_id=? ORDER BY col_name"); if ($stmt) { $stmt->bind_param("i", $uni_id); $stmt->execute(); $result = $stmt->get_result(); $data = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); $success = true; } else { error_log("DB Prepare Error loading colleges: ".$conn->error); } }
          echo json_encode(["success" => $success, "data" => $data], JSON_UNESCAPED_UNICODE); exit;

    case 'load_departments':
         $col_id = filter_input(INPUT_GET, 'col_id', FILTER_VALIDATE_INT); $data = []; $success = false;
         if ($col_id > 0) { $stmt = $conn->prepare("SELECT dep_id, dep_name FROM departments WHERE col_id=? ORDER BY dep_name"); if ($stmt) { $stmt->bind_param("i", $col_id); $stmt->execute(); $result = $stmt->get_result(); $data = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); $success = true; } else { error_log("DB Prepare Error loading departments: ".$conn->error); } }
         echo json_encode(["success" => $success, "data" => $data], JSON_UNESCAPED_UNICODE); exit;

    // --- **** COMPLETE EXTENDED REGISTRATION WITH S3 UPLOAD & ROLE HANDLING **** ---
    case 'complete_extended_registration':
        // Ensure session is started
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // 1. Check prerequisites
        if (!isset($_SESSION['verified']) || $_SESSION['verified'] !== true) { echo json_encode(["success" => false, "message" => "لم يتم التحقق من هويتك."]); exit; }
        $email = $_SESSION['otp_email'] ?? ($_SESSION['email'] ?? '');
        if (empty($email)) { error_log("Complete registration failed: Missing session email."); echo json_encode(["success" => false, "message" => "بيانات البريد الإلكتروني للجلسة مفقودة."]); exit; }
        if (empty($_SESSION['temp_password'])) { error_log("Complete registration failed: Missing session password."); echo json_encode(["success" => false, "message" => "بيانات كلمة المرور للجلسة مفقودة."]); exit; }

        // 2. Get Form Data & Determine User Type/Role ID
        $userType = $_POST['userType'] ?? 'student';
        $roleId = ($userType === 'teacher') ? 6 : 7;

        $rawPass = $_SESSION['temp_password'];
        $hashed  = password_hash($rawPass, PASSWORD_DEFAULT);

        // Common Fields
        $firstName        = trim($_POST['firstName'] ?? '');
        $lastName         = trim($_POST['lastName'] ?? '');
        $thirdName        = trim($_POST['thirdName'] ?? '');
        // fourthName removed based on schema feedback
        $motherFirstName  = trim($_POST['motherFirstName'] ?? '');
        $motherSecondName = trim($_POST['motherSecondName'] ?? '');
        $birthday         = $_POST['birthday'] ?? '';
        $gender           = $_POST['gender'] ?? '';
        $city             = $_POST['city'] ?? '';
        $nationality      = $_POST['nationality'] ?? 'Iraq';
        $uni_id           = filter_input(INPUT_POST, 'uni_id', FILTER_VALIDATE_INT);
        $col_id           = filter_input(INPUT_POST, 'col_id', FILTER_VALIDATE_INT);
        $dep_id           = filter_input(INPUT_POST, 'dep_id', FILTER_VALIDATE_INT);

        // Conditional Fields
        $stage            = null;
        $degree           = null;
        $study_mode       = null;
        $academic_title   = null; // DB column name

        if ($userType === 'student') {
            $stage = filter_input(INPUT_POST, 'stage', FILTER_VALIDATE_INT);
            $degree = $_POST['degree'] ?? null;
            $study_mode = $_POST['study_mode'] ?? null;
        } else { // teacher
            // Get value from form field named 'academic_title'
            $academic_title = $_POST['academic_title'] ?? null;
            $stage = null;
            $degree = null;
            $study_mode = null;
        }

        // 3. Server-Side Validation
        $minAge = 17;
        $maxAge = ($userType === 'teacher') ? 65 : 55;
        if (!checkBirthday($birthday, $minAge, $maxAge)) { echo json_encode(["success" => false, "message" => "العمر خارج المدى ({$minAge}-{$maxAge}) أو تاريخ الميلاد غير صالح."]); exit; }

        // Common required fields
        if (empty($firstName) || empty($lastName) || empty($thirdName) || empty($motherFirstName) || empty($motherSecondName) || empty($gender) || empty($city) || empty($nationality) || !$uni_id || !$col_id || !$dep_id) {
             echo json_encode(["success" => false, "message" => "الرجاء ملء جميع الحقول الشخصية والأكاديمية العامة."]); exit;
        }
        if (!in_array($gender, ['Male', 'Female'])) { echo json_encode(["success" => false, "message" => "قيمة الجنس غير صالحة."]); exit; }

        // Student specific required fields
        if ($userType === 'student') {
            if (!$stage || empty($degree) || empty($study_mode)) {
                echo json_encode(["success" => false, "message" => "الرجاء ملء حقول المرحلة والدرجة ونوع الدراسة للطالب."]); exit;
            }
        }

        // Teacher specific required fields
        if ($userType === 'teacher') {
            if (empty($academic_title)) { // Check the correct variable
                echo json_encode(["success" => false, "message" => "الرجاء اختيار اللقب العلمي للتدريسي."]); exit;
            }
        }

        // 4. Check for existing user by name (fourthName removed)
        $stmtCheck = $conn->prepare("SELECT id FROM users WHERE first_name=? AND last_name=? AND third_name=? AND mother_first_name=? AND mother_second_name=? LIMIT 1");
        if(!$stmtCheck) { error_log("Prepare failed (check user): ".$conn->error); echo json_encode(["success" => false, "message" => "خطأ في قاعدة البيانات (CU)."]); exit; }
        $stmtCheck->bind_param("sssss", $firstName, $lastName, $thirdName, $motherFirstName, $motherSecondName); // Removed one 's'
        $stmtCheck->execute(); $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) { echo json_encode(["success" => false, "message" => "يوجد مستخدم مسجل بنفس الاسم واسم الأم."]); $stmtCheck->close(); exit; }
        $stmtCheck->close();

        // 5. Validate Uploaded Files (Conditional Uni ID Back)
        // Helper function definition
        if (!function_exists('validate_uploaded_file')) {
             function validate_uploaded_file($fileInputName, $allowedMimeTypes, $maxFileSize, $isRequired = true) {
                 if (!isset($_FILES[$fileInputName])) { return $isRequired ? ["success" => false, "message" => "الملف المطلوب '$fileInputName' مفقود."] : ["success" => true, "data" => null]; } $file = $_FILES[$fileInputName];
                 if ($file['error'] !== UPLOAD_ERR_OK) { if ($file['error'] === UPLOAD_ERR_NO_FILE) { return $isRequired ? ["success" => false, "message" => "الرجاء رفع الملف المطلوب '$fileInputName'."] : ["success" => true, "data" => null]; } $uploadErrors = [ UPLOAD_ERR_INI_SIZE => 'الملف أكبر من الحد المسموح به في الخادم.', UPLOAD_ERR_FORM_SIZE => 'الملف أكبر من الحد المسموح به في النموذج.', UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط.', UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة مفقود.', UPLOAD_ERR_CANT_WRITE => 'فشل في كتابة الملف على القرص.', UPLOAD_ERR_EXTENSION => 'امتداد PHP أوقف رفع الملف.', ]; $errorMsg = $uploadErrors[$file['error']] ?? "خطأ غير معروف ($fileInputName) - رمز: " . $file['error']; error_log("Upload error for {$fileInputName}: Code " . $file['error']); return ["success" => false, "message" => $errorMsg]; }
                 if ($file['size'] > $maxFileSize) return ["success" => false, "message" => "حجم الملف '$fileInputName' كبير جداً (Max: " . ($maxFileSize / 1024 / 1024) . "MB)."]; if ($file['size'] === 0) return ["success" => false, "message" => "الملف '$fileInputName' فارغ."];
                 if (!function_exists('finfo_open')) { error_log("finfo not enabled"); return ["success" => false, "message" => "خطأ إعداد خادم الملفات."]; }
                 $finfo = finfo_open(FILEINFO_MIME_TYPE); $mimeType = finfo_file($finfo, $file['tmp_name']); finfo_close($finfo);
                 if (!in_array($mimeType, $allowedMimeTypes)) { error_log("Invalid MIME type '{$mimeType}' for {$fileInputName}. Allowed: " . implode(',', $allowedMimeTypes)); return ["success" => false, "message" => "نوع الملف '$fileInputName' غير مسموح به."]; }
                 if (strpos($mimeType, 'image/') === 0) { $imgSize = @getimagesize($file['tmp_name']); if ($imgSize === false && $mimeType !== 'image/webp') { error_log("getimagesize failed for non-webp image {$fileInputName}"); return ["success" => false, "message" => "ملف الصورة '$fileInputName' غير صالح أو تالف."]; } }
                 return ["success" => true, "data" => [ 'tmp_path' => $file['tmp_name'], 'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), 'mime' => $mimeType ]];
             }
         }

        $profilePicDetails = null; $uniIdFrontDetails = null; $uniIdBackDetails = null; $natIdFrontDetails = null; $natIdBackDetails = null;
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        $imageMimes = ['image/jpeg', 'image/png'];

        $profilePicResult = validate_uploaded_file('profile_pic', $imageMimes, $maxFileSize, true);
        if (!$profilePicResult['success']) { echo json_encode($profilePicResult); exit; } $profilePicDetails = $profilePicResult['data'];

        $uniIdFrontResult = validate_uploaded_file('uni_id_front', $imageMimes, $maxFileSize, true);
        if (!$uniIdFrontResult['success']) { echo json_encode($uniIdFrontResult); exit; } $uniIdFrontDetails = $uniIdFrontResult['data'];

        $isUniIdBackRequired = ($userType === 'student');
        $uniIdBackResult = validate_uploaded_file('uni_id_back', $imageMimes, $maxFileSize, $isUniIdBackRequired);
        if (!$uniIdBackResult['success']) { echo json_encode($uniIdBackResult); exit; }
        $uniIdBackDetails = $uniIdBackResult['data'];

        $natIdFrontResult = validate_uploaded_file('national_id_front', $imageMimes, $maxFileSize, true);
        if (!$natIdFrontResult['success']) { echo json_encode($natIdFrontResult); exit; } $natIdFrontDetails = $natIdFrontResult['data'];

        $natIdBackResult = validate_uploaded_file('national_id_back', $imageMimes, $maxFileSize, true);
        if (!$natIdBackResult['success']) { echo json_encode($natIdBackResult); exit; } $natIdBackDetails = $natIdBackResult['data'];

        $uploadsNeeded = $profilePicDetails || $uniIdFrontDetails || $uniIdBackDetails || $natIdFrontDetails || $natIdBackDetails;
        if ($uploadsNeeded && $s3Client === null) { error_log("S3 client not configured, but file uploads are present."); echo json_encode(["success" => false, "message" => "خطأ في إعداد خدمة تخزين الملفات."]); exit; }

        // 6. Database Transaction
        $conn->begin_transaction();
        $newUserId = 0;
        $s3ProfileKey = null; $s3UniFrontKey = null; $s3UniBackKey = null; $s3NatFrontKey = null; $s3NatBackKey = null;

        try {
            // A. Insert User
            
            $sqlInsertUser = "INSERT INTO users (
                                first_name, last_name, third_name,
                                mother_first_name, mother_second_name, birthday, gender, city, nationality,
                                uni_id, col_id, dep_id,
                                stage, degree, study_mode, academic_title, -- Conditional fields
                                profile_pic, user_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'temp')"; // 17 placeholders

            $stmtUser = $conn->prepare($sqlInsertUser);
            if(!$stmtUser) throw new Exception("DB Error (UI Prep): " . $conn->error);

            // Updated parameter binding
            // sss ss s s s s   iii   i s s s   s  -> Total 17
            $stmtUser->bind_param("sssssssssiiiisss",
                $firstName, $lastName, $thirdName,
                $motherFirstName, $motherSecondName, $birthday, $gender, $city, $nationality,
                $uni_id, $col_id, $dep_id,
                $stage, $degree, $study_mode, $academic_title // Use correct variable for academic title
            );

            if (!$stmtUser->execute()) throw new Exception("DB Error (UE): " . $stmtUser->error . " | SQL: " . $sqlInsertUser);
            $newUserId = $conn->insert_id; $stmtUser->close();
            if ($newUserId <= 0) throw new Exception("Failed to get new user ID.");
            error_log("[Tx] User inserted ID: " . $newUserId . " (Type: {$userType})");

            // B. Define upload_to_s3 helper function
            if (!function_exists('upload_to_s3')) {
                function upload_to_s3($s3Client, $bucket, $userId, $prefix, $fileDetails) {
                    if (!$fileDetails || !$s3Client || !$bucket || !$userId) return null;
                    $key = $prefix . "/" . $userId . "/" . bin2hex(random_bytes(8)) . "." . $fileDetails['extension'];
                    error_log("[Tx] Attempting S3 upload for user {$userId} to key: {$key}");
                    try { $s3Client->putObject(['Bucket' => $bucket, 'Key' => $key, 'SourceFile' => $fileDetails['tmp_path'], 'ContentType'=> $fileDetails['mime']]); error_log("[Tx] S3 upload successful for user {$userId}. Key: {$key}"); return $key;
                    } catch (AwsException $e) { error_log("[Tx] S3 Upload Error for user {$userId}, key {$key}: " . $e->getMessage()); throw new Exception("فشل تحميل الملف ({$prefix}). Error: " . $e->getAwsErrorMessage()); }
                }
            }

            // C. Upload profile pic & Update user
            $s3ProfileKey = upload_to_s3($s3Client, $s3BucketName, $newUserId, 'profile_pictures', $profilePicDetails);
            if ($s3ProfileKey !== null) {
                $stmtUpdatePic = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                if(!$stmtUpdatePic) throw new Exception("DB Error (UPP): " . $conn->error);
                $stmtUpdatePic->bind_param("si", $s3ProfileKey, $newUserId);
                if (!$stmtUpdatePic->execute()) throw new Exception("DB Error (UPE): " . $stmtUpdatePic->error);
                $stmtUpdatePic->close(); error_log("[Tx] Updated user {$newUserId} profile_pic.");
            } else {
                 throw new Exception("فشل تحميل صورة الملف الشخصي المطلوبة.");
            }

            // D. Upload document files
            $s3UniFrontKey = upload_to_s3($s3Client, $s3BucketName, $newUserId, 'user_documents/uni_id', $uniIdFrontDetails);
            $s3UniBackKey = upload_to_s3($s3Client, $s3BucketName, $newUserId, 'user_documents/uni_id', $uniIdBackDetails); // Might be null for teachers
            $s3NatFrontKey = upload_to_s3($s3Client, $s3BucketName, $newUserId, 'user_documents/national_id', $natIdFrontDetails);
            $s3NatBackKey = upload_to_s3($s3Client, $s3BucketName, $newUserId, 'user_documents/national_id', $natIdBackDetails);

            // E. Insert into user_documents table (Validate mandatory keys)
            if (!$s3UniFrontKey || !$s3NatFrontKey || !$s3NatBackKey) {
                throw new Exception("فشل تحميل واحد أو أكثر من المستندات المطلوبة (غير صورة هوية الجامعة الخلفية).");
            }
            if ($userType === 'student' && !$s3UniBackKey) {
                 throw new Exception("فشل تحميل الوجه الخلفي لهوية الجامعة (مطلوب للطالب).");
            }

            $stmtDocs = $conn->prepare("INSERT INTO user_documents (user_id, uni_id_front, uni_id_back, iraqi_id_front, iraqi_id_back) VALUES (?, ?, ?, ?, ?)");
            if(!$stmtDocs) throw new Exception("DB Error (UDI): " . $conn->error);
            $stmtDocs->bind_param("issss", $newUserId, $s3UniFrontKey, $s3UniBackKey, $s3NatFrontKey, $s3NatBackKey);
            if (!$stmtDocs->execute()) throw new Exception("DB Error (UDE): " . $stmtDocs->error);
            $stmtDocs->close(); error_log("[Tx] Inserted document keys for user {$newUserId}");

            // F. Insert Credentials (Using the correct roleId)
            $stmtCred = $conn->prepare("SELECT record_id FROM user_credentials WHERE Email=? LIMIT 1");
            if(!$stmtCred) throw new Exception("DB Error (UCC): " . $conn->error);
            $stmtCred->bind_param("s", $email); $stmtCred->execute(); $stmtCred->store_result();
            $credentialsExist = $stmtCred->num_rows > 0; $stmtCred->close();

            if (!$credentialsExist) {
                $stmtCred2 = $conn->prepare("INSERT INTO user_credentials (u_id, Email, Password, role_id) VALUES (?, ?, ?, ?)");
                if(!$stmtCred2) throw new Exception("DB Error (UCI): " . $conn->error);
                $stmtCred2->bind_param("issi", $newUserId, $email, $hashed, $roleId);
                if (!$stmtCred2->execute()) { $error = $stmtCred2->error; $stmtCred2->close(); throw new Exception("DB Error (UCE): " . $error); }
                $stmtCred2->close(); error_log("[Tx] Inserted credentials for new user {$newUserId} with Role ID: {$roleId}");
            } else {
                error_log("[Tx Warning] Credentials already exist for {$email}. Registration flow might be inconsistent.");
                // This shouldn't happen if OtpController works correctly, but log it.
                // Maybe rollback the transaction here?
                // throw new Exception("البريد الإلكتروني مسجل مسبقاً في بيانات الاعتماد.");
            }

            // G. Commit Transaction
            $conn->commit();
            error_log("Transaction committed for user {$newUserId}, email {$email}.");

            // Clear sensitive session data AFTER successful commit
            unset($_SESSION['temp_password'], $_SESSION['verified'], $_SESSION['otp_email'], $_SESSION['email']);

            echo json_encode(["success" => true, "message" => "تم إكمال تسجيل بياناتك ورفع المستندات بنجاح. حسابك قيد المراجعة."]);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("[Tx Rollback] Transaction rolled back for email {$email}. Reason: " . $e->getMessage());
            $userMessage = $e->getMessage();
            // Mask internal errors for the user
            if (strpos($userMessage, "DB Error") === 0 || strpos($userMessage, "S3 Upload Error") === 0 || strpos($userMessage, "Failed to get new user ID") === 0 || strpos($userMessage, "فشل تحميل") === 0) {
                $userMessage = "حدث خطأ فني أثناء حفظ البيانات أو رفع الملفات.";
            }
            echo json_encode(["success" => false, "message" => "حدث خطأ أثناء إكمال التسجيل: " . $userMessage]);
        }
        exit;
    // --- **** END COMPLETE EXTENDED REGISTRATION **** ---


    default:
        echo json_encode(["success" => false, "message" => "إجراء غير صالح."]);
        exit;
} // End Switch

// --- Utility Functions ---
function checkBirthday($dob, $minAge, $maxAge) {
    if (!$dob) return false;
    try {
         $today = new DateTime();
         $birthDate = new DateTime($dob);
         if ($birthDate > $today) return false; // Cannot be born in the future
         $age = $today->diff($birthDate)->y;
         return ($age >= $minAge && $age <= $maxAge);
    } catch (Exception $e) {
        error_log("Error in checkBirthday parsing '$dob': " . $e->getMessage());
        return false;
    }
}
// --- End Utility Functions ---

// Close DB connection if still open (good practice)
if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) {
    $conn->close();
}
?>