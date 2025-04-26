// frontend/assets/js/subject_details.js

document.addEventListener('DOMContentLoaded', () => {
    console.log("Subject Details JS: DOM Loaded.");

    // --- Element References ---
    const courseDetailHeader = document.getElementById('courseDetailHeader');
    const courseImage = document.getElementById('courseImage');
    const courseTitle = document.getElementById('courseTitle');
    const courseDescription = document.getElementById('courseDescription');
    const teacherProfilePic = document.getElementById('teacherProfilePic');
    const teacherInfoDiv = document.getElementById('teacherInfo'); // Reference to the div containing teacher details
    const teacherTitleSpan = document.getElementById('teacherTitle');
    const teacherNameSpan = document.getElementById('teacherName');
    const courseTabsSection = document.getElementById('courseTabsSection');
    const messageArea = document.getElementById('courseDetailMessageArea');
    const headerLoadingIndicator = document.getElementById('headerLoadingIndicator');
    const filesContentPane = document.getElementById('filesContent');
    const videosContentPane = document.getElementById('videosContent');
    const assignmentsContentPane = document.getElementById('assignmentsContent'); // Container for assignments tab
    const userProfilePicNav = document.getElementById('userProfilePicNav');
    const userNameNav = document.getElementById('userNameNav');
    const videoPlayerModalElement = document.getElementById('videoPlayerModal');
    const videoPlayerModalTitle = document.getElementById('videoPlayerModalTitle');
    const videoPlayerModalBody = document.getElementById('videoPlayerModalBody');
    const uploadModalElement = document.getElementById('assignmentUploadModal'); // Upload modal element

    // --- Essential Element Check ---
    if (!headerLoadingIndicator || !courseDetailHeader || !courseTabsSection || !filesContentPane || !videosContentPane || !assignmentsContentPane) {
        console.error("Subject Details JS: One or more essential content containers are missing from the HTML!");
        if(messageArea) {
             showMessage("خطأ في تحميل الصفحة: بعض العناصر الأساسية غير موجودة.", true);
        } else {
            // Fallback if message area itself is missing
            document.body.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Page Loading Error: Missing essential elements.</div>');
        }
        if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'none';
        return; // Stop script execution if essential elements are missing
    }

    // --- Modal Initialization ---
    let videoModalInstance = null;
     if (videoPlayerModalElement) {
         try {
            // Initialize Bootstrap modal for the video player
            videoModalInstance = new bootstrap.Modal(videoPlayerModalElement);
         } catch(e) {
             console.error("Failed to initialize video modal:", e);
         }
     } else {
         console.warn("Video player modal element not found.");
     }
     // Note: Assignment modal initialization is handled by Bootstrap automatically via data attributes

    // --- API Endpoints and Defaults ---
    const courseDataEndpoint = '../backend/subject_data.php';
    const profileDataEndpoint = '../backend/user_data.php';
    const defaultProfilePic = 'assets/images/placeholder-profile.png'; // Path relative to HTML file
    const defaultCourseImage = 'assets/images/default-course-image.png'; // Path relative to HTML file
    const loginPageUrl = 'login.html'; // Path relative to HTML file

    // --- Helper Functions ---

    /**
     * Displays a message in the designated message area.
     * @param {string} msg - The message to display.
     * @param {boolean} [isError=false] - True for error styling, false for info styling.
     */
    function showMessage(msg, isError = false) {
        if (!messageArea) { console.warn("Message area not found."); return; }
        messageArea.textContent = msg;
        messageArea.className = 'alert mb-4'; // Reset classes
        if (msg) {
            messageArea.classList.add(isError ? 'alert-danger' : 'alert-info');
             messageArea.classList.remove('d-none'); // Make it visible
        } else {
            messageArea.classList.add('d-none'); // Hide if no message
        }
    }

    /**
     * Formats file size in bytes to a human-readable format (KB, MB, GB).
     * @param {number} bytes - The file size in bytes.
     * @param {number} [decimals=2] - The number of decimal places.
     * @returns {string} Formatted file size string or empty string if bytes is 0.
     */
    function formatBytes(bytes, decimals = 2) {
         if (!+bytes) return ''; // Return empty string if bytes is 0 or falsy
         const k = 1024;
         const dm = decimals < 0 ? 0 : decimals;
         const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
         const i = Math.floor(Math.log(bytes) / Math.log(k));
         return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    /**
     * Formats a timestamp string into a localized date/time string.
     * Assumes the input timestamp might need local time conversion.
     * @param {string} timestamp - The timestamp string (e.g., "YYYY-MM-DD HH:MM:SS").
     * @param {boolean} [includeTime=true] - Whether to include the time part.
     * @returns {string} Formatted date/time string or 'غير محدد'.
     */
    function formatTimestamp(timestamp, includeTime = true) {
        if (!timestamp) return 'غير محدد';
        try {
            // Assumes timestamp from DB is UTC or needs conversion based on server/DB config
            // The .replace(' ', 'T') helps parsing in some JS environments
            const date = new Date(timestamp.replace(' ', 'T'));
            // Check if date is valid after parsing
            if (isNaN(date.getTime())) {
                throw new Error("Invalid date parsed");
            }
            const options = {
                // timeZone: 'Asia/Baghdad', // Uncomment if explicit timezone conversion is needed
                year: 'numeric', month: 'short', day: 'numeric',
            };
            if (includeTime) {
                options.hour = 'numeric'; options.minute = '2-digit';
                // options.hour12 = true; // Optional: Use 12-hour format
            }
            // Use 'ar-IQ' locale for Arabic formatting specific to Iraq
            return new Intl.DateTimeFormat('ar-IQ', options).format(date);
        } catch (e) {
            console.error("Error formatting date:", timestamp, e);
            return timestamp; // Return original string as fallback
        }
    }

    /**
     * Determines the Font Awesome icon class based on material type and filename extension.
     * @param {string} materialType - The type of the material (e.g., 'slide', 'document').
     * @param {string} [filename=''] - The original filename to check extension.
     * @returns {string} Font Awesome icon class string.
     */
    function getFileIconClass(materialType, filename = '') {
        const extension = filename.split('.').pop().toLowerCase();
        switch (materialType) {
            case 'slide': return 'fas fa-file-powerpoint text-danger'; // PowerPoint
            case 'document':
                 if (extension === 'pdf') return 'fas fa-file-pdf text-danger'; // PDF
                 if (['doc', 'docx'].includes(extension)) return 'fas fa-file-word text-primary'; // Word
                 if (['xls', 'xlsx'].includes(extension)) return 'fas fa-file-excel text-success'; // Excel
                 return 'fas fa-file-alt text-secondary'; // Generic document
            case 'link': return 'fas fa-link text-warning'; // Web Link
            case 'other': // Handle common 'other' types
            default:
                 if (['zip', 'rar', '7z'].includes(extension)) return 'fas fa-file-archive text-muted'; // Archive
                 if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'].includes(extension)) return 'fas fa-file-image text-info'; // Image
                 if (['mp4', 'mov', 'avi', 'wmv', 'mkv'].includes(extension)) return 'fas fa-file-video text-purple'; // Video (Added purple color example)
                 if (['mp3', 'wav', 'ogg'].includes(extension)) return 'fas fa-file-audio text-orange'; // Audio (Added orange color example)
                 return 'fas fa-file text-muted'; // Default file icon
        }
    }

    /**
     * Extracts and validates the 'id' parameter from the URL query string.
     * @returns {number|null} The validated course ID or null if invalid/missing.
     */
    function getCourseIdFromUrl() {
        try {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('id');
            const parsedId = id ? parseInt(id, 10) : null;
            // Validate if it's a positive integer
            if (!parsedId || isNaN(parsedId) || parsedId <= 0) {
                console.error("Invalid or missing Course ID 'id' in URL parameter.");
                return null;
            }
            return parsedId;
        } catch (e) {
            console.error("Error getting Course ID from URL:", e);
            return null;
        }
    }

    // --- Display Functions ---

    /**
     * Renders the list of file materials in the 'files' tab.
     * @param {Array} materials - Array of file material objects.
     */
    function displayMaterials(materials) {
        if (!filesContentPane) { console.error("Files content pane (#filesContent) not found."); return; }
        filesContentPane.innerHTML = ''; // Clear loading/previous content

        if (!materials || materials.length === 0) {
            filesContentPane.innerHTML = '<p class="text-center text-secondary p-4">لا توجد ملفات متاحة لهذه المادة حالياً.</p>';
            return;
        }

        const listGroup = document.createElement('div');
        listGroup.className = 'list-group list-group-flush material-list'; // Use flush for borderless items

        materials.forEach(material => {
             const fileSizeFormatted = material.file_size_bytes ? formatBytes(material.file_size_bytes) : '';
             const uploadedDateFormatted = formatTimestamp(material.uploaded_at, true); // Include time
             const iconClass = getFileIconClass(material.material_type, material.original_filename || material.title);
             // Set download attribute correctly
             const downloadAttribute = material.original_filename ? `download="${material.original_filename}"` : 'download';
             // Determine if preview is possible and set button state
             const canPreview = material.isPreviewable && material.previewUrl && material.previewUrl !== '#';
             const previewButtonClass = canPreview ? 'btn-outline-info' : 'btn-outline-secondary disabled';
             const previewTarget = canPreview ? '_blank' : '_self'; // Open preview in new tab if possible
             // Determine if download is possible and set button state
             const canDownload = material.downloadUrl && material.downloadUrl !== '#';
             const downloadButtonClass = canDownload ? 'btn-outline-light' : 'btn-outline-secondary disabled';

             const listItem = document.createElement('div');
             // Use Bootstrap list group item structure
             listItem.className = 'list-group-item bg-transparent border-bottom border-secondary py-3 px-0';
             listItem.innerHTML = `
                 <div class="d-flex w-100 justify-content-between align-items-center flex-wrap">
                     <div class="d-flex align-items-center mb-2 mb-md-0 me-md-3 flex-grow-1"> <i class="${iconClass} fa-fw fa-2x me-3"></i>
                         <div>
                             <h5 class="mb-1 text-light material-title">${material.title || 'بدون عنوان'}</h5>
                             <small class="text-muted d-block material-description">${material.description || ''}</small>
                         </div>
                     </div>
                     <div class="text-start text-md-end mt-2 mt-md-0 material-actions flex-shrink-0"> <small class="text-muted d-block mb-2">تاريخ الرفع: ${uploadedDateFormatted}</small>
                         <a href="${canPreview ? material.previewUrl : '#'}" class="btn ${previewButtonClass} btn-sm me-2 ${!canPreview ? 'disabled' : ''}" target="${previewTarget}" title="${canPreview ? 'معاينة الملف' : 'المعاينة غير متاحة'}">
                             <i class="fas fa-eye fa-fw"></i><span class="d-none d-sm-inline ms-1">معاينة</span>
                         </a>
                         <a href="${canDownload ? material.downloadUrl : '#'}" class="btn ${downloadButtonClass} btn-sm ${!canDownload ? 'disabled' : ''}" ${canDownload ? downloadAttribute : ''} title="${canDownload ? 'تحميل الملف' : 'التحميل غير متاح'}">
                             <i class="fas fa-download fa-fw"></i><span class="d-none d-sm-inline ms-1">تحميل</span>
                             ${fileSizeFormatted ? `<span class="badge bg-secondary ms-2 align-middle">${fileSizeFormatted}</span>` : ''}
                         </a>
                     </div>
                 </div>
             `;
             listGroup.appendChild(listItem);
        });
        filesContentPane.appendChild(listGroup);
    }

    /**
     * Renders the list of video materials in the 'videos' tab.
     * @param {Array} videos - Array of video material objects.
     */
    function displayVideos(videos) {
        if (!videosContentPane) { console.error("Videos content pane (#videosContent) not found."); return; }
        videosContentPane.innerHTML = ''; // Clear loading/previous content

        if (!videos || videos.length === 0) {
            videosContentPane.innerHTML = '<p class="text-center text-secondary p-4">لا توجد فيديوهات متاحة لهذه المادة حالياً.</p>';
            return;
        }

        const videoListGroup = document.createElement('div');
        videoListGroup.className = 'list-group list-group-flush video-list';

        videos.forEach(video => {
            const embedUrl = video.signedEmbedUrl; // Use the signed URL from backend
            if (!embedUrl) {
                console.warn(`Video '${video.title || video.material_id}' is missing a signed embed URL.`);
                return; // Skip videos without a valid URL
            }

            // Create a button for each video item to trigger the modal
            const listItem = document.createElement('button');
            listItem.type = 'button';
            listItem.className = 'list-group-item list-group-item-action bg-transparent border-bottom border-secondary py-3 px-2 d-flex justify-content-between align-items-center';
            listItem.setAttribute('data-video-url', embedUrl);
            listItem.setAttribute('data-video-title', video.title || 'فيديو'); // Store title for modal

            const uploadedDateFormatted = formatTimestamp(video.uploaded_at, true); // Include time

            listItem.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-play-circle fa-fw fa-lg me-3 text-info"></i>
                    <div>
                        <span class="video-item-title text-light">${video.title || 'فيديو'}</span>
                        <small class="video-item-date text-muted d-block">تاريخ الرفع: ${uploadedDateFormatted}</small>
                    </div>
                </div>
                <i class="fas fa-chevron-left play-icon text-muted"></i> `;
            videoListGroup.appendChild(listItem);
        });
        videosContentPane.appendChild(videoListGroup);
    }

    /**
     * Renders the list of assignments in the 'assignments' tab.
     * Relies on Bootstrap's data attributes for modal triggering.
     * @param {Array} assignments - Array of assignment objects.
     */
    function displayAssignments(assignments) {
        // Use assignmentsContentPane which is defined in this file's scope
        if (!assignmentsContentPane) {
            console.error("Assignments content pane (#assignmentsContent) not found.");
            return;
        }
        assignmentsContentPane.innerHTML = ''; // Clear placeholder/previous content

        if (!assignments || assignments.length === 0) {
            assignmentsContentPane.innerHTML = '<div class="text-center text-secondary p-4">لا توجد واجبات متاحة لهذه المادة حالياً.</div>';
            return;
        }

        const listWrapper = document.createElement('div');
        // Use 'row' for consistency, but cards will likely stack in a single column in the tab pane
        listWrapper.className = 'row';

        assignments.forEach((assignment, index) => {
             const colWrapper = document.createElement('div');
             // Use full width column inside the tab pane
             colWrapper.className = 'col-12 mb-4';
             const card = document.createElement('div');
             card.className = 'assignment-card card bg-dark text-light h-100 shadow-sm';

             // --- Deadline & Type Handling ---
             // Ensure formatTimestamp function is accessible
             const deadlineFormatted = typeof formatTimestamp === 'function' ? formatTimestamp(assignment.deadline_at, true) : assignment.deadline_at;
             const submissionMethodText = assignment.assignment_type === 'info_only' ? 'تسليم في الصف' : 'تسليم عبر المنصة';
             const submissionMethodClass = assignment.assignment_type === 'info_only' ? 'badge bg-info' : 'badge bg-primary';

             // --- CORRECTED Deadline Check Logic ---
             let deadlinePassed = false;
             let deadlineTimestampForAttr = 0; // For the data attribute

             if (assignment.deadline_at) {
                 try {
                     // Parse the DB string WITHOUT 'Z', assuming it's local time
                     const deadlineDateLocal = new Date(assignment.deadline_at.replace(' ', 'T'));
                     if (isNaN(deadlineDateLocal.getTime())) {
                         console.warn(`SubjectDetails - Assignment ${assignment.assignment_id}: Could not parse deadline '${assignment.deadline_at}' as local time.`);
                         deadlinePassed = true; // Fail safe
                     } else {
                         deadlineTimestampForAttr = deadlineDateLocal.getTime();
                         const nowDate = new Date();
                         deadlinePassed = nowDate > deadlineDateLocal; // Direct comparison
                     }
                      console.log(`DEBUG (SubjectDetails) - Assignment ${assignment.assignment_id}: Deadline String '${assignment.deadline_at}', Parsed Deadline (Browser Local) ${deadlineDateLocal.toLocaleString()}, Now (Browser Local) ${new Date().toLocaleString()}, Passed: ${deadlinePassed}`);
                 } catch (e) {
                     console.error(`SubjectDetails - Assignment ${assignment.assignment_id}: Error checking deadline '${assignment.deadline_at}'`, e);
                     deadlinePassed = true; // Fail safe
                 }
             } else {
                 console.log(`SubjectDetails - Assignment ${assignment.assignment_id}: No deadline set.`);
                 deadlinePassed = false;
             }
             // --- End CORRECTED Deadline Check ---

             // --- Button State Calculation ---
             const isElectronic = assignment.assignment_type === 'electronic';
             // Ensure has_submitted is treated as boolean, check if property exists
             const hasSubmitted = assignment.hasOwnProperty('has_submitted') ? Boolean(assignment.has_submitted) : false;

             const canSubmit = isElectronic && !hasSubmitted && !deadlinePassed;
             const uploadButtonDisabled = !canSubmit;

             let uploadButtonClass = '';
             let submitButtonText = '';
             let uploadButtonTitle = '';
             let buttonIcon = 'fa-users'; // Default for 'info_only'

             if (!isElectronic) {
                 uploadButtonClass = 'btn-secondary disabled';
                 submitButtonText = 'في الصف';
                 uploadButtonTitle = 'التسليم في الصف';
                 buttonIcon = 'fa-users';
             } else if (hasSubmitted) {
                  uploadButtonClass = 'btn-success disabled'; // Submitted = Green and disabled
                  submitButtonText = 'تم التسليم';
                  uploadButtonTitle = 'لقد قمت بالتسليم بالفعل';
                  buttonIcon = 'fa-check-circle';
             } else if (deadlinePassed) {
                  uploadButtonClass = 'btn-danger disabled'; // Past Deadline = Red/Gray and disabled
                  submitButtonText = 'انتهى الوقت';
                  uploadButtonTitle = 'انتهى وقت التسليم';
                  buttonIcon = 'fa-times-circle';
             } else { // Electronic, not submitted, not past deadline
                  uploadButtonClass = 'btn-primary'; // Can submit = Blue and enabled
                  submitButtonText = 'تسليم';
                  uploadButtonTitle = 'تسليم الواجب';
                  buttonIcon = 'fa-upload';
             }
             // --- End Button State Calculation ---

             // --- Description Handling ---
             const collapseId = `assignment-subject-collapse-${assignment.assignment_id || index}`;
             const descriptionSnippet = assignment.description ? assignment.description.substring(0, 100) + (assignment.description.length > 100 ? '...' : '') : '';
             let descriptionDir = 'ltr';
             const arabicRegex = /[\u0600-\u06FF]/;
             if (assignment.description && arabicRegex.test(assignment.description)) { descriptionDir = 'rtl'; }

             // --- Deadline Badge Styling ---
              let deadlineBadgeClass = 'badge bg-secondary';
              let deadlineIcon = 'fas fa-calendar-alt';
              if (!deadlinePassed && assignment.deadline_at) {
                  try {
                      const deadlineDate = new Date(deadlineTimestampForAttr || 0);
                      const now = new Date();
                      if ((deadlineDate.getTime() - now.getTime()) / (1000 * 3600 * 24) < 3) {
                           deadlineBadgeClass = 'badge bg-warning text-dark'; deadlineIcon = 'fas fa-clock';
                      } else {
                          deadlineBadgeClass = 'badge bg-success';
                      }
                  } catch(e) {}
              } else if (deadlinePassed && assignment.deadline_at) {
                   deadlineBadgeClass = 'badge bg-danger'; deadlineIcon = 'fas fa-exclamation-triangle';
              }


             // --- Card HTML (NO Course Image/Name here) ---
             card.innerHTML = `
                 <div class="card-body d-flex flex-column">
                      <h5 class="card-title assignment-title mb-2">${assignment.title || 'بدون عنوان'}</h5>
                      <div class="assignment-meta d-flex flex-wrap gap-2 mb-3">
                           <span class="${deadlineBadgeClass}" title="${deadlineFormatted}"><i class="${deadlineIcon} me-1"></i> ${deadlineFormatted}</span>
                           <span class="${submissionMethodClass}">${submissionMethodText}</span>
                           ${hasSubmitted ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> تم التسليم</span>' : ''}
                      </div>
                      ${assignment.description ? `
                           <div class="assignment-description-wrapper mb-auto flex-grow-1">
                                <p class="assignment-description-snippet small text-light" dir="${descriptionDir}">
                                     ${descriptionSnippet}
                                     ${assignment.description.length > 100 ? `
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

                // Attach collapse listener logic (ensure this is correct)
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
        // Append to the correct pane defined in this file's scope
        assignmentsContentPane.appendChild(listWrapper);
   } // End displayAssignments


    // --- Main Data Loading Function ---

    /**
     * Fetches course details (header, files, videos, assignments) from the backend.
     * @param {number} courseId - The ID of the course to load.
     */
    async function loadCourseDetails(courseId) {
        if (!courseId) {
            showMessage("لم يتم تحديد المادة الدراسية المطلوبة.", true);
            if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'none';
            return;
        }
        console.log(`Fetching details for course ID: ${courseId}`);
        // Show loading indicators, hide content
        if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'block';
        if(courseDetailHeader) courseDetailHeader.style.display = 'none';
        if(courseTabsSection) courseTabsSection.style.display = 'none';
        showMessage(''); // Clear previous messages

        try {
            const response = await fetch(`${courseDataEndpoint}?id=${courseId}`);
            console.log(`Response status for course ${courseId}: ${response.status}`);

            if (!response.ok) {
                let errorData = null;
                try { errorData = await response.json(); } catch(e) { /* Ignore parsing error on error response */ }
                 const errorMsg = errorData?.message || `Network error fetching course data (${response.status})`;
                 console.error("Error fetching course details:", errorMsg, response);
                 throw new Error(errorMsg); // Throw error to be caught below
            }
             // Check content type before parsing JSON
             if (!response.headers.get('content-type')?.includes('application/json')) {
                  const textResponse = await response.text();
                  console.error("Received non-JSON response from subject_data:", textResponse);
                 throw new Error('Invalid response format from server.');
             }

            const data = await response.json();
            console.log("Received course data:", data);

            // Check for logout signal from backend
            if (data.logout === true) {
                console.log("Logout signal received from subject_data.");
                window.location.href = loginPageUrl;
                return;
            }

            // Process successful data fetch
            if (data.success && data.course) {
                const course = data.course;

                // --- Update Header ---
                document.title = `${course.course_name || 'تفاصيل المادة'} - جامعتي`; // Set page title
                if (courseImage) {
                    courseImage.src = course.courseImageUrl || defaultCourseImage;
                    courseImage.alt = course.course_name || 'Course';
                    courseImage.onerror = () => { if (courseImage) courseImage.src = defaultCourseImage; }; // Fallback image
                }
                if (courseTitle) courseTitle.textContent = course.course_name || 'N/A';
                if (courseDescription) courseDescription.textContent = course.description || '';
                // Teacher Info
                 if(teacherProfilePic) {
                    teacherProfilePic.src = course.teacherImageUrl || defaultProfilePic;
                    teacherProfilePic.alt = `Teacher ${course.teacher_fname || ''}`;
                    teacherProfilePic.onerror = () => { if(teacherProfilePic) teacherProfilePic.src = defaultProfilePic; };
                 }
                 let teacherFullName = "غير محدد";
                 if(course.teacher_fname || course.teacher_lname) {
                      teacherFullName = `${course.teacher_title || ''} ${course.teacher_fname || ''} ${course.teacher_lname || ''}`.trim();
                 }
                  if (teacherNameSpan) teacherNameSpan.textContent = teacherFullName;
                  if (teacherTitleSpan) teacherTitleSpan.textContent = course.teacher_title || '';

                // --- Display Content in Tabs ---
                displayMaterials(data.fileMaterials || []);
                displayVideos(data.videoMaterials || []);
                displayAssignments(data.assignments || []); // Use the updated display function

                // --- Show Content Sections ---
                if(courseDetailHeader) courseDetailHeader.style.display = 'flex'; // Show header
                if(courseTabsSection) courseTabsSection.style.display = 'block'; // Show tabs

            } else { // Handle case where backend responds with success: false
                 const failMsg = data.message || "فشل تحميل بيانات المادة من الخادم.";
                 console.error("Backend failed to provide course data:", failMsg);
                 showMessage(failMsg, true);
                 // Show error messages in tab panes
                 if (filesContentPane) filesContentPane.innerHTML = '<p class="text-center text-danger p-4">فشل تحميل محتوى الملفات.</p>';
                 if (videosContentPane) videosContentPane.innerHTML = '<p class="text-center text-danger p-4">فشل تحميل محتوى الفيديوهات.</p>';
                 if (assignmentsContentPane) assignmentsContentPane.innerHTML = '<p class="text-center text-danger p-4">فشل تحميل محتوى الواجبات.</p>';
            }
        } catch (error) { // Handle fetch errors or errors thrown above
            console.error('Critical error in loadCourseDetails:', error);
            showMessage(`خطأ في تحميل التفاصيل: ${error.message}`, true);
             // Show error messages in tab panes
             if (filesContentPane) filesContentPane.innerHTML = '<p class="text-center text-danger p-4">حدث خطأ أثناء تحميل محتوى الملفات.</p>';
             if (videosContentPane) videosContentPane.innerHTML = '<p class="text-center text-danger p-4">حدث خطأ أثناء تحميل محتوى الفيديوهات.</p>';
             if (assignmentsContentPane) assignmentsContentPane.innerHTML = '<p class="text-center text-danger p-4">حدث خطأ أثناء تحميل محتوى الواجبات.</p>';
             // Ensure header/tabs remain hidden on critical error
             if(courseDetailHeader) courseDetailHeader.style.display = 'none';
             if(courseTabsSection) courseTabsSection.style.display = 'none';
        } finally {
            // Always hide loading indicator
             if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'none';
        }
    }

    /**
     * Fetches and updates the user's profile picture and name in the navbar.
     */
    async function loadNavbarProfile() {
         console.log("Subject Details JS: Loading Navbar Profile...");
         if (!userProfilePicNav && !userNameNav) {
             console.warn("Navbar elements (userProfilePicNav/userNameNav) not found.");
             return; // Exit if navbar elements aren't present
         }
         const endpoint = profileDataEndpoint;
         try {
             const response = await fetch(endpoint);
              if (!response.ok) { throw new Error(`Navbar Profile Network Error: ${response.status}`); }
              if (!response.headers.get('content-type')?.includes('application/json')) {
                  throw new Error('Navbar Profile received non-JSON response');
              }
             const data = await response.json();
             console.log("Navbar profile data received:", data);

             if (data.success) {
                 // Update navbar elements if data is successfully fetched
                 if (userProfilePicNav) {
                     userProfilePicNav.src = data.profilePicUrl || defaultProfilePic;
                     userProfilePicNav.onerror = () => { userProfilePicNav.src = defaultProfilePic; }; // Fallback
                 }
                 if (userNameNav) {
                     const fullName = [data.firstName, data.lastName].filter(Boolean).join(' ').trim();
                     userNameNav.textContent = fullName || 'المستخدم';
                     userNameNav.classList.remove('d-none'); // Show the name
                 }
                 console.log("Navbar profile updated.");
             } else {
                 // Handle backend failure or logout signal
                 if (data.logout) {
                     console.log("Logout signal received from user_data.");
                     window.location.href = loginPageUrl; // Redirect to login
                     return;
                 }
                 console.error('Failed to load navbar profile (backend success=false):', data.message);
                 // Use defaults if loading failed
                 if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic;
                 if (userNameNav) userNameNav.textContent = "المستخدم";
             }
         } catch (error) { // Handle fetch/network errors
             console.error('Error loading navbar profile (catch block):', error);
             // Use defaults on error
             if (userProfilePicNav) userProfilePicNav.src = defaultProfilePic;
             if (userNameNav) userNameNav.textContent = "المستخدم";
         }
     }

    // --- Event Listeners ---

     /**
      * Event listener for clicking on video items to open the video player modal.
      */
     if (videosContentPane && videoModalInstance) {
          // Use event delegation on the container
          videosContentPane.addEventListener('click', (event) => {
              // Find the closest ancestor button that represents a video item
              const clickedItem = event.target.closest('.list-group-item-action[data-video-url]');
              if (!clickedItem) return; // Exit if the click wasn't on a video item button

              const videoUrl = clickedItem.getAttribute('data-video-url');
              const videoTitle = clickedItem.getAttribute('data-video-title');

              // Basic validation for the URL
              if (!videoUrl || videoUrl === '#') {
                  showMessage("لا يمكن تشغيل هذا الفيديو حالياً.", true);
                  return;
              }

              // Update modal title and content
              if (videoPlayerModalTitle) { videoPlayerModalTitle.textContent = videoTitle || 'مشغل الفيديو'; }
              if (videoPlayerModalBody) {
                   // Use responsive iframe wrapper
                   videoPlayerModalBody.innerHTML = `
                       <div style="position: relative; padding-top: 56.25%; background-color: #000;">
                           <iframe src="${videoUrl}" loading="lazy" style="border: none; position: absolute; top: 0; left: 0; height: 100%; width: 100%;"
                                   allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                                   allowfullscreen="true">
                           </iframe>
                       </div>`;
              }
              // Show the modal
              videoModalInstance.show();
          });
     } else {
         if (!videosContentPane) console.warn("Video content pane not found for click listener.");
         if (!videoModalInstance) console.warn("Video player modal instance not found or failed to initialize.");
     }

    /**
     * Event listener to clear the video modal content when it's hidden.
     */
    if (videoPlayerModalElement) {
         videoPlayerModalElement.addEventListener('hidden.bs.modal', () => {
              // Clear the iframe to stop video playback
              if (videoPlayerModalBody) { videoPlayerModalBody.innerHTML = ''; }
              // Reset the title
              if (videoPlayerModalTitle) { videoPlayerModalTitle.textContent = 'مشغل الفيديو'; }
         });
    }

    // --- Initial Page Load Logic ---
    console.log("Subject Details JS: Starting initial load...");
    const courseId = getCourseIdFromUrl(); // Get course ID from URL

    // Load navbar profile first, then load course details if ID is valid
    loadNavbarProfile().then(() => {
         console.log("Navbar profile loading attempted.");
         if (courseId) {
             loadCourseDetails(courseId); // Load main content
         } else {
             // Handle invalid/missing course ID
             console.error("Subject Details JS: No valid course ID found in URL.");
             showMessage("لم يتم العثور على معرف المادة في الرابط. يرجى الرجوع لقائمة المواد.", true);
             // Hide loading/content sections if no ID
             if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'none';
             if(courseDetailHeader) courseDetailHeader.style.display = 'none';
             if(courseTabsSection) courseTabsSection.style.display = 'none';
         }
    }).catch(error => {
         // Handle errors during the initial navbar load sequence
         console.error("Error during initial navbar load sequence:", error);
          // Still try to load course details if ID exists, even if navbar failed
          if (courseId) {
             loadCourseDetails(courseId);
         } else {
             // Handle case where both navbar load failed AND course ID is missing
             showMessage("لم يتم العثور على معرف المادة في الرابط.", true);
             if(headerLoadingIndicator) headerLoadingIndicator.style.display = 'none';
         }
    });

}); // End DOMContentLoaded
