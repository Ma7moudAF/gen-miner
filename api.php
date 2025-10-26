<?php
$config = include __DIR__ . '/config.php';
require_once __DIR__ . '/mining_api.php';

try {
    $db = new PDO("sqlite:" . $config['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// ========================
// 🧩 استقبال الطلب من الواجهة
// ========================
header('Content-Type: application/json; charset=utf-8');
// header('Content-Type: application/json; charset=utf-8');
// $input = json_decode(file_get_contents('php://input'), true);
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$telegram_id = intval($input['telegram_id'] ?? 0);

// ========================
// 🧠 دوال المساعدة العامة
// ========================
function getUser($telegram_id)
{
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// 🔹 تسجيل مستخدم جديد أو تحديث بياناته
// =====================================================
function registerUser($telegram_id, $firstname, $username, $referrer_id, $photo_url = null)
{
    global $db, $config;

    // تحقق إذا المستخدم موجود مسبقًا
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // استخراج المستويات
        $lvl1 = $referrer_id;
        $lvl2 = null;
        $lvl3 = null;

        if ($referrer_id) {
            $ref_stmt = $db->prepare("SELECT ref_lvl1, ref_lvl2 FROM users WHERE telegram_id = ?");
            $ref_stmt->execute([$referrer_id]);
            $ref_data = $ref_stmt->fetch(PDO::FETCH_ASSOC);
            if ($ref_data) {
                $lvl2 = $ref_data['ref_lvl1'];
                $lvl3 = $ref_data['ref_lvl2'];
            }
        }

        // إدخال المستخدم
        $insert = $db->prepare("
            INSERT INTO users (telegram_id, firstname, username, ref_lvl1, ref_lvl2, ref_lvl3, balance, referral_balance, photo_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $telegram_id,
            $firstname,
            $username,
            $lvl1,
            $lvl2,
            $lvl3,
            $config['register_balance'],
            $config['register_referral_balance'],
            $photo_url
        ]);

        // مكافأة الداعي
        if ($referrer_id) {
            $rewardbalance = $config['referral_reward_balance'];
            $rewardStmt = $db->prepare("UPDATE users SET referral_balance = referral_balance + ?, invite_count = invite_count + 1 WHERE telegram_id = ?");
            $rewardStmt->execute([$rewardbalance, $referrer_id]);
        }
    } else {
        // تحديث البيانات
        $update = $db->prepare("
            UPDATE users
            SET firstname = ?, username = ?, photo_url = ?
            WHERE telegram_id = ?
        ");
        $update->execute([$firstname, $username, $photo_url, $telegram_id]);
    }
    
}




// =====================================================
// 🔹 حساب حالة التعدين (تلقائيًا حسب الوقت)
// =====================================================
function isMiningActive($user)
{
    if (!$user['mining_start_time']) return false;

    $elapsed = time() - $user['mining_start_time'];
    return $elapsed < $user['mining_duration'];
}

// =====================================================
// 🔹 حساب عدد عملات GEN المكتسبة
// =====================================================
function calculateGeneratedGEN($user)
{
    if (!$user['mining_start_time']) return 0;

    $elapsed = min(time() - $user['mining_start_time'], $user['mining_duration']);
    $gen_per_second = $user['mining_power'] / 3600; // توليد متناسب مع القوة

    return $elapsed * $gen_per_second;
}

// =====================================================
// 🔹 استلام التعدين وتحويل GEN إلى TON
// =====================================================
function collectmining($telegram_id)
{
    global $db, $config;

    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;

    $generated = calculateGeneratedGEN($user);
    //$value_usdt = $generated * $config['gen_price']; // تحويل العملة الوهمية

    // تحديث الرصيد وإعادة تشغيل التعدين
    $update = $db->prepare("
        UPDATE users 
        SET gen_balance = gen_balance + ?, 
            mining_start_time = ?, 
            mining_duration = ?
        WHERE telegram_id = ?
    ");
    $update->execute([
        $generated,
        time(),
        $config['default_mining_duration'],
        $telegram_id
    ]);

    // تسجيل العملية
    // $log = $db->prepare("INSERT INTO transactions (telegram_id, type, amount, status) VALUES (?, 'mining', ?, 'approved')");
    // $log->execute([$telegram_id, $value_usdt]);

    return [
        'success' => true,
        'gen_collected' => $generated
    ];
}

// =====================================================
// 🔹 إنشاء طلب سحب
// =====================================================
function requestWithdraw($telegram_id, $amount)
{
    global $db;

    $stmt = $db->prepare("SELECT balance, referral_balance FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) return "❌ المستخدم غير موجود";
    if ($user['balance'] < $amount) return "❌ الرصيد غير كافٍ";

    // الحد المسموح للسحب هو ضعف رصيد الدعوات
    $max_withdrawable = $user['referral_balance'] * 2;
    if ($amount > $max_withdrawable) return "⚠️ لا يمكنك سحب أكثر من $max_withdrawable ";

    // خصم الرصيد مؤقتًا
    $deduct_bal = $db->prepare("UPDATE users SET balance = balance - ?, referral_balance = 0 WHERE telegram_id = ?");
    $deduct_bal->execute([$amount, $telegram_id]);

    $ins = $db->prepare("INSERT INTO transactions (telegram_id, type, amount, status) VALUES (?, 'withdraw', ?, 'pending')");
    $ins->execute([$telegram_id, $amount]);

    return "💸 تم إرسال طلب السحب وهو قيد المراجعة الآن.";
}




// ========================
// 🧾 تنفيذ المهمات والمكافآت
// ========================

function startTask($telegram_id, $task_id) {
    global $db, $config;

    try {
        // 1. التحقق من وجود التاسك
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_active = 1");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return ['success' => false, 'message' => '❌ المهمة غير موجودة'];
        }

        // 2. التحقق من عدم أخذ الجائزة سابقاً
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE telegram_id = ? AND task_id = ?");
        $stmt->execute([$telegram_id, $task_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '✅ لقد أكملت هذه المهمة من قبل'];
        }

        // 3. تسجيل محاولة جديدة
        $started_at = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO task_attempts (telegram_id, task_id, started_at, status) 
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$telegram_id, $task_id, $started_at]);

        $attempt_id = $db->lastInsertId();

        return [
            'success' => true,
            'message' => '🚀 تم بدء المهمة',
            'attempt_id' => $attempt_id,
            'started_at' => $started_at,
            'task_url' => $task['task_url'],
            'task_type' => $task['task_type'],
            'min_duration' => $task['min_duration'],
            'button_wait_time' => $task['button_wait_time'],
            'has_password' => !empty($task['password'])
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => '❌ ' . $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════
// دالة التحقق من اشتراك القناة
// ═══════════════════════════════════════════════════════════

function checkChannelSubscription($telegram_id, $channel_username) {
    global $config;

    if (empty($channel_username)) return false;

    $bot_token = $config['bot_token'];
    $url = "https://api.telegram.org/bot{$bot_token}/getChatMember";

    $data = [
        'chat_id' => '@' . str_replace('@', '', $channel_username),
        'user_id' => $telegram_id
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Channel check failed for @{$channel_username}: HTTP {$httpCode}");
        return false;
    }

    $result = json_decode($response, true);

    if (!$result['ok']) {
        error_log("Channel check error: " . json_encode($result));
        return false;
    }

    $status = $result['result']['status'] ?? 'left';

    // الحالات المقبولة: member, administrator, creator
    return in_array($status, ['member', 'administrator', 'creator']);
}

// ═══════════════════════════════════════════════════════════
// دالة التحقق من المهمة وإعطاء الجائزة
// ═══════════════════════════════════════════════════════════

function verifyTask($telegram_id, $task_id, $password = null) {
    global $db, $config;

    try {
        // 1. جلب التاسك
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_active = 1");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return ['success' => false, 'message' => '❌ المهمة غير موجودة'];
        }

        // 2. التحقق من عدم أخذ الجائزة سابقاً
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE telegram_id = ? AND task_id = ?");
        $stmt->execute([$telegram_id, $task_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '✅ لقد أكملت هذه المهمة من قبل'];
        }

        // 3. جلب آخر محاولة
        $stmt = $db->prepare("
            SELECT * FROM task_attempts 
            WHERE telegram_id = ? AND task_id = ? AND status = 'pending'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$telegram_id, $task_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            return ['success' => false, 'message' => '⚠️ يجب بدء المهمة أولاً'];
        }

        // 4. حساب المدة
        $started = new DateTime($attempt['started_at']);
        $now = new DateTime();
        $duration = $now->getTimestamp() - $started->getTimestamp();

        // 5. التحقق حسب نوع التاسك
        $task_type = $task['task_type'];

        switch ($task_type) {
            case 'channel':
            case 'group':
                // التحقق من الاشتراك
                $isSubscribed = checkChannelSubscription($telegram_id, $task['channel_username']);

                if (!$isSubscribed) {
                    return ['success' => false, 'message' => '⚠️ يجب الاشتراك في القناة أولاً'];
                }
                break;

            case 'video':
                // التحقق من الوقت
                $min_duration = (int)$task['min_duration'];

                if ($duration < $min_duration) {
                    $remaining = $min_duration - $duration;
                    return [
                        'success' => false, 
                        'message' => "⏱️ يجب إكمال المشاهدة (باقي {$remaining} ثانية)",
                        'remaining' => $remaining
                    ];
                }

                // التحقق من كلمة السر
                if (!empty($task['password'])) {
                    if (empty($password)) {
                        return ['success' => false, 'message' => '🔑 يجب إدخال كلمة السر', 'requires_password' => true];
                    }

                    // زيادة عدد المحاولات
                    $password_attempts = (int)$attempt['password_attempts'] + 1;

                    if ($password_attempts > 3) {
                        // تحديث الحالة لـ failed
                        $db->prepare("UPDATE task_attempts SET status = 'failed' WHERE id = ?")
                           ->execute([$attempt['id']]);

                        return ['success' => false, 'message' => '❌ تم تجاوز عدد المحاولات المسموحة'];
                    }

                    // تحديث عدد المحاولات
                    $db->prepare("UPDATE task_attempts SET password_attempts = ? WHERE id = ?")
                       ->execute([$password_attempts, $attempt['id']]);

                    // التحقق من كلمة السر
                    if (trim($password) !== trim($task['password'])) {
                        $remaining_attempts = 3 - $password_attempts;
                        return [
                            'success' => false, 
                            'message' => "❌ كلمة السر خاطئة (باقي {$remaining_attempts} محاولات)",
                            'requires_password' => true,
                            'remaining_attempts' => $remaining_attempts
                        ];
                    }
                }
                break;

            case 'bot':
                // للبوتات: نفترض أنه اشترك
                // يمكن إضافة تحقق إضافي إذا لزم الأمر
                break;

            // case 'invite':
            //     // التحقق من عدد الدعوات
            //     $user = getUser($telegram_id);
            //     $inviteCount = (int)$user['invite_count'];
            //     $target = (int)$task['target'];

            //     if ($inviteCount < $target) {
            //         return ['success' => false, 'message' => "⚠️ تحتاج إلى {$target} دعوات (لديك {$inviteCount})"];
            //     }
            //     break;

            case 'deposit':
                // التحقق من الإيداع
                $stmt = $db->prepare("
                    SELECT SUM(amount) as total 
                    FROM transactions 
                    WHERE telegram_id = ? AND type = 'deposit' AND status = 'approved'
                ");
                $stmt->execute([$telegram_id]);
                $totalDeposit = (float)$stmt->fetchColumn();
                $target = (float)$task['target'];

                if ($totalDeposit < $target) {
                    return ['success' => false, 'message' => "⚠️ تحتاج إلى إيداع {$target} USDT"];
                }
                break;
        }

        // 6. إعطاء الجائزة
        $user = getUser($telegram_id);
        if (!$user) {
            return ['success' => false, 'message' => '❌ المستخدم غير موجود'];
        }

        $db->beginTransaction();

        try {
            $rewardType = $task['reward_type'];
            $rewardValue = (float)$task['reward_value'];

            if ($rewardType === 'power') {
                $newPower = $user['mining_power'] + $rewardValue;
                $db->prepare("UPDATE users SET mining_power = ? WHERE telegram_id = ?")
                   ->execute([$newPower, $telegram_id]);
                $rewardText = "⚡ +{$rewardValue} Power";
            } elseif ($rewardType === 'balance') {
                $newBalance = $user['balance'] + $rewardValue;
                $db->prepare("UPDATE users SET balance = ? WHERE telegram_id = ?")
                   ->execute([$newBalance, $telegram_id]);
                $rewardText = "💰 +{$rewardValue} USDT";
            }

            // تسجيل الاكتمال
            $db->prepare("INSERT INTO user_tasks (telegram_id, task_id) VALUES (?, ?)")
               ->execute([$telegram_id, $task_id]);

            // تحديث المحاولة
            $completed_at = date('Y-m-d H:i:s');
            $db->prepare("UPDATE task_attempts SET completed_at = ?, duration = ?, status = 'completed' WHERE id = ?")
               ->execute([$completed_at, $duration, $attempt['id']]);

            $db->commit();

            return [
                'success' => true,
                'message' => "🎉 مبروك!\n{$rewardText}",
                'user' => getUser($telegram_id)
            ];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => '❌ ' . $e->getMessage()];
    }
}

function getTasks($telegram_id) {
    global $db;
    // جلب المهام التي حصل عليها المستخدم
    $stmt = $db->prepare("SELECT task_id FROM user_tasks WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $claimedTaskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // جلب جميع المهام النشطة
    $allTasks = $db->query("
        SELECT * FROM tasks 
        WHERE is_active = 1 
        ORDER BY task_type ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // تصنيف المهام حسب النوع
    $tasksByType = [
        'invite' => [],
        'join' => [],
        'deposit' => []
    ];

    foreach ($allTasks as $task) {
        $tasksByType[$task['task_type']][] = $task;
    }

    $availableTasks = [];

    // معالجة كل نوع مهام
    foreach ($tasksByType as $type => $tasks) {
        $limits = [
            'invite' => 3,   // عرض أول 3 مهام دعوات
            'join' => 10,    // عرض أول 10 مهام انضمام
            'deposit' => 1   // عرض مهمة إيداع واحدة
        ];

        $limit = $limits[$type] ?? 999;
        $count = 0;

        foreach ($tasks as $task) {
            // تخطي المهام المكتملة
            if (in_array($task['id'], $claimedTaskIds)) {
                continue;
            }

            // إضافة المهمة للمتاحة
            $availableTasks[] = $task;
            $count++;

            // إيقاف عند الوصول للحد المسموح
            if ($count >= $limit) {
                break;
            }
        }
    }

    return $availableTasks;
}

function claimTaskReward($telegram_id, $task_id) {
    global $db;
    // 1. جلب المهمة
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_active = 1");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        return ['success' => false, 'message' => '❌ المهمة غير موجودة أو غير نشطة'];
    }
    // 2. التحقق من عدم استلام الجائزة سابقاً
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE telegram_id = ? AND task_id = ?");
    $stmt->execute([$telegram_id, $task_id]);

    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => '⚠️ لقد حصلت على هذه الجائزة من قبل!'];
    }

    // 3. جلب بيانات المستخدم
    $user = getUser($telegram_id);
    if (!$user) {
        return ['success' => false, 'message' => '❌ المستخدم غير موجود'];
    }

    // 4. التحقق من الشرط حسب نوع المهمة
    $taskType = $task['task_type'];
    $target = (int)$task['target'];
    $isCompleted = false;

    switch ($taskType) {
        case 'invite':
            $inviteCount = (int)$user['invite_count'];
            $isCompleted = $inviteCount >= $target;
            if (!$isCompleted) {
                return ['success' => false, 'message' => "⚠️ تحتاج إلى {$target} دعوات. لديك {$inviteCount}"];
            }
            break;

        case 'join':
            // هنا تحقق من انضمام المستخدم للقناة (يمكن تطويره لاحقاً)
            $isCompleted = true; // مؤقتاً
            break;

        case 'deposit':
            // التحقق من الإيداع (يمكن ربطه بجدول transactions)
            $stmt = $db->prepare("
                SELECT SUM(amount) as total 
                FROM transactions 
                WHERE telegram_id = ? AND type = 'deposit' AND status = 'approved'
            ");
            $stmt->execute([$telegram_id]);
            $totalDeposit = (float)$stmt->fetchColumn();
            $isCompleted = $totalDeposit >= $target;
            if (!$isCompleted) {
                return ['success' => false, 'message' => "⚠️ تحتاج إلى إيداع {$target} USDT"];
            }
            break;

        default:
            return ['success' => false, 'message' => '❌ نوع مهمة غير معروف'];
    }

    // 5. إعطاء المكافأة
    $rewardType = $task['reward_type'];
    $rewardValue = (float)$task['reward_value'];

    try {
        $db->beginTransaction();

        if ($rewardType === 'power') {
            $newPower = $user['mining_power'] + $rewardValue;
            $db->prepare("UPDATE users SET mining_power = ? WHERE telegram_id = ?")
               ->execute([$newPower, $telegram_id]);
            $rewardText = "⚡ +{$rewardValue} Power";
        } elseif ($rewardType === 'balance') {
            $newBalance = $user['balance'] + $rewardValue;
            $db->prepare("UPDATE users SET balance = ? WHERE telegram_id = ?")
               ->execute([$newBalance, $telegram_id]);
            $rewardText = "💰 +{$rewardValue} USDT";
        }

        // 6. تسجيل استلام الجائزة
        $db->prepare("INSERT INTO user_tasks (telegram_id, task_id) VALUES (?, ?)")
           ->execute([$telegram_id, $task_id]);

        $db->commit();

        return [
            'success' => true,
            'message' => "🎉 {$task['description']}\n{$rewardText}",
            'user' => getUser($telegram_id)
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => '❌ حدث خطأ: ' . $e->getMessage()];
    }
}


// =====================================================
// 🔹 تحديث عدد الدعوات
// =====================================================
function addReferral($inviter_id, $new_user_id)
{
    global $db;

    $update = $db->prepare("UPDATE users SET invite_count = invite_count + 1 WHERE telegram_id = ?");
    $update->execute([$inviter_id]);

    // توزيع الأرباح للمستويات
    $levels = [
        1 => $inviter_id
    ];

    // استخراج المستويات العليا
    $stmt = $db->prepare("SELECT ref_lvl1, ref_lvl2 FROM users WHERE telegram_id = ?");
    $stmt->execute([$inviter_id]);
    $ref = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ref) {
        if ($ref['ref_lvl1']) $levels[2] = $ref['ref_lvl1'];
        if ($ref['ref_lvl2']) $levels[3] = $ref['ref_lvl2'];
    }

    // توزيع المكافآت حسب المستويات
    $bonus = [
        1 => 0.5,
        2 => 0.3,
        3 => 0.2
    ];

    foreach ($levels as $level => $uid) {
        $stmt = $db->prepare("UPDATE users SET referral_balance = referral_balance + ? WHERE telegram_id = ?");
        $stmt->execute([$bonus[$level], $uid]);

        $ins = $db->prepare("INSERT INTO referral_earnings (from_id, to_id, level, amount) VALUES (?, ?, ?, ?)");
        $ins->execute([$uid, $new_user_id, $level, $bonus[$level]]);
    }
}


// ====================================
// 💰 تسجيل إيداع USDT
// ====================================
function recordDeposit($telegram_id, $amount, $currency, $transaction_hash, $wallet_address) {
    global $db;

    try {
        // التحقق من عدم تكرار نفس المعاملة
        $check = $db->prepare("SELECT COUNT(*) FROM transactions WHERE transaction_hash = ?");
        $check->execute([$transaction_hash]);

        if ($check->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'هذه المعاملة مسجلة مسبقاً'];
        }

        // إضافة المعاملة كـ pending
        $stmt = $db->prepare("
            INSERT INTO transactions 
            (telegram_id, type, amount, currency, transaction_hash, wallet_address, status, created_at)
            VALUES (?, 'deposit', ?, ?, ?, ?, 'pending', datetime('now'))
        ");

        $stmt->execute([
            $telegram_id,
            $amount,
            $currency,
            $transaction_hash,
            $wallet_address
        ]);

        $transaction_id = $db->lastInsertId();

        // إرسال إشعار للأدمن
        notifyAdminNewDeposit($telegram_id, $amount, $currency, $transaction_hash);

        return [
            'success' => true,
            'message' => 'تم تسجيل الإيداع وسيتم مراجعته',
            'transaction_id' => $transaction_id
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ في التسجيل: ' . $e->getMessage()];
    }
}

// ====================================
// 📤 طلب سحب USDT
// ====================================
function requestWithdrawUSDT($telegram_id, $amount, $currency, $wallet_address) {
    global $db;

    try {
        // جلب بيانات المستخدم
        $user = getUser($telegram_id);
        if (!$user) {
            return ['success' => false, 'message' => 'المستخدم غير موجود'];
        }
        // الحد الأدنى للسحب
        if ($amount < 5) {
            return ['success' => false, 'message' => 'الحد الأدنى للسحب 5 USDT'];
        }
        // التحقق من الرصيد
        if ($user['balance'] < $amount) {
            return ['success' => false, 'message' => 'الرصيد غير كافٍ'];
        }

        // حساب الحد المسموح للسحب
        $balance = floatval($user['balance'] ?? 0);
        $max_withdrawable = $balance * 0.95;

        if ($amount > $max_withdrawable) {
            return [
                'success' => false, 
                'message' => "لا يمكنك سحب أكثر من {$max_withdrawable} USDT"
            ];
        }

       

        // التحقق من وجود محفظة
        if (empty($wallet_address)) {
            return ['success' => false, 'message' => 'يجب ربط المحفظة أولاً'];
        }

        $db->beginTransaction();

        try {
            // خصم المبلغ من الرصيد
            $stmt = $db->prepare("
                UPDATE users 
                SET balance = balance - ?, 
                    referral_balance = 0 
                WHERE telegram_id = ?
            ");
            $stmt->execute([$amount, $telegram_id]);

            // تسجيل طلب السحب
            $stmt = $db->prepare("
                INSERT INTO transactions 
                (telegram_id, type, amount, currency, wallet_address, status, created_at)
                VALUES (?, 'withdraw', ?, ?, ?, 'pending', datetime('now'))
            ");
            $stmt->execute([$telegram_id, $amount, $currency, $wallet_address]);

            $transaction_id = $db->lastInsertId();

            $db->commit();

            // إرسال إشعار للأدمن
            notifyAdminNewWithdraw($telegram_id, $amount, $currency, $wallet_address);

            return [
                'success' => true,
                'message' => 'تم إرسال طلب السحب وسيتم معالجته خلال 24 ساعة',
                'transaction_id' => $transaction_id
            ];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
    }
}

// ====================================
// 💾 حفظ عنوان المحفظة
// ====================================
function saveWalletAddress($telegram_id, $wallet_address, $wallet_type = 'USDT') {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET wallet_address = ?,
                wallet_type = ?,
                wallet_connected_at = datetime('now')
            WHERE telegram_id = ?
        ");

        $stmt->execute([$wallet_address, $wallet_type, $telegram_id]);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ====================================
// 📊 جلب سجل المعاملات
// ====================================
function getTransactionHistory($telegram_id, $limit = 20) {
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT 
                id,
                type,
                amount,
                currency,
                status,
                transaction_hash,
                created_at,
                updated_at
            FROM transactions
            WHERE telegram_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$telegram_id, $limit]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'transactions' => $transactions
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}



// ====================================
// 📨 إرسال رسالة للدعم الفني
// ====================================
function sendSupportMessage($telegram_id, $message) {
    global $db;

    try {
        $stmt = $db->prepare("
            INSERT INTO support_messages 
            (telegram_id, message, is_admin, created_at)
            VALUES (?, ?, 0, datetime('now'))
        ");

        $stmt->execute([$telegram_id, $message]);

        // إرسال إشعار للأدمن
        notifyAdminNewSupportMessage($telegram_id, $message);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ====================================
// 📥 جلب رسائل الدعم
// ====================================
function getSupportMessages($telegram_id) {
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT 
                id,
                message,
                is_admin,
                created_at
            FROM support_messages
            WHERE telegram_id = ?
            ORDER BY created_at ASC
        ");

        $stmt->execute([$telegram_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'messages' => $messages
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ====================================
// 🔔 دوال الإشعارات
// ====================================
function notifyAdminNewDeposit($telegram_id, $amount, $currency, $hash) {
    global $config;

    $user = getUser($telegram_id);
    $message = "🔔 إيداع جديد\n\n";
    $message .= "👤 المستخدم: {$user['firstname']} (@{$user['username']})\n";
    $message .= "💰 المبلغ: {$amount} {$currency}\n";
    $message .= "🔗 Hash: {$hash}\n\n";
    $message .= "يرجى المراجعة والتأكيد";

    sendTelegramMessage($config['admin_chat_id'], $message);
}

function notifyAdminNewWithdraw($telegram_id, $amount, $currency, $wallet) {
    global $config;

    $user = getUser($telegram_id);
    $message = "📤 طلب سحب جديد\n\n";
    $message .= "👤 المستخدم: {$user['firstname']} (@{$user['username'] || ''} )\n";
    $message .= "💰 المبلغ: {$amount} {$currency}\n";
    $message .= "📍 المحفظة: {$wallet}\n\n";
    $message .= "يرجى معالجة الطلب";

    sendTelegramMessage($config['admin_chat_id'], $message);
}

function notifyUserDepositApproved($telegram_id, $amount) {
    $message = "✅ تم تأكيد إيداعك\n\n";
    $message .= "تم إضافة {$amount} USDT إلى رصيدك";

    sendTelegramMessage($telegram_id, $message);
}

function notifyUserTransactionRejected($telegram_id, $type, $reason) {
    $type_text = $type === 'deposit' ? 'الإيداع' : 'السحب';
    $message = "❌ تم رفض طلب {$type_text}\n\n";

    if ($reason) {
        $message .= "السبب: {$reason}\n\n";
    }

    $message .= "للمزيد من المعلومات، تواصل مع الدعم";

    sendTelegramMessage($telegram_id, $message);
}

function notifyAdminNewSupportMessage($telegram_id, $message) {
    global $config;

    $user = getUser($telegram_id);
    $text = "💬 رسالة دعم جديدة\n\n";
    $text .= "👤 من: {$user['firstname']} (@{$user['username']})\n";
    $text .= "📝 الرسالة: {$message}";

    sendTelegramMessage($config['admin_chat_id'], $text);
}

function sendTelegramMessage($chat_id, $message) {
    global $config;

    $url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_exec($ch);
    curl_close($ch);
}




// ========================
// ⚡ نظام الأوامر (Actions)
// ========================
switch ($action) {
    case 'register':
        if (!$telegram_id) exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id']));
        $firstname = $input['firstname'] ?? '';
        $username = $input['username'] ?? '';
        $referrer_id = $input['referrer_id'] ?? null;
        $photo_url = $input['photo_url'] ?? null;
        registerUser($telegram_id, $firstname, $username, $referrer_id, $photo_url);
        echo json_encode(['success' => true, 'user_id' =>$telegram_id, 'firstname' => $firstname, 'username' => $username, 'referrer_id' => $referrer_id, 'photo_url' => $photo_url]);
        break;

    case 'getUser':
    if (!$telegram_id) exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id']));
        echo json_encode(['success' => true, 'user' =>getUser($telegram_id) ,'bot_username' => $config['YourBotUsername']]);
        break;

    case 'startTask':
        $task_id = $input['task_id'];
        if (!$telegram_id || !$task_id)
        exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id or task_id']));
        $result = startTask($telegram_id, $task_id);
        echo json_encode($result);
        exit;
    case 'verifyTask':
        $task_id = $input['task_id'];
        if (!$telegram_id || !$task_id)
        exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id or task_id']));
        $password = $input['password'] ?? null;
        $result = verifyTask($telegram_id, $task_id, $password);
        echo json_encode($result);
        exit;
    case 'getTaskStatus':
        $task_id = $input['task_id'];
        if (!$telegram_id || !$task_id)
        exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id or task_id']));
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE telegram_id = ? AND task_id = ?");
        $stmt->execute([$telegram_id, $task_id]);
        $isCompleted = $stmt->fetchColumn() > 0;

        if ($isCompleted) {
            echo json_encode(['success' => true, 'status' => 'completed']);
            exit;
        }

        // التحقق من محاولة جارية
        $stmt = $db->prepare("
            SELECT * FROM task_attempts 
            WHERE telegram_id = ? AND task_id = ? AND status = 'pending'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$telegram_id, $task_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attempt) {
            $started = new DateTime($attempt['started_at']);
            $now = new DateTime();
            $elapsed = $now->getTimestamp() - $started->getTimestamp();

            echo json_encode([
                'success' => true,
                'status' => 'in_progress',
                'started_at' => $attempt['started_at'],
                'elapsed' => $elapsed
            ]);
            exit;
        }

        echo json_encode(['success' => true, 'status' => 'not_started']);
        exit;
    
    case 'getTasks':
        $tasks = getTasks($telegram_id);
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        exit;

    case 'claimTask':
        $task_id = $input['task_id'] ?? '';
        if (!$telegram_id || !$task_id)
            exit(json_encode(['status' => 'error', 'message' => 'Missing telegram_id or task_id']));
        $result = claimTaskReward($telegram_id, $task_id);
        echo json_encode($result);
        exit;
    
    case 'collectmining':
        echo json_encode(collectmining($telegram_id));
        break;

    case 'withdraw':
        $amount = floatval($input['amount'] ?? 0);
        echo json_encode(['message' => requestWithdraw($telegram_id, $amount)]);
        break;

    case 'startMining':
        $stmt = $db->prepare("UPDATE users SET mining_start_time = ?, mining_duration = ? WHERE telegram_id = ?");
        $stmt->execute([time(), $config['default_mining_duration'], $telegram_id]);
        echo json_encode(['success' => true]);
        break;

    case 'getReferrals':
        $level = $input['level'] ?? null;
        if (!$telegram_id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing telegram_id']);
            exit;
        }
    
        if ($level === 1) {
            $stmt = $db->prepare("SELECT firstname, photo_url ,username FROM users WHERE ref_lvl1 = ?");
        } elseif ($level === 2) {
            $stmt = $db->prepare("SELECT firstname, photo_url ,username FROM users WHERE ref_lvl2 = ?");
        } elseif ($level === 3) {
            $stmt = $db->prepare("SELECT firstname, photo_url,username FROM users WHERE ref_lvl3 = ?");
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid level ', 'level' => $level]);
            exit;
        }
    
        $stmt->execute([$telegram_id]);
        $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        echo json_encode(['success' => true, 'referrals' => $referrals]);
        break;
    

    case 'getRigs':
        $rigs = getRigs();
        echo json_encode(['success' => true, 'rigs' => $rigs]);
        break;

    case 'purchaseRig':
        $rig_id = intval($input['rig_id'] ?? 0);
        if (!$telegram_id || !$rig_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }
        $result = purchaseRig($telegram_id, $rig_id);
        echo json_encode($result);
        break;

    case 'getUserRigs':
        if (!$telegram_id) {
            echo json_encode(['success' => false, 'message' => 'Missing telegram_id']);
            exit;
        }
        $rigs = getUserRigs($telegram_id);
        echo json_encode(['success' => true, 'rigs' => $rigs]);
        break;

    case 'calculatePendingEarnings':
        if (!$telegram_id) {
            echo json_encode(['success' => false, 'message' => 'Missing telegram_id']);
            exit;
        }
        $earnings = calculatePendingEarnings($telegram_id);
        echo json_encode(['success' => true, 'earnings' => $earnings]);
        break;

    case 'claimMiningEarnings':
        if (!$telegram_id) {
            echo json_encode(['success' => false, 'message' => 'Missing telegram_id']);
            exit;
        }
        $result = claimMiningEarnings($telegram_id);
        echo json_encode($result);
        break;

    case 'getMiningStats':
        if (!$telegram_id) {
            echo json_encode(['success' => false, 'message' => 'Missing telegram_id']);
            exit;
        }
        $stats = getMiningStats($telegram_id);
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    case 'upgradeRig':
        $user_rig_id = intval($input['user_rig_id'] ?? 0);
        $new_rig_id = intval($input['new_rig_id'] ?? 0);
        if (!$telegram_id || !$user_rig_id || !$new_rig_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }
        $result = upgradeRig($telegram_id, $user_rig_id, $new_rig_id);
        echo json_encode($result);
        break;

    case 'renewRig':
        $user_rig_id = intval($input['user_rig_id'] ?? 0);
        if (!$telegram_id || !$user_rig_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }
        $result = renewRig($telegram_id, $user_rig_id);
        echo json_encode($result);
        break;

    case 'deactivateExpiredRigs':
        $result = deactivateExpiredRigs();
        echo json_encode($result);
        break;

    case 'recordDeposit':
        $amount = floatval($input['amount'] ?? 0);
        $currency = $input['currency'] ?? 'USDT';
        $hash = $input['transaction_hash'] ?? '';
        $wallet = $input['wallet_address'] ?? '';

        echo json_encode(recordDeposit($telegram_id, $amount, $currency, $hash, $wallet));
        break;

    case 'requestWithdraw':
        $amount = floatval($input['amount'] ?? 0);
        $currency = $input['currency'] ?? 'USDT';
        $wallet = $input['wallet_address'] ?? '';

        echo json_encode(requestWithdrawUSDT($telegram_id, $amount, $currency, $wallet));
        break;

    case 'saveWalletAddress':
        $wallet = $input['wallet_address'] ?? '';
        $type = $input['wallet_type'] ?? 'TON';

        echo json_encode(saveWalletAddress($telegram_id, $wallet, $type));
        break;

    case 'getTransactionHistory':
        echo json_encode(getTransactionHistory($telegram_id));
        break;

    case 'sendSupportMessage':
        $message = $input['message'] ?? '';
        echo json_encode(sendSupportMessage($telegram_id, $message));
        break;

    case 'getSupportMessages':
        echo json_encode(getSupportMessages($telegram_id));
        break;

    
    default:
        echo json_encode(['status' => 'error', 'message' => '❌ طلب غير معروف']);
        break;
}


?>
