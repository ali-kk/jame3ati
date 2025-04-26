document.addEventListener('DOMContentLoaded', () => {
    // Set the backend endpoint; note we use a dedicated login endpoint
    const baseURL = 'http://localhost/jame3ati/backend/login.php'; // Adjust path if needed
    const loginForm = document.getElementById('loginForm');
    const loginMessage = document.getElementById('loginMessage');
    const loginEmail = document.getElementById('loginEmail');
    const loginPassword = document.getElementById('loginPassword');
    const toggleLoginPass = document.getElementById('toggleLoginPass');
  
    // --- Helper: Setup Password Toggle ---
    function setupToggle(field, button) {
        if (!field || !button) return;
        const icon = button.querySelector('i'); // Get icon inside button
        if (!icon) return; // Check if icon exists
  
        // Set initial state
        icon.classList.toggle('fa-eye', field.type === 'password');
        icon.classList.toggle('fa-eye-slash', field.type !== 'password');
  
        button.addEventListener('click', () => {
            const isPwd = field.type === 'password';
            field.type = isPwd ? 'text' : 'password';
            // Toggle icon classes
            icon.classList.toggle('fa-eye', !isPwd);
            icon.classList.toggle('fa-eye-slash', isPwd);
        });
    }
    // Only setup if elements exist
    if(loginPassword && toggleLoginPass) setupToggle(loginPassword, toggleLoginPass);
  
  
    // --- Helper: Display Message ---
    function displayMessage(element, message, isSuccess) {
        if (!element) return;
        element.textContent = message;
        element.className = 'alert mb-3'; // Reset classes first
        if (message) {
            element.classList.add(isSuccess ? 'alert-success' : 'alert-danger');
        } else {
            element.classList.add('d-none');
        }
    }
  
    // --- Handle Login Form Submission ---
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            displayMessage(loginMessage, '', true); // Clear previous message
  
            const email = loginEmail ? loginEmail.value.trim() : '';
            const password = loginPassword ? loginPassword.value.trim() : '';
  
            // Basic Validation
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                displayMessage(loginMessage, "يرجى إدخال بريد إلكتروني صحيح.", false);
                return;
            }
            if (!password) {
                displayMessage(loginMessage, "يرجى إدخال كلمة المرور.", false);
                return;
            }
  
            // Prepare data
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
  
            // Show spinner on the submit button
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const spinner = submitBtn ? submitBtn.querySelector('.spinner-border') : null;
            if (spinner) spinner.classList.remove('d-none');
            if (submitBtn) submitBtn.disabled = true;
  
            try {
                const res = await fetch(`${baseURL}?action=login`, {
                    method: 'POST',
                    body: formData
                });
  
                let data;
                try {
                     data = await res.json();
                } catch (jsonError) {
                     console.error("Failed to parse login response JSON:", jsonError);
                     const textResponse = await res.text();
                     console.error("Raw login response text:", textResponse);
                     throw new Error("حدث خطأ غير متوقع من الخادم.");
                }
  
  
                if (!res.ok) {
                     // Use message from backend if available, otherwise generic
                     throw new Error(data.message || `خطأ ${res.status}: فشل تسجيل الدخول.`);
                }
  
                // Handle Success/Failure based on parsed data
                if (data.success) {
                    const status = data.user_status;
                    if (status === 'perm')
                 {
                    if(data.role_id==7)
                    {
                        displayMessage(loginMessage, data.message || "تم تسجيل الدخول بنجاح.", true);
                        // *** REDIRECT TO home.php ***
                        setTimeout(() => {
                            window.location.href = 'home.php'; // Corrected redirection
                        }, 1500);
                      

                    }

                  else  if(data.role_id==5)
                        {
                            displayMessage(loginMessage, data.message || "تم تسجيل الدخول بنجاح.", true);
                            // *** REDIRECT TO home.php ***
                            setTimeout(() => {
                                window.location.href = 'hod_dashboard.php'; // Corrected redirection
                            }, 1500);
                           
    
                        }
                    } 
                    
                    else {
                        // Handle other statuses ('temp', 'rejected', 'frozen') by displaying message
                         displayMessage(loginMessage, data.message || "حالة الحساب لا تسمح بالدخول.", false);
                    }
                } else {
                    // Handle login failure (e.g., wrong password, unknown status from backend)
                    displayMessage(loginMessage, data.message || "فشل تسجيل الدخول. يرجى المحاولة مرة أخرى.", false);
                }
  
            } catch (err) {
                 console.error("Login fetch error:", err);
                displayMessage(loginMessage, `حدث خطأ أثناء تسجيل الدخول: ${err.message}`, false);
            } finally {
                // Hide spinner and re-enable button
                if (spinner) spinner.classList.add('d-none');
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    } else {
         console.error("Login form not found!");
         if(loginMessage) displayMessage(loginMessage, "خطأ في تحميل صفحة الدخول.", false);
    }
  });