<?php
/**
 * لوحة تحكم الأدمن - Backend
 * نظام إدارة شامل للتعدين والمستخدمين
 */

header('Content-Type: application/json; charset=utf-8');

// تحميل الإعدادات
$config = include __DIR__ . '/config.php';

// الاتصال بقاعدة البيانات
try {
    $db = new PDO("sqlite:" . $config['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die(json_encode(["ok"=>false,"error"=>"DB connection failed: ".$e->getMessage()]));
}

// استخراج البيانات من POST أو GET
$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true) ?? [];

$user_id = intval($inputData['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? 0);

// التحقق من وجود user_id
if (!$user_id) {
    echo json_encode(["ok"=>false, "error"=>"session_expired"]);
    exit;
}

// ============================================
// معالجة POST Requests
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $inputData['action'] ?? $_POST['action'] ?? '';

    // فحص صلاحية الأدمن
    if ($action === 'check_admin' && $user_id) {
        if ($user_id === $config['admin_id']) {
            echo json_encode(['ok'=>true, 'user_id'=>$user_id]);
        } else {
            echo json_encode(['ok'=>false, 'error'=>'not_authorized']);
        }
        exit;
    }

    // التحقق من صلاحيات الأدمن لباقي العمليات
    if ($user_id !== $config['admin_id']) {
        echo json_encode(['ok'=>false,'error'=>'not_authorized']);
        exit;
    }

    // ============================================
    // إدارة المهام
    // ============================================
    if ($action === 'tasks') {
        $sub_action = $inputData['sub_action'] ?? $_POST['sub_action'] ?? '';

        switch($sub_action){
            case 'fetch':
                $data = $db->query("SELECT * FROM tasks ORDER BY task_type ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['ok'=>true,'data'=>$data]); 
                exit;

            case 'add':
                try {
                    $stmt = $db->prepare("INSERT INTO tasks (task_type,target,reward_type,reward_value,description) VALUES (?,?,?,?,?)");
                    $stmt->execute([ 
                        $_POST['task_type'], 
                        $_POST['target'],
                        $_POST['reward_type'], 
                        $_POST['reward_value'],
                        $_POST['description']
                    ]);
                    echo json_encode(['ok'=>true,'message'=>'✅ تمت إضافة المهمة بنجاح']);
                } catch(Exception $e) {
                    echo json_encode(['ok'=>false,'message'=>'❌ ' . $e->getMessage()]);
                }
                exit;

            case 'update':
                try {
                    $stmt = $db->prepare("UPDATE tasks SET task_type=?, target=?, reward_type=?, reward_value=?, description=?, is_active=?, max_claims=? WHERE id=?");
                    $stmt->execute([
                        $_POST['task_type'], 
                        $_POST['target'], 
                        $_POST['reward_type'],
                        $_POST['reward_value'], 
                        $_POST['description'],
                        $_POST['is_active'], 
                        $_POST['max_claims'], 
                        $_POST['id']
                    ]);
                    echo json_encode(['ok'=>true,'message'=>'✅ تم تعديل المهمة بنجاح']);
                } catch(Exception $e) {
                    echo json_encode(['ok'=>false,'message'=>'❌ ' . $e->getMessage()]);
                }
                exit;

            case 'delete':
                try {
                    $stmt = $db->prepare("DELETE FROM tasks WHERE id=?");
                    $stmt->execute([$_POST['id']]);
                    echo json_encode(['ok'=>true,'message'=>'✅ تم حذف المهمة بنجاح']);
                } catch(Exception $e) {
                    echo json_encode(['ok'=>false,'message'=>'❌ ' . $e->getMessage()]);
                }
                exit;
        }
        exit;
    }

    // ============================================
    // تحديث بيانات المستخدم
    // ============================================
    if ($action === 'updateuser') {
        $uid = intval($inputData['userid'] ?? $_POST['userid'] ?? 0);
        $bal = floatval($inputData['balance'] ?? $_POST['balance'] ?? 0);
        $gen = floatval($inputData['gen_balance'] ?? $_POST['gen_balance'] ?? 0);
        $pow = floatval($inputData['mining_power'] ?? $_POST['mining_power'] ?? 0);
        $inv = intval($inputData['invite_count'] ?? $_POST['invite_count'] ?? 0);
        $stage = intval($inputData['stage'] ?? $_POST['stage'] ?? 0);

        try {
            $stmt = $db->prepare("UPDATE users SET balance=?, gen_balance=?, mining_power=?, invite_count=?, stage=? WHERE telegram_id=?");
            $stmt->execute([$bal, $gen, $pow, $inv, $stage, $uid]);

            // تسجيل في سجل التعديلات (optional)
            logAdminAction($db, $user_id, "update_user", "User ID: $uid");

            echo json_encode(['ok'=>true, 'message'=>'✅ تم تحديث بيانات المستخدم']);
        } catch(Exception $e) {
            echo json_encode(['ok'=>false, 'message'=>'❌ ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================================
    // حذف مستخدم
    // ============================================
    if ($action === 'delete_user') {
        $uid = intval($inputData['target_user_id'] ?? $_POST['target_user_id'] ?? 0);
        if($uid){
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE telegram_id=?");
                $stmt->execute([$uid]);

                logAdminAction($db, $user_id, "delete_user", "User ID: $uid");

                echo json_encode(['ok'=>true, 'message'=>'✅ تم حذف المستخدم']);
            } catch(Exception $e) {
                echo json_encode(['ok'=>false, 'message'=>'❌ ' . $e->getMessage()]);
            }
        }
        exit;
    }

    // ============================================
    // إدارة قاعدة البيانات
    // ============================================
    if ($action === 'database') {
        $sub_action = $inputData['sub_action'] ?? $_POST['sub_action'] ?? '';

        try {
            switch($sub_action){

                // عرض جميع الجداول
                case 'list_tables':
                    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                                 ->fetchAll(PDO::FETCH_COLUMN);

                    $tableData = [];
                    foreach($tables as $t){
                        $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
                        $tableData[] = [
                            'name' => $t,
                            'columns' => count($cols)
                        ];
                    }

                    echo json_encode(['ok'=>true, 'tables'=>$tableData]);
                    break;

                // عرض محتوى جدول محدد
                case 'view_table':
                    $table = $_POST['name'] ?? '';
                    $page = intval($_POST['page'] ?? 1);
                    $limit = intval($_POST['limit'] ?? 10);
                    $offset = ($page - 1) * $limit;
    
                    if (!$table) {
                        echo json_encode(['ok' => false, 'message' => 'لم يتم تحديد اسم الجدول']);
                        exit;
                    }
    
                    try {
                        // جلب أسماء الأعمدة
                        $cols = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                        $columns = array_column($cols, 'name');
    
                        // جلب الصفوف مع limit
                        $stmt = $db->prepare("SELECT * FROM $table LIMIT :limit OFFSET :offset");
                        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                        echo json_encode(['ok' => true, 'columns' => $columns, 'rows' => $rows]);
                    } catch (Exception $e) {
                        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                    }
                    exit;

                // إنشاء جدول جديد
                case 'create_table':
                    $name = $_POST['name'] ?? '';
                    $columns = $_POST['columns'] ?? '';
                    if(!$name || !$columns){ 
                        echo json_encode(['ok'=>false,'message'=>'البيانات غير مكتملة']); 
                        break; 
                    }

                    $db->exec("CREATE TABLE IF NOT EXISTS $name ($columns)");
                    logAdminAction($db, $user_id, "create_table", "Table: $name");
                    echo json_encode(['ok'=>true,'message'=>"✅ تم إنشاء الجدول $name بنجاح"]);
                    break;
                
                // تعديل اسم جدول   
                case 'rename_table':
                    $old = $_POST['oldName'] ?? '';
                    $new = $_POST['newName'] ?? '';
                    if(!$old || !$new){ 
                        echo json_encode(['ok'=>false,'message'=>'بيانات ناقصة']); 
                        break; 
                    }
    
                    $db->exec("ALTER TABLE $old RENAME TO $new");
                    logAdminAction($db, $user_id, "rename_table", "Table: $old, $old -> $new");
                    echo json_encode(['ok'=>true,'message'=>"✅ تم تعديل اسم الجدول من $old إلى $new"]);
                    break;
                
                // تفريغ جدول    
                case 'clear_table':
                    $name = $_POST['name'] ?? '';
                    if (!$name) {
                        echo json_encode(['ok' => false, 'error' => 'لم يتم تحديد اسم الجدول']);
                        exit;
                    }

                    try {
                        $db->exec("DELETE FROM $name");
                        echo json_encode(['ok' => true, 'message' => "تم تفريغ الجدول $name بنجاح"]);
                    } catch (Exception $e) {
                        echo json_encode(['ok' => false, 'error' => 'فشل تفريغ الجدول: ' . $e->getMessage()]);
                    }
                    break;

                // حذف جدول
                case 'delete_table':
                    $name = $_POST['name'] ?? '';
                    if(!$name){ 
                        echo json_encode(['ok'=>false,'message'=>'اسم الجدول مطلوب']); 
                        break; 
                    }

                    $db->exec("DROP TABLE IF EXISTS $name");
                    logAdminAction($db, $user_id, "delete_table", "Table: $name");
                    echo json_encode(['ok'=>true,'message'=>"✅ تم حذف الجدول $name"]);
                    break;

                // إضافة عمود جديد
                case 'add_column':
                    $table = $_POST['table'] ?? '';
                    $col = $_POST['col'] ?? '';
                    $type = $_POST['type'] ?? 'TEXT';
                    if(!$table || !$col){ 
                        echo json_encode(['ok'=>false,'message'=>'البيانات ناقصة']); 
                        break; 
                    }

                    $db->exec("ALTER TABLE $table ADD COLUMN $col $type");
                    logAdminAction($db, $user_id, "add_column", "Table: $table, Column: $col");
                    echo json_encode(['ok'=>true,'message'=>"✅ تمت إضافة العمود $col في الجدول $table"]);
                    break;

                // تعديل اسم عمود
                case 'rename_column':
                    $table = $_POST['table'] ?? '';
                    $old = $_POST['oldName'] ?? '';
                    $new = $_POST['newName'] ?? '';
                    if(!$table || !$old || !$new){ 
                        echo json_encode(['ok'=>false,'message'=>'بيانات ناقصة']); 
                        break; 
                    }

                    $db->exec("ALTER TABLE $table RENAME COLUMN $old TO $new");
                    logAdminAction($db, $user_id, "rename_column", "Table: $table, $old -> $new");
                    echo json_encode(['ok'=>true,'message'=>"✅ تم تعديل اسم العمود من $old إلى $new"]);
                    break;
                
                // حذف عمود (عن طريق إعادة إنشاء الجدول)
                case 'delete_column':
                    $table = $_POST['table'] ?? '';
                    $column = $_POST['column'] ?? '';
                    if(!$table || !$column){ 
                        echo json_encode(['ok'=>false,'message'=>'بيانات ناقصة']); 
                        break; 
                    }

                    $cols = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                    $columns = array_column($cols, 'name');
                    $newCols = array_diff($columns, [$column]);

                    if(count($newCols) === count($columns)){
                        echo json_encode(['ok'=>false,'message'=>'❌ العمود غير موجود']); 
                        break;
                    }

                    $temp = $table . "_temp";
                    $colsDef = [];
                    foreach($cols as $c){
                        if($c['name'] !== $column)
                            $colsDef[] = "{$c['name']} {$c['type']}";
                    }

                    $db->exec("CREATE TABLE $temp (" . implode(',', $colsDef) . ")");
                    $db->exec("INSERT INTO $temp SELECT " . implode(',', $newCols) . " FROM $table");
                    $db->exec("DROP TABLE $table");
                    $db->exec("ALTER TABLE $temp RENAME TO $table");

                    logAdminAction($db, $user_id, "delete_column", "Table: $table, Column: $column");
                    echo json_encode(['ok'=>true,'message'=>"✅ تم حذف العمود $column من الجدول $table"]);
                    break;
                
                // تنفيذ أمر SQL
                case 'run_sql':
                    $query = trim($_POST['query'] ?? '');
    
                    if (!$query) {
                        echo json_encode(['ok' => false, 'error' => 'لا يوجد أمر لتنفيذه']);
                        exit;
                    }
    
                    try {
                        // ⚠️ حماية بسيطة: منع أوامر الحذف الكاملة
                        if (preg_match('/DROP\s+DATABASE/i', $query)) {
                            echo json_encode(['ok' => false, 'error' => '🚫 هذا النوع من الأوامر غير مسموح به']);
                            exit;
                        }
    
                        $stmt = $db->query($query);
    
                        // لو SELECT نعرض النتائج
                        if (stripos($query, 'SELECT') === 0) {
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            echo json_encode(['ok' => true, 'result' => $rows]);
                        } else {
                            echo json_encode(['ok' => true, 'message' => '✅ تم تنفيذ الأمر بنجاح']);
                        }
                    } catch (PDOException $e) {
                        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                    }
                    exit;
                
                // اختبار الأداء
                case 'benchmark':
                    try {
                        $startTime = microtime(true);
                        $db->beginTransaction();
    
                        // 🔹 إنشاء جدول تجريبي
                        $db->exec("CREATE TABLE IF NOT EXISTS benchmark_test (id INTEGER PRIMARY KEY, value TEXT)");
    
                        // 🔹 إدخال 10,000 صف
                        $insert = $db->prepare("INSERT INTO benchmark_test (value) VALUES (?)");
                        for ($i = 0; $i < 10000; $i++) {
                            $insert->execute(['test_' . $i]);
                        }
    
                        $insertTime = microtime(true) - $startTime;
    
                        // 🔹 قراءة 5,000 صف
                        $readStart = microtime(true);
                        $stmt = $db->query("SELECT * FROM benchmark_test LIMIT 5000");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $readTime = microtime(true) - $readStart;
    
                        // 🔹 تعديل 5,000 صف
                        $updateStart = microtime(true);
                        $update = $db->prepare("UPDATE benchmark_test SET value='updated' WHERE id <= 5000");
                        $update->execute();
                        $updateTime = microtime(true) - $updateStart;
    
                        $db->commit();
    
                        // حساب النتائج
                        $totalOps = 20000;
                        $totalTime = $insertTime + $readTime + $updateTime;
                        $avgSpeed = $totalOps / $totalTime;
    
                        $result = "🚀 بدء اختبار الأداء على: {$config['db_path']}\n";
                        $result .= "🧩 إدخال 10,000 صف:\n⏱ الوقت المستغرق: " . round($insertTime, 2) . " ثانية\n";
                        $result .= "⚡ السرعة: " . number_format(10000 / $insertTime, 2) . " عملية/ثانية\n\n";
                        $result .= "📖 قراءة 5,000 صف:\n⏱ الوقت المستغرق: " . round($readTime, 2) . " ثانية\n";
                        $result .= "⚡ السرعة: " . number_format(5000 / $readTime, 2) . " عملية/ثانية\n\n";
                        $result .= "✏️ تعديل 5,000 صف:\n⏱ الوقت المستغرق: " . round($updateTime, 2) . " ثانية\n";
                        $result .= "⚡ السرعة: " . number_format(5000 / $updateTime, 2) . " عملية/ثانية\n";
                        $result .= "========================================\n";
                        $result .= "📊 الأداء الكلي للنظام:\n";
                        $result .= "🧮 إجمالي العمليات: {$totalOps}\n";
                        $result .= "⏱ الوقت الكلي: " . round($totalTime, 2) . " ثانية\n";
                        $result .= "⚡ المتوسط العام: " . number_format($avgSpeed, 2) . " عملية/ثانية\n";
                        $result .= "========================================\n";
    
                        echo json_encode(['ok' => true, 'result' => $result]);
                    } catch (Exception $e) {
                        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                    }
                    exit;

                default:
                    echo json_encode(['ok'=>false,'message'=>'sub_action غير معروف']);
            }
        } catch(Exception $e){
            echo json_encode(['ok'=>false,'message'=>'❌ ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================================
    // الموافقة على السحب
    // ============================================
    if ($action === 'approve_withdraw') {
        $tid = intval($inputData['trans_id'] ?? $_POST['trans_id'] ?? 0);
        if($tid){
            try {
                $stmt = $db->prepare("UPDATE transactions SET status='approved' WHERE id=?");
                $stmt->execute([$tid]);

                // الحصول على تفاصيل العملية للإشعار
                $trans = $db->query("SELECT * FROM transactions WHERE id=$tid")->fetch(PDO::FETCH_ASSOC);

                logAdminAction($db, $user_id, "approve_withdraw", "Trans ID: $tid, Amount: {$trans['amount']}");

                // إرسال إشعار (optional)
                require_once 'notifications.php';
                sendWithdrawApprovalNotification($trans['telegram_id'], $trans['amount']);

                echo json_encode(['ok'=>true, 'message'=>'✅ تمت الموافقة على طلب السحب']);
            } catch(Exception $e) {
                echo json_encode(['ok'=>false, 'message'=>'❌ ' . $e->getMessage()]);
            }
        }
        exit;
    }


    // case 'approveDeposit':
    //     $transaction_id = $input['transaction_id'] ?? 0;
    //     // التحقق من أن المستخدم أدمن
    //     if ($telegram_id == $config['admin_user_id']) {
    //         echo json_encode(approveDeposit($transaction_id, $telegram_id));
    //     } else {
    //         echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    //     }
    //     break;

    // case 'rejectTransaction':
    //     $transaction_id = $input['transaction_id'] ?? 0;
    //     $reason = $input['reason'] ?? '';

    //     if ($telegram_id == $config['admin_user_id']) {
    //         echo json_encode(rejectTransaction($transaction_id, $telegram_id, $reason));
    //     } else {
    //         echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    //     }
    //     break;
    

    // ============================================
    // رفض السحب
    // ============================================
    if ($action === 'reject_withdraw') {
        $tid = intval($inputData['trans_id'] ?? $_POST['trans_id'] ?? 0);
        $reason = $inputData['reason'] ?? $_POST['reason'] ?? '';

        if($tid){
            try {
                $stmt = $db->prepare("UPDATE transactions SET status='rejected' WHERE id=?");
                $stmt->execute([$tid]);

                // إرجاع المبلغ للمستخدم
                $trans = $db->query("SELECT * FROM transactions WHERE id=$tid")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
                $stmt->execute([$trans['amount'], $trans['telegram_id']]);

                logAdminAction($db, $user_id, "reject_withdraw", "Trans ID: $tid, Reason: $reason");

                // إرسال إشعار (optional)
                require_once 'notifications.php';
                sendWithdrawRejectionNotification($trans['telegram_id'], $trans['amount'], $reason);

                echo json_encode(['ok'=>true, 'message'=>'✅ تم رفض الطلب وإرجاع المبلغ']);
            } catch(Exception $e) {
                echo json_encode(['ok'=>false, 'message'=>'❌ ' . $e->getMessage()]);
            }
        }
        exit;
    }

    echo json_encode(['ok'=>true]);
    exit;
}

// ============================================
// معالجة GET Requests (جلب البيانات)
// ============================================
$tab = $_GET['tab'] ?? '';

// التحقق من الصلاحيات
if ($user_id !== $config['admin_id']) {
    echo json_encode(['ok'=>false,'error'=>'not_authorized']);
    exit;
}

try {
    switch ($tab) {
        // ============================================
        // المستخدمين
        // ============================================
        
        case 'users':
            // 📥 القيم المرسلة من الواجهة
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(10, intval($_GET['limit'])) : 50;
            $offset = ($page - 1) * $limit;
    
            // 📊 الإحصائيات العامة (تُحسب مرة واحدة فقط)
            $stats = $db->query("SELECT COUNT(*) as total, SUM(balance) as total_balance FROM users")->fetch(PDO::FETCH_ASSOC);
            $pending = $db->query("SELECT COUNT(*) as count FROM transactions WHERE status='pending' AND type='withdraw'")->fetch(PDO::FETCH_ASSOC);
    
            // 🔍 إذا كان فيه بحث
            if ($search !== '') {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE CAST(telegram_id AS TEXT) LIKE ? OR username LIKE ? OR firstname LIKE ?");
                $countStmt->execute(["%$search%", "%$search%", "%$search%"]);
                $total = (int)$countStmt->fetchColumn();
    
                $stmt = $db->prepare("
                    SELECT * FROM users 
                    WHERE CAST(telegram_id AS TEXT) LIKE ? 
                       OR username LIKE ? 
                       OR firstname LIKE ? 
                    ORDER BY join_date DESC 
                    LIMIT :limit OFFSET :offset
                ");
                $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
                $stmt->bindValue(2, "%$search%", PDO::PARAM_STR);
                $stmt->bindValue(3, "%$search%", PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
            } 
            // 📄 بدون بحث (عرض عادي بالصفحات)
            else {
                $total = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
                $stmt = $db->prepare("SELECT * FROM users ORDER BY join_date DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
            }
    
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // 📤 النتيجة النهائية
            echo json_encode([
                'ok' => true,
                'tab' => $tab,
                'data' => $data,
                'total' => $total,
                'stats' => [
                    'total_users' => $stats['total'],
                    'total_balance' => round($stats['total_balance'], 2),
                    'pending_withdraws' => $pending['count']
                ]
            ]);
            break;

        // ============================================
        // المعاملات
        // ============================================
        case 'transactions':
            // قراءة قيم الصفحة والعدد في كل صفحة
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
            $offset = ($page - 1) * $limit;
    
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
            if ($search !== '') {
                // البحث
                $stmt = $db->prepare("
                    SELECT * FROM transactions 
                    WHERE CAST(id AS TEXT) LIKE ? OR CAST(telegram_id AS TEXT) LIKE ? 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset
                ");
                $stmt->bindValue(1, "%$search%");
                $stmt->bindValue(2, "%$search%");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
    
                // العدد الكلي في حالة البحث
                $countStmt = $db->prepare("
                    SELECT COUNT(*) FROM transactions 
                    WHERE CAST(id AS TEXT) LIKE ? OR CAST(telegram_id AS TEXT) LIKE ?
                ");
                $countStmt->execute(["%$search%", "%$search%"]);
                $total = $countStmt->fetchColumn();
            } else {
                // عرض عام بدون بحث
                $stmt = $db->prepare("SELECT * FROM transactions ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
    
                // العدد الكلي
                $total = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
            }
    
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            echo json_encode([
                'ok' => true,
                'tab' => $tab,
                'data' => $data,
                'total' => intval($total),
                'page' => $page,
                'limit' => $limit
            ]);
            break;

        case 'withdrawals':
            // 🧭 المتغيرات القادمة من الواجهة
            $page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(10, intval($_GET['limit'])) : 50;
            $offset = ($page - 1) * $limit;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
            try {
                // 🧮 فلترة البيانات (بحث أو عادي)
                if ($search !== '') {
                    $stmt = $db->prepare("
                        SELECT * FROM transactions
                        WHERE type='withdraw'
                        AND (CAST(id AS TEXT) LIKE :search OR CAST(telegram_id AS TEXT) LIKE :search)
                        ORDER BY created_at DESC
                        LIMIT :limit OFFSET :offset
                    ");
                    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
                } else {
                    $stmt = $db->prepare("
                        SELECT * FROM transactions
                        WHERE type='withdraw'
                        ORDER BY created_at DESC
                        LIMIT :limit OFFSET :offset
                    ");
                }
    
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                // 🧾 حساب إجمالي عدد الصفوف (لصفحات التنقل)
                if ($search !== '') {
                    $countStmt = $db->prepare("
                        SELECT COUNT(*) FROM transactions
                        WHERE type='withdraw'
                        AND (CAST(id AS TEXT) LIKE :search OR CAST(telegram_id AS TEXT) LIKE :search)
                    ");
                    $countStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
                } else {
                    $countStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE type='withdraw'");
                }
                $countStmt->execute();
                $totalRows = (int) $countStmt->fetchColumn();
    
                echo json_encode([
                    'ok' => true,
                    'tab' => 'withdrawals',
                    'data' => $data,
                    'total' => $totalRows,
                    'page' => $page,
                    'limit' => $limit
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'ok' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            break;
    
        // ============================================
        // المهام
        // ============================================
        case 'tasks':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(10, intval($_GET['limit'])) : 25;
            $offset = ($page - 1) * $limit;
    
            $stmt = $db->prepare("SELECT * FROM tasks ORDER BY task_type ASC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $total = (int)$db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    
            echo json_encode([
                'ok' => true,
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]);
            break;

        // ============================================
        // الإحالات
        // ============================================
        case 'referrals':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(10, intval($_GET['limit'])) : 25;
            $offset = ($page - 1) * $limit;
    
            $stmt = $db->prepare("SELECT * FROM referral_earnings ORDER BY id DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $total = (int)$db->query("SELECT COUNT(*) FROM referral_earnings")->fetchColumn();
    
            echo json_encode([
                'ok' => true,
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]);
            break;


        // ============================================
        // الإحصائيات اليومية
        // ============================================
        case 'daily':
            $data = $db->query("
                SELECT date(join_date) as date, COUNT(*) as count, SUM(balance) as balance_sum
                FROM users
                GROUP BY date(join_date)
                ORDER BY date(join_date) DESC
                LIMIT 30
            ")->fetchAll(PDO::FETCH_ASSOC);

            // عكس الترتيب للعرض الصحيح في الرسم البياني
            $data = array_reverse($data);

            echo json_encode(['ok'=>true, 'tab'=>$tab, 'data'=>$data]);
            break;

        

        default:
            $data = [];
            echo json_encode(['ok'=>true, 'tab'=>$tab, 'data'=>$data]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

// ============================================
// دالة تسجيل إجراءات الأدمن
// ============================================
function logAdminAction($db, $admin_id, $action, $details = '') {
    try {
        // إنشاء جدول السجلات إذا لم يكن موجود
        $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            action TEXT,
            details TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $action, $details]);
    } catch(Exception $e) {
        // تجاهل الأخطاء في السجل
    }
}


// ====================================
// ✅ تأكيد الإيداع (Admin Only)
// ====================================
function approveDeposit($transaction_id, $admin_id) {
    global $db;

    try {
        // جلب بيانات المعاملة
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'deposit'");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            return ['success' => false, 'message' => 'المعاملة غير موجودة'];
        }

        if ($transaction['status'] !== 'pending') {
            return ['success' => false, 'message' => 'المعاملة تمت معالجتها مسبقاً'];
        }

        $db->beginTransaction();

        try {
            // إضافة المبلغ لرصيد المستخدم
            $stmt = $db->prepare("
                UPDATE users 
                SET balance = balance + ? 
                WHERE telegram_id = ?
            ");
            $stmt->execute([
                $transaction['amount'],
                $transaction['telegram_id']
            ]);

            // تحديث حالة المعاملة
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'approved',
                    approved_by = ?,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$admin_id, $transaction_id]);

            $db->commit();

            // إرسال إشعار للمستخدم
            notifyUserDepositApproved($transaction['telegram_id'], $transaction['amount']);

            return ['success' => true, 'message' => 'تم تأكيد الإيداع'];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ====================================
// ❌ رفض المعاملة (Admin Only)
// ====================================
function rejectTransaction($transaction_id, $admin_id, $reason = '') {
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            return ['success' => false, 'message' => 'المعاملة غير موجودة'];
        }

        $db->beginTransaction();

        try {
            // إذا كان سحب، إرجاع المبلغ
            if ($transaction['type'] === 'withdraw' && $transaction['status'] === 'pending') {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET balance = balance + ? 
                    WHERE telegram_id = ?
                ");
                $stmt->execute([
                    $transaction['amount'],
                    $transaction['telegram_id']
                ]);
            }

            // تحديث حالة المعاملة
            $stmt = $db->prepare("
                UPDATE transactions 
                SET status = 'rejected',
                    rejection_reason = ?,
                    approved_by = ?,
                    updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$reason, $admin_id, $transaction_id]);

            $db->commit();

            // إرسال إشعار للمستخدم
            notifyUserTransactionRejected($transaction['telegram_id'], $transaction['type'], $reason);

            return ['success' => true, 'message' => 'تم رفض المعاملة'];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}