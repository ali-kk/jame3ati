<?php
// teacher_dashboard.php - Teacher Dashboard Mockup
// Add session check later if needed for security
// if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] !== 6) { header('Location: login.html'); exit; }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم التدريسي</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="assets/css/hod_dashboard.css" rel="stylesheet">
    <link href="assets/css/teacher_dashboard.css" rel="stylesheet">
</head>
<body class="hod-body">
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="#"> <img src="assets/images/logo2.png" alt="Logo" height="30" class="me-2">
        <span class="brand-text" style="color:whitesmoke">لوحة تحكم التدريسي</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="ms-auto"> <ul class="navbar-nav profile-nav"> <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="assets/images/3.jpg" alt="User pic" class="profile-pic-nav">
                    <span class="username ms-2">م.م احمد حسين</span> </a>
                <ul class="dropdown-menu dropdown-menu-start" aria-labelledby="profileDropdown">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>الإعدادات</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../backend/logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a></li>
                </ul>
            </li>
        </ul>
    </div>
  </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3 sidebar-sticky">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-2 mb-1 text-muted text-uppercase">
                  <span>القائمة</span>
                </h6>
                <ul class="nav flex-column nav-pills mt-3" id="teacherSidebarNav" role="tablist">
                     <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="dashboard-tab-link" data-bs-toggle="tab" data-bs-target="#dashboardMainContent" href="#dashboardMainContent" role="tab" aria-controls="dashboardMainContent" aria-selected="true">
                            <i class="fas fa-tachometer-alt fa-fw me-2"></i>لوحة المعلومات
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                         <a class="nav-link" id="assignments-tab-link" data-bs-toggle="tab" data-bs-target="#assignmentsMainContent" href="#assignmentsMainContent" role="tab" aria-controls="assignmentsMainContent" aria-selected="false">
                            <i class="fas fa-clipboard-check fa-fw me-2"></i>تسليمات الواجبات
                        </a>
                    </li>
                     <li class="nav-item">
                         <a class="nav-link" href="#">
                            <i class="fas fa-cog fa-fw me-2"></i>الإعدادات
                        </a>
                    </li>
                     </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content-area pt-5" id="mainContentArea">

            <div class="tab-content" id="teacherTabContent">

                <div class="tab-pane fade show active" id="dashboardMainContent" role="tabpanel" aria-labelledby="dashboard-tab-link">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">لوحة المعلومات</h1>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-xl-4 col-md-6"> <div class="card text-white bg-primary h-100 shadow-sm stat-card"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-book-open fa-3x"></i> <div class="text-end"> <div class="fs-2 fw-bold">3</div> <div class="small">المواد الدراسية</div> </div> </div> </div> </div> </div>
                        <div class="col-xl-4 col-md-6"> <div class="card text-white bg-success h-100 shadow-sm stat-card"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-file-alt fa-3x"></i> <div class="text-end"> <div class="fs-2 fw-bold">45</div> <div class="small">الملفات المرفوعة</div> </div> </div> </div> </div> </div>
                        <div class="col-xl-4 col-md-6"> <div class="card text-white bg-info h-100 shadow-sm stat-card"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-video fa-3x"></i> <div class="text-end"> <div class="fs-2 fw-bold">15</div> <div class="small">الفيديوهات المرفوعة</div> </div> </div> </div> </div> </div>
                    </div>

                    <h4 class="mb-3">المواد الدراسية</h4>
                    <div class="row g-4 mb-4">
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 shadow-sm teacher-course-card">
                                <img src="assets/images/cpp.png" class="card-img-top" alt="اساسيات البرمجة" style="height: 150px; width: auto; max-width:150px; margin-left: auto; margin-right: auto;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">اساسيات البرمجة</h5> <p class="card-text small text-muted">المرحلة الأولى - الفصل الأول</p>
                                    <div class="mt-auto text-center pt-2 border-top border-secondary"> <button class="btn btn-primary btn-sm w-100 btn-manage-course" data-bs-toggle="modal" data-bs-target="#manageContentModal" data-course-name="اساسيات البرمجة"> <i class="fas fa-edit me-1"></i> إدارة المحتوى </button> </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 shadow-sm teacher-course-card">
                                <img src="assets/images/c.svg" class="card-img-top" alt="C#" style="height: 150px; width: auto; max-width:150px; margin-left: auto; margin-right: auto;">
                                <div class="card-body d-flex flex-column" >
                                    <h5 class="card-title">C#</h5> <p class="card-text small text-muted">المرحلة الثانية - الفصل الثاني</p>
                                    <div class="mt-auto text-center pt-2 border-top border-secondary"> <button class="btn btn-primary btn-sm w-100 btn-manage-course" data-bs-toggle="modal" data-bs-target="#manageContentModal" data-course-name="C#"> <i class="fas fa-edit me-1"></i> إدارة المحتوى </button> </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 shadow-sm teacher-course-card">
                                <img src="assets/images/flutter.svg" class="card-img-top" alt="تطبيقات الموبايل" style="height: 150px; width: auto; max-width:150px; margin-left: auto; margin-right: auto;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">تطبيقات الموبايل</h5> <p class="card-text small text-muted">المرحلة الرابعة - الفصل الأول</p>
                                    <div class="mt-auto text-center pt-2 border-top border-secondary"> <button class="btn btn-primary btn-sm w-100 btn-manage-course" data-bs-toggle="modal" data-bs-target="#manageContentModal" data-course-name="تطبيقات الموبايل"> <i class="fas fa-edit me-1"></i> إدارة المحتوى </button> </div>
                                </div>
                            </div>
                        </div>
                    </div> </div> <div class="tab-pane fade" id="assignmentsMainContent" role="tabpanel" aria-labelledby="assignments-tab-link">
                     <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">تسليمات الواجبات</h1>
                         <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createAssignmentModal" data-course-name="any"> <i class="fas fa-plus me-1"></i> إنشاء واجب جديد
                         </button>
                    </div>

                    <div class="mb-3">
                        <label for="mainSubjectFilter" class="form-label form-label-sm">تصفية حسب المادة:</label>
                        <select class="form-select form-select-sm d-inline-block" id="mainSubjectFilter" style="width: auto;">
                            <option value="all" selected>الكل</option>
                            <option value="prog_basics">اساسيات البرمجة</option>
                            <option value="csharp">C#</option>
                            <option value="mobile_app">تطبيقات الموبايل</option>
                        </select>
                    </div>

                    <div class="card shadow-sm">
                         <div class="card-header">
                             <h5 class="mb-0">التسليمات المستلمة</h5>
                         </div>
                         <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>اسم الواجب</th>
                                            <th>اسم الطالب</th>
                                            <th>تاريخ التسليم</th>
                                            <th>الملف</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody id="mainSubmissionsTableBody">
                                        <tr data-subject="prog_basics">
                                            <td>الواجب الأول: حل التمارين</td>
                                            <td class="student-name">علي حسن</td>
                                            <td><small>2025-04-18 09:30</small></td>
                                            <td><button class="btn btn-outline-primary btn-sm main-view-submission-btn" data-submission-id="main_sub1" title="عرض الملف"><i class="fas fa-eye fa-fw"></i></button></td>
                                
                                        </tr>
                                         <tr data-subject="prog_basics">
                                            <td>الواجب الأول: حل التمارين</td>
                                            <td class="student-name">فاطمة محمد</td>
                                            <td><small>2025-04-19 11:15</small></td>
                                            <td><button class="btn btn-outline-primary btn-sm main-view-submission-btn" data-submission-id="main_sub2" title="عرض الملف"><i class="fas fa-eye fa-fw"></i></button></td>
                                           
                                        </tr>
                                        <tr data-subject="csharp">
                                            <td>مشروع C#</td>
                                            <td class="student-name">احمد علي</td>
                                            <td><small>2025-04-20 01:05</small></td>
                                            <td><button class="btn btn-outline-primary btn-sm main-view-submission-btn" data-submission-id="main_sub3" title="عرض الملف"><i class="fas fa-eye fa-fw"></i></button></td>
                                           
                                        </tr>
                                         <tr class="no-results-row" style="display: none;">
                                             <td colspan="6" class="text-center text-muted p-3">لا توجد تسليمات مطابقة لهذا الفلتر.</td>
                                         </tr>
                                    </tbody>
                                </table>
                            </div>
                         </div>
                    </div> </div> </div> </main>
    </div>
