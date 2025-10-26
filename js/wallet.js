/* ========================================
   wallet.js - نظام المحفظة الكامل
======================================== */

let currentWallet = null;
let universalLink = null;
const userId =window.APP_CONFIG.user_id
// قائمة المحافظ المدعومة
const SUPPORTED_WALLETS = [
    {
        name: 'Wallet On Telegram',
        appName: 'telegram-wallet',
        image: '💙',
        bridgeUrl: 'https://bridge.tonapi.io/bridge',
        universalUrl: 'https://t.me/wallet?attach=wallet',
        deepLink: 'tg://wallet'
    },
    {
        name: 'Tonkeeper',
        appName: 'tonkeeper',
        image: '💎',
        bridgeUrl: 'https://bridge.tonapi.io/bridge',
        universalUrl: 'https://app.tonkeeper.com/ton-connect',
        deepLink: 'tonkeeper://ton-connect'
    },
    {
        name: 'MyTonWallet',
        appName: 'mytonwallet',
        image: '🔵',
        bridgeUrl: 'https://bridge.mytonwallet.org/bridge',
        universalUrl: 'https://connect.mytonwallet.org',
        deepLink: 'mytonwallet://ton-connect'
    },
    {
        name: 'Tonhub',
        appName: 'tonhub',
        image: '💜',
        bridgeUrl: 'https://connect.tonhub.com/bridge',
        universalUrl: 'https://tonhub.com/ton-connect',
        deepLink: 'tonhub://ton-connect'
    }
];

// ====================================
// 🎨 فتح مودال اختيار المحفظة
// ====================================
function openWalletModal() {
    if (window.APP_STATE.isWalletConnected) {
        if (confirm('هل تريد قطع اتصال المحفظة؟')) {
            disconnectTonWallet();
        }
        return;
    }

    const modal = document.getElementById('walletSelectModal');
    if (!modal) {
        createWalletModal();
    }

    renderWalletOptions();
    openModal('walletSelectModal');
}

// استخدام هذه الدالة في الهيدر
function openWalletConnect() {
    openWalletModal();
}

// ====================================
// 🎨 إنشاء مودال المحافظ
// ====================================
function createWalletModal() {
    const modalHTML = `
        <div id="walletSelectModal" class="modal">
            <div class="modal-content wallet-modal">
                <div class="modal-header">
                    <div class="modal-title">Connect your wallet</div>
                    <button class="close-btn" onclick="closeModal('walletSelectModal')">×</button>
                </div>

                <div class="wallet-subtitle">
                    Open Wallet in Telegram or select your wallet to connect
                </div>

                <button class="btn-telegram-wallet" onclick="openTelegramWallet()">
                    💙 Open Wallet in Telegram
                </button>

                <div class="wallet-grid" id="walletGrid"></div>

                <div class="wallet-actions">
                    <button class="wallet-action-btn" onclick="showAllWallets()">
                        📱<br>View all
                    </button>
                    <button class="wallet-action-btn" onclick="showQRCode()">
                        📷<br>Open Link
                    </button>
                    <button class="wallet-action-btn" onclick="copyConnectionLink()">
                        📋<br>Copy Link
                    </button>
                </div>

                <div class="ton-connect-footer">
                    ⚡ TON Connect
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// ====================================
// 🎨 رسم خيارات المحافظ
// ====================================
function renderWalletOptions() {
    const grid = document.getElementById('walletGrid');
    if (!grid) return;

    const initialWallets = SUPPORTED_WALLETS.slice(0, 4);

    grid.innerHTML = initialWallets.map(wallet => `
        <div class="wallet-option-card" onclick="connectToWallet('${wallet.appName}')">
            <div class="wallet-icon">${wallet.image}</div>
            <div class="wallet-name">${wallet.name}</div>
        </div>
    `).join('');
}

// ====================================
// 📱 عرض جميع المحافظ
// ====================================
function showAllWallets() {
    const grid = document.getElementById('walletGrid');
    if (!grid) return;

    grid.innerHTML = SUPPORTED_WALLETS.map(wallet => `
        <div class="wallet-option-card" onclick="connectToWallet('${wallet.appName}')">
            <div class="wallet-icon">${wallet.image}</div>
            <div class="wallet-name">${wallet.name}</div>
        </div>
    `).join('');
}

// ====================================
// 🔗 إنشاء رابط TonConnect
// ====================================
async function generateUniversalLink(walletApp) {
    const wallet = SUPPORTED_WALLETS.find(w => w.appName === walletApp);
    if (!wallet) return null;

    const manifestUrl = window.APP_CONFIG.TONCONNECT_MANIFEST;
    const sessionId = generateSessionId();

    const request = {
        manifestUrl: manifestUrl,
        items: [{ name: 'ton_addr' }]
    };

    const encodedRequest = encodeURIComponent(JSON.stringify(request));
    const universalLink = `tc://?v=2&id=${sessionId}&r=${encodedRequest}`;

    window.CURRENT_CONNECT_LINK = universalLink;
    return universalLink;
}

