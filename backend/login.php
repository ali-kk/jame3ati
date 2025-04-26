<?php
// login.php
// Ensure session is started if needed, especially if setting session variables on success
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/db.php'; // Adjust the path as needed

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get the action parameter - ensure it's 'login' for this script
// If this script ONLY handles login, the action check might be redundant,
// but keeping it based on the provided code.
$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); // Validate email format
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (!$email) {
        echo json_encode(["success" => false, "message" => "يرجى إدخال بريد إلكتروني صالح."]);
        exit;
    }
     if (!$password) {
        echo json_encode(["success" => false, "message" => "كلمة المرور مطلوبة."]);
        exit;
    }

    // Prepare a query to find the user, password hash, status, role_id, AND other needed IDs
    // **** MODIFIED SQL: Added u.stage ****
    $stmt = $conn->prepare(
        "SELECT uc.u_id, uc.Password, u.user_status, uc.role_id, u.uni_id, u.col_id, u.dep_id, u.stage
         FROM user_credentials uc
         JOIN users u ON uc.u_id = u.id
         WHERE uc.Email = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log("Login Prepare failed: " . $conn->error); // Log DB errors
        echo json_encode(["success" => false, "message" => "خطأ في الاستعلام (DBP)." ]); // Generic error
        exit;
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Login Execute failed: " . $stmt->error);
        $stmt->close();
        echo json_encode(["success" => false, "message" => "خطأ في الاستعلام (DBE)." ]);
        exit;
    }

    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // User email not found
        echo json_encode(["success" => false, "message" => "البريد الالكتروني أو كلمة المرور غير صحيحة."]); // Keep message generic
        $stmt->close();
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close(); // Close statement after fetching

    // Verify the password
    if (!password_verify($password, $row['Password'])) {
        // Password mismatch
        echo json_encode(["success" => false, "message" => "كلمة المرور أو البريد الالكتروني غير صحيحة."]); // Keep message generic
        exit;
    }

    // --- Check Status and Role ID ---
    $status = $row['user_status'];
    $role_id = (int)$row['role_id']; // Cast role_id to integer
if($status=='perm')
{       // Login successful: Regenerate session ID for security
        // session_regenerate_id(true); // Keeping this commented as per 

        // Store necessary info in session
        $_SESSION['email'] = $email; // User's email
        $_SESSION['user_status'] = $status; // 'perm'
        $_SESSION['role_id'] = $role_id; // 7
        $_SESSION['user_id'] = (int)$row['u_id']; // The actual user ID from users table
        $_SESSION['uni_id'] = (int)$row['uni_id'];   // University ID
        $_SESSION['col_id'] = (int)$row['col_id'];   // College ID
        $_SESSION['dep_id'] = (int)$row['dep_id'];   // Department ID

        if($role_id===7)
            {
                $_SESSION['stage'] = (int)$row['stage'];     // User's Stage

                error_log("Login success for user: $email, UserID: {$_SESSION['user_id']}, Status: $status, Role: $role_id, Stage: {$_SESSION['stage']}");
                echo json_encode([
                    "success" => true,
                    "message" => "تم تسجيل الدخول بنجاح.",
                    "user_status" => "perm", // Signal to frontend JS
                    "role_id" => $role_id // Send role_id back too
                    // No need to send other IDs back, they are in session
                ]);
            }

            elseif($role_id===5)
            {
                error_log("Login success for user: $email, UserID: {$_SESSION['user_id']}, Status: $status, Role: $role_id");
                echo json_encode([
                    "success" => true,
                    "message" => "تم تسجيل الدخول بنجاح.",
                    "user_status" => "perm", // Signal to frontend JS
                    "role_id" => $role_id // Send role_id back too
                    // No need to send other IDs back, they are in session
                ]);
            }

}
   
   
    elseif ($status === 'temp') {
         error_log("Login attempt for pending user: $email");
        echo json_encode(["success" => false, "message" => "حسابك قيد الانتظار للموافقة.", "user_status" => "temp"]);
    } elseif ($status === 'rejected') {
         error_log("Login attempt for rejected user: $email");
        echo json_encode(["success" => false, "message" => "تم رفض طلبك لانشاء حساب.", "user_status" => "rejected"]);
    } elseif ($status === 'frozen') {
         error_log("Login attempt for frozen user: $email");
        echo json_encode(["success" => false, "message" => "حسابك معطل.", "user_status" => "frozen"]);
    } elseif ($status === 'perm' && $role_id !== 7) {
         error_log("Login attempt for permitted user with wrong role: $email, Role: $role_id");
         echo json_encode(["success" => false, "message" => "البريد الالكتروني أو كلمة المرور غير صحيحة."]); // Generic message
    } else {
         error_log("Login attempt for user with unknown status: $email, Status: $status, Role: $role_id");
        echo json_encode(["success" => false, "message" => "حالة الحساب غير معروفة أو غير صالحة للدخول."]);
    }
    exit; // Ensure clean exit after handling login result

} else {
    // Default response if action is not 'login'
    echo json_encode(["success" => false, "message" => "إجراء غير صالح."]);
    exit;
}

?>