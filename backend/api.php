<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Security configuration
define('AUTH_KEY_LENGTH', 32);
define('MAX_REQUEST_SIZE', 1024 * 1024); // 1MB
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

class SecureAPI {
    private $dataDir;
    private $logFile;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/data/';
        $this->logFile = $this->dataDir . 'api.log';
        
        // Create data directory if it doesn't exist
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        // Initialize data files if they don't exist
        $this->initializeDataFiles();
    }
    
    private function initializeDataFiles() {
        $files = [
            'users.json' => '{}',
            'missions.json' => '{}',
            'referrals.json' => '{}',
            'conversions.json' => '{}',
            'config.json' => json_encode([
                'botToken' => '7270345128:AAEuRX7lABDMBRh6lRU1d-4aFzbiIhNgOWE',
                'botUsername' => 'UCCoinUltraBot',
                'bannerUrl' => 'https://mining-master.onrender.com//assets/banner-BH8QO14f.png'
            ]),
            'wallet_categories.json' => file_get_contents(__DIR__ . '/../wallet.json')
        ];
        
        foreach ($files as $filename => $defaultContent) {
            $filepath = $this->dataDir . $filename;
            if (!file_exists($filepath)) {
                file_put_contents($filepath, $defaultContent);
            }
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function validateAuthKey($authKey) {
        if (empty($authKey) || strlen($authKey) < 10) {
            return false;
        }
        return true;
    }
    
    private function checkRateLimit($ip) {
        $rateLimitFile = $this->dataDir . 'rate_limit.json';
        $rateLimits = [];
        
        if (file_exists($rateLimitFile)) {
            $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        }
        
        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW;
        
        // Clean old entries
        $rateLimits = array_filter($rateLimits, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Count requests from this IP
        $ipRequests = array_filter($rateLimits, function($timestamp, $key) use ($ip) {
            return strpos($key, $ip . '_') === 0;
        }, ARRAY_FILTER_USE_BOTH);
        
        if (count($ipRequests) >= RATE_LIMIT_REQUESTS) {
            return false;
        }
        
        // Add current request
        $rateLimits[$ip . '_' . $now] = $now;
        file_put_contents($rateLimitFile, json_encode($rateLimits), LOCK_EX);
        
        return true;
    }
    
    private function readJsonFile($filename) {
        $filepath = $this->dataDir . $filename;
        if (!file_exists($filepath)) {
            return [];
        }
        
        $content = file_get_contents($filepath);
        return json_decode($content, true) ?: [];
    }
    
    private function writeJsonFile($filename, $data) {
        $filepath = $this->dataDir . $filename;
        $tempFile = $filepath . '.tmp';
        
        // Write to temporary file first
        if (file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            return false;
        }
        
        // Atomic move
        return rename($tempFile, $filepath);
    }
    
    private function validateUser($userId, $authKey) {
        if (empty($userId) || empty($authKey)) {
            return false;
        }
        
        $users = $this->readJsonFile('users.json');
        
        if (!isset($users[$userId])) {
            return false;
        }
        
        return $users[$userId]['authKey'] === $authKey;
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
                    $this->handleMissions($authKey);
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
        
        if (empty($userId)) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }
        
        $users = $this->readJsonFile('users.json');
        
        // Check if user exists
        if (isset($users[$userId])) {
            echo json_encode([
                'success' => true,
                'authKey' => $users[$userId]['authKey'],
                'isNewUser' => false,
                'userData' => $users[$userId]
            ]);
            return;
        }
        
        // Create new user
        $authKey = $this->generateAuthKey();
        $now = time() * 1000; // milliseconds
        
        $userData = [
            'id' => $userId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'avatarUrl' => $avatarUrl,
            'authKey' => $authKey,
            'balance' => 0,
            'ucBalance' => 0,
            'energyLimit' => 500,
            'multiTapValue' => 1,
            'rechargingSpeed' => 1,
            'tapBotPurchased' => false,
            'tapBotActive' => false,
            'bonusClaimed' => false,
            'pubgId' => '',
            'totalTaps' => 0,
            'totalEarned' => 0,
            'lastJackpotTime' => 0,
            'referredBy' => $referredBy,
            'referralCount' => 0,
            'level' => 1,
            'xp' => 0,
            'streak' => 0,
            'combo' => 0,
            'lastTapTime' => 0,
            'isMining' => false,
            'miningStartTime' => 0,
            'lastClaimTime' => 0,
            'pendingRewards' => 0,
            'miningRate' => 0.001,
            'minClaimTime' => 1800,
            'settings' => [
                'sound' => true,
                'vibration' => true,
                'notifications' => true
            ],
            'boosts' => [
                'miningSpeedLevel' => 1,
                'claimTimeLevel' => 1,
                'miningRateLevel' => 1
            ],
            'missions' => new stdClass(),
            'withdrawals' => [],
            'conversions' => [],
            'joinedAt' => $now,
            'lastActive' => $now,
            'isReturningUser' => false,
            'dataInitialized' => false
        ];
        
        $users[$userId] = $userData;
        
        if ($this->writeJsonFile('users.json', $users)) {
            // Process referral if exists
            if (!empty($referredBy) && $referredBy !== $userId) {
                $this->processReferral($referredBy, $userId, $userData);
            }
            
            echo json_encode([
                'success' => true,
                'authKey' => $authKey,
                'isNewUser' => true,
                'userData' => $userData
            ]);
        } else {
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
            $users = $this->readJsonFile('users.json');
            echo json_encode($users[$userId] ?? null);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            $users = $this->readJsonFile('users.json');
            
            if (isset($users[$userId])) {
                // Preserve authKey
                $input['authKey'] = $users[$userId]['authKey'];
                $input['lastActive'] = time() * 1000;
                $users[$userId] = $input;
                
                if ($this->writeJsonFile('users.json', $users)) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update user']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        }
    }
    
    private function handleMissions($authKey) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $missions = $this->readJsonFile('missions.json');
        echo json_encode($missions);
    }
    
    private function handleUserMissions($authKey) {
        $userId = $_GET['userId'] ?? '';
        
        if (!$this->validateUser($userId, $authKey)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $userMissions = $this->readJsonFile('user_missions.json');
            echo json_encode($userMissions[$userId] ?? []);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            $missionId = $input['missionId'] ?? '';
            $missionData = $input['missionData'] ?? [];
            
            if (empty($missionId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Mission ID required']);
                return;
            }
            
            $userMissions = $this->readJsonFile('user_missions.json');
            if (!isset($userMissions[$userId])) {
                $userMissions[$userId] = [];
            }
            
            $userMissions[$userId][$missionId] = $missionData;
            
            if ($this->writeJsonFile('user_missions.json', $userMissions)) {
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
            $referrals = $this->readJsonFile('referrals.json');
            echo json_encode($referrals[$userId] ?? ['count' => 0, 'totalUC' => 0, 'referrals' => []]);
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
            $conversions = $this->readJsonFile('conversions.json');
            $userConversions = [];
            
            foreach ($conversions as $conversion) {
                if ($conversion['userId'] === $userId) {
                    $userConversions[] = $conversion;
                }
            }
            
            echo json_encode($userConversions);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $conversions = $this->readJsonFile('conversions.json');
            $conversionId = uniqid('conv_', true);
            
            $conversionData = [
                'id' => $conversionId,
                'userId' => $userId,
                'fromCurrency' => $input['fromCurrency'] ?? 'DRX',
                'toCurrency' => $input['toCurrency'] ?? '',
                'amount' => $input['amount'] ?? 0,
                'convertedAmount' => $input['convertedAmount'] ?? 0,
                'category' => $input['category'] ?? '',
                'packageType' => $input['packageType'] ?? '',
                'packageImage' => $input['packageImage'] ?? null,
                'requiredInfo' => $input['requiredInfo'] ?? [],
                'status' => 'pending',
                'requestedAt' => time() * 1000
            ];
            
            $conversions[] = $conversionData;
            
            if ($this->writeJsonFile('conversions.json', $conversions)) {
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
        
        $config = $this->readJsonFile('config.json');
        echo json_encode($config);
    }
    
    private function handleWalletCategories() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $walletData = $this->readJsonFile('wallet_categories.json');
        $categories = $walletData['wallet']['categories'] ?? [];
        
        // Filter active categories
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
        $users = $this->readJsonFile('users.json');
        
        $leaderboard = [];
        foreach ($users as $userId => $userData) {
            $leaderboard[] = [
                'id' => $userId,
                'firstName' => $userData['firstName'] ?? 'User',
                'lastName' => $userData['lastName'] ?? '',
                'avatarUrl' => $userData['avatarUrl'] ?? '',
                'totalEarned' => $userData['totalEarned'] ?? 0,
                'xp' => $userData['xp'] ?? 0,
                'level' => $this->calculateLevel($userData['xp'] ?? 0)['level']
            ];
        }
        
        // Sort by type
        if ($type === 'balance') {
            usort($leaderboard, function($a, $b) {
                return $b['totalEarned'] - $a['totalEarned'];
            });
        } else {
            usort($leaderboard, function($a, $b) {
                return $b['xp'] - $a['xp'];
            });
        }
        
        echo json_encode(array_slice($leaderboard, 0, 100));
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
        
        // Simulate verification (replace with actual Telegram API call)
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
    
    private function processReferral($refId, $userId, $userData) {
        $users = $this->readJsonFile('users.json');
        $referrals = $this->readJsonFile('referrals.json');
        
        if (!isset($users[$refId])) {
            return false;
        }
        
        // Check if referral already exists
        if (isset($referrals[$refId]['referrals'][$userId])) {
            return false;
        }
        
        // Initialize referral data if not exists
        if (!isset($referrals[$refId])) {
            $referrals[$refId] = [
                'count' => 0,
                'totalUC' => 0,
                'referrals' => []
            ];
        }
        
        // Add referral
        $referrals[$refId]['count']++;
        $referrals[$refId]['totalUC'] += 200;
        $referrals[$refId]['referrals'][$userId] = [
            'date' => date('c'),
            'earned' => 200,
            'firstName' => $userData['firstName'],
            'lastName' => $userData['lastName'],
            'avatarUrl' => $userData['avatarUrl']
        ];
        
        // Update referrer's balance
        $users[$refId]['balance'] = ($users[$refId]['balance'] ?? 0) + 200;
        $users[$refId]['referralCount'] = ($users[$refId]['referralCount'] ?? 0) + 1;
        $users[$refId]['totalEarned'] = ($users[$refId]['totalEarned'] ?? 0) + 200;
        $users[$refId]['xp'] = ($users[$refId]['xp'] ?? 0) + 60;
        
        $this->writeJsonFile('referrals.json', $referrals);
        $this->writeJsonFile('users.json', $users);
        
        return true;
    }
    
    private function calculateLevel($xp) {
        $level = 1;
        $remainingXP = $xp;
        
        while ($remainingXP >= $this->getXpForLevel($level)) {
            $remainingXP -= $this->getXpForLevel($level);
            $level++;
        }
        
        return [
            'level' => $level,
            'currentXP' => $remainingXP,
            'xpForNext' => $this->getXpForLevel($level)
        ];
    }
    
    private function getXpForLevel($level) {
        if ($level === 1) return 100;
        return 100 + ($level - 1) * 50;
    }
}

// Initialize and handle request
$api = new SecureAPI();
$api->handleRequest();
?>