// ====================================
// 🔐 الاتصال بمحفظة معينة
// ====================================
async function connectToWallet(walletApp) {
    try {
        showToast('🔄 جاري الاتصال...');

        const wallet = SUPPORTED_WALLETS.find(w => w.appName === walletApp);
        if (!wallet) {
            showToast('❌ محفظة غير مدعومة');
            return;
        }

        const link = await generateUniversalLink(walletApp);

        if (window.Telegram?.WebApp) {
            window.Telegram.WebApp.openLink(wallet.universalUrl + '?connect=' + encodeURIComponent(link));
        } else {
            window.location.href = wallet.deepLink + '?connect=' + encodeURIComponent(link);
            setTimeout(() => {
                window.open(wallet.universalUrl + '?connect=' + encodeURIComponent(link), '_blank');
            }, 1000);
        }

        waitForWalletResponse();

    } catch (error) {
        console.error('Connection error:', error);
        showToast('❌ فشل الاتصال');
    }
}

// ====================================
// 💙 فتح محفظة تليجرام
// ====================================
// async function openTelegramWallet() {
//     try {
//         showToast('🔄 جاري فتح المحفظة...');
//         const link = await generateUniversalLink('telegram-wallet');

//         if (window.Telegram?.WebApp) {
//             window.Telegram.WebApp.openLink('https://t.me/wallet?attach=wallet&startattach=' + encodeURIComponent(link));
//         } else {
//             window.open('https://t.me/wallet?attach=wallet&startattach=' + encodeURIComponent(link), '_blank');
//         }

//         waitForWalletResponse();

//     } catch (error) {
//         console.error('Error:', error);
//         showToast('❌ فشل فتح المحفظة');
//     }
// }
// ✅ فتح المحفظة داخل Telegram أو في المتصفح
// async function openTelegramWallet() {
//     try {
//         showToast('🔄 جاري فتح المحفظة...');

//         const link = await generateUniversalLink('telegram-wallet');

//         const telegramWalletUrl = 'https://t.me/wallet?attach=wallet&startattach=' + encodeURIComponent(link);

//         if (window.Telegram?.WebApp) {
//             // ✅ الفتح داخل WebApp Telegram نفسه
//             window.Telegram.WebApp.openLink(telegramWalletUrl);
//         } else {
//             // ✅ الفتح في المتصفح (لو المستخدم مش داخل Telegram)
//             window.open(telegramWalletUrl, '_blank');
//         }

//         waitForWalletResponse();

//     } catch (error) {
//         console.error('❌ Wallet open error:', error);
//         showToast('❌ فشل فتح المحفظة');
//     }
// }
// async function openTelegramWallet() {
//     try {
//         showToast('🔄 جاري فتح المحفظة...');

//         // يمكنك إضافة session id أو user id لو أردت تتبع المستخدم
//         const sessionId = generateSessionId();
//         const telegramWalletUrl = `https://t.me/wallet?attach=wallet&startattach=${sessionId}`;

