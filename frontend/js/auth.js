/**
 * OpsMan – Authentication JS
 * Handles login form, token storage, and role-based redirects.
 */

document.addEventListener('DOMContentLoaded', () => {
    // If already logged in, redirect
    const token = getToken();
    const user  = getStoredUser();
    if (token && user) {
        redirectByRole(user.role);
        return;
    }

    const form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', handleLogin);
    }
});

async function handleLogin(e) {
    e.preventDefault();
    clearErrors();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    let valid = true;

    if (!username) {
        showFieldError('usernameError', 'Username is required');
        valid = false;
    }
    if (!password) {
        showFieldError('passwordError', 'Password is required');
        valid = false;
    }
    if (!valid) return;

    setLoading('loginBtn', true);

    try {
        const res = await fetch(API_BASE_URL + '/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
        });

        const data = await res.json();

        if (!data.success) {
            showLoginError(data.error || 'Login failed. Please try again.');
            return;
        }

        // Persist auth
        setAuth(data.data.token, data.data.user);

        showToast('Login successful! Redirecting…', 'success', 1200);
        setTimeout(() => redirectByRole(data.data.user.role), 800);

    } catch (err) {
        showLoginError('Unable to connect to server. Please try again.');
    } finally {
        setLoading('loginBtn', false);
    }
}

function redirectByRole(role) {
    if (role === 'field_employee') {
        window.location.href = 'employee-portal.html';
    } else {
        window.location.href = 'dashboard.html';
    }
}

function showFieldError(id, msg) {
    const el = document.getElementById(id);
    if (el) el.textContent = msg;
}

function showLoginError(msg) {
    const el = document.getElementById('loginError');
    if (el) {
        el.textContent = msg;
        el.classList.remove('hidden');
    }
}

function clearErrors() {
    ['usernameError', 'passwordError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
    const errEl = document.getElementById('loginError');
    if (errEl) errEl.classList.add('hidden');
}
