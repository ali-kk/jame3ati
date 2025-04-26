<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Get user role
$role_id = $_SESSION['role_id'];
$rolePages = [
    '5' => 'hod_dashboard.php',    // HOD
    '6' => 'teacher_dashboard.php', // Teacher
    '7' => 'home.php'              // Student
];

// Determine dashboard URL based on role
$dashboardUrl = isset($rolePages[$role_id]) ? $rolePages[$role_id] : 'login.html';

// Get role name for display
$roleNames = [
    '5' => 'رئيس القسم',
    '6' => 'تدريسي',
    '7' => 'طالب'
];
$roleName = isset($roleNames[$role_id]) ? $roleNames[$role_id] : 'مستخدم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>الملف الشخصي - جامعتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/hod_dashboard.css" rel="stylesheet">
    <link href="assets/css/personal_info.css" rel="stylesheet">
</head>
<body class="hod-body">
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <div class="container-fluid d-flex align-items-center">
    <div>
    <a class="navbar-brand me-auto d-flex align-items-center" href="<?php echo $dashboardUrl; ?>">
      <img src="assets/images/logo2.png" alt="Logo" height="40" class="me-3">
      <span class="brand-text">منصة جامعتي</span>
    </a>
    </div>
   
    <div>
    <ul class="navbar-nav ms-auto profile-nav">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="assets/images/3.jpg" alt="User pic" class="profile-pic-nav" id="userProfilePicNav">
          <span class="username" id="userNameNav">المستخدم</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item active" href="personal_info.php">الملف الشخصي<i class="fas fa-user ms-3"></i></a></li>
          <li><a class="dropdown-item" href="settings.php">الإعدادات<i class="fas fa-cog ms-3"></i></a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="../backend/logout.php">تسجيل الخروج<i class="fas fa-sign-out-alt ms-3"></i></a></li>
        </ul>
      </li>
    </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 pt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $dashboardUrl; ?>">الرئيسية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">الملف الشخصي</li>
                </ol>
            </nav>
            
            <div id="messageArea" class="alert d-none mb-4"></div>
            
            <div class="profile-section">
                <div class="profile-header">
                    <div class="profile-pic-container">
                        <img src="assets/images/3.jpg" alt="صورة الملف الشخصي" class="profile-pic" id="userProfilePic">
                        <div class="profile-pic-edit" id="editProfilePic" title="تغيير الصورة">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h2 id="userName">اسم المستخدم</h2>
                        <span class="badge bg-primary mb-3" id="userRole"><?php echo htmlspecialchars($roleName); ?></span>
                        <p class="text-muted" id="userEmail">email@example.com</p>
                    </div>
                </div>
                
                <h4 class="mb-4">المعلومات الشخصية</h4>
                
                <div class="row info-row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">الاسم الأول</div>
                        <div id="firstName">-</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">اسم الأب</div>
                        <div id="lastName">-</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">اسم الجد</div>
                        <div id="thirdName">-</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">الجنس</div>
                        <div id="gender">-</div>
                    </div>
                </div>
                
                <div class="row info-row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">تاريخ الميلاد</div>
                        <div id="birthday">-</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">المدينة</div>
                        <div id="city">-</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">الجنسية</div>
                        <div id="nationality">-</div>
                    </div>
                </div>
                
                <div class="row info-row" id="academicInfoRow">
                    <!-- This section will be populated based on user role -->
                </div>
            </div>
            
            <?php if ($role_id === '5'): ?>
            <div class="profile-section" id="documentsSection">
                <h4 class="mb-4">المستمسكات</h4>
                <div class="row" id="documentsContainer">
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-3">جاري تحميل المستمسكات...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Picture Upload Modal -->
<div class="modal fade" id="profilePicModal" tabindex="-1" aria-labelledby="profilePicModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h5 class="modal-title" id="profilePicModalLabel">تغيير صورة الملف الشخصي</h5>
            </div>
            <div class="modal-body">
                <form id="profilePicForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profilePicFile" class="form-label">اختر صورة جديدة</label>
                        <input class="form-control" type="file" id="profilePicFile" name="profile_pic" accept="image/*" required>
                        <div class="form-text">يجب أن تكون الصورة بصيغة JPG، PNG، أو GIF وحجمها أقل من 2 ميجابايت.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">معاينة</label>
                        <div class="text-center">
                            <img id="profilePicPreview" src="#" alt="معاينة الصورة" style="max-width: 100%; max-height: 200px; display: none;">
                            <div id="previewPlaceholder" class="border rounded p-5 text-center text-muted">
                                <i class="fas fa-image fa-3x mb-3"></i>
                                <p>سيتم عرض معاينة الصورة هنا</p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                </form>
                <div id="uploadProgress" class="progress mb-3" style="display: none;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div id="uploadMessage" class="alert d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="saveProfilePic">حفظ الصورة</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-dark text-white">
    <div class="container text-center">
        <div class="row">
            <div class="col-md-12">
                <p class="mb-0">منصة جامعتي - جميع الحقوق محفوظة <?php echo date('Y'); ?></p>
                <p class="small text-muted mb-0">مركز الحاسبة الالكترونية-جامعة كركوك</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/personal_info.js"></script>
</body>
</html>
