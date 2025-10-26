// admin.js - لوحة تحكم الأدمن
const API_ENDPOINT = 'admin.php';
const tg = window.Telegram?.WebApp || null;
let user_id = null;
let testMode = false;
let statusEl, tabContent, navButtons;
let currentTabData = [];
let currentTab = 'users';
let currentPage = 1;
let rowsPerPage = 10;
let totalPages = 1;
// ============================================
// إعداد معرف المستخدم
// ============================================
function setupuser_id() {
  if (tg && tg.initDataUnsafe?.user?.id) {
    user_id = tg.initDataUnsafe.user.id;
    testMode = false;
  } else {
    const param = new URLSearchParams(location.search).get('test_admin');
    user_id = param ? Number(param) : 5139923260;
    testMode = true;
  }
}

// ============================================
// عرض شعار الاختبار
// ============================================
function showTestBanner() {
  const banner = document.getElementById('testBanner');
  if (testMode) {
    banner.style.display = 'block';
    banner.innerHTML = `🧪 وضع الاختبار — Admin ID: <code>${user_id}</code>`;
  }
}

// ============================================
// عرض Toast Notification
// ============================================
function showToast(message, type = 'info') {
  const toast = document.getElementById('toast');
  toast.className = `toast toast-${type} active`;
  toast.textContent = message;
  setTimeout(() => toast.classList.remove('active'), 3000);
}

// ============================================
// التحقق من صلاحيات الأدمن
// ============================================
async function checkAdminLogin() {
  statusEl.textContent = 'جاري الاتصال...';
  showTestBanner();

  try {
    const res = await fetch(API_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'check_admin', user_id: user_id })
    });
    const j = await res.json();

    if (j.ok) {
      document.getElementById('loginPrompt').style.display = 'none';
      document.getElementById('dashboard').style.display = 'block';
      statusEl.textContent = '✅ متصل';
      loadTab('users');
      startNotificationCheck();
    } else {
      document.getElementById('loginPrompt').innerHTML = `
        <div style="text-align:center;padding:60px 20px;color:white;">
          <h2 style="font-size:48px;margin-bottom:20px;">🚫</h2>
          <h2 style="font-size:32px;margin-bottom:10px;">غير مصرح</h2>
          <p style="font-size:18px;opacity:0.9;">${j.error}</p>
        </div>
      `;
    }
  } catch (err) {
    document.getElementById('loginPrompt').innerHTML = `
      <div style="text-align:center;padding:60px 20px;color:white;">
        <h2 style="font-size:48px;margin-bottom:20px;">⚠️</h2>
        <h2 style="font-size:32px;margin-bottom:10px;">خطأ بالشبكة</h2>
        <p style="font-size:18px;opacity:0.9;">يرجى التحقق من الاتصال والمحاولة مرة أخرى</p>
      </div>
    `;
  }
}

// ============================================
// نظام الإشعارات
// ============================================
function startNotificationCheck() {
  checkPendingWithdrawals();
  setInterval(checkPendingWithdrawals, 30000); // كل 30 ثانية
}

async function checkPendingWithdrawals() {
  try {
    const url = new URL(API_ENDPOINT, location.href);
    url.searchParams.set('user_id', user_id);
    url.searchParams.set('tab', 'transactions');

    const res = await fetch(url);
    const data = await res.json();

    if (data.ok && data.data) {
      const pending = data.data.filter(t => t.type === 'withdraw' && t.status === 'pending').length;
      updateNotificationBadges(pending);
    }
  } catch (e) {
    console.error('Notification check failed:', e);
  }
}

function updateNotificationBadges(count) {
  const badge = document.getElementById('withdrawBadge');
  const tabBadge = document.getElementById('withdrawTabBadge');

  if (count > 0) {
    badge.style.display = 'block';
    badge.textContent = count;
    tabBadge.style.display = 'inline-block';
    tabBadge.textContent = count;
    document.getElementById('pendingWithdraws').textContent = count;
  } else {
    badge.style.display = 'none';
    tabBadge.style.display = 'none';
  }
}

// ============================================
// تحميل التبويبات
// ============================================
async function loadTab(tabName, searchQuery = '') {
  currentTab = tabName;
  currentPage = 1;
  statusEl.textContent = '⏳ جاري التحميل...';
  tabContent.innerHTML = '<div class="loading">جاري التحميل...</div>';

  const exportBtn = document.getElementById('exportBtn');
  exportBtn.style.display = ['users', 'transactions', 'withdrawals', 'referrals', 'tasks'].includes(tabName) ? 'block' : 'none';
  const dbdownloadBtn = document.getElementById('dbdownloadBtn');
  dbdownloadBtn.style.display = ['database'].includes(tabName) ? 'block' : 'none';
  const runDbBenchmarkBtn = document.getElementById('runDbBenchmarkBtn');
  runDbBenchmarkBtn.style.display = ['database'].includes(tabName) ? 'block' : 'none';

  try {
    
    const url = new URL(API_ENDPOINT, location.href);
    url.searchParams.set('user_id', user_id);
    url.searchParams.set('tab', tabName);
    if (['users', 'transactions', 'withdrawals', 'referrals', 'tasks'].includes(tabName)) {
      url.searchParams.set('page', currentPage);
      url.searchParams.set('limit', rowsPerPage);
    }
    
    if (searchQuery) url.searchParams.set('search', searchQuery);

    const res = await fetch(url);
    const data = await res.json();

    if (!data.ok) {
      tabContent.innerHTML = `<div style="color:red;padding:20px;"><pre>${JSON.stringify(data, null, 2)}</pre></div>`;
      statusEl.textContent = '❌ خطأ';
      return;
    }

    currentTabData = data.data || [];

    if (data.stats) {
      document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
      document.getElementById('totalBalance').textContent = (data.stats.total_balance || 0).toFixed(2);
      updateNotificationBadges(data.stats.pending_withdraws || 0);
    }

    switch (tabName) {
      case 'users': renderUsers(data.data, searchQuery); break;
      case 'transactions': renderTransactions(data.data, searchQuery); break;
      case 'withdrawals': renderWithdrawals(data.data, searchQuery); break;
      case 'tasks': renderTasks(data.data); break;
      case 'referrals': renderReferrals(data.data); break;
      case 'daily': renderChart(data.data); break;
      case 'database': renderDatabaseManager(); break;
    }

    statusEl.textContent = '✅ جاهز';
    document.getElementById('lastUpdated').textContent = 'آخر تحديث: ' + new Date().toLocaleString('ar-EG');
  } catch (e) {
    tabContent.innerHTML = `<div style="color:red;padding:20px;"><p>❌ ${e.message}</p></div>`;
    statusEl.textContent = '❌ خطأ';
  }
}

