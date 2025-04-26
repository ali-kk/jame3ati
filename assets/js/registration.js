document.addEventListener('DOMContentLoaded', async () => {
    // Backend API endpoint
    const baseURL = 'http://localhost/jame3ati/backend/index.php'; // Adjust if necessary

    // Get references to DOM elements
    const regMessage      = document.getElementById('regMessage');
    const formContainer   = document.getElementById('extendedFormContainer');
    const waitingPage     = document.getElementById('waitingPage');
    const acceptedPage    = document.getElementById('acceptedPage');
    const regForm         = document.getElementById('extendedRegForm');

    // User Type Selection Elements
    const userTypeRadios = document.querySelectorAll('input[name="userType"]');
    const studentFieldsContainer = document.getElementById('studentFields');
    const teacherFieldsContainer = document.getElementById('teacherFields');

    // Conditionally Required Fields (Student vs Teacher)
    const stageInput      = document.getElementById('stage');
    const degreeSelect    = document.getElementById('degree');
    const studyModeSelect = document.getElementById('study_mode');
    const scientificTitleSelect = document.getElementById('scientific_title');
    const uniIdBackInput = document.getElementById('uni_id_back');
    const uniIdBackContainer = document.getElementById('uniIdBackContainer');

    // Dropdown select elements
    const uniSelect = document.getElementById('uni_id');
    const colSelect = document.getElementById('col_id');
    const depSelect = document.getElementById('dep_id');

     // Check if essential elements exist
     if (!regForm || !uniSelect || !colSelect || !depSelect || !formContainer || !waitingPage || !acceptedPage || !regMessage || !studentFieldsContainer || !teacherFieldsContainer || !uniIdBackInput || !uniIdBackContainer || !stageInput || !degreeSelect || !studyModeSelect || !scientificTitleSelect) {
       console.error("One or more essential page elements are missing! Cannot initialize registration script.");
       if(regMessage) { // Try to display an error if message div exists
           regMessage.textContent = "خطأ حرج في تحميل الصفحة. لا يمكن المتابعة.";
           regMessage.className = 'alert alert-danger mb-4'; // Make sure it's visible
       }
       return; // Stop execution
     }

    // --- Helper Function to Display Messages ---
    function displayMessage(element, message, isSuccess) {
        if (!element) return;
        // Basic sanitization example (replace with a more robust library if needed)
        const tempDiv = document.createElement('div');
        tempDiv.textContent = message;
        element.innerHTML = tempDiv.innerHTML; // Use innerHTML after textContent to render potential safe HTML like line breaks if needed later, but be cautious

        element.className = 'alert mb-4'; // Reset classes first

        if (message) {
            element.classList.add(isSuccess ? 'alert-success' : 'alert-danger');
        } else {
            element.classList.add('d-none');
        }
    }

    // --- Helper Function to Check Age Range ---
    function checkAgeRange(dateString, minAge, maxAge) {
        try {
            if (!dateString) {
                 // console.log("checkAgeRange: No dateString provided.");
                 return false;
            }
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Compare dates only

            // Ensure date is parsed correctly, potentially as local time
            const parts = dateString.split('-'); // Assume YYYY-MM-DD
            if(parts.length !== 3) {
                console.error("checkAgeRange: Invalid date parts count:", parts);
                return false;
            }

            // Parse parts into numbers first
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1; // JS months are 0-indexed
            const day = parseInt(parts[2], 10);

            if (isNaN(year) || isNaN(month) || isNaN(day)) {
                 console.error("checkAgeRange: Invalid integer parts:", year, month, day);
                 return false;
            }

            // Create date using UTC values but check components first for validity
            // Check month and day validity before creating Date object
             if (month < 0 || month > 11 || day < 1 || day > 31) {
                 console.error("checkAgeRange: Invalid month or day value", month + 1, day);
                 return false;
             }
            // Rough check for days in month (doesn't account for leap years perfectly, but prevents major errors)
            if (day > [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month]) {
                 console.error("checkAgeRange: Invalid day for the given month", month + 1, day);
                 return false;
            }


            const birthDate = new Date(Date.UTC(year, month, day));

            // Check if the resulting date object is valid
            if (isNaN(birthDate.getTime())) {
                console.error("checkAgeRange: Constructed invalid date from timestamp:", dateString);
                return false; // Invalid date constructed
            }

            // Double-check components match after construction (Date.UTC can be lenient)
            if (birthDate.getUTCFullYear() !== year || birthDate.getUTCMonth() !== month || birthDate.getUTCDate() !== day) {
                console.error("checkAgeRange: Date object components mismatch after creation", dateString);
                return false;
            }


            // Compare components after ensuring valid date object
            let age = today.getFullYear() - birthDate.getUTCFullYear();
            const monthDiff = today.getMonth() - birthDate.getUTCMonth();
            const dayDiff = today.getDate() - birthDate.getUTCDate(); // Use getDate for day comparison

            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age--;
            }
            const isValidAge = (age >= minAge && age <= maxAge); // Adjusted max age for teachers potentially
            // console.log(`checkAgeRange: Date=${dateString}, Age=${age}, Valid=${isValidAge}`); // Optional debug log
            return isValidAge;

        } catch (e) {
             console.error("Error in checkAgeRange function:", e);
            return false;
        }
    }


    // --- File Input Validation Helper ---
    function validateFile(inputId, errorDivId, allowedMimeTypes, allowedExtensions, maxSizeMB, isRequired) {
        const input = document.getElementById(inputId);
        const errorDiv = document.getElementById(errorDivId);
        const maxSize = maxSizeMB * 1024 * 1024;
        let isValid = true;

        if (!input || !errorDiv) {
            console.error(`Validation Error: Missing input or error div for ${inputId}`);
            // If the field itself is missing, it can't be valid if required
            return !isRequired;
        }

        // Check the current required status based on user type
        const currentRequiredStatus = input.hasAttribute('required');

        input.classList.remove('is-invalid', 'is-valid');
        errorDiv.textContent = '';
        errorDiv.style.display = 'none';

        if (input.files.length > 0) {
            const file = input.files[0];
            const fileName = file.name || '';
            const fileExtension = fileName.split('.').pop().toLowerCase();
            let errors = [];

            // Check Type (MIME preferred, extension fallback)
            // Note: Client-side MIME check isn't foolproof, backend validation is essential
            if (!allowedMimeTypes.includes(file.type) && !allowedExtensions.includes(fileExtension) ) {
                errors.push(`نوع الملف غير صالح (${allowedExtensions.join(', ').toUpperCase()}).`);
                isValid = false;
            }

            // Check Size
            if (file.size > maxSize) {
                errors.push(`حجم الملف كبير جداً (الحد الأقصى ${maxSizeMB}MB).`);
                isValid = false;
            }
            if (file.size === 0) {
                 errors.push(`الملف فارغ.`);
                 isValid = false;
            }

            if (!isValid) {
                errorDiv.textContent = errors.join(' ');
                errorDiv.style.display = 'block';
                input.classList.add('is-invalid');
                input.value = ''; // Clear invalid file
            } else {
                 input.classList.add('is-valid');
            }

        } else if (currentRequiredStatus) { // Check if the file is required based on the attribute
            errorDiv.textContent = `هذا الحقل مطلوب.`;
            errorDiv.style.display = 'block';
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            // File not present, but not required -> Mark as valid (or neutral)
             isValid = true;
             // Optionally mark as valid: input.classList.add('is-valid');
        }

        return isValid;
    }


    // --- 1. Check User Status on Page Load ---
    async function checkUserStatus() {
        formContainer.classList.add('d-none');
        waitingPage.classList.add('d-none');
        acceptedPage.classList.add('d-none');
        try {
            const checkRes = await fetch(`${baseURL}?action=check_registration`);
            if (!checkRes.ok) { throw new Error(`Network error (${checkRes.status})`); }
            const checkData = await checkRes.json();
            if (!checkData || typeof checkData.success === 'undefined') { throw new Error("Invalid check response"); }
            if (checkData.success) {
                formContainer.classList.remove('d-none');
                await loadUniversities();
                // Set initial field visibility based on default selection (student)
                toggleFields('student');
            } else {
                displayMessage(regMessage, checkData.message || "Verification failed, redirecting...", false);
                setTimeout(() => { window.location.href = 'index.html'; }, 3000);
            }
        } catch (err) {
            displayMessage(regMessage, `Error checking status: ${err.message}`, false);
        }
    }

    // --- 2. Load Universities ---
    async function loadUniversities() {
        colSelect.disabled = true; depSelect.disabled = true;
        colSelect.innerHTML = '<option value="" disabled selected>...</option>';
        depSelect.innerHTML = '<option value="" disabled selected>...</option>';
        try {
            uniSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            const res = await fetch(`${baseURL}?action=load_universities`);
            if (!res.ok) throw new Error(`Network error (${res.status})`);
            const data = await res.json();
            if (!data.success || !Array.isArray(data.data)) { throw new Error(data.message || "Failed to load universities."); }
            uniSelect.innerHTML = '<option value="" disabled selected>اختر الجامعة</option>';
            data.data.forEach(uni => uniSelect.add(new Option(uni.uni_name, uni.uni_id)));
        } catch (err) {
            displayMessage(regMessage, `Error loading universities: ${err.message}`, false);
             uniSelect.innerHTML = '<option value="" disabled selected>Error</option>';
        }
    }

    // --- 3. Load Colleges ---
    async function loadColleges(universityId) {
        if (!universityId) return;
        depSelect.disabled = true; depSelect.innerHTML = '<option value="" disabled selected>...</option>';
        colSelect.innerHTML = '<option value="" disabled selected>Loading...</option>'; colSelect.disabled = true;
        try {
            const res = await fetch(`${baseURL}?action=load_colleges&uni_id=${universityId}`);
            if (!res.ok) throw new Error(`Network error (${res.status})`);
            const data = await res.json();
            if (!data.success || !Array.isArray(data.data)) { throw new Error(data.message || "Failed to load colleges."); }
            colSelect.innerHTML = '<option value="" disabled selected>اختر الكلية</option>';
            if (data.data.length === 0) colSelect.add(new Option("لا توجد كليات", ""));
            else data.data.forEach(col => colSelect.add(new Option(col.col_name, col.col_id)));
            colSelect.disabled = data.data.length === 0;
        } catch (err) {
            displayMessage(regMessage, `Error loading colleges: ${err.message}`, false);
            colSelect.innerHTML = '<option value="" disabled selected>Error</option>'; colSelect.disabled = true;
        }
    }

    // --- 4. Load Departments ---
    async function loadDepartments(collegeId) {
        if (!collegeId) return;
        depSelect.innerHTML = '<option value="" disabled selected>Loading...</option>'; depSelect.disabled = true;
        try {
            const res = await fetch(`${baseURL}?action=load_departments&col_id=${collegeId}`);
             if (!res.ok) throw new Error(`Network error (${res.status})`);
            const data = await res.json();
            if (!data.success || !Array.isArray(data.data)) { throw new Error(data.message || "Failed to load departments."); }
            depSelect.innerHTML = '<option value="" disabled selected>اختر القسم</option>';
            if (data.data.length === 0) depSelect.add(new Option("لا توجد أقسام", ""));
            else data.data.forEach(dep => depSelect.add(new Option(dep.dep_name, dep.dep_id)));
            depSelect.disabled = data.data.length === 0;
        } catch (err) {
            displayMessage(regMessage, `Error loading departments: ${err.message}`, false);
            depSelect.innerHTML = '<option value="" disabled selected>Error</option>'; depSelect.disabled = true;
        }
    }

    // --- 5. Toggle Fields Based on User Type ---
    function toggleFields(userType) {
        const isTeacher = (userType === 'teacher');
      
        studentFieldsContainer.classList.toggle('d-none',  isTeacher);
        teacherFieldsContainer.classList.toggle('d-none', !isTeacher);
      
        // fix required attributes…
        if (isTeacher) {
          stageInput.removeAttribute('required');
          degreeSelect.removeAttribute('required');
          studyModeSelect.removeAttribute('required');
          scientificTitleSelect.setAttribute('required', 'required');
          uniIdBackInput.removeAttribute('required');
          uniIdBackContainer.querySelector('label').textContent =
            'هوية الجامعة (الوجه الخلفي – اختياري)';
        } else {
          stageInput.setAttribute('required', 'required');
          degreeSelect.setAttribute('required', 'required');
          studyModeSelect.setAttribute('required', 'required');
          scientificTitleSelect.removeAttribute('required');
          uniIdBackInput.setAttribute('required', 'required');
          uniIdBackContainer.querySelector('label').textContent =
            'هوية الجامعة (الوجه الخلفي – JPG/PNG – حد أقصى 2MB)';
        }
      
        clearValidationStates();
      }
      

    // --- 6. Clear Validation States ---
     function clearValidationStates() {
        Array.from(regForm.elements).forEach(element => {
            element.classList.remove('is-invalid', 'is-valid');
            if(element.type === 'file') {
                const errorDiv = document.getElementById(element.id + 'Error');
                if(errorDiv) errorDiv.textContent = '';
            }
            element.setCustomValidity(''); // Clear custom validity like the age check one
        });
        // Also clear the main message area
         displayMessage(regMessage, '', true);
     }

    // --- 7. Handle Form Submission ---
    async function handleFormSubmit(event) {
        event.preventDefault();
        clearValidationStates(); // Clear previous errors first

        regForm.classList.remove('was-validated'); // Reset validation state

        let isFormValid = true; // Assume valid initially
        const MAX_FILE_SIZE_MB = 2;
        const imageMimes = ['image/jpeg', 'image/png'];
        const imageExts = ['jpg', 'jpeg', 'png'];

        // Validate all files - update isFormValid if any fail
        // The validateFile function now respects the 'required' attribute dynamically set by toggleFields
        if (!validateFile('profile_pic', 'profilePicError', imageMimes, imageExts, MAX_FILE_SIZE_MB, true)) isFormValid = false; // Always required
        if (!validateFile('uni_id_front', 'uniIdFrontError', imageMimes, imageExts, MAX_FILE_SIZE_MB, true)) isFormValid = false; // Always required
        if (!validateFile('uni_id_back', 'uniIdBackError', imageMimes, imageExts, MAX_FILE_SIZE_MB, uniIdBackInput.hasAttribute('required'))) isFormValid = false; // Required status checked dynamically
        if (!validateFile('national_id_front', 'nationalIdFrontError', imageMimes, imageExts, MAX_FILE_SIZE_MB, true)) isFormValid = false; // Always required
        if (!validateFile('national_id_back', 'nationalIdBackError', imageMimes, imageExts, MAX_FILE_SIZE_MB, true)) isFormValid = false; // Always required

        // Check standard Bootstrap validation & Age Check
        if (!regForm.checkValidity()) { isFormValid = false; }

        // Determine age range based on selected user type
        const selectedUserType = document.querySelector('input[name="userType"]:checked')?.value || 'student';
        const minAge = 17;
        const maxAge = (selectedUserType === 'teacher') ? 65 : 55; // Example: Allow older teachers

        const birthdayInput = document.getElementById('birthday');
        let isAgeValid = true; // Local scope for age check result
        if (birthdayInput && birthdayInput.value && !checkAgeRange(birthdayInput.value, minAge, maxAge)) {
             isAgeValid = false; // Age check failed
             isFormValid = false; // Overall form is invalid
        }

        // Add was-validated class *after* all checks
        regForm.classList.add('was-validated');
        // Mark birthday field specifically if age check failed but date format was okay
        if (!isAgeValid && birthdayInput && birthdayInput.checkValidity()) {
             birthdayInput.classList.add('is-invalid'); // Force invalid display
             // Provide a more specific message based on user type
             birthdayInput.setCustomValidity(`العمر يجب ان يكون بين ${minAge} و ${maxAge} سنة.`);
             // Add a general message as well
              displayMessage(regMessage, `العمر يجب ان يكون بين ${minAge} و ${maxAge} سنة.`, false);
        } else if (birthdayInput) {
             birthdayInput.setCustomValidity(""); // Clear custom validity message
        }


        // If any validation failed, stop
        if (!isFormValid) {
             event.stopPropagation();
             // Ensure a general message is shown if not already set by age check
              if (!regMessage.textContent || regMessage.classList.contains('d-none') || regMessage.classList.contains('alert-success')) {
                  displayMessage(regMessage, "الرجاء تصحيح الأخطاء في الحقول المميزة.", false);
              }
             const firstInvalid = regForm.querySelector(':invalid, .is-invalid'); // Find first invalid element
             if(firstInvalid) firstInvalid.focus(); // Focus on it
             return;
        }

        // --- If all checks passed, proceed ---
        const formData = new FormData(regForm);
        // Append the selected user type explicitly, in case the radio button name isn't sent reliably by default
        formData.append('user_type', selectedUserType);


        const submitBtn = regForm.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner-border');
        if (spinner) spinner.classList.remove('d-none');
        submitBtn.disabled = true;
        displayMessage(regMessage, 'جاري إرسال البيانات ورفع الملفات...', true);

        try {
            const res = await fetch(`${baseURL}?action=complete_extended_registration`, {
                method: 'POST',
                body: formData
            });

            let data;
            try { data = await res.json(); } catch(e) {
                console.error("Failed to parse JSON response:", e);
                const textResponse = await res.text(); console.error("Raw Server Response:", textResponse);
                throw new Error(`Server error (Status: ${res.status}). Check server logs.`);
            }

            if (!res.ok) { throw new Error(data.message || `Network error (${res.status})`); }

            if (data.success) {
                formContainer.classList.add('d-none');
                regMessage.classList.add('d-none');
                waitingPage.classList.remove('d-none');
            } else {
                displayMessage(regMessage, data.message || "فشل إكمال عملية التسجيل.", false);
            }
        } catch (err) {
            displayMessage(regMessage, `حدث خطأ أثناء إرسال البيانات: ${err.message}`, false);
        } finally {
            if (spinner) spinner.classList.add('d-none');
            submitBtn.disabled = false;
        }
    } // End handleFormSubmit

    // --- Add Event Listeners ---
    if (uniSelect) uniSelect.addEventListener('change', () => loadColleges(uniSelect.value));
    if (colSelect) colSelect.addEventListener('change', () => loadDepartments(colSelect.value));

    // Listener for User Type radio buttons
    userTypeRadios.forEach(radio => {
        radio.addEventListener('change', (event) => {
            toggleFields(event.target.value);
        });
    });


    if (regForm) {
        // Clear validation feedback on input/change for better UX
        Array.from(regForm.elements).forEach(element => {
            const handler = () => {
                 // Only remove validation if it's currently invalid
                 if (element.classList.contains('is-invalid')) {
                     element.classList.remove('is-invalid');
                     if (element.id === 'birthday') { element.setCustomValidity(''); } // Clear custom msg for age
                     if (element.type === 'file') {
                         const errorDiv = document.getElementById(element.id + 'Error');
                         if (errorDiv) errorDiv.textContent = '';
                     }
                 }
                  // Optionally, remove 'is-valid' too if you prefer neutral state on edit
                  element.classList.remove('is-valid');
            };
            element.addEventListener('input', handler);
            if (element.type === 'file' || element.tagName === 'SELECT' || element.type === 'radio') {
                element.addEventListener('change', handler); // Also clear for file/select/radio changes
            }
        });
        regForm.addEventListener('submit', handleFormSubmit);
    }

     // Optional: Logout button listener
     const logoutWaitingBtn = document.getElementById('logoutWaitingBtn');
     if(logoutWaitingBtn) {
         logoutWaitingBtn.addEventListener('click', (e) => {
             e.preventDefault();
             window.location.href = 'login.html';
         });
     }

    // --- Initial Page Load ---
    checkUserStatus(); // Start the process

}); // End DOMContentLoaded