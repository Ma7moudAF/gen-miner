<?php
// ========================================
// Mining Rigs API Functions
// ========================================

/**
 * الحصول على جميع الأجهزة المتاحة
 */
function getRigs() {
    global $db;

    $stmt = $db->query("
        SELECT * FROM mining_rigs 
        WHERE is_active = 1 
        ORDER BY price ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * شراء جهاز تعدين جديد
 */
function purchaseRig($telegram_id, $rig_id) {
    global $db, $config;

    try {
        $db->beginTransaction();

        // جلب بيانات الجهاز
        $stmt = $db->prepare("SELECT * FROM mining_rigs WHERE id = ? AND is_active = 1");
        $stmt->execute([$rig_id]);
        $rig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rig) {
            $db->rollBack();
            return ['success' => false, 'message' => '❌ الجهاز غير موجود'];
        }

        // جلب بيانات المستخدم
        $user = getUser($telegram_id);
        if (!$user) {
            $db->rollBack();
            return ['success' => false, 'message' => '❌ المستخدم غير موجود'];
        }

        // التحقق من الرصيد
        if ($user['balance'] < $rig['price']) {
            $db->rollBack();
            return ['success' => false, 'message' => '⚠️ رصيدك غير كافٍ'];
        }

        // خصم المبلغ
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE telegram_id = ?");
        $stmt->execute([$rig['price'], $telegram_id]);

        // حساب تاريخ الانتهاء
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$rig['duration_days']} days"));

        // إضافة الجهاز للمستخدم
        $stmt = $db->prepare("
            INSERT INTO user_rigs (telegram_id, rig_id, expiry_date, purchase_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$telegram_id, $rig_id, $expiry_date, $rig['price']]);

        $user_rig_id = $db->lastInsertId();

        // توزيع عمولات الإحالة
        distributeRigCommissions($telegram_id, $user_rig_id, $rig['price']);

        $db->commit();

        return [
            'success' => true,
            'message' => "🎉 تم شراء {$rig['name_ar']} بنجاح!",
            'user_rig_id' => $user_rig_id,
            'expiry_date' => $expiry_date
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => '❌ حدث خطأ: ' . $e->getMessage()];
    }
}

/**
 * توزيع عمولات الإحالة عند شراء جهاز
 */
function distributeRigCommissions($telegram_id, $user_rig_id, $purchase_price) {
    global $db, $config;

    try {
        // جلب بيانات المُحيل
        $user = getUser($telegram_id);
        if (!$user) return;

        // نسب العمولات (يمكن تخصيصها من config)
        $commission_rates = [
            1 => 0.05, // 5% للمستوى الأول
            2 => 0.03, // 3% للمستوى الثاني
            3 => 0.02  // 2% للمستوى الثالث
        ];

        $levels = [
            1 => $user['ref_lvl1'],
            2 => $user['ref_lvl2'],
            3 => $user['ref_lvl3']
        ];

        foreach ($levels as $level => $referrer_id) {
            if (!$referrer_id) continue;

            $commission = $purchase_price * $commission_rates[$level];

            // إضافة العمولة للرصيد
            $stmt = $db->prepare("
                UPDATE users 
                SET balance = balance + ?, referral_balance = referral_balance + ?
                WHERE telegram_id = ?
            ");
            $stmt->execute([$commission, $commission, $referrer_id]);

            // تسجيل العمولة
            $stmt = $db->prepare("
                INSERT INTO rig_referral_earnings (from_id, to_id, user_rig_id, level, amount)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$referrer_id, $telegram_id, $user_rig_id, $level, $commission]);
        }

    } catch (Exception $e) {
        error_log("Commission distribution error: " . $e->getMessage());
    }
}

/**
 * حساب الأرباح المعلقة (المتراكمة منذ آخر استلام)
 */
function calculatePendingEarnings($telegram_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT ur.*, mr.daily_profit, mr.name_ar
        FROM user_rigs ur
        JOIN mining_rigs mr ON ur.rig_id = mr.id
        WHERE ur.telegram_id = ? 
        AND ur.is_active = 1 
        AND ur.expiry_date > datetime('now')
    ");
    $stmt->execute([$telegram_id]);
    $rigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_pending = 0;
    $rig_details = [];

    foreach ($rigs as $rig) {
        $last_claim = strtotime($rig['last_claim']);
        $now = time();
        $hours_passed = ($now - $last_claim) / 3600;

        // حساب الأرباح بالساعة
        $hourly_rate = $rig['daily_profit'] / 24;
        $pending = $hours_passed * $hourly_rate;

        $total_pending += $pending;

        $rig_details[] = [
            'user_rig_id' => $rig['id'],
            'name' => $rig['name_ar'],
            'pending' => $pending,
            'hours_passed' => round($hours_passed, 2)
        ];
    }

    return [
        'total' => $total_pending,
        'rigs' => $rig_details
    ];
}

