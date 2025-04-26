<?php
// frontend/subject_details.php - Secure Subject Detail Page

// Start the session to access session variables
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- SECURITY CHECK ---
$userId = $_SESSION['user_id'] ?? null;
$userRoleId = $_SESSION['role_id'] ?? null;
$userStatusSession = $_SESSION['user_status'] ?? null;
$isAuthorized = false;

// 1. Basic session check
if ($userId && $userRoleId === 7 && $userStatusSession === 'perm') {
    // 2. Verify user status against DB
    require_once __DIR__ . '/../backend/config/db.php'; // Adjust path to db.php
    if (isset($conn) && $conn instanceof mysqli) { // Check if connection is valid
        try {
            $stmtStatus = $conn->prepare("SELECT user_status FROM users WHERE id = ?");
            if($stmtStatus instanceof mysqli_stmt) {
                $stmtStatus->bind_param("i", $userId);
                if($stmtStatus->execute()) {
                    $resultStatus = $stmtStatus->get_result();
                    if ($resultStatus->num_rows > 0) {
                        $userDb = $resultStatus->fetch_assoc();
                        if ($userDb['user_status'] === 'perm') {
                            $isAuthorized = true; // All checks passed
                        } else { error_log("Status check failed for user {$userId} on subject_details.php: DB status is '{$userDb['user_status']}'"); }
                    } else { error_log("User ID {$userId} from session not found in DB during subject_details.php check."); }
                } else { error_log("Status check execute failed: ".$stmtStatus->error); }
                $stmtStatus->close();
            } else { error_log("Status check prepare failed: ".$conn->error); }
        } catch (Exception $e) { error_log("Exception during status check in subject_details.php: ".$e->getMessage()); }
        // Close connection if it was opened here
        if (method_exists($conn, 'close')) { $conn->close(); }
    } else { error_log("DB connection not available or invalid for status check in subject_details.php"); }
}

// Redirect if not authorized
if (!$isAuthorized) {
    error_log("Unauthorized access attempt to subject_details.php. Session: " . print_r($_SESSION, true));
    header('Location: login.html'); // Adjust path if needed
    exit; // Stop script execution immediately
}
// --- END SECURITY CHECK ---

