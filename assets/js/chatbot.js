// DOM Elements
const messageInput = document.getElementById('messageInput');
const sendButton = document.getElementById('sendButton');
const chatMessages = document.getElementById('chatMessages');
const chatHistory = document.getElementById('chatHistory');
const searchInput = document.getElementById('searchInput');
const newChatBtn = document.querySelector('.new-chat-btn');
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const mainArea = document.getElementById('mainArea'); // Get main area
const body = document.body; // Get body element for overlay class

// Local Storage Key
const CHAT_HISTORY_KEY = 'universityChatHistory';
let currentChatIndex = -1;

// Load chat history from local storage
let chats = JSON.parse(localStorage.getItem(CHAT_HISTORY_KEY)) || [];

// Initialize the UI
function initializeUI() {
    if (chats.length > 0) {
        // Find the most recent chat (assuming last in array is oldest, first is newest due to unshift)
        currentChatIndex = 0; // Select the first chat (most recent)
        renderChatHistory();
        loadChat(currentChatIndex);
    } else {
        showEmptyState();
        renderChatHistory(); // Render empty state in sidebar if needed
    }
    // Set initial main area margin based on sidebar visibility (only matters on desktop)
    if (window.innerWidth > 768) {
         mainArea.classList.remove('sidebar-hidden');
    } else {
         mainArea.classList.add('sidebar-hidden');
    }
}


// Auto-resize textarea & enable/disable send button
messageInput.addEventListener('input', function () {
    this.style.height = 'auto';
    const maxHeight = parseInt(window.getComputedStyle(this).maxHeight, 10) || 160; // Fallback max height
    this.style.height = Math.min(this.scrollHeight, maxHeight) + 'px';
    sendButton.disabled = this.value.trim() === '';
});

// Render chat history in the sidebar
function renderChatHistory() {
    chatHistory.innerHTML = '';
    if (chats.length === 0) {
        chatHistory.innerHTML = '<p class="no-history-msg">لا توجد محادثات سابقة.</p>';
        return;
    }

    chats.forEach((chat, index) => {
        const lastMessage = chat.messages.length > 0 ? chat.messages[chat.messages.length - 1] : null;
        let previewText = '...';
        if (lastMessage) {
            previewText = lastMessage.text.length > 40 ? lastMessage.text.substring(0, 40) + '...' : lastMessage.text;
        }

        const chatItem = document.createElement('div');
        chatItem.className = `chat-item ${index === currentChatIndex ? 'active' : ''}`;
        chatItem.setAttribute('data-index', index);

        // Using Bootstrap icon for chat
        chatItem.innerHTML = `
          <div class="chat-icon">
             <i class="bi bi-chat-left-text"></i>
          </div>
          <div class="chat-info">
            <div class="chat-title">${chat.name || `محادثة ${index + 1}`}</div>
            <div class="chat-preview">${previewText}</div>
            <div class="chat-actions">
              <button class="rename-btn" title="تعديل الاسم" onclick="event.stopPropagation(); renameChat(${index})"><i class="bi bi-pencil"></i></button>
              <button class="delete-btn" title="حذف المحادثة" onclick="event.stopPropagation(); deleteChat(${index})"><i class="bi bi-trash3"></i></button>
            </div>
          </div>
        `;

        chatItem.addEventListener('click', () => {
            loadChat(index);
            // Close sidebar on mobile after selecting a chat
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                body.classList.remove('sidebar-open'); // Remove overlay class
            }
        });

        chatHistory.appendChild(chatItem);
    });

    highlightActiveChatItem();
}

