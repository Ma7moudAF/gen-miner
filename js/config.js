/* ========================================
   Configuration - الإعدادات العامة
======================================== */

// Telegram Web App
const tg = window.Telegram?.WebApp || null;

// API Configuration
const API_ENDPOINT = 'api.php';
const BOT_USERNAME = 'freesusdtbot'; // من ملف config.php
const manifestUrl = 'https://2a0eab44-d9ea-4fa0-a63d-feea08499c67-00-3rnj0g8hx1yrw.janeway.replit.dev/tonconnect-manifest.json';

// Global Variables
let user_id = null;
let userData = null;
let isWalletConnected = false;
let walletAddress = null;
let miningInterval = null;

// Constants
const GEN_PRICE = 0.000001; // سعر GEN بالـ USDT

// Initialize Telegram WebApp
if (tg && tg.initDataUnsafe?.user) {
    tg.expand();
    user_id = tg.initDataUnsafe.user.id;

    // حفظ بيانات المستخدم من Telegram
    userData = {
        telegram_id: tg.initDataUnsafe.user.id,
        firstname: tg.initDataUnsafe.user.first_name || '',
        username: tg.initDataUnsafe.user.username || '',
        photo_url: tg.initDataUnsafe.user.photo_url || ''
    };
} else {
    // For testing - يجب حذف هذا في الإنتاج
    user_id = 5243908686;
    userData = {
        telegram_id: 5243908686,
        firstname: 'Test User',
        username: 'testuser',
        photo_url: ''
    };
}

// Check saved wallet connection
const savedWallet = localStorage.getItem('ton_wallet_connected');
if (savedWallet === 'true') {
    isWalletConnected = true;
    walletAddress = localStorage.getItem('ton_wallet_address');
}

// Export for use in other files
window.APP_CONFIG = {
    tg,
    API_ENDPOINT,
    BOT_USERNAME,
    user_id,
    GEN_PRICE,
    TONCONNECT_MANIFEST: manifestUrl,
    userData // إضافة بيانات المستخدم
};

window.APP_STATE = {
    userData: null, // سيتم تحديثها من loadUser()
    isWalletConnected,
    walletAddress,
    miningInterval
};

// Auto-register on load
document.addEventListener('DOMContentLoaded', async () => {
    if (user_id) {
        try {
            // محاولة تسجيل المستخدم تلقائياً
            const urlParams = new URLSearchParams(window.location.search);
            const referrerId = urlParams.get('start') || null;

            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register',
                    telegram_id: user_id,
                    firstname: userData.firstname,
                    username: userData.username,
                    photo_url: userData.photo_url,
                    referrer_id: referrerId
                })
            });

            const result = await response.json();
            console.log('Auto-register result:', result);
        } catch (error) {
            console.error('Auto-register failed:', error);
        }
    }
});