document.addEventListener('DOMContentLoaded', () => {
    // ----- Element References -----
    const otpRequestSection = document.getElementById('otpRequestSection');
    const otpVerifySection  = document.getElementById('otpVerifySection');
    const responseMsg       = document.getElementById('responseMsg');
    const responseMsg2      = document.getElementById('responseMsg2'); // Message area in verify section
    const otpRequestForm    = document.getElementById('otpRequestForm');
    const otpVerifyForm     = document.getElementById('otpVerifyForm');
    const emailField        = document.getElementById('email'); // Original email input
    const passwordField     = document.getElementById('password');
    const passwordField2    = document.getElementById('password2');
    const togglePass1       = document.getElementById('togglePass1');
    const togglePass2       = document.getElementById('togglePass2');
    const otpCodeField      = document.getElementById('otpCode');
    const currentOtpEmail   = document.getElementById('currentOtpEmail'); // Email display in verify section

    // Resend OTP and Change Email elements
    const resendOtpLink       = document.getElementById('resendOtpLink');
    const changeEmailLink     = document.getElementById('changeEmailLink');
    const changeEmailModalEl  = document.getElementById('changeEmailModal');
    const changeEmailModal    = changeEmailModalEl ? new bootstrap.Modal(changeEmailModalEl) : null; // Check if exists
    const displayEmailSpan    = document.getElementById('displayEmail'); // Email display in modal
    const newEmailInput       = document.getElementById('newEmail');     // New email input in modal
    const confirmChangeEmailBtn = document.getElementById('confirmChangeEmailBtn');
    const changeEmailMessage  = document.getElementById('changeEmailMessage');
    const resendTimerDisplay  = document.getElementById('resendTimerDisplay');

    // ----- Endpoints -----
    const BASE_URL = window.location.origin + '/backend/index.php'; // Dynamic origin
    const SEND_OTP_URL = `${BASE_URL}?action=send_otp`;
    const VERIFY_OTP_URL = `${BASE_URL}?action=verify_otp`;

    // ----- State -----
    let isRequestingOTP = false; // Flag to prevent concurrent requests

    // ----- Helper Functions -----
    function showMessage(elem, msg, isSuccess) {
        if (!elem) { console.error("showMessage: Element not found."); return; }
        elem.classList.remove('d-none');
        elem.textContent = msg;
        elem.className = 'alert mb-3 '; // Reset classes
        if (isSuccess) {
            elem.classList.add('alert-info');
        } else {
            elem.classList.add('alert-danger');
        }
    }

    function setupPasswordToggle(field, button) {
        if (!field || !button) return;
        const icon = button.querySelector('i');
        if (!icon) return;
        icon.classList.toggle('fa-eye', field.type === 'password');
        icon.classList.toggle('fa-eye-slash', field.type !== 'password');

        button.addEventListener('click', () => {
            const isPwd = field.type === 'password';
            field.type = isPwd ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isPwd);
            icon.classList.toggle('fa-eye-slash', isPwd);
        });
    }
    setupPasswordToggle(passwordField, togglePass1);
    setupPasswordToggle(passwordField2, togglePass2);

    // ----- Resend OTP Timer -----
    const RESEND_INTERVAL = 120; // seconds
    let resendTimer = null;

    function disableResendLink() {
        if (!resendOtpLink) return;
        resendOtpLink.style.pointerEvents = "none";
        resendOtpLink.style.opacity = "0.6";
        resendOtpLink.textContent = "إعادة إرسال"; // Reset text while disabled
    }
    function enableResendLink() {
         if (!resendOtpLink) return;
         resendOtpLink.style.pointerEvents = "auto";
         resendOtpLink.style.opacity = "1";
         resendOtpLink.textContent = "إعادة إرسال";
         if (resendTimerDisplay) resendTimerDisplay.textContent = ""; // Clear timer display
    }

    function startResendCountdown() {
        if (!resendOtpLink || !resendTimerDisplay) return;
        let remaining = RESEND_INTERVAL;
        disableResendLink(); // Disable link immediately
        updateResendDisplay(remaining);

        if (resendTimer) clearInterval(resendTimer); // Clear existing timer

        resendTimer = setInterval(() => {
            remaining--;
            updateResendDisplay(remaining);
            if (remaining <= 0) {
                clearInterval(resendTimer);
                resendTimer = null;
                enableResendLink(); // Enable link only when timer finishes
            }
        }, 1000);
    }

    function updateResendDisplay(seconds) {
        if (!resendTimerDisplay) return;
        if (seconds > 0) {
             resendTimerDisplay.textContent = `(${seconds} ثانية)`;
        } else {
             resendTimerDisplay.textContent = ""; // Hide timer when expired
        }
    }

    // ----- Client-side Rate Limit for Changing Email -----
    let lastEmailChangeTime = 0;
    const EMAIL_CHANGE_INTERVAL = 120000; // 2 minutes in ms

    // ----- Request OTP (Handles initial request, resend, and change email requests) -----
    async function requestOTP(emailToSend) {
        if (isRequestingOTP) {
            console.log("Request OTP already in progress. Ignoring.");
            return; // Prevent concurrent requests
        }
        isRequestingOTP = true; // Set flag

        console.log(`requestOTP called for email: ${emailToSend}`);

        // Determine which message element to use
        const messageElement = otpVerifySection && !otpVerifySection.classList.contains('d-none') ? responseMsg2 : responseMsg;

        if (!messageElement){
             console.error("Message element not found for OTP request.");
             isRequestingOTP = false;
             return;
        }

        // Validate email
        if (!emailToSend || !/^\S+@\S+\.\S+$/.test(emailToSend)) {
            showMessage(messageElement, "بريد إلكتروني غير صالح.", false);
            isRequestingOTP = false;
            return;
        }

        // --- UI Updates before sending ---
        showMessage(messageElement, "جاري إرسال طلب الرمز...", true); // More accurate loading message
        disableResendLink(); // Ensure link is disabled

        const formData = new FormData();
        formData.append('email', emailToSend);
        // *** ALWAYS add password if available ***
        if (passwordField && passwordField.value) {
            formData.append('password', passwordField.value.trim());
        }
        // Only add password if it's the initial request (Step 1 form)
        if (otpRequestSection && !otpRequestSection.classList.contains('d-none')) {
            if (passwordField && passwordField.value) {
                formData.append('password', passwordField.value.trim());
            }
        }
        // No password needed for resend or change email

        try {
            const res = await fetch(SEND_OTP_URL, {
                method: 'POST',
                body: formData
            });

            let data;
            try {
                 data = await res.json();
                 console.log("Response from backend send OTP:", data);
            } catch (jsonError) {
                 console.error("Failed to parse JSON response from send OTP:", jsonError);
                 // Try to get text response if possible
                 const textResponse = await res.text();
                 console.error("Raw response text:", textResponse);
                 throw new Error("حدث خطأ غير متوقع من الخادم.");
            }

            if (!res.ok) {
                // Use message from backend if available, otherwise generic
                throw new Error(data.message || `خطأ ${res.status}: فشل طلب الرمز.`);
            }

            // --- Handle SUCCESS response ---
            if (data.success) {
                showMessage(messageElement, data.message, true); // Show backend success message

                console.log(`Successfully requested OTP for: ${emailToSend}. Updating UI.`);

                // Update the displayed email in the verification section
                if (currentOtpEmail) {
                    currentOtpEmail.textContent = emailToSend;
                    console.log(`Updated currentOtpEmail display to: ${emailToSend}`);
                } else {
                     console.warn("currentOtpEmail element not found.");
                }
                // Update the hidden email field value from Step 1 (optional, but keeps consistent)
                if (emailField) {
                    emailField.value = emailToSend;
                }

                // Ensure verification section is visible and focus input
                if(otpRequestSection) otpRequestSection.classList.add('d-none');
                if(otpVerifySection) otpVerifySection.classList.remove('d-none');
                if(otpCodeField) otpCodeField.focus();

                // Start the countdown timer (which also keeps link disabled)
                startResendCountdown();

            } else {
                // --- Handle FAILURE response from backend (e.g., rate limit) ---
                showMessage(messageElement, data.message, false);
                enableResendLink(); // Re-enable link on failure
            }

        } catch (err) {
            // --- Handle FETCH/Network errors ---
            console.error("Error in requestOTP fetch:", err);
            showMessage(messageElement, err.message || "حدث خطأ في الشبكة أثناء طلب الرمز.", false);
            enableResendLink(); // Re-enable link on error
        } finally {
            isRequestingOTP = false; // Reset flag
        }
    }

    // ----- Change Email Functionality -----
    if (changeEmailLink && changeEmailModal && currentOtpEmail && newEmailInput && displayEmailSpan && confirmChangeEmailBtn && changeEmailMessage) {
        changeEmailLink.addEventListener('click', (e) => {
            e.preventDefault();
            const emailForModal = currentOtpEmail.textContent || (emailField ? emailField.value.trim() : '');
            console.log("Opening change email modal for:", emailForModal);
            displayEmailSpan.textContent = emailForModal;
            newEmailInput.value = emailForModal;
            changeEmailMessage.textContent = "";
            changeEmailMessage.className = 'alert d-none mt-2'; // Reset message classes
            changeEmailModal.show();
        });

        confirmChangeEmailBtn.addEventListener('click', async () => {
            const newEmailVal = newEmailInput.value.trim();
            if (!newEmailVal || !/^\S+@\S+\.\S+$/.test(newEmailVal)) {
                showMessage(changeEmailMessage, "يرجى إدخال بريد إلكتروني صحيح.", false);
                return;
            }

            const currentEmailVal = currentOtpEmail.textContent;
            if (newEmailVal.toLowerCase() === currentEmailVal.toLowerCase()) {
                 showMessage(changeEmailMessage, "البريد الإلكتروني الجديد مطابق للحالي.", false);
                 return;
            }


            // Optional: Client-side rate limit check
            const now = Date.now();
            if (now - lastEmailChangeTime < EMAIL_CHANGE_INTERVAL) {
                showMessage(changeEmailMessage, "يرجى الانتظار دقيقتين قبل تغيير البريد الإلكتروني مرة أخرى.", false);
                return;
            }
            lastEmailChangeTime = now;

            changeEmailModal.hide();
            console.log(`Attempting OTP request for new email: ${newEmailVal}`);

            // Trigger OTP request for the NEW email
            await requestOTP(newEmailVal);
        });
    } else {
         console.warn("One or more elements for 'Change Email' functionality not found.");
    }

    // ----- Resend OTP Link -----
    if (resendOtpLink && currentOtpEmail) {
        resendOtpLink.addEventListener('click', async (e) => {
            e.preventDefault();
            if (isRequestingOTP) return; // Prevent clicking while request is in progress

            const emailToResend = currentOtpEmail.textContent || (emailField ? emailField.value.trim() : '');
            console.log(`Resend OTP clicked for: ${emailToResend}`);

            if (emailToResend) {
               // Immediately disable link for visual feedback
               disableResendLink();
               await requestOTP(emailToResend); // Call request function
            } else {
               showMessage(responseMsg2, "لا يمكن تحديد البريد الإلكتروني لإعادة الإرسال.", false);
            }
        });
    } else {
         console.warn("Resend OTP link or current email display element not found.");
    }

    // ----- OTP Request Form Submission (Step 1 - Initial Request) -----
    if (otpRequestForm) {
        otpRequestForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isRequestingOTP) return; // Prevent double submission

            responseMsg.className = 'alert d-none mb-3'; // Hide message initially
            const email = emailField.value.trim();
            const pass  = passwordField.value.trim();
            const pass2 = passwordField2.value.trim();

            // Validations
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                showMessage(responseMsg, "الرجاء إدخال بريد إلكتروني صالح.", false); return;
            }
            if (pass !== pass2) {
                showMessage(responseMsg, "كلمات المرور غير متطابقة.", false); return;
            }
            const passRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_\d]).{8,}$/;
            if (!passRegex.test(pass)) {
                showMessage(responseMsg, "كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، ورقم أو رمز خاص.", false); return;
            }

            const submitBtn = otpRequestForm.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.remove('d-none');
            submitBtn.disabled = true;

            await requestOTP(email); // Call central OTP request function

            // Re-enable button (spinner is hidden within requestOTP's finally block if using it there, or here)
            if (spinner) spinner.classList.add('d-none');
            submitBtn.disabled = false;
        });
    } else {
         console.warn("OTP Request Form not found.");
    }

    // ----- OTP Verification Form Submission (Step 2) -----
    if (otpVerifyForm) {
        otpVerifyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isRequestingOTP) return; // Prevent submission while other requests might be happening

            responseMsg2.className = 'alert d-none mb-3'; // Hide message
            const otpCode = otpCodeField.value.trim();

            if (!otpCode || !/^\d{6}$/.test(otpCode)) {
                showMessage(responseMsg2, "يرجى إدخال رمز التحقق المكون من 6 أرقام.", false);
                return;
            }

            const formData = new FormData();
            formData.append('otp', otpCode);

            const submitBtn = otpVerifyForm.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.remove('d-none');
            submitBtn.disabled = true;
            // Disable resend/change while verifying
            if(resendOtpLink) resendOtpLink.style.pointerEvents = "none";
            if(changeEmailLink) changeEmailLink.style.pointerEvents = "none";


            try {
                const res = await fetch(VERIFY_OTP_URL, {
                    method: 'POST',
                    body: formData
                });

                 let data;
                 try {
                      data = await res.json();
                      console.log("Response from verify OTP:", data);
                 } catch (jsonError) {
                       console.error("Failed to parse JSON from verify OTP:", jsonError);
                       const textResponse = await res.text();
                       console.error("Raw response text from verify OTP:", textResponse);
                       throw new Error("حدث خطأ غير متوقع من الخادم أثناء التحقق.");
                 }

                if (!res.ok) {
                    throw new Error(data.message || "حدث خطأ أثناء التحقق من الرمز.");
                }

                if (data.success) {
                    showMessage(responseMsg2, data.message, true);
                    responseMsg2.innerHTML += `
                      <div class="text-center mt-2">
                        <div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                        <p class="d-inline-block ms-2 mb-0">جاري تحويلك لإكمال التسجيل...</p>
                      </div>`;
                    // Keep buttons disabled, clear timer
                    if (resendTimer) clearInterval(resendTimer);
                    if (resendTimerDisplay) resendTimerDisplay.textContent = "";

                    setTimeout(() => { window.location.href = 'registration.html'; }, 2500);
                } else {
                    showMessage(responseMsg2, data.message, false);
                    // Re-enable buttons only if verification fails
                    submitBtn.disabled = false;
                    if(resendOtpLink && !resendTimer) enableResendLink(); // Re-enable resend only if timer isn't running
                    if(changeEmailLink) changeEmailLink.style.pointerEvents = "auto";
                    if (spinner) spinner.classList.add('d-none');
                }
            } catch (err) {
                console.error("Error verifying OTP:", err);
                showMessage(responseMsg2, err.message || "حدث خطأ أثناء التحقق من الرمز.", false);
                 // Re-enable buttons on error
                submitBtn.disabled = false;
                if(resendOtpLink && !resendTimer) enableResendLink(); // Re-enable resend only if timer isn't running
                if(changeEmailLink) changeEmailLink.style.pointerEvents = "auto";
                if (spinner) spinner.classList.add('d-none');
            }
        });
    } else {
        console.warn("OTP Verify Form not found.");
    }

    // Initial setup checks
    if (!otpRequestSection || !otpVerifySection) {
         console.error("Required sections (otpRequestSection or otpVerifySection) not found.");
    }
     if (!changeEmailModal) {
         console.warn("Change Email Modal element not found, 'Change Email' link functionality might be limited.");
     }

});