/* ========================================
   API Functions - دوال API محدثة
======================================== */

/**
 * Make API request
 */
async function apiRequest(action, data = {}) {
    try {
        const response = await fetch(window.APP_CONFIG.API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action,
                telegram_id: window.APP_CONFIG.user_id,
                ...data
            })
        });

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * Load user data
 */
async function loadUser() {
    try {
        const data = await apiRequest('getUser');

        if (data.success) {
            window.APP_STATE.userData = data.user;
            window.APP_CONFIG.BOT_USERNAME = data.bot_username || window.APP_CONFIG.BOT_USERNAME;
            updateTopBar(data.user);
            return data.user;
        } else {
            showToast('⚠️ فشل تحميل البيانات');
            return null;
        }
    } catch (error) {
        console.error('Load User Error:', error);
        showToast('⚠️ خطأ في الاتصال');
        return null;
    }
}

/**
 * Start mining
 */
async function startMining() {
    try {
        const data = await apiRequest('startMining');

        if (data.success) {
            window.APP_STATE.userData.mining_start_time = Math.floor(Date.now() / 1000);
            return true;
        }
        return false;
    } catch (error) {
        console.error('Start Mining Error:', error);
        return false;
    }
}

/**
 * Collect mining rewards
 */
async function collectMining() {
    try {
        const data = await apiRequest('collectmining');
        return data;
    } catch (error) {
        console.error('Collect Mining Error:', error);
        return { success: false };
    }
}

/**
 * Start task
 */
async function startTask(taskId) {
    try {
        const data = await apiRequest('startTask', { task_id: taskId });
        return data;
    } catch (error) {
        console.error('Start Task Error:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * Verify task
 */
async function verifyTask(taskId, password = null) {
    try {
        const params = { task_id: taskId };
        if (password) params.password = password;

        const data = await apiRequest('verifyTask', params);
        return data;
    } catch (error) {
        console.error('Verify Task Error:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * Get task status
 */
async function getTaskStatus(taskId) {
    try {
        const data = await apiRequest('getTaskStatus', { task_id: taskId });
        return data;
    } catch (error) {
        console.error('Get Task Status Error:', error);
        return { success: false, status: 'not_started' };
    }
}

/**
 * Get tasks
 */
async function getTasks() {
    try {
        const data = await apiRequest('getTasks', {});
        return data.success ? data.tasks : [];
    } catch (error) {
        console.error('Get Tasks Error:', error);
        return [];
    }
}

/**
 * Claim task reward
 */
async function claimTask(taskId) {
    try {
        const data = await apiRequest('claimTask', { task_id: taskId });
        return data;
    } catch (error) {
        console.error('Claim Task Error:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * Get referrals by level
 */
async function getReferrals(level) {
    try {
        const data = await apiRequest('getReferrals', { level });
        return data.success ? data.referrals : [];
    } catch (error) {
        console.error('Get Referrals Error:', error);
        return [];
    }
}

/**
 * Convert GEN to USDT
 */
async function convertGen(amount) {
    try {
        const data = await apiRequest('convert_gen', { amount });
        return data;
    } catch (error) {
        console.error('Convert GEN Error:', error);
        return { success: false };
    }
}

/**
 * Request withdrawal
 */
async function requestWithdraw(amount) {
    try {
        const data = await apiRequest('request_withdraw', { amount });
        return data;
    } catch (error) {
        console.error('Request Withdraw Error:', error);
        return { success: false };
    }
}

/**
 * Buy upgrade
 */
async function buyUpgrade(type, value, cost) {
    try {
        const data = await apiRequest('buy_upgrade', { type, value, cost });
        return data;
    } catch (error) {
        console.error('Buy Upgrade Error:', error);
        return { success: false };
    }
}

/**
 * Record deposit - للمحفظة
 */
async function recordDeposit(amount, currency, hash, walletAddress) {
    try {
        const data = await apiRequest('recordDeposit', {
            amount,
            currency,
            transaction_hash: hash,
            wallet_address: walletAddress
        });
        return data;
    } catch (error) {
        console.error('Record Deposit Error:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * Request withdraw - للمحفظة
 */
async function requestWithdrawUSDT(amount, currency, walletAddress) {
    try {
        const data = await apiRequest('requestWithdraw', {
            amount,
            currency,
            wallet_address: walletAddress
        });
        return data;
    } catch (error) {
        console.error('Request Withdraw Error:', error);
        return { success: false, message: 'Network error' };
    }
}

/**
 * Save wallet address
 */
async function saveWalletAddress(address, type = 'TON') {
    try {
        const data = await apiRequest('saveWalletAddress', {
            wallet_address: address,
            wallet_type: type
        });
        return data;
    } catch (error) {
        console.error('Save Wallet Error:', error);
        return { success: false };
    }
}

/**
 * Get transaction history
 */
async function getTransactionHistory(limit = 20) {
    try {
        const data = await apiRequest('getTransactionHistory', { limit });
        return data;
    } catch (error) {
        console.error('Get Transactions Error:', error);
        return { success: false, transactions: [] };
    }
}

// تصدير الدوال للاستخدام العام
window.getTransactionHistory = getTransactionHistory;

/**
 * Send support message
 */
async function sendSupportMessage(message) {
    try {
        const data = await apiRequest('sendSupportMessage', { message });
        return data;
    } catch (error) {
        console.error('Send Support Message Error:', error);
        return { success: false };
    }
}

/**
 * Get support messages
 */
async function getSupportMessages() {
    try {
        const data = await apiRequest('getSupportMessages');
        return data;
    } catch (error) {
        console.error('Get Support Messages Error:', error);
        return { success: false, messages: [] };
    }
}

// ====================================
// 🌐 تصدير جميع الدوال للاستخدام العام
// ====================================
window.apiRequest = apiRequest;
window.loadUser = loadUser;
window.startMining = startMining;
window.collectMining = collectMining;
window.startTask = startTask;
window.verifyTask = verifyTask;
window.getTaskStatus = getTaskStatus;
window.getTasks = getTasks;
window.claimTask = claimTask;
window.getReferrals = getReferrals;
window.convertGen = convertGen;
window.requestWithdraw = requestWithdraw;
window.buyUpgrade = buyUpgrade;
window.recordDeposit = recordDeposit;
window.requestWithdrawUSDT = requestWithdrawUSDT;
window.saveWalletAddress = saveWalletAddress;
window.getTransactionHistory = getTransactionHistory;
window.sendSupportMessage = sendSupportMessage;
window.getSupportMessages = getSupportMessages;

console.log('✅ API.js loaded and exported successfully');