/**
 * استلام الأرباح المتراكمة
 */
function claimMiningEarnings($telegram_id) {
    global $db;

    try {
        $db->beginTransaction();

        // حساب الأرباح
        $earnings_data = calculatePendingEarnings($telegram_id);

        if ($earnings_data['total'] <= 0) {
            $db->rollBack();
            return ['success' => false, 'message' => '⚠️ لا توجد أرباح للاستلام'];
        }

        // إضافة الأرباح للرصيد
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
        $stmt->execute([$earnings_data['total'], $telegram_id]);

        // تحديث آخر استلام لكل جهاز
        $now = date('Y-m-d H:i:s');

        foreach ($earnings_data['rigs'] as $rig) {
            // تحديث total_earned و last_claim
            $stmt = $db->prepare("
                UPDATE user_rigs 
                SET total_earned = total_earned + ?, last_claim = ?
                WHERE id = ?
            ");
            $stmt->execute([$rig['pending'], $now, $rig['user_rig_id']]);

            // تسجيل في تاريخ الأرباح
            $stmt = $db->prepare("
                INSERT INTO earnings_history (telegram_id, user_rig_id, amount)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$telegram_id, $rig['user_rig_id'], $rig['pending']]);
        }

        $db->commit();

        return [
            'success' => true,
            'message' => '✅ تم استلام الأرباح بنجاح!',
            'amount' => $earnings_data['total'],
            'user' => getUser($telegram_id)
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => '❌ حدث خطأ: ' . $e->getMessage()];
    }
}

/**
 * الحصول على أجهزة المستخدم
 */
