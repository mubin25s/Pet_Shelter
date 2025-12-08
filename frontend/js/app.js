const USE_MOCK = true;
const DB_VERSION = 2; // Increment this to force client-side data refresh

async function apiCall(endpoint, method = 'GET', data = null) {
    if (USE_MOCK) return mockApi(endpoint, method, data);
    // ...
}

async function mockApi(endpoint, method, data) {
    await new Promise(r => setTimeout(r, 300));
    
    // DB Loading with Version Check
    let db = JSON.parse(localStorage.getItem('pet_db'));
    let currentVer = localStorage.getItem('db_version');

    if (!db || currentVer != DB_VERSION) {
        console.log('Refreshing DB from data.json...');
        const res = await fetch('data.json');
        db = await res.json();
        localStorage.setItem('pet_db', JSON.stringify(db));
        localStorage.setItem('db_version', DB_VERSION);
    }

    // Auth
    if (endpoint.includes('auth.php')) {
        const action = new URLSearchParams(endpoint.split('?')[1]).get('action');
        if (action === 'login') {
            const user = db.users.find(u => u.email === data.email && u.password === data.password);
            if (user) {
                localStorage.setItem('session', JSON.stringify(user));
                return { success: true, role: user.role };
            }
            return { success: false, error: 'Invalid credentials (Mock: try admin@paws.com / admin)' };
        }
        if (action === 'check_session') {
            const user = JSON.parse(localStorage.getItem('session'));
            return user ? { loggedIn: true, user } : { loggedIn: false };
        }
        if (action === 'logout') {
            localStorage.removeItem('session');
            return { success: true };
        }
        if (action === 'register') {
            data.role = 'user';
            db.users.push(data);
            localStorage.setItem('pet_db', JSON.stringify(db));
            return { success: true };
        }
    }

    // Pets
    if (endpoint.includes('pets.php')) {
        if (method === 'GET') return db.pets;
        if (method === 'POST') {
            let img = '../images/dog_1.webp';
            const file = data.get('image');
            if(file && file instanceof File) {
                img = await fileToDataUrl(file);
            }

            const newPet = {
                id: Date.now(),
                name: data.get('name'),
                type: data.get('type'),
                image: img,
                health_status: data.get('health_status') || 'green',
                status: 'available',
                description: data.get('description'),
                history: data.get('history'),
                vaccine_status: data.get('vaccine_status'),
                food_habit: data.get('food_habit')
            };
            db.pets.unshift(newPet);
            localStorage.setItem('pet_db', JSON.stringify(db));
            return { success: true };
        }
    }
    
    // Misc
    if (endpoint.includes('misc.php')) {
        const type = new URLSearchParams(endpoint.split('?')[1]).get('type');
        
        if (type === 'rescue' && method === 'POST') {
            let img = '../images/dog_1.webp';
            const file = data.get('image');
            if(file && file instanceof File) {
                img = await fileToDataUrl(file);
            }

            const newPet = {
                id: Date.now(),
                name: data.get('name') || 'Unknown',
                type: data.get('type') || 'Rescued',
                image: img,
                health_status: 'red',
                status: 'available',
                description: 'Rescued from: ' + data.get('location') + '. ' + data.get('description'),
                history: 'Condition: ' + data.get('condition'),
                vaccine_status: 'Unknown',
                food_habit: 'Unknown'
            };
            db.pets.unshift(newPet);
            db.rescues.push({ 
                location: data.get('location'), 
                condition_desc: data.get('condition'), 
                status: 'reported' 
            });
            localStorage.setItem('pet_db', JSON.stringify(db));
            return { success: true };
        }

        if(type === 'donation') {
            if(method === 'POST') {
                // If image is present (e.g. receipt), we just mock save it
                // Logic: db.donations.history.push({ amount, image: '...' })
                return { success: true };
            }
            return { balance: 500, total_donations: 500, total_expenses: 0, recent_donations: [], recent_expenses: [] };
        }
    }
    
    // Adoptions
    if (endpoint.includes('adoptions.php')) {
        const action = new URLSearchParams(endpoint.split('?')[1]).get('action');
         if (action === 'list_pending') {
             return db.adoptions || [];
         }
    }

    return { success: true };
}

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
