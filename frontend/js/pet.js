const USE_MOCK = false; // Set to false when using the real backend
const DB_VERSION = 2; // Bump this to refresh data on the client side

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

    // 2. Use the Custom PHP Server URL (Port 8000)
    // We are running a dedicated server for this project to avoid XAMPP path issues
    const baseUrl = 'http://localhost:8000/backend/api/'; 
    
    try {
        const response = await fetch(baseUrl + endpoint, options);
        
        // 3. Handle HTTP Errors (404, 500)
        if (!response.ok) {
            console.error(`HTTP Error: ${response.status}`);
            return { success: false, error: `Server Error (${response.status}). Check XAMPP.` };
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
        return { success: false, error: 'Network Connection Failed. Is XAMPP running?' };
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
    const res = await apiCall('auth.php?action=check_session');
    updateNav(res.loggedIn, res.user);
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
