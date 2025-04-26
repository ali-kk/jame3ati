<?php
// frontend/assignments.php - Display All Relevant Assignments

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// --- SECURITY CHECK ---
$userId = $_SESSION['user_id'] ?? null; $userRoleId = $_SESSION['role_id'] ?? null; $userStatusSession = $_SESSION['user_status'] ?? null; $isAuthorized = false;
if ($userId && $userRoleId === 7 && $userStatusSession === 'perm') {
    require_once __DIR__ . '/../backend/config/db.php'; if (isset($conn) && $conn instanceof mysqli) { try { $stmtStatus = $conn->prepare("SELECT user_status FROM users WHERE id = ?"); if($stmtStatus instanceof mysqli_stmt) { $stmtStatus->bind_param("i", $userId); if($stmtStatus->execute()) { $resultStatus = $stmtStatus->get_result(); if ($resultStatus->num_rows > 0) { $userDb = $resultStatus->fetch_assoc(); if ($userDb['user_status'] === 'perm') { $isAuthorized = true; } } } $stmtStatus->close(); } } catch (Exception $e) {} if (method_exists($conn, 'close')) { $conn->close(); } } else { error_log("DB connection failed for status check in assignments.php"); }
}
if (!$isAuthorized) { header('Location: login.html'); exit; }
// --- END SECURITY CHECK ---
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الواجبات الدراسية - جامعتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/home.css" rel="stylesheet"> 
    <link href="assets/css/assignments.css" rel="stylesheet">
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
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="assignments.php">الواجبات</a></li>
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

    <main class="main-content content-area container mt-4">
    <h2>الواجبات الدراسية</h2>

    <div id="assignmentsMessage" class="alert d-none mb-3"></div>

    <div id="assignmentsLoading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-light">جاري تحميل الواجبات...</p>
    </div>

    <div id="assignmentsListContainer">
        </div>

</main>



    <footer class="footer mt-auto py-3 bg-dark text-light text-center">
        <div class="container"><span class="text-white-50"> 2025 منصة جامعتي. جميع الحقوق محفوظة.</span></div>
    </footer>
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
  
    <script src="assets/js/assignments.js"></script>
    <script src="assets/js/assignment-upload.js"></script>
</body>
</html>