//         if (window.Telegram?.WebApp) {
//             window.Telegram.WebApp.openLink(telegramWalletUrl);
//         } else {
//             window.open(telegramWalletUrl, '_blank');
//         }

//         // هنا تنتظر callback أو رسالة من المحفظة لإكمال الربط
//         waitForTelegramWalletAuthorization(sessionId);

//     } catch (error) {
//         console.error('❌ Wallet open error:', error);
//         showToast('❌ فشل فتح المحفظة');
//     }
// }

// async function openTelegramWallet(userId) {
    
//     const manifestUrl = encodeURIComponent('https://raw.githubusercontent.com/Ma7moudAF/gen-miner/master/manifest/tonconnect-manifest.json');
//     const request = encodeURIComponent(JSON.stringify({
//         manifestUrl: 'https://raw.githubusercontent.com/Ma7moudAF/gen-miner/master/manifest/tonconnect-manifest.json',
//         items: [{ name: 'ton_addr' }]
//     }));

//     const sessionId = Date.now(); // ممكن تستخدمه لتتبع المستخدم

//     const walletUrl = `https://walletbot.me/wv?tgWebAppUserId=${userId}&tgWebAppStartParam=tonconnect-v__2-id__${sessionId}-r__--${request}-ret__back`;

//   // يفتح الرابط داخل تيليجرام
//       if (window.Telegram?.WebApp) {
//         window.Telegram.WebApp.openLink(walletUrl);
//       } else {
//         window.open(walletUrl, '_blank');
//       }
// }

async function openTelegramWallet() {
    try {
        showToast('🔄 جاري فتح المحفظة...');

        // ✅ 1. تحديد manifest الخاص بتطبيقك
        const manifestUrl = 'https://raw.githubusercontent.com/Ma7moudAF/gen-miner/master/manifest/tonconnect-manifest.json';

        // ✅ 2. توليد session ID لتتبع المستخدم
        const sessionId = generateSessionId();

        // ✅ 3. إنشاء طلب TonConnect
        const request = {
            manifestUrl: manifestUrl,
            items: [{ name: 'ton_addr' }] // نطلب عنوان المحفظة فقط
        };

        // ✅ 4. ترميز الطلب داخل الرابط
        const encodedRequest = encodeURIComponent(JSON.stringify(request));

        // ✅ 5. إنشاء الرابط النهائي وفق بروتوكول TonConnect v2
        const tonconnectLink = `tonconnect-v__2-id__${sessionId}-r__${encodedRequest}-ret__back`;

        // ✅ 6. بناء رابط Telegram Wallet
        // const telegramWalletUrl = `https://t.me/wallet?attach=wallet&startattach=${tonconnectLink}`;
        const telegramWalletUrl = `https://walletbot.me/wv?tgWebAppUserId=5243908686&tgWebAppStartParam=tonconnect-v__2-id__${sessionId}-r__--7B--22manifestUrl--22--3A--22https--3A--2F--2Fraw--2Egithubusercontent--2Ecom--2FMa7moudAF--2Fgen--2Dminer--2Fmaster--2Fmanifest--2Ftonconnect--2Dmanifest--2Ejson--22--2C--22items--22--3A--5B--7B--22name--22--3A--22ton--5Faddr--22--7D--5D--7D-ret__back`;

        // ✅ 7. فتح المحفظة سواء من داخل تيليجرام أو خارجها
        if (window.Telegram?.WebApp) {
            window.Telegram.WebApp.openLink(telegramWalletUrl);
        } else {
            window.open(telegramWalletUrl, '_blank');
        }

        // ✅ 8. انتظار رد المحفظة لتأكيد الاتصال
        waitForTelegramWalletAuthorization(sessionId);

    } catch (error) {
        console.error('❌ Wallet open error:', error);
        showToast('❌ فشل فتح المحفظة');
    }
}

// هذه للهيدر القديم
function connectTelegramWallet() {
    openTelegramWallet();
}