// ============================================
// عرض المستخدمين
// ============================================

function renderUsers(list = [], searchQuery = '', total = 0) {
  totalPages = Math.max(1, Math.ceil(total / rowsPerPage));

  const searchBarHTML = `
    <div class="search-bar">
      <input type="text" id="userSearch" placeholder="🔍 ابحث بالمعرف أو الاسم..." value="${escapeHtml(searchQuery)}">
      <button id="searchBtn" class="btn btn-primary btn-sm">🔍 بحث</button>
      <button id="clearBtn" class="btn btn-secondary btn-sm">✖ إلغاء</button>
    </div>
  `;

  let tableHTML = `
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>المعرف</th><th>الاسم</th><th>اليوزر</th><th>الرصيد</th>
            <th>رصيد التعدين</th><th>الطاقة</th><th>الإحالات</th>
            <th>المرحلة</th><th>تاريخ الانضمام</th><th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          ${list.length === 0 ? `
            <tr><td colspan="10" style="text-align:center;padding:20px;color:#999;">لا يوجد بيانات</td></tr>
          ` : list.map(u => `
            <tr>
              <td><code>${u.telegram_id}</code></td>
              <td><strong>${escapeHtml(u.firstname)}</strong></td>
              <td><span style="color:#3B82F6;">@${escapeHtml(u.username || 'N/A')}</span></td>
              <td><strong style="color:#10B981;">${parseFloat(u.balance).toFixed(2)}</strong></td>
              <td>${parseFloat(u.gen_balance).toFixed(2)}</td>
              <td>${parseFloat(u.mining_power).toLocaleString()}</td>
              <td><span class="status-badge" style="background:#E0E7FF;color:#3730A3;">${u.invite_count || 0}</span></td>
              <td><span class="status-badge" style="background:#DBEAFE;color:#1E40AF;">${u.stage || 1}</span></td>
              <td style="font-size:13px;color:#6B7280;">${u.join_date || ''}</td>
              <td>
                <div class="action-buttons">
                  <button class="btn btn-warning btn-sm" onclick="editUser(${u.telegram_id})">✏️ تعديل</button>
                </div>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
    <div class="pagination-controls" style="display:flex;gap:10px;align-items:center;justify-content:center;margin-top:15px;">
      <button id="prevPage" class="btn btn-secondary btn-sm" ${currentPage <= 1 ? 'disabled' : ''}>➡ السابق</button>
      <span>صفحة ${currentPage} من ${totalPages}</span>
      <button id="nextPage" class="btn btn-secondary btn-sm" ${currentPage >= totalPages ? 'disabled' : ''}>التالي ⬅</button>
      <label style="font-size:14px;">عدد الصفوف:</label>
      <select id="rowsPerPageSelect" class="btn btn-secondary btn-sm" style="padding:12px 20px;margin-right:10px;">
        <option value="10" ${rowsPerPage === 10 ? 'selected' : ''}>10</option>
        <option value="20" ${rowsPerPage === 20 ? 'selected' : ''}>20</option>
        <option value="50" ${rowsPerPage === 50 ? 'selected' : ''}>50</option>
        <option value="100" ${rowsPerPage === 100 ? 'selected' : ''}>100</option>
      </select>
    </div>
  `;

  tabContent.innerHTML = searchBarHTML + tableHTML;

  // 🔍 البحث
  document.getElementById('searchBtn').addEventListener('click', () => {
    const query = document.getElementById('userSearch').value.trim();
    loadTab('users', query);
  });
  document.getElementById('clearBtn').addEventListener('click', () => {
    loadTab('users');
  });

  // ⏮ التنقل بين الصفحات
  document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      loadTab('users', searchQuery);
    }
  });
  document.getElementById('nextPage').addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      loadTab('users', searchQuery);
    }
  });

  // 📏 تغيير عدد الصفوف
  document.getElementById('rowsPerPageSelect').addEventListener('change', e => {
    rowsPerPage = parseInt(e.target.value);
    currentPage = 1;
    loadTab('users', searchQuery);
  });
}

// ============================================
// عرض المعاملات
// ============================================

function renderTransactions(list = [], searchQuery = '', total = 0) {
  // حساب عدد الصفحات
  totalPages = Math.max(1, Math.ceil(total / rowsPerPage));

  const searchBarHTML = `
    <div class="search-bar">
      <input type="text" id="transSearch" placeholder="🔍 ابحث برقم العملية أو معرف المستخدم..." value="${escapeHtml(searchQuery)}">
      <button id="transSearchBtn" class="btn btn-primary btn-sm">🔍 بحث</button>
      <button id="transClearBtn" class="btn btn-secondary btn-sm">✖ إلغاء</button>
    </div>
  `;

  let tableHTML = `
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>رقم العملية</th>
            <th>المستخدم</th>
            <th>النوع</th>
            <th>القيمة</th>
            <th>الحالة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          ${list.length > 0 ? list.map(t => `
            <tr>
              <td><code>#${t.id}</code></td>
              <td><code>${t.telegram_id}</code></td>
              <td><span class="status-badge" style="background:#F3F4F6;color:#374151;">${t.type}</span></td>
              <td><strong style="color:#10B981;">${parseFloat(t.amount).toFixed(2)}</strong></td>
              <td><span class="status-badge status-${t.status}">${t.status}</span></td>
              <td style="font-size:13px;color:#6B7280;">${t.created_at || ''}</td>
            </tr>
          `).join('') : `<tr><td colspan="6" style="text-align:center;color:#6B7280;padding:30px;">لا توجد بيانات</td></tr>`}
        </tbody>
      </table>
    </div>
  `;

  // ✅ أزرار التنقل بين الصفحات
  const paginationHTML = `
    <div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px;">
      <button class="btn btn-secondary btn-sm" ${currentPage <= 1 ? 'disabled' : ''} onclick="changeTaskPage(-1)">➡ السابق</button>
      <span>صفحة ${currentPage} من ${totalPages}</span>
      <button class="btn btn-secondary btn-sm" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changeTaskPage(1)">التالي ⬅</button>
      <label style="font-size:14px;">عدد الصفوف:</label>
      <select id="rowsPerPageselect" class="btn btn-secondary btn-sm" style="padding:12px 20px;margin-right:10px;">
        <option value="10" ${rowsPerPage==10?'selected':''}>10</option>
        <option value="20" ${rowsPerPage==20?'selected':''}>20</option>
        <option value="50" ${rowsPerPage==50?'selected':''}>50</option>
        <option value="100" ${rowsPerPage==100?'selected':''}>100</option>
      </select>
    </div>
  `;

  tabContent.innerHTML = searchBarHTML + tableHTML + paginationHTML;

  // أزرار البحث
  document.getElementById('transSearchBtn').addEventListener('click', () => {
    const query = document.getElementById('transSearch').value.trim();
    currentPage = 1;
    loadTab('transactions', query);
  });

  document.getElementById('transClearBtn').addEventListener('click', () => {
    currentPage = 1;
    loadTab('transactions');
  });

  // تغيير عدد الصفوف
  document.getElementById('rowsPerPageselect').addEventListener('change', e => {
    rowsPerPage = parseInt(e.target.value);
    currentPage = 1;
    loadTab('transactions', searchQuery);
  });
}

function changeTransPage(dir) {
  const newPage = currentPage + dir;
  if (newPage >= 1 && newPage <= totalPages) {
    currentPage = newPage;
    loadTab('transactions');
  }
}

// ============================================
// عرض السحوبات
// ============================================
function renderWithdrawals(list = [], searchQuery = '', total = 0) {
  totalPages = Math.max(1, Math.ceil(total / rowsPerPage));

  const searchBarHTML = `
    <div class="search-bar">
      <input type="text" id="withdrawSearch" placeholder="🔍 ابحث بمعرف المستخدم..." value="${escapeHtml(searchQuery)}">
      <button id="withdrawSearchBtn" class="btn btn-primary btn-sm">🔍 بحث</button>
      <button id="withdrawClearBtn" class="btn btn-secondary btn-sm">✖ إلغاء</button>
      <select id="statusFilter" class="btn btn-secondary btn-sm" style="padding:12px 20px;">
        <option value="">كل الحالات</option>
        <option value="pending">معلقة</option>
        <option value="approved">موافق عليها</option>
        <option value="rejected">مرفوضة</option>
      </select>
    </div>
  `;

  const withdrawals = list.filter(t => t.type === 'withdraw');

  let tableHTML = `
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>رقم العملية</th>
            <th>المستخدم</th>
            <th>المبلغ</th>
            <th>الحالة</th>
            <th>التاريخ</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          ${withdrawals.length > 0 ? withdrawals.map(w => `
            <tr>
              <td><code>#${w.id}</code></td>
              <td><code>${w.telegram_id}</code></td>
              <td><strong style="color:#10B981;font-size:16px;">${parseFloat(w.amount).toFixed(2)} TON</strong></td>
              <td><span class="status-badge status-${w.status}">${w.status}</span></td>
              <td style="font-size:13px;color:#6B7280;">${w.created_at || ''}</td>
              <td>
                <div class="action-buttons">
                  ${w.status === 'pending' ? `
                    <button class="btn btn-success btn-sm" onclick="approveWithdraw(${w.id})">✓ موافقة</button>
                    <button class="btn btn-danger btn-sm" onclick="rejectWithdraw(${w.id})">✗ رفض</button>
                  ` : `
                    <button class="btn btn-secondary btn-sm" onclick="viewWithdrawDetails(${w.id})">👁 عرض</button>
                  `}
                </div>
              </td>
            </tr>
          `).join('') : `<tr><td colspan="6" style="text-align:center;color:#6B7280;padding:30px;">لا توجد بيانات</td></tr>`}
        </tbody>
      </table>
    </div>
  `;
     

  // 🔹 أزرار التنقل بين الصفحات
  const paginationHTML = `
    <div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px;">
      <button class="btn btn-secondary btn-sm" ${currentPage <= 1 ? 'disabled' : ''} onclick="changeWithdrawPage(-1)">➡ السابق</button>
      <span>صفحة ${currentPage} من ${totalPages}</span>
      <button class="btn btn-secondary btn-sm" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changeWithdrawPage(1)">التالي ⬅</button>
      <label style="font-size:14px;">عدد الصفوف:</label>
      <select id="rowsPerPageselect" class="btn btn-secondary btn-sm" style="padding:12px 20px;margin-right:10px;">
        <option value="10" ${rowsPerPage == 10 ? 'selected' : ''}>10</option>
        <option value="20" ${rowsPerPage == 20 ? 'selected' : ''}>20</option>
        <option value="50" ${rowsPerPage == 50 ? 'selected' : ''}>50</option>
        <option value="100" ${rowsPerPage == 100 ? 'selected' : ''}>100</option>
      </select>
    </div>`;

  tabContent.innerHTML = searchBarHTML + tableHTML + paginationHTML;

  // 🔹 البحث
  document.getElementById('withdrawSearchBtn').addEventListener('click', () => {
    const query = document.getElementById('withdrawSearch').value.trim();
    loadTab('withdrawals', query);
  });

  document.getElementById('withdrawClearBtn').addEventListener('click', () => {
    loadTab('withdrawals');
  });

  // 🔹 الفلترة
  document.getElementById('statusFilter').addEventListener('change', (e) => {
    const status = e.target.value;
    const rows = tabContent.querySelectorAll('tbody tr');
    rows.forEach(row => {
      if (!status || row.innerHTML.includes(`status-${status}`)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });

  // 🔹 عدد الصفوف لكل صفحة
  document.getElementById('rowsPerPageselect').addEventListener('change', (e) => {
    rowsPerPage = parseInt(e.target.value);
    changeWithdrawPage(1);
  });
}

// ✅ دالة لتغيير الصفحة
function changeWithdrawPage(dir) {
  const newPage = currentPage + dir;
  if (newPage >= 1 && newPage <= totalPages) {
    currentPage = newPage;
    loadTab('withdrawals');
  }
  
}
// ============================================
// موافقة / رفض السحب
// ============================================
async function approveWithdraw(transId) {
  if (!confirm('هل أنت متأكد من الموافقة على هذا الطلب؟')) return;

  try {
    const res = await fetch(API_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'approve_withdraw',
        user_id: user_id,
        trans_id: transId
      })
    });

    const data = await res.json();
    if (data.ok) {
      showToast('✅ تمت الموافقة على الطلب بنجاح', 'success');
      loadTab('withdrawals');
    } else {
      showToast('❌ حدث خطأ: ' + (data.error || 'Unknown'), 'error');
    }
  } catch (e) {
    showToast('❌ خطأ في الاتصال', 'error');
  }
}

async function rejectWithdraw(transId) {
  const reason = prompt('أدخل سبب الرفض (اختياري):');

  try {
    const res = await fetch(API_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'reject_withdraw',
        user_id: user_id,
        trans_id: transId,
        reason: reason || ''
      })
    });

    const data = await res.json();
    if (data.ok) {
      showToast('✅ تم رفض الطلب', 'success');
      loadTab('withdrawals');
    } else {
      showToast('❌ حدث خطأ', 'error');
    }
  } catch (e) {
    showToast('❌ خطأ في الاتصال', 'error');
  }
}

function viewWithdrawDetails(transId) {
  const withdraw = currentTabData.find(t => t.id === transId);
  if (!withdraw) return;

  const details = `
    <div style="line-height:2;">
      <p><strong>🆔 رقم العملية:</strong> ${withdraw.id}</p>
      <p><strong>👤 معرف المستخدم:</strong> ${withdraw.telegram_id}</p>
      <p><strong>💰 المبلغ:</strong> <span style="color:#10B981;font-weight:700;font-size:18px;">${parseFloat(withdraw.amount).toFixed(2)} TON</span></p>
      <p><strong>📊 الحالة:</strong> <span class="status-badge status-${withdraw.status}">${withdraw.status}</span></p>
      <p><strong>📅 التاريخ:</strong> ${withdraw.created_at || 'N/A'}</p>
    </div>
  `;

  document.getElementById('withdrawDetails').innerHTML = details;
  document.getElementById('withdrawModal').classList.add('active');
}

function closeWithdrawModal() {
  document.getElementById('withdrawModal').classList.remove('active');
}

// ============================================
// عرض المهام
// ============================================


function renderTasks(list = [], total = 0) {
  totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
  
  let html = `
    <div style="display:flex;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;align-items:center;">
      <h2 style="margin:0;font-size:24px;font-weight:800;">🪙 قائمة المهام</h2>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button onclick="showAddTaskForm()" class="btn btn-primary">➕ إضافة مهمة</button>
      </div>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>الايدي</th>
            <th>النوع</th>
            <th>الهدف</th>
            <th>نوع المكافأة</th>
            <th>القيمة</th>
            <th>الوصف</th>
            <th>الحاله</th>
            <th>المنفذين</th>
            <th>تاريخ الانشاء</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
  `;

  if (!list.length) {
    html += `<tr><td colspan="7" style="text-align:center;padding:40px;color:#6B7280;">لا توجد مهام حالياً</td></tr>`;
  } else {
    for (const t of list) {
      html += `
        <tr>
          <td><span class="status-badge" style="background:#DBEAFE;color:#1E40AF;font-size:16px;">${t.id}</span></td>
          <td><span class="status-badge" style="background:#F3F4F6;color:#374151;">${t.task_type}</span></td>
          <td><strong>${t.target}</strong></td>
          <td><span class="status-badge" style="background:#FEF3C7;color:#92400E;">${t.reward_type}</span></td>
          <td><strong style="color:#10B981;">${t.reward_value}</strong></td>
          <td style="max-width:300px;">${escapeHtml(t.description)}</td>
          <td><strong style="color:#10B981;">${t.is_active}</strong></td>
          <td><strong style="color:#10B981;">${t.max_claims}</strong></td>
          <td><strong style="color:#10B981;">${t.created_at}</strong></td>
          <td>
            <div class="action-buttons">
              <button class="btn btn-warning btn-sm" onclick="editTask(${t.id})">✏️ تعديل</button>
              <button class="btn btn-danger btn-sm" onclick="deleteTask(${t.id})">🗑️ حذف</button>
            </div>
          </td>
        </tr>`;
    }
  }

  html += `
      </tbody>
    </table>
  </div>

  <div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px;">
    <button class="btn btn-secondary btn-sm" ${currentPage <= 1 ? 'disabled' : ''} onclick="changeTaskPage(-1)">➡ السابق</button>
    <span>صفحة ${currentPage} من ${totalPages}</span>
    <button class="btn btn-secondary btn-sm" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changeTaskPage(1)">التالي ⬅</button>
    <label style="font-size:14px;">عدد الصفوف:</label>
    <select id="tasksLimitSelect" class="btn btn-secondary btn-sm" style="padding:8px 12px;">
      <option value="10" ${rowsPerPage==10?'selected':''}>10</option>
      <option value="20" ${rowsPerPage==20?'selected':''}>20</option>
      <option value="50" ${rowsPerPage==50?'selected':''}>50</option>
      <option value="100" ${rowsPerPage==100?'selected':''}>100</option>
    </select>
  </div>
  
  `;

  

  tabContent.innerHTML = html;

  // تحديث limit
  document.getElementById("tasksLimitSelect").addEventListener("change", (e) => {
    rowsPerPage = parseInt(e.target.value);
    loadTab("tasks");
  });
}

function changeTaskPage(dir) {
  const newPage = currentPage + dir;
  if (newPage >= 1 && newPage <= totalPages) {
    currentPage = newPage;
    loadTab("tasks");
  }
}


function showAddTaskForm() {
  document.getElementById('taskModalTitle').textContent = '➕ إضافة مهمة';
  // document.getElementById('taskStage').value = '';
  document.getElementById('inputStage').value = '';
  document.getElementById('inputType').value = 'invite';
  document.getElementById('inputTarget').value = '';
  document.getElementById('inputRewardType').value = 'power';
  document.getElementById('inputRewardValue').value = '';
  document.getElementById('inputDescription').value = '';
  document.getElementById('taskModal').classList.add('active');
}

function editTask(stage) {
  const t = currentTabData.find(x => x.stage == stage);
  if (!t) return showToast('❌ لم يتم العثور على المهمة', 'error');

  document.getElementById('taskModalTitle').textContent = '✏️ تعديل المهمة';
  // document.getElementById('taskStage').value = t.stage;
  document.getElementById('inputStage').value = t.stage;
  document.getElementById('inputType').value = t.type;
  document.getElementById('inputTarget').value = t.target;
  document.getElementById('inputRewardType').value = t.reward_type;
  document.getElementById('inputRewardValue').value = t.reward_value;
  document.getElementById('inputDescription').value = t.description;
  document.getElementById('taskModal').classList.add('active');
}

function closeTaskModal() {
  document.getElementById('taskModal').classList.remove('active');
}

async function deleteTask(id) {
  if (!confirm("هل أنت متأكد من حذف هذه المهمة؟")) return;

  try {
    const res = await fetch(API_ENDPOINT, {
      method: 'POST',
      body: new URLSearchParams({
        user_id: user_id,
        action: 'tasks',
        sub_action: 'delete',
        id
      })
    });

    const json = await res.json();
    showToast(json.message || 'تم الحذف', json.ok ? 'success' : 'error');

    if (json.ok) {
      currentTabData = currentTabData.filter(t => t.id != id);
      renderTasks(currentTabData);
    }
  } catch (e) {
    showToast('خطأ في الاتصال', 'error');
  }
}

// ============================================
// تعديل المستخدم
// ============================================
function editUser(telegramId) {
  const u = currentTabData.find(x => x.telegram_id == telegramId);
  if (!u) return showToast('المستخدم غير موجود', 'error');

  document.getElementById('editUserId').value = u.telegram_id;
  document.getElementById('editBalance').value = u.balance;
  document.getElementById('editPower').value = u.mining_power;
  document.getElementById('editGenBalance').value = u.gen_balance;
  document.getElementById('editInvites').value = u.invite_count || 0;
  document.getElementById('editStage').value = u.stage || 1;

  document.getElementById('editUserModal').classList.add('active');
}

function closeEditUserModal() {
  document.getElementById('editUserModal').classList.remove('active');
}

// ============================================
// عرض الإحالات
// ============================================
function renderReferrals(list = [], total = 0) {
  totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
  let html = `
    <div style="display:flex;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;align-items:center;">
      <h2 style="margin:0;font-size:24px;font-weight:800;">🤝 نظام الإحالات</h2>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>من</th>
            <th>إلى</th>
            <th>المستوى</th>
            <th>المبلغ</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
  `;

  if (!list.length) {
    html += `<tr><td colspan="6" style="text-align:center;padding:40px;color:#6B7280;">لا توجد إحالات حالياً</td></tr>`;
  } else {
    for (const r of list) {
      html += `
        <tr>
          <td><code>#${r.id}</code></td>
          <td><code>${r.from_id}</code></td>
          <td><code>${r.to_id}</code></td>
          <td><span class="status-badge" style="background:#E0E7FF;color:#3730A3;">Level ${r.level}</span></td>
          <td><strong style="color:#10B981;">${parseFloat(r.amount).toFixed(2)}</strong></td>
          <td style="font-size:13px;color:#6B7280;">${r.created_at || ''}</td>
        </tr>`;
    }
  }

  html += `
      </tbody>
    </table>
  </div>

  <div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px;">
    <button class="btn btn-secondary btn-sm" ${currentPage <= 1 ? 'disabled' : ''} onclick="changeTaskPage(-1)">➡ السابق</button>
    <span>صفحة ${currentPage} من ${totalPages}</span>
    <button class="btn btn-secondary btn-sm" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changeTaskPage(1)">التالي ⬅</button>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <label style="font-size:14px;">عدد الصفوف:</label>
      <select id="refLimitSelect" class="btn btn-secondary btn-sm" style="padding:8px 12px;">
        <option value="10" ${rowsPerPage==10?'selected':''}>10</option>
        <option value="20" ${rowsPerPage==20?'selected':''}>20</option>
        <option value="50" ${rowsPerPage==50?'selected':''}>50</option>
        <option value="100" ${rowsPerPage==100?'selected':''}>100</option>
      </select>
    </div>
  </div>
  `;

  tabContent.innerHTML = html;

  // تحديث عدد الصفوف
  document.getElementById("refLimitSelect").addEventListener("change", (e) => {
    rowsPerPage = parseInt(e.target.value);
    loadTab("referrals");
  });
}

function changeReferralPage(dir) {
  const newPage = currentPage + dir;
  if (newPage >= 1 && newPage <= totalPages) {
    currentPage = newPage;
    loadTab("referrals");
  }
}

// ============================================
// عرض الإحصائيات اليومية
// ============================================
function renderChart(daily = []) {
  const canvas = document.createElement('canvas');
  canvas.style.maxHeight = '500px';
  tabContent.innerHTML = '<div style="padding:20px;"><h2 style="margin-bottom:20px;font-size:24px;font-weight:800;">📊 إحصائيات النمو اليومي</h2></div>';
  tabContent.appendChild(canvas);

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: daily.map(d => d.date),
      datasets: [
        { 
          label: 'مستخدمين جدد', 
          data: daily.map(d => +d.count), 
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          fill: true,
          tension: 0.4,
          borderWidth: 3
        },
        { 
          label: 'إجمالي الرصيد', 
          data: daily.map(d => +(d.balance_sum || 0)), 
          borderColor: '#10B981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: true,
          tension: 0.4,
          borderWidth: 3
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { 
          position: 'top',
          labels: {
            font: { size: 14, family: 'Cairo', weight: '600' },
            padding: 15,
            usePointStyle: true
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          titleFont: { size: 14, family: 'Cairo', weight: '700' },
          bodyFont: { size: 13, family: 'Cairo' },
          cornerRadius: 8
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          },
          ticks: {
            font: { size: 12, family: 'Cairo' }
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            font: { size: 12, family: 'Cairo' }
          }
        }
      }
    }
  });
}



// ============================================
// إدارة قاعدة البيانات
// ============================================
async function renderDatabaseManager() {
  tabContent.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <h2 style="margin:0;font-size:24px;font-weight:800;">🗄️ إدارة قاعدة البيانات</h2>
      <button class="btn btn-warning" onclick="openSqlConsole()">💻 تنفيذ أمر SQL</button>
      <button class="btn btn-primary" onclick="loadTablesList()">🔄 تحديث</button>
      
    </div>
    <div id="dbContent" </div>
  `;
  loadTablesList();
}
// تحميل الجداول
async function loadTablesList() {
  const dbContent = document.getElementById("dbContent");
  dbContent.innerHTML = "<div class='loading'>تحميل الجداول...</div>";

  try {
    const res = await fetch(API_ENDPOINT, {
      method: "POST",
      body: new URLSearchParams({
        user_id,
        action: "database",
        sub_action: "list_tables"
      })
    });

    const json = await res.json();

    if (!json.ok) {
      dbContent.innerHTML = `<p style="color:red;padding:20px;">${json.message || "فشل تحميل الجداول"}</p>`;
      return;
    }

    let html = `
      <div style="margin-bottom:20px;">
        <button class="btn btn-success" onclick="showCreateTableForm()">➕ إنشاء جدول جديد</button>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>اسم الجدول</th><th>عدد الأعمدة</th><th>إجراءات</th></tr></thead>
          <tbody>
    `;

    json.tables.forEach(t => {
      html += `
        <tr>
          <td><strong style="font-size:16px;">${t.name}</strong></td>
          <td><span class="status-badge" style="background:#DBEAFE;color:#1E40AF;">${t.columns}</span></td>
          <td>
            <div class="action-buttons">
              <button class="btn btn-primary btn-sm" onclick="viewTable('${t.name}')">👁 عرض</button>
              <button class="btn btn-warning btn-sm" onclick="addColumnPrompt('${t.name}')">➕ عمود</button>
              <button class="btn btn-danger" onclick="clearTable('${t.name}')">🧹 تفريغ الجدول</button>
              <button class="btn btn-danger btn-sm" onclick="deleteTable('${t.name}')">🗑 حذف</button>
            </div>
          </td>
        </tr>
      `;
    });

    html += "</tbody></table></div>";
    dbContent.innerHTML = html;
  } catch (err) {
    dbContent.innerHTML = `<p style="color:red;padding:20px;">${err.message}</p>`;
  }
}
// عرض الجداول