// Highlight the active chat item in the sidebar
function highlightActiveChatItem() {
    document.querySelectorAll('.chat-item.active').forEach(item => item.classList.remove('active'));
    if (currentChatIndex !== -1) {
        const activeItem = chatHistory.querySelector(`.chat-item[data-index="${currentChatIndex}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
            // Scroll the active item into view if it's not fully visible
            activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

// Show the initial empty state message
function showEmptyState() {
    chatMessages.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-chat-dots-fill empty-state-icon"></i>
        <h2>مرحبًا بكم في منصة جامعتي</h2>
        <p>ابدأ محادثة مع مساعد الذكاء الاصطناعي الخاص بمنصة جامعتي. سيتم حفظ محادثاتك تلقائيًا على جهازك.</p>
      </div>
    `;
     // Update header for empty state
    const chatTitle = mainArea.querySelector('.chat-title-container h1');
    const chatSubtitle = mainArea.querySelector('.chat-subtitle');
    if(chatTitle) chatTitle.textContent = 'منصة جامعتي';
    if(chatSubtitle) chatSubtitle.textContent = 'مساعد الذكاء الاصطناعي';
}


// Load a specific chat by index
function loadChat(index) {
    if (index < 0 || index >= chats.length) {
        currentChatIndex = -1;
        showEmptyState();
        highlightActiveChatItem();
        return;
    }

    currentChatIndex = index;
    const chat = chats[currentChatIndex];
    chatMessages.innerHTML = ''; // Clear messages area




    if (chat && chat.messages.length > 0) {
        chat.messages.forEach(message => {
            addMessageToUI(message.text, message.sender);
        });
        // Scroll to the bottom after a short delay to ensure rendering
        setTimeout(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }, 100);
    } else {
        showEmptyState(); // Show empty state if chat has no messages
    }
    highlightActiveChatItem();
}

// Function to copy text to clipboard
// Function to copy text to clipboard (Refined)
function copyToClipboard(textToCopy, buttonElement) {
    if (!navigator.clipboard) {
        // Fallback for older browsers or insecure contexts (like HTTP)
        console.warn('Clipboard API not available. Falling back to execCommand (may not work).');
        try {
            const textArea = document.createElement("textarea");
            textArea.value = textToCopy;
            textArea.style.position = "fixed"; // Prevent scrolling to bottom
            textArea.style.opacity = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if(successful) {
                 // Visual feedback (same as below)
                 const originalIcon = buttonElement.innerHTML;
                 buttonElement.innerHTML = '<i class="bi bi-check-lg"></i>';
                 buttonElement.classList.add('copied');
                 setTimeout(() => {
                     buttonElement.innerHTML = originalIcon;
                     buttonElement.classList.remove('copied');
                 }, 1500);
            } else {
                 console.error('Fallback copy command failed.');
                 alert("فشل النسخ. متصفحك قد لا يدعم هذه الميزة.");
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            alert("حدث خطأ أثناء محاولة النسخ.");
        }
        return;
    }

    // Use modern Clipboard API
    navigator.clipboard.writeText(textToCopy).then(() => {
        // Success feedback
        const originalIcon = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="bi bi-check-lg"></i>'; // Show checkmark
        buttonElement.classList.add('copied');

        setTimeout(() => {
            buttonElement.innerHTML = originalIcon; // Revert icon
            buttonElement.classList.remove('copied');
        }, 1500); // Revert after 1.5 seconds
    }).catch(err => {
        console.error('Failed to copy text using Clipboard API: ', err);
        alert("فشل النسخ. تأكد من أن الصفحة محملة عبر HTTPS."); // Inform user about potential issue
    });
}

function addMessageToUI(text, sender) {
    const messageContainer = document.createElement('div');
    messageContainer.className = `message-container ${sender}`;
    const avatarPath = window.location.origin + '/assets/images/logo2.png';
  
    // Basic HTML escaping
    const escapeHtml = (unsafe) => {
      return unsafe
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    };
  
    const textElement = sender === 'ai'
      ? `<pre>${escapeHtml(text)}</pre>`
      : `<p>${escapeHtml(text)}</p>`;
  
    if (sender === 'ai') {
      // 1) Build the content div (bubble + copy button)
      const messageContentDiv = document.createElement('div');
      messageContentDiv.className = 'message-content';
  
      const messageBubble = document.createElement('div');
      messageBubble.className = 'message ai';
      messageBubble.innerHTML = textElement;
  
      const copyButton = document.createElement('button');
      copyButton.className = 'copy-btn';
      copyButton.title = 'نسخ النص';
      copyButton.innerHTML = '<i class="bi bi-clipboard"></i>';
  
      // Attach listener directly to this real button
      copyButton.addEventListener('click', (e) => {
        e.stopPropagation();
        
        const pre = messageBubble.querySelector('pre');
        if (pre && pre.textContent) {
          copyToClipboard(pre.textContent, copyButton);
        } else {
          alert("لم يتم العثور على نص للنسخ.");
        }
      });
  
      messageContentDiv.appendChild(copyButton);
      messageContentDiv.appendChild(messageBubble);
  
      // 2) Append content div to container
      messageContainer.appendChild(messageContentDiv);
  
      // 3) Build and append the avatar node
      const avatarDiv = document.createElement('div');
      avatarDiv.className = 'ai-avatar';
      const avatarImg = document.createElement('img');
      avatarImg.src = avatarPath;
      avatarImg.alt = 'AI Avatar';
      avatarDiv.appendChild(avatarImg);
      messageContainer.appendChild(avatarDiv);
  
    } else {
      // User message—no copy button or avatar
      const userContent = document.createElement('div');
      userContent.className = 'message-content';
      userContent.innerHTML = `<div class="message user">${textElement}</div>`;
      messageContainer.appendChild(userContent);
    }
  
    // Remove empty-state if present
    const empty = chatMessages.querySelector('.empty-state');
    if (empty) chatMessages.removeChild(empty);
  
    chatMessages.appendChild(messageContainer);
    // (scrolling logic, etc. stays the same)
  }
  
// Save the entire chat history to local storage
function saveChat() {
    localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(chats));
}

// Create a new, empty chat
function createNewChat() {
    // Find the highest existing chat number to avoid duplicates if chats were deleted
    let maxNum = 0;
    chats.forEach(chat => {
        const match = chat.name.match(/^محادثة (\d+)$/);
        if (match && parseInt(match[1]) > maxNum) {
            maxNum = parseInt(match[1]);
        }
    });
    const newChatName = `محادثة ${maxNum + 1}`; // Default name in Arabic

    chats.unshift({ name: newChatName, messages: [] }); // Add to the beginning
    currentChatIndex = 0; // Set the new chat as active
    saveChat();
    renderChatHistory();
    loadChat(0); // Load the new empty chat

    // Close sidebar on mobile after creating a new chat
    if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
        body.classList.remove('sidebar-open');
    }
    messageInput.focus(); // Focus input field
}

