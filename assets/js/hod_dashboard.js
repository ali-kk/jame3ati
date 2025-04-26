// assets/js/hod_dashboard.js - Complete Version

document.addEventListener('DOMContentLoaded', () => {
    console.log("HoD Dashboard JS Loaded");

    // --- Global Elements ---
    const hodMessageArea = document.getElementById('hodMessageArea');
    const hodNameNav = document.getElementById('hodNameNav'); // For navbar HoD name update if needed
    const dashboardSkeleton = document.getElementById('dashboardSkeleton');

    // --- Tab & Loading Elements ---
    const loadingOverlay = document.getElementById('loadingOverlay');
    const tabContent = document.getElementById('hodTabContent');
    const dashboardLoading = document.getElementById('dashboardLoading');
    const dashboardStatsRow = document.getElementById('dashboardStatsRow');
    const pendingStudentsLoading = document.getElementById('pendingStudentsLoading');
    const pendingStudentsListDiv = document.getElementById('pendingStudentsList');
    const pendingStudentsTbody = pendingStudentsListDiv?.querySelector('tbody');
    const pendingStudentsPaginationDiv = document.getElementById('pendingStudentsPagination');
    const pendingTeachersLoading = document.getElementById('pendingTeachersLoading');
    const pendingTeachersListDiv = document.getElementById('pendingTeachersList');
    const pendingTeachersTbody = pendingTeachersListDiv?.querySelector('tbody');
    const pendingTeachersPaginationDiv = document.getElementById('pendingTeachersPagination'); // Needed if paginating separately
    const deptStudentsLoading = document.getElementById('deptStudentsLoading');
    const deptStudentsListDiv = document.getElementById('deptStudentsList');
    const deptStudentsTbody = deptStudentsListDiv?.querySelector('tbody');
    const deptStudentsPaginationDiv = document.getElementById('deptStudentsPagination');
    const deptTeachersLoading = document.getElementById('deptTeachersLoading');
    const deptTeachersListDiv = document.getElementById('deptTeachersList');
    const deptTeachersTbody = deptTeachersListDiv?.querySelector('tbody');
    const deptTeachersPaginationDiv = document.getElementById('deptTeachersPagination');
    const deptSubjectsLoading = document.getElementById('deptSubjectsLoading');
    const deptSubjectsListDiv = document.getElementById('deptSubjectsList');
    const deptSubjectsTbody = deptSubjectsListDiv?.querySelector('tbody');
    const deptSubjectsPaginationDiv = document.getElementById('deptSubjectsPagination');
    const pageTitleElement = document.getElementById('pageTitle'); // For updating title based on tab

    // --- Badges ---
    const totalPendingBadge = document.getElementById('totalPendingBadge');

    // --- Modals & Forms ---
    const docViewerModal = document.getElementById('viewDocumentModal');
    const docImageViewer = document.getElementById('docImageViewer');
    const docErrorMessage = document.getElementById('docErrorMessage');
    const docUserInfo = document.getElementById('docUserInfo');
    const docLoadingIndicator = document.getElementById('docLoadingIndicator');
    const docImageContainer = document.getElementById('docImageContainer');
    let viewDocModalInstance = docViewerModal ? new bootstrap.Modal(docViewerModal) : null;

    // Student Edit Modal
    const editStudentModalElement = document.getElementById('editStudentModal');
    const editStudentModal = editStudentModalElement ? new bootstrap.Modal(editStudentModalElement) : null;
    const editStudentForm = document.getElementById('editStudentForm');
    const studentEditId = document.getElementById('studentEditId');
    const studentEditMessage = document.getElementById('studentEditMessage');
    
    // Teacher Edit Modal
    const editTeacherModalElement = document.getElementById('editTeacherModal');
    const editTeacherModal = editTeacherModalElement ? new bootstrap.Modal(editTeacherModalElement) : null;
    const editTeacherForm = document.getElementById('editTeacherForm');
    const teacherEditId = document.getElementById('teacherEditId');
    const teacherEditMessage = document.getElementById('teacherEditMessage');

    const subjectModalElement = document.getElementById('subjectModal');
    const subjectModal = subjectModalElement ? new bootstrap.Modal(subjectModalElement) : null;
    const subjectForm = document.getElementById('subjectForm');
    const subjectModalLabel = document.getElementById('subjectModalLabel');
    const subjectEditId = document.getElementById('subjectEditId');
    const saveSubjectBtn = document.getElementById('saveSubjectBtn');
    const subjectMessage = document.getElementById('subjectMessage');
    const subjectTeachersSelect = document.getElementById('subjectTeachers');
    const subjectNameInput = document.getElementById('subjectName');
    const subjectStageSelect = document.getElementById('subjectStage');
    const subjectSemesterSelect = document.getElementById('subjectSemester');
    const subjectDescription = document.getElementById('subjectDescription');
    const subjectImage = document.getElementById('subjectImage');
    const subjectImagePreview = document.getElementById('subjectImagePreview');

    // --- Endpoints ---
    const dataEndpoint = '../backend/hod_data.php';
    const approveActionEndpoint = '../backend/hod_approve_user.php';
    const statusActionEndpoint = '../backend/hod_update_user_status.php';
    const documentsEndpoint = '../backend/get_user_documents.php';
    const subjectActionEndpoint = '../backend/hod_mange_subject.php';
    const editUserEndpoint = '../backend/hod_edit_user.php';
    const loginPageUrl = 'login.html';

    // --- State ---
    let dataCache = {}; // Simple cache for data sections
    let currentSection = 'dashboard'; // Track current visible tab/section
    let currentPageState = { // Store current page for each section
        pendingUsers: 1,
        students: 1,
        teachers: 1,
        subjects: 1
    };
    const defaultLimit = 10;

    // --- Security Functions ---
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Get CSRF token from meta tag
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // Add CSRF token to fetch requests
    async function fetchWithCsrf(url, options = {}) {
        const csrfToken = getCsrfToken();
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        };
        
        const mergedOptions = { 
            ...defaultOptions, 
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        return fetch(url, mergedOptions);
    }

    // --- Helper Functions ---
    function showMessage(msg, isError = false, area = hodMessageArea, autoHide = true) {
        if (!area) return; area.textContent = escapeHtml(msg); area.className = 'alert mb-3 mx-3'; // Added mx-3
        if (msg) { area.classList.add(isError ? 'alert-danger' : 'alert-success'); area.classList.remove('d-none'); if(autoHide){ setTimeout(() => { area.classList.add('d-none'); area.textContent = ''; }, 5000); } }
        else { area.classList.add('d-none'); }
    }
    function formatUserStatus(status) {
        switch (status) {
            case 'perm': return '<span class="badge bg-success">فعال</span>';
            case 'temp': return '<span class="badge bg-warning text-dark">معلق</span>';
            case 'frozen': return '<span class="badge bg-secondary">مجمد</span>';
            case 'rejected': return '<span class="badge bg-danger">مرفوض</span>';
            default: return `<span class="badge bg-dark">${status || 'غير معروف'}</span>`;
        }
    }
    function setLoadingState(isLoading, element) { if (element) { element.style.display = isLoading ? 'block' : 'none'; } }
    function showTableLoading(tbodyElement, listDivElement, loadingElement, colSpan = 5) {
        if (tbodyElement) tbodyElement.innerHTML = `<tr><td colspan="${colSpan}" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        if (listDivElement) listDivElement.style.display = 'block'; // Show table structure even when loading
        if (loadingElement) loadingElement.style.display = 'none'; // Hide separate loader
    }

    // --- Pagination Rendering ---
    function renderPagination(containerElement, currentPage, totalItems, limit, sectionKey) {
        if (!containerElement) return;
        containerElement.innerHTML = '';
        if(totalItems <= limit) return; // No pagination needed for one page

        const totalPages = Math.ceil(totalItems / limit);
        let paginationHTML = '<ul class="pagination pagination-sm justify-content-center">'; // Center pagination

        paginationHTML += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}" data-section="${sectionKey}" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>`;

        // Simplified page number display (e.g., show first, last, current, and neighbors)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        if (startPage > 1) paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="1" data-section="${sectionKey}">1</a></li>`;
        if (startPage > 2) paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}" data-section="${sectionKey}">${i}</a></li>`;
        }

        if (endPage < totalPages - 1) paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        if (endPage < totalPages) paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}" data-section="${sectionKey}">${totalPages}</a></li>`;

        paginationHTML += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}" data-section="${sectionKey}" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>`;
        paginationHTML += '</ul>';
        containerElement.innerHTML = paginationHTML;
    }

    // --- Display Functions ---
     function displayDashboardStats(stats) {
         if (!dashboardStatsRow) return;
         document.getElementById('totalStudentsStat').textContent = stats?.students ?? 0;
         document.getElementById('totalTeachersStat').textContent = stats?.teachers ?? 0; 
         document.getElementById('totalSubjectsStat').textContent = stats?.subjects ?? 0;
         document.getElementById('totalMaterialsStat').textContent = stats?.materials ?? 0;
         
         // Hide loading indicators and show stats
         setLoadingState(false, dashboardLoading);
         if (dashboardSkeleton) dashboardSkeleton.style.display = 'none';
         dashboardStatsRow.style.display = 'flex';
     }

    function displayPendingUsers(usersData) {
        const students = usersData.data.filter(u => u.role_id == 7);
        const teachers = usersData.data.filter(u => u.role_id == 6);

        // Students
        if (!pendingStudentsTbody || !pendingStudentsListDiv) return;
        pendingStudentsTbody.innerHTML = '';
        if (!students || students.length === 0) { pendingStudentsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3 small">لا يوجد طلاب بانتظار الموافقة.</td></tr>'; }
        else { students.forEach(student => { 
            const row = pendingStudentsTbody.insertRow(); 
            row.innerHTML = `<td>${escapeHtml(student.first_name||'')} ${escapeHtml(student.last_name||'')} ${escapeHtml(student.third_name||'')}</td>
                            <td>${escapeHtml(student.Email||'N/A')}</td>
                            <td>${escapeHtml(student.stage||'N/A')} (${escapeHtml(student.study_mode||'N/A')})</td>
                            <td class="text-center"><button class="btn btn-sm btn-outline-secondary view-doc-btn" data-user-id="${student.id}" title="عرض المستمسكات"><i class="far fa-id-card fa-fw"></i></button></td>
                            <td class="table-action-btns">
                                <button class="btn btn-success btn-sm approve-user-btn" data-user-id="${student.id}" title="قبول"><i class="fas fa-check fa-fw"></i></button>
                                <button class="btn btn-danger btn-sm reject-user-btn" data-user-id="${student.id}" title="رفض"><i class="fas fa-times fa-fw"></i></button>
                            </td>`; 
        }); }
        setLoadingState(false, pendingStudentsLoading); pendingStudentsListDiv.style.display = 'block';

        // Teachers
        if (!pendingTeachersTbody || !pendingTeachersListDiv) return;
        pendingTeachersTbody.innerHTML = '';
        if (!teachers || teachers.length === 0) { pendingTeachersTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3 small">لا يوجد تدريسيون بانتظار الموافقة.</td></tr>'; }
        else { teachers.forEach(teacher => { 
            const row = pendingTeachersTbody.insertRow(); 
            row.innerHTML = `<td>${escapeHtml(teacher.first_name||'')} ${escapeHtml(teacher.last_name||'')} ${escapeHtml(teacher.third_name||'')}</td>
                            <td>${escapeHtml(teacher.Email||'N/A')}</td>
                            <td>${escapeHtml(teacher.academic_title||'N/A')}</td>
                            <td class="text-center"><button class="btn btn-sm btn-outline-secondary view-doc-btn" data-user-id="${teacher.id}" title="عرض المستمسكات"><i class="far fa-id-card fa-fw"></i></button></td>
                            <td class="table-action-btns">
                                <button class="btn btn-success btn-sm approve-user-btn" data-user-id="${teacher.id}" title="قبول"><i class="fas fa-check fa-fw"></i></button>
                                <button class="btn btn-danger btn-sm reject-user-btn" data-user-id="${teacher.id}" title="رفض"><i class="fas fa-times fa-fw"></i></button>
                            </td>`; 
        }); }
        setLoadingState(false, pendingTeachersLoading); pendingTeachersListDiv.style.display = 'block';

        // Update combined badge - Using total from backend pagination data
        const totalPending = usersData.total || 0;
        if(totalPendingBadge) { totalPendingBadge.textContent = totalPending; totalPendingBadge.style.display = totalPending > 0 ? 'inline-block' : 'none'; }
         // Render pagination for pending users (using the combined total)
        renderPagination(pendingStudentsPaginationDiv, usersData.page, usersData.total, usersData.limit, 'pendingUsers');
        if(pendingTeachersPaginationDiv) pendingTeachersPaginationDiv.innerHTML = ''; // Clear second pagination if exists
    }

     function displayDepartmentStudents(studentsData) {
          if (!deptStudentsTbody || !deptStudentsListDiv) return;
          deptStudentsTbody.innerHTML = '';
          if (!studentsData.data || studentsData.data.length === 0) {
              deptStudentsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3 small">لا يوجد طلاب في القسم.</td></tr>';
          } else {
              studentsData.data.forEach(student => {
                  const row = deptStudentsTbody.insertRow();
                  row.innerHTML = `<td>${student.id}</td>
                                  <td>${escapeHtml(student.first_name||'')} ${escapeHtml(student.last_name||'')} ${escapeHtml(student.third_name||'')}</td>
                                  <td>${escapeHtml(student.Email||'N/A')}</td>
                                  <td>${escapeHtml(student.stage||'N/A')}</td>
                                  <td>${formatUserStatus(student.user_status)}</td>
                                  <td class="table-action-btns">
                                      <button class="btn btn-info btn-sm edit-student-btn" data-user-id="${student.id}" title="تعديل"><i class="fas fa-edit fa-fw"></i></button>
                                      ${student.user_status === 'perm' ? 
                                          `<button class="btn btn-warning btn-sm freeze-user-btn" data-user-id="${student.id}" data-user-type="student" title="تجميد"><i class="fas fa-pause fa-fw"></i></button>` : 
                                          `<button class="btn btn-success btn-sm unfreeze-user-btn" data-user-id="${student.id}" data-user-type="student" title="إلغاء التجميد"><i class="fas fa-play fa-fw"></i></button>`}
                                  </td>`;
              });
          }
          renderPagination(deptStudentsPaginationDiv, studentsData.page, studentsData.total, studentsData.limit, 'students');
          setLoadingState(false, deptStudentsLoading);
          deptStudentsListDiv.style.display = 'block';
      }

    function displayDepartmentTeachers(teachersData) {
        if (!deptTeachersTbody || !deptTeachersListDiv) return;
        deptTeachersTbody.innerHTML = '';
         if (!teachersData.data || teachersData.data.length === 0) {
             deptTeachersTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3 small">لا يوجد تدريسيين في القسم.</td></tr>';
         } else {
             teachersData.data.forEach(teacher => {
                 const row = deptTeachersTbody.insertRow();
                 row.innerHTML = `<td>${teacher.id}</td>
                                 <td>${escapeHtml(teacher.first_name||'')} ${escapeHtml(teacher.last_name||'')}</td>
                                 <td>${escapeHtml(teacher.academic_title||'')}</td>
                                 <td>${escapeHtml(teacher.Email||'N/A')}</td>
                                 <td>${formatUserStatus(teacher.user_status)}</td>
                                 <td class="table-action-btns">
                                     <button class="btn btn-info btn-sm edit-teacher-btn" data-user-id="${teacher.id}" title="تعديل"><i class="fas fa-edit fa-fw"></i></button>
                                     ${teacher.user_status === 'perm' ? 
                                         `<button class="btn btn-warning btn-sm freeze-user-btn" data-user-id="${teacher.id}" data-user-type="teacher" title="تجميد"><i class="fas fa-pause fa-fw"></i></button>` : 
                                         `<button class="btn btn-success btn-sm unfreeze-user-btn" data-user-id="${teacher.id}" data-user-type="teacher" title="إلغاء التجميد"><i class="fas fa-play fa-fw"></i></button>`}
                                 </td>`;
             });
         }
         renderPagination(deptTeachersPaginationDiv, teachersData.page, teachersData.total, teachersData.limit, 'teachers');
         setLoadingState(false, deptTeachersLoading);
         deptTeachersListDiv.style.display = 'block';
     }

     function displayDepartmentSubjects(subjectsData) {
        if (!deptSubjectsTbody || !deptSubjectsListDiv) return;
        deptSubjectsTbody.innerHTML = '';
         if (!subjectsData.data || subjectsData.data.length === 0) {
             deptSubjectsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-3 small">لا توجد مواد دراسية.</td></tr>';
         } else {
             subjectsData.data.forEach(subject => {
                 const row = deptSubjectsTbody.insertRow();
                 row.innerHTML = `<td>${subject.course_id}</td>
                                 <td>${escapeHtml(subject.course_name||'N/A')}</td>
                                 <td>${escapeHtml(subject.stage||'N/A')}</td>
                                 <td>${escapeHtml(subject.semester||'N/A')}</td>
                                 <td>${escapeHtml(subject.teachers||'')|| '<span class="text-muted small">لم يتم التعيين</span>'}</td>
                                 <td class="table-action-btns">
                                     <button class="btn btn-warning btn-sm edit-subject-btn" data-course-id="${subject.course_id}" data-bs-toggle="modal" data-bs-target="#subjectModal" title="تعديل"><i class="fas fa-edit fa-fw"></i></button>
                                     <button class="btn btn-danger btn-sm delete-subject-btn" data-course-id="${subject.course_id}" title="حذف"><i class="fas fa-trash fa-fw"></i></button>
                                 </td>`;
             });
         }
         setLoadingState(false, deptSubjectsLoading); deptSubjectsListDiv.style.display = 'block';
         renderPagination(deptSubjectsPaginationDiv, subjectsData.page, subjectsData.total, subjectsData.limit, 'subjects');
     }

    // --- Load Data Function ---
    async function loadData(page = 1, limit = defaultLimit, section = 'all') {
        console.log(`Loading data for section: ${section}, page: ${page}`);
        let url = `${dataEndpoint}?page=${page}&limit=${limit}`;
        // TODO: Add filter/sort parameters to URL based on current state
        // Example: url += `&status=${currentFilterStatus}&sortBy=${currentSortColumn}`

        // Show relevant loading indicators
        switch(section) {
            case 'pendingUsers': 
                showTableLoading(pendingStudentsTbody, pendingStudentsListDiv, pendingStudentsLoading, 6); 
                showTableLoading(pendingTeachersTbody, pendingTeachersListDiv, pendingTeachersLoading, 6); 
                break;
            case 'students': 
                showTableLoading(deptStudentsTbody, deptStudentsListDiv, deptStudentsLoading, 6); 
                break;
            case 'teachers': 
                showTableLoading(deptTeachersTbody, deptTeachersListDiv, deptTeachersLoading, 6); 
                break;
            case 'subjects': 
                showTableLoading(deptSubjectsTbody, deptSubjectsListDiv, deptSubjectsLoading, 6); 
                break;
            case 'dashboard':
                setLoadingState(false, dashboardLoading);
                if (dashboardSkeleton) {
                    dashboardSkeleton.style.display = 'flex';
                }
                if (dashboardStatsRow) {
                    dashboardStatsRow.style.display = 'none';
                }
                break;
            case 'all': 
                setLoadingState(true, dashboardLoading); 
                if (dashboardSkeleton) {
                    dashboardSkeleton.style.display = 'flex';
                }
                if (dashboardStatsRow) {
                    dashboardStatsRow.style.display = 'none';
                }
                showTableLoading(pendingStudentsTbody, pendingStudentsListDiv, pendingStudentsLoading, 6); 
                showTableLoading(pendingTeachersTbody, pendingTeachersListDiv, pendingTeachersLoading, 6); 
                showTableLoading(deptStudentsTbody, deptStudentsListDiv, deptStudentsLoading, 6); 
                showTableLoading(deptTeachersTbody, deptTeachersListDiv, deptTeachersLoading, 6); 
                showTableLoading(deptSubjectsTbody, deptSubjectsListDiv, deptSubjectsLoading, 6); 
                break;
        }
        showMessage('', false);

        try {
            // Start refresh button animation if applicable
            const refreshBtn = getRefreshButtonForSection(section);
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.querySelector('i').classList.add('btn-refresh-spin');
            }
            
            const response = await fetchWithCsrf(url);
            if (!response.ok) throw new Error(`Network error ${response.status}`);
            const data = await response.json();
            if (data.logout) { window.location.href = loginPageUrl; return; }
            if (!data.success) throw new Error(data.message || "Failed to load data");

            dataCache = {...dataCache, ...data}; // Update cache

            if (data.dashboardStats && (section === 'all' || section === 'dashboard')) displayDashboardStats(data.dashboardStats);
            if (data.pendingUsers && (section === 'all' || section === 'pendingUsers')) displayPendingUsers(data.pendingUsers);
            if (data.departmentStudents && (section === 'all' || section === 'students')) displayDepartmentStudents(data.departmentStudents);
            if (data.departmentTeachers && (section === 'all' || section === 'teachers')) { displayDepartmentTeachers(data.departmentTeachers); populateTeacherDropdown(data.departmentTeachers.data); } // Populate dropdown when teachers load
            if (data.departmentSubjects && (section === 'all' || section === 'subjects')) displayDepartmentSubjects(data.departmentSubjects);

        } catch (error) {
            console.error(`Error loading section ${section}:`, error);
            showMessage(`Error loading data: ${error.message}`, true);
            setLoadingState(false, dashboardLoading); 
            if (dashboardSkeleton) dashboardSkeleton.style.display = 'none';
            setLoadingState(false, pendingStudentsLoading); 
            setLoadingState(false, pendingTeachersLoading); 
            setLoadingState(false, deptStudentsLoading); 
            setLoadingState(false, deptTeachersLoading); 
            setLoadingState(false, deptSubjectsLoading);
        } finally {
            // Stop refresh button animation
            const refreshBtn = getRefreshButtonForSection(section);
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.querySelector('i').classList.remove('btn-refresh-spin');
            }
        }
    }
    
    // Helper function to get the refresh button for a specific section
    function getRefreshButtonForSection(section) {
        switch(section) {
            case 'dashboard': return document.getElementById('refreshDashboardBtn');
            case 'pendingUsers': return document.getElementById('refreshRequestsBtn');
            case 'students': return document.getElementById('refreshStudentsBtn');
            case 'teachers': return document.getElementById('refreshTeachersBtn');
            case 'subjects': return document.getElementById('refreshSubjectsBtn');
            case 'all': return null; // No specific button for all sections
            default: return null;
        }
    }

    // --- Populate Teacher Dropdown for Subject Modal ---
    function populateTeacherDropdown(teachers) {
         if(subjectTeachersSelect && teachers){
             subjectTeachersSelect.innerHTML = '<option value="" selected>-- اختر تدريسي --</option>'; // Reset
             teachers.forEach(t => {
                 // Only include active teachers
                 if (t.user_status === 'perm') {
                    subjectTeachersSelect.add(new Option(`${t.academic_title || ''} ${t.first_name} ${t.last_name}`.trim(), t.id));
                 }
             });
         }
    }

    // --- Navbar Profile ---
    async function loadNavbarProfile() { 
        const hodNameNavElement = document.getElementById('hodNameNav');
        const profilePicElement = document.getElementById('userProfilePicNav');
        if (!hodNameNavElement) return;
        
        try {
            const response = await fetchWithCsrf('../backend/get_user_profile.php');
            if (!response.ok) throw new Error(`Network error ${response.status}`);
            const data = await response.json();
            
            if (data.success && data.profile) {
                const profile = data.profile;
                let displayName = 'رئيس القسم';
                
                if (profile.academic_title && (profile.first_name || profile.last_name)) {
                    displayName = `${profile.academic_title || ''} ${profile.first_name || ''} ${profile.last_name || ''}`.trim();
                } else if (profile.first_name || profile.last_name) {
                    displayName = `${profile.first_name || ''} ${profile.last_name || ''}`.trim();
                }
                
                hodNameNavElement.textContent = displayName;
                
                // Update profile picture if available
                if (profilePicElement && profile.profile_pic) {
                    profilePicElement.src = profile.profile_pic;
                    
                    // Add error handler for image loading failures
                    profilePicElement.onerror = function() {
                        // Fallback to default image if the profile pic fails to load
                        this.src = 'assets/images/3.jpg';
                        console.error('Failed to load profile picture, using default image');
                    };
                }
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            // Set default image on error
            if (profilePicElement) {
                profilePicElement.src = 'assets/images/3.jpg';
            }
        }
    }

    // --- ACTION EVENT LISTENERS ---

    // Tab activation - load data for the activated tab
    const tabLinks = document.querySelectorAll('#sidebarMenu .nav-link[data-bs-toggle="tab"]');
    tabLinks.forEach(tabLink => {
        tabLink.addEventListener('show.bs.tab', event => {
            const targetId = event.target.getAttribute('data-bs-target');
            pageTitleElement.textContent = event.target.textContent.trim().replace(/[\d]+$/, '').trim(); // Update page title
             switch(targetId) {
                 case '#dashboardContent': currentSection='dashboard'; /* Already loaded */ break;
                 case '#requestsContent': currentSection='pendingUsers'; loadData(currentPageState.pendingUsers, defaultLimit, currentSection); break;
                 case '#manageStudentsContent': currentSection='students'; loadData(currentPageState.students, defaultLimit, currentSection); break;
                 case '#manageTeachersContent': currentSection='teachers'; loadData(currentPageState.teachers, defaultLimit, currentSection); break;
                 case '#manageSubjectsContent': currentSection='subjects'; loadData(currentPageState.subjects, defaultLimit, currentSection); break;
             }
        });
    });


    // Central handler for pagination clicks
    tabContent.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' && e.target.closest('.pagination')) {
            e.preventDefault();
            const pageLink = e.target.closest('.page-link');
            if (pageLink && !pageLink.parentElement.classList.contains('disabled') && !pageLink.parentElement.classList.contains('active')) {
                const page = parseInt(pageLink.dataset.page);
                const sectionKey = pageLink.dataset.section;
                if (page && sectionKey && currentPageState.hasOwnProperty(sectionKey)) {
                    currentPageState[sectionKey] = page; // Update current page for that section
                    loadData(page, defaultLimit, sectionKey); // Reload data for that section
                }
            }
        }
    });

    // Approve/Reject Users
    const requestsContent = document.getElementById('requestsContent');
    if(requestsContent){
        requestsContent.addEventListener('click', async (e) => {
             const targetUserId = e.target.closest('.approve-user-btn')?.dataset.userId || e.target.closest('.reject-user-btn')?.dataset.userId;
             const action = e.target.closest('.approve-user-btn') ? 'approve' : (e.target.closest('.reject-user-btn') ? 'reject' : null);
             if (!action || !targetUserId || !confirm(`هل أنت متأكد من ${action === 'approve' ? 'قبول' : 'رفض'} طلب المستخدم ${targetUserId}?`)) return;
             const button = e.target.closest('button'); button.disabled = true;
             showMessage('جاري المعالجة...', false);
              try {
                 const response = await fetchWithCsrf(approveActionEndpoint, { 
                    method: 'POST', 
                    body: JSON.stringify({ 
                        userId: targetUserId, 
                        action: action,
                        csrf_token: getCsrfToken() 
                    }) 
                });
                 const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Failed');
                 showMessage(`تم ${action === 'approve' ? 'قبول' : 'رفض'} المستخدم بنجاح.`, true);
                 loadData(currentPageState.pendingUsers, defaultLimit, 'pendingUsers'); // Reload relevant list
                 // Also reload student/teacher lists as applicable
                 if (action === 'approve') {
                     const role = e.target.closest('tr')?.querySelector('.approve-user-btn')?.dataset.roleId || e.target.closest('tr')?.querySelector('.reject-user-btn')?.dataset.roleId; // Get role if needed
                     if (role == '7') loadData(1, defaultLimit, 'students');
                     else if (role == '6') loadData(1, defaultLimit, 'teachers');
                 }
             } catch (error) { showMessage(`خطأ: ${error.message}`, true); button.disabled = false; }
        });
    }

    // Freeze/Unfreeze Users
     function handleFreezeClick(event, listContainerId, userType, sectionKey) {
        const freezeBtn = event.target.closest('.freeze-user-btn, .unfreeze-user-btn');
        if (!freezeBtn) return;
        
        const targetUserId = freezeBtn.dataset.userId;
        const action = freezeBtn.classList.contains('freeze-user-btn') ? 'freeze' : 'unfreeze';
        const newStatus = action === 'freeze' ? 'frozen' : 'perm';
        
        if (!targetUserId || !confirm(`هل أنت متأكد من ${action === 'freeze' ? 'تجميد' : 'إلغاء تجميد'} حساب ${userType} ${targetUserId}?`)) return;
        
        freezeBtn.disabled = true; 
        showMessage('جاري المعالجة...', false);
        fetchWithCsrf(statusActionEndpoint, { 
            method: 'POST', 
            body: JSON.stringify({ 
                userId: targetUserId, 
                status: newStatus,
                csrf_token: getCsrfToken() 
            }) 
        })
            .then(res => res.ok ? res.json() : res.text().then(text => { throw new Error(text || 'Server error')}))
            .then(result => { if (!result.success) throw new Error(result.message || 'Failed'); showMessage(`${userType} status updated.`, true); loadData(currentPageState[sectionKey], defaultLimit, sectionKey); }) // Reload current page of relevant section
            .catch(error => { showMessage(`خطأ: ${error.message}`, true); freezeBtn.disabled = false; });
     }
     if(deptStudentsListDiv) deptStudentsListDiv.addEventListener('click', (e) => handleFreezeClick(e, 'deptStudentsList', 'student', 'students'));
     if(deptTeachersListDiv) deptTeachersListDiv.addEventListener('click', (e) => handleFreezeClick(e, 'deptTeachersList', 'teacher', 'teachers'));

    // View Documents
     const allTablesContainer = document.getElementById('hodTabContent'); // Listen higher up for any view doc button
     if (allTablesContainer && viewDocModalInstance) {
         allTablesContainer.addEventListener('click', async (e) => {
             const viewBtn = e.target.closest('.view-doc-btn'); if (!viewBtn) return;
             const targetUserId = viewBtn.dataset.userId;
             const nameCellIndex = viewBtn.closest('table')?.querySelector('thead th:first-child')?.textContent === 'ID' ? 1 : 0; // Guess name column index
             const name = viewBtn.closest('tr')?.cells[nameCellIndex]?.textContent ?? `User ${targetUserId}`;
             if (!targetUserId) return;

             if(docUserInfo) docUserInfo.textContent = `تحميل مستمسكات ${name}...`; 
             if(docImageContainer) docImageContainer.innerHTML = ''; 
             if(docErrorMessage) docErrorMessage.style.display = 'none'; 
             if(docLoadingIndicator) docLoadingIndicator.style.display = 'block';
             viewDocModalInstance.show();

             try {
                 const response = await fetchWithCsrf(`${documentsEndpoint}?user_id=${targetUserId}&doc_type=all`);
                 if (!response.ok) throw new Error(`Network error ${response.status}`); 
                 const data = await response.json(); 
                 if (!data.success || !data.documents) throw new Error(data.message || 'Failed to get docs');
                 
                 if(docUserInfo) docUserInfo.textContent = `مستمسكات ${name}`; 
                 if(docImageContainer) docImageContainer.innerHTML = ''; 
                 let docsFound = false;
                 
                 for (const [type, url] of Object.entries(data.documents)) {
                     if (url) { 
                         docsFound = true; 
                         const col = document.createElement('div'); 
                         col.className = 'col-md-6 mb-3'; 
                         const title = type.replace(/_/g,' ').replace('id',' ID').replace('front','Front').replace('back','Back'); 
                         col.innerHTML = `<h6 class="text-center text-muted small">${title}</h6>
                                         <div class="text-center">
                                             <img src="${url}" class="img-fluid rounded border document-viewer-img" alt="${title}">
                                             <div class="mt-2">
                                                 <a href="${url}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                     <i class="fas fa-external-link-alt"></i> فتح في نافذة جديدة
                                                 </a>
                                             </div>
                                         </div>`; 
                         docImageContainer.appendChild(col); 
                     }
                 }
                 if (!docsFound) { 
                     throw new Error("لم يتم العثور على مستمسكات لهذا المستخدم."); 
                 }
             } catch (error) { 
                 if(docUserInfo) docUserInfo.textContent = `مستمسكات ${name}`; 
                 if(docErrorMessage){ 
                     docErrorMessage.textContent = `خطأ تحميل المستمسكات: ${error.message}`; 
                     docErrorMessage.style.display = 'block'; 
                 }
             }
             finally { 
                 if(docLoadingIndicator) docLoadingIndicator.style.display = 'none'; 
             }
         });
     }

      // Subject Management: Add/Edit Form Submission
      if (subjectForm && subjectModal) {
          subjectModalElement.addEventListener('show.bs.modal', (e) => { /* Keep prefill/reset logic */ });
          subjectForm.addEventListener('submit', async (e) => { /* Keep submit logic */ });
      }

      // TODO: Subject Management: Delete Button Listener
       const subjectsListContainer = document.getElementById('deptSubjectsList');
       if(subjectsListContainer) {
           subjectsListContainer.addEventListener('click', async (e) => {
               const deleteBtn = e.target.closest('.delete-subject-btn');
               if(!deleteBtn) return;
               const courseId = deleteBtn.dataset.courseId;
               const courseName = deleteBtn.closest('tr')?.cells[1]?.textContent ?? `ID ${courseId}`;
               if (!courseId || !confirm(`هل أنت متأكد من حذف المادة "${courseName}"؟ سيتم حذف جميع المواد والواجبات المرتبطة بها.`)) return;

               deleteBtn.disabled = true; showMessage('جاري الحذف...', false);
                try {
                    const response = await fetchWithCsrf(subjectActionEndpoint, { method: 'POST', body: JSON.stringify({ action: 'delete_subject', course_id: courseId }) });
                    const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Failed');
                    showMessage('تم حذف المادة بنجاح.', true);
                    loadData(currentPageState.subjects, defaultLimit, 'subjects'); // Refresh
                } catch (error) { showMessage(`خطأ الحذف: ${error.message}`, true); deleteBtn.disabled = false; }
           });
           // TODO: Listener for Edit button (similar to Add button logic for modal trigger)
       }

    // --- Edit Teacher Button Click ---
    if (deptTeachersListDiv) {
        deptTeachersListDiv.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('.edit-teacher-btn');
            if (!editBtn) return;
            
            const userId = editBtn.dataset.userId;
            if (!userId) return;
            
            try {
                showMessage('جاري تحميل بيانات التدريسي...', false);
                
                const response = await fetchWithCsrf(editUserEndpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'get_user',
                        user_id: userId
                    })
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to load teacher data');
                }
                
                const teacher = result.user;
                
                // Fill the form with teacher data
                document.getElementById('teacherEditId').value = teacher.id;
                document.getElementById('teacherFirstName').value = teacher.first_name || '';
                document.getElementById('teacherLastName').value = teacher.last_name || '';
                document.getElementById('teacherThirdName').value = teacher.third_name || '';
                document.getElementById('teacherMotherFirstName').value = teacher.mother_first_name || '';
                document.getElementById('teacherMotherSecondName').value = teacher.mother_second_name || '';
                document.getElementById('teacherBirthday').value = teacher.birthday || '';
                document.getElementById('teacherGender').value = teacher.gender || '';
                document.getElementById('teacherCity').value = teacher.city || '';
                document.getElementById('teacherNationality').value = teacher.nationality || 'Iraq';
                
                // Set the academic title dropdown value
                const academicTitleSelect = document.getElementById('teacherAcademicTitle');
                const academicTitle = teacher.academic_title || '';
                
                // First try to find an exact match
                let optionFound = false;
                for (let i = 0; i < academicTitleSelect.options.length; i++) {
                    if (academicTitleSelect.options[i].value === academicTitle) {
                        academicTitleSelect.selectedIndex = i;
                        optionFound = true;
                        break;
                    }
                }
                
                // If no match found and we have a value, select the first option and show a warning
                if (!optionFound && academicTitle) {
                    console.warn(`Academic title "${academicTitle}" not found in dropdown options`);
                    academicTitleSelect.selectedIndex = 0; // Select the default option
                }
                
                document.getElementById('teacherEmail').value = teacher.email || '';
                
                // Show the modal
                editTeacherModal.show();
                showMessage('', false); // Clear message
                
            } catch (error) {
                console.error('Error loading teacher data:', error);
                showMessage(`خطأ تحميل بيانات التدريسي: ${error.message}`, true);
            }
        });
    }
    
    // --- Edit Student Button Click ---
    if (deptStudentsListDiv) {
        deptStudentsListDiv.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('.edit-student-btn');
            if (!editBtn) return;
            
            const userId = editBtn.dataset.userId;
            if (!userId) return;
            
            try {
                showMessage('جاري تحميل بيانات الطالب...', false);
                
                const response = await fetchWithCsrf(editUserEndpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'get_user',
                        user_id: userId
                    })
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to load student data');
                }
                
                const student = result.user;
                
                // Fill the form with student data
                document.getElementById('studentEditId').value = student.id;
                document.getElementById('studentFirstName').value = student.first_name || '';
                document.getElementById('studentLastName').value = student.last_name || '';
                document.getElementById('studentThirdName').value = student.third_name || '';
                document.getElementById('studentMotherFirstName').value = student.mother_first_name || '';
                document.getElementById('studentMotherSecondName').value = student.mother_second_name || '';
                document.getElementById('studentBirthday').value = student.birthday || '';
                document.getElementById('studentGender').value = student.gender || '';
                document.getElementById('studentCity').value = student.city || '';
                document.getElementById('studentNationality').value = student.nationality || 'Iraq';
                document.getElementById('studentEmail').value = student.email || '';
                document.getElementById('studentStage').value = student.stage || '';
                document.getElementById('studentDegree').value = student.degree || '';
                document.getElementById('studentStudyMode').value = student.study_mode || '';
                
                // Show the modal
                editStudentModal.show();
                showMessage('', false); // Clear message
                
            } catch (error) {
                console.error('Error loading student data:', error);
                showMessage(`خطأ تحميل بيانات الطالب: ${error.message}`, true);
            }
        });
    }
    
    // --- Student Form Submit ---
    if (editStudentForm) {
        editStudentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!editStudentForm.checkValidity()) {
                e.stopPropagation();
                editStudentForm.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(editStudentForm);
            const formDataObj = Object.fromEntries(formData.entries());
            
            try {
                showMessage('جاري حفظ البيانات...', false, studentEditMessage);
                
                const response = await fetchWithCsrf(editUserEndpoint, {
                    method: 'POST',
                    body: JSON.stringify(formDataObj)
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to update student data');
                }
                
                showMessage('تم تحديث بيانات الطالب بنجاح', false, studentEditMessage);
                
                // Reload the students data after a short delay
                setTimeout(() => {
                    loadData(currentPageState.students, defaultLimit, 'students');
                    editStudentModal.hide();
                    showMessage('تم تحديث بيانات الطالب بنجاح', false);
                }, 1500);
                
            } catch (error) {
                console.error('Error updating student:', error);
                showMessage(`خطأ تحديث البيانات: ${error.message}`, true, studentEditMessage);
            }
        });
    }
    
    // --- Teacher Form Submit ---
    if (editTeacherForm) {
        editTeacherForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!editTeacherForm.checkValidity()) {
                e.stopPropagation();
                editTeacherForm.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(editTeacherForm);
            const formDataObj = Object.fromEntries(formData.entries());
            
            try {
                showMessage('جاري حفظ البيانات...', false, teacherEditMessage);
                
                const response = await fetchWithCsrf(editUserEndpoint, {
                    method: 'POST',
                    body: JSON.stringify(formDataObj)
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to update teacher data');
                }
                
                showMessage('تم تحديث بيانات التدريسي بنجاح', false, teacherEditMessage);
                
                // Reload the teachers data after a short delay
                setTimeout(() => {
                    loadData(currentPageState.teachers, defaultLimit, 'teachers');
                    editTeacherModal.hide();
                    showMessage('تم تحديث بيانات التدريسي بنجاح', false);
                }, 1500);
                
            } catch (error) {
                console.error('Error updating teacher:', error);
                showMessage(`خطأ تحديث البيانات: ${error.message}`, true, teacherEditMessage);
            }
        });
    }
    
    // --- Update Subject Form Submit to Handle File Upload ---
    if (subjectForm) {
        subjectForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!subjectForm.checkValidity()) {
                e.stopPropagation();
                subjectForm.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(subjectForm);
            const isEdit = formData.get('course_id') ? true : false;
            formData.append('action', isEdit ? 'update_subject' : 'create_subject');
            
            try {
                showMessage('جاري حفظ المادة...', false, subjectMessage);
                saveSubjectBtn.disabled = true;
                
                const response = await fetch(subjectActionEndpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': getCsrfToken()
                    }
                });
                
                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to save subject');
                }
                
                showMessage(`تم ${isEdit ? 'تحديث' : 'إضافة'} المادة بنجاح`, false, subjectMessage);
                
                // Reload the subjects data after a short delay
                setTimeout(() => {
                    loadData(currentPageState.subjects, defaultLimit, 'subjects');
                    subjectModal.hide();
                    showMessage(`تم ${isEdit ? 'تحديث' : 'إضافة'} المادة بنجاح`, false);
                    
                    // Reset the form
                    subjectForm.reset();
                    subjectForm.classList.remove('was-validated');
                    subjectImagePreview.style.display = 'none';
                }, 1500);
                
            } catch (error) {
                console.error('Error saving subject:', error);
                showMessage(`خطأ حفظ المادة: ${error.message}`, true, subjectMessage);
            } finally {
                saveSubjectBtn.disabled = false;
            }
        });
    }
    
    // --- Edit Subject Button Click ---
    if (subjectModalElement) {
        subjectModalElement.addEventListener('show.bs.modal', async (e) => {
            const button = e.relatedTarget;
            const isEdit = button && button.classList.contains('edit-subject-btn');
            
            // Reset form and update modal title
            subjectForm.reset();
            subjectForm.classList.remove('was-validated');
            subjectModalLabel.textContent = isEdit ? 'تعديل مادة' : 'إضافة مادة';
            subjectImagePreview.style.display = 'none';
            
            if (isEdit) {
                const courseId = button.dataset.courseId;
                if (!courseId) return;
                
                try {
                    showMessage('جاري تحميل بيانات المادة...', false, subjectMessage);
                    
                    const response = await fetchWithCsrf(subjectActionEndpoint, {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'get_subject',
                            course_id: courseId
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Failed to load subject data');
                    }
                    
                    const subject = result.subject;
                    
                    // Fill the form with subject data
                    subjectEditId.value = subject.course_id;
                    subjectNameInput.value = subject.course_name || '';
                    subjectStageSelect.value = subject.stage || '';
                    subjectSemesterSelect.value = subject.semester || '';
                    subjectDescription.value = subject.description || '';
                    
                    // Set teacher if available
                    if (subject.teacher_id && subjectTeachersSelect) {
                        subjectTeachersSelect.value = subject.teacher_id;
                    }
                    
                    // Show image preview if available
                    if (subject.image_url && subjectImagePreview) {
                        const img = subjectImagePreview.querySelector('img');
                        img.src = subject.image_url;
                        subjectImagePreview.style.display = 'block';
                    }
                    
                    showMessage('', false, subjectMessage); // Clear message
                    
                } catch (error) {
                    console.error('Error loading subject data:', error);
                    showMessage(`خطأ تحميل بيانات المادة: ${error.message}`, true, subjectMessage);
                }
            } else {
                // Clear form for new subject
                subjectEditId.value = '';
                showMessage('', false, subjectMessage); // Clear message
            }
        });
    }

    // --- Setup Refresh Buttons ---
    const refreshDashboardBtn = document.getElementById('refreshDashboardBtn');
    const refreshRequestsBtn = document.getElementById('refreshRequestsBtn');
    const refreshStudentsBtn = document.getElementById('refreshStudentsBtn');
    const refreshTeachersBtn = document.getElementById('refreshTeachersBtn');
    const refreshSubjectsBtn = document.getElementById('refreshSubjectsBtn');
    
    if (refreshDashboardBtn) {
        refreshDashboardBtn.addEventListener('click', () => loadData(1, defaultLimit, 'dashboard'));
    }
    
    if (refreshRequestsBtn) {
        refreshRequestsBtn.addEventListener('click', () => loadData(1, defaultLimit, 'pendingUsers'));
    }
    
    if (refreshStudentsBtn) {
        refreshStudentsBtn.addEventListener('click', () => loadData(1, defaultLimit, 'students'));
    }
    
    if (refreshTeachersBtn) {
        refreshTeachersBtn.addEventListener('click', () => loadData(1, defaultLimit, 'teachers'));
    }
    
    if (refreshSubjectsBtn) {
        refreshSubjectsBtn.addEventListener('click', () => loadData(1, defaultLimit, 'subjects'));
    }
    
    // --- Setup Pagination Event Listeners ---
    // Add event delegation for pagination controls
    document.addEventListener('click', function(e) {
        // Check if the clicked element is a pagination link
        const pageLink = e.target.closest('.page-link');
        if (!pageLink) return;
        
        e.preventDefault();
        
        // Get the page number and section from the data attributes
        const page = parseInt(pageLink.dataset.page);
        const section = pageLink.dataset.section;
        
        if (!isNaN(page) && section) {
            // Store the current page state for this section
            currentPageState[section] = page;
            
            // Load the data for the selected page and section
            loadData(page, defaultLimit, section);
            
            // Scroll to the top of the table
            const tableContainer = document.querySelector(`#${section}Content`) || 
                                  document.querySelector(`#manage${section.charAt(0).toUpperCase() + section.slice(1)}Content`);
            if (tableContainer) {
                tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });

    // --- Initial Load ---
    loadNavbarProfile();
    loadData(); // Load initial data (page 1 of all sections)
});