// assets/js/assignment-upload.js

document.addEventListener('DOMContentLoaded', () => {
    console.log("Assignment Upload JS: Initializing.");

    // --- Elements specific to the upload modal ---
    const uploadModalElement = document.getElementById('assignmentUploadModal');
    const uploadForm = document.getElementById('assignmentUploadForm');
    const modalAssignmentTitle = document.getElementById('modalAssignmentTitle');
    const modalAssignmentIdInput = document.getElementById('modalAssignmentId');
    const submissionFileInput = document.getElementById('submissionFile');
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadProgressBar = uploadProgress ? uploadProgress.querySelector('.progress-bar') : null;
    const uploadResultMessage = document.getElementById('uploadResultMessage');
    const uploadSubmitButton = document.getElementById('uploadSubmitButton');

    let currentAssignmentId = null;
    let currentDeadlineTimestamp = 0;

    // Check if all required modal elements exist
    if (!uploadModalElement || !uploadForm || !modalAssignmentTitle || !modalAssignmentIdInput || !submissionFileInput || !uploadProgress || !uploadProgressBar || !uploadResultMessage || !uploadSubmitButton) {
        console.error("Assignment Upload JS: One or more modal elements are missing from the HTML. Upload functionality may fail.");
        // No return here, maybe only parts of the modal are broken
    }


    // --- Modal Event Listener (When modal is about to be shown) ---
    if (uploadModalElement) {
        uploadModalElement.addEventListener('show.bs.modal', function (event) {
            console.log("Assignment Upload JS: Modal show event triggered.");
            const button = event.relatedTarget; // Button that triggered the modal
            if (!button) return; // Exit if triggered programmatically without a button

            // Extract info from data-* attributes
            currentAssignmentId = button.getAttribute('data-assignment-id');
            const assignmentTitle = button.getAttribute('data-assignment-title');
            currentDeadlineTimestamp = parseInt(button.getAttribute('data-deadline-timestamp') || '0', 10);

            console.log(`Assignment Upload JS: Modal opened for Assignment ID: ${currentAssignmentId}, Title: ${assignmentTitle}, Deadline Timestamp: ${currentDeadlineTimestamp}`);

            // Update the modal's content
            if(modalAssignmentTitle) modalAssignmentTitle.textContent = assignmentTitle || 'N/A';
            if(modalAssignmentIdInput) modalAssignmentIdInput.value = currentAssignmentId || '';

            // Reset form state
            if(uploadForm) uploadForm.reset();
            if(uploadProgress) uploadProgress.style.display = 'none';
            if(uploadProgressBar) uploadProgressBar.style.width = '0%';
            if(uploadResultMessage) { uploadResultMessage.className = 'alert d-none'; uploadResultMessage.textContent = ''; }
            if(uploadSubmitButton) uploadSubmitButton.disabled = false;
            if(submissionFileInput) submissionFileInput.disabled = false;


             // Final client-side deadline check
             if (currentDeadlineTimestamp > 0 && Date.now() > currentDeadlineTimestamp) {
                 if(uploadResultMessage) {
                     uploadResultMessage.className = 'alert alert-danger d-block'; // Show it
                     uploadResultMessage.textContent = 'انتهى وقت التسليم لهذا الواجب.';
                 }
                  if(uploadSubmitButton) uploadSubmitButton.disabled = true;
                  if(submissionFileInput) submissionFileInput.disabled = true;
                  console.warn("Assignment Upload JS: Upload modal opened after deadline.");
             }

        });
    }


    // --- Form Submission Handler ---
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            console.log("Assignment Upload JS: Form submitted.");

             // Re-check deadline just before submitting
             if (currentDeadlineTimestamp > 0 && Date.now() > currentDeadlineTimestamp) {
                 if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-danger d-block'; uploadResultMessage.textContent = 'لا يمكن التسليم، لقد انتهى الوقت.'; }
                 console.warn("Assignment Upload JS: Submission blocked - deadline passed.");
                 return;
             }

            if (!currentAssignmentId || !submissionFileInput?.files?.length) {
                 if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-warning d-block'; uploadResultMessage.textContent = 'الرجاء اختيار ملف للرفع.'; }
                 console.warn("Assignment Upload JS: Submission blocked - no file selected.");
                return;
            }

            const file = submissionFileInput.files[0];

            // Basic client-side validation
            const allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif|\.webp|\.pdf|\.doc|\.docx|\.xls|\.xlsx|\.ppt|\.pptx)$/i;
             if (!allowedExtensions.exec(file.name)) {
                 if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-warning d-block'; uploadResultMessage.textContent = 'نوع الملف غير مسموح به.'; }
                 console.warn("Assignment Upload JS: Submission blocked - invalid file extension.");
                 return;
             }
             const maxSize = 10 * 1024 * 1024; // 10MB
             if (file.size > maxSize) {
                  if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-warning d-block'; uploadResultMessage.textContent = 'حجم الملف كبير جداً (الحد الأقصى 10MB).'; }
                   console.warn("Assignment Upload JS: Submission blocked - file too large.");
                  return;
             }

            const formData = new FormData();
            formData.append('assignmentId', currentAssignmentId);
            formData.append('submissionFile', file);

            // Disable button, show progress
            if(uploadSubmitButton) uploadSubmitButton.disabled = true;
            if(uploadProgress) uploadProgress.style.display = 'block';
            if(uploadProgressBar) uploadProgressBar.style.width = '0%';
            if(uploadResultMessage) { uploadResultMessage.className = 'alert d-none'; uploadResultMessage.textContent = ''; }

            console.log(`Assignment Upload JS: Attempting upload for assignment ${currentAssignmentId}...`);

            try {
                // ** Make sure this path is correct relative to where your HTML pages are **
                const response = await fetch('../backend/submit_assignment.php', {
                    method: 'POST',
                    body: formData,
                     headers: { 'Accept': 'application/json' },
                });

                console.log(`Assignment Upload JS: Response Status: ${response.status}`);

                let data;
                 if (response.headers.get('content-type')?.indexOf('application/json') > -1) {
                    data = await response.json();
                    console.log("Assignment Upload JS: Response Data:", data);
                 } else {
                      const textResponse = await response.text();
                      console.error("Assignment Upload JS: Received non-JSON response:", textResponse);
                     throw new Error("Server returned an unexpected response format.");
                 }

                if (!response.ok) {
                     throw new Error(data.message || `Server error (${response.status})`);
                }

                if (data.success) {
                    if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-success d-block'; uploadResultMessage.textContent = data.message || 'تم التسليم بنجاح!'; }
                    if(uploadProgressBar) { uploadProgressBar.style.width = '100%'; uploadProgressBar.textContent = '100%';}
                     console.log("Assignment Upload JS: Upload successful.");
                     setTimeout(() => {
                         const modalInstance = bootstrap.Modal.getInstance(uploadModalElement);
                         modalInstance?.hide();
                         // Update UI on the original page (target the button that opened modal)
                         const triggerButton = document.querySelector(`.upload-assignment-btn[data-assignment-id="${currentAssignmentId}"]`);
                         if (triggerButton) {
                              const buttonTextSpan = triggerButton.querySelector('span');
                              if (buttonTextSpan) buttonTextSpan.textContent = 'تم التسليم';
                              triggerButton.classList.remove('btn-primary');
                              triggerButton.classList.add('btn-success'); // Change to success style maybe
                              // Optionally disable after success if needed
                              // triggerButton.disabled = true;
                         }
                     }, 1500);
                } else {
                     throw new Error(data.message || 'فشل التسليم.');
                }

            } catch (error) {
                console.error("Assignment Upload JS: Upload failed:", error);
                 if(uploadResultMessage) { uploadResultMessage.className = 'alert alert-danger d-block'; uploadResultMessage.textContent = `فشل الرفع: ${error.message}`; }
                 if(uploadSubmitButton) uploadSubmitButton.disabled = false;
                 if(uploadProgress) uploadProgress.style.display = 'none';
            }
        });
    } // end if(uploadForm)

}); // End DOMContentLoaded for assignment-upload.js