</div>

<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
  <div class="modal-dialog"> <div class="modal-content"> <form id="uploadFileForm"> <div class="modal-header"> <h5 class="modal-title" id="uploadFileModalLabel">رفع ملف للمادة: <span class="course-name-placeholder"></span></h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <div id="uploadFileMessage" class="alert d-none"></div> <div class="mb-3"> <label for="fileTitle" class="form-label">عنوان الملف</label> <input type="text" class="form-control" id="fileTitle" required> </div> <div class="mb-3"> <label for="fileDescription" class="form-label">الوصف (اختياري)</label> <textarea class="form-control" id="fileDescription" rows="2"></textarea> </div> <div class="mb-3"> <label for="fileInput" class="form-label">اختر الملف</label> <input class="form-control" type="file" id="fileInput" required> <div class="form-text">أنواع الملفات المسموحة: PDF, DOCX, PPTX, صور, ZIP. الحد الأقصى: 10MB.</div> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button> <button type="submit" class="btn btn-primary"> <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <span class="button-text">رفع الملف</span> </button> </div> </form> </div> </div>
</div>

<div class="modal fade" id="uploadVideoModal" tabindex="-1" aria-labelledby="uploadVideoModalLabel" aria-hidden="true">
  <div class="modal-dialog"> <div class="modal-content"> <form id="uploadVideoForm"> <div class="modal-header"> <h5 class="modal-title" id="uploadVideoModalLabel">رفع فيديو للمادة: <span class="course-name-placeholder"></span></h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <div id="uploadVideoMessage" class="alert d-none"></div> <div class="mb-3"> <label for="videoTitle" class="form-label">عنوان الفيديو</label> <input type="text" class="form-control" id="videoTitle" required> <div class="invalid-feedback">عنوان الفيديو مطلوب.</div> </div> <div class="mb-3"> <label for="videoDescription" class="form-label">الوصف (اختياري)</label> <textarea class="form-control" id="videoDescription" rows="2"></textarea> </div> <div class="mb-3"> <label for="videoFileInput" class="form-label">اختر ملف الفيديو</label> <input class="form-control" type="file" id="videoFileInput" required accept="video/mp4,video/webm,video/ogg,video/quicktime"> <div class="form-text">الأنواع المسموحة: MP4, WebM, Ogg, MOV.</div> <div class="invalid-feedback">الرجاء اختيار ملف فيديو صالح.</div> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button> <button type="submit" class="btn btn-primary"> <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <span class="button-text">رفع الفيديو</span> </button> </div> </form> </div> </div>