function getUserRigs($telegram_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT 
            ur.*,
            mr.name,
            mr.name_ar,
            mr.icon,
            mr.daily_profit,
            mr.duration_days,
            mr.price,
            CASE 
                WHEN ur.expiry_date <= datetime('now') THEN 'expired'
                WHEN ur.is_active = 0 THEN 'inactive'
                ELSE 'active'
            END as status,
            CAST((julianday(ur.expiry_date) - julianday('now')) AS INTEGER) as days_left
        FROM user_rigs ur
        JOIN mining_rigs mr ON ur.rig_id = mr.id
        WHERE ur.telegram_id = ?
        ORDER BY ur.is_active DESC, ur.purchase_date DESC
    ");
    $stmt->execute([$telegram_id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ترقية جهاز موجود
 */
function upgradeRig($telegram_id, $user_rig_id, $new_rig_id) {
    global $db;

    try {
        $db->beginTransaction();

        // جلب الجهاز الحالي
        $stmt = $db->prepare("
            SELECT ur.*, mr.price as current_price 
            FROM user_rigs ur
            JOIN mining_rigs mr ON ur.rig_id = mr.id
            WHERE ur.id = ? AND ur.telegram_id = ? AND ur.is_active = 1
        ");
        $stmt->execute([$user_rig_id, $telegram_id]);
        $current_rig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_rig) {
            $db->rollBack();
            return ['success' => false, 'message' => '❌ الجهاز غير موجود'];
        }

        // جلب الجهاز الجديد
        $stmt = $db->prepare("SELECT * FROM mining_rigs WHERE id = ? AND is_active = 1");
        $stmt->execute([$new_rig_id]);
        $new_rig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$new_rig) {
            $db->rollBack();
            return ['success' => false, 'message' => '❌ الجهاز المطلوب غير موجود'];
        }

        // حساب الفرق في السعر
        $price_diff = $new_rig['price'] - $current_rig['current_price'];

        if ($price_diff <= 0) {
            $db->rollBack();
            return ['success' => false, 'message' => '⚠️ لا يمكن الترقية لجهاز أقل'];
        }

        // التحقق من الرصيد
        $user = getUser($telegram_id);
        if ($user['balance'] < $price_diff) {
            $db->rollBack();
            return ['success' => false, 'message' => '⚠️ رصيدك غير كافٍ'];
        }

        // خصم الفرق
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE telegram_id = ?");
        $stmt->execute([$price_diff, $telegram_id]);

        // تحديث الجهاز
        $new_expiry = date('Y-m-d H:i:s', strtotime("+{$new_rig['duration_days']} days"));

        $stmt = $db->prepare("
            UPDATE user_rigs 
            SET rig_id = ?, 
                expiry_date = ?,
                purchase_price = purchase_price + ?
            WHERE id = ?
        ");
        $stmt->execute([$new_rig_id, $new_expiry, $price_diff, $user_rig_id]);

        $db->commit();

        return [
            'success' => true,
            'message' => "🎉 تمت الترقية إلى {$new_rig['name_ar']} بنجاح!",
            'price_paid' => $price_diff
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => '❌ حدث خطأ: ' . $e->getMessage()];
    }
}

/**
 * تجديد جهاز منتهي الصلاحية
 */
function renewRig($telegram_id, $user_rig_id) {
    global $db;

    try {
        $db->beginTransaction();

        // جلب الجهاز
        $stmt = $db->prepare("
            SELECT ur.*, mr.price, mr.duration_days, mr.name_ar
            FROM user_rigs ur
            JOIN mining_rigs mr ON ur.rig_id = mr.id
            WHERE ur.id = ? AND ur.telegram_id = ?
        ");
        $stmt->execute([$user_rig_id, $telegram_id]);
        $rig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rig) {
            $db->rollBack();
            return ['success' => false, 'message' => '❌ الجهاز غير موجود'];
        }

        // التحقق من الرصيد
        $user = getUser($telegram_id);
        if ($user['balance'] < $rig['price']) {
            $db->rollBack();
            return ['success' => false, 'message' => '⚠️ رصيدك غير كافٍ'];
        }

        // خصم المبلغ
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE telegram_id = ?");
        $stmt->execute([$rig['price'], $telegram_id]);

        // تجديد الجهاز
        $new_expiry = date('Y-m-d H:i:s', strtotime("+{$rig['duration_days']} days"));

        $stmt = $db->prepare("
            UPDATE user_rigs 
            SET expiry_date = ?, 
                is_active = 1,
                last_claim = datetime('now'),
                notification_sent = 0
            WHERE id = ?
        ");
        $stmt->execute([$new_expiry, $user_rig_id]);

        $db->commit();

        return [
            'success' => true,
            'message' => "🎉 تم تجديد {$rig['name_ar']} بنجاح!",
            'new_expiry' => $new_expiry
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => '❌ حدث خطأ: ' . $e->getMessage()];
    }
}

/**
 * إحصائيات التعدين للمستخدم
 */
function getMiningStats($telegram_id) {
    global $db;

    // إجمالي الاستثمار
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(purchase_price), 0) as total_invested
        FROM user_rigs
        WHERE telegram_id = ?
    ");
    $stmt->execute([$telegram_id]);
    $total_invested = $stmt->fetchColumn();

    // إجمالي الأرباح
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_earned), 0) as total_earned
        FROM user_rigs
        WHERE telegram_id = ?
    ");
    $stmt->execute([$telegram_id]);
    $total_earned = $stmt->fetchColumn();

    // الأرباح اليومية الحالية
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(mr.daily_profit), 0) as daily_profit
        FROM user_rigs ur
        JOIN mining_rigs mr ON ur.rig_id = mr.id
        WHERE ur.telegram_id = ? 
        AND ur.is_active = 1 
        AND ur.expiry_date > datetime('now')
    ");
    $stmt->execute([$telegram_id]);
    $daily_profit = $stmt->fetchColumn();

    // عدد الأجهزة النشطة
    $stmt = $db->prepare("
        SELECT COUNT(*) as active_rigs
        FROM user_rigs
        WHERE telegram_id = ? 
        AND is_active = 1 
        AND expiry_date > datetime('now')
    ");
    $stmt->execute([$telegram_id]);
    $active_rigs = $stmt->fetchColumn();

    // الأرباح المعلقة
    $pending = calculatePendingEarnings($telegram_id);

    return [
        'total_invested' => floatval($total_invested),
        'total_earned' => floatval($total_earned),
        'daily_profit' => floatval($daily_profit),
        'active_rigs' => intval($active_rigs),
        'pending_earnings' => floatval($pending['total']),
        'roi_percentage' => $total_invested > 0 ? round(($total_earned / $total_invested) * 100, 2) : 0
    ];
}

/**
 * Cron Job - تعطيل الأجهزة المنتهية
 */
function deactivateExpiredRigs() {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE user_rigs 
            SET is_active = 0 
            WHERE expiry_date <= datetime('now') 
            AND is_active = 1
        ");

        $stmt->execute();

        return [
            'success' => true,
            'deactivated' => $stmt->rowCount()
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>