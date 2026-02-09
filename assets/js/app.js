/**
 * AltNET Ecount ERP - Main JavaScript
 */

// ===== CSRF Token for AJAX =====
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// ===== Number Formatting =====
function formatNumber(num) {
    if (!num && num !== 0) return '0';
    return parseInt(num).toLocaleString('ko-KR');
}

function parseNumber(str) {
    if (!str) return 0;
    return parseInt(String(str).replace(/[^0-9\-]/g, '')) || 0;
}

// Apply comma formatting to money input fields
function initMoneyInputs() {
    document.querySelectorAll('.input-money').forEach(el => {
        el.addEventListener('input', function() {
            const pos = this.selectionStart;
            const oldLen = this.value.length;
            const raw = parseNumber(this.value);
            this.value = raw ? formatNumber(raw) : '';
            const newLen = this.value.length;
            const newPos = pos + (newLen - oldLen);
            this.setSelectionRange(newPos, newPos);
        });

        el.addEventListener('focus', function() {
            if (this.value === '0') this.value = '';
        });
    });
}

// ===== Phone number formatting (010-XXXX-XXXX) =====
function initPhoneInputs() {
    document.querySelectorAll('.input-phone').forEach(el => {
        if (!el.value) el.value = '010-';
        el.addEventListener('input', function() {
            let v = this.value.replace(/[^0-9]/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 7) {
                this.value = v.slice(0, 3) + '-' + v.slice(3, 7) + '-' + v.slice(7);
            } else if (v.length > 3) {
                this.value = v.slice(0, 3) + '-' + v.slice(3);
            } else {
                this.value = v;
            }
        });
        el.addEventListener('focus', function() {
            if (!this.value) this.value = '010-';
        });
    });
}

// ===== Sidebar Toggle (Mobile) =====
function initSidebar() {
    const toggle = document.getElementById('menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay && overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
}

// ===== Session Timeout Warning =====
let sessionTimer = null;
let countdownTimer = null;

function initSessionTimer() {
    const warningEl = document.getElementById('session-warning');
    if (!warningEl) return;

    const timeout = parseInt(warningEl.dataset.timeout || 1800) * 1000;
    const warningBefore = parseInt(warningEl.dataset.warning || 300) * 1000;
    const warningAt = timeout - warningBefore;

    resetSessionTimer(warningAt);

    // Extend session button
    const extendBtn = document.getElementById('session-extend');
    if (extendBtn) {
        extendBtn.addEventListener('click', function() {
            fetch('?page=api/session&action=extend', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Content-Type': 'application/json' }
            }).then(r => r.json()).then(data => {
                warningEl.classList.remove('show');
                clearInterval(countdownTimer);
                resetSessionTimer(warningAt);
            });
        });
    }

    // Logout button
    const logoutBtn = document.getElementById('session-logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => { window.location.href = '?page=logout'; });
    }
}

function resetSessionTimer(warningAt) {
    clearTimeout(sessionTimer);
    sessionTimer = setTimeout(() => { showSessionWarning(); }, warningAt);
}

function showSessionWarning() {
    const warningEl = document.getElementById('session-warning');
    if (!warningEl) return;
    warningEl.classList.add('show');

    let remaining = parseInt(warningEl.dataset.warning || 300);
    const countEl = document.getElementById('session-countdown');

    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
        remaining--;
        if (countEl) {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            countEl.textContent = `${m}:${String(s).padStart(2, '0')}`;
        }
        if (remaining <= 0) {
            clearInterval(countdownTimer);
            window.location.href = '?page=logout';
        }
    }, 1000);
}

// ===== Confirm Delete =====
function confirmDelete(msg) {
    return confirm(msg || '정말 삭제하시겠습니까?\n삭제된 데이터는 15일간 보관 후 완전 삭제됩니다.');
}

// ===== Toast Notification =====
function showToast(msg, type) {
    type = type || 'success';
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation:slideIn .3s ease';
    toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle') + '"></i> ' + msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// ===== Daum Address Search =====
function searchAddress(zipcodeId, addressId, detailId) {
    new daum.Postcode({
        oncomplete: function(data) {
            document.getElementById(zipcodeId).value = data.zonecode;
            document.getElementById(addressId).value = data.address;
            if (detailId) document.getElementById(detailId).focus();
        }
    }).open();
}

// ===== AJAX Helper =====
function ajaxPost(url, data) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    if (data) {
        Object.keys(data).forEach(k => formData.append(k, data[k]));
    }
    return fetch(url, { method: 'POST', body: formData })
        .then(r => r.json());
}

// ===== Init on DOM Ready =====
document.addEventListener('DOMContentLoaded', function() {
    initMoneyInputs();
    initPhoneInputs();
    initSidebar();
    initSessionTimer();
});
