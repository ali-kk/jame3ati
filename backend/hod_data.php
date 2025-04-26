<?php
// backend/hod_data.php
// Fetches initial data for Head of Department Dashboard (Corrected SQL Queries)

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Dependencies & Config ---
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For Dotenv and S3 SDK
use Dotenv\Dotenv; $dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); $dotenv->load();
use Aws\S3\S3Client; use Aws\Exception\AwsException; // For S3 URLs if needed later

header('Content-Type: application/json; charset=utf-8');



// 1) Grab & cast your session values up front:
$userId      = $_SESSION['user_id']      ?? null;
$userStatus  = $_SESSION['user_status']  ?? '';
$userRoleId  = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
$userDepId   = isset($_SESSION['dep_id'])  ? (int)$_SESSION['dep_id']  : null;

$isAuthorized = false;

// 2) First, quick in‑memory check of session values:
if (
    $userId                  &&             // logged in
    $userStatus === 'perm'   &&             // permanent status
    $userRoleId  === 5       &&             // exactly role 5
    $userDepId               &&             // has a department
    require_once __DIR__ . '/config/db.php' // bring in $conn
) {
    // 3) Double‑verify against the users table
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT user_status FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (isset($row['user_status']) && $row['user_status'] === 'perm') {
                $isAuthorized = true;
            }
        }
    }
}

if (! $isAuthorized) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Access denied.',
      'logout'  => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// --- S3 Client & Helper (Keep from previous version if needed for document URLs) ---
$s3Client = null; $s3BucketName = $_ENV['AWS_S3_BUCKET'] ?? null;
$s3CourseImageDefault = '../assets/images/default-course-image.png'; // Default path
try { /* ... S3 client instantiation ... */ } catch (Exception $e) { /* ... S3 error handling ... */ }
if (!function_exists('get_presigned_s3_url')) { function get_presigned_s3_url(/* ... */){ /* ... */ } }
// --- End S3 Setup ---

// --- Pagination/Filter Parameters ---
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 5]]);
$offset = ($page - 1) * $limit;
// TODO: Add parameters for search, filter status, sort column, sort direction

// --- Response Structure ---
$response = ['success' => false, 'message' => 'Failed to load dashboard data.'];
$dashboardStats = ['students' => 0, 'teachers' => 0, 'subjects' => 0, 'materials' => 0];
// Paginated data structure
$pendingUsers = ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
$deptStudents = ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
$deptTeachers = ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
$deptSubjects = ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];