async function viewTable(table, page = 1) {
  
  currentPage = page;

  const dbContent = document.getElementById("dbContent");
  dbContent.innerHTML = "<div class='loading'>جارٍ تحميل البيانات...</div>";

  try {
    const res = await fetch(API_ENDPOINT, {
      method: "POST",
      body: new URLSearchParams({
        user_id,
        action: "database",
        sub_action: "view_table",
        name: table,
        page,
        limit: rowsPerPage
      })
    });

    const json = await res.json();

    if (!json.ok) {
      dbContent.innerHTML = `<p style="color:red;padding:20px;">${json.message}</p>`;
      return;
    }

    let html = `
      <div style="margin-bottom:20px;">
        <h3 style="font-size:20px;font-weight:700;margin-bottom:15px;">📋 جدول: ${table}</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn btn-secondary btn-sm" onclick="loadTablesList()">⬅ رجوع</button>
          <button class="btn btn-success btn-sm" onclick="addColumnPrompt('${table}')">➕ إضافة عمود</button>
          <button class="btn btn-danger btn-sm" onclick="clearTable('${table}')">🧹 تفريغ الجدول</button>
        </div>
      </div>

      <div style="margin-bottom:15px;">
        <label>عرض 
          <select id="rowsPerPage" onchange="changerowsPerPage('${table}')">
            <option value="10" ${rowsPerPage==10?'selected':''}>10</option>
            <option value="20" ${rowsPerPage==20?'selected':''}>20</option>
            <option value="50" ${rowsPerPage==50?'selected':''}>50</option>
            <option value="100" ${rowsPerPage==100?'selected':''}>100</option>
          </select> صف في الصفحة
        </label>
      </div>

      <div class="table-container">
        <table>
          <thead><tr>${json.columns.map(c => `<th>${c}</th>`).join('')}</tr></thead>
          <tbody>
    `;

    if (json.rows.length === 0) {
      html += `<tr><td colspan="${json.columns.length}" style="text-align:center;color:#6B7280;padding:40px;">لا توجد بيانات</td></tr>`;
    } else {
      json.rows.forEach(row => {
        html += `<tr>${json.columns.map(c => `<td>${row[c] ?? ""}</td>`).join('')}</tr>`;
      });
    }

    html += `
        </tbody>
      </table>
    </div>

    <div style="margin-top:15px;display:flex;justify-content:space-between;align-items:center;">
      <button class="btn btn-secondary btn-sm" ${page <= 1 ? "disabled" : ""} onclick="viewTable('${table}', ${page - 1})">➡ السابق</button>
      <span style="font-size:14px;color:var(--text-muted);">صفحة ${page}</span>
      <button class="btn btn-secondary btn-sm" ${json.rows.length < rowsPerPage ? "disabled" : ""} onclick="viewTable('${table}', ${page + 1})">التالي ⬅</button>
    </div>

    <div style="margin-top:30px;">
      <h4 style="margin-bottom:15px;font-size:18px;font-weight:700;">⚙️ إدارة الأعمدة</h4>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        ${json.columns.map(c => `
          <button class="btn btn-warning btn-sm" onclick="renameColumnPrompt('${table}', '${c}')">✏️ ${c}</button>
          <button class="btn btn-danger btn-sm" onclick="deleteColumnPrompt('${table}', '${c}')">🗑 ${c}</button>
        `).join('')}
      </div>
    </div>
    `;

    dbContent.innerHTML = html;

  } catch (err) {
    dbContent.innerHTML = `<p style="color:red;padding:20px;">${err.message}</p>`;
  }
}
// تغيير عدد الصفوف في الصفحة
function changerowsPerPage(table) {
  const select = document.getElementById("rowsPerPage");
  rowsPerPage = parseInt(select.value);
  viewTable(table, 1);
}