// للتوافق مع الهيدر القديم
function connectWallet(walletName) {
    const walletMap = {
        'Tonkeeper': 'tonkeeper',
        'TonHub': 'tonhub',
        'MyTonWallet': 'mytonwallet'
    };
    
    const appName = walletMap[walletName] || walletName.toLowerCase();
    connectToWallet(appName);
}

// ====================================
// 📋 نسخ رابط الاتصال
// ====================================
async function copyConnectionLink() {
    try {
        let link = window.CURRENT_CONNECT_LINK;

        if (!link) {
            link = await generateUniversalLink('tonkeeper');
        }

        if (navigator.clipboard) {
            await navigator.clipboard.writeText(link);
            showToast('✅ تم نسخ الرابط');
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = link;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('✅ تم نسخ الرابط');
        }
    } catch (error) {
        console.error('Copy error:', error);
        showToast('❌ فشل نسخ الرابط');
    }
}

// ====================================
// 📷 عرض QR Code
// ====================================
async function showQRCode() {
    try {
        let link = window.CURRENT_CONNECT_LINK;
        if (!link) {
            link = await generateUniversalLink('tonkeeper');
        }
        window.open(link, '_blank');
        showToast('🔗 تم فتح الرابط');
    } catch (error) {
        console.error('QR error:', error);
        showToast('❌ فشل فتح الرابط');
    }
}

// ====================================
// ⏳ انتظار استجابة المحفظة
// ====================================
function waitForWalletResponse() {
    let attempts = 0;
    const maxAttempts = 60;

    const interval = setInterval(() => {
        attempts++;

        checkWalletConnection().then(connected => {
            if (connected) {
                clearInterval(interval);
                handleWalletConnected(connected);
            }
        });

        if (attempts >= maxAttempts) {
            clearInterval(interval);
            showToast('⏱️ انتهت مهلة الاتصال');
        }
    }, 1000);
}

// ====================================
// ✅ التحقق من الاتصال
// ====================================
async function checkWalletConnection() {
    try {
        const savedWallet = localStorage.getItem('ton_wallet_data');
        if (savedWallet) {
            return JSON.parse(savedWallet);
        }
        return null;
    } catch (error) {
        return null;
    }
}

// ====================================
// ✅ معالجة الاتصال الناجح
// ====================================
function handleWalletConnected(wallet) {
    currentWallet = wallet;
    const address = wallet.address || wallet.account?.address;
    const friendlyAddress = formatTonAddress(address);

    window.APP_STATE.isWalletConnected = true;
    window.APP_STATE.walletAddress = friendlyAddress;

    localStorage.setItem('ton_wallet_connected', 'true');
    localStorage.setItem('ton_wallet_address', friendlyAddress);
    localStorage.setItem('ton_wallet_data', JSON.stringify(wallet));

    updateWalletUI();
    closeModal('walletSelectModal');
    showToast('✅ تم ربط المحفظة بنجاح');

    saveWalletAddress(friendlyAddress);
}

// ====================================
// 🔀 قطع اتصال المحفظة
// ====================================
function disconnectTonWallet() {
    currentWallet = null;
    window.APP_STATE.isWalletConnected = false;
    window.APP_STATE.walletAddress = null;

    localStorage.removeItem('ton_wallet_connected');
    localStorage.removeItem('ton_wallet_address');
    localStorage.removeItem('ton_wallet_data');

    updateWalletUI();
    showToast('🔌 تم قطع اتصال المحفظة');
}

function disconnectWallet() {
    disconnectTonWallet();
}