// --- Main Data Fetching ---
try {
    if (!isset($conn) || !($conn instanceof mysqli)) { require_once __DIR__ . '/config/db.php'; } // Re-ensure connection
    if (!isset($conn) || !($conn instanceof mysqli)) { throw new Exception("Database connection unavailable."); }

    // --- 1. Dashboard Stats (Corrected Queries) ---
    // Count Active Students (Role 7, Perm Status, HoD's Dept)
    $stmt = $conn->prepare("SELECT COUNT(u.id) FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND uc.role_id = 7 AND u.user_status = 'perm'");
    $stmt->bind_param("i", $userDepId); $stmt->execute(); $dashboardStats['students'] = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();

    // Count Active Teachers (Role 6, Perm Status, HoD's Dept)
    $stmt = $conn->prepare("SELECT COUNT(u.id) FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND uc.role_id = 6 AND u.user_status = 'perm'");
    $stmt->bind_param("i", $userDepId); $stmt->execute(); $dashboardStats['teachers'] = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();

    // Count Subjects (No change needed)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE dep_id = ?");
    $stmt->bind_param("i", $userDepId); $stmt->execute(); $dashboardStats['subjects'] = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();

    // Count Course Materials (Joining courses to filter by dep_id)
    $stmt = $conn->prepare("SELECT COUNT(cm.material_id) FROM course_materials cm JOIN courses c ON cm.course_id = c.course_id WHERE c.dep_id = ?");
    $stmt->bind_param("i", $userDepId); $stmt->execute(); $dashboardStats['materials'] = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();


    // --- 2. Pending Users (Query Already Correct) ---
    $sqlPendingCount = "SELECT COUNT(u.id) FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND u.user_status = 'temp' AND uc.role_id IN (6, 7)";
    $stmtCount = $conn->prepare($sqlPendingCount); $stmtCount->bind_param("i", $userDepId); $stmtCount->execute(); $pendingUsers['total'] = $stmtCount->get_result()->fetch_row()[0] ?? 0; $stmtCount->close();
    if ($pendingUsers['total'] > 0) {
        $sqlPendingData = "SELECT u.id, u.first_name, u.last_name, u.third_name, uc.Email, u.birthday, u.gender, u.city, u.stage, u.degree, u.study_mode, u.academic_title, uc.role_id FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND u.user_status = 'temp' AND uc.role_id IN (6, 7) ORDER BY u.id DESC LIMIT ? OFFSET ?";
        $stmtPending = $conn->prepare($sqlPendingData); $stmtPending->bind_param("iii", $userDepId, $limit, $offset); $stmtPending->execute(); $resultPending = $stmtPending->get_result();
        while ($row = $resultPending->fetch_assoc()) { $pendingUsers['data'][] = $row; } $stmtPending->close();
    }

    // --- 3. Department Students (Query Already Correct) ---
    $sqlStudentCount = "SELECT COUNT(u.id) FROM users u JOIN user_credentials uc ON u.id=uc.u_id WHERE u.dep_id = ? AND uc.role_id = 7 AND u.user_status IN ('perm', 'frozen')";
    $stmtCount = $conn->prepare($sqlStudentCount); $stmtCount->bind_param("i", $userDepId); $stmtCount->execute(); $deptStudents['total'] = $stmtCount->get_result()->fetch_row()[0] ?? 0; $stmtCount->close();
    if($deptStudents['total'] > 0) {
        $sqlStudentData = "SELECT u.id, u.first_name, u.last_name, u.third_name, uc.Email, u.stage, u.user_status FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND uc.role_id = 7 AND u.user_status IN ('perm', 'frozen') ORDER BY u.stage, u.last_name LIMIT ? OFFSET ?";
        $stmtDeptStudents = $conn->prepare($sqlStudentData); $stmtDeptStudents->bind_param("iii", $userDepId, $limit, $offset); $stmtDeptStudents->execute(); $resultDeptStudents = $stmtDeptStudents->get_result();
        while ($row = $resultDeptStudents->fetch_assoc()) { $deptStudents['data'][] = $row; } $stmtDeptStudents->close();
    }

    // --- 4. Department Teachers (Query Already Correct) ---
    $sqlTeacherCount = "SELECT COUNT(u.id) FROM users u JOIN user_credentials uc ON u.id=uc.u_id WHERE u.dep_id = ? AND uc.role_id = 6 AND u.user_status IN ('perm', 'frozen')";
    $stmtCount = $conn->prepare($sqlTeacherCount); $stmtCount->bind_param("i", $userDepId); $stmtCount->execute(); $deptTeachers['total'] = $stmtCount->get_result()->fetch_row()[0] ?? 0; $stmtCount->close();
    if($deptTeachers['total'] > 0) {
        $sqlTeacherData = "SELECT u.id, u.first_name, u.last_name, uc.Email, u.academic_title, u.user_status FROM users u JOIN user_credentials uc ON u.id = uc.u_id WHERE u.dep_id = ? AND uc.role_id = 6 AND u.user_status IN ('perm', 'frozen') ORDER BY u.last_name LIMIT ? OFFSET ?";
        $stmtDeptTeachers = $conn->prepare($sqlTeacherData); $stmtDeptTeachers->bind_param("iii", $userDepId, $limit, $offset); $stmtDeptTeachers->execute(); $resultDeptTeachers = $stmtDeptTeachers->get_result();
        while ($row = $resultDeptTeachers->fetch_assoc()) { $deptTeachers['data'][] = $row; } $stmtDeptTeachers->close();
     }

     // --- 5. Department Subjects (Corrected Teacher Role Check) ---
     $sqlSubjectCount = "SELECT COUNT(*) FROM courses WHERE dep_id = ?";
     $stmtCount = $conn->prepare($sqlSubjectCount); $stmtCount->bind_param("i", $userDepId); $stmtCount->execute(); $deptSubjects['total'] = $stmtCount->get_result()->fetch_row()[0] ?? 0; $stmtCount->close();
     if($deptSubjects['total'] > 0) {
         // Corrected JOIN: Check role_id in user_credentials when joining users (t)
         $sqlSubjectData = "SELECT c.course_id, c.course_name, c.stage, c.semester,
                                   GROUP_CONCAT(DISTINCT CONCAT(t.first_name, ' ', t.last_name) SEPARATOR ', ') as teachers
                            FROM courses c
                            LEFT JOIN course_teachers ct ON c.course_id = ct.course_id
                            LEFT JOIN users t ON ct.teacher_user_id = t.id
                            LEFT JOIN user_credentials tc ON t.id = tc.u_id AND tc.role_id = 6  -- Check role in user_credentials
                            WHERE c.dep_id = ?
                            GROUP BY c.course_id -- Removed redundant columns from GROUP BY
                            ORDER BY c.stage, c.course_name LIMIT ? OFFSET ?";
         $stmtDeptSubjects = $conn->prepare($sqlSubjectData);
         $stmtDeptSubjects->bind_param("iii", $userDepId, $limit, $offset);
         $stmtDeptSubjects->execute();
         $resultDeptSubjects = $stmtDeptSubjects->get_result();
         while ($row = $resultDeptSubjects->fetch_assoc()) { $deptSubjects['data'][] = $row; }
         $stmtDeptSubjects->close();
     }

    // --- Final Success Response ---
    $response = [
        'success' => true,
        'dashboardStats' => $dashboardStats,
        'pendingUsers' => $pendingUsers,
        'departmentStudents' => $deptStudents,
        'departmentTeachers' => $deptTeachers,
        'departmentSubjects' => $deptSubjects
    ];

} catch (Exception $e) {
    error_log("Error fetching HoD data for UserID {$userId}, DepID {$userDepId}: " . $e->getMessage());
    $response['message'] = 'Error loading dashboard data: ' . $e->getMessage();
     http_response_code(500);
} finally {
    if (isset($conn) && $conn instanceof mysqli && method_exists($conn, 'close')) { $conn->close(); }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>