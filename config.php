<?php
// config.php
return [
  'bot_token' => '8083012485:AAHD7QsrsoLuX9TbLMNp2SW7kSfQb3FDaao',
  'YourBotUsername' => 'freesusdtbot',
  'admin_id'  => 5139923260, // Telegram ID للأدمن
  'webapp_url' => 'https://2a0eab44-d9ea-4fa0-a63d-feea08499c67-00-3rnj0g8hx1yrw.janeway.replit.dev/', // ضع هنا رابط الويب اب"
   // 'm' :"https://your-domain.com/tonconnect-manifest.json",

  'referral_bonus' => [ // النسب لكل مستوى
      1 => 0.15, // المستوى الأول 15%
      2 => 0.07, // المستوى الثاني 7%
      3 => 0.02  // المستوى الثالث 2%
  ],
  // DB path
  'db_path' => __DIR__ . '/database.sqlite',

  // التعدين
  'base_rate' => 0.001,       // GEN per second per 1.0 mining_power
  'gen_price' => 0.000001,    // 1 GEN = 0.000001 USDT

  // زيادة الباور لكل دعوة مباشرة
  'per_invite_power' => 0.1,  // كل دعوة تضيف +0.1 إلى mining_power (قابل للتعديل)
  
  // (مكافأة الانضمام (دخول المستخدم الجديد
  'default_mining_duration' => 4 * 3600,
  'register_balance' => 0,
  'register_gen_balance' => 0,
  'register_power' => 500,    

  //مكافئة الدعوات  (مكافأة المستخدمين الذين يدعون مستخدمين جدد)
  'referral_reward_power' => 100  // مكافأة قوة تعدين للداعي الأول


  
];





//     <?php
//     /* ========================================
//        Configuration File - ملف الإعدادات
//     ======================================== */

//     return [
//         // Database
//         'db_path' => __DIR__ . '/database.db',

//         // Telegram Bot
//         'bot_token' => 'YOUR_BOT_TOKEN_HERE',
//         'YourBotUsername' => '@YourBotUsername',
//         'admin_user_id' => 123456789, // Telegram ID للأدمن
//         'admin_chat_id' => 123456789, // Chat ID للإشعارات

//         // Wallet Settings
//         'project_ton_wallet' => 'EQD...YourTONWalletAddress',
//         'project_usdt_wallet' => 'TRC20_USDT_Wallet_Address',

//         // USDT Contract on TON
//         'usdt_contract_address' => 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs',

//         // Limits
//         'min_deposit_usdt' => 1,
//         'min_withdraw_usdt' => 5,
//         'max_withdraw_multiplier' => 2, // ضعف رصيد الإحالات

//         // Fees
//         'withdraw_fee_percent' => 0, // 0% رسوم سحب
//         'deposit_fee_percent' => 0,  // 0% رسوم إيداع

//         // Prices
//         'GEN_PRICE' => 0.000035, // سعر GEN بالدولار
//         'gen_price' => 0.000035,

//         // Mining Settings
//         'default_mining_duration' => 14400, // 4 ساعات
//         'register_balance' => 0,
//         'register_gen_balance' => 20,
//         'register_power' => 10,

//         // Referral Rewards
//         'referral_reward_power' => 5,
//         'referral_level1_percent' => 0.5,  // 50%
//         'referral_level2_percent' => 0.3,  // 30%
//         'referral_level3_percent' => 0.2,  // 20%

//         // TonConnect
//         'tonconnect_manifest_url' => 'https://your-domain.com/tonconnect-manifest.json',

//         // Maintenance
//         'maintenance_mode' => false,
//         'maintenance_message' => 'النظام قيد الصيانة، سنعود قريباً',

//         // Auto Approval (خطير - للتطوير فقط)
//         'auto_approve_deposits' => false, // اجعلها false في الإنتاج
//         'auto_approve_withdraws' => false,

//         // Blockchain Verification
//         'verify_transactions' => true, // التحقق من المعاملات على Blockchain
//         'required_confirmations' => 3, // عدد التأكيدات المطلوبة

//         // API Keys (إذا كنت تستخدم خدمات خارجية)
//         'tonscan_api_key' => '',
//         'tonapi_key' => '',

//         // Notifications
//         'send_telegram_notifications' => true,
//         'notify_on_deposit' => true,
//         'notify_on_withdraw' => true,
//         'notify_on_support_message' => true,

//         // Support
//         'support_chat_enabled' => true,
//         'support_chat_hours' => '24/7',
//         'support_telegram' => '@YourSupportBot',

//         // Security
//         'max_login_attempts' => 5,
//         'login_timeout' => 300, // 5 دقائق
//         'session_lifetime' => 3600, // ساعة

//         // Rate Limiting
//         'max_deposits_per_day' => 10,
//         'max_withdraws_per_day' => 3,
//         'max_support_messages_per_hour' => 10,

//         // Logging
//         'log_transactions' => true,
//         'log_wallet_connections' => true,
//         'log_api_requests' => true,
//         'log_errors' => true,
//         'log_path' => __DIR__ . '/logs/',

//         // Cache
//         'cache_enabled' => true,
//         'cache_lifetime' => 300, // 5 دقائق

//         // Features
//         'features' => [
//             'mining' => true,
//             'tasks' => true,
//             'referrals' => true,
//             'wallet' => true,
//             'exchange' => false, // تبادل GEN/USDT
//             'staking' => false,  // رهن العملات
//             'nft' => false,      // NFTs
//         ]
//     ];
//     ?>
// \*