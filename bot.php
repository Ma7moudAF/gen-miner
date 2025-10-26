<?php
flush();
ob_start();
set_time_limit(0);
error_reporting(0);
ob_implicit_flush(1);
$config = include __DIR__ . '/config.php';
include __DIR__ . '/api.php';
# === BOT TOKEN === #
$token = $config['bot_token']; // توكن البوت
define('API_KEY', $token);

# === CHANNEL TO CHECK === #


# === ADMIN ID === #
$admin_id = $config['admin_id'];

# === WEBAPP LINK === #


$webhook_info = bot('getWebhookInfo');
if(isset($webhook_info->result) && empty($webhook_info->result->url)){
    bot('setWebhook', ['url' => $config['webapp_url'].'bot.php']);
    echo "Webhook set successfully: $webhook_url";
} else {
    echo "Webhook already set: ".$webhook_info->result->url;
}
# === DATABASE === #
try {
    $db = new PDO("sqlite:" . $config['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}



# === BOT FUNCTION === #
function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if(curl_error($ch)){
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

# === SAVE USER === #
function cheek_user($db, $telegram_id, $first_name, $username) {
    // التحقق إذا كان المستخدم موجود مسبقًا
    $stmt = $db->prepare("SELECT telegram_id FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        // إدخال مستخدم جديد
        
        return false;
    } else {
        $stmt = $db->prepare("UPDATE users SET firstname = ?, username  = ?  WHERE telegram_id = ?");
        $stmt->execute([$first_name, $username ?: '', $telegram_id]);
        return true;
    }
}


# === HANDLE UPDATE === #
$update = json_decode(file_get_contents("php://input"));
$message = $update->message ?? null;
$chat_id = $message->chat->id ?? null;
$from_id = $message->from->id ?? null;
$first_name = $message->from->first_name ?? "";
$username = $message->from->username ?? $from_first;
$text = $message->text ?? "";
$referrer_id = null;
$photo_url =  null;

// if($chat_id && $from_id) {
//     $user = cheek_user($db, $chat_id, $first_name, $username,);
//     if($user == false){
//         registerUser($chat_id, $first_name, $username, $referrer_id )
        
//     }
// }

# === HANDLE COMMANDS === #
if (strpos($text, "/start") === 0) {
    // استخراج ID الداعي (إن وجد)
    $parts = explode(" ", $text);
    $referrer_id = isset($parts[1]) ? intval($parts[1]) : null;

    $user = cheek_user($db, $chat_id, $first_name, $username,);
    if($user == false) {
        registerUser($from_id, $first_name, $username, $referrer_id, $photo_url );

    }
    // تسجيل المستخدم
    // registerUser($from_id, $from_first, $username, $referrer_id);

    if ($from_id == $admin_id) {
        // ✅ زر يفتح WebApp للأدمن فقط
        $button = [
            "inline_keyboard" => [
                [
                    ["text" => "🧩 افتح لوحة التحكم", "web_app" => ["url" => $config['webapp_url']."admin.html"]]
                ],
                [
                    ["text" => "🚀 دخول للبوت", "web_app" => ["url" => $config['webapp_url']]]
                ]
            ]
        ];
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👋 أهلاً بك، أدمن النظام.\nاضغط الزر أدناه لفتح لوحة التحكم.",
            'reply_markup' => json_encode($button),
            'parse_mode' => "Markdown"
        ]);
    } else {
        // 👇 زر الدخول للمستخدم العادي
        $button = [
            "inline_keyboard" => [
                [
                    ["text" => "🚀 دخول للبوت", "web_app" => ["url" => $config['webapp_url']]]
                ]
            ]
        ];

        $msg = "👋 أهلاً بك يا *$first_name*!\n";
        if ($referrer_id && $user == false) {//
            $stmt = $db->prepare("SELECT firstname FROM users WHERE telegram_id = ?");
            $stmt->execute([$referrer_id]);
            $exists = $stmt->fetchColumn();
            
            $msg .= "🎉 لقد انضممت عبر دعوة المستخدم  `$exists`.\n";
        }
        $msg .= "\nاضغط الزر بالأسفل لبدء الاستخدام.";

        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $msg,
            'reply_markup' => json_encode($button),
            'parse_mode' => "Markdown"
        ]);
    }
}

?>
