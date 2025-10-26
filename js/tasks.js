/* ========================================
   Mining Page Logic - منطق صفحة التعدين
======================================== */

/**
 * Calculate and update mining progress
 */
// function calculateMining() {
//     const userData = window.APP_STATE.userData;

//     if (!userData || !userData.mining_start_time) {
//         if (document.getElementById('genEarned'))
//             document.getElementById('genEarned').textContent = '0';

//         if (document.getElementById('miningProgress'))
//             document.getElementById('miningProgress').style.width = '0%';

//         if (document.getElementById('timeRemaining'))
//             document.getElementById('timeRemaining').textContent = '--:--';

//         if (document.getElementById('startBtn'))
//             document.getElementById('startBtn').classList.remove('hidden');

//         if (document.getElementById('collectBtn'))
//             document.getElementById('collectBtn').classList.add('hidden');

//         return;
//     }

//     const now = Math.floor(Date.now() / 1000);
//     const start = userData.mining_start_time;
//     const duration = userData.mining_duration || 14400;
//     const elapsed = now - start;

//     if (elapsed >= duration) {
//         // Mining completed
//         const genPerSec = userData.mining_power / 3600;
//         const total = duration * genPerSec;

//         if (document.getElementById('genEarned'))
//             document.getElementById('genEarned').textContent = fmt(total);

//         if (document.getElementById('miningProgress'))
//             document.getElementById('miningProgress').style.width = '100%';

//         if (document.getElementById('timeRemaining'))
//             document.getElementById('timeRemaining').textContent = '00:00';

//         if (document.getElementById('startBtn'))
//             document.getElementById('startBtn').classList.add('hidden');

//         if (document.getElementById('collectBtn'))
//             document.getElementById('collectBtn').classList.remove('hidden');
//     } else {
//         // Mining in progress
//         const genPerSec = userData.mining_power / 3600;
//         const current = elapsed * genPerSec;
//         const progress = (elapsed / duration) * 100;
//         const remaining = duration - elapsed;

//         if (document.getElementById('genEarned'))
//             document.getElementById('genEarned').textContent = fmt(current);

//         if (document.getElementById('miningProgress'))
//             document.getElementById('miningProgress').style.width = `${progress}%`;

//         if (document.getElementById('timeRemaining'))
//             document.getElementById('timeRemaining').textContent = fmtTime(remaining);

//         if (document.getElementById('startBtn'))
//             document.getElementById('startBtn').classList.add('hidden');

//         if (document.getElementById('collectBtn'))
//             document.getElementById('collectBtn').classList.add('hidden');
//     }
// }

// /**
//  * Start mining timer
//  */
// function startMiningTimer() {
//     if (window.APP_STATE.miningInterval) {
//         clearInterval(window.APP_STATE.miningInterval);
//     }
//     window.APP_STATE.miningInterval = setInterval(calculateMining, 1000);
// }

// /**
//  * Handle start mining button click
//  */
// async function handleStartMining() {
//     const btn = document.getElementById('startBtn');
//     if (!btn) return;

//     btn.disabled = true;
//     btn.textContent = 'Starting Mining... ⏳';

//     const success = await startMining();

//     if (success) {
//         showToast('✅ Mining has started!');
//         await loadUser();
//         calculateMining();
//     } else {
//         showToast('Start Failed ⚠️');
//         btn.disabled = false;
//         btn.textContent = ' Start Mining 🚀';
//     }
// }

// /**
//  * Handle collect mining button click
//  */
// async function handleCollectMining() {
//     const btn = document.getElementById('collectBtn');
//     if (!btn) return;

//     btn.disabled = true;
//     btn.textContent = ' Collection in Progress...⏳';

//     const result = await collectMining();

//     if (result.success) {
//         showToast(`Collected ${fmt(result.gen_collected)} GEN ✅`);
//         await loadUser();
//         calculateMining();
//     } else {
//         showToast('Collection Failed ⚠️');
//     }

//     btn.disabled = false;
//     btn.textContent = 'Collect Profits 🎁';
// }

/**
 * Update mining stats display
 */
function updateMiningStats() {
    const userData = window.APP_STATE.userData;
    if (!userData) return;

    if (document.getElementById('inviteCount'))
        document.getElementById('inviteCount').textContent = userData.invite_count || 0;

    if (document.getElementById('miningPower'))
        document.getElementById('miningPower').textContent = 'Power: ' + fmt(userData.mining_power || 1);
}

// ═══════════════════════════════════════════════════════════
// معالج أزرار التاسكات
// ═══════════════════════════════════════════════════════════

/**
 * بدء تنفيذ المهمة
 */
