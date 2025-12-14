const USE_MOCK = false; // Set to false when using the real backend
const DB_VERSION = 2; // Bump this to refresh data on the client side

// DEBUG BANNER
const banner = document.createElement('div');
banner.id = 'debug-banner';
banner.style.position = 'fixed';
banner.style.top = '0';
banner.style.left = '0';
banner.style.width = '100%';
banner.style.background = '#333';
banner.style.color = '#fff';
banner.style.textAlign = 'center';
banner.style.padding = '10px';
banner.style.zIndex = '9999';
banner.style.fontSize = '14px';
banner.innerText = 'Initializing...';
document.body.appendChild(banner);

function updateBanner(msg, color) {
    if (banner) {
        banner.innerText = msg;
        banner.style.background = color;
    }
}

async function findBackendUrl() {
    const candidates = [
        'http://localhost:8000/backend/api/',        // Priority: Match Standard Browser URL
        'http://127.0.0.1:8000/backend/api/',  // Fallback
        'http://localhost/Pet_Shelter/backend/api/', // XAMPP
        'http://localhost:8080/Pet_Shelter/backend/api/'
    ];

    for (const url of candidates) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 1000); // 1s timeout
            
            const res = await fetch(url + 'test_connection.php', { 
                method: 'GET',
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            
            if (res.ok) {
                console.log("Found Backend at:", url);
                return url;
            }
        } catch (e) { 
            // Continue to next candidate
        }
    }
    return null;
}

async function apiCall(endpoint, method = 'GET', data = null) {
    // 1. Check for File Protocol Error
    if (window.location.protocol === 'file:') {
        alert("⚠️ CRITICAL ERROR ⚠️\n\nYou are opening the file directly (file://).\nPlease use the VS Code Live Server (http://localhost:5500/...)");
        return { success: false, error: 'Opened via file:// protocol' };
    }
    
    // 2. Auto-fix 127.0.0.1 usage (Cookies won't work with localhost backend)
    if (window.location.hostname === '127.0.0.1') {
        const newUrl = window.location.href.replace('127.0.0.1', 'localhost');
        window.location.replace(newUrl);
        return { success: false, error: 'Redirecting to localhost...' };
    }

    if (USE_MOCK) return mockApi(endpoint, method, data);
    
    const options = {
        method: method,
        credentials: 'include', // Important: Send cookies with the request
    };

    if (data) {
        if (data instanceof FormData) {
            options.body = data; 
        } else {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(data);
        }
    }
    
    let baseUrl = '';
    
    try {
        baseUrl = await findBackendUrl();
        
        if (!baseUrl) {
             // Fallback to 8000 just to show a meaningful error path if discovery fails
             baseUrl = 'http://localhost:8000/backend/api/'; 
        }

        const response = await fetch(baseUrl + endpoint, options);
        
        // 3. Handle HTTP Errors (404, 500, 401)
        if (!response.ok) {
            const errText = await response.text();
            console.error(`HTTP Error: ${response.status} at ${response.url}`, errText);
            return { success: false, error: `Server Error (${response.status}) at ${response.url}: ${errText || 'Check XAMPP/Console'}` };
        }

        // 4. Handle JSON Errors (PHP Warnings mixed with JSON)
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Invalid JSON:", text);
            return { success: false, error: 'Server returned invalid data. See Console.' };
        }

    } catch (error) {
        console.error('API Error:', error);
        let msg = 'Network Connection Failed.';
        const fullUrl = baseUrl + endpoint;
        
        if (window.location.port === '5500') {
             msg += ` Is start_backend.bat running? (Trying: ${fullUrl})`;
        } else {
             msg += ` Is XAMPP running? Check Console for details. (Target: ${fullUrl})`;
        }
        return { success: false, error: msg };
    }
}
// Mock API removed. Using real backend.

function fileToDataUrl(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.readAsDataURL(file);
    });
}

let warmModalCallback = null;

