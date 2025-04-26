document.addEventListener('DOMContentLoaded', function() {
    // --- Global Elements ---
    const messageArea = document.getElementById('messageArea');
    const userProfilePicNav = document.getElementById('userProfilePicNav');
    const userNameNav = document.getElementById('userNameNav');
    
    // --- Email Change Elements ---
    const emailChangeForm = document.getElementById('emailChangeForm');
    const currentEmail = document.getElementById('currentEmail');
    const newEmail = document.getElementById('newEmail');
    const password = document.getElementById('password');
    const changeEmailBtn = document.getElementById('changeEmailBtn');
    
    // --- Email Verification Elements ---
    const emailVerificationSection = document.getElementById('emailVerificationSection');
    const emailOtpForm = document.getElementById('emailOtpForm');
    const emailOtpInputs = emailOtpForm.querySelectorAll('.otp-input');
    const emailOtpComplete = document.getElementById('emailOtpComplete');
    const resendEmailOtp = document.getElementById('resendEmailOtp');
    const emailOtpCountdown = document.getElementById('emailOtpCountdown');
    const verifyEmailOtpBtn = document.getElementById('verifyEmailOtpBtn');
    const cancelEmailChangeBtn = document.getElementById('cancelEmailChangeBtn');
    
    // --- New Email Verification Elements ---
    const newEmailVerificationSection = document.getElementById('newEmailVerificationSection');
    const newEmailOtpForm = document.getElementById('newEmailOtpForm');
    const newEmailOtpInputs = newEmailOtpForm.querySelectorAll('.otp-input');
    const newEmailOtpComplete = document.getElementById('newEmailOtpComplete');
    const resendNewEmailOtp = document.getElementById('resendNewEmailOtp');
    const newEmailOtpCountdown = document.getElementById('newEmailOtpCountdown');
    const verifyNewEmailOtpBtn = document.getElementById('verifyNewEmailOtpBtn');
    const cancelNewEmailChangeBtn = document.getElementById('cancelNewEmailChangeBtn');
    
    // --- Password Change Elements ---
    const passwordChangeForm = document.getElementById('passwordChangeForm');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    // --- Password Verification Elements ---
    const passwordVerificationSection = document.getElementById('passwordVerificationSection');
    const passwordOtpForm = document.getElementById('passwordOtpForm');
    const passwordOtpInputs = passwordOtpForm.querySelectorAll('.otp-input');
    const passwordOtpComplete = document.getElementById('passwordOtpComplete');
    const resendPasswordOtp = document.getElementById('resendPasswordOtp');
    const passwordOtpCountdown = document.getElementById('passwordOtpCountdown');
    const verifyPasswordOtpBtn = document.getElementById('verifyPasswordOtpBtn');
    const cancelPasswordChangeBtn = document.getElementById('cancelPasswordChangeBtn');
    
    // --- Endpoints ---
    const userDataEndpoint = '../backend/get_user_data.php';
    const changeEmailEndpoint = '../backend/routes/change_email.php';
    const verifyEmailOtpEndpoint = '../backend/routes/verify_email_otp.php';
    const verifyNewEmailOtpEndpoint = '../backend/routes/verify_new_email_otp.php';
    const changePasswordEndpoint = '../backend/routes/change_password.php';
    const verifyPasswordOtpEndpoint = '../backend/routes/verify_password_otp.php';
    
    // --- State Variables ---
    let emailChangeData = null;
    let passwordChangeData = null;
    let emailOtpTimer = null;
    let newEmailOtpTimer = null;
    let passwordOtpTimer = null;
    
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
        
        // Add CSRF token to headers
        options.headers['X-CSRF-Token'] = getCsrfToken();
        
        // Set content type if not specified and not FormData
        if (!options.headers['Content-Type'] && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
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
    
    function startCountdown(targetElement, buttonElement, durationInSeconds, onComplete) {
        let timeLeft = durationInSeconds;
        
        // Disable resend button
        buttonElement.disabled = true;
        
        // Update countdown text
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            targetElement.textContent = `(${minutes}:${seconds.toString().padStart(2, '0')})`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                buttonElement.disabled = false;
                targetElement.textContent = '';
                if (onComplete) onComplete();
            }
            
            timeLeft--;
        }
        
        // Initial update
        updateCountdown();
        
        // Start interval
        const timer = setInterval(updateCountdown, 1000);
        
        // Return timer ID for cleanup
        return timer;
    }
    
    function setupOtpInputs(inputs, completeInput) {
        inputs.forEach((input, index) => {
            // Focus next input on input
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1) {
                    // Move to next input if available
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Update complete OTP value
                    updateCompleteOtp(inputs, completeInput);
                }
            });
            
            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value) {
                    // Move to previous input if available
                    if (index > 0) {
                        inputs[index - 1].focus();
                    }
                }
            });
            
            // Handle paste event
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').trim();
                
                // Check if pasted data is a 6-digit number
                if (/^\d{6}$/.test(pasteData)) {
                    // Fill all inputs with respective digits
                    inputs.forEach((input, i) => {
                        input.value = pasteData[i] || '';
                    });
                    
                    // Update complete OTP value
                    updateCompleteOtp(inputs, completeInput);
                }
            });
        });
    }
    
    function updateCompleteOtp(inputs, completeInput) {
        const otp = Array.from(inputs).map(input => input.value).join('');
        completeInput.value = otp;
    }
    
    function resetOtpInputs(inputs, completeInput) {
        inputs.forEach(input => {
            input.value = '';
        });
        completeInput.value = '';
    }
    
    function validatePassword(password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        return regex.test(password);
    }
    
    // --- Load User Data ---
    function loadUserData() {
        fetch(userDataEndpoint)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to load user data:', data.message);
                    return;
                }
                
                const user = data.user;
                console.log('User data loaded:', user);
                
                // Update profile picture in navbar
                if (user.profile_pic && userProfilePicNav) {
                    userProfilePicNav.src = user.profile_pic;
                }
                
                // Update user name in navbar
                if (userNameNav) {
                    const fullName = `${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')} ${escapeHtml(user.third_name || '')}`;
                    userNameNav.textContent = fullName;
                }
                
                // Update current email
                if (currentEmail) {
                    currentEmail.value = user.email || '';
                }
            })
            .catch(error => {
                console.error('Error loading user data:', error);
            });
    }
    
    // --- Initialize OTP Inputs ---
    // Hide verification sections initially
    if (emailVerificationSection) emailVerificationSection.style.display = 'none';
    if (newEmailVerificationSection) newEmailVerificationSection.style.display = 'none';
    if (passwordVerificationSection) passwordVerificationSection.style.display = 'none';
    
    // Setup OTP inputs
    if (emailOtpInputs) setupOtpInputs(emailOtpInputs, emailOtpComplete);
    if (newEmailOtpInputs) setupOtpInputs(newEmailOtpInputs, newEmailOtpComplete);
    if (passwordOtpInputs) setupOtpInputs(passwordOtpInputs, passwordOtpComplete);
    
    // Load user data
    loadUserData();
    
    // --- Email Change Form Submit ---
    if (emailChangeForm) {
        emailChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!emailChangeForm.checkValidity()) {
                e.stopPropagation();
                emailChangeForm.classList.add('was-validated');
                return;
            }
            
            // Validate email format
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.value)) {
                showMessage('يرجى إدخال بريد إلكتروني صحيح.', true);
                return;
            }
            
            // Check if new email is different from current email
            if (newEmail.value === currentEmail.value) {
                showMessage('البريد الإلكتروني الجديد يجب أن يكون مختلفًا عن البريد الإلكتروني الحالي.', true);
                return;
            }
            
            changeEmailBtn.disabled = true;
            showMessage('جاري التحقق...', false);
            
            fetchWithCsrf(changeEmailEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    new_email: newEmail.value,
                    password: password.value
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to initiate email change');
                }
                
                // Store email change data for verification
                emailChangeData = {
                    new_email: newEmail.value,
                    request_id: result.request_id
                };
                
                // Show verification section
                emailChangeForm.style.display = 'none';
                emailVerificationSection.style.display = 'block';
                
                // Focus on first OTP input
                emailOtpInputs[0].focus();
                
                // Start countdown
                emailOtpTimer = startCountdown(emailOtpCountdown, resendEmailOtp, 120);
                
                showMessage('تم إرسال رمز التحقق إلى بريدك الإلكتروني الحالي.', false);
            })
            .catch(error => {
                console.error('Error initiating email change:', error);
                showMessage(`خطأ: ${error.message}`, true);
            })
            .finally(() => {
                changeEmailBtn.disabled = false;
            });
        });
    }
    
    // --- Email OTP Form Submit ---
    if (emailOtpForm) {
        emailOtpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!emailOtpForm.checkValidity() || emailOtpComplete.value.length !== 6) {
                e.stopPropagation();
                emailOtpForm.classList.add('was-validated');
                return;
            }
            
            verifyEmailOtpBtn.disabled = true;
            showMessage('جاري التحقق من الرمز...', false);
            
            fetchWithCsrf(verifyEmailOtpEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    otp_code: emailOtpComplete.value,
                    request_id: emailChangeData.request_id
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to verify OTP');
                }
                
                // Show new email verification section
                emailVerificationSection.style.display = 'none';
                newEmailVerificationSection.style.display = 'block';
                
                // Focus on first OTP input
                newEmailOtpInputs[0].focus();
                
                // Start countdown
                newEmailOtpTimer = startCountdown(newEmailOtpCountdown, resendNewEmailOtp, 120);
                
                showMessage('تم التحقق من بريدك الإلكتروني الحالي. يرجى التحقق من رمز التحقق المرسل إلى بريدك الإلكتروني الجديد.', false);
            })
            .catch(error => {
                console.error('Error verifying email OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
            })
            .finally(() => {
                verifyEmailOtpBtn.disabled = false;
            });
        });
    }
    
    // --- New Email OTP Form Submit ---
    if (newEmailOtpForm) {
        newEmailOtpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!newEmailOtpForm.checkValidity() || newEmailOtpComplete.value.length !== 6) {
                e.stopPropagation();
                newEmailOtpForm.classList.add('was-validated');
                return;
            }
            
            verifyNewEmailOtpBtn.disabled = true;
            showMessage('جاري التحقق من الرمز...', false);
            
            fetchWithCsrf(verifyNewEmailOtpEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    otp_code: newEmailOtpComplete.value,
                    request_id: emailChangeData.request_id
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to verify OTP');
                }
                
                // Reset forms and sections
                emailChangeForm.reset();
                resetOtpInputs(emailOtpInputs, emailOtpComplete);
                resetOtpInputs(newEmailOtpInputs, newEmailOtpComplete);
                
                emailVerificationSection.style.display = 'none';
                newEmailVerificationSection.style.display = 'none';
                emailChangeForm.style.display = 'block';
                
                // Update current email in the form and session
                currentEmail.value = emailChangeData.new_email;
                
                // Clear email change data
                emailChangeData = null;
                
                // Clear timers
                if (emailOtpTimer) clearInterval(emailOtpTimer);
                if (newEmailOtpTimer) clearInterval(newEmailOtpTimer);
                
                showMessage('تم تغيير البريد الإلكتروني بنجاح.', false);
                
                // Reload user data to update the session
                loadUserData();
            })
            .catch(error => {
                console.error('Error verifying new email OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
            })
            .finally(() => {
                verifyNewEmailOtpBtn.disabled = false;
            });
        });
    }
    
    // --- Password Change Form Submit ---
    if (passwordChangeForm) {
        passwordChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Password change form submitted');
            
            if (!passwordChangeForm.checkValidity()) {
                e.stopPropagation();
                passwordChangeForm.classList.add('was-validated');
                console.log('Form validation failed');
                return;
            }
            
            // Validate password strength
            if (!validatePassword(newPassword.value)) {
                showMessage('كلمة المرور الجديدة يجب أن تتكون من 8 أحرف على الأقل وتحتوي على حروف كبيرة وصغيرة وأرقام ورموز.', true);
                console.log('Password strength validation failed');
                return;
            }
            
            // Check if passwords match
            if (newPassword.value !== confirmPassword.value) {
                showMessage('كلمات المرور غير متطابقة.', true);
                console.log('Passwords do not match');
                return;
            }
            
            // Check if new password is the same as current password
            if (newPassword.value === currentPassword.value) {
                showMessage('كلمة المرور الجديدة يجب أن تكون مختلفة عن كلمة المرور الحالية.', true);
                console.log('New password is the same as current password');
                return;
            }
            
            changePasswordBtn.disabled = true;
            showMessage('جاري التحقق...', false);
            console.log('Sending password change request');
            
            fetchWithCsrf(changePasswordEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    current_password: currentPassword.value,
                    new_password: newPassword.value
                })
            })
            .then(response => response.json())
            .then(result => {
                console.log('Password change response:', result);
                if (!result.success) {
                    throw new Error(result.message || 'Failed to initiate password change');
                }
                
                // Store password change data for verification
                passwordChangeData = {
                    request_id: result.request_id
                };
                
                // Show verification section
                passwordChangeForm.style.display = 'none';
                passwordVerificationSection.style.display = 'block';
                
                // Focus on first OTP input
                passwordOtpInputs[0].focus();
                
                // Start countdown
                passwordOtpTimer = startCountdown(passwordOtpCountdown, resendPasswordOtp, 120);
                
                showMessage('تم إرسال رمز التحقق إلى بريدك الإلكتروني.', false);
            })
            .catch(error => {
                console.error('Error initiating password change:', error);
                showMessage(`خطأ: ${error.message}`, true);
            })
            .finally(() => {
                changePasswordBtn.disabled = false;
            });
        });
    }
    
    // --- Password OTP Form Submit ---
    if (passwordOtpForm) {
        passwordOtpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!passwordOtpForm.checkValidity() || passwordOtpComplete.value.length !== 6) {
                e.stopPropagation();
                passwordOtpForm.classList.add('was-validated');
                return;
            }
            
            verifyPasswordOtpBtn.disabled = true;
            showMessage('جاري التحقق من الرمز...', false);
            
            fetchWithCsrf(verifyPasswordOtpEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    otp_code: passwordOtpComplete.value,
                    request_id: passwordChangeData.request_id
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to verify OTP');
                }
                
                // Reset forms and sections
                passwordChangeForm.reset();
                resetOtpInputs(passwordOtpInputs, passwordOtpComplete);
                
                passwordVerificationSection.style.display = 'none';
                passwordChangeForm.style.display = 'block';
                
                // Clear password change data
                passwordChangeData = null;
                
                // Clear timer
                if (passwordOtpTimer) clearInterval(passwordOtpTimer);
                
                showMessage('تم تغيير كلمة المرور بنجاح.', false);
            })
            .catch(error => {
                console.error('Error verifying password OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
            })
            .finally(() => {
                verifyPasswordOtpBtn.disabled = false;
            });
        });
    }
    
    // --- Cancel Email Change ---
    if (cancelEmailChangeBtn) {
        cancelEmailChangeBtn.addEventListener('click', function() {
            emailVerificationSection.style.display = 'none';
            emailChangeForm.style.display = 'block';
            resetOtpInputs(emailOtpInputs, emailOtpComplete);
            
            // Clear timer
            if (emailOtpTimer) clearInterval(emailOtpTimer);
            
            // Clear email change data
            emailChangeData = null;
        });
    }
    
    // --- Cancel New Email Change ---
    if (cancelNewEmailChangeBtn) {
        cancelNewEmailChangeBtn.addEventListener('click', function() {
            newEmailVerificationSection.style.display = 'none';
            emailChangeForm.style.display = 'block';
            resetOtpInputs(newEmailOtpInputs, newEmailOtpComplete);
            
            // Clear timer
            if (newEmailOtpTimer) clearInterval(newEmailOtpTimer);
            
            // Clear email change data
            emailChangeData = null;
        });
    }
    
    // --- Cancel Password Change ---
    if (cancelPasswordChangeBtn) {
        cancelPasswordChangeBtn.addEventListener('click', function() {
            passwordVerificationSection.style.display = 'none';
            passwordChangeForm.style.display = 'block';
            resetOtpInputs(passwordOtpInputs, passwordOtpComplete);
            
            // Clear timer
            if (passwordOtpTimer) clearInterval(passwordOtpTimer);
            
            // Clear password change data
            passwordChangeData = null;
        });
    }
    
    // --- Resend Email OTP ---
    if (resendEmailOtp) {
        resendEmailOtp.addEventListener('click', function() {
            if (!emailChangeData) return;
            
            resendEmailOtp.disabled = true;
            
            fetchWithCsrf(changeEmailEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    new_email: emailChangeData.new_email,
                    password: password.value,
                    resend: true
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to resend OTP');
                }
                
                // Update request ID
                emailChangeData.request_id = result.request_id;
                
                // Start countdown again
                emailOtpTimer = startCountdown(emailOtpCountdown, resendEmailOtp, 120);
                
                showMessage('تم إعادة إرسال رمز التحقق.', false);
            })
            .catch(error => {
                console.error('Error resending OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
                resendEmailOtp.disabled = false;
            });
        });
    }
    
    // --- Resend New Email OTP ---
    if (resendNewEmailOtp) {
        resendNewEmailOtp.addEventListener('click', function() {
            if (!emailChangeData) return;
            
            resendNewEmailOtp.disabled = true;
            
            fetchWithCsrf(verifyEmailOtpEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    request_id: emailChangeData.request_id,
                    resend_new: true
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to resend OTP');
                }
                
                // Start countdown again
                newEmailOtpTimer = startCountdown(newEmailOtpCountdown, resendNewEmailOtp, 120);
                
                showMessage('تم إعادة إرسال رمز التحقق.', false);
            })
            .catch(error => {
                console.error('Error resending OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
                resendNewEmailOtp.disabled = false;
            });
        });
    }
    
    // --- Resend Password OTP ---
    if (resendPasswordOtp) {
        resendPasswordOtp.addEventListener('click', function() {
            if (!passwordChangeData) return;
            
            resendPasswordOtp.disabled = true;
            
            fetchWithCsrf(changePasswordEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    current_password: currentPassword.value,
                    new_password: newPassword.value,
                    resend: true
                })
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to resend OTP');
                }
                
                // Update request ID
                passwordChangeData.request_id = result.request_id;
                
                // Start countdown again
                passwordOtpTimer = startCountdown(passwordOtpCountdown, resendPasswordOtp, 120);
                
                showMessage('تم إعادة إرسال رمز التحقق.', false);
            })
            .catch(error => {
                console.error('Error resending OTP:', error);
                showMessage(`خطأ: ${error.message}`, true);
                resendPasswordOtp.disabled = false;
            });
        });
    }
});
