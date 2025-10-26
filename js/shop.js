/* ========================================
   Shop Page Logic - منطق صفحة المتجر
======================================== */

/**
 * تحميل قائمة الأجهزة المتاحة
 */
async function loadShopRigs() {
    const container = document.getElementById('shopRigsList');
    if (!container) return;

    container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const data = await apiRequest('getRigs');

        if (!data.success || !data.rigs || data.rigs.length === 0) {
            container.innerHTML = '<p style="text-align:center;color:var(--text-muted);">No devices available</p>';
            return;
        }

        let html = '';

        data.rigs.forEach(rig => {
            const roi = ((rig.daily_profit * rig.duration_days) / rig.price * 100).toFixed(0);
            const dailyROI = (rig.daily_profit / rig.price * 100).toFixed(2);

            html += `
                <div class="rig-card" data-rig-id="${rig.id}">
                    <div class="rig-icon">${rig.icon}</div>

                    <div class="rig-header">
                        <div class="rig-name">${rig.name_ar}</div>
                        <div class="rig-price">$${fmt(rig.price)}</div>
                    </div>

                    <div class="rig-desc">${rig.description || ''}</div>

                    <div class="rig-stats">
                        <div class="rig-stat">
                            <span class="stat-label">💰 Daily Profit</span>
                            <span class="stat-value">$${fmt(rig.daily_profit)}</span>
                        </div>

                        <div class="rig-stat">
                            <span class="stat-label">📅 Duration</span>
                            <span class="stat-value">${rig.duration_days} Days</span>
                        </div>

                        <div class="rig-stat">
                            <span class="stat-label">📈 Total ROI</span>
                            <span class="stat-value highlight">${roi}%</span>
                        </div>

                        <div class="rig-stat">
                            <span class="stat-label">⚡ Daily ROI</span>
                            <span class="stat-value">${dailyROI}%</span>
                        </div>
                    </div>

                    <button class="btn-purchase" onclick="handlePurchaseRig(${rig.id}, ${rig.price})">
                        🛒 Buy Now
                    </button>
                </div>
            `;
        });

        container.innerHTML = html;

    } catch (error) {
        console.error('Load Rigs Error:', error);
        container.innerHTML = '<p style="text-align:center;color:var(--danger);">⚠️ Failed to load devices</p>';
    }
}

/**
 * شراء جهاز تعدين
 */
async function handlePurchaseRig(rigId, price) {
    const userData = window.APP_STATE.userData;

    if (!userData) {
        showToast('⚠️ Please login first');
        return;
    }

    // التحقق من الرصيد
    if ((userData.balance || 0) < price) {
        showToast(`⚠️ Insufficient balance. You need $${fmt(price)}`);
        return;
    }

    // تأكيد الشراء
    if (!confirm(`Are you sure you want to buy this device for $${fmt(price)}?`)) {
        return;
    }

    showToast('🔄 Processing purchase...');

    try {
        const result = await apiRequest('purchaseRig', { rig_id: rigId });

        if (result.success) {
            showToast('🎉 ' + result.message);

            // تحديث بيانات المستخدم
            await loadUser();

            // الانتقال لصفحة أجهزتي
            setTimeout(() => {
                window.location.href = 'my-rigs.html';
            }, 1500);
        } else {
            showToast('⚠️ ' + (result.message || 'Purchase failed'));
        }

    } catch (error) {
        console.error('Purchase Error:', error);
        showToast('❌ Connection error');
    }
}

/**
 * تهيئة صفحة المتجر
 */
async function initShopPage() {
    await loadUser();
    await loadShopRigs();
}

// Initialize on DOM load
if (document.body.dataset.page === 'shop') {
    document.addEventListener('DOMContentLoaded', initShopPage);
}