function showWarmModal(title, msg, icon = '🐾', callback = null) {
    let modal = document.getElementById('warm-modal');
    warmModalCallback = callback; // Store the callback

    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'warm-modal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="modal-icon">${icon}</span>
                <h2 class="modal-title">${title}</h2>
                <p class="modal-msg">${msg}</p>
                <button onclick="closeWarmModal()" class="btn btn-primary">Okay!</button>
            </div>`;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-icon').innerHTML = icon;
        modal.querySelector('.modal-title').innerText = title;
        modal.querySelector('.modal-msg').innerText = msg;
    }
    setTimeout(() => modal.classList.add('active'), 10);
}

function closeWarmModal() {
    const modal = document.getElementById('warm-modal');
    if(modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            if (warmModalCallback && typeof warmModalCallback === 'function') {
                warmModalCallback();
                warmModalCallback = null; // Reset
            }
        }, 300); // Wait for transition
    }
}

async function checkSession() {
    // Debug: Test connection first
    try {
        const test = await apiCall('test_connection.php');
        console.log("Backend Connection Test:", test);
    } catch(e) { console.error("Connection Test Failed", e); }

    let res = { loggedIn: false };
    try {
        res = await apiCall('auth.php?action=check_session');
    } catch (e) {
        console.error("Session Check Failed:", e);
    }
    
    // Ensure nav is ALWAYs updated, even if backend fails
    if (!res || typeof res.loggedIn === 'undefined') {
        res = { loggedIn: false };
    }
    
    updateNav(res.loggedIn, res.user);
    
    // Remove the initializing banner once done
    const b = document.getElementById('debug-banner');
    if(b) b.remove();
    
    return res;
}

function updateNav(loggedIn, user) {
    const nav = document.getElementById('nav-links');
    if (!nav) return;
    
    let html = `
        <a href="index.html">Home</a>
        <a href="about.html">About Us</a>
        <div class="dropdown">
            <a href="#">Adoption ▾</a>
            <div class="dropdown-content">
                <a href="adopt.html">Adopt a Pet</a>
                <a href="give_pet.html">Give a Pet</a>
                <a href="rescue.html">Report a Rescue</a>
            </div>
        </div>
    `;
    
    // Only show Donate to non-admin or public
    if (!loggedIn || (loggedIn && user.role !== 'admin')) {
        html += `<a href="donate.html">Donate</a>`;
    }
    
    if (loggedIn) {
        if (user.role === 'admin') {
            html += `<a href="admin_dashboard.html" class="btn btn-primary">Admin Panel</a>`;
        } else if (user.role === 'volunteer') {
            html += `<a href="volunteer_dashboard.html" class="btn btn-primary">Volunteer Panel</a>`;
        } else {
            html += `<a href="user_dashboard.html" class="btn btn-primary">Dashboard</a>`;
        }
        html += `<a href="#" onclick="logout()">Logout</a>`;
    } else {
        html += `<a href="login.html" class="btn btn-primary">Login / Register</a>`;
    }
    nav.innerHTML = html;
}

// Custom Confirm Modal
let confirmCallback = null;

function showConfirmModal(title, msg, onConfirm, yesText = 'Yes', noText = 'Cancel') {
    let modal = document.getElementById('confirm-modal');
    confirmCallback = onConfirm;

    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'confirm-modal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 350px; text-align: center; padding: 25px;">
                <h3 style="margin-bottom: 10px;">${title}</h3>
                <p style="margin-bottom: 20px; color: #555;">${msg}</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button id="confirm-yes-btn" onclick="closeConfirmModal(true)" class="btn btn-primary" style="padding: 10px 20px;">${yesText}</button>
                    <button id="confirm-no-btn" onclick="closeConfirmModal(false)" class="btn btn-secondary" style="padding: 10px 20px; background: #ccc; border: none; color: #333;">${noText}</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('h3').innerText = title;
        modal.querySelector('p').innerText = msg;
        modal.querySelector('#confirm-yes-btn').innerText = yesText;
        modal.querySelector('#confirm-no-btn').innerText = noText;
    }
    setTimeout(() => modal.classList.add('active'), 10);
}

function closeConfirmModal(confirmed) {
    const modal = document.getElementById('confirm-modal');
    if(modal) {
        modal.classList.remove('active');
        if(confirmed && confirmCallback) confirmCallback();
        confirmCallback = null;
    }
}

async function logout() {
    showConfirmModal("Log Out?", "Are you sure you want to log out of your account?", async () => {
        await apiCall('auth.php?action=logout');
        window.location.href = 'index.html';
    }, "Yes, Logout", "Cancel");
}

async function updatePetDetails(id) {
    const form = document.getElementById('edit-pet-form');
    const formData = new FormData(form);
    formData.append('id', id);

    const res = await apiCall('pets.php?action=update', 'POST', formData);
    
    if (res.success) {
        showWarmModal('Success!', 'Pet profile updated successfully.', '✅', () => {
            closePetModal();
            loadPets(); // Refresh grid
        });
    } else {
        showWarmModal('Error', 'Failed to update: ' + (res.error || 'Unknown error'), '❌');
    }
}

document.addEventListener('DOMContentLoaded', checkSession);

// --- Shared Pet Logic ---

function getStatusColor(status) {
    if (status === 'yellow') return { bg: '#ffc107', text: '#333' };
    if (status === 'red') return { bg: '#dc3545', text: 'white' };
    return { bg: '#28a745', text: 'white' };
}

async function loadPets() {
    const container = document.getElementById('pet-container');
    if (!container) return;

    // container.innerHTML = 'Loading pets...'; // Optional: show loading state
    const pets = await apiCall('pets.php');
    window.allPets = pets || [];
    
    if (!pets || pets.length === 0) {
        container.innerHTML = '<p style="text-align:center; width:100%;">No pets available for adoption right now. Check back later!</p>';
        return;
    }

    container.innerHTML = '';
    pets.forEach(pet => {
        const card = document.createElement('div');
        card.className = 'pet-card';
        // Handle different image paths
        let imgPath = pet.image;
        if (imgPath && !imgPath.startsWith('http') && !imgPath.startsWith('../')) {
                imgPath = '../backend/' + imgPath;
        }
        
        const colors = getStatusColor(pet.health_status);
        
        let statusText = 'Healthy';
        if(pet.health_status === 'yellow') statusText = 'Needs Help';
        if(pet.health_status === 'red') statusText = 'Critical';

        card.innerHTML = `
            <div style="height:200px; overflow:hidden;">
                <img src="${imgPath}" alt="${pet.name}" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div class="pet-info" style="padding:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                    <h3 style="margin:0;">${pet.name}</h3>
                    <span style="padding:4px 12px; border-radius:20px; background-color:${colors.bg}; color:${colors.text}; font-size:0.8rem; font-weight:bold;">
                        ${statusText}
                    </span>
                </div>
                <p class="type" style="color:#666; font-size:0.9rem; margin-bottom:10px;">${pet.type}</p>
                
                <p style="font-size:0.9rem; color:#555; height:45px; overflow:hidden; margin-bottom:10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">${pet.description || 'No description available.'}</p>
                <button onclick="viewDetails('${pet.id}')" class="btn btn-primary" style="width:100%;">View Details</button>
            </div>
        `;
        container.appendChild(card);
    });
}

// --- Chat System ---

let chatPollInterval = null;
let currentChatUser = null;

function initChat() {
    // Hide on login/register pages
    if (window.location.pathname.includes('login.html') || window.location.pathname.includes('register.html')) {
        return;
    }

    // Only show if logged in
    checkSession().then(res => {
        if (res.loggedIn) {
            createChatWidget();
        }
    });
}

function createChatWidget() {
    if (document.getElementById('chat-widget-btn')) return;

    // Button
    const btn = document.createElement('button');
    btn.id = 'chat-widget-btn';
    btn.className = 'chat-widget-btn';
    btn.innerHTML = `<img src="images/chat_icon_orange.png" alt="Chat" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
    btn.onclick = toggleChat;
    document.body.appendChild(btn);

    // Box
    const box = document.createElement('div');
    box.id = 'chat-box';
    box.className = 'chat-box';
    box.innerHTML = `
        <div class="chat-header">
            <h3 id="chat-title">Messages</h3>
            <button class="chat-close" onclick="toggleChat()">×</button>
        </div>
        <div class="chat-body" id="chat-body">
            <!-- Content goes here -->
        </div>
        <div class="chat-footer" id="chat-footer" style="display:none;">
            <input type="text" id="chat-input" placeholder="Type a message..." onkeypress="handleChatEnter(event)">
            <button onclick="sendChatMessage()">➤</button>
        </div>
    `;
    document.body.appendChild(box);
}

