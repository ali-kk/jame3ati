document.addEventListener('DOMContentLoaded', () => {

    // Navbar elements
    const userProfilePicNav = document.getElementById('userProfilePicNav');
    const userNameNav = document.getElementById('userNameNav');
    const messageArea = document.getElementById('homeMessageArea');
    const logoutButton = document.getElementById('logoutButton');
    // Content area element
    const courseCardsContainer = document.getElementById('course-cards-container'); // Re-added

    const userDataEndpoint =  'http://localhost/jame3ati/backend/user_data.php';
    const defaultProfilePic = '../assets/images/placeholder-profile.png';
    const defaultCourseImage = '../assets/images/default-course-image.png'; // Add a default course image path
    const loginPageUrl = 'http://localhost/jame3ati/backend/login.php';

    // --- Helper Functions ---
    function showHomeMessage(msg, isError = false) { /* ... same as before ... */
        if (!messageArea) return;
        messageArea.textContent = msg; messageArea.className = 'alert mb-4';
        if (msg) { messageArea.classList.add(isError ? 'alert-warning' : 'alert-info'); }
        else { messageArea.classList.add('d-none'); }
    }

    // --- Function to Render Course Cards ---
    function displayCourses(courses) {
        if (!courseCardsContainer) { console.error("Course container not found."); return; }

        courseCardsContainer.innerHTML = ''; // Clear loading indicator or previous cards

        if (!courses || courses.length === 0) {
            courseCardsContainer.innerHTML = '<div class="col-12"><p class="text-center text-secondary py-5">لا توجد مواد دراسية متاحة حالياً.</p></div>';
            return;
        }
      
        courses.forEach(course => {
           
                    
              
            // Prepare teacher display name (Title + Name)
            let teacherName = 'غير محدد'; // Default if no teacher
            if (course.teacher_fname || course.teacher_lname) {
                 teacherName = `${course.teacher_title || ''} ${course.teacher_fname || ''} ${course.teacher_lname || ''}`.trim();
            }

             // Decide on image source (presigned URL or default)
             const imageUrl = course.courseImageUrl || defaultCourseImage;

            // Create card HTML dynamically using Bootstrap card structure
            const cardCol = document.createElement('div');
            cardCol.className = 'col-lg-3 col-md-4 col-sm-6 mb-4'; // Responsive grid columns

            // Use template literals for cleaner HTML structure
            // Added link wrapping the card content
            // **** UPDATED: Link points to subject_details.php ****
        cardCol.innerHTML = `
        <a href="subject_details.php?id=${course.course_id || ''}" class="card course-card h-100 text-decoration-none">
            <img src="${imageUrl}" class="card-img-top course-card-img" alt="صورة المادة: ${course.course_name || 'مادة دراسية'}">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title course-card-title">${course.course_name || 'اسم المادة'}</h5>
                <p class="card-text course-card-teacher mt-auto">
                    <small>${teacherName}</small>
                </p>
            </div>
        </a>
    `;
            courseCardsContainer.appendChild(cardCol);

             // Optional: Add error handling for course images within the loop
             const imgElement = cardCol.querySelector('.course-card-img');
             if (imgElement) {
                 imgElement.onerror = () => {
                     console.warn(`Failed to load image for course ${course.course_id}: ${imgElement.src}`);
                     imgElement.src = defaultCourseImage; // Fallback to default on error
                 };
             }
             });
    }


    // --- Function to fetch and display user profile data & courses ---
    async function loadHomePageData() {
        console.log("Loading home page data...");
        // Show loading indicator in course area
        if(courseCardsContainer) {
            courseCardsContainer.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 lead">جاري تحميل المواد الدراسية...</p>
                </div>`;
        }

        try {
            const response = await fetch(userDataEndpoint, { /* ... fetch options ... */
                 method: 'GET', headers: { 'Accept': 'application/json' }
            });

            let data;
            try { data = await response.json(); } catch (e) {
                console.error("Failed to parse data JSON:", e); const text = await response.text(); console.error("Raw response:", text);
                throw new Error("Invalid response format from server.");
            }

            if (data.logout === true) { window.location.href = loginPageUrl; return; } // Redirect if logout needed
            if (!response.ok) { throw new Error(data.message || `Network error (${response.status})`); }

            if (data.success) {
                console.log("User and course data received:", data);
                // Update Profile Picture & Name (same as before)
                if (userProfilePicNav) { userProfilePicNav.src = data.profilePicUrl || defaultProfilePic; userProfilePicNav.alt = `Profile picture for ${data.firstName || ''}`; userProfilePicNav.onerror = () => { userProfilePicNav.src = defaultProfilePic; }; }
                if (userNameNav) { userNameNav.textContent = `${data.firstName || ''} ${data.lastName || ''}`; userNameNav.classList.remove('d-none'); }

                // **** ADDED: Call displayCourses ****
                displayCourses(data.courses || []);

            } else { // Handle backend failure
                console.error("Backend failed to fetch data:", data.message);
                showHomeMessage(data.message || "Failed to load page data.", true);
                 if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic;
                 if (userNameNav) userNameNav.textContent = "المستخدم";
                 // Display error in course area
                 if (courseCardsContainer) courseCardsContainer.innerHTML = `<div class="col-12"><p class="text-center text-danger py-5">حدث خطأ أثناء تحميل المواد الدراسية.</p></div>`;
            }

        } catch (error) { // Handle fetch/network errors
            console.error('Error loading home page data:', error);
            showHomeMessage(`Error loading page: ${error.message}`, true);
             if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic;
             if (userNameNav) userNameNav.textContent = "المستخدم";
             if (courseCardsContainer) courseCardsContainer.innerHTML = `<div class="col-12"><p class="text-center text-danger py-5">فشل تحميل المواد الدراسية.</p></div>`;
        }
    } // End loadHomePageData

    
    // --- Initial Load ---
    loadHomePageData(); // Fetch profile AND course data

}); // End DOMContentLoaded