async function handleStartTask(taskId, taskUrl, taskType) {
    console.log('🚀 Starting task:', taskId);

    // ✅ 1. تعطيل الزرار فوراً
    const button = document.querySelector(`[onclick*="handleStartTask(${taskId}"]`);
    if (button) {
        button.disabled = true;
        button.textContent = 'Starting...';
        button.className = 'btn-claim disabled';
    }

    // استدعاء API
    const result = await startTask(taskId);

    if (!result.success) {
        showToast('⚠️ ' + result.message);
        // إعادة الزرار للحالة الأصلية
        if (button) {
            button.disabled = false;
            button.textContent = 'Start Task';
            button.className = 'btn-claim';
        }
        return;
    }

    // ✅ 2. حفظ البيانات مع الوقت المحلي
    const localStartTime = new Date().toISOString();
    result.started_at = localStartTime;
    TaskManager.saveTaskStart(taskId, result);

    // فتح الرابط
    if (taskUrl) {
        window.open(taskUrl, '_blank');
    }

    showToast('🚀 Task is started!');

    // ✅ 3. إعادة تحميل القائمة لعرض التاسك الجديد
    await loadTasksList();
}

/**
 * التحقق من المهمة
 */
async function handleVerifyTask(taskId) {
    console.log('🔍 Verifying task:', taskId);

    const taskData = TaskManager.getTaskStart(taskId);

    // التحقق من الوقت المطلوب
    if (!TaskManager.isMinDurationOver(taskId)) {
        const remaining = TaskManager.getRemainingTime(taskId, 'duration');
        showToast(`⏱️ يجب إكمال المهمة (باقي ${remaining} ثانية)`);
        return;
    }

    // إذا كانت المهمة تتطلب كلمة سر
    if (taskData?.has_password) {
        showPasswordPrompt(taskId);
        return;
    }

    // التحقق مباشرة
    await verifyTaskAndClaim(taskId);
}

/**
 * التحقق وأخذ الجائزة
 */
async function verifyTaskAndClaim(taskId, password = null) {
    const result = await verifyTask(taskId, password);

    if (!result.success) {
        if (result.requires_password) {
            // إعادة عرض كلمة السر
            showPasswordPrompt(taskId, result.message);
        } else {
            showToast('⚠️ ' + result.message);
            TaskManager.clearTaskStart(taskId);
            closePasswordModal(); // ✅ إغلاق الـ Modal
            setTimeout(loadTasksList, 500);
        }
        return;
    }

    // نجح!
    closePasswordModal(); // ✅ إغلاق الـ Modal
    showToast('🎉 ' + result.message);

    // تحديث بيانات المستخدم
    if (result.user) {
        window.APP_STATE.userData = result.user;
        updateTopBar(result.user);
        updateMiningStats();
    }

    // حذف البيانات المحفوظة
    TaskManager.clearTaskStart(taskId);

    // تحديث القائمة
    loadTasksList();
}

/**
 * عرض نافذة كلمة السر
 */
function showPasswordPrompt(taskId, errorMsg = '') {
    // حفظ الـ taskId في الـ modal نفسه
    document.getElementById('passwordModal').dataset.taskId = taskId;

    // فتح الـ Modal
    openModal('passwordModal');

    // تنظيف الحقول
    document.getElementById('passwordInput').value = '';
    document.getElementById('passwordInput').focus();

    // عرض رسالة الخطأ إن وجدت
    const errorDiv = document.getElementById('passwordError');
    if (errorMsg) {
        errorDiv.textContent = errorMsg;
        errorDiv.classList.remove('hidden');
    } else {
        errorDiv.classList.add('hidden');
    }

    // السماح بالإرسال عند الضغط على Enter
    document.getElementById('passwordInput').onkeypress = function(e) {
        if (e.key === 'Enter') {
            submitPassword();
        }
    };
}

/**
 * إغلاق نافذة كلمة السر
 */
function closePasswordModal() {
    closeModal('passwordModal');
    delete document.getElementById('passwordModal').dataset.taskId;
}

/**
 * إرسال كلمة السر
 */
function submitPassword() {
    const password = document.getElementById('passwordInput').value.trim();
    const taskId = parseInt(document.getElementById('passwordModal').dataset.taskId);

    if (!password) {
        const errorDiv = document.getElementById('passwordError');
        errorDiv.textContent = '⚠️ You must enter the password';
        errorDiv.classList.remove('hidden');
        return;
    }

    // إغلاق الـ Modal
    closePasswordModal();

    // استدعاء الدالة الأصلية
    verifyTaskAndClaim(taskId, password);
}

/**
 * إعادة فتح رابط التاسك (إعادة بدء من الأول)
 */
/**
 * إعادة فتح رابط التاسك (إعادة بدء من الأول)
 */