async function toggleChat() {
    const box = document.getElementById('chat-box');
    box.classList.toggle('active');
    
    if (box.classList.contains('active')) {
        // Opened
        if (!currentChatUser) {
            await loadChatTargets();
        } else {
            scrollToBottom();
            startPolling();
        }
    } else {
        // Closed
        stopPolling();
    }
}

async function loadChatTargets() {
    const body = document.getElementById('chat-body');
    const footer = document.getElementById('chat-footer');
    const title = document.getElementById('chat-title');
    
    title.innerText = 'Messages';
    footer.style.display = 'none';
    body.innerHTML = '<p style="text-align:center; margin-top:20px; color:#888;">Connecting...</p>';
    currentChatUser = null;
    stopPolling();

    try {
        const res = await apiCall('messages.php?action=get_targets');
        
        if (res.success && res.data.length > 0) {
            body.innerHTML = '<div class="user-list"></div>';
            const list = body.querySelector('.user-list');
            
            res.data.forEach(user => {
                const item = document.createElement('div');
                item.className = 'user-item';
                item.onclick = () => openConversation(user.id, user.name);
                item.innerHTML = `
                    <div class="user-avatar">${user.name.charAt(0).toUpperCase()}</div>
                    <div class="user-info">
                        <h4>${user.name}</h4>
                        <span>${user.role}</span>
                    </div>
                `;
                list.appendChild(item);
            });
        } else {
            console.error("Chat Load Error:", res);
            let errorMsg = res.error || "Unknown Error";
            if (!res.success && res.data && res.data.length === 0) errorMsg = "No contacts found.";

            // Extended Diagnostics
            body.innerHTML = `<div style="text-align:center; padding:20px; color:red;">
                <p><strong>Connection Failed</strong></p>
                <small>${errorMsg}</small>
                <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                <div style="text-align:left; font-size:11px; color:#555; background:#f9f9f9; padding:5px; border-radius:4px;">
                    <strong>Diagnostics:</strong><br>
                    URL: ${baseUrl || 'Not Found'}<br>
                    Time: ${new Date().toLocaleTimeString()}
                </div>
                <br>
                <button class="btn btn-primary" onclick="loadChatTargets()">Retry</button>
            </div>`;
        }
    } catch (e) {
        console.error("Chat System Error:", e);
        body.innerHTML = `<div style="text-align:center; padding:20px; color:red;">
            <p><strong>System Error</strong></p>
            <small>${e.message}</small>
            <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
             <div style="text-align:left; font-size:11px; color:#555; background:#f9f9f9; padding:5px; border-radius:4px;">
                <strong>Diagnostics:</strong><br>
                URL: ${baseUrl || 'Not Found'}<br>
                Protocol: ${window.location.protocol}
            </div>
            <br>
            <button class="btn btn-primary" onclick="loadChatTargets()">Retry</button>
        </div>`;
    }
}

