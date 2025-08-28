<?php
// Set security headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configure error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

// Branding variable
$brand = 'Tanga';

// Static configuration
$config = [
    'firebase' => [
        'url' => 'https://code-48189-default-rtdb.firebaseio.com',
        'secret' => 'X7SecYcX4aDyPPt88sh2YaLkgVdisuOZZVuTktJM'
    ],
    'telegram' => [
        'bot_token' => '7679095719:AAGchunkQuC3SuMtCLVynNGU-7e_7Yp_Vms',
        'bot_username' => '@davronov_v1_bot'
    ],
    'app' => [
        'mini_app_url' => 'https://c694.coresuz.ru/vip/',
        'admin_web_url' => 'https://c694.coresuz.ru/admin/',
        'avatar_url' => 'https://c694.coresuz.ru/avatars',
        'avatar_path' => __DIR__ . '/avatars/',
        'welcome_image' => 'https://global.discourse-cdn.com/tnation/original/4X/8/e/f/8efd723a66357e02d1c6cc5b2a43b8083c0a8f19.jpeg'
    ],
    'sqlite' => [
        'db_path' => __DIR__ . '/bot_data.db'
    ],
    'mega_admin_id' => '6547102814'
];

// Create avatar directory
if (!is_dir($config['app']['avatar_path']) && !mkdir($config['app']['avatar_path'], 0755, true)) {
    error_log('Failed to create avatar directory: ' . $config['app']['avatar_path']);
    exit(json_encode(['status' => 'error', 'message' => 'Avatar directory creation failed']));
}

// Initialize SQLite database
try {
    $db = new PDO('sqlite:' . $config['sqlite']['db_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // $db->exec("PRAGMA foreign_keys = OFF;"); // Uncomment for debugging only
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id TEXT PRIMARY KEY,
            joined_at INTEGER NOT NULL,
            last_active INTEGER
        );
        CREATE TABLE IF NOT EXISTS statistics (
            stat_id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            activity_date INTEGER NOT NULL,
            activity_type TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        );
        CREATE TABLE IF NOT EXISTS admins (
            admin_id TEXT PRIMARY KEY,
            role TEXT CHECK(role IN ('mega_admin', 'sub_admin')),
            added_by TEXT NOT NULL,
            added_at INTEGER NOT NULL,
            last_active INTEGER,
            FOREIGN KEY (added_by) REFERENCES admins(admin_id)
        );
        CREATE TABLE IF NOT EXISTS podcasts (
            podcast_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            image_url TEXT,
            button_text TEXT,
            button_url TEXT,
            sent_by TEXT NOT NULL,
            sent_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            target_type TEXT NOT NULL,
            target_id TEXT,
            FOREIGN KEY (sent_by) REFERENCES admins(admin_id)
        );
        CREATE TABLE IF NOT EXISTS config_settings (
            setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            updated_by TEXT NOT NULL,
            updated_at INTEGER NOT NULL,
            FOREIGN KEY (updated_by) REFERENCES admins(admin_id)
        );
        CREATE TABLE IF NOT EXISTS podcast_sessions (
            session_id TEXT PRIMARY KEY,
            admin_id TEXT NOT NULL,
            step TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
        );
        CREATE TABLE IF NOT EXISTS admin_sessions (
            session_id TEXT PRIMARY KEY,
            admin_id TEXT NOT NULL,
            step TEXT NOT NULL,
            data TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
        );
    ");
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO admins (admin_id, role, added_by, added_at, last_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$config['mega_admin_id'], 'mega_admin', $config['mega_admin_id'], time(), time()]);
} catch (PDOException $e) {
    error_log('SQLite Error: ' . $e->getMessage());
    exit(json_encode(['status' => 'error', 'message' => 'Database initialization failed']));
}