async function reopenTaskLink() {
    const taskId = parseInt(document.getElementById('passwordModal').dataset.taskId);

    if (!taskId) {
        showToast('⚠️ error: taskId not found');
        return;
    }

    // جلب بيانات التاسك من localStorage
    const taskData = TaskManager.getTaskStart(taskId);

    // البحث عن التاسك في القائمة الحالية
    const response = await apiRequest('getTasks', {});
    if (!response.success || !response.tasks) {
        showToast('⚠️ failed to get tasks');
        return;
    }

    const task = response.tasks.find(t => Number(t.id) === taskId);
    if (!task) {
        showToast('⚠️ cannot find task');
        return;
    }

    const taskUrl = task.task_url;
    const taskType = task.task_type;

    // إلغاء التاسك الحالي
    TaskManager.clearTaskStart(taskId);
    stopTaskTimer(taskId);

    // إغلاق الـ Modal
    closePasswordModal();

    // إعادة بدء التاسك من الأول
    showToast('🔄 Restarting the Task ...');
    await handleStartTask(taskId, taskUrl, taskType);
}

// ═══════════════════════════════════════════════════════════
// نظام المؤقتات للأزرار
// ═══════════════════════════════════════════════════════════

const taskTimers = {};

/**
 * بدء مؤقت للتاسك
 */
function startTaskTimer(taskId) {
    // إيقاف المؤقت القديم إن وجد
    if (taskTimers[taskId]) {
        clearInterval(taskTimers[taskId]);
    }
    
    // بدء مؤقت جديد
    taskTimers[taskId] = setInterval(() => {
        updateTaskButton(taskId);
    }, 1000); // كل ثانية
}

/**
 * إيقاف مؤقت التاسك
 */
function stopTaskTimer(taskId) {
    if (taskTimers[taskId]) {
        clearInterval(taskTimers[taskId]);
        delete taskTimers[taskId];
    }
}

/**
 * تحديث زر التاسك
 */
function updateTaskButton(taskId) {
    const button = document.querySelector(`[data-task-id="${taskId}"]`);
    if (!button) {
        // console.log('⚠️ Button not found for task:', taskId);
        return;
    }
    
    const taskData = TaskManager.getTaskStart(taskId);
    if (!taskData) {
        // console.log('⚠️ No task data for:', taskId);
        stopTaskTimer(taskId);
        return;
    }
    
    const elapsed = TaskManager.getElapsedTime(taskId);
    const waitTime = taskData.button_wait_time || 10;
    const minDuration = taskData.min_duration || 60;
    
    // console.log(`⏱️ Task ${taskId}: elapsed=${elapsed}s, wait=${waitTime}s, min=${minDuration}s`);
    
    // المرحلة 1: انتظار (0-10 ثواني)
    if (elapsed < waitTime) {
        const remaining = waitTime - elapsed;
        button.textContent = `Wait... `;
        button.disabled = true;
        button.className = 'btn-claim disabled';
        return;
    }
    
    // المرحلة 2: جاهز للتحقق (10-60 ثانية)
    if (elapsed < minDuration) {
        const remaining = minDuration - elapsed;
        button.textContent = `Verify Task`;
        button.disabled = false;
        button.className = 'btn-claim';
        return;
    }
    
    // المرحلة 3: جاهز للاستلام
    button.textContent = 'Verify Task';
    button.disabled = false;
    button.className = 'btn-claim';
    button.onclick = () => handleVerifyTask(taskId);
    
    // إيقاف المؤقت
    stopTaskTimer(taskId);
}

