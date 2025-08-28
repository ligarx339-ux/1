<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class SecureAPI {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    private function log($message) {
        error_log("[" . date('Y-m-d H:i:s') . "] $message");
    }
    
    private function validateAuthKey($authKey) {
        if (empty($authKey) || strlen($authKey) < 32) {
            return false;
        }
        return true;
    }
    
    private function checkRateLimit($ip) {
        $stmt = $this->db->prepare("SELECT request_count, window_start FROM rate_limits WHERE ip = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        
        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW;
        
        if ($result) {
            if ($result['window_start'] < $windowStart) {
                // Reset window
                $stmt = $this->db->prepare("UPDATE rate_limits SET request_count = 1, window_start = ? WHERE ip = ?");
                $stmt->execute([$now, $ip]);
                return true;
            } elseif ($result['request_count'] >= RATE_LIMIT_REQUESTS) {
                return false;
            } else {
                // Increment count
                $stmt = $this->db->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip = ?");
                $stmt->execute([$ip]);
                return true;
            }
        } else {
            // First request from this IP
            $stmt = $this->db->prepare("INSERT INTO rate_limits (ip, request_count, window_start) VALUES (?, 1, ?)");
            $stmt->execute([$ip, $now]);
            return true;
        }
    }
    
    private function validateUser($userId, $authKey) {
        if (empty($userId) || empty($authKey)) {
            return false;
        }
        
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND auth_key = ? AND status = 'active'");
        $stmt->execute([$userId, $authKey]);
        return $stmt->fetchColumn() !== false;
    }
    
    private function generateAuthKey() {
        return bin2hex(random_bytes(AUTH_KEY_LENGTH / 2));
    }
    
    public function handleRequest() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Check rate limiting
        if (!$this->checkRateLimit($ip)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            return;
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';
        $authKey = $_SERVER['HTTP_X_AUTH_KEY'] ?? '';
        
        $this->log("$method $path from $ip");
        
        try {
            switch ($path) {
                case 'auth':
                    $this->handleAuth();
                    break;
                case 'user':
                    $this->handleUser($authKey);
                    break;
                case 'missions':
                    $this->handleMissions();
                    break;
                case 'user-missions':
                    $this->handleUserMissions($authKey);
                    break;
                case 'referrals':
                    $this->handleReferrals($authKey);
                    break;
                case 'conversions':
                    $this->handleConversions($authKey);
                    break;
                case 'config':
                    $this->handleConfig();
                    break;
                case 'wallet-categories':
                    $this->handleWalletCategories();
                    break;
                case 'leaderboard':
                    $this->handleLeaderboard();
                    break;
                case 'verify-telegram':
                    $this->handleTelegramVerification($authKey);
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint not found']);
            }
        } catch (Exception $e) {
            $this->log("Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
    
    private function handleAuth() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['userId'] ?? '';
        $firstName = $input['firstName'] ?? 'User';
        $lastName = $input['lastName'] ?? '';
        $avatarUrl = $input['avatarUrl'] ?? '';
        $referredBy = $input['referredBy'] ?? '';
        $refAuth = $input['refAuth'] ?? '';
        
        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        // Check if user exists
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Update last active time
            $stmt = $this->db->prepare("UPDATE users SET last_active = ? WHERE id = ?");
            $stmt->execute([time() * 1000, $userId]);
            
            echo json_encode([
                'success' => true,
                'authKey' => $existingUser['auth_key'],
                'isNewUser' => false,
                'userData' => $this->formatUserData($existingUser)
            ]);
            return;
        }
        
        // Create new user
        $authKey = $this->generateAuthKey();
        $now = time() * 1000;
        
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("INSERT INTO users (
                id, first_name, last_name, avatar_url, auth_key, 
                referred_by, joined_at, last_active, is_returning_user
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE)");
            
            $stmt->execute([
                $userId, $firstName, $lastName, $avatarUrl, $authKey,
                $referredBy, $now, $now
            ]);
            
            // Process referral if exists
            if (!empty($referredBy) && $referredBy !== $userId) {
                $this->processReferral($referredBy, $userId, $refAuth);
            }
            
            $this->db->commit();
            
            // Get created user data
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'authKey' => $authKey,
                'isNewUser' => true,
                'userData' => $this->formatUserData($userData)
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->log("User creation failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user']);
        }
    }
    
    private function handleUser($authKey) {
        $userId = $_GET['userId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if ($userData) {
                echo json_encode($this->formatUserData($userData));
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Update user data
            $stmt = $this->db->prepare("UPDATE users SET 
                balance = ?, total_earned = ?, is_mining = ?, mining_start_time = ?,
                last_claim_time = ?, pending_rewards = ?, mining_rate = ?, min_claim_time = ?,
                mining_speed_level = ?, claim_time_level = ?, mining_rate_level = ?,
                sound_enabled = ?, vibration_enabled = ?, notifications_enabled = ?,
                bonus_claimed = ?, data_initialized = ?, xp = ?, level_num = ?,
                last_active = ?, pubg_id = ?
                WHERE id = ?");
            
            $result = $stmt->execute([
                $input['balance'] ?? 0,
                $input['totalEarned'] ?? 0,
                $input['isMining'] ?? false,
                $input['miningStartTime'] ?? 0,
                $input['lastClaimTime'] ?? 0,
                $input['pendingRewards'] ?? 0,
                $input['miningRate'] ?? BASE_MINING_RATE,
                $input['minClaimTime'] ?? MIN_CLAIM_TIME,
                $input['boosts']['miningSpeedLevel'] ?? 1,
                $input['boosts']['claimTimeLevel'] ?? 1,
                $input['boosts']['miningRateLevel'] ?? 1,
                $input['settings']['sound'] ?? true,
                $input['settings']['vibration'] ?? true,
                $input['settings']['notifications'] ?? true,
                $input['bonusClaimed'] ?? false,
                $input['dataInitialized'] ?? false,
                $input['xp'] ?? 0,
                $input['level'] ?? 1,
                time() * 1000,
                $input['pubgId'] ?? '',
                $userId
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update user']);
            }
        }
    }
    
    private function handleMissions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM missions WHERE active = TRUE ORDER BY priority ASC");
        $stmt->execute();
        $missions = $stmt->fetchAll();
        
        $result = [];
        foreach ($missions as $mission) {
            $result[$mission['id']] = [
                'id' => $mission['id'],
                'title' => $mission['title'],
                'description' => $mission['description'],
                'detailedDescription' => $mission['detailed_description'],
                'reward' => (int)$mission['reward'],
                'requiredCount' => (int)$mission['required_count'],
                'channelId' => $mission['channel_id'],
                'url' => $mission['url'],
                'code' => $mission['code'],
                'requiredTime' => $mission['required_time'] ? (int)$mission['required_time'] : null,
                'active' => (bool)$mission['active'],
                'category' => $mission['category'],
                'type' => $mission['type'],
                'icon' => $mission['icon'],
                'img' => $mission['img'],
                'priority' => (int)$mission['priority'],
                'instructions' => $mission['instructions'] ? json_decode($mission['instructions'], true) : [],
                'tips' => $mission['tips'] ? json_decode($mission['tips'], true) : [],
                'createdAt' => $mission['created_at']
            ];
        }
        
        echo json_encode($result);
    }
    
    private function handleUserMissions($authKey) {
        $userId = $_GET['userId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $this->db->prepare("SELECT * FROM user_missions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userMissions = $stmt->fetchAll();
            
            $result = [];
            foreach ($userMissions as $mission) {
                $result[$mission['mission_id']] = [
                    'started' => (bool)$mission['started'],
                    'completed' => (bool)$mission['completed'],
                    'claimed' => (bool)$mission['claimed'],
                    'currentCount' => (int)$mission['current_count'],
                    'startedDate' => $mission['started_date'],
                    'completedAt' => $mission['completed_at'],
                    'claimedAt' => $mission['claimed_at'],
                    'lastVerifyAttempt' => $mission['last_verify_attempt'],
                    'timerStarted' => $mission['timer_started'],
                    'codeSubmitted' => $mission['code_submitted']
                ];
            }
            
            echo json_encode($result);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            $missionId = $input['missionId'] ?? '';
            $missionData = $input['missionData'] ?? [];
            
            if (empty($missionId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Mission ID required']);
                return;
            }
            
            $stmt = $this->db->prepare("INSERT INTO user_missions (
                user_id, mission_id, started, completed, claimed, current_count,
                started_date, completed_at, claimed_at, last_verify_attempt,
                timer_started, code_submitted
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                started = VALUES(started),
                completed = VALUES(completed),
                claimed = VALUES(claimed),
                current_count = VALUES(current_count),
                completed_at = VALUES(completed_at),
                claimed_at = VALUES(claimed_at),
                last_verify_attempt = VALUES(last_verify_attempt),
                timer_started = VALUES(timer_started),
                code_submitted = VALUES(code_submitted)");
            
            $result = $stmt->execute([
                $userId,
                $missionId,
                $missionData['started'] ?? false,
                $missionData['completed'] ?? false,
                $missionData['claimed'] ?? false,
                $missionData['currentCount'] ?? 0,
                $missionData['startedDate'] ?? null,
                $missionData['completedAt'] ?? null,
                $missionData['claimedAt'] ?? null,
                $missionData['lastVerifyAttempt'] ?? null,
                $missionData['timerStarted'] ?? null,
                $missionData['codeSubmitted'] ?? null
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update mission']);
            }
        }
    }
    
    private function handleReferrals($authKey) {
        $userId = $_GET['userId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Get referral count and total earned
            $stmt = $this->db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(earned), 0) as total_earned FROM referrals WHERE referrer_id = ?");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();
            
            // Get referral details
            $stmt = $this->db->prepare("
                SELECT r.*, u.first_name, u.last_name, u.avatar_url, r.created_at as date
                FROM referrals r 
                JOIN users u ON r.referred_id = u.id 
                WHERE r.referrer_id = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$userId]);
            $referrals = $stmt->fetchAll();
            
            $referralData = [];
            foreach ($referrals as $ref) {
                $referralData[$ref['referred_id']] = [
                    'date' => $ref['date'],
                    'earned' => (int)$ref['earned'],
                    'firstName' => $ref['first_name'],
                    'lastName' => $ref['last_name'],
                    'avatarUrl' => $ref['avatar_url']
                ];
            }
            
            echo json_encode([
                'count' => (int)$stats['count'],
                'totalUC' => (int)$stats['total_earned'],
                'referrals' => $referralData
            ]);
        }
    }
    
    private function handleConversions($authKey) {
        $userId = $_GET['userId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $this->db->prepare("SELECT * FROM conversions WHERE user_id = ? ORDER BY requested_at DESC");
            $stmt->execute([$userId]);
            $conversions = $stmt->fetchAll();
            
            $result = [];
            foreach ($conversions as $conv) {
                $result[] = [
                    'id' => $conv['id'],
                    'fromCurrency' => $conv['from_currency'],
                    'toCurrency' => $conv['to_currency'],
                    'amount' => (float)$conv['amount'],
                    'convertedAmount' => (float)$conv['converted_amount'],
                    'category' => $conv['category'],
                    'packageType' => $conv['package_type'],
                    'packageImage' => $conv['package_image'],
                    'status' => $conv['status'],
                    'requestedAt' => (int)$conv['requested_at'],
                    'completedAt' => $conv['completed_at'] ? (int)$conv['completed_at'] : null,
                    'requiredInfo' => $conv['required_info'] ? json_decode($conv['required_info'], true) : []
                ];
            }
            
            echo json_encode($result);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $conversionId = uniqid('conv_', true);
            $now = time() * 1000;
            
            $stmt = $this->db->prepare("INSERT INTO conversions (
                id, user_id, from_currency, to_currency, amount, converted_amount,
                category, package_type, package_image, required_info, requested_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $conversionId,
                $userId,
                $input['fromCurrency'] ?? 'DRX',
                $input['toCurrency'] ?? '',
                $input['amount'] ?? 0,
                $input['convertedAmount'] ?? 0,
                $input['category'] ?? '',
                $input['packageType'] ?? '',
                $input['packageImage'] ?? null,
                json_encode($input['requiredInfo'] ?? []),
                $now
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'conversionId' => $conversionId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create conversion']);
            }
        }
    }
    
    private function handleConfig() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM config");
        $stmt->execute();
        $config = $stmt->fetchAll();
        
        $result = [];
        foreach ($config as $item) {
            $result[$item['setting_key']] = $item['setting_value'];
        }
        
        echo json_encode($result);
    }
    
    private function handleWalletCategories() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        // Load from wallet.json file
        $walletData = json_decode(file_get_contents(__DIR__ . '/../wallet.json'), true);
        $categories = $walletData['wallet']['categories'] ?? [];
        
        $activeCategories = array_filter($categories, function($category) {
            return $category['active'] ?? false;
        });
        
        echo json_encode(array_values($activeCategories));
    }
    
    private function handleLeaderboard() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $type = $_GET['type'] ?? 'balance';
        
        if ($type === 'balance') {
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, avatar_url, total_earned, xp FROM users WHERE status = 'active' ORDER BY total_earned DESC LIMIT 100");
        } else {
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, avatar_url, total_earned, xp FROM users WHERE status = 'active' ORDER BY xp DESC LIMIT 100");
        }
        
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user['id'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'avatarUrl' => $user['avatar_url'],
                'totalEarned' => (float)$user['total_earned'],
                'xp' => (int)$user['xp']
            ];
        }
        
        echo json_encode($result);
    }
    
    private function handleTelegramVerification($authKey) {
        $userId = $_GET['userId'] ?? '';
        $channelId = $_GET['channelId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if (empty($channelId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Channel ID required']);
            return;
        }
        
        $verified = $this->verifyTelegramMembership($userId, $channelId);
        echo json_encode(['verified' => $verified]);
    }
    
    private function verifyTelegramMembership($userId, $channelId) {
        try {
            $apiData = '';
            
            if (strpos($channelId, '@') === 0) {
                $apiData = base64_encode($channelId . '|' . $userId);
            } elseif (strpos($channelId, '-100') === 0 || ctype_digit($channelId)) {
                $fullChannelId = strpos($channelId, '-100') === 0 ? $channelId : '-100' . $channelId;
                $apiData = base64_encode($fullChannelId . '|' . $userId);
            } else {
                $apiData = base64_encode('@' . $channelId . '|' . $userId);
            }
            
            $url = "https://m5576.myxvest.ru/davronovapi/api.php?data=" . $apiData;
            $response = file_get_contents($url);
            
            return strtolower(trim($response)) === 'yes';
        } catch (Exception $e) {
            $this->log("Telegram verification error: " . $e->getMessage());
            return false;
        }
    }
    
    private function processReferral($referrerId, $referredId, $refAuth = '') {
        try {
            // Check if referrer exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$referrerId]);
            if (!$stmt->fetchColumn()) {
                return false;
            }
            
            // Check if referral already exists
            $stmt = $this->db->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_id = ?");
            $stmt->execute([$referrerId, $referredId]);
            if ($stmt->fetchColumn()) {
                return false;
            }
            
            // Add referral record
            $stmt = $this->db->prepare("INSERT INTO referrals (referrer_id, referred_id, earned) VALUES (?, ?, ?)");
            $stmt->execute([$referrerId, $referredId, REFERRAL_BONUS]);
            
            // Update referrer's balance and stats
            $stmt = $this->db->prepare("UPDATE users SET 
                balance = balance + ?, 
                total_earned = total_earned + ?, 
                referral_count = referral_count + 1,
                xp = xp + 60
                WHERE id = ?");
            $stmt->execute([REFERRAL_BONUS, REFERRAL_BONUS, $referrerId]);
            
            return true;
        } catch (Exception $e) {
            $this->log("Referral processing failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function formatUserData($userData) {
        return [
            'id' => $userData['id'],
            'firstName' => $userData['first_name'],
            'lastName' => $userData['last_name'],
            'avatarUrl' => $userData['avatar_url'],
            'authKey' => $userData['auth_key'],
            'balance' => (float)$userData['balance'],
            'ucBalance' => (float)$userData['uc_balance'],
            'energyLimit' => (int)$userData['energy_limit'],
            'multiTapValue' => (int)$userData['multi_tap_value'],
            'rechargingSpeed' => (int)$userData['recharging_speed'],
            'tapBotPurchased' => (bool)$userData['tap_bot_purchased'],
            'tapBotActive' => (bool)$userData['tap_bot_active'],
            'bonusClaimed' => (bool)$userData['bonus_claimed'],
            'pubgId' => $userData['pubg_id'],
            'totalTaps' => (int)$userData['total_taps'],
            'totalEarned' => (float)$userData['total_earned'],
            'lastJackpotTime' => (int)$userData['last_jackpot_time'],
            'referredBy' => $userData['referred_by'],
            'referralCount' => (int)$userData['referral_count'],
            'level' => (int)$userData['level_num'],
            'xp' => (int)$userData['xp'],
            'streak' => (int)$userData['streak'],
            'combo' => (int)$userData['combo'],
            'lastTapTime' => (int)$userData['last_tap_time'],
            'isMining' => (bool)$userData['is_mining'],
            'miningStartTime' => (int)$userData['mining_start_time'],
            'lastClaimTime' => (int)$userData['last_claim_time'],
            'pendingRewards' => (float)$userData['pending_rewards'],
            'miningRate' => (float)$userData['mining_rate'],
            'minClaimTime' => (int)$userData['min_claim_time'],
            'settings' => [
                'sound' => (bool)$userData['sound_enabled'],
                'vibration' => (bool)$userData['vibration_enabled'],
                'notifications' => (bool)$userData['notifications_enabled']
            ],
            'boosts' => [
                'miningSpeedLevel' => (int)$userData['mining_speed_level'],
                'claimTimeLevel' => (int)$userData['claim_time_level'],
                'miningRateLevel' => (int)$userData['mining_rate_level']
            ],
            'missions' => new stdClass(),
            'withdrawals' => [],
            'conversions' => [],
            'joinedAt' => (int)$userData['joined_at'],
            'lastActive' => (int)$userData['last_active'],
            'isReturningUser' => (bool)$userData['is_returning_user'],
            'dataInitialized' => (bool)$userData['data_initialized']
        ];
    }
}

// Initialize and handle request
$api = new SecureAPI();
$api->handleRequest();
?>