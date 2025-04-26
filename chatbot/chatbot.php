<?php
// home.php - Secure Student Home Page
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_status']) || $_SESSION['user_status'] !== 'perm') { header('Location: ../login.html'); exit; }
// Determine dashboard URL based on role_id
$role_id = $_SESSION['role_id'] ?? '';
$rolePages = ['5'=>'../hod_dashboard.php','6'=>'../teacher_dashboard.php','7'=>'../home.php'];
$dashboardUrl = $rolePages[$role_id] ?? '../home.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة جامعتي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/chatbot.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">

        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2 class="tit">منصة جامعتي</h2>
            </div>
            <button class="new-chat-btn">
                <i class="bi bi-plus-lg"></i> محادثة جديدة
            </button>
            <div class="sidebar-search">
                <div class="search-container">
                    <div class="search-icon"><i class="bi bi-search"></i></div>
                    <input type="text" id="searchInput" placeholder="ابحث في المحادثات...">
                </div>
            </div>
            <div id="chatHistory" class="chat-history-list">
                </div>
        </div>

        <div class="main" id="mainArea">
            <div class="chat-header position-relative">
                <div class="back-toggle-group">
                    <a href="<?= $dashboardUrl ?>" class="btn btn-light btn-sm back-btn text-decoration-none d-inline-flex align-items-center flex-row-reverse">
                        <i class="bi bi-arrow-left"></i><span class="me-2">الرجوع</span>
                    </a>
                    <button id="sidebarToggle" class="btn btn-light btn-sm chat-toggle d-md-none d-inline-flex align-items-center ms-2">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
                <div class="ai-avatar">
                    <img src="../assets/images/logo2.png" alt="AI Avatar">
                </div>
                <div class="chat-title-container">
                    <h1>منصة جامعتي</h1>
                    <div class="chat-subtitle">مساعد الذكاء الاصطناعي-منصة جامعتي</div>
                </div>
            </div>

            <div id="chatMessages">
                <div class="empty-state">
                    <i class="bi bi-chat-dots-fill empty-state-icon"></i>
                    <h2>مرحبًا بكم في منصة جامعتي</h2>
                    <p>ابدأ محادثة مع مساعد الذكاء الاصطناعي الخاص بمنصة جامعتي. سيتم حفظ محادثاتك تلقائيًا على جهازك.</p>
                </div>
                </div>

            <div class="input-area">
                <div class="input-container">
                    <textarea id="messageInput" placeholder="اكتب رسالتك هنا..." rows="1" dir="rtl"></textarea>
                    <button id="sendButton" disabled
                            aria-label="إرسال الرسالة"
                            title="إرسال">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/chatbot.js"></script>
</body>
</html>