async function showCreateTableForm() {
  const name = prompt("اسم الجدول الجديد:");
  if (!name) return;

  const columns = prompt("أدخل أسماء الأعمدة (مفصولة بفواصل):", "id INTEGER PRIMARY KEY, name TEXT");
  if (!columns) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "create_table",
      name,
      columns
    })
  });

  const json = await res.json();
  showToast(json.message || "تمت العملية", json.ok ? "success" : "error");
  if (json.ok) loadTablesList();
}

// حذف الجدول
async function deleteTable(name) {
  if (!confirm("هل تريد حذف الجدول " + name + "؟")) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "delete_table",
      name
    })
  });

  const json = await res.json();
  showToast(json.message, json.ok ? "success" : "error");
  if (json.ok) loadTablesList();
}
async function clearTable(name) {
  if (!confirm("هل تريد تفريغ الجدول " + name + "؟")) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "clear_table",
      name
    })
  });

  const json = await res.json();
  showToast(json.message, json.ok ? "success" : "error");
  if (json.ok) viewTable(name);
}

// إضافة عمود جديد
async function addColumnPrompt(table) {
  const col = prompt("اسم العمود الجديد:");
  if (!col) return;
  const type = prompt("نوع العمود (مثلاً TEXT, INTEGER, REAL):", "TEXT");
  if (!type) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "add_column",
      table,
      col,
      type
    })
  });

  const json = await res.json();
  showToast(json.message, json.ok ? "success" : "error");
  if (json.ok) viewTable(table);
}