</div>

<div class="modal fade" id="createAssignmentModal" tabindex="-1" aria-labelledby="createAssignmentModalLabel" aria-hidden="true">
  <div class="modal-dialog"> <div class="modal-content"> <form id="createAssignmentForm"> <div class="modal-header"> <h5 class="modal-title" id="createAssignmentModalLabel">إنشاء واجب للمادة: <span class="course-name-placeholder"></span></h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> </div> <div class="modal-body"> <div id="createAssignmentMessage" class="alert d-none"></div> <div class="mb-3"> <label for="assignmentTitle" class="form-label">عنوان الواجب</label> <input type="text" class="form-control" id="assignmentTitle" required> </div> <div class="mb-3"> <label for="assignmentDescription" class="form-label">وصف الواجب</label> <textarea class="form-control" id="assignmentDescription" rows="3" required></textarea> </div> <div class="row"> <div class="col-md-6 mb-3"> <label for="assignmentDeadline" class="form-label">الموعد النهائي للتسليم</label> <input type="datetime-local" class="form-control" id="assignmentDeadline" required> </div> <div class="col-md-6 mb-3"> <label for="assignmentType" class="form-label">نوع التسليم</label> <select class="form-select" id="assignmentType" required> <option value="" selected disabled>اختر...</option> <option value="electronic">إلكتروني (عبر المنصة)</option> <option value="info_only">في الصف (ورقي)</option> </select> </div> </div> <div class="mb-3"> <label for="assignmentFileInput" class="form-label">ملف مرفق للواجب (اختياري)</label> <input class="form-control" type="file" id="assignmentFileInput"> </div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button> <button type="submit" class="btn btn-primary"> <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span> <span class="button-text">إنشاء الواجب</span> </button> </div> </form> </div> </div>
</div>

