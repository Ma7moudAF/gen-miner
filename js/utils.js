/* ========================================
   Utility Functions - دوال مساعدة
======================================== */

/**
 * Show toast notification
 */
function showToast(msg) {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

/**
 * Format number to 2 decimal places
 */
function fmt(n) {
    return parseFloat(n || 0).toFixed(2);
}

/**
 * Format time in HH:MM format
 */
function fmtTime(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
}

/**
 * Open modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

/**
 * Close modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Load HTML component into element
 */
async function loadComponent(elementId, componentPath) {
    try {
        const response = await fetch(componentPath);
        const html = await response.text();
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = html;
        }
    } catch (error) {
        console.error(`Error loading component ${componentPath}:`, error);
    }
}

/**
 * Update top bar balances
 */
function updateTopBar(userData) {
    if (!userData) return;

    const topGen = document.getElementById('topGen');
    const topUsdt = document.getElementById('topUsdt');

    if (topGen) topGen.textContent = fmt(userData.gen_balance || 0);
    if (topUsdt) topUsdt.textContent = fmt(userData.balance || 0);
}

/**
 * Update wallet button UI - للهيدر
 */
function updateWalletUI() {
    // تحديث زر المحفظة في الهيدر
    const walletBtn = document.getElementById('walletBtn');
    if (walletBtn) {
        if (window.APP_STATE.isWalletConnected) {
            walletBtn.classList.add('wallet-connected');
            walletBtn.innerHTML = '✓';
            walletBtn.title = 'Wallet Connected';
        } else {
            walletBtn.classList.remove('wallet-connected');
            walletBtn.innerHTML = '🔷';
            walletBtn.title = 'Connect Wallet';
        }
    }

    // تحديث نص زر المحفظة في الإعدادات
    const walletBtnText = document.getElementById('walletBtnText');
    if (walletBtnText) {
        const isConnected = window.APP_STATE.isWalletConnected;
        const address = window.APP_STATE.walletAddress;

        if (isConnected && address) {
            walletBtnText.textContent = `✅ ${address}`;
        } else {
            walletBtnText.textContent = '🔷 Connect Wallet';
        }
    }

    // تحديث حالة المحفظة في صفحة المحفظة
    const walletStatusIndicator = document.getElementById('walletStatusIndicator');
    const walletStatusText = document.getElementById('walletStatusText');
    const mainConnectBtn = document.getElementById('mainConnectBtn');

    if (walletStatusIndicator && walletStatusText && mainConnectBtn) {
        const isConnected = window.APP_STATE.isWalletConnected;
        const address = window.APP_STATE.walletAddress;

        if (isConnected && address) {
            walletStatusIndicator.classList.add('connected');
            walletStatusText.textContent = `متصل: ${address}`;
            mainConnectBtn.textContent = '❌ قطع الاتصال';
            mainConnectBtn.classList.add('connected');
        } else {
            walletStatusIndicator.classList.remove('connected');
            walletStatusText.textContent = 'غير متصل';
            mainConnectBtn.textContent = '🔷 ربط المحفظة';
            mainConnectBtn.classList.remove('connected');
        }
    }

    // تحديث عنوان المحفظة في modal السحب
    const userWalletDisplay = document.getElementById('userWalletDisplay');
    if (userWalletDisplay) {
        if (window.APP_STATE.isWalletConnected) {
            userWalletDisplay.textContent = window.APP_STATE.walletAddress || 'غير متصل';
        } else {
            userWalletDisplay.textContent = 'غير متصل';
        }
    }
}

/**
 * Open settings modal
 */
function openSettings() {
    const userData = window.APP_STATE.userData;

    if (document.getElementById('settingsName'))
        document.getElementById('settingsName').textContent = userData?.firstname || userData?.username || '--';

    if (document.getElementById('settingsId'))
        document.getElementById('settingsId').textContent = window.APP_CONFIG.user_id || '--';

    if (document.getElementById('settingsDate'))
        document.getElementById('settingsDate').textContent = userData?.join_date || '--';

    const walletStatus = window.APP_STATE.isWalletConnected 
        ? `✅ متصل (${window.APP_STATE.walletAddress})`
        : '❌ غير متصل';

    if (document.getElementById('settingsWallet'))
        document.getElementById('settingsWallet').textContent = walletStatus;

    openModal('settingsModal');
}

/**
 * Initialize component loaders
 */
async function initializeComponents() {
    await loadComponent('headerContainer', 'components/header.html');
    await loadComponent('footerContainer', 'components/footer.html');
}

/**
 * Close modals on backdrop click
 */
function initializeModalHandlers() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    initializeModalHandlers();
    updateWalletUI();

    // تحديث الواجهة كل ثانية للحفاظ على التزامن
    setInterval(() => {
        updateWalletUI();
    }, 1000);
});

// ====================================
// 🌐 تصدير الدوال للاستخدام العام
// ====================================
window.showToast = showToast;
window.fmt = fmt;
window.fmtTime = fmtTime;
window.openModal = openModal;
window.closeModal = closeModal;
window.loadComponent = loadComponent;
window.updateTopBar = updateTopBar;
window.updateWalletUI = updateWalletUI;
window.openSettings = openSettings;

console.log('✅ Utils.js loaded and exported successfully');