async function openConversation(userId, userName) {
    currentChatUser = userId;
    const body = document.getElementById('chat-body');
    const footer = document.getElementById('chat-footer');
    const title = document.getElementById('chat-title');

    title.innerText = userName;
    // Add back button logic if needed, or just relying on "Messages" title click? 
    // Let's add a small back arrow to title if deep in convo? 
    // For simplicity, click '×' to close, or reopen to see list? 
    // A back button is better UX.
    title.innerHTML = `<span onclick="loadChatTargets()" style="cursor:pointer; margin-right:10px;">❮</span> ${userName}`;
    
    footer.style.display = 'flex';
    body.innerHTML = '<div class="conversation-view" id="chat-messages">Loading...</div>';
    
    await loadMessages();
    startPolling();
}

async function loadMessages() {
    if (!currentChatUser) return;
    
    const res = await apiCall(`messages.php?action=get_conversation&user_id=${currentChatUser}`);
    const container = document.getElementById('chat-messages');
    if (!container) return; // Closed?

    if (res.success) {
        const myId = (await checkSession()).user.id; // Cache this?
        
        container.innerHTML = '';
        if (res.data.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#ccc; margin-top:20px;">No messages yet. Say hi!</p>';
            return;
        }

        res.data.forEach(msg => {
            const div = document.createElement('div');
            const isMe = msg.sender_id == myId;
            div.className = `message-bubble ${isMe ? 'message-sent' : 'message-received'}`;
            div.innerText = msg.message;
            div.title = msg.created_at;
            container.appendChild(div);
        });
        scrollToBottom();
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text || !currentChatUser) return;

    input.value = ''; // Optimistic clear
    
    // Append temporarily? Or just wait for poll? 
    // Better to append strictly for UX, but polling handles it too. 
    // Let's just wait for reload for simplicity or manual append.
    
    const res = await apiCall('messages.php?action=send', 'POST', {
        receiver_id: currentChatUser,
        message: text
    });

    if (res.success) {
        await loadMessages();
    } else {
        alert('Failed to send message: ' + (res.error || 'Unknown network error. Check console.'));
        console.error("Send Message Failed:", res);
    }
}