// تعديل اسم عمود
async function renameColumnPrompt(table, oldName) {
  const newName = prompt(`الاسم الجديد للعمود "${oldName}":`);
  if (!newName) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "rename_column",
      table,
      oldName,
      newName
    })
  });

  const json = await res.json();
  showToast(json.message, json.ok ? "success" : "error");
  if (json.ok) viewTable(table);
}

async function deleteColumnPrompt(table, column) {
  if (!confirm(`هل تريد حذف العمود "${column}"؟`)) return;

  const res = await fetch(API_ENDPOINT, {
    method: "POST",
    body: new URLSearchParams({
      user_id,
      action: "database",
      sub_action: "delete_column",
      table,
      column
    })
  });

  const json = await res.json();
  showToast(json.message, json.ok ? "success" : "error");
  if (json.ok) viewTable(table);
}

function openSqlConsole() {
  // openModal('sqlConsoleModal');
  document.getElementById('sqlConsoleModal').classList.add('active');
}
function closeSqlConsole() {
  document.getElementById('sqlConsoleModal').classList.remove('active');
}

async function executeSqlCommand() {
  const command = document.getElementById('sqlCommand').value.trim();
  const resultBox = document.getElementById('sqlResult');

  if (!command) {
    showToast('⚠️ اكتب أمر SQL أولاً');
    return;
  }

  resultBox.textContent = '⏳ جاري التنفيذ...';

  try {
    const res = await fetch(API_ENDPOINT, {
      method: 'POST',
      body: new URLSearchParams({
        user_id,
        action: 'database',
        sub_action: 'run_sql',
        query: command
      })
    });

    const json = await res.json();

    if (json.ok) {
      if (Array.isArray(json.result)) {
        // عرض جدول النتائج
        resultBox.textContent = JSON.stringify(json.result, null, 2);
      } else {
        resultBox.textContent = json.message || 'تم التنفيذ بنجاح ✅';
      }
    } else {
      resultBox.textContent = '❌ خطأ: ' + (json.error || 'فشل التنفيذ');
    }
  } catch (err) {
    resultBox.textContent = '⚠️ خطأ أثناء الاتصال بالسيرفر';
  }
}


