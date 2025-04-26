<?php
// frontend/hod_dashboard.php - Revised

if (session_status() !== PHP_SESSION_ACTIVE)
 {
    session_start();
}

// Include security helpers
require_once __DIR__ . '/../backend/security.php';
set_security_headers();

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_status'] ?? '') !== 'perm' ||
    (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 5) ||
    empty($_SESSION['dep_id'])
) {
    header('Location: login.html');
    exit;
}

// Generate CSRF token for forms and AJAX requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>لوحة تحكم القسم</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/hod_dashboard.css" rel="stylesheet"> </head>
<body class="hod-body">
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
  <div class="container-fluid d-flex align-items-center">
    <div>
    <a class="navbar-brand me-auto d-flex align-items-center" href="hod_dashboard.php">
      <img src="assets/images/logo2.png" alt="Logo" height="40" class="me-3">
      <span class="brand-text">لوحة تحكم القسم</span>
    </a>
    </div>
   
    <div>
    <ul class="navbar-nav ms-auto profile-nav">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="assets/images/3.jpg" alt="User pic" class="profile-pic-nav" id="userProfilePicNav">
          <span class="username" id="hodNameNav">رئيس القسم</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="personal_info.php">الملف الشخصي<i class="fas fa-user ms-3"></i></a></li>
          <li><a class="dropdown-item" href="settings.php">الإعدادات<i class="fas fa-cog ms-3"></i></a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="../backend/logout.php">تسجيل الخروج<i class="fas fa-sign-out-alt ms-3"></i></a></li>
        </ul>
      </li>
    </ul>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3 sidebar-sticky">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-2 mb-1 text-muted text-uppercase">
                      <span>قسم علوم الحاسوب</span>
                    </h6>
                    <ul class="nav flex-column nav-pills mt-3">
                        <li class="nav-item"> <a class="nav-link active" id="dashboard-tab-link" data-bs-toggle="tab" data-bs-target="#dashboardContent" href="#dashboardContent" role="tab"><i class="fas fa-tachometer-alt fa-fw me-2"></i>لوحة المعلومات</a> </li>
                        <li class="nav-item"> <a class="nav-link" id="requests-tab-link" data-bs-toggle="tab" data-bs-target="#requestsContent" href="#requestsContent" role="tab"><i class="fas fa-user-clock fa-fw me-2"></i>طلبات التسجيل <span class="badge bg-danger rounded-pill float-end ms-1" id="totalPendingBadge" style="display: none;">0</span></a> </li>
                        <li class="nav-item"> <a class="nav-link" id="students-tab-link" data-bs-toggle="tab" data-bs-target="#manageStudentsContent" href="#manageStudentsContent" role="tab"><i class="fas fa-users-cog fa-fw me-2"></i>إدارة الطلاب</a> </li>
                        <li class="nav-item"> <a class="nav-link" id="teachers-tab-link" data-bs-toggle="tab" data-bs-target="#manageTeachersContent" href="#manageTeachersContent" role="tab"><i class="fas fa-chalkboard-teacher fa-fw me-2"></i>إدارة التدريسيين</a> </li>
                        <li class="nav-item"> <a class="nav-link" id="subjects-tab-link" data-bs-toggle="tab" data-bs-target="#manageSubjectsContent" href="#manageSubjectsContent" role="tab"><i class="fas fa-book fa-fw me-2"></i>إدارة المواد</a> </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content-area pt-5">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="pageTitle">لوحة المعلومات</h1>
                </div>

                <div id="hodMessageArea" class="alert d-none mb-4 mx-3"></div>

                <div class="tab-content" id="hodTabContent">

                    <div class="tab-pane fade show active" id="dashboardContent" role="tabpanel">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                             <h4>نظرة عامة على القسم</h4>
                             <button id="refreshDashboardBtn" class="btn btn-sm btn-outline-primary">
                                 <i class="fas fa-sync-alt"></i> تحديث
                             </button>
                         </div>
                         <div id="dashboardLoading" class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                         
                         <!-- Skeleton loader for dashboard stats -->
                         <div id="dashboardSkeleton" class="row g-4 mb-4" style="display: none;">
                             <div class="col-xl-3 col-md-6">
                                 <div class="card h-100 shadow-sm skeleton-card">
                                     <div class="card-body">
                                         <div class="d-flex justify-content-between align-items-center">
                                             <div class="skeleton-icon"></div>
                                             <div class="text-end w-50">
                                                 <div class="skeleton-title"></div>
                                                 <div class="skeleton-text"></div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-xl-3 col-md-6">
                                 <div class="card h-100 shadow-sm skeleton-card">
                                     <div class="card-body">
                                         <div class="d-flex justify-content-between align-items-center">
                                             <div class="skeleton-icon"></div>
                                             <div class="text-end w-50">
                                                 <div class="skeleton-title"></div>
                                                 <div class="skeleton-text"></div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-xl-3 col-md-6">
                                 <div class="card h-100 shadow-sm skeleton-card">
                                     <div class="card-body">
                                         <div class="d-flex justify-content-between align-items-center">
                                             <div class="skeleton-icon"></div>
                                             <div class="text-end w-50">
                                                 <div class="skeleton-title"></div>
                                                 <div class="skeleton-text"></div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-xl-3 col-md-6">
                                 <div class="card h-100 shadow-sm skeleton-card">
                                     <div class="card-body">
                                         <div class="d-flex justify-content-between align-items-center">
                                             <div class="skeleton-icon"></div>
                                             <div class="text-end w-50">
                                                 <div class="skeleton-title"></div>
                                                 <div class="skeleton-text"></div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                         
                         <div class="row g-4 mb-4" id="dashboardStatsRow" style="display: none;">
                             <div class="col-xl-3 col-md-6"> <div class="card text-white bg-primary h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-users fa-3x opacity-75"></i> <div class="text-end"> <div class="fs-2 fw-bold" id="totalStudentsStat">0</div> <div class="small">طالب فعال</div> </div> </div> </div> </div> </div>
                             <div class="col-xl-3 col-md-6"> <div class="card text-white bg-success h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-chalkboard-teacher fa-3x opacity-75"></i> <div class="text-end"> <div class="fs-2 fw-bold" id="totalTeachersStat">0</div> <div class="small">تدريسي فعال</div> </div> </div> </div> </div> </div>
                             <div class="col-xl-3 col-md-6"> <div class="card text-white bg-info h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-book-open fa-3x opacity-75"></i> <div class="text-end"> <div class="fs-2 fw-bold" id="totalSubjectsStat">0</div> <div class="small">مادة دراسية</div> </div> </div> </div> </div> </div>
                             <div class="col-xl-3 col-md-6"> <div class="card text-dark bg-light h-100 shadow-sm"> <div class="card-body"> <div class="d-flex justify-content-between align-items-center"> <i class="fas fa-photo-video fa-3x opacity-75"></i> <div class="text-end"> <div class="fs-2 fw-bold" id="totalMaterialsStat">0</div> <div class="small">ملف/فيديو</div> </div> </div> </div> </div> </div>
                         </div>
                    </div>

                    <div class="tab-pane fade" id="requestsContent" role="tabpanel">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                             <h4>طلبات التسجيل المعلقة</h4>
                             <button id="refreshRequestsBtn" class="btn btn-sm btn-outline-primary">
                                 <i class="fas fa-sync-alt"></i> تحديث
                             </button>
                         </div>
                         <div class="card shadow mb-4">
                              <div class="card-header py-3"> <h6 class="m-0">الطلاب</h6> </div>
                              <div class="card-body">
                                  <div id="pendingStudentsLoading" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                                  <div id="pendingStudentsList" class="table-responsive" style="display: none;">
                                      <table class="table table-hover align-middle" width="100%">
                                         <thead><tr><th>الاسم</th><th>البريد</th><th>المرحلة/الدراسة</th><th>المستمسكات</th><th>الإجراء</th></tr></thead>
                                         <tbody class="table-group-divider"></tbody>
                                      </table>
                                      <div id="pendingStudentsPagination" class="pagination-container"></div>
                                  </div>
                              </div>
                         </div>
                          <div class="card shadow mb-4">
                              <div class="card-header py-3"> <h6 class="m-0">التدريسيون</h6> </div>
                              <div class="card-body">
                                   <div id="pendingTeachersLoading" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                                   <div id="pendingTeachersList" class="table-responsive" style="display: none;">
                                       <table class="table table-hover align-middle" width="100%">
                                          <thead><tr><th>الاسم</th><th>البريد</th><th>اللقب العلمي</th><th>المستمسكات</th><th>الإجراء</th></tr></thead>
                                          <tbody class="table-group-divider"></tbody>
                                      </table>
                                      <div id="pendingTeachersPagination" class="pagination-container"></div>
                                  </div>
                             </div>
                         </div>
                    </div>

                    <div class="tab-pane fade" id="manageStudentsContent" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                             <h4>الطلاب</h4>
                             <button id="refreshStudentsBtn" class="btn btn-sm btn-outline-primary">
                                 <i class="fas fa-sync-alt"></i> تحديث
                             </button>
                         </div>
                         <div class="card shadow mb-4">
                             <div class="card-header py-3"><h6 class="m-0">طلاب القسم</h6></div>
                             <div class="card-body">
                                 <div id="deptStudentsLoading" class="text-center py-5"><div class="spinner-border"></div></div>
                                 <div id="deptStudentsList" class="table-responsive" style="display: none;">
                                     <table class="table table-hover align-middle" width="100%">
                                         <thead><tr><th>ID</th><th>الاسم</th><th>البريد</th><th>المرحلة</th><th>الحالة</th><th style="width: 15%;">الإجراء</th></tr></thead>
                                         <tbody class="table-group-divider"></tbody>
                                     </table>
                                      <div id="deptStudentsPagination" class="pagination-container"></div>
                                 </div>
                             </div>
                         </div>
                    </div>

                    <div class="tab-pane fade" id="manageTeachersContent" role="tabpanel">
                          <div class="d-flex justify-content-between align-items-center mb-3">
                             <h4>التدريسيين</h4>
                             <button id="refreshTeachersBtn" class="btn btn-sm btn-outline-primary">
                                 <i class="fas fa-sync-alt"></i> تحديث
                             </button>
                         </div>
                         <div class="card shadow mb-4">
                             <div class="card-header py-3"><h6 class="m-0">تدريسيي القسم</h6></div>
                             <div class="card-body">
                                  <div id="deptTeachersLoading" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                                   <div id="deptTeachersList" class="table-responsive" style="display: none;">
                                       <table class="table table-hover align-middle" width="100%">
                                           <thead><tr><th>ID</th><th>الاسم</th><th>اللقب</th><th>البريد</th><th>الحالة</th><th style="width: 15%;">الإجراء</th></tr></thead>
                                           <tbody class="table-group-divider"></tbody>
                                       </table>
                                       <div id="deptTeachersPagination" class="pagination-container"></div>
                                   </div>
                             </div>
                         </div>
                    </div>

                    <div class="tab-pane fade" id="manageSubjectsContent" role="tabpanel">
                           <div class="d-flex justify-content-between align-items-center mb-3">
                             <h4>المواد الدراسية</h4>
                             <button id="refreshSubjectsBtn" class="btn btn-sm btn-outline-primary">
                                 <i class="fas fa-sync-alt"></i> تحديث
                             </button>
                         </div>
                         <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                     <h6 class="m-0">مواد القسم الدراسية</h6>
                                     <button class="btn btn-primary btn-sm" id="addSubjectBtn" data-bs-toggle="modal" data-bs-target="#subjectModal">
                                         <i class="fas fa-plus fa-sm"></i> إضافة مادة
                                     </button>
                                </div>
                                <div class="card-body">
                                    <div id="deptSubjectsLoading" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                                    <div id="deptSubjectsList" class="table-responsive" style="display: none;">
                                        <table class="table table-hover align-middle" width="100%">
                                            <thead><tr><th>ID</th><th>اسم المادة</th><th>المرحلة</th><th>الفصل</th><th>التدريسيون</th><th style="width: 15%;">الإجراء</th></tr></thead>
                                            <tbody class="table-group-divider"></tbody>
                                        </table>
                                        <div id="deptSubjectsPagination" class="pagination-container"></div>
                                    </div>
                                </div>
                           </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
       <div class="modal-dialog modal-xl modal-dialog-scrollable"> 
           <div class="modal-content"> 
               <div class="modal-header d-flex justify-content-between align-items-center">
                   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   <h5 class="modal-title" id="viewDocumentModalLabel">عرض المستمسكات</h5>
               </div> 
               <div class="modal-body"> 
                   <p id="docUserInfo" class="fw-bold text-center mb-3"></p> 
                   <div id="docLoadingIndicator" class="text-center my-3"><div class="spinner-border text-primary"></div> تحميل...</div> 
                   <div id="docErrorMessage" class="alert alert-danger text-center" style="display: none;"></div> 
                   <div id="docImageContainer" class="row g-3"></div> 
               </div> 
           </div> 
       </div>
     </div>

     <!-- Edit Student Modal -->
     <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="editStudentForm" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <h5 class="modal-title" id="editStudentModalLabel">تعديل بيانات الطالب</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="studentEditId" name="user_id">
                        <input type="hidden" name="action" value="update_user">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="studentFirstName" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="studentFirstName" name="first_name" required>
                                <div class="invalid-feedback">الاسم الأول مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentLastName" class="form-label">اسم الأب</label>
                                <input type="text" class="form-control" id="studentLastName" name="last_name" required>
                                <div class="invalid-feedback">اسم الأب مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentThirdName" class="form-label">اسم الجد</label>
                                <input type="text" class="form-control" id="studentThirdName" name="third_name" required>
                                <div class="invalid-feedback">اسم الجد مطلوب.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="studentMotherFirstName" class="form-label">اسم الأم</label>
                                <input type="text" class="form-control" id="studentMotherFirstName" name="mother_first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="studentMotherSecondName" class="form-label">اسم أب الأم</label>
                                <input type="text" class="form-control" id="studentMotherSecondName" name="mother_second_name">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="studentBirthday" class="form-label">تاريخ الميلاد</label>
                                <input type="date" class="form-control" id="studentBirthday" name="birthday" required>
                                <div class="invalid-feedback">تاريخ الميلاد مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentGender" class="form-label">الجنس</label>
                                <select class="form-select" id="studentGender" name="gender" required>
                                    <option value="" selected disabled>اختر...</option>
                                    <option value="Male">ذكر</option>
                                    <option value="Female">أنثى</option>
                                </select>
                                <div class="invalid-feedback">الجنس مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentCity" class="form-label">المدينة</label>
                                <input type="text" class="form-control" id="studentCity" name="city" required>
                                <div class="invalid-feedback">المدينة مطلوبة.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="studentNationality" class="form-label">الجنسية</label>
                                <input type="text" class="form-control" id="studentNationality" name="nationality" value="Iraq">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="studentEmail" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="studentEmail" name="email" required>
                                <div class="invalid-feedback">البريد الإلكتروني مطلوب وبصيغة صحيحة.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="studentStage" class="form-label">المرحلة</label>
                                <select class="form-select" id="studentStage" name="stage" required>
                                    <option value="" selected disabled>اختر...</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                                <div class="invalid-feedback">المرحلة مطلوبة.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentDegree" class="form-label">الشهادة</label>
                                <select class="form-select" id="studentDegree" name="degree" required>
                                    <option value="" selected disabled>اختر...</option>
                                    <option value="Diploma">دبلوم</option>
                                    <option value="Bachelor">بكالوريوس</option>
                                    <option value="Master's">ماجستير</option>
                                </select>
                                <div class="invalid-feedback">الشهادة مطلوبة.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="studentStudyMode" class="form-label">نوع الدراسة</label>
                                <select class="form-select" id="studentStudyMode" name="study_mode" required>
                                    <option value="" selected disabled>اختر...</option>
                                    <option value="morning">صباحي</option>
                                    <option value="evening">مسائي</option>
                                    <option value="parallel">موازي</option>
                                </select>
                                <div class="invalid-feedback">نوع الدراسة مطلوب.</div>
                            </div>
                        </div>
                        
                        <div id="studentEditMessage" class="alert d-none mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="saveStudentBtn">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
     </div>

     <!-- Edit Teacher Modal -->
     <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="editTeacherForm" class="needs-validation" novalidate>
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <h5 class="modal-title" id="editTeacherModalLabel">تعديل بيانات التدريسي</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="teacherEditId" name="user_id">
                        <input type="hidden" name="action" value="update_user">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="teacherFirstName" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="teacherFirstName" name="first_name" required>
                                <div class="invalid-feedback">الاسم الأول مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherLastName" class="form-label">اسم الأب</label>
                                <input type="text" class="form-control" id="teacherLastName" name="last_name" required>
                                <div class="invalid-feedback">اسم الأب مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherThirdName" class="form-label">اسم الجد</label>
                                <input type="text" class="form-control" id="teacherThirdName" name="third_name" required>
                                <div class="invalid-feedback">اسم الجد مطلوب.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="teacherMotherFirstName" class="form-label">اسم الأم</label>
                                <input type="text" class="form-control" id="teacherMotherFirstName" name="mother_first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="teacherMotherSecondName" class="form-label">اسم أب الأم</label>
                                <input type="text" class="form-control" id="teacherMotherSecondName" name="mother_second_name">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="teacherBirthday" class="form-label">تاريخ الميلاد</label>
                                <input type="date" class="form-control" id="teacherBirthday" name="birthday" required>
                                <div class="invalid-feedback">تاريخ الميلاد مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherGender" class="form-label">الجنس</label>
                                <select class="form-select" id="teacherGender" name="gender" required>
                                    <option value="" selected disabled>اختر...</option>
                                    <option value="Male">ذكر</option>
                                    <option value="Female">أنثى</option>
                                </select>
                                <div class="invalid-feedback">الجنس مطلوب.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherCity" class="form-label">المدينة</label>
                                <input type="text" class="form-control" id="teacherCity" name="city" required>
                                <div class="invalid-feedback">المدينة مطلوبة.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="teacherNationality" class="form-label">الجنسية</label>
                                <input type="text" class="form-control" id="teacherNationality" name="nationality" value="Iraq">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherAcademicTitle" class="form-label">اللقب العلمي</label>
                                <select class="form-select" id="teacherAcademicTitle" name="academic_title">
                                    <option value="" disabled selected>اختر...</option>
                                    <option value="م.م">مدرس مساعد (م.م)</option>
                                    <option value="م">مدرس (م)</option>
                                    <option value="أ.م">أستاذ مساعد (أ.م)</option>
                                    <option value="أ.د">أستاذ (أ.د)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="teacherEmail" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="teacherEmail" name="email" required>
                                <div class="invalid-feedback">البريد الإلكتروني مطلوب وبصيغة صحيحة.</div>
                            </div>
                        </div>
                        
                        <div id="teacherEditMessage" class="alert d-none mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="saveTeacherBtn">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
     </div>

     <div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalLabel" aria-hidden="true">
         <div class="modal-dialog"> <div class="modal-content"> <form id="subjectForm" class="needs-validation" enctype="multipart/form-data" novalidate> <div class="modal-header"> <button type="button" class="btn-close" data-bs-dismiss="modal"></button> <h5 class="modal-title" id="subjectModalLabel">إضافة مادة</h5> </div> <div class="modal-body"> <input type="hidden" id="subjectEditId" name="course_id"> <div class="mb-3"> <label for="subjectName" class="form-label">اسم المادة</label> <input type="text" class="form-control" id="subjectName" name="course_name" required> <div class="invalid-feedback">اسم المادة مطلوب.</div> </div> <div class="row"> <div class="col-md-6 mb-3"> <label for="subjectStage" class="form-label">المرحلة</label> <select class="form-select" id="subjectStage" name="stage" required> <option value="" selected disabled>اختر...</option> <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option> </select> <div class="invalid-feedback">اختر المرحلة.</div> </div> <div class="col-md-6 mb-3"> <label for="subjectSemester" class="form-label">الفصل</label> <select class="form-select" id="subjectSemester" name="semester" required> <option value="" selected disabled>اختر...</option> <option value="1">1</option><option value="2">2</option> </select> <div class="invalid-feedback">اختر الفصل.</div> </div> </div> <div class="mb-3"> <label for="subjectDescription" class="form-label">الوصف (اختياري)</label> <textarea class="form-control" id="subjectDescription" name="description" rows="3"></textarea> </div> <div class="mb-3"> <label for="subjectTeachers" class="form-label">التدريسي</label> <select class="form-select" id="subjectTeachers" name="teacher_user_id"> <option value="" selected>-- اختر تدريسي (اختياري) --</option> </select> </div> <div class="mb-3">
                <label for="subjectImage" class="form-label">صورة المادة</label>
                <input type="file" class="form-control" id="subjectImage" name="subject_image" accept="image/*">
                <div class="form-text">يرجى اختيار صورة بحجم أقل من 5 ميجابايت (JPG, PNG, GIF, WEBP)</div>
                <div id="subjectImagePreview" class="mt-2 text-center" style="display: none;">
                    <img src="" alt="معاينة الصورة" class="img-fluid img-thumbnail" style="max-height: 150px;">
                </div>
            </div> <div id="subjectMessage" class="alert d-none mt-3"></div> </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button> <button type="submit" class="btn btn-primary" id="saveSubjectBtn">حفظ</button> </div> </form> </div> </div>
     </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/hod_dashboard.js"></script>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-dark text-white">
        <div class="container text-center">
            <div class="row">
                <div class="col-md-12">
                    <p class="mb-0">  منصة جامعتي - جميع الحقوق محفوظة <?php echo date('Y'); ?></p>
                    <p class="small text-muted mb-0">مركز الحاسبة الالكترونية-جامعة كركوك</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>