function handleChatEnter(e) {
    if (e.key === 'Enter') sendChatMessage();
}

function scrollToBottom() {
    const body = document.getElementById('chat-body');
    body.scrollTop = body.scrollHeight;
}

function startPolling() {
    stopPolling();
    chatPollInterval = setInterval(loadMessages, 3000); // 3s poll
}

function stopPolling() {
    if (chatPollInterval) clearInterval(chatPollInterval);
    chatPollInterval = null;
}

// Auto init
document.addEventListener('DOMContentLoaded', initChat);

async function askAboutPet(petId, petName) {
    // 1. Check login
    const session = await checkSession();
    if (!session.loggedIn) {
        showWarmModal('Login Required', 'Please login to ask about pets.', '🔒', () => {
            window.location.href = 'login.html';
        });
        return;
    }

    // 2. Open Chat Widget
    const box = document.getElementById('chat-box');
    if (!box.classList.contains('active')) {
        await toggleChat();
    }
    
    // Close the details modal if open
    const detailsModal = document.getElementById('pet-details-modal');
    if(detailsModal) detailsModal.classList.remove('active');

    // 3. Find an Admin to talk to (or specific owner if we had that logic)
    // For now, let's pick the first admin found in targets
    const res = await apiCall('messages.php?action=get_targets');
    let targetId = null;
    let targetName = 'Admin';
    
    if (res.success && res.data.length > 0) {
        // Prefer admin
        const admin = res.data.find(u => u.role === 'admin');
        if (admin) {
            targetId = admin.id;
            targetName = admin.name;
        } else {
            // Fallback to first contact (maybe a volunteer)
            targetId = res.data[0].id;
            targetName = res.data[0].name;
        }
    }
    
    if (!targetId) {
        // If we couldn't find a target (e.g. Network Error), just open the box.
        // The box will show the "Network Error" or "No contacts" state.
        console.warn("No admin target found. Opening chat list.");
        await toggleChat();
        return;
    }

    // 4. Open Conversation
    await openConversation(targetId, targetName);
    
    // 5. Pre-fill input
    const input = document.getElementById('chat-input');
    input.value = `Hi, I am interested in ${petName}. Is this pet still available?`;
    input.focus();
}