<div class="modal fade" id="manageContentModal" tabindex="-1" aria-labelledby="manageContentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageContentModalLabel">إدارة محتوى المادة: <span class="course-name-placeholder"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="manageContentMessage" class="alert d-none"></div>
                <ul class="nav nav-tabs nav-fill mb-3" id="manageContentTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="manage-files-tab" data-bs-toggle="tab" data-bs-target="#manageFilesContent" type="button" role="tab" aria-controls="manageFilesContent" aria-selected="true"><i class="fas fa-folder-open me-1"></i> الملفات</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manage-videos-tab" data-bs-toggle="tab" data-bs-target="#manageVideosContent" type="button" role="tab" aria-controls="manageVideosContent" aria-selected="false"><i class="fas fa-video me-1"></i> الفيديوهات</button>
                    </li>
                    <li class="nav-item" role="presentation">
                         <button class="nav-link" id="manage-assignments-tab-in-modal" data-bs-toggle="tab" data-bs-target="#manageAssignmentsContentInModal" type="button" role="tab" aria-controls="manageAssignmentsContentInModal" aria-selected="false"><i class="fas fa-clipboard-list me-1"></i> الواجبات المنشأة</button>
                    </li>
                </ul>
                <div class="tab-content" id="manageContentTabContent">
                    <div class="tab-pane fade show active" id="manageFilesContent" role="tabpanel" aria-labelledby="manage-files-tab">
                        <button class="btn btn-success btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#uploadFileModal"> <i class="fas fa-plus me-1"></i> رفع ملف جديد </button>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center"> <span><i class="fas fa-file-pdf text-danger me-2"></i> المحاضرة الأولى.pdf</span> <div> <button class="btn btn-outline-secondary btn-sm edit-item-btn" data-item-id="file1" data-item-type="file" title="تعديل"><i class="fas fa-pencil-alt fa-fw"></i></button> <button class="btn btn-outline-danger btn-sm delete-item-btn" data-item-id="file1" data-item-type="file" title="حذف"><i class="fas fa-trash-alt fa-fw"></i></button> </div> </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"> <span><i class="fas fa-file-powerpoint text-warning me-2"></i> شرح الفصل الثاني.pptx</span> <div> <button class="btn btn-outline-secondary btn-sm edit-item-btn" data-item-id="file2" data-item-type="file" title="تعديل"><i class="fas fa-pencil-alt fa-fw"></i></button> <button class="btn btn-outline-danger btn-sm delete-item-btn" data-item-id="file2" data-item-type="file" title="حذف"><i class="fas fa-trash-alt fa-fw"></i></button> </div> </li>
                            <li class="list-group-item text-center text-muted p-3">-- نهاية القائمة --</li>
                        </ul>
                    </div>
                    <div class="tab-pane fade" id="manageVideosContent" role="tabpanel" aria-labelledby="manage-videos-tab">
                        <button class="btn btn-success btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#uploadVideoModal"> <i class="fas fa-plus me-1"></i> رفع فيديو جديد </button>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center"> <span><i class="fas fa-film text-info me-2"></i> مقدمة عن المادة</span> <div> <button class="btn btn-outline-secondary btn-sm edit-item-btn" data-item-id="video1" data-item-type="video" title="تعديل"><i class="fas fa-pencil-alt fa-fw"></i></button> <button class="btn btn-outline-danger btn-sm delete-item-btn" data-item-id="video1" data-item-type="video" title="حذف"><i class="fas fa-trash-alt fa-fw"></i></button> </div> </li>
                            <li class="list-group-item text-center text-muted p-3">-- نهاية القائمة --</li>
                        </ul>
                    </div>
                     <div class="tab-pane fade" id="manageAssignmentsContentInModal" role="tabpanel" aria-labelledby="manage-assignments-tab-in-modal">
                        <button class="btn btn-success btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#createAssignmentModal"> <i class="fas fa-plus me-1"></i> إنشاء واجب جديد </button>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap"> <div class="me-auto mb-2 mb-md-0"> <span class="fw-bold"><i class="fas fa-clipboard text-primary me-2"></i> الواجب الأول: حل التمارين</span><br> <small class="text-muted">الموعد النهائي: 2025-05-15 11:59 PM</small> </div> <div class="flex-shrink-0"> <button class="btn btn-outline-secondary btn-sm edit-item-btn me-1" data-item-id="assign1" data-item-type="assignment" title="تعديل"><i class="fas fa-pencil-alt fa-fw"></i></button> <button class="btn btn-outline-danger btn-sm delete-item-btn" data-item-id="assign1" data-item-type="assignment" title="حذف"><i class="fas fa-trash-alt fa-fw"></i></button> </div> </li>
                            <li class="list-group-item text-center text-muted p-3">-- نهاية القائمة --</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/teacher_dashboard.js"></script> </body>
</html>