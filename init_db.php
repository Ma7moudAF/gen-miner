<?php
$config = include __DIR__ . '/config.php';

try {
    $db = new PDO("sqlite:" . $config['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🔹 Database connection established successfully.\n";

    // =====================================================
    // USERS TABLE — بيانات المستخدمين + نظام الدعوات والمهمات
    // =====================================================
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        telegram_id INTEGER PRIMARY KEY,
        firstname TEXT,
        username TEXT DEFAULT NULL,
        balance REAL DEFAULT {$config['register_balance']},
        gen_balance REAL DEFAULT {$config['register_gen_balance']},
        referral_balance REAL DEFAULT 0,
        mining_power REAL DEFAULT {$config['register_power']},
        mining_start_time INTEGER DEFAULT NULL,
        mining_duration INTEGER DEFAULT {$config['default_mining_duration']},
        invite_count INTEGER DEFAULT 0,
        ref_lvl1 INTEGER DEFAULT NULL,
        ref_lvl2 INTEGER DEFAULT NULL,
        ref_lvl3 INTEGER DEFAULT NULL,
        stage INTEGER DEFAULT 1,  -- المرحلة الحالية من المهمات
        photo_url TEXT DEFAULT NULL,
        join_date TEXT DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // =====================================================
    // TRANSACTIONS TABLE — عمليات الشراء والسحب والإيداع
    // =====================================================
    $db->exec("
    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        telegram_id INTEGER,
        type TEXT,                      -- deposit / withdraw / mining / bonus
        amount REAL,
        status TEXT DEFAULT 'pending',  -- pending / approved / rejected
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // =====================================================
    // REFERRAL_EARNINGS TABLE — نظام الإحالات (3 مستويات)
    // =====================================================
    $db->exec("
    CREATE TABLE IF NOT EXISTS referral_earnings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        to_id INTEGER,  -- الشخص اللي حصل على الربح
        from_id INTEGER,    -- الشخص اللي تسبب في الربح (المحال)
        level INTEGER,    -- المستوى: 1 أو 2 أو 3
        amount REAL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    ");

    // =====================================================
    // TASKS TABLE — نظام المهمات (مراحل قابلة للتطور)
    // =====================================================
    $db->exec("
    CREATE TABLE IF NOT EXISTS tasks (
        stage INTEGER PRIMARY KEY,         -- رقم المرحلة
        type TEXT,                         -- نوع المهمة (invite, deposit...)
        target INTEGER,                    -- الهدف (عدد الدعوات مثلاً)
        reward_type TEXT,                  -- نوع المكافأة (power / balance)
        reward_value REAL,                 -- قيمة المكافأة
        description TEXT                   -- وصف المهمة للمستخدم
    );
    ");

    echo "✅ All tables created successfully.\n";

    // =====================================================
    // إدخال المهمات الافتراضية (لو مش موجودة)
    // =====================================================
    $stmt = $db->query("SELECT * FROM tasks");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $db->exec("
        INSERT INTO tasks (stage, type, target, reward_type, reward_value, description)
        VALUES
        (1, 'invite', 3, 'power', 50000,  'ادعُ 3 أصدقاء لزيادة قوة التعدين بمقدار 50K'),
        (2, 'invite', 5, 'power', 100000, 'ادعُ 5 أصدقاء لزيادة القوة بمقدار 100K'),
        (3, 'invite', 10, 'balance', 2,   'ادعُ 10 أصدقاء واربح 2 USDT'),
        (4, 'invite', 20, 'power', 250000, 'ادعُ 20 صديقًا لزيادة القوة بمقدار 250K'),
        (5, 'invite', 50, 'balance', 5,   'ادعُ 50 صديقًا لتحصل على 5 USDT إضافية');
        ");
        echo "🎯 Default tasks inserted successfully.\n";
    } else {
        echo "ℹ️ Tasks already exist, skipping insertion.\n";
    }

} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage());
}

echo "🚀 Database initialization complete.\n";
