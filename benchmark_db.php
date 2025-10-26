<?php
/**
 * SQLite Benchmark Script
 * ------------------------
 * يقيس سرعة القراءة والكتابة في قاعدة البيانات الحالية
 */

$config = include __DIR__ . '/config.php';
$db = new PDO("sqlite:" . $config['db_path']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// تحسين الأداء المؤقت (لا تستخدم في الإنتاج)
$db->exec("PRAGMA synchronous = OFF;");
$db->exec("PRAGMA journal_mode = MEMORY;");

echo "🚀 بدء اختبار الأداء على: {$config['db_path']}\n\n";

// ===================================================
// 1️⃣ اختبار الكتابة (Insert)
// ===================================================
$start = microtime(true);

$db->exec("DROP TABLE IF EXISTS benchmark;");
$db->exec("CREATE TABLE benchmark (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, value INTEGER);");

$db->beginTransaction();
for ($i = 0; $i < 10000; $i++) {
    $stmt = $db->prepare("INSERT INTO benchmark (name, value) VALUES (?, ?)");
    $stmt->execute(["user_" . $i, rand(1, 100000)]);
}
$db->commit();

$write_time = microtime(true) - $start;
$write_tps = round(10000 / $write_time, 2);

echo "🧩 إدخال 10,000 صف:\n";
echo "   ⏱ الوقت المستغرق: " . round($write_time, 2) . " ثانية\n";
echo "   ⚡ السرعة: {$write_tps} عملية/ثانية\n\n";

// ===================================================
// 2️⃣ اختبار القراءة (Select)
// ===================================================
$start = microtime(true);

for ($i = 0; $i < 5000; $i++) {
    $id = rand(1, 10000);
    $stmt = $db->query("SELECT * FROM benchmark WHERE id = $id");
    $stmt->fetch(PDO::FETCH_ASSOC);
}

$read_time = microtime(true) - $start;
$read_tps = round(5000 / $read_time, 2);

echo "📖 قراءة 5,000 صف:\n";
echo "   ⏱ الوقت المستغرق: " . round($read_time, 2) . " ثانية\n";
echo "   ⚡ السرعة: {$read_tps} عملية/ثانية\n\n";

// ===================================================
// 3️⃣ اختبار التحديث (Update)
// ===================================================
$start = microtime(true);

$db->beginTransaction();
for ($i = 0; $i < 5000; $i++) {
    $id = rand(1, 10000);
    $stmt = $db->prepare("UPDATE benchmark SET value = ? WHERE id = ?");
    $stmt->execute([rand(1, 100000), $id]);
}
$db->commit();

$update_time = microtime(true) - $start;
$update_tps = round(5000 / $update_time, 2);

echo "✏️ تعديل 5,000 صف:\n";
echo "   ⏱ الوقت المستغرق: " . round($update_time, 2) . " ثانية\n";
echo "   ⚡ السرعة: {$update_tps} عملية/ثانية\n\n";

// ===================================================
// 4️⃣ النتيجة النهائية
// ===================================================
$total_ops = 20000;
$total_time = $write_time + $read_time + $update_time;
$overall_tps = round($total_ops / $total_time, 2);

echo "========================================\n";
echo "📊 الأداء الكلي للنظام:\n";
echo "   🧮 إجمالي العمليات: {$total_ops}\n";
echo "   ⏱ الوقت الكلي: " . round($total_time, 2) . " ثانية\n";
echo "   ⚡ المتوسط العام: {$overall_tps} عملية/ثانية\n";
echo "========================================\n";