// تشغيل اختبار الأداء
async function runDbBenchmark() {
  if (!confirm("هل تريد تشغيل اختبار الأداء؟\nقد يستغرق عدة ثوانٍ.")) return;

  const dbContent = document.getElementById("dbContent");
  dbContent.innerHTML = "<div class='loading'>🔄 جاري إجراء اختبار الأداء...</div>";

  try {
    const res = await fetch(API_ENDPOINT, {
      method: "POST",
      body: new URLSearchParams({
        user_id,
        action: "database",
        sub_action: "benchmark"
      })
    });

    const json = await res.json();
    if (!json.ok) {
      dbContent.innerHTML = `<p style="color:red;padding:20px;">❌ ${json.error || "فشل الاختبار"}</p>`;
      return;
    }

    // عرض النتيجة في نافذة منبثقة جميلة
    showModalResult(`
      <h2>🚀 تقرير اختبار الأداء</h2>
      <pre style="white-space:pre-wrap;text-align:left;direction:ltr;padding:10px;background:#f5f5f5;border-radius:10px;">
${json.result}
      </pre>
    `);

  } catch (err) {
    dbContent.innerHTML = `<p style="color:red;padding:20px;">${err.message}</p>`;
  }
}
// عرض نافذة النتيجة
function showModalResult(content) {
  const modal = document.createElement("div");
  modal.className = "modal active";
  modal.innerHTML = `
    <div class="modal-content" style="max-width:700px;">
      <div class="modal-header">
        <div class="modal-title">نتيجة الاختبار</div>
        <button class="close-btn" onclick="this.closest('.modal').remove()">×</button>
      </div>
      <div class="modal-body">${content}</div>
    </div>`;
  document.body.appendChild(modal);
  loadTablesList();
}


