// assets/js/assignments.js

document.addEventListener('DOMContentLoaded', () => {
    // --- Elements ---
    // Get the main container where all assignment cards will be placed.
    // *** IMPORTANT: Make sure your HTML has an element with this ID (or change the ID here) ***
    const courseDataEndpoint = window.location.origin +'/backend/subject_data.php';
    const profileDataEndpoint = window.location.origin +'/backend/user_data.php';
    const defaultProfilePic = window.location.origin +'/assets/images/placeholder-profile.png'; 
    const defaultCourseImage = window.location.origin +'/assets/images/default-course-image.png'; 
    const loginPageUrl = 'login.html'; // Adjust if needed
    const allAssignmentsContainer = document.getElementById('assignmentsListContainer');
    const loadingIndicator = document.getElementById('assignmentsLoading'); // Optional
    const messageArea = document.getElementById('assignmentsMessage'); // Optional

    // --- Navbar Elements ---
    // **** ADDED these again to ensure loadNavbarProfile works ****
    const userProfilePicNav = document.getElementById('userProfilePicNav');
    const userNameNav = document.getElementById('userNameNav');
    // --- End Navbar Elements ---

    // --- Helper Functions ---

    // **** Call loadNavbarProfile ****
    // loadNavbarProfile(); // <<< Make sure this function is defined below and called

    // formatTimestamp function (using Asia/Baghdad timezone as per your provided code)
    function formatTimestamp(timestamp, includeTime = true) {
        if (!timestamp) return 'غير محدد';
        try {
            // NOTE: Your provided code still includes timezone conversion.
            // If you want NO conversion, remove timeZone option AND the '+ 'Z'' part
            const date = new Date(timestamp.replace(' ', 'T') /* + 'Z' REMOVE THIS IF DB TIME IS ALREADY LOCAL */);
            const options = {
                 timeZone: 'Asia/Baghdad', // REMOVE this line if DB time is already local
                 year: 'numeric', month: 'short', day: 'numeric',
            };
            if (includeTime) {
                options.hour = 'numeric'; options.minute = '2-digit';
            }
            return new Intl.DateTimeFormat('ar-IQ', options).format(date);
        } catch (e) { console.error("Error formatting date:", timestamp, e); return timestamp; }
    }


    function displayAssignments(assignments, containerElement) {
        // Check the passed container argument
        if (!containerElement) {
            console.error("Assignments container element (#assignmentsListContainer) not provided or found.");
            // Assuming 'messageArea' is defined in the broader scope of assignments.js
            if(typeof messageArea !== 'undefined' && messageArea) {
                 messageArea.textContent = 'Error: Cannot find where to display assignments.';
                 messageArea.className = 'alert alert-danger d-block';
            }
            return;
        }
        containerElement.innerHTML = ''; // Clear placeholder/previous content
    
        if (!assignments || assignments.length === 0) {
            containerElement.innerHTML = '<div class="text-center text-secondary p-4">لا توجد واجبات متاحة حالياً.</div>';
            return;
        }
    
        const listWrapper = document.createElement('div');
        listWrapper.className = 'row'; // Use Bootstrap row for grid layout
    
        assignments.forEach((assignment, index) => {
            const colWrapper = document.createElement('div');
            // Responsive columns (adjust col-* classes as needed for your layout)
            colWrapper.className = 'col-12 col-md-6 col-xl-4 mb-4 course-card-wrapper'; // Added course-card-wrapper
            const card = document.createElement('div');
            card.className = 'assignment-card card bg-dark text-light h-100 shadow-sm';
    
            // --- Deadline & Type Handling ---
            // Make sure formatTimestamp is defined and accessible in this scope
            const deadlineFormatted = typeof formatTimestamp === 'function' ? formatTimestamp(assignment.deadline_at, true) : assignment.deadline_at;
            const submissionMethodText = assignment.assignment_type === 'info_only' ? 'تسليم في الصف' : 'تسليم عبر المنصة';
            const submissionMethodClass = assignment.assignment_type === 'info_only' ? 'badge bg-info' : 'badge bg-primary';
    
            // --- Deadline Check & Button State ---
            let deadlinePassed = false;
            let deadlineTimestampForAttr = 0;
    
            // Check deadline only if needed (relevant for electronic, non-submitted)
            if (assignment.deadline_at && assignment.assignment_type === 'electronic' && !assignment.has_submitted) {
                try {
                    const deadlineDateLocal = new Date(assignment.deadline_at.replace(' ', 'T'));
                    if (isNaN(deadlineDateLocal.getTime())) {
                        console.warn(`AssignmentsJS - Assignment ${assignment.assignment_id}: Could not parse deadline '${assignment.deadline_at}' as local time.`);
                        deadlinePassed = true; // Fail safe
                    } else {
                        deadlineTimestampForAttr = deadlineDateLocal.getTime();
                        deadlinePassed = new Date() > deadlineDateLocal; // Direct comparison
                    }
                     console.log(`DEBUG (AssignmentsJS) - Assignment ${assignment.assignment_id}: Deadline Passed: ${deadlinePassed}`);
                } catch (e) {
                    console.error(`AssignmentsJS - Assignment ${assignment.assignment_id}: Error checking deadline '${assignment.deadline_at}'`, e);
                    deadlinePassed = true; // Fail safe
                }
            } else if (assignment.has_submitted) {
                // Get timestamp even if submitted, for the data attribute
                try { deadlineTimestampForAttr = new Date(assignment.deadline_at.replace(' ', 'T')).getTime() || 0; } catch(e){}
            }
            else if (!assignment.deadline_at) {
                deadlinePassed = false; // No deadline means not passed
            }
    
            // Determine final button state
            const isElectronic = assignment.assignment_type === 'electronic';
            // Ensure has_submitted is treated as boolean, check if property exists
            const hasSubmitted = assignment.hasOwnProperty('has_submitted') ? Boolean(assignment.has_submitted) : false;
    
            const canSubmit = isElectronic && !hasSubmitted && !deadlinePassed;
            const uploadButtonDisabled = !canSubmit;
    
            let uploadButtonClass = '';
            let submitButtonText = '';
            let uploadButtonTitle = '';
            let buttonIcon = 'fa-users';
    
            if (!isElectronic) {
                uploadButtonClass = 'btn-secondary disabled'; submitButtonText = 'في الصف'; uploadButtonTitle = 'التسليم في الصف'; buttonIcon = 'fa-users';
            } else if (hasSubmitted) {
                 uploadButtonClass = 'btn-success disabled'; submitButtonText = 'تم التسليم'; uploadButtonTitle = 'لقد قمت بالتسليم بالفعل'; buttonIcon = 'fa-check-circle';
            } else if (deadlinePassed) {
                 uploadButtonClass = 'btn-danger disabled'; submitButtonText = 'انتهى الوقت'; uploadButtonTitle = 'انتهى وقت التسليم'; buttonIcon = 'fa-times-circle';
            } else {
                 uploadButtonClass = 'btn-primary'; submitButtonText = 'تسليم'; uploadButtonTitle = 'تسليم الواجب'; buttonIcon = 'fa-upload';
            }
            // --- End Button State Calculation ---
    
            // --- Description Handling ---
            const collapseId = `assignment-all-collapse-${assignment.assignment_id || index}`; // Use unique prefix
            const descriptionSnippet = assignment.description ? assignment.description.substring(0, 80) + (assignment.description.length > 80 ? '...' : '') : '';
            let descriptionDir = 'ltr'; const arabicRegex = /[\u0600-\u06FF]/; if (assignment.description && arabicRegex.test(assignment.description)) { descriptionDir = 'rtl'; }
            // --- End Description Handling ---
    
            // --- Deadline Badge Styling ---
             let deadlineBadgeClass = 'badge bg-secondary'; let deadlineIcon = 'fas fa-calendar-alt';
             if (!deadlinePassed && assignment.deadline_at) { try { const d=new Date(deadlineTimestampForAttr||0); const n=new Date(); if((d.getTime()-n.getTime())/(1000*3600*24)<3){deadlineBadgeClass='badge bg-warning text-dark';deadlineIcon='fas fa-clock';} else{deadlineBadgeClass='badge bg-success';}}catch(e){} }
             else if(deadlinePassed && assignment.deadline_at){deadlineBadgeClass='badge bg-danger';deadlineIcon='fas fa-exclamation-triangle';}
            // --- End Deadline Badge Styling ---
    
    
            // --- Card HTML (Includes Course Image/Name) ---
            // Ensure defaultCourseImage is defined in the scope
            const imageSrc = assignment.courseImageUrl || (typeof defaultCourseImage !== 'undefined' ? defaultCourseImage : '');
            card.innerHTML = `
                <div class="card-body d-flex flex-column">
                     <div class="d-flex align-items-center mb-3">
                          <img src="${imageSrc}" alt="${assignment.course_name || 'Course'}" class="assignment-course-image rounded-circle me-3" width="45" height="45" onerror="this.onerror=null; this.src='${typeof defaultCourseImage !== 'undefined' ? defaultCourseImage : ''}';">
                          <div>
                              <h6 class="card-title assignment-title mb-0">${assignment.title || 'بدون عنوان'}</h6>
                              <small class=" assignment-course-name">${assignment.course_name || 'اسم المادة غير متوفر'}</small>
                          </div>
                     </div>
                     <div class="assignment-meta d-flex flex-wrap gap-2 mb-3">
                          <span class="${deadlineBadgeClass}" title="${deadlineFormatted}"><i class="${deadlineIcon} me-1"></i> ${deadlineFormatted}</span>
                          <span class="${submissionMethodClass}">${submissionMethodText}</span>
                          ${hasSubmitted ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> تم التسليم</span>' : ''}
                     </div>
                     ${assignment.description ? `
                          <div class="assignment-description-wrapper mb-auto flex-grow-1">
                               <p class="assignment-description-snippet small text-light" dir="${descriptionDir}">
                                    ${descriptionSnippet}
                                    ${assignment.description.length > 80 ? `
                                        <button class="btn btn-link btn-sm text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                             <span class="more-text">اقرأ المزيد</span>
                                             <span class="less-text d-none">أقل</span>
                                        </button>
                                    ` : ''}
                               </p>
                               <div class="collapse" id="${collapseId}">
                                    <p class="small text-light" dir="${descriptionDir}">${assignment.description.replace(/\n/g, '<br>')}</p>
                               </div>
                          </div>
                          ` : '<div class="mb-auto flex-grow-1"></div>'}
                     <div class="assignment-actions mt-3 text-end border-top border-secondary pt-2">
                          <a href="subject_details.php?id=${assignment.course_id}#assignmentsContent" class="btn btn-outline-light btn-sm" title="عرض تفاصيل المادة والواجب"><i class="fas fa-info-circle fa-fw"></i><span class="d-none d-sm-inline ms-1">التفاصيل</span></a>
                          <button type="button" class="btn ${uploadButtonClass} btn-sm ms-2 upload-assignment-btn"
                               data-bs-toggle="modal"
                               data-bs-target="#assignmentUploadModal"
                               data-assignment-id="${assignment.assignment_id}"
                               data-assignment-title="${assignment.title || 'بدون عنوان'}"
                               data-deadline-timestamp="${deadlineTimestampForAttr}"
                               ${uploadButtonDisabled ? 'disabled' : ''}
                               title="${uploadButtonTitle}">
                               <i class="fas ${buttonIcon} fa-fw"></i>
                               <span class="d-none d-sm-inline ms-1">${submitButtonText}</span>
                           </button>
                     </div>
                </div>`;
    
               colWrapper.appendChild(card);
               listWrapper.appendChild(colWrapper);
    
               // Attach collapse listener logic
               const toggleButton = card.querySelector(`[data-bs-target="#${collapseId}"]`);
                if(toggleButton) {
                    const collapseElement = card.querySelector(`#${collapseId}`);
                    collapseElement?.addEventListener('show.bs.collapse', () => {
                        toggleButton.querySelector('.more-text')?.classList.add('d-none');
                        toggleButton.querySelector('.less-text')?.classList.remove('d-none');
                        card.querySelector('.assignment-description-snippet')?.classList.add('d-none');
                    });
                    collapseElement?.addEventListener('hide.bs.collapse', () => {
                        toggleButton.querySelector('.more-text')?.classList.remove('d-none');
                        toggleButton.querySelector('.less-text')?.classList.add('d-none');
                        card.querySelector('.assignment-description-snippet')?.classList.remove('d-none');
                    });
                }
        });
    
        // Append to the correct container passed as argument
        containerElement.appendChild(listWrapper);
    } // End displayAssignments for assignments.js

     // --- Load Navbar Profile Function ---
     // Keep the definition from the previous response here
    async function loadNavbarProfile() {
         if (!userProfilePicNav && !userNameNav) return;
         const endpoint = profileDataEndpoint;
         try {
             const response = await fetch(endpoint);
             if (!response.ok) { throw new Error(`Network response was not ok: ${response.status}`); }
             if (response.headers.get('content-type')?.indexOf('application/json') === -1) { throw new Error('Received non-JSON response from profile endpoint');}
             const data = await response.json();
             if (data.success) {
                 if (userProfilePicNav) { userProfilePicNav.src = data.profilePicUrl || defaultProfilePic; userProfilePicNav.onerror = () => { userProfilePicNav.src = defaultProfilePic; };}
                 if (userNameNav) { const fullName = [data.firstName, data.lastName].filter(Boolean).join(' ').trim(); userNameNav.textContent = fullName || 'المستخدم'; userNameNav.classList.remove('d-none');}
             } else {
                 if (data.logout) { window.location.href = loginPageUrl; return; }
                 console.error('Failed to load navbar profile:', data.message);
                 if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic; if (userNameNav) userNameNav.textContent = "المستخدم";
             }
         } catch (error) {
             console.error('Error loading navbar profile:', error);
             if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic; if (userNameNav) userNameNav.textContent = "المستخدم";
         }
     }


    // --- Load All Assignments Function ---
    async function loadAllAssignments() {
        console.log("Loading all assignments...");
        if (!allAssignmentsContainer) { /*...*/ return; }
        if(loadingIndicator) loadingIndicator.style.display = 'flex';
        allAssignmentsContainer.innerHTML = '';
        if(messageArea) messageArea.classList.add('d-none');

        try {
            const response = await fetch(window.location.origin + '/backend/assignments_data.php'); // Verify Endpoint
            if (!response.ok) { throw new Error(`Network error (${response.status})`); }
            // Add robust JSON check
            if (response.headers.get('content-type')?.indexOf('application/json') === -1) {
                 const textResponse = await response.text(); // Get text for debugging
                 console.error("Non-JSON response received:", textResponse);
                 throw new Error('Received non-JSON response from assignments endpoint');
            }
            const data = await response.json();

            if (data.logout === true) { window.location.href = loginPageUrl; return; } // Use correct login URL
            if (!data.success) { throw new Error(data.message || "Failed to load assignments data."); }

            displayAssignments(data.assignments || [], allAssignmentsContainer);

        } catch (error) {
            console.error('Error loading assignments:', error);
            const errorMsg = `فشل تحميل الواجبات: ${error.message}`;
            if (messageArea) { /*...*/ } else { /*...*/ }
        } finally {
            if(loadingIndicator) loadingIndicator.style.display = 'none';
        }
    }

    // --- Initial Load ---
    loadNavbarProfile(); // Ensure navbar profile loads
    loadAllAssignments(); // Load assignments

}); // End DOMContentLoaded