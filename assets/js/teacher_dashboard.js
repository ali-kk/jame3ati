// frontend/assets/js/teacher_dashboard.js
document.addEventListener('DOMContentLoaded', () => {
    console.log("Teacher Dashboard JS Loaded");

    // --- Keep existing handleMockSubmit function ---
    const handleMockSubmit = (formId, messageId) => {
        const form = document.getElementById(formId);
        const messageDiv = document.getElementById(messageId);
        if (!form || !messageDiv) { console.warn(`Form or Message Div not found for ${formId}`); return; }
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            form.classList.remove('was-validated');
            messageDiv.className = 'alert d-none'; messageDiv.textContent = '';
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                messageDiv.className = 'alert alert-warning d-block';
                messageDiv.textContent = 'الرجاء ملء جميع الحقول المطلوبة واختيار ملف صالح.';
                if (formId === 'uploadVideoForm') {
                     const videoInput = document.getElementById('videoFileInput');
                     if (videoInput && videoInput.files.length === 0 && videoInput.hasAttribute('required')) { videoInput.classList.add('is-invalid'); }
                } return;
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn?.querySelector('.spinner-border');
            const btnText = submitBtn?.querySelector('.button-text');
            const originalText = btnText?.textContent || 'Submit';
            spinner?.classList.remove('d-none');
            if(btnText) btnText.textContent = 'جاري...';
            if(submitBtn) submitBtn.disabled = true;
            messageDiv.className = 'alert alert-info d-block'; messageDiv.textContent = 'جاري معالجة الطلب...';
            setTimeout(() => {
                messageDiv.className = 'alert alert-success d-block'; messageDiv.textContent = 'تمت العملية بنجاح!';
                spinner?.classList.add('d-none');
                setTimeout(() => {
                    const modalElement = form.closest('.modal'); const modalInstance = bootstrap.Modal.getInstance(modalElement); modalInstance?.hide();
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        if(btnText) btnText.textContent = originalText; if(submitBtn) submitBtn.disabled = false; messageDiv.className = 'alert d-none'; form.reset(); form.classList.remove('was-validated');
                    }, { once: true });
                }, 1500);
            }, 1000);
        });
    };
    // --- End handleMockSubmit ---

    // Apply mock submit handler to all upload/create forms
    handleMockSubmit('uploadFileForm', 'uploadFileMessage');
    handleMockSubmit('uploadVideoForm', 'uploadVideoMessage');
    handleMockSubmit('createAssignmentForm', 'createAssignmentMessage');

    // --- Keep existing setModalCourseName function ---
    const setModalCourseName = (modalElement, triggerButton) => {
        if (!modalElement || !triggerButton) return;
        // Use 'any' as default if no specific course name needed (like create assignment from main tab)
        const courseName = triggerButton.getAttribute('data-course-name') || 'any';
        const placeholder = modalElement.querySelector('.course-name-placeholder');
        if(placeholder) {
             // Only display specific course name if it's not 'any'
             placeholder.textContent = (courseName !== 'any') ? courseName : 'مادة جديدة';
        }
        modalElement.dataset.courseName = courseName || 'any'; // Store it regardless
    };
    // --- End setModalCourseName ---

    // Listener for Upload/Create Modals & Manage Content Modal show events
    const allModals = ['uploadFileModal', 'uploadVideoModal', 'createAssignmentModal', 'manageContentModal'];
    allModals.forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            modalElement.addEventListener('show.bs.modal', (event) => {
                if (event.relatedTarget) { setModalCourseName(modalElement, event.relatedTarget); }
                // Reset messages in manage content modal specifically
                if (modalId === 'manageContentModal') {
                    const manageMessageDiv = document.getElementById('manageContentMessage');
                    if(manageMessageDiv) manageMessageDiv.className = 'alert d-none';
                    console.log(`Opened manage content for: ${modalElement.dataset.courseName}`);
                     // Activate the first tab in manage content modal when it opens
                     const firstTabButton = manageModalElement.querySelector('#manageContentTab button');
                     if (firstTabButton) {
                         const tab = new bootstrap.Tab(firstTabButton);
                         tab.show();
                     }
                    // TODO: Reset mock lists or fetch real data
                }
            });
        } else { console.warn(`Modal element not found: #${modalId}`); }
    });


    // --- Event Delegation for Manage Content Modal ---
    const manageModalElement = document.getElementById('manageContentModal');
    if (manageModalElement) {
        const manageMessageDiv = document.getElementById('manageContentMessage');
        manageModalElement.addEventListener('click', (event) => {
            const targetButton = event.target.closest('button');
            if (!targetButton) return;
            const itemId = targetButton.dataset.itemId; const itemType = targetButton.dataset.itemType;
            if(manageMessageDiv) manageMessageDiv.className = 'alert d-none';
            if (targetButton.classList.contains('edit-item-btn')) { alert(`(Mockup) Edit ${itemType} with ID: ${itemId}`); }
            else if (targetButton.classList.contains('delete-item-btn')) {
                const itemName = targetButton.closest('li')?.querySelector('span')?.textContent || `Item ${itemId}`;
                if (confirm(`(Mockup) هل أنت متأكد من حذف هذا العنصر:\n${itemName}؟`)) {
                    targetButton.closest('li')?.remove();
                    if(manageMessageDiv){ manageMessageDiv.className = 'alert alert-warning d-block'; manageMessageDiv.textContent = `(Mockup) تم حذف العنصر ${itemName}.`; }
                }
            }
            // Listener for the (now removed?) view-submissions-btn inside the modal
             else if (targetButton.classList.contains('view-submissions-btn')) {
                  alert(`(Mockup) View submissions for assignment ID: ${itemId} (from modal)`);
             }
        });
    } else { console.warn("Manage Content Modal element not found: #manageContentModal"); }
    // --- End Manage Content Modal Listeners ---


    // --- Listeners for Main Assignments Tab ---
    const assignmentsMainPane = document.getElementById('assignmentsMainContent');
    const mainSubjectFilter = document.getElementById('mainSubjectFilter');
    const mainSubmissionsTableBody = document.getElementById('mainSubmissionsTableBody');

    if (assignmentsMainPane && mainSubjectFilter && mainSubmissionsTableBody) {

        // Filter Listener
        mainSubjectFilter.addEventListener('change', () => {
            const selectedValue = mainSubjectFilter.value;
            const tableRows = mainSubmissionsTableBody.querySelectorAll('tr[data-subject]');
            const noResultsRow = mainSubmissionsTableBody.querySelector('.no-results-row');
            let visibleCount = 0;

            // No alert needed here usually, filtering should be visual
            // alert(`(Mockup) Filtering main submissions list for: ${selectedValue}`);

            tableRows.forEach(row => {
                if (selectedValue === 'all' || row.dataset.subject === selectedValue) {
                    row.style.display = ''; // Show row using default table display
                    visibleCount++;
                } else {
                    row.style.display = 'none'; // Hide row
                }
            });

            // Show/hide "no results" row
            if (noResultsRow) {
                // Show if count is 0 AND there are actually rows to filter (i.e., not empty initially)
                noResultsRow.style.display = (visibleCount === 0 && tableRows.length > 0) ? '' : 'none';
            }
        });

        // Event delegation for buttons within the main submissions table
        mainSubmissionsTableBody.addEventListener('click', (event) => {
            const targetButton = event.target.closest('button');
            if (!targetButton) return;

            const submissionId = targetButton.dataset.submissionId;
            const tableRow = targetButton.closest('tr');
            const studentName = tableRow?.querySelector('.student-name')?.textContent || 'الطالب';

            if (targetButton.classList.contains('main-view-submission-btn')) {
                alert(`(Mockup) عرض ملف التسليم ${submissionId} من الطالب: ${studentName}`);
                // Later: Implement file viewing logic (e.g., open in new modal/tab)
            } else if (targetButton.classList.contains('main-save-grade-btn')) {
                const gradeInput = tableRow?.querySelector('.main-grade-input');
                const grade = gradeInput?.value;

                // Reset potential invalid state
                gradeInput?.classList.remove('is-invalid');

                if (grade === '' || grade < 0 || grade > 100 || isNaN(grade)) {
                     alert("(Mockup) الرجاء إدخال درجة صالحة بين 0 و 100.");
                     gradeInput?.classList.add('is-invalid'); // Add BS invalid state
                    gradeInput?.focus();
                    return;
                }
                alert(`(Mockup) تم حفظ الدرجة ${grade} للتسليم ${submissionId}.`);
                // Later: Send grade to backend
                targetButton.innerHTML = '<i class="fas fa-check text-success fa-fw"></i>'; // Show checkmark
                targetButton.disabled = true; // Disable after saving
                if (gradeInput) gradeInput.disabled = true; // Disable input after saving
                // Optional: Remove checkmark after a delay
                // setTimeout(() => { targetButton.innerHTML = '<i class="fas fa-save fa-fw"></i>'; }, 2000);
            }
        });

    } else {
        console.warn("One or more elements for the main assignments tab were not found.");
    }
    // --- End Main Assignments Tab Listeners ---

}); // End DOMContentLoaded