// Event listener for the "New Chat" button
newChatBtn.addEventListener('click', createNewChat);

// Send message function
async function sendMessage() {
    const messageText = messageInput.value.trim();
    if (!messageText) return;

    // If this is the very first message ever, or no chat is selected, create one
    if (currentChatIndex === -1 || chats.length === 0) {
        createNewChat();
        // If creation failed for some reason, stop
        if (currentChatIndex === -1) {
             console.error("Failed to create a new chat before sending.");
             return;
        }
    }


    const userMessage = { sender: 'user', text: messageText };

    // Add user message to UI
    addMessageToUI(messageText, 'user');

    // Add to the current chat's history
    chats[currentChatIndex].messages.push(userMessage);
    saveChat();
    renderChatHistory(); // Update sidebar preview

    // Clear input and reset height
    const currentScroll = chatMessages.scrollTop; // Store scroll position
    messageInput.value = '';
    messageInput.style.height = 'auto';
    sendButton.disabled = true;
     messageInput.focus(); // Keep focus on input

    // Show typing indicator
    showTypingIndicator();
     chatMessages.scrollTop = currentScroll; // Restore scroll position roughly

    try {
        const response = await fetch(window.location.origin + '/backend/ai_api.php', { // Make sure this points to your PHP file
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: messageText }),
        });

         removeTypingIndicator(); // Remove indicator regardless of success/failure

        if (!response.ok) {
            const errData = await response.json().catch(() => ({ error: `HTTP error! Status: ${response.status}` }));
            throw new Error(errData.error || `HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();

        if (data.response) {
            const aiMessage = { sender: 'ai', text: data.response };
            addMessageToUI(data.response, 'ai');
            chats[currentChatIndex].messages.push(aiMessage);

             // Attempt to rename chat after first AI response if name is default
            if (chats[currentChatIndex].messages.length === 2 && chats[currentChatIndex].name.startsWith('محادثة ')) {
               try {
                   const generatedName = await generateChatName(userMessage.text);
                    // Check again if chat still exists and has the default name
                    if (chats[currentChatIndex] && chats[currentChatIndex].name.startsWith('محادثة ')) {
                        chats[currentChatIndex].name = generatedName;
                        saveChat();
                        renderChatHistory(); // Update sidebar immediately
                    }
                } catch (nameError) {
                    console.error("Could not auto-generate chat name:", nameError);
                    saveChat(); // Still save the AI message even if naming failed
                }
            } else {
                saveChat(); // Save chat again after adding AI response
                 renderChatHistory(); // Update preview potentially
            }
        } else {
            throw new Error(data.error || 'Invalid response structure from API');
        }

    } catch (error) {
        console.error('Error sending message:', error);
        removeTypingIndicator();
        // Display error message in chat UI
        const errorMessageText = `عذراً، حدث خطأ ما. الرجاء المحاولة مرة أخرى. (${error.message})`;
        const errorMessage = { sender: 'ai', text: errorMessageText };
        addMessageToUI(errorMessageText, 'ai');
        // Optionally add error to history (might clutter)
        // chats[currentChatIndex].messages.push(errorMessage);
        // saveChat();
    }
}


// Event listeners for sending message
sendButton.addEventListener('click', sendMessage);
messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!sendButton.disabled) { // Only send if button is enabled
            sendMessage();
        }
    }
});

// Show Typing Indicator
function showTypingIndicator() {
    if (chatMessages.querySelector('.typing-indicator')) return;

    const typingDiv = document.createElement('div');
    typingDiv.className = 'message-container ai typing-indicator';

    // --- Make sure this path is correct ---
    const avatarPath = window.location.origin + '/assets/images/logo2.png';

    typingDiv.innerHTML = `
       <div class="message-content">
           <div class="message ai">
               <p>يكتب...</p> </div>
       </div>
       <div class="ai-avatar">
            <img src="${avatarPath}" alt="AI Avatar Typing">
       </div>
    `;
    // Remove empty state if present
    const emptyState = chatMessages.querySelector('.empty-state');
    if (emptyState) chatMessages.removeChild(emptyState);

    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
}
// Remove Typing Indicator
function removeTypingIndicator() {
    const typingDiv = chatMessages.querySelector('.typing-indicator');
    if (typingDiv) {
        typingDiv.remove();
    }
}


// Generate chat name using your API (adapt endpoint/prompt if needed)
async function generateChatName(firstMessage) {
     // Keep it simple for now, return a generic name or implement later
     // You can uncomment the fetch call if your API supports name generation
    
    try {
        const response = await fetch(window.location.origin + '/backend/ai_api.php', { // Adjust endpoint if different
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              
                    message:`Generate only one  short and relevant arabic  chat name for this conversation:"${firstMessage}"`,
            }),
        });
        if (!response.ok) throw new Error(`API error! status: ${response.status}`);
        const data = await response.json();
        return data.response ? data.response.replace(/["*]/g, '').trim() : "محادثة مميزة"; // Fallback
    } catch (error) {
        console.error('Error generating chat name:', error);
        return "محادثة مميزة"; // Fallback name
    }
  
 
}


// Search chats
searchInput.addEventListener('input', () => {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const chatItems = chatHistory.querySelectorAll('.chat-item');

    chatItems.forEach(item => {
        const titleElement = item.querySelector('.chat-title');
        const previewElement = item.querySelector('.chat-preview');
        let isMatch = false;

        if (titleElement && titleElement.textContent.toLowerCase().includes(searchTerm)) {
            isMatch = true;
        }
        if (previewElement && previewElement.textContent.toLowerCase().includes(searchTerm)) {
            // Basic preview search, could expand to full message search if needed
            isMatch = true;
        }

      
        const chatIndex = parseInt(item.getAttribute('data-index'));
        if (!isMatch && chats[chatIndex]) {
            isMatch = chats[chatIndex].messages.some(msg => msg.text.toLowerCase().includes(searchTerm));
        }


        item.style.display = isMatch ? 'flex' : 'none';
    });
});

// Toggle Sidebar
sidebarToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpening = !sidebar.classList.contains('show');
    sidebar.classList.toggle('show');
    body.classList.toggle('sidebar-open', isOpening); // Add overlay class only when opening

    // Adjust main area margin for desktop view
    if (window.innerWidth > 768) {
        mainArea.classList.toggle('sidebar-hidden', !isOpening);
    }
});

// Close sidebar if clicking on the overlay (outside sidebar)
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
        // Check if the click is outside the sidebar AND not on the toggle button itself
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('show');
            body.classList.remove('sidebar-open'); // Remove overlay class
        }
    }
});


// Delete Chat
function deleteChat(index) {
    const chatName = chats[index]?.name || `المحادثة ${index + 1}`;
    if (!confirm(`هل أنت متأكد من حذف "${chatName}"؟ لا يمكن التراجع عن هذا الإجراء.`)) {
        return;
    }

    chats.splice(index, 1); // Remove chat from array
    saveChat(); // Save updated array

    // Logic to decide which chat to load next
    if (chats.length === 0) {
        // No chats left
        currentChatIndex = -1;
        createNewChat(); // Or just show empty state: showEmptyState(); renderChatHistory();
    } else if (currentChatIndex === index) {
        // If the active chat was deleted, load the previous one or the first one
        currentChatIndex = Math.max(0, index - 1);
        loadChat(currentChatIndex);
    } else if (currentChatIndex > index) {
        // If a chat before the active one was deleted, adjust the active index
        currentChatIndex--;
        // No need to reload, just update history and highlight
         renderChatHistory();
         highlightActiveChatItem();
    } else {
         // If a chat after the active one was deleted, index remains the same
         renderChatHistory(); // Just update history
    }

     // Ensure history is re-rendered if not handled by loadChat
     if (currentChatIndex !== index) {
        renderChatHistory();
     }
}

// Rename Chat
function renameChat(index) {
    const currentName = chats[index].name;
    const newName = prompt('أدخل الاسم الجديد للمحادثة:', currentName);

    if (newName && newName.trim() !== '' && newName.trim() !== currentName) {
        chats[index].name = newName.trim();
        saveChat();
        renderChatHistory(); // Update the name in the sidebar
        // If this is the currently loaded chat, update the header title too
       
    }
}

// Initial setup call
initializeUI();