// ============================================
// تصدير Excel
// ============================================
function exportToExcel() {
  if (!currentTabData || currentTabData.length === 0) {
    showToast('لا توجد بيانات للتصدير', 'error');
    return;
  }

  let fileName = '';
  let data = [];

  switch(currentTab) {
    case 'users':
      fileName = 'users_export.xlsx';
      data = currentTabData.map(u => ({
        'المعرف': u.telegram_id,
        'الاسم': u.firstname,
        'اليوزر': u.username,
        'الرصيد': u.balance,
        'رصيد التعدين': u.gen_balance,
        'الطاقة': u.mining_power,
        'الإحالات': u.invite_count || 0,
        'المرحلة': u.stage || 1,
        'تاريخ الانضمام': u.join_date
      }));
      break;

    case 'transactions':
      fileName = 'transactions_export.xlsx';
      data = currentTabData.map(t => ({
        'رقم العملية': t.id,
        'المستخدم': t.telegram_id,
        'النوع': t.type,
        'القيمة': t.amount,
        'الحالة': t.status,
        'التاريخ': t.created_at
      }));
      break;

    case 'withdrawals':
      fileName = 'withdrawals_export.xlsx';
      data = currentTabData.filter(t => t.type === 'withdraw').map(w => ({
        'رقم العملية': w.id,
        'المستخدم': w.telegram_id,
        'المبلغ': w.amount,
        'الحالة': w.status,
        'التاريخ': w.created_at
      }));
      break;

    case 'referrals':
      fileName = 'referrals_export.xlsx';
      data = currentTabData.map(r => ({
        'ID': r.id,
        'من': r.from_id,
        'إلى': r.to_id,
        'المستوى': r.level,
        'المبلغ': r.amount,
        'التاريخ': r.created_at
      }));
      break;

    default:
      showToast('لا يمكن تصدير هذا التبويب', 'error');
      return;
  }

  try {
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Data');
    XLSX.writeFile(wb, fileName);
    showToast('✅ تم تصدير البيانات بنجاح', 'success');
  } catch (e) {
    showToast('❌ خطأ في التصدير', 'error');
  }
}

