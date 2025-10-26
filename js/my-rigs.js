/* ========================================
   My Rigs Page Logic - منطق صفحة أجهزتي
======================================== */

let pendingEarnings = { total: 0, rigs: [] };
let earningsTimer = null;

/**
 * تحميل أجهزة المستخدم
 */
async function loadUserRigs() {
    const container = document.getElementById('myRigsList');
    if (!container) return;

    container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const data = await apiRequest('getUserRigs');

        if (!data.success || !data.rigs || data.rigs.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p>You don't have any mining devices yet</p>
                    <button class="btn btn-primary" onclick="window.location.href='shop.html'">
                        🛒 Go to Shop
                    </button>
                </div>
            `;
            return;
        }

        let html = '';

        data.rigs.forEach(rig => {
            const status = rig.status;
            const isActive = status === 'active';
            const isExpired = status === 'expired';

            // حساب النسبة المتبقية
            const totalDays = rig.duration_days;
            const daysLeft = Math.max(0, parseInt(rig.days_left || 0));
            const progressPercent = isActive ? ((totalDays - daysLeft) / totalDays * 100) : 100;

            // إيجاد الأرباح المعلقة لهذا الجهاز
            const rigEarnings = pendingEarnings.rigs.find(r => r.user_rig_id === rig.id);
            const pendingAmount = rigEarnings ? rigEarnings.pending : 0;

            html += `
                <div class="user-rig-card ${isActive ? 'active' : isExpired ? 'expired' : 'inactive'}">
                    <div class="rig-status-badge ${status}">${getStatusText(status)}</div>

                    <div class="rig-header">
                        <div class="rig-icon">${rig.icon}</div>
                        <div class="rig-info">
                            <div class="rig-name">${rig.name_ar}</div>
                            <div class="rig-meta">
                                📅 Purchased: ${formatDate(rig.purchase_date)}
                            </div>
                        </div>
                    </div>

                    ${isActive ? `
                        <div class="rig-progress">
                            <div class="progress-info">
                                <span>⏳ ${daysLeft} days left</span>
                                <span>${Math.floor(progressPercent)}%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: ${progressPercent}%"></div>
                            </div>
                        </div>
                    ` : ''}

                    <div class="rig-earnings">
                        <div class="earnings-item">
                            <span class="label">💰 Daily Profit</span>
                            <span class="value">$${fmt(rig.daily_profit)}</span>
                        </div>

                        <div class="earnings-item">
                            <span class="label">📊 Total Earned</span>
                            <span class="value highlight">$${fmt(rig.total_earned)}</span>
                        </div>

                        ${isActive ? `
                            <div class="earnings-item pending">
                                <span class="label">⏱️ Pending</span>
                                <span class="value" data-rig-pending="${rig.id}">$${fmt(pendingAmount)}</span>
                            </div>
                        ` : ''}
                    </div>

                    <div class="rig-actions">
                        ${isExpired ? `
                            <button class="btn-action renew" onclick="handleRenewRig(${rig.id}, ${rig.price})">
                                🔄 Renew ($${fmt(rig.price)})
                            </button>
                        ` : isActive ? `
                            <button class="btn-action upgrade" onclick="showUpgradeModal(${rig.id}, ${rig.rig_id})">
                                ⬆️ Upgrade
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

    } catch (error) {
        console.error('Load User Rigs Error:', error);
        container.innerHTML = '<p style="text-align:center;color:var(--danger);">⚠️ Failed to load devices</p>';
    }
}

/**
 * تحميل الأرباح المعلقة
 */
async function loadPendingEarnings() {
    try {
        const data = await apiRequest('calculatePendingEarnings');

        if (data.success && data.earnings) {
            pendingEarnings = data.earnings;

            // تحديث العرض
            updatePendingDisplay();
        }

    } catch (error) {
        console.error('Load Pending Error:', error);
    }
}

/**
 * تحديث عرض الأرباح المعلقة
 */
function updatePendingDisplay() {
    // تحديث الإجمالي
    const totalElement = document.getElementById('totalPending');
    if (totalElement) {
        totalElement.textContent = fmt(pendingEarnings.total);
    }

    // تحديث كل جهاز
    pendingEarnings.rigs.forEach(rig => {
        const element = document.querySelector(`[data-rig-pending="${rig.user_rig_id}"]`);
        if (element) {
            element.textContent = `$${fmt(rig.pending)}`;
        }
    });
}

/**
 * استلام الأرباح
 */
async function handleClaimEarnings() {
    if (!pendingEarnings.total || pendingEarnings.total <= 0) {
        showToast('⚠️ No earnings to claim');
        return;
    }

    const btn = document.getElementById('claimBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Claiming...';
    }

    try {
        const result = await apiRequest('claimMiningEarnings');

        if (result.success) {
            showToast('🎉 ' + result.message);

            // تحديث البيانات
            if (result.user) {
                window.APP_STATE.userData = result.user;
                updateTopBar(result.user);
            }

            // إعادة تحميل كل شيء
            await loadUserRigs();
            await loadPendingEarnings();
            await loadMiningStats();

        } else {
            showToast('⚠️ ' + (result.message || 'Failed to claim'));
        }

    } catch (error) {
        console.error('Claim Error:', error);
        showToast('❌ Connection error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '💰 Claim Earnings';
        }
    }
}

/**
 * تجديد جهاز منتهي
 */
async function handleRenewRig(userRigId, price) {
    const userData = window.APP_STATE.userData;

    if (!userData || (userData.balance || 0) < price) {
        showToast(`⚠️ Insufficient balance. Need $${fmt(price)}`);
        return;
    }

    if (!confirm(`Renew this device for $${fmt(price)}?`)) {
        return;
    }

    showToast('🔄 Processing...');

    try {
        const result = await apiRequest('renewRig', { user_rig_id: userRigId });

        if (result.success) {
            showToast('🎉 ' + result.message);
            await loadUser();
            await loadUserRigs();
            await loadMiningStats();
        } else {
            showToast('⚠️ ' + (result.message || 'Renewal failed'));
        }

    } catch (error) {
        console.error('Renew Error:', error);
        showToast('❌ Connection error');
    }
}

/**
 * عرض نافذة الترقية
 */
async function showUpgradeModal(userRigId, currentRigId) {
    // جلب الأجهزة المتاحة
    const data = await apiRequest('getRigs');

    if (!data.success || !data.rigs) {
        showToast('⚠️ Failed to load upgrade options');
        return;
    }

    // تصفية الأجهزة الأعلى سعراً
    const currentRig = data.rigs.find(r => r.id === currentRigId);
    const upgradeOptions = data.rigs.filter(r => r.price > currentRig.price);

    if (upgradeOptions.length === 0) {
        showToast('ℹ️ You already have the best device!');
        return;
    }

    // بناء محتوى النافذة
    let html = '<div style="padding: 20px;">';
    html += '<h3 style="margin-bottom: 20px;">⬆️ Upgrade Device</h3>';

    upgradeOptions.forEach(rig => {
        const priceDiff = rig.price - currentRig.price;

        html += `
            <div class="upgrade-option" style="margin-bottom: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 700;">${rig.icon} ${rig.name_ar}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            💰 $${fmt(rig.daily_profit)}/day
                        </div>
                    </div>
                    <button class="btn-action" onclick="confirmUpgrade(${userRigId}, ${rig.id}, ${priceDiff})" style="padding: 8px 16px;">
                        Upgrade (+$${fmt(priceDiff)})
                    </button>
                </div>
            </div>
        `;
    });

    html += '</div>';

    // عرض في modal
    const modal = document.getElementById('upgradeModal');
    if (modal) {
        modal.querySelector('.modal-body').innerHTML = html;
        openModal('upgradeModal');
    }
}

/**
 * تأكيد الترقية
 */
async function confirmUpgrade(userRigId, newRigId, priceDiff) {
    const userData = window.APP_STATE.userData;

    if (!userData || (userData.balance || 0) < priceDiff) {
        showToast(`⚠️ Insufficient balance. Need $${fmt(priceDiff)}`);
        return;
    }

    closeModal('upgradeModal');

    if (!confirm(`Pay $${fmt(priceDiff)} to upgrade this device?`)) {
        return;
    }

    showToast('🔄 Processing upgrade...');

    try {
        const result = await apiRequest('upgradeRig', {
            user_rig_id: userRigId,
            new_rig_id: newRigId
        });

        if (result.success) {
            showToast('🎉 ' + result.message);
            await loadUser();
            await loadUserRigs();
            await loadMiningStats();
        } else {
            showToast('⚠️ ' + (result.message || 'Upgrade failed'));
        }

    } catch (error) {
        console.error('Upgrade Error:', error);
        showToast('❌ Connection error');
    }
}

/**
 * تحميل إحصائيات التعدين
 */
async function loadMiningStats() {
    try {
        const data = await apiRequest('getMiningStats');

        if (data.success && data.stats) {
            const stats = data.stats;

            if (document.getElementById('totalInvested'))
                document.getElementById('totalInvested').textContent = fmt(stats.total_invested);

            if (document.getElementById('totalEarned'))
                document.getElementById('totalEarned').textContent = fmt(stats.total_earned);

            if (document.getElementById('dailyProfit'))
                document.getElementById('dailyProfit').textContent = fmt(stats.daily_profit);

            if (document.getElementById('activeRigs'))
                document.getElementById('activeRigs').textContent = stats.active_rigs;

            if (document.getElementById('roiPercent'))
                document.getElementById('roiPercent').textContent = stats.roi_percentage + '%';

            if (document.getElementById('totalPending'))
                document.getElementById('totalPending').textContent = fmt(stats.pending_earnings);
        }

    } catch (error) {
        console.error('Load Stats Error:', error);
    }
}

/**
 * بدء تحديث تلقائي للأرباح
 */
function startEarningsTimer() {
    if (earningsTimer) {
        clearInterval(earningsTimer);
    }

    // تحديث كل 10 ثواني
    earningsTimer = setInterval(() => {
        loadPendingEarnings();
    }, 10000);
}

/**
 * إيقاف التحديث التلقائي
 */
function stopEarningsTimer() {
    if (earningsTimer) {
        clearInterval(earningsTimer);
        earningsTimer = null;
    }
}

/**
 * تنسيق حالة الجهاز
 */
function getStatusText(status) {
    const statusMap = {
        'active': '✅ Active',
        'expired': '⏰ Expired',
        'inactive': '⏸️ Inactive'
    };
    return statusMap[status] || status;
}

/**
 * تنسيق التاريخ
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

/**
 * تهيئة صفحة أجهزتي
 */
async function initMyRigsPage() {
    await loadUser();
    await loadPendingEarnings();
    await loadMiningStats();
    await loadUserRigs();

    // بدء التحديث التلقائي
    startEarningsTimer();
}

// Initialize on DOM load
if (document.body.dataset.page === 'my-rigs') {
    document.addEventListener('DOMContentLoaded', initMyRigsPage);

    // إيقاف التحديث عند مغادرة الصفحة
    window.addEventListener('beforeunload', stopEarningsTimer);
}