// User is authorized, proceed to render HTML
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل المادة - جامعتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/home.css" rel="stylesheet"> 
    <link href="assets/css/subject_details.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top home-navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center home-brand" href="home.php">
                <img src="assets/images/logo2.png" alt="شعار جامعتي" class="logo-img">
                <span class="brand-text">منصة جامعتي</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavContent" aria-controls="navbarNavContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNavContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 centered-nav-links">
                    <li class="nav-item"><a class="nav-link" href="home.php">المواد</a></li>
                    <li class="nav-item"><a class="nav-link" href="assignments.php">الواجبات</a></li>
                    <li class="nav-item"><a class="nav-link" href="chatbot/chatbot.php">المساعد الآلي</a></li>
                </ul>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 profile-nav">
                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="assets/images/placeholder-profile.png" alt="صورة المستخدم" class="profile-pic-nav" id="userProfilePicNav">
                            <span class="d-none d-lg-inline text-white ms-2" id="userNameNav">...</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="personal_info.php">الملف الشخصي<i class="fas fa-user me-5"></i></a></li>
                            <li><a class="dropdown-item" href="settings.php">الإعدادات<i class="fas fa-cog me-5"></i></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" id="logoutButton" href="../backend/logout.php">تسجيل الخروج<i class="fas fa-sign-out-alt me-5"></i></a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content content-area">
        <div class="container">
            <div id="courseDetailMessageArea" class="alert d-none mb-4"></div>

           
             <div id="headerLoadingIndicator" class="text-center py-5">
                 <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
                     <span class="visually-hidden">Loading...</span>
                 </div>
                 <p class="mt-3 text-light">جاري تحميل بيانات المادة...</p>
             </div>

         
            <div id="courseDetailHeader" class="course-details-header" style="display: none;">
                <div class="course-header-image">
                    <img src="assets/images/default-course-image.png" alt="صورة المادة" id="courseImage">
                </div>
                <div class="course-header-info">
                    <h1 id="courseTitle" class="mb-2"></h1>
                    <div class="teacher-info mb-3">
                        <img src="assets/images/placeholder-profile.png" alt="صورة المدرس" id="teacherProfilePic">
                        <div class="teacher-details" id="teacherInfo">
                            <span class="teacher-title" id="teacherTitle"></span>
                            <span class="teacher-name" id="teacherName"></span>
                        </div>
                    </div>
                    <p class="course-description lead" id="courseDescription"></p>
                </div>
            </div>

            <div id="courseTabsSection" class="course-tabs mt-4" style="display: none;">
                <ul class="nav nav-tabs" id="courseTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="files-tab" data-bs-toggle="tab" data-bs-target="#filesContent" type="button" role="tab" aria-controls="filesContent" aria-selected="true">
                           <i class="fas fa-folder-open me-1"></i> الملفات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videosContent" type="button" role="tab" aria-controls="videosContent" aria-selected="false">
                           <i class="fas fa-video me-1"></i> الفيديوهات
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignmentsContent" type="button" role="tab" aria-controls="assignmentsContent" aria-selected="false">
                           <i class="fas fa-clipboard-list me-1"></i> الواجبات
                        </button>
                    </li>
                </ul>
                <div class="tab-content pt-3" id="courseTabContent">
                  
                    <div class="tab-pane fade show active" id="filesContent" role="tabpanel" aria-labelledby="files-tab">
                      
                        <p class="text-secondary p-3 text-center">جاري تحميل الملفات...</p>
                    </div>
                
                    <div class="tab-pane fade" id="videosContent" role="tabpanel" aria-labelledby="videos-tab">
                       
                         <p class="text-secondary p-3 text-center">جاري تحميل الفيديوهات...</p>
                    </div>
                    <div class="tab-pane fade" id="assignmentsContent" role="tabpanel" aria-labelledby="assignments-tab">
      
                      <p class="text-secondary p-3 text-center">جاري تحميل الواجبات...</p>
        
                     </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="footer mt-auto py-3 bg-dark text-light text-center">
        <div class="container"><span class="text-white-50"> 2025 منصة جامعتي. جميع الحقوق محفوظة.</span></div>
    </footer>

    <div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-labelledby="videoPlayerModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="videoPlayerModalTitle">Video Player</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-right: 75%;"></button>
                </div>
                <div class="modal-body p-0" id="videoPlayerModalBody">
                   
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade text-dark" id="assignmentUploadModal" tabindex="-1" aria-labelledby="assignmentUploadModalLabel" aria-hidden="true" dir="rtl">
  <div class="modal-dialog">
    <div class="modal-content bg-light">
      <form id="assignmentUploadForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="assignmentUploadModalLabel">تسليم الواجب</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>الواجب: <strong id="modalAssignmentTitle">اسم الواجب</strong></p>
          <input type="hidden" name="assignmentId" id="modalAssignmentId">

          <div class="mb-3">
            <label for="submissionFile" class="form-label">اختر الملف:</label>
            <input class="form-control" type="file" id="submissionFile" name="submissionFile" required accept=".jpg, .jpeg, .png, .gif, .webp, .pdf, .doc, .docx, .xls, .xlsx, .ppt, .pptx">
            <div class="form-text">
                الملفات المسموحة: صور, PDF, Word, Excel, PowerPoint. الحد الأقصى: 10MB.
            </div>
          </div>
           <div id="uploadProgress" class="progress mb-3" style="display: none;">
               <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
           </div>
          <div id="uploadResultMessage" class="alert d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-primary" id="uploadSubmitButton">
                <i class="fas fa-upload me-1"></i> رفع الملف
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/subject_details.js"></script> 
    <script src="assets/js/assignment-upload.js"></script>
</body>
</html>
