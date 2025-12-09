const USE_MOCK = false; // Set to false to use real backend
const DB_VERSION = 2; // Increment this to force client-side data refresh

async function apiCall(endpoint, method = 'GET', data = null) {
    if (USE_MOCK) return mockApi(endpoint, method, data);
    
    const options = {
        method: method,
    };

    if (data) {
        if (data instanceof FormData) {
            options.body = data; // Fetch automatically sets Content-Type to multipart/form-data
        } else {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(data);
        }
    }

    // Determine base URL dynamically or use relative path
    // Assuming frontend/js/app.js calling backend/api/
    const baseUrl = '../backend/api/'; 
    
    try {
        const response = await fetch(baseUrl + endpoint, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, error: 'Network error' };
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

function showWarmModal(title, msg, icon = '🐾') {
    let modal = document.getElementById('warm-modal');
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
        setTimeout(() => window.location.href = 'user_dashboard.html', 300);
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
        <div class="dropdown">
            <a href="#">Adoption ▾</a>
            <div class="dropdown-content">
                <a href="adopt.html">Adopt</a>
                <a href="rescue.html">Give a Pet for Adoption</a>
            </div>
        </div>
        <a href="donate.html">Donate</a>
    `;
    
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

async function logout() {
    await apiCall('auth.php?action=logout');
    window.location.href = 'index.html';
}

document.addEventListener('DOMContentLoaded', checkSession);