async function loadTasksList() {
    const list = document.getElementById('tasksList');
    if (!list) return;

    list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    const userData = window.APP_STATE.userData;
    if (!userData) {
        list.innerHTML = '<p style="text-align:center;color:var(--danger);">Error</p>';
        return;
    }

    const response = await apiRequest('getTasks', {});

    if (!response.success || !response.tasks || response.tasks.length === 0) {
        list.innerHTML = '<p style="text-align:center;color:var(--text-muted);">🎉 All tasks completed!</p>';
        return;
    }

    const tasks = response.tasks;
    const inviteCount = Number(userData.invite_count) || 0;

    let html = '';

    for (const task of tasks) {
        const taskId = task.id ;

        // التحقق من حالة التاسك
        const taskData = TaskManager.getTaskStart(taskId);
        const isInProgress = taskData !== null;

        let progress = 0;
        let isCompleted = false;
        let buttonHtml = '';

        // حساب التقدم حسب نوع المهمة
        if (task.task_type === 'invite') {
            progress = Math.min((inviteCount / task.target) * 100, 100);
            isCompleted = Number(inviteCount) >= Number(task.target);

        } else if (task.task_type === 'channel' || task.task_type === 'group' || task.task_type === 'bot') {
            isCompleted = true; // سيتم التحقق من السيرفر
            progress = 100;

        } else if (task.task_type === 'video') {
            if (isInProgress) {
                const elapsed = TaskManager.getElapsedTime(taskId);
                const minDuration = task.min_duration || 60;
                progress = Math.min((elapsed / minDuration) * 100, 100);
                isCompleted = elapsed >= minDuration;
            } else {
                progress = 0;
                isCompleted = true; // ✅ يعتبر جاهز للبدء
            }
        }

        // تحديد نوع الزر
        if (isInProgress) {
            // ✅ المهمة جارية - عرض Timer
            const elapsed = TaskManager.getElapsedTime(taskId);
            const waitTime = taskData.button_wait_time || 10;

            if (elapsed < waitTime) {
                const remaining = waitTime - elapsed;
                buttonHtml = `<button class="btn-claim disabled" disabled data-task-id="${taskId}">Wait...</button>`;
            } else {
                buttonHtml = `<button class="btn-claim" onclick="handleVerifyTask(${taskId})" data-task-id="${taskId}">Verify Task</button>`;
            }

        } else if (isCompleted) {
            // ✅ المهمة جاهزة للبدء أو الاستلام
            if (task.task_type === 'invite') {
                buttonHtml = `<button class="btn-claim" onclick="handleClaimTask(${taskId})">Collect</button>`;
            } else {
                // ✅ كل الأنواع الباقية (video, channel, group, bot)
                buttonHtml = `<button class="btn-claim" onclick="handleStartTask(${taskId}, '${task.task_url || ''}', '${task.task_type}')">Start Task</button>`;
            }
        } else {
            // المهمة غير مكتملة (مثل الدعوات)
            buttonHtml = `<button class="btn-claim" disabled>Incomplete</button>`;
        }

        const taskTypeEmoji = {
            'invite': '👥',
            'join': '📢',
            'channel': '📢',
            'group': '👥',
            'bot': '🤖',
            'video': '📺',
            'deposit': '💳'
        };

        html += `
            <div class="task-item ${isInProgress ? 'in-progress' : ''}">
                <div class="task-header">
                    <div class="task-title">
                        ${taskTypeEmoji[task.task_type] || '🎯'} ${task.description}
                        ${task.password ? '<span style="margin-left:5px;">🔑</span>' : ''}
                    </div>
                    <div class="task-reward">
                        +${fmt(task.reward_value)} ${task.reward_type === 'power' ? 'Power' : 'TON'}
                    </div>
                </div>

                ${((task.task_type === 'invite') || (task.task_type === 'video' && isInProgress)) ? `
                <div class="task-progress-text">
                    <span>${task.task_type === 'invite' ? `${Math.min(inviteCount, task.target)} / ${task.target}` : `${Math.floor(progress)}%`}</span>
                    ${task.task_type === 'video' && isInProgress ? `<span>⏱️ ${TaskManager.getRemainingTime(taskId, 'duration')}s</span>` : ''}
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: ${progress}%"></div>
                </div>
                ` : ''}

                <div class="task-footer">
                    <span style="color: var(--text-muted); font-size: 12px;">
                        ${task.task_type.toUpperCase()}
                        ${task.min_duration && task.task_type === 'video' ? ` • ${task.min_duration}s` : ''}
                    </span>
                    ${buttonHtml}
                </div>
            </div>
        `;
    }

    list.innerHTML = html;

    // ✅ إعادة تشغيل المؤقتات للتاسكات الجارية بعد Refresh
    setTimeout(() => {
        for (const task of tasks) {
            const taskId = task.id ;
            const taskData = TaskManager.getTaskStart(taskId);

            if (taskData) {
                startTaskTimer(taskId);
            }
        }
    }, 200);
}


/**
 * Handle claim task button click
 */
async function handleClaimTask(taskId) {
    const result = await apiRequest('claimTask', { task_id: taskId });

    if (result.success) {
        showToast('🎉 ' + result.message);

        if (result.user) {
            window.APP_STATE.userData = result.user;
        } else {
            await loadUser();
        }

        updateTopBar(window.APP_STATE.userData);
        updateMiningStats();
        loadTasksList();
        updateUI();
    } else {
        showToast('⚠️ ' + (result.message || 'Failed'));
    }
}



/**
 * Initialize mining page
 */
async function initTasksPage() {
    // تحميل بيانات المستخدم أولاً
    const user = await loadUser();

    if (user) {
        // بعد التأكد من تحميل البيانات، نحدّث كل شيء
        // updateMiningStats();
        // calculateMining();
        // startMiningTimer();
        loadTasksList();
    } else {
        showToast('⚠️ Failed to get data');
    }
}

// Initialize on DOM load
if (document.body.dataset.page === 'tasks') {
    document.addEventListener('DOMContentLoaded', autoRegisterUser);
    document.addEventListener('DOMContentLoaded', initTasksPage);
}