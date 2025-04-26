document.addEventListener('DOMContentLoaded', () => {
    // --- Global Elements ---
    const messageArea = document.getElementById('messageArea');
    const userProfilePic = document.getElementById('userProfilePic');
    const userProfilePicNav = document.getElementById('userProfilePicNav');
    const userName = document.getElementById('userName');
    const userNameNav = document.getElementById('userNameNav');
    const userEmail = document.getElementById('userEmail');
    const userRole = document.getElementById('userRole');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const thirdName = document.getElementById('thirdName');
    const gender = document.getElementById('gender');
    const birthday = document.getElementById('birthday');
    const city = document.getElementById('city');
    const nationality = document.getElementById('nationality');
    const academicInfoRow = document.getElementById('academicInfoRow');
    const documentsContainer = document.getElementById('documentsContainer');
    const documentsSection = document.getElementById('documentsSection');
    
    // --- Profile Picture Modal Elements ---
    const editProfilePicBtn = document.getElementById('editProfilePic');
    const profilePicModal = new bootstrap.Modal(document.getElementById('profilePicModal'));
    const profilePicForm = document.getElementById('profilePicForm');
    const profilePicFile = document.getElementById('profilePicFile');
    const profilePicPreview = document.getElementById('profilePicPreview');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');
    const uploadMessage = document.getElementById('uploadMessage');
    const saveProfilePicBtn = document.getElementById('saveProfilePic');
    
    // --- Endpoints ---
    const userDataEndpoint = '../backend/get_user_data.php';
    const documentsEndpoint = '../backend/get_user_documents.php';
    const uploadProfilePicEndpoint = '../backend/upload_profile_pic.php';
    
    // --- Helper Functions ---
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }
    
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    function showMessage(message, isError = false, targetElement = messageArea) {
        if (!targetElement) return;
        
        targetElement.textContent = message;
        targetElement.classList.remove('d-none', 'alert-success', 'alert-danger');
        targetElement.classList.add(isError ? 'alert-danger' : 'alert-success');
        
        if (message) {
            targetElement.style.display = 'block';
            // Auto-hide success messages after 5 seconds
            if (!isError) {
                setTimeout(() => {
                    targetElement.classList.add('d-none');
                }, 5000);
            }
        } else {
            targetElement.style.display = 'none';
        }
    }
    
    async function fetchWithCsrf(url, options = {}) {
        // Ensure options.headers exists
        options.headers = options.headers || {};
        
        // Add CSRF token to headers if not using FormData
        if (!options.body || !(options.body instanceof FormData)) {
            options.headers['X-CSRF-Token'] = getCsrfToken();
            options.headers['Content-Type'] = options.headers['Content-Type'] || 'application/json';
        } else {
            // For FormData, the token should be included in the form data
            // and Content-Type should be omitted to let the browser set it with the boundary
            delete options.headers['Content-Type'];
        }
        
        try {
            const response = await fetch(url, options);
            
            // Check for HTTP errors
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || `HTTP error ${response.status}`);
            }
            
            return response;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }
    
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString; // Return original if invalid
        
        return date.toLocaleDateString('ar-IQ', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    function translateGender(gender) {
        const translations = {
            'Male': 'ذكر',
            'Female': 'أنثى'
        };
        return translations[gender] || gender || '-';
    }
    
    function translateStudyMode(mode) {
        const translations = {
            'morning': 'صباحي',
            'evening': 'مسائي',
            'parallel': 'موازي'
        };
        return translations[mode] || mode || '-';
    }
    
    function translateDegree(degree) {
        const translations = {
            'Diploma': 'دبلوم',
            'Bachelor': 'بكالوريوس',
            'Master\'s': 'ماجستير',
            'PhD': 'دكتوراه'
        };
        return translations[degree] || degree || '-';
    }
    
    // --- Load User Data ---
    async function loadUserData() {
        try {
            const response = await fetchWithCsrf(userDataEndpoint);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load user data');
            }
            
            const user = data.user;
            
            // Update profile picture
            if (user.profile_pic) {
                // Use the profile picture URL directly as it's now a presigned S3 URL
                userProfilePic.src = user.profile_pic;
                userProfilePic.onerror = function() {
                    // Fallback to default image if the profile pic fails to load
                    this.src = 'assets/images/3.jpg';
                };
                
                if (userProfilePicNav) {
                    userProfilePicNav.src = user.profile_pic;
                    userProfilePicNav.onerror = function() {
                        // Fallback to default image if the profile pic fails to load
                        this.src = 'assets/images/3.jpg';
                    };
                }
            }
            
            // Update user name
            const fullName = `${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')} ${escapeHtml(user.third_name || '')}`;
            userName.textContent = fullName;
            if (userNameNav) userNameNav.textContent = fullName;
            
            // Update basic info
            userEmail.textContent = escapeHtml(user.email || '');
            firstName.textContent = escapeHtml(user.first_name || '-');
            lastName.textContent = escapeHtml(user.last_name || '-');
            thirdName.textContent = escapeHtml(user.third_name || '-');
            gender.textContent = translateGender(user.gender);
            birthday.textContent = formatDate(user.birthday);
            city.textContent = escapeHtml(user.city || '-');
            nationality.textContent = escapeHtml(user.nationality || '-');
            
            // Update role-specific information
            const roleId = user.role_id;
            
            if (roleId == 7) { // Student
                academicInfoRow.innerHTML = `
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">المرحلة</div>
                        <div>${escapeHtml(user.stage || '-')}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">نوع الدراسة</div>
                        <div>${translateStudyMode(user.study_mode)}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">الشهادة</div>
                        <div>${translateDegree(user.degree)}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">القسم</div>
                        <div>${escapeHtml(user.department_name || '-')}</div>
                    </div>
                `;
            } else if (roleId == 6) { // Teacher
                academicInfoRow.innerHTML = `
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">اللقب العلمي</div>
                        <div>${escapeHtml(user.academic_title || '-')}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">القسم</div>
                        <div>${escapeHtml(user.department_name || '-')}</div>
                    </div>
                `;
            } else if (roleId == 5) { // HOD
                academicInfoRow.innerHTML = `
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">اللقب العلمي</div>
                        <div>${escapeHtml(user.academic_title || '-')}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">القسم</div>
                        <div>${escapeHtml(user.department_name || '-')}</div>
                    </div>
                `;
            }
            
            // Load documents after user data is loaded
            loadUserDocuments();
            
        } catch (error) {
            console.error('Error loading user data:', error);
            showMessage(`خطأ في تحميل بيانات المستخدم: ${error.message}`, true);
        }
    }
    
    // --- Load User Documents ---
    async function loadUserDocuments() {
        try {
            const response = await fetchWithCsrf(`${documentsEndpoint}?doc_type=all`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load documents');
            }
            
            const documents = data.documents;
            documentsContainer.innerHTML = '';
            
            let docsFound = false;
            
            for (const [type, url] of Object.entries(documents)) {
                if (url) {
                    docsFound = true;
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-sm-6 mb-4';
                    
                    const title = type
                        .replace(/_/g, ' ')
                        .replace('id', ' ID')
                        .replace('front', 'Front')
                        .replace('back', 'Back');
                    
                    col.innerHTML = `
                        <div class="document-card">
                            <img src="${url}" class="document-img" alt="${title}">
                            <div class="document-title">${title}</div>
                            <div class="p-3 text-center">
                                <a href="${url}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-external-link-alt me-1"></i> فتح في نافذة جديدة
                                </a>
                            </div>
                        </div>
                    `;
                    
                    documentsContainer.appendChild(col);
                }
            }
            
            if (!docsFound) {
                documentsContainer.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">لم يتم العثور على مستمسكات</p>
                    </div>
                `;
            }
            
        } catch (error) {
            console.error('Error loading documents:', error);
            documentsContainer.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <p class="text-danger">خطأ في تحميل المستمسكات: ${error.message}</p>
                </div>
            `;
        }
    }
    
    // --- Profile Picture Preview ---
    profilePicFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        
        if (file) {
            // Check file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                showMessage('يجب أن تكون الصورة بصيغة JPG، PNG، أو GIF.', true, uploadMessage);
                profilePicFile.value = '';
                profilePicPreview.style.display = 'none';
                previewPlaceholder.style.display = 'block';
                return;
            }
            
            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                showMessage('يجب أن يكون حجم الصورة أقل من 2 ميجابايت.', true, uploadMessage);
                profilePicFile.value = '';
                profilePicPreview.style.display = 'none';
                previewPlaceholder.style.display = 'block';
                return;
            }
            
            // Preview the image
            const reader = new FileReader();
            reader.onload = (event) => {
                profilePicPreview.src = event.target.result;
                profilePicPreview.style.display = 'block';
                previewPlaceholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
            
            // Clear any previous error messages
            showMessage('', false, uploadMessage);
        } else {
            profilePicPreview.style.display = 'none';
            previewPlaceholder.style.display = 'block';
        }
    });
    
    // --- Upload Profile Picture ---
    saveProfilePicBtn.addEventListener('click', async () => {
        if (!profilePicFile.files[0]) {
            showMessage('الرجاء اختيار صورة.', true, uploadMessage);
            return;
        }
        
        try {
            // Prepare form data
            const formData = new FormData(profilePicForm);
            
            // Show progress bar
            uploadProgress.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.setAttribute('aria-valuenow', 0);
            
            // Disable save button
            saveProfilePicBtn.disabled = true;
            
            // Upload the image with progress tracking
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.setAttribute('aria-valuenow', percentComplete);
                }
            });
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update profile pictures
                            if (response.profile_pic_url) {
                                userProfilePic.src = response.profile_pic_url;
                                userProfilePic.onerror = function() {
                                    // Fallback to default image if the profile pic fails to load
                                    this.src = 'assets/images/3.jpg';
                                };
                                if (userProfilePicNav) {
                                    userProfilePicNav.src = response.profile_pic_url;
                                    userProfilePicNav.onerror = function() {
                                        // Fallback to default image if the profile pic fails to load
                                        this.src = 'assets/images/3.jpg';
                                    };
                                }
                            }
                            
                            showMessage('تم تحديث صورة الملف الشخصي بنجاح.', false, uploadMessage);
                            
                            // Close modal after a short delay
                            setTimeout(() => {
                                profilePicModal.hide();
                                resetProfilePicForm();
                                
                                // Show success message on main page
                                showMessage('تم تحديث صورة الملف الشخصي بنجاح.', false);
                            }, 1500);
                        } else {
                            throw new Error(response.message || 'Failed to upload profile picture');
                        }
                    } catch (error) {
                        showMessage(`خطأ: ${error.message}`, true, uploadMessage);
                    }
                } else {
                    showMessage(`خطأ في الخادم: ${xhr.status}`, true, uploadMessage);
                }
                
                // Hide progress bar and enable save button
                uploadProgress.style.display = 'none';
                saveProfilePicBtn.disabled = false;
            };
            
            xhr.onerror = function() {
                showMessage('خطأ في الاتصال بالخادم.', true, uploadMessage);
                uploadProgress.style.display = 'none';
                saveProfilePicBtn.disabled = false;
            };
            
            xhr.open('POST', uploadProfilePicEndpoint, true);
            xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
            xhr.send(formData);
            
        } catch (error) {
            console.error('Error uploading profile picture:', error);
            showMessage(`خطأ: ${error.message}`, true, uploadMessage);
            uploadProgress.style.display = 'none';
            saveProfilePicBtn.disabled = false;
        }
    });
    
    // --- Reset Profile Picture Form ---
    function resetProfilePicForm() {
        profilePicForm.reset();
        profilePicPreview.style.display = 'none';
        previewPlaceholder.style.display = 'block';
        uploadProgress.style.display = 'none';
        showMessage('', false, uploadMessage);
    }
    
    // --- Open Profile Picture Modal ---
    editProfilePicBtn.addEventListener('click', () => {
        resetProfilePicForm();
        profilePicModal.show();
    });
    
    // --- Initialize ---
    loadUserData();
});
