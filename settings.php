<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Role-based dashboard URL
$role_id = $_SESSION['role_id'];
$rolePages = [
    '5' => 'hod_dashboard.php',
    '6' => 'teacher_dashboard.php',
    '7' => 'home.php'
];
$dashboardUrl = $rolePages[$role_id] ?? 'login.html';

// Role name for display
$roleNames = [
    '5' => 'رئيس القسم',
    '6' => 'تدريسي',
    '7' => 'طالب'
];
// Determine role name
$roleName = isset($roleNames[$role_id]) ? $roleNames[$role_id] : 'مستخدم';
// Fetch user data for navbar and current email
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare("SELECT uc.Email AS email, us.profile_pic, us.first_name, us.last_name, us.third_name FROM user_credentials uc JOIN users us ON uc.u_id = us.id WHERE uc.u_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$profilePicUrl = !empty($user['profile_pic']) ? (isset($_ENV['AWS_S3_BASE_URL']) ? rtrim($_ENV['AWS_S3_BASE_URL'], '/') . '/' . $user['profile_pic'] : 'assets/images/3.jpg') : 'assets/images/3.jpg';
$fullName = htmlspecialchars(trim("{$user['first_name']} {$user['last_name']} {$user['third_name']}"), ENT_QUOTES, 'UTF-8');
$currentEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>الإعدادات - جامعتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/hod_dashboard.css" rel="stylesheet">
    <link href="assets/css/settings.css" rel="stylesheet">
</head>
<body class="hod-body">
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <div class="container-fluid d-flex align-items-center">
    <div>
    <a class="navbar-brand me-auto d-flex align-items-center">
      <img src="assets/images/logo2.png" alt="Logo" height="40" class="me-3">
      <span class="brand-text">منصة جامعتي</span>
    </a>
    </div>
   
    <div>
    <ul class="navbar-nav ms-auto profile-nav">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="<?= $profilePicUrl ?>" alt="User pic" class="profile-pic-nav" id="userProfilePicNav">
          <span class="username" id="userNameNav"><?= $fullName ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="personal_info.php">الملف الشخصي<i class="fas fa-user me-5"></i></a></li>
          <li><a class="dropdown-item active" href="settings.php">الإعدادات<i class="fas fa-cog me-5"></i></a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="../backend/logout.php">تسجيل الخروج<i class="fas fa-sign-out-alt me-5"></i></a></li>
        </ul>
      </li>
    </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 pt-4">
    <div class="row">
        <div class="col-12">
          
            
            <div id="messageArea" class="alert d-none mb-4"></div>
            
            <!-- Change Email Section -->
            <div class="settings-section">
                <div class="d-flex align-items-center settings-header">
                    <div class="settings-icon">
                        <i class="fas fa-envelope fa-lg"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">تغيير البريد الإلكتروني</h4>
                        <p class="text-muted mb-0">قم بتحديث عنوان البريد الإلكتروني المرتبط بحسابك</p>
                    </div>
                </div>
                
                <!-- Email Change Form -->
                <form id="emailChangeForm" action="javascript:void(0);" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="currentEmail" class="form-label">البريد الإلكتروني الحالي</label>
                        <input type="email" class="form-control" id="currentEmail" readonly value="<?= $currentEmail ?>">
                    </div>
                    <div class="mb-3">
                        <label for="newEmail" class="form-label">البريد الإلكتروني الجديد</label>
                        <input type="email" class="form-control" id="newEmail" name="new_email" required>
                        <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="invalid-feedback">يرجى إدخال كلمة المرور.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="changeEmailBtn">تغيير البريد الإلكتروني</button>
                </form>
                
                <!-- Email OTP Verification Section -->
                <div id="emailVerificationSection" class="verification-section mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="emailVerificationMessage">تم إرسال رمز التحقق إلى بريدك الإلكتروني الحالي. يرجى إدخال الرمز المكون من 6 أرقام.</span>
                    </div>
                    
                    <form id="emailOtpForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label text-center d-block">رمز التحقق</label>
                            <div class="otp-inputs">
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="emailOtpComplete" name="otp_code">
                            <div class="text-center mt-3">
                                <span>لم يصلك الرمز؟ </span>
                                <button type="button" class="btn btn-link p-0" id="resendEmailOtp" disabled>إعادة الإرسال</button>
                                <span id="emailOtpCountdown" class="countdown ms-1">(2:00)</span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="verifyEmailOtpBtn">تأكيد</button>
                        <button type="button" class="btn btn-secondary" id="cancelEmailChangeBtn">إلغاء</button>
                    </form>
                </div>
                
                <!-- New Email OTP Verification Section -->
                <div id="newEmailVerificationSection" class="verification-section mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="newEmailVerificationMessage">تم إرسال رمز التحقق إلى بريدك الإلكتروني الجديد. يرجى إدخال الرمز المكون من 6 أرقام.</span>
                    </div>
                    
                    <form id="newEmailOtpForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label text-center d-block">رمز التحقق</label>
                            <div class="otp-inputs">
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="newEmailOtpComplete" name="otp_code">
                            <div class="text-center mt-3">
                                <span>لم يصلك الرمز؟ </span>
                                <button type="button" class="btn btn-link p-0" id="resendNewEmailOtp" disabled>إعادة الإرسال</button>
                                <span id="newEmailOtpCountdown" class="countdown ms-1">(2:00)</span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="verifyNewEmailOtpBtn">تأكيد</button>
                        <button type="button" class="btn btn-secondary" id="cancelNewEmailChangeBtn">إلغاء</button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Section -->
            <div class="settings-section">
                <div class="d-flex align-items-center settings-header">
                    <div class="settings-icon">
                        <i class="fas fa-lock fa-lg"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">تغيير كلمة المرور</h4>
                        <p class="text-muted mb-0">قم بتحديث كلمة المرور الخاصة بك بشكل دوري للحفاظ على أمان حسابك</p>
                    </div>
                </div>
                
                <!-- Password Change Form -->
                <form id="passwordChangeForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">كلمة المرور الحالية</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="invalid-feedback">يرجى إدخال كلمة المرور الحالية.</div>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">كلمة المرور الجديدة</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="8">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="form-text">يجب أن تتكون كلمة المرور من 8 أحرف على الأقل وتحتوي على حروف كبيرة وصغيرة وأرقام ورموز.</div>
                        <div class="invalid-feedback">يرجى إدخال كلمة مرور قوية (8 أحرف على الأقل).</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="invalid-feedback">كلمات المرور غير متطابقة.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="changePasswordBtn">تغيير كلمة المرور</button>
                </form>
                
                <!-- Password OTP Verification Section -->
                <div id="passwordVerificationSection" class="verification-section mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="passwordVerificationMessage">تم إرسال رمز التحقق إلى بريدك الإلكتروني. يرجى إدخال الرمز المكون من 6 أرقام.</span>
                    </div>
                    
                    <form id="passwordOtpForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label text-center d-block">رمز التحقق</label>
                            <div class="otp-inputs">
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                                <input type="text" class="form-control otp-input" maxlength="1" pattern="[0-9]" required>
                            </div>
                            <input type="hidden" id="passwordOtpComplete" name="otp_code">
                            <div class="text-center mt-3">
                                <span>لم يصلك الرمز؟ </span>
                                <button type="button" class="btn btn-link p-0" id="resendPasswordOtp" disabled>إعادة الإرسال</button>
                                <span id="passwordOtpCountdown" class="countdown ms-1">(2:00)</span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="verifyPasswordOtpBtn">تأكيد</button>
                        <button type="button" class="btn btn-secondary" id="cancelPasswordChangeBtn">إلغاء</button>
                    </form>
                </div>
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
<script src="assets/js/settings.js"></script>
<script>
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.getAttribute('data-target'));
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        btn.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
});
</script>
</body>
</html>
