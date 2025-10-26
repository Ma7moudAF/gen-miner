<?php
/**
 * نظام إشعارات Telegram للأدمن
 * يرسل إشعارات فورية عند حدوث أحداث مهمة
 * Version: 2.0 - Enhanced
 */

$config = include __DIR__ . '/config.php';

// الاتصال بقاعدة البيانات
try {
    $db = new PDO("sqlite:" . $config['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("❌ Database connection failed: " . $e->getMessage());
    die("❌ Database connection failed");
}

/**
 * إرسال رسالة Telegram مع معالجة الأخطاء
 */
function sendTelegramNotification($chat_id, $message, $parse_mode = 'HTML', $disable_notification = false) {
    global $config;

    $url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'disable_notification' => $disable_notification
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode !== 200 || !$result['ok']) {
        error_log("Failed to send Telegram notification: " . print_r($result, true));
        return false;
    }

    return true;
}

/**
 * إشعار طلب سحب جديد
 */
function notifyNewWithdrawal($user_id, $amount, $transaction_id) {
    global $config, $db;

    try {
        // الحصول على معلومات المستخدم
        $stmt = $db->prepare("SELECT username, firstname, balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $username = $user['username'] ?? 'Unknown';
        $firstname = $user['firstname'] ?? 'Unknown';
        $remaining_balance = $user['balance'] ?? 0;

        $message = "🔔 <b>طلب سحب جديد!</b>\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 <b>المستخدم:</b> {$firstname}\n";
        $message .= "🆔 <b>اليوزر:</b> @{$username}\n";
        $message .= "🔢 <b>المعرف:</b> <code>{$user_id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 <b>المبلغ المطلوب:</b> <code>{$amount} USDT</code>\n";
        $message .= "📊 <b>الرصيد المتبقي:</b> <code>{$remaining_balance} USDT</code>\n";
        $message .= "🆔 <b>رقم العملية:</b> <code>#{$transaction_id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "✅ يرجى المراجعة من لوحة التحكم";

        return sendTelegramNotification($config['admin_id'], $message);
    } catch (Exception $e) {
        error_log("Error in notifyNewWithdrawal: " . $e->getMessage());
        return false;
    }
}

/**
 * إشعار عند تسجيل مستخدم جديد
 */
function notifyNewUser($user_id, $username, $firstname, $referrer_id = null) {
    global $config;

    $message = "👥 <b>مستخدم جديد انضم!</b>\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "👤 <b>الاسم:</b> {$firstname}\n";
    $message .= "🆔 <b>اليوزر:</b> @{$username}\n";
    $message .= "🔢 <b>المعرف:</b> <code>{$user_id}</code>\n";

    if ($referrer_id) {
        $message .= "🔗 <b>عن طريق دعوة:</b> <code>{$referrer_id}</code>\n";
    }

    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "📊 العدد الإجمالي للمستخدمين: <b>" . getTotalUsers() . "</b>";

    return sendTelegramNotification($config['admin_id'], $message, 'HTML', true);
}

/**
 * إشعار عند اكتمال مهمة
 */
function notifyTaskCompleted($user_id, $task_stage, $reward_value, $reward_type) {
    global $config, $db;

    try {
        $stmt = $db->prepare("SELECT username, firstname FROM users WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $username = $user['username'] ?? 'Unknown';
        $firstname = $user['firstname'] ?? 'Unknown';

        $reward_text = $reward_type === 'power' ? "طاقة تعدين" : "USDT";

        $message = "🎯 <b>مهمة مكتملة!</b>\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 <b>المستخدم:</b> {$firstname} (@{$username})\n";
        $message .= "🔢 <b>المعرف:</b> <code>{$user_id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>المرحلة:</b> <code>#{$task_stage}</code>\n";
        $message .= "🎁 <b>المكافأة:</b> <code>{$reward_value} {$reward_text}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s');

        return sendTelegramNotification($config['admin_id'], $message);
    } catch (Exception $e) {
        error_log("Error in notifyTaskCompleted: " . $e->getMessage());
        return false;
    }
}

/**
 * إشعار يومي بالإحصائيات
 */
function sendDailyStats() {
    global $config, $db;

    try {
        $today = date('Y-m-d');

        // عدد المستخدمين الجدد اليوم
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE date(join_date) = ?");
        $stmt->execute([$today]);
        $new_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // إجمالي المستخدمين
        $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

        // طلبات السحب المعلقة
        $pending = $db->query("SELECT COUNT(*) FROM transactions WHERE type='withdraw' AND status='pending'")->fetchColumn();

        // إجمالي الأرصدة
        $total_balance = $db->query("SELECT SUM(balance) FROM users")->fetchColumn();

        // المعاملات اليوم
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE date(created_at) = ?");
        $stmt->execute([$today]);
        $trans_count = $stmt->fetchColumn();

        // السحوبات المكتملة اليوم
        $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE date(created_at) = ? AND type='withdraw' AND status='approved'");
        $stmt->execute([$today]);
        $withdrawals = $stmt->fetch(PDO::FETCH_ASSOC);

        $message = "📊 <b>التقرير اليومي</b>\n";
        $message .= "📅 <b>التاريخ:</b> " . date('Y-m-d') . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📈 <b>النمو:</b>\n";
        $message .= "👥 <b>عدد الإحالات:</b> <code>{$referral_count}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "🌟 مستخدم نشط ومؤثر!";

        return sendTelegramNotification($config['admin_id'], $message, 'HTML', true);
    } catch (Exception $e) {
        error_log("Error in notifyReferralMilestone: " . $e->getMessage());
        return false;
    }
}

/**
 * إشعار عند وصول الرصيد الإجمالي لحد معين
 */
function notifyBalanceThreshold($threshold) {
    global $config, $db;

    try {
        $total_balance = $db->query("SELECT SUM(balance) FROM users")->fetchColumn();

        if ($total_balance >= $threshold) {
            $message = "💎 <b>إنجاز مالي جديد!</b>\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💰 <b>إجمالي الأرصدة:</b> <code>" . number_format($total_balance, 2) . " USDT</code>\n";
            $message .= "🎯 <b>الهدف:</b> <code>" . number_format($threshold, 2) . " USDT</code>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
            $message .= "🚀 النظام ينمو بشكل ممتاز!";

            return sendTelegramNotification($config['admin_id'], $message);
        }
    } catch (Exception $e) {
        error_log("Error in notifyBalanceThreshold: " . $e->getMessage());
        return false;
    }
}

/**
 * دوال مساعدة
 */
function getTotalUsers() {
    global $db;
    try {
        return $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getPendingWithdrawals() {
    global $db;
    try {
        return $db->query("SELECT COUNT(*) FROM transactions WHERE type='withdraw' AND status='pending'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * فحص ومراقبة النشاط المشبوه
 */
function checkSuspiciousActivity($user_id) {
    global $db;

    try {
        // فحص عدد المعاملات في آخر ساعة
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM transactions 
            WHERE telegram_id = ? 
            AND datetime(created_at) > datetime('now', '-1 hour')
        ");
        $stmt->execute([$user_id]);
        $recent_trans = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($recent_trans > 10) {
            notifySuspiciousActivity(
                $user_id, 
                "معاملات متكررة", 
                "قام المستخدم بـ {$recent_trans} معاملة في آخر ساعة"
            );
        }

        // فحص محاولات السحب المتكررة
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM transactions 
            WHERE telegram_id = ? 
            AND type = 'withdraw'
            AND datetime(created_at) > datetime('now', '-24 hour')
        ");
        $stmt->execute([$user_id]);
        $withdrawals_24h = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($withdrawals_24h > 5) {
            notifySuspiciousActivity(
                $user_id, 
                "محاولات سحب متكررة", 
                "قام المستخدم بـ {$withdrawals_24h} محاولة سحب في آخر 24 ساعة"
            );
        }

    } catch (Exception $e) {
        error_log("Error in checkSuspiciousActivity: " . $e->getMessage());
    }
}

/**
 * إرسال تقرير أسبوعي شامل
 */
function sendWeeklyReport() {
    global $config, $db;

    try {
        $week_ago = date('Y-m-d', strtotime('-7 days'));

        // إحصائيات الأسبوع
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE date(join_date) >= ?");
        $stmt->execute([$week_ago]);
        $new_users_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM transactions WHERE type='withdraw' AND status='approved' AND date(created_at) >= ?");
        $stmt->execute([$week_ago]);
        $withdrawals_week = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $total_balance = $db->query("SELECT SUM(balance) FROM users")->fetchColumn();
        $active_miners = $db->query("SELECT COUNT(*) FROM users WHERE mining_start_time IS NOT NULL")->fetchColumn();

        $message = "📈 <b>التقرير الأسبوعي</b>\n";
        $message .= "📅 <b>الفترة:</b> " . $week_ago . " - " . date('Y-m-d') . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👥 <b>المستخدمين:</b>\n";
        $message .= "   • جدد هذا الأسبوع: <b>{$new_users_week}</b>\n";
        $message .= "   • الإجمالي: <b>{$total_users}</b>\n";
        $message .= "   • نشطين في التعدين: <b>{$active_miners}</b>\n\n";

        $message .= "💰 <b>المالية:</b>\n";
        $message .= "   • إجمالي الأرصدة: <b>" . number_format($total_balance, 2) . " USDT</b>\n";
        $message .= "   • سحوبات الأسبوع: <b>{$withdrawals_week['count']}</b>\n";
        $message .= "   • قيمة السحوبات: <b>" . number_format($withdrawals_week['total'], 2) . " USDT</b>\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>متوسط النمو اليومي:</b> <b>" . round($new_users_week / 7, 1) . "</b> مستخدم\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ استمر في النمو الممتاز! 🚀";

        return sendTelegramNotification($config['admin_id'], $message);
    } catch (Exception $e) {
        error_log("Error in sendWeeklyReport: " . $e->getMessage());
        return false;
    }
}

/**
 * تنبيه عند انخفاض الرصيد العام
 */
function notifyLowSystemBalance($threshold = 1000) {
    global $config, $db;

    try {
        $total_balance = $db->query("SELECT SUM(balance) FROM users")->fetchColumn();

        if ($total_balance < $threshold) {
            $message = "⚠️ <b>تحذير: انخفاض الرصيد الإجمالي!</b>\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💰 <b>الرصيد الحالي:</b> <code>" . number_format($total_balance, 2) . " USDT</code>\n";
            $message .= "⚠️ <b>الحد الأدنى:</b> <code>" . number_format($threshold, 2) . " USDT</code>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
            $message .= "📢 يرجى المراجعة والتأكد من السيولة المالية!";

            return sendTelegramNotification($config['admin_id'], $message);
        }
    } catch (Exception $e) {
        error_log("Error in notifyLowSystemBalance: " . $e->getMessage());
        return false;
    }
}

// ============================================
// واجهة CLI للإشعارات
// ============================================
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? '';

    switch($command) {
        case 'daily':
            echo "📊 Sending daily stats...\n";
            if (sendDailyStats()) {
                echo "✅ Daily stats sent successfully\n";
            } else {
                echo "❌ Failed to send daily stats\n";
            }
            break;

        case 'weekly':
            echo "📈 Sending weekly report...\n";
            if (sendWeeklyReport()) {
                echo "✅ Weekly report sent successfully\n";
            } else {
                echo "❌ Failed to send weekly report\n";
            }
            break;

        case 'test':
            echo "🧪 Sending test notification...\n";
            if (sendTelegramNotification($config['admin_id'], "✅ <b>نظام الإشعارات يعمل بشكل صحيح!</b>\n\n⏰ الوقت: " . date('Y-m-d H:i:s'))) {
                echo "✅ Test notification sent successfully\n";
            } else {
                echo "❌ Failed to send test notification\n";
            }
            break;

        case 'check-pending':
            echo "🔍 Checking pending withdrawals...\n";
            $pending = getPendingWithdrawals();
            echo "⏳ Pending withdrawals: {$pending}\n";
            if ($pending > 0) {
                $message = "⚠️ <b>لديك {$pending} طلب سحب معلق!</b>\n\n";
                $message .= "يرجى المراجعة من لوحة التحكم.";
                sendTelegramNotification($config['admin_id'], $message);
                echo "✅ Alert sent to admin\n";
            }
            break;

        case 'help':
        default:
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "  نظام الإشعارات - Admin Panel\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            echo "الاستخدام:\n";
            echo "  php notifications.php [command]\n\n";
            echo "الأوامر المتاحة:\n";
            echo "  daily          - إرسال التقرير اليومي\n";
            echo "  weekly         - إرسال التقرير الأسبوعي\n";
            echo "  test           - اختبار النظام\n";
            echo "  check-pending  - فحص الطلبات المعلقة\n";
            echo "  help           - عرض هذه المساعدة\n\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "إعداد Cron Jobs:\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "# التقرير اليومي (9 صباحاً)\n";
            echo "0 9 * * * /usr/bin/php /path/to/notifications.php daily\n\n";
            echo "# التقرير الأسبوعي (الأحد 10 صباحاً)\n";
            echo "0 10 * * 0 /usr/bin/php /path/to/notifications.php weekly\n\n";
            echo "# فحص الطلبات المعلقة (كل ساعة)\n";
            echo "0 * * * * /usr/bin/php /path/to/notifications.php check-pending\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            break;
    }
}مستخدمين جدد اليوم: <b>{$new_users}</b>\n";
        $message .= "👨‍👩‍👧‍👦 إجمالي المستخدمين: <b>{$total_users}</b>\n\n";

        $message .= "💰 <b>الأموال:</b>\n";
        $message .= "💵 إجمالي الأرصدة: <b>" . number_format($total_balance, 2) . " USDT</b>\n";
        $message .= "💸 سحوبات اليوم: <b>{$withdrawals['count']}</b> (<code>" . number_format($withdrawals['total'], 2) . " USDT</code>)\n";
        $message .= "⏳ طلبات معلقة: <b>{$pending}</b>\n\n";

        $message .= "📊 <b>النشاط:</b>\n";
        $message .= "🔄 معاملات اليوم: <b>{$trans_count}</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✅ <b>كل شيء يسير بشكل جيد!</b>";

        return sendTelegramNotification($config['admin_id'], $message);
    } catch (Exception $e) {
        error_log("Error in sendDailyStats: " . $e->getMessage());
        return false;
    }
}

/**
 * تنبيه نشاط مشبوه
 */
function notifySuspiciousActivity($user_id, $activity_type, $details) {
    global $config, $db;

    try {
        $stmt = $db->prepare("SELECT username, firstname FROM users WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $username = $user['username'] ?? 'Unknown';
        $firstname = $user['firstname'] ?? 'Unknown';

        $message = "⚠️ <b>تحذير: نشاط مشبوه!</b>\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 <b>المستخدم:</b> {$firstname} (@{$username})\n";
        $message .= "🔢 <b>المعرف:</b> <code>{$user_id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🔍 <b>نوع النشاط:</b> <code>{$activity_type}</code>\n";
        $message .= "📝 <b>التفاصيل:</b> {$details}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "⚡ <b>يرجى المراجعة فوراً!</b>";

        return sendTelegramNotification($config['admin_id'], $message);
    } catch (Exception $e) {
        error_log("Error in notifySuspiciousActivity: " . $e->getMessage());
        return false;
    }
}

/**
 * إشعار عند الموافقة على السحب
 */
function sendWithdrawApprovalNotification($user_id, $amount) {
    $message = "✅ <b>تمت الموافقة على طلب السحب</b>\n\n";
    $message .= "💰 <b>المبلغ:</b> <code>{$amount} USDT</code>\n";
    $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "سيتم تحويل المبلغ خلال 24-48 ساعة.";

    return sendTelegramNotification($user_id, $message);
}

/**
 * إشعار عند رفض السحب
 */
function sendWithdrawRejectionNotification($user_id, $amount, $reason = '') {
    $message = "❌ <b>تم رفض طلب السحب</b>\n\n";
    $message .= "💰 <b>المبلغ:</b> <code>{$amount} USDT</code>\n";

    if ($reason) {
        $message .= "📝 <b>السبب:</b> {$reason}\n";
    }

    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "✅ تم إرجاع المبلغ إلى رصيدك.\n";
    $message .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s');

    return sendTelegramNotification($user_id, $message);
}

/**
 * إشعار عند تحقيق هدف إحالات
 */
function notifyReferralMilestone($user_id, $referral_count) {
    global $config, $db;

    try {
        $stmt = $db->prepare("SELECT username, firstname FROM users WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $username = $user['username'] ?? 'Unknown';
        $firstname = $user['firstname'] ?? 'Unknown';

        $message = "🎉 <b>إنجاز جديد في الإحالات!</b>\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 <b>المستخدم:</b> {$firstname} (@{$username})\n";
        $message .= "🔢 <b>المعرف:</b> <code>{$user_id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👥