// ====================================
// 💰 إيداع USDT
// ====================================
async function depositUSDT() {
    if (!currentWallet) {
        showToast('⚠️ يجب ربط المحفظة أولاً');
        openWalletModal();
        return;
    }

    const amountInput = document.getElementById('depositAmount');
    const amount = parseFloat(amountInput?.value || 0);

    if (amount <= 0 || amount < 1) {
        showToast('⚠️ الحد الأدنى للإيداع 1 USDT');
        return;
    }

    try {
        showToast('🔄 جاري معالجة الإيداع...');

        const result = await recordDeposit(
            amount,
            'USDT',
            'manual_' + Date.now(),
            currentWallet.address || currentWallet.account?.address
        );

        if (result.success) {
            showToast('✅ تم تسجيل الإيداع! سيتم مراجعته خلال دقائق');
            amountInput.value = '';
            closeModal('depositModal');

            setTimeout(async () => {
                await loadUser();
                updateWalletDisplay();
            }, 3000);
        } else {
            showToast(`❌ ${result.message || 'فشل التسجيل'}`);
        }
    } catch (error) {
        console.error('Deposit error:', error);
        showToast('❌ فشل الإيداع');
    }
}

// ====================================
// 💸 سحب USDT
// ====================================
async function withdrawUSDT() {
    if (!currentWallet) {
        showToast('⚠️ يجب ربط المحفظة أولاً');
        return;
    }

    const amountInput = document.getElementById('withdrawAmount');
    const amount = parseFloat(amountInput?.value || 0);
    const userData = window.APP_STATE.userData;

    if (amount <= 0) {
        showToast('⚠️ أدخل مبلغ صحيح');
        return;
    }

    if (amount > userData.balance) {
        showToast('⚠️ الرصيد غير كافٍ');
        return;
    }

    if (amount < 5) {
        showToast('⚠️ الحد الأدنى للسحب 5 USDT');
        return;
    }

    try {
        showToast('🔄 جاري إرسال طلب السحب...');

        const result = await requestWithdrawUSDT(
            amount,
            'USDT',
            currentWallet.address || currentWallet.account?.address
        );

        if (result.success) {
            showToast('✅ تم إرسال طلب السحب');
            amountInput.value = '';
            closeModal('withdrawModal');
            await loadUser();
            updateWalletDisplay();
        } else {
            showToast(`❌ ${result.message || 'فشل الطلب'}`);
        }
    } catch (error) {
        console.error('Withdraw error:', error);
        showToast('❌ حدث خطأ');
    }
}

// ====================================
// 🔧 دوال مساعدة
// ====================================
function generateSessionId() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
}

function formatTonAddress(address) {
    if (!address) return '';
    const start = address.slice(0, 4);
    const end = address.slice(-4);
    return `${start}...${end}`;
}

// ====================================
// 💬 نظام الدعم الفني
// ====================================
let supportMessages = [];

async function loadSupportChat() {
    try {
        const result = await getSupportMessages();
        if (result.success) {
            supportMessages = result.messages || [];
            renderSupportMessages();
        }
    } catch (error) {
        console.error('Failed to load support messages:', error);
    }
}

function renderSupportMessages() {
    const container = document.getElementById('supportMessages');
    if (!container) return;

    if (supportMessages.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:20px;">لا توجد رسائل</p>';
        return;
    }

    container.innerHTML = supportMessages.map(msg => `
        <div class="support-message ${msg.is_admin ? 'admin' : 'user'}">
            <div class="message-header">
                <span class="message-sender">${msg.is_admin ? '👨‍💼 الدعم' : '👤 أنت'}</span>
                <span class="message-time">${formatTime(msg.created_at)}</span>
            </div>
            <div class="message-body">${escapeHtml(msg.message)}</div>
        </div>
    `).join('');

    container.scrollTop = container.scrollHeight;
}

async function sendSupportMessageFunc() {
    const input = document.getElementById('supportMessageInput');
    const message = input?.value?.trim();

    if (!message) {
        showToast('⚠️ اكتب رسالتك أولاً');
        return;
    }

    try {
        const result = await sendSupportMessage(message);

        if (result.success) {
            input.value = '';
            supportMessages.push({
                message: message,
                is_admin: 0,
                created_at: new Date().toISOString()
            });
            renderSupportMessages();
            showToast('✅ تم إرسال رسالتك');
        } else {
            showToast('❌ فشل إرسال الرسالة');
        }
    } catch (error) {
        console.error('Send message error:', error);
        showToast('❌ حدث خطأ');
    }
}