// Load dynamic config
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM config_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'firebase_url') {
            $config['firebase']['url'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'firebase_secret') {
            $config['firebase']['secret'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'mini_app_url') {
            $config['app']['mini_app_url'] = $row['setting_value'];
        } elseif ($row['setting_key'] === 'admin_web_url') {
            $config['app']['admin_web_url'] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log('Config Load Error: ' . $e->getMessage());
}

// Helper functions
function generateAuthKey() {
    return bin2hex(random_bytes(32));
}

function makeFirebaseRequest($method, $path, $data, $config) {
    $url = $config['firebase']['url'] . $path . '.json?auth=' . $config['firebase']['secret'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    if ($response === false) {
        error_log('Firebase Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        error_log("Firebase HTTP Error: $httpCode for $path");
        return null;
    }
    
    return json_decode($response, true);
}

function sendTelegramMessage($method, $data, $config) {
    $ch = curl_init("https://api.telegram.org/bot{$config['telegram']['bot_token']}/$method");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => is_array($data) ? http_build_query($data) : $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        error_log('Telegram Error: ' . curl_error($ch));
        curl_close($ch);
        return ['ok' => false, 'error' => 'Telegram so'rovi muvaffaqiyatsiz'];
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    if (!$result['ok']) {
        error_log('Telegram API Error: ' . json_encode($result));
    }
    return $result;
}

function processUserAvatar($userId, $telegramId, $config) {
    $avatarFile = $config['app']['avatar_path'] . $userId . '.jpg';
    if (file_exists($avatarFile)) {
        return $config['app']['avatar_url'] . '/' . $userId . '.jpg';
    }
    
    $photos = sendTelegramMessage('getUserProfilePhotos', ['user_id' => $telegramId, 'limit' => 1], $config);
    if (!$photos['ok'] || empty($photos['result']['photos'][0])) {
        return '';
    }
    
    $fileId = $photos['result']['photos'][0][count($photos['result']['photos'][0]) - 1]['file_id'];
    $fileInfo = sendTelegramMessage('getFile', ['file_id' => $fileId], $config);
    if (!$fileInfo['ok'] || empty($fileInfo['result']['file_path'])) {
        return '';
    }
    
    $fileContent = file_get_contents("https://api.telegram.org/file/bot{$config['telegram']['bot_token']}/{$fileInfo['result']['file_path']}");
    if ($fileContent === false || !file_put_contents($avatarFile, $fileContent)) {
        error_log("Failed to save avatar for user: $userId");
        return '';
    }
    
    return $config['app']['avatar_url'] . '/' . $userId . '.jpg';
}

function ensureUserExists($db, $userId) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $stmt->execute([(string)$userId]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO users (user_id, joined_at, last_active) VALUES (?, ?, ?)");
            $stmt->execute([(string)$userId, (int)time(), (int)time()]);
            return true;
        }
        return true;
    } catch (PDOException $e) {
        error_log('EnsureUserExists Error: ' . $e->getMessage() . ' | UserID: ' . $userId);
        return false;
    }
}

function logUserActivity($db, $userId, $activityType) {
    try {
        if (!ensureUserExists($db, $userId)) {
            error_log("User $userId does not exist in users table");
            return;
        }
        $stmt = $db->prepare("INSERT INTO statistics (stat_id, user_id, activity_date, activity_type) VALUES (?, ?, ?, ?)");
        $values = [
            bin2hex(random_bytes(16)),
            (string)$userId,
            (int)time(),
            (string)$activityType
        ];
        $stmt->execute($values);
    } catch (PDOException $e) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $stmt->execute([(string)$userId]);
        $userExists = $stmt->fetchColumn() > 0;
        error_log('Activity Log Error: ' . $e->getMessage() . ' | Values: ' . json_encode($values) . ' | UserExists: ' . ($userExists ? 'Yes' : 'No'));
    }
}

function isAdmin($userId, $db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE admin_id = ?");
        $stmt->execute([(string)$userId]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('IsAdmin Error: ' . $e->getMessage());
        return false;
    }
}

function getAdminRole($userId, $db) {
    try {
        $stmt = $db->prepare("SELECT role FROM admins WHERE admin_id = ?");
        $stmt->execute([(string)$userId]);
        return $stmt->fetchColumn() ?: false;
    } catch (PDOException $e) {
        error_log('GetAdminRole Error: ' . $e->getMessage());
        return false;
    }
}

function getPodcastSession($adminId, $db) {
    try {
        $stmt = $db->prepare("SELECT * FROM podcast_sessions WHERE admin_id = ?");
        $stmt->execute([(string)$adminId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('GetPodcastSession Error: ' . $e->getMessage());
        return false;
    }
}

function savePodcastSession($adminId, $step, $data, $db) {
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO podcast_sessions (session_id, admin_id, step, data, created_at) VALUES (?, ?, ?, ?, ?)");
        $sessionId = (string)$adminId . '_' . time();
        $stmt->execute([$sessionId, (string)$adminId, $step, json_encode($data, JSON_UNESCAPED_UNICODE), (int)time()]);
    } catch (PDOException $e) {
        error_log('Podcast Session Save Error: ' . $e->getMessage());
    }
}

function deletePodcastSession($adminId, $db) {
    try {
        $stmt = $db->prepare("DELETE FROM podcast_sessions WHERE admin_id = ?");
        $stmt->execute([(string)$adminId]);
    } catch (PDOException $e) {
        error_log('Podcast Session Delete Error: ' . $e->getMessage());
    }
}

function getAdminSession($adminId, $db) {
    try {
        $stmt = $db->prepare("SELECT * FROM admin_sessions WHERE admin_id = ?");
        $stmt->execute([(string)$adminId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('GetAdminSession Error: ' . $e->getMessage());
        return false;
    }
}

function saveAdminSession($adminId, $step, $data, $db) {
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO admin_sessions (session_id, admin_id, step, data, created_at) VALUES (?, ?, ?, ?, ?)");
        $sessionId = (string)$adminId . '_admin_' . time();
        $stmt->execute([$sessionId, (string)$adminId, $step, json_encode($data, JSON_UNESCAPED_UNICODE), (int)time()]);
    } catch (PDOException $e) {
        error_log('Admin Session Save Error: ' . $e->getMessage());
    }
}

function deleteAdminSession($adminId, $db) {
    try {
        $stmt = $db->prepare("DELETE FROM admin_sessions WHERE admin_id = ?");
        $stmt->execute([(string)$adminId]);
    } catch (PDOException $e) {
        error_log('Admin Session Delete Error: ' . $e->getMessage());
    }
}

// Main logic
try {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        error_log('Empty input received');
        exit(json_encode(['ok' => true]));
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        error_log('Invalid JSON: ' . json_last_error_msg() . ' | Input: ' . substr($rawInput, 0, 1000));
        exit(json_encode(['ok' => true]));
    }
    
    $message = $input['message'] ?? $input['callback_query']['message'] ?? null;
    if (!$message || !isset($message['chat']['id'])) {
        error_log('Invalid message format | Input: ' . substr($rawInput, 0, 1000));
        exit(json_encode(['ok' => true]));
    }
    
    $chatId = $message['chat']['id'];
    $userId = (string)$chatId;
    $text = $message['text'] ?? '';
    
    // Log user activity
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO users (user_id, joined_at, last_active) VALUES (?, ?, ?)");
        $stmt->execute([(string)$userId, (int)time(), (int)time()]);
        $stmt = $db->prepare("UPDATE users SET last_active = ? WHERE user_id = ?");
        $stmt->execute([(int)time(), (string)$userId]);
        logUserActivity($db, $userId, 'message_received');
    } catch (PDOException $e) {
        error_log('User Log Error: ' . $e->getMessage() . ' | UserID: ' . $userId);
    }
    
    // Persistent keyboard
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📲 {$brand}'ga Kirish", 'callback_data' => 'open_mini_app']],
            [['text' => '📎 Havolani Nusxalash', 'callback_data' => 'copy_ref'], ['text' => '📤 Ulashish', 'switch_inline_query' => "{$brand}'ga qo'shiling! 🍊nhttps://t.me/{$config['telegram']['bot_username']}?start=$userId"]]
        ],
        'resize_keyboard' => true,
        'persistent' => true
    ];
    
    if (isAdmin($userId, $db)) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '📋 Admin Panel', 'callback_data' => 'admin_panel'],
            ['text' => '🎙 Podcast Yuborish', 'callback_data' => 'send_podcast']
        ];
    }
    
    // Handle /start
    if (strpos($text, '/start') === 0) {
        $response = sendTelegramMessage('sendMessage', [
            'chat_id' => $chatId,
            'text' => "👋 Assalomu alaykum! {$brand}'ga xush kelibsiz! ⏳",
            'parse_mode' => 'HTML'
        ], $config);
        
        if (!$response['ok']) {
            throw new Exception('Initial message failed: ' . json_encode($response));
        }
        
        $userData = makeFirebaseRequest('GET', "/users/$userId", null, $config);
        $authKey = $userData['authKey'] ?? null;
        $isNewUser = ($userData === null || empty($userData['authKey']));
        $urlParams = "id=$userId";
        
        $referralId = null;
        if (preg_match('//starts+(w+)/', $text, $matches)) {
            $referralId = $matches[1];
        }
        
        if ($isNewUser) {
            $authKey = generateAuthKey();
            $avatarUrl = processUserAvatar($userId, $chatId, $config);
            $userData = [
                'id' => $userId,
                'firstName' => $message['from']['first_name'] ?? 'Foydalanuvchi',
                'lastName' => $message['from']['last_name'] ?? '',
                'authKey' => $authKey,
                'avatarUrl' => $avatarUrl,
                'joinedAt' => time(),
                'lastActive' => time()
            ];
            if (makeFirebaseRequest('PUT', "/users/$userId", $userData, $config) === null) {
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Ma'lumotlarni saqlashda xatolik.',
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ], $config);
                exit(json_encode(['status' => 'error']));
            }
            $urlParams .= "&authkey=" . urlencode($authKey);
            if ($referralId) {
                $urlParams .= "&ref=" . urlencode($referralId);
            }
            $welcomeMessage = "🎉 {$brand}'ga xush kelibsiz!nnQuyidagi tugmalardan foydalaning:";
        } else {
            if (!$authKey) {
                $authKey = generateAuthKey();
                $patchData = ['authKey' => $authKey, 'lastActive' => time()];
            } else {
                $patchData = ['lastActive' => time()];
            }
            if (makeFirebaseRequest('PATCH', "/users/$userId", $patchData, $config) === null) {
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => '⚠️ Ma'lumotlarni yangilashda xatolik.',
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ], $config);
                exit(json_encode(['status' => 'error']));
            }
            $urlParams .= "&authkey=" . urlencode($authKey);
            $welcomeMessage = "✨ {$brand}'ga qaytganingiz bilan!nnQuyidagi tugmalardan foydalaning:";
        }
        
        $keyboard['inline_keyboard'][0][0]['url'] = $config['app']['mini_app_url'] . "?$urlParams";
        
        $response = sendTelegramMessage('sendPhoto', [
            'chat_id' => $chatId,
            'photo' => $config['app']['welcome_image'],
            'caption' => $welcomeMessage,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ], $config);
        
        if (!$response['ok']) {
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => '⚠️ Xatolik yuz berdi. Qaytadan urinib ko'ring.',
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ], $config);
            exit(json_encode(['status' => 'error']));
        }
    }
    
    // Handle admin commands and callbacks
    if (isAdmin($userId, $db)) {
        // Admin panel
        if ($text === '/admin' || (isset($input['callback_query']['data']) && $input['callback_query']['data'] === 'admin_panel')) {
            if (isset($input['callback_query']['id'])) {
                sendTelegramMessage('answerCallbackQuery', ['callback_query_id' => $input['callback_query']['id']], $config);
            }
            deleteAdminSession($userId, $db);
            $adminKeyboard = [
                'inline_keyboard' => [
                    [['text' => '➕ Sub-Admin Qo'shish', 'callback_data' => 'add_admin']],
                    [['text' => '➖ Sub-Admin O'chirish', 'callback_data' => 'remove_admin']],
                    [['text' => '⚙️ Konfiguratsiyani Yangilash', 'callback_data' => 'update_config']],
                    [['text' => '📊 Statistika', 'callback_data' => 'stats']],
                    [['text' => '🔙 Orqaga', 'callback_data' => 'back_to_main']]
                ]
            ];
            if (getAdminRole($userId, $db) !== 'mega_admin') {
                unset($adminKeyboard['inline_keyboard'][0], $adminKeyboard['inline_keyboard'][1]);
                $adminKeyboard['inline_keyboard'] = array_values($adminKeyboard['inline_keyboard']);
            }
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => "📋 <b>Admin Paneli</b>nnAmalni tanlang:",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($adminKeyboard)
            ], $config);
        }
        
        // Admin session handling
        $adminSession = getAdminSession($userId, $db);
        if ($adminSession && !isset($input['callback_query'])) {
            $sessionData = json_decode($adminSession['data'], true);
            $step = $adminSession['step'];
            
            if ($step === 'add_admin_id') {
                if (preg_match('/^d+$/', $text)) {
                    try {
                        $stmt = $db->prepare("INSERT INTO admins (admin_id, role, added_by, added_at, last_active) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([(string)$text, 'sub_admin', (string)$userId, (int)time(), (int)time()]);
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "✅ Sub-admin ($text) qo'shildi!",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $text,
                            'text' => "🎉 Siz {$brand} botining sub-admini bo'ldingiz! /admin buyrug'i bilan panelga kiring.",
                            'parse_mode' => 'HTML'
                        ], $config);
                        deleteAdminSession($userId, $db);
                    } catch (PDOException $e) {
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "⚠️ Xatolik: " . htmlspecialchars($e->getMessage()),
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        deleteAdminSession($userId, $db);
                    }
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "💬 Iltimos, faqat raqamlardan iborat ID kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'remove_admin_id') {
                if (preg_match('/^d+$/', $text)) {
                    try {
                        $stmt = $db->prepare("DELETE FROM admins WHERE admin_id = ? AND role = 'sub_admin'");
                        $stmt->execute([(string)$text]);
                        if ($stmt->rowCount() > 0) {
                            sendTelegramMessage('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => "✅ Sub-admin ($text) o'chirildi!",
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode($keyboard)
                            ], $config);
                            sendTelegramMessage('sendMessage', [
                                'chat_id' => $text,
                                'text' => "ℹ️ Siz {$brand} sub-admin ro'yxatidan o'chirildingiz.",
                                'parse_mode' => 'HTML'
                            ], $config);
                        } else {
                            sendTelegramMessage('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => "⚠️ Sub-admin topilmadi!",
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode($keyboard)
                            ], $config);
                        }
                        deleteAdminSession($userId, $db);
                    } catch (PDOException $e) {
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "⚠️ Xatolik: " . htmlspecialchars($e->getMessage()),
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        deleteAdminSession($userId, $db);
                    }
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "💬 Iltimos, faqat raqamlardan iborat ID kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'update_config') {
                if (preg_match('/^firebase_url=(S+)s+firebase_secret=(S+)s+mini_app_url=(S+)$/', $text, $matches)) {
                    $settings = [
                        'firebase_url' => $matches[1],
                        'firebase_secret' => $matches[2],
                        'mini_app_url' => $matches[3]
                    ];
                    try {
                        foreach ($settings as $key => $value) {
                            $stmt = $db->prepare("INSERT OR REPLACE INTO config_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$key, $value, (string)$userId, (int)time()]);
                        }
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "✅ Konfiguratsiya yangilandi!",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        deleteAdminSession($userId, $db);
                    } catch (PDOException $e) {
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "⚠️ Xatolik: " . htmlspecialchars($e->getMessage()),
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        deleteAdminSession($userId, $db);
                    }
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "⚙️ To'g'ri formatda kiriting:nfirebase_url=https://example.com firebase_secret=secret mini_app_url=https://app.com",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            }
        }
        
        // Podcast creation
        if ($text === '/sendpodcast' || (isset($input['callback_query']['data']) && $input['callback_query']['data'] === 'send_podcast')) {
            if (isset($input['callback_query']['id'])) {
                sendTelegramMessage('answerCallbackQuery', ['callback_query_id' => $input['callback_query']['id']], $config);
            }
            deletePodcastSession($userId, $db);
            $sessionData = [
                'target_type' => '',
                'target_id' => '',
                'image' => false,
                'image_url' => '',
                'image_file' => '',
                'title' => '',
                'content' => '',
                'button' => false,
                'button_text' => '',
                'button_url' => ''
            ];
            savePodcastSession($userId, 'target_type', $sessionData, $db);
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => "🎙 <b>Podcast Yuborish</b>nnPodcast qaysi foydalanuvchilarga yuborilsin?",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '👥 Barcha foydalanuvchilar', 'callback_data' => 'target_all']],
                        [['text' => '📅 Oxirgi kun online', 'callback_data' => 'target_last_day']],
                        [['text' => '📅 Oxirgi hafta online', 'callback_data' => 'target_last_week']],
                        [['text' => '📅 Oxirgi oy online', 'callback_data' => 'target_last_month']],
                        [['text' => '👤 Muayyan foydalanuvchi', 'callback_data' => 'target_specific']],
                        [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                    ]
                ])
            ], $config);
        }
        
        // Podcast session handling
        $podcastSession = getPodcastSession($userId, $db);
        if ($podcastSession && !isset($input['callback_query'])) {
            $sessionData = json_decode($podcastSession['data'], true);
            $step = $podcastSession['step'];
            
            if ($step === 'target_specific_id') {
                if (preg_match('/^d+$/', $text)) {
                    $sessionData['target_id'] = (string)$text;
                    $sessionData['target_type'] = 'specific';
                    savePodcastSession($userId, 'image', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📷 <b>Rasm yuborasizmi?</b>",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '✅ Ha', 'callback_data' => 'image_yes']],
                                [['text' => '❌ Yo'q', 'callback_data' => 'image_no']],
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "💬 Iltimos, faqat raqamlardan iborat ID kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'image_upload') {
                if (isset($message['photo'])) {
                    $photo = end($message['photo']);
                    $fileInfo = sendTelegramMessage('getFile', ['file_id' => $photo['file_id']], $config);
                    if ($fileInfo['ok'] && !empty($fileInfo['result']['file_path'])) {
                        $fileContent = file_get_contents("https://api.telegram.org/file/bot{$config['telegram']['bot_token']}/{$fileInfo['result']['file_path']}");
                        $imageFile = $config['app']['avatar_path'] . $userId . '_podcast_' . time() . '.jpg';
                        if ($fileContent !== false && file_put_contents($imageFile, $fileContent)) {
                            $sessionData['image_url'] = $config['app']['avatar_url'] . '/' . basename($imageFile);
                            $sessionData['image_file'] = $imageFile;
                            $sessionData['image'] = true;
                            savePodcastSession($userId, 'title', $sessionData, $db);
                            sendTelegramMessage('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => "✅ Rasm qabul qilindi!nn📝 Podcast <b>sarlavhasini</b> kiriting:",
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode(['force_reply' => true])
                            ], $config);
                        } else {
                            sendTelegramMessage('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => "⚠️ Rasmni saqlashda xatolik. Qaytadan yuboring:",
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode([
                                    'inline_keyboard' => [
                                        [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                                    ]
                                ])
                            ], $config);
                        }
                    } else {
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "⚠️ Rasm yuklashda xatolik. Qaytadan yuboring:",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                                ]
                            ])
                        ], $config);
                    }
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📷 Iltimos, rasm yuboring:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                }
            } elseif ($step === 'title') {
                if (trim($text) !== '') {
                    $sessionData['title'] = trim($text);
                    savePodcastSession($userId, 'content', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📝 <b>Mazmunni</b> kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📝 Iltimos, sarlavha kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'content') {
                if (trim($text) !== '') {
                    $sessionData['content'] = trim($text);
                    savePodcastSession($userId, 'button', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📌 <b>Tugma qo'shasizmi?</b>",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '✅ Ha', 'callback_data' => 'button_yes']],
                                [['text' => '❌ Yo'q', 'callback_data' => 'button_no']],
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📝 Iltimos, mazmun kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'button_text') {
                if (trim($text) !== '') {
                    $sessionData['button_text'] = trim($text);
                    savePodcastSession($userId, 'button_url', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "🔗 <b>Tugma URL</b> manzilini kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📌 Iltimos, tugma matnini kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            } elseif ($step === 'button_url') {
                if (filter_var($text, FILTER_VALIDATE_URL)) {
                    $sessionData['button_url'] = $text;
                    savePodcastSession($userId, 'confirm', $sessionData, $db);
                    $confirmText = "📢 <b>Podcast ma'lumotlari:</b>nn";
                    $confirmText .= "<b>Sarlavha:</b> " . htmlspecialchars($sessionData['title']) . "n";
                    $confirmText .= "<b>Mazmun:</b> " . htmlspecialchars($sessionData['content']) . "n";
                    $confirmText .= "<b>Yuboriladi:</b> " . ($sessionData['target_type'] === 'specific' ? "ID: " . htmlspecialchars($sessionData['target_id']) : str_replace('_', ' ', ucwords($sessionData['target_type']))) . "n";
                    $confirmText .= "<b>Rasm:</b> " . ($sessionData['image'] ? "Ha" : "Yo'q") . "n";
                    $confirmText .= "<b>Tugma:</b> Han";
                    $confirmText .= "<b>Tugma matni:</b> " . htmlspecialchars($sessionData['button_text']) . "n";
                    $confirmText .= "<b>Tugma URL:</b> " . htmlspecialchars($sessionData['button_url']) . "n";
                    $confirmText .= "nTasdiqlaysizmi?";
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => $confirmText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '✅ Tasdiqlash', 'callback_data' => 'confirm_yes']],
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                } else {
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "🔗 Iltimos, to'g'ri URL kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                }
            }
        }
    }
    
    // Handle callback queries
    if (isset($input['callback_query']['data'])) {
        $callbackData = $input['callback_query']['data'];
        $callbackId = $input['callback_query']['id'];
        
        sendTelegramMessage('answerCallbackQuery', ['callback_query_id' => $callbackId], $config);
        
        if ($callbackData === 'open_mini_app') {
            $userData = makeFirebaseRequest('GET', "/users/$userId", null, $config);
            $authKey = $userData['authKey'] ?? generateAuthKey();
            $urlParams = "id=$userId&authkey=" . urlencode($authKey);
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => "📲 {$brand} ilovasiga o'tdingiz!nnOrqaga qaytish uchun tugmani bosing:",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '🔙 Orqaga', 'callback_data' => 'back_to_main']]
                    ]
                ])
            ], $config);
        } elseif ($callbackData === 'copy_ref') {
            $refLink = "https://t.me/{$config['telegram']['bot_username']}?start=$userId";
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => "✅ Havola nusxalandi!n$refLink",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ], $config);
        } elseif ($callbackData === 'back_to_main') {
            sendTelegramMessage('sendMessage', [
                'chat_id' => $chatId,
                'text' => "🔻 Asosiy menyuga qaytdingiz.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ], $config);
        } elseif (isAdmin($userId, $db)) {
            // Admin panel callbacks
            if ($callbackData === 'add_admin' && getAdminRole($userId, $db) === 'mega_admin') {
                saveAdminSession($userId, 'add_admin_id', [], $db);
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "➕ Yangi sub-admin ID'sini kiriting:",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['force_reply' => true])
                ], $config);
            } elseif ($callbackData === 'remove_admin' && getAdminRole($userId, $db) === 'mega_admin') {
                saveAdminSession($userId, 'remove_admin_id', [], $db);
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "➖ O'chiriladigan sub-admin ID'sini kiriting:",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['force_reply' => true])
                ], $config);
            } elseif ($callbackData === 'update_config') {
                saveAdminSession($userId, 'update_config', [], $db);
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => "⚙️ Konfiguratsiyani kiriting:nfirebase_url=https://example.com firebase_secret=secret mini_app_url=https://app.com",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['force_reply' => true])
                ], $config);
            } elseif ($callbackData === 'stats') {
                $stats = "📊 <b>Statistika</b>nn";
                $stats .= "<b>Foydalanuvchilar:</b> " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "n";
                $stats .= "<b>Podcastlar:</b> " . $db->query("SELECT COUNT(*) FROM podcasts")->fetchColumn() . "n";
                $stats .= "<b>Adminlar:</b>n";
                $stmt = $db->query("SELECT admin_id, role, added_at FROM admins ORDER BY added_at DESC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $stats .= "ID: {$row['admin_id']} (" . ($row['role'] === 'mega_admin' ? 'Mega Admin' : 'Sub Admin') . ")n";
                    $stats .= "Qo'shilgan: " . date('Y-m-d H:i:s', $row['added_at']) . "nn";
                }
                $stats .= "<b>So'nggi faolliklar:</b>n";
                $stmt = $db->query("SELECT user_id, activity_type, activity_date FROM statistics ORDER BY activity_date DESC LIMIT 5");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $stats .= "ID: {$row['user_id']} | {$row['activity_type']} | " . date('Y-m-d H:i:s', $row['activity_date']) . "n";
                }
                sendTelegramMessage('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $stats,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ], $config);
            }
            
            // Podcast callbacks
            $podcastSession = getPodcastSession($userId, $db);
            if ($podcastSession) {
                $sessionData = json_decode($podcastSession['data'], true);
                
                if (in_array($callbackData, ['target_all', 'target_last_day', 'target_last_week', 'target_last_month', 'target_specific'])) {
                    if ($callbackData === 'target_specific') {
                        $sessionData['target_type'] = 'pending_specific';
                        savePodcastSession($userId, 'target_specific_id', $sessionData, $db);
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "💬 Foydalanuvchi ID'sini kiriting:",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode(['force_reply' => true])
                        ], $config);
                    } else {
                        $targetMap = [
                            'target_all' => 'all',
                            'target_last_day' => 'last_day',
                            'target_last_week' => 'last_week',
                            'target_last_month' => 'last_month'
                        ];
                        $sessionData['target_type'] = $targetMap[$callbackData];
                        savePodcastSession($userId, 'image', $sessionData, $db);
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "📷 <b>Rasm yuborasizmi?</b>",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [
                                    [['text' => '✅ Ha', 'callback_data' => 'image_yes']],
                                    [['text' => '❌ Yo'q', 'callback_data' => 'image_no']],
                                    [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                                ]
                            ])
                        ], $config);
                    }
                } elseif ($callbackData === 'image_yes') {
                    $sessionData['image'] = true;
                    savePodcastSession($userId, 'image_upload', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📷 Iltimos, podcast uchun rasm yuboring:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                } elseif ($callbackData === 'image_no') {
                    $sessionData['image'] = false;
                    $sessionData['image_url'] = '';
                    $sessionData['image_file'] = '';
                    savePodcastSession($userId, 'title', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📝 Podcast <b>sarlavhasini</b> kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                } elseif ($callbackData === 'button_yes') {
                    $sessionData['button'] = true;
                    savePodcastSession($userId, 'button_text', $sessionData, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "📌 Tugma matnini kiriting:",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['force_reply' => true])
                    ], $config);
                } elseif ($callbackData === 'button_no') {
                    $sessionData['button'] = false;
                    $sessionData['button_text'] = '';
                    $sessionData['button_url'] = '';
                    savePodcastSession($userId, 'confirm', $sessionData, $db);
                    $confirmText = "📢 <b>Podcast ma'lumotlari:</b>nn";
                    $confirmText .= "<b>Sarlavha:</b> " . htmlspecialchars($sessionData['title']) . "n";
                    $confirmText .= "<b>Mazmun:</b> " . htmlspecialchars($sessionData['content']) . "n";
                    $confirmText .= "<b>Yuboriladi:</b> " . ($sessionData['target_type'] === 'specific' ? "ID: " . htmlspecialchars($sessionData['target_id']) : str_replace('_', ' ', ucwords($sessionData['target_type']))) . "n";
                    $confirmText .= "<b>Rasm:</b> " . ($sessionData['image'] ? "Ha" : "Yo'q") . "n";
                    $confirmText .= "<b>Tugma:</b> Yo'qn";
                    $confirmText .= "nTasdiqlaysizmi?";
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => $confirmText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '✅ Tasdiqlash', 'callback_data' => 'confirm_yes']],
                                [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_podcast']]
                            ]
                        ])
                    ], $config);
                } elseif ($callbackData === 'confirm_yes') {
                    try {
                        $stmt = $db->prepare("INSERT INTO podcasts (title, content, image_url, button_text, button_url, sent_by, sent_at, created_at, target_type, target_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $sessionData['title'],
                            $sessionData['content'],
                            $sessionData['image_url'] ?? '',
                            $sessionData['button_text'] ?? '',
                            $sessionData['button_url'] ?? '',
                            (string)$userId,
                            (int)time(),
                            (int)time(),
                            $sessionData['target_type'],
                            $sessionData['target_id'] ?? ''
                        ]);
                        
                        $users = [];
                        $time = time();
                        if ($sessionData['target_type'] === 'all') {
                            $users = $db->query("SELECT user_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($sessionData['target_type'] === 'last_day') {
                            $users = $db->query("SELECT user_id FROM users WHERE last_active >= " . ($time - 86400))->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($sessionData['target_type'] === 'last_week') {
                            $users = $db->query("SELECT user_id FROM users WHERE last_active >= " . ($time - 604800))->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($sessionData['target_type'] === 'last_month') {
                            $users = $db->query("SELECT user_id FROM users WHERE last_active >= " . ($time - 2592000))->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($sessionData['target_type'] === 'specific') {
                            $users = [$sessionData['target_id']];
                        }
                        
                        $messageData = [
                            'chat_id' => '',
                            'caption' => "<b>{$sessionData['title']}</b>nn{$sessionData['content']}",
                            'parse_mode' => 'HTML'
                        ];
                        if ($sessionData['button']) {
                            $messageData['reply_markup'] = json_encode([
                                'inline_keyboard' => [
                                    [['text' => $sessionData['button_text'], 'url' => $sessionData['button_url']]]
                                ]
                            ]);
                        }
                        
                        foreach ($users as $uid) {
                            $messageData['chat_id'] = $uid;
                            if ($sessionData['image_url']) {
                                $messageData['photo'] = $sessionData['image_url'];
                                $response = sendTelegramMessage('sendPhoto', $messageData, $config);
                            } else {
                                $messageData['text'] = $messageData['caption'];
                                unset($messageData['caption']);
                                $response = sendTelegramMessage('sendMessage', $messageData, $config);
                            }
                            if (!$response['ok']) {
                                error_log("Failed to send podcast to $uid: " . json_encode($response));
                            }
                        }
                        
                        deletePodcastSession($userId, $db);
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "✅ Podcast muvaffaqiyatli saqlandi va yuborildi!n(24 soatdan keyin serverdan o'chiriladi)",
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                    } catch (PDOException $e) {
                        sendTelegramMessage('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "⚠️ Podcast yuborishda xatolik: " . htmlspecialchars($e->getMessage()),
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ], $config);
                        // Delete image file if it exists and creation failed
                        if (!empty($sessionData['image_file']) && file_exists($sessionData['image_file'])) {
                            unlink($sessionData['image_file']);
                        }
                        deletePodcastSession($userId, $db);
                    }
                } elseif ($callbackData === 'cancel_podcast') {
                    // Delete image file if it exists
                    if (!empty($sessionData['image_file']) && file_exists($sessionData['image_file'])) {
                        unlink($sessionData['image_file']);
                    }
                    deletePodcastSession($userId, $db);
                    sendTelegramMessage('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "❌ Podcast yuborish bekor qilindi.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($keyboard)
                    ], $config);
                }
            }
        }
    }
    
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . 'Error: ' . $e->getMessage() . ' | Input: ' . substr($rawInput ?? '', 0, 1000));
    echo json_encode(['status' => 'error', 'message' => '']);
}
?>