// ============================================
// التبديل بين التبويبات
// ============================================
function switchToTab(tabName) {
  const btn = document.querySelector(`[data-tab="${tabName}"]`);
  if (btn) {
    navButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadTab(tabName);
  }
}

// ============================================
// تنظيف HTML
// ============================================
function escapeHtml(s) {
  return String(s || '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

// ============================================
// Form Handlers
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  statusEl = document.getElementById('status');
  tabContent = document.getElementById('tabContent');
  navButtons = document.querySelectorAll('.tab-btn');

  setupuser_id();
  checkAdminLogin();

  // التبويبات
  navButtons.forEach(btn => {
    btn.onclick = () => {
      navButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      loadTab(btn.dataset.tab);
    };
  });

  // أزرار الهيدر
  document.getElementById('refreshTabBtn').addEventListener('click', () => {
    const active = document.querySelector('.tab-btn.active');
    const currentTab = active ? active.dataset.tab : 'users';
    loadTab(currentTab);
  });

  document.getElementById('exportBtn').addEventListener('click', exportToExcel);

  document.getElementById('logoutBtn').addEventListener('click', () => {
    if (confirm('هل تريد تسجيل الخروج؟')) {
      location.reload();
    }
  });

  // Form تعديل المستخدم
  document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const userid = document.getElementById('editUserId').value;
    const balance = document.getElementById('editBalance').value;
    const mining_power = document.getElementById('editPower').value;
    const gen_balance = document.getElementById('editGenBalance').value;
    const invite_count = document.getElementById('editInvites').value;
    const stage = document.getElementById('editStage').value;

    try {
      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        body: new URLSearchParams({
          user_id: user_id,
          action: 'updateuser',
          userid,
          balance,
          gen_balance,
          mining_power,
          invite_count,
          stage
        })
      });

      const json = await res.json();
      showToast(json.message || 'تم تعديل المستخدم', json.ok ? 'success' : 'error');

      if (json.ok) {
        const index = currentTabData.findIndex(u => u.telegram_id == userid);
        if (index !== -1) {
          currentTabData[index] = {
            ...currentTabData[index],
            balance, gen_balance, mining_power, invite_count, stage
          };
          renderUsers(currentTabData);
        }
        closeEditUserModal();
      }
    } catch (err) {
      showToast('⚠️ خطأ في الاتصال', 'error');
    }
  });

  // Form المهام
  document.getElementById('taskForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const oldStage = document.getElementById('taskStage').value;
    const stage = document.getElementById('inputStage').value;
    const type = document.getElementById('inputType').value;
    const target = document.getElementById('inputTarget').value;
    const reward_type = document.getElementById('inputRewardType').value;
    const reward_value = document.getElementById('inputRewardValue').value;
    const description = document.getElementById('inputDescription').value;

    const isEdit = !!oldStage;
    const actionType = isEdit ? 'update' : 'add';

    try {
      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        body: new URLSearchParams({
          user_id,
          action: 'tasks',
          sub_action: actionType,
          stage,
          type,
          target,
          reward_type,
          reward_value,
          description
        })
      });

      const json = await res.json();
      showToast(json.message || (isEdit ? 'تم التعديل' : 'تمت الإضافة'), json.ok ? 'success' : 'error');

      if (json.ok) {
        if (isEdit) {
          const index = currentTabData.findIndex(t => t.stage == oldStage);
          if (index !== -1) currentTabData[index] = { stage, type, target, reward_type, reward_value, description };
        } else {
          currentTabData.push({ stage, type, target, reward_type, reward_value, description });
        }
        renderTasks(currentTabData);
        closeTaskModal();
      }
    } catch (err) {
      showToast('⚠️ خطأ في الاتصال', 'error');
    }
  });

  // إغلاق المودال عند الضغط خارجه
  document.getElementById('withdrawModal').addEventListener('click', (e) => {
    if (e.target.id === 'withdrawModal') closeWithdrawModal();
  });

  document.getElementById('editUserModal').addEventListener('click', (e) => {
    if (e.target.id === 'editUserModal') closeEditUserModal();
  });

  document.getElementById('sqlConsoleModal').addEventListener('click', (e) => {
    if (e.target.id === 'sqlConsoleModal') closeSqlConsole();
  });

  document.getElementById('taskModal').addEventListener('click', (e) => {
    if (e.target.id === 'taskModal') closeTaskModal();
  });
});