function openSupportChat() {
    openModal('supportModal');
    loadSupportChat();

    const interval = setInterval(() => {
        const modal = document.getElementById('supportModal');
        if (!modal || !modal.classList.contains('active')) {
            clearInterval(interval);
            return;
        }
        loadSupportChat();
    }, 10000);
}

function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return 'الآن';
    if (diff < 3600000) return `${Math.floor(diff/60000)} دقيقة`;
    if (diff < 86400000) return `${Math.floor(diff/3600000)} ساعة`;
    return date.toLocaleDateString('ar');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openDepositModal() {
    if (!window.APP_STATE.isWalletConnected) {
        showToast('⚠️ يجب ربط المحفظة أولاً');
        openWalletModal();
        return;
    }
    openModal('depositModal');
}

function openWithdrawModal() {
    if (!window.APP_STATE.isWalletConnected) {
        showToast('⚠️ يجب ربط المحفظة أولاً');
        openWalletModal();
        return;
    }
    openModal('withdrawModal');
}

// ====================================
// 📊 تحديث عرض المحفظة
// ====================================
function updateWalletDisplay() {
    const userData = window.APP_STATE.userData;
    if (!userData) return;

    const usdtBalance = parseFloat(userData.balance || 0);
    const genBalance = parseFloat(userData.gen_balance || 0);

    if (document.getElementById('walletBalance'))
        document.getElementById('walletBalance').textContent = usdtBalance.toFixed(2);

    if (document.getElementById('usdtBalance'))
        document.getElementById('usdtBalance').textContent = usdtBalance.toFixed(2);

    if (document.getElementById('walletGen'))
        document.getElementById('walletGen').textContent = fmt(genBalance);

    if (document.getElementById('genBalance'))
        document.getElementById('genBalance').textContent = fmt(genBalance);

    const refBalance = parseFloat(userData.referral_balance || 0);
    const maxWithdraw = refBalance * 2;

    if (document.getElementById('refBalanceInfo'))
        document.getElementById('refBalanceInfo').textContent = refBalance.toFixed(2);

    if (document.getElementById('maxWithdrawInfo'))
        document.getElementById('maxWithdrawInfo').textContent = maxWithdraw.toFixed(2);

    if (document.getElementById('availableBalanceDisplay'))
        document.getElementById('availableBalanceDisplay').textContent = usdtBalance.toFixed(2);
}

// ====================================
// 🚀 التهيئة وتصدير الدوال
// ====================================

// تصدير جميع الدوال للاستخدام العام
window.openWalletModal = openWalletModal;
window.openWalletConnect = openWalletConnect;
window.connectToWallet = connectToWallet;
window.openTelegramWallet = openTelegramWallet;
window.connectTelegramWallet = connectTelegramWallet;
window.connectWallet = connectWallet;
window.showAllWallets = showAllWallets;
window.copyConnectionLink = copyConnectionLink;
window.showQRCode = showQRCode;
window.disconnectTonWallet = disconnectTonWallet;
window.disconnectWallet = disconnectWallet;
window.depositUSDT = depositUSDT;
window.withdrawUSDT = withdrawUSDT;
window.openSupportChat = openSupportChat;
window.sendSupportMessageFunc = sendSupportMessageFunc;
window.openDepositModal = openDepositModal;
window.openWithdrawModal = openWithdrawModal;
window.updateWalletDisplay = updateWalletDisplay;

// التهيئة عند تحميل صفحة المحفظة
if (document.body.dataset.page === 'wallet') {
    document.addEventListener('DOMContentLoaded', async () => {
        await loadUser();
        updateWalletDisplay();

        const savedWallet = localStorage.getItem('ton_wallet_data');
        if (savedWallet) {
            try {
                currentWallet = JSON.parse(savedWallet);
                window.APP_STATE.isWalletConnected = true;
                window.APP_STATE.walletAddress = localStorage.getItem('ton_wallet_address');
                updateWalletUI();
            } catch (e) {
                console.error('Failed to restore wallet:', e);
            }
        }
    });
}

console.log('✅ Wallet.js loaded successfully');