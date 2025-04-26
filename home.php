<?php
// home.php - Secure Student Home Page
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_status']) || $_SESSION['user_status'] !== 'perm') { header('Location: login.html'); exit; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الطالب - جامعتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/home.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top home-navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center home-brand" href="#"><img src="assets/images/logo2.png" alt="شعار جامعتي" class="logo-img"><span class="brand-text">منصة جامعتي</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavContent" aria-controls="navbarNavContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNavContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 centered-nav-links">
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="#">المواد</a></li>
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
            <div id="homeMessageArea" class="alert d-none mb-4"></div> 
           
          
<div class="search-bar-placeholder mb-5"><i class="fas fa-search"></i><span>بحث عن مادة...</span></div>
           
            <div id="course-cards-container" class="row">
            
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 lead">جاري تحميل المواد الدراسية...</p>
                </div>
               
            </div>
     
        <hr class="my-5 text-secondary">



    
</div>

        </div>
    </main>

    <footer class="footer mt-auto py-3 bg-dark text-light text-center">
        <div class="container"><span class="text-white-50"> 2025 منصة جامعتي. جميع الحقوق محفوظة.</span></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="assets/js/home.js"></script> 
</body>
</html>