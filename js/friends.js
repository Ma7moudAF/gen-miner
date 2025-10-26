/* ========================================
   Friends Page Logic - منطق صفحة الأصدقاء
======================================== */

/**
 * Switch between friend level tabs
 */
function switchTab(level) {
    document.querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', i === level);
    });

    document.querySelectorAll('.tab-content').forEach((c, i) => {
        c.classList.toggle('active', i === level);
    });

    loadFriendsByLevel(level + 1);
}

/**
 * Load friends by level
 */
async function loadFriendsByLevel(level) {
    const listId = `level${level}List`;
    const list = document.getElementById(listId);

    if (!list) return;

    list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    const referrals = await getReferrals(level);

    if (referrals && referrals.length > 0) {
        // const countElement = document.getElementById(`level${level}Count`);
        // if (countElement) {
        //     countElement.textContent = referrals.length + 5;
        // }

        document.getElementById(`level${level}Count`).textContent = referrals.length;

        let html = '';
        referrals.forEach(ref => {
            html += `
                <div class="friend-item">
                    <div class="friend-left">
                        <img src="${ref.photo_url || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'}" class="friend-photo">
                        <div>
                            <div class="friend-name">${ref.firstname || 'User'}</div>
                            <div class="friend-username">@${ref.username}</div>
                        </div>
                    </div>

                </div>
            `;
            // <div class="friend-reward">+${fmt(ref.reward || 0)} 💰</div>
        });
        list.innerHTML = html;
    }else {
        list.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:20px;">No Friends</p>';}
    
}

/**
 * Update referral link display
 */
function updateReferralLink() {
    const referralLink = document.getElementById('referralLink');
    if (referralLink) {
        referralLink.textContent = `https://t.me/${window.APP_CONFIG.BOT_USERNAME}?start=${window.APP_CONFIG.user_id}`;
    }
}

/**
 * Share invite link
 */
function shareInvite() {
    const link = `https://t.me/${window.APP_CONFIG.BOT_USERNAME}?start=${window.APP_CONFIG.user_id}`;
    const text = `🚀 انضم لـ GEN Mining واربح عملات مجاناً!\n⛏️ تعدين بسيط وسهل\n\n${link}`;

    if (window.APP_CONFIG.tg && window.APP_CONFIG.tg.openTelegramLink) {
        window.APP_CONFIG.tg.openTelegramLink(`https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(text)}`);
    } else {
        navigator.clipboard.writeText(link).then(() => {
            showToast('✅ تم نسخ الرابط');
        });
    }
}

/**
 * Share on specific platform
 */
function shareOn(platform) {
    const link = `https://t.me/${window.APP_CONFIG.BOT_USERNAME}?start=${window.APP_CONFIG.user_id}`;
    const text = `🚀 انضم لـ GEN Mining واربح عملات مجاناً!\n⛏️ تعدين بسيط وسهل`;

    const urls = {
        whatsapp: `https://wa.me/?text=${encodeURIComponent(text + '\n' + link)}`,
        telegram: `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(text)}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`,
        twitter: `https://twitter.com/intent/tweet?url=${encodeURIComponent(link)}&text=${encodeURIComponent(text)}`
    };

    if (urls[platform]) {
        window.open(urls[platform], '_blank');
    }
}

/**
 * Initialize friends page
 */
async function initFriendsPage() {
    await loadUser();
    updateReferralLink();
    loadFriendsByLevel(1);
}

// Initialize on DOM load
if (document.body.dataset.page === 'friends') {
    document.addEventListener('DOMContentLoaded', initFriendsPage);
}
// referrals.length;
//         }

//         let html = '';
//         referrals.forEach(ref => {
//             html += `
//                 <div class="friend-item">
//                     <div class="friend-left">
//                         <img src="${ref.photo_url || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'}" 
//                              class="friend-photo" 
//                              onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
//                         <div class="friend-photo" style="display:none;">👤</div>
//                         <div>
//                             <div class="friend-name">${ref.firstname || 'مستخدم'}</div>
//                             <div class="friend-username">@${ref.username || 'user'}</div>
//                         </div>
//                     </div>
//                 </div>
//             `;
//         });

//         list.innerHTML = html;
//     } else {
//         const countElement = document.getElementById(`level${level}Count`);
//         if (countElement) {
//             countElement.textContent =