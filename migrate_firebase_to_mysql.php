<?php
require_once 'backend/config.php';

class FirebaseToMySQLMigrator {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function migrate($jsonFile = 'firebasejson-to-php.json') {
        if (!file_exists($jsonFile)) {
            throw new Exception("JSON file not found: $jsonFile");
        }
        
        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        echo "Starting migration from Firebase JSON to MySQL...\n";
        
        try {
            $this->db->beginTransaction();
            
            // Migrate users
            if (isset($data['users'])) {
                $this->migrateUsers($data['users']);
            }
            
            // Migrate missions
            if (isset($data['missions'])) {
                $this->migrateMissions($data['missions']);
            }
            
            // Migrate user missions
            if (isset($data['userMissions'])) {
                $this->migrateUserMissions($data['userMissions']);
            }
            
            // Migrate referrals
            if (isset($data['referrals'])) {
                $this->migrateReferrals($data['referrals']);
            }
            
            // Migrate conversions
            if (isset($data['conversions']) || isset($data['history'])) {
                $this->migrateConversions($data['conversions'] ?? [], $data['history'] ?? []);
            }
            
            // Migrate config
            if (isset($data['config'])) {
                $this->migrateConfig($data['config']);
            }
            
            $this->db->commit();
            echo "Migration completed successfully!\n";
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Migration failed: " . $e->getMessage());
        }
    }
    
    private function migrateUsers($users) {
        echo "Migrating users...\n";
        $count = 0;
        
        foreach ($users as $userId => $userData) {
            try {
                // Check if user already exists
                $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn()) {
                    echo "User $userId already exists, skipping...\n";
                    continue;
                }
                
                $authKey = $userData['authKey'] ?? bin2hex(random_bytes(32));
                $now = time() * 1000;
                
                $stmt = $this->db->prepare("INSERT INTO users (
                    id, first_name, last_name, avatar_url, auth_key, balance, uc_balance,
                    energy_limit, multi_tap_value, recharging_speed, tap_bot_purchased,
                    tap_bot_active, bonus_claimed, pubg_id, total_taps, total_earned,
                    last_jackpot_time, referred_by, referral_count, level_num, xp,
                    streak, combo, last_tap_time, is_mining, mining_start_time,
                    last_claim_time, pending_rewards, mining_rate, min_claim_time,
                    mining_speed_level, claim_time_level, mining_rate_level,
                    sound_enabled, vibration_enabled, notifications_enabled,
                    joined_at, last_active, is_returning_user, data_initialized
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $userId,
                    $userData['firstName'] ?? 'User',
                    $userData['lastName'] ?? '',
                    $userData['avatarUrl'] ?? '',
                    $authKey,
                    $userData['balance'] ?? 0,
                    $userData['ucBalance'] ?? 0,
                    $userData['energyLimit'] ?? 500,
                    $userData['multiTapValue'] ?? 1,
                    $userData['rechargingSpeed'] ?? 1,
                    $userData['tapBotPurchased'] ?? false,
                    $userData['tapBotActive'] ?? false,
                    $userData['bonusClaimed'] ?? false,
                    $userData['pubgId'] ?? '',
                    $userData['totalTaps'] ?? 0,
                    $userData['totalEarned'] ?? 0,
                    $userData['lastJackpotTime'] ?? 0,
                    $userData['referredBy'] ?? '',
                    $userData['referralCount'] ?? 0,
                    $userData['level'] ?? 1,
                    $userData['xp'] ?? 0,
                    $userData['streak'] ?? 0,
                    $userData['combo'] ?? 0,
                    $userData['lastTapTime'] ?? 0,
                    $userData['isMining'] ?? false,
                    $userData['miningStartTime'] ?? 0,
                    $userData['lastClaimTime'] ?? 0,
                    $userData['pendingRewards'] ?? 0,
                    $userData['miningRate'] ?? 0.001,
                    $userData['minClaimTime'] ?? 1800,
                    $userData['boosts']['miningSpeedLevel'] ?? 1,
                    $userData['boosts']['claimTimeLevel'] ?? 1,
                    $userData['boosts']['miningRateLevel'] ?? 1,
                    $userData['settings']['sound'] ?? true,
                    $userData['settings']['vibration'] ?? true,
                    $userData['settings']['notifications'] ?? true,
                    $userData['joinedAt'] ?? $now,
                    $userData['lastActive'] ?? $now,
                    $userData['isReturningUser'] ?? false,
                    $userData['dataInitialized'] ?? false
                ]);
                
                $count++;
            } catch (Exception $e) {
                echo "Failed to migrate user $userId: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Migrated $count users\n";
    }
    
    private function migrateMissions($missions) {
        echo "Migrating missions...\n";
        $count = 0;
        
        foreach ($missions as $missionId => $missionData) {
            try {
                $stmt = $this->db->prepare("INSERT IGNORE INTO missions (
                    id, title, description, reward, required_count, channel_id,
                    url, code, required_time, active, category, type, icon, img, priority
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $missionId,
                    $missionData['title'] ?? '',
                    $missionData['description'] ?? '',
                    $missionData['reward'] ?? 0,
                    $missionData['requiredCount'] ?? 1,
                    $missionData['channelId'] ?? null,
                    $missionData['url'] ?? null,
                    $missionData['code'] ?? null,
                    $missionData['requiredTime'] ?? null,
                    $missionData['active'] ?? true,
                    $missionData['category'] ?? 'General',
                    $missionData['type'] ?? 'join_channel',
                    $missionData['icon'] ?? null,
                    $missionData['img'] ?? null,
                    $missionData['priority'] ?? 999
                ]);
                
                $count++;
            } catch (Exception $e) {
                echo "Failed to migrate mission $missionId: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Migrated $count missions\n";
    }
    
    private function migrateUserMissions($userMissions) {
        echo "Migrating user missions...\n";
        $count = 0;
        
        foreach ($userMissions as $userId => $missions) {
            foreach ($missions as $missionId => $missionData) {
                try {
                    $stmt = $this->db->prepare("INSERT IGNORE INTO user_missions (
                        user_id, mission_id, started, completed, claimed, current_count,
                        started_date, completed_at, claimed_at, last_verify_attempt,
                        timer_started, code_submitted
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
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
                    
                    $count++;
                } catch (Exception $e) {
                    echo "Failed to migrate user mission $userId:$missionId: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "Migrated $count user missions\n";
    }
    
    private function migrateReferrals($referrals) {
        echo "Migrating referrals...\n";
        $count = 0;
        
        foreach ($referrals as $referrerId => $referralData) {
            if (isset($referralData['referrals'])) {
                foreach ($referralData['referrals'] as $referredId => $refData) {
                    try {
                        $stmt = $this->db->prepare("INSERT IGNORE INTO referrals (
                            referrer_id, referred_id, earned
                        ) VALUES (?, ?, ?)");
                        
                        $stmt->execute([
                            $referrerId,
                            $referredId,
                            $refData['earned'] ?? 200
                        ]);
                        
                        $count++;
                    } catch (Exception $e) {
                        echo "Failed to migrate referral $referrerId:$referredId: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "Migrated $count referrals\n";
    }
    
    private function migrateConversions($conversions, $history) {
        echo "Migrating conversions...\n";
        $count = 0;
        
        // Migrate from conversions
        foreach ($conversions as $userId => $userConversions) {
            if (is_array($userConversions)) {
                foreach ($userConversions as $conversionData) {
                    $this->insertConversion($conversionData, $userId);
                    $count++;
                }
            }
        }
        
        // Migrate from history
        foreach ($history as $userId => $userHistory) {
            if (is_array($userHistory)) {
                foreach ($userHistory as $conversionData) {
                    $this->insertConversion($conversionData, $userId);
                    $count++;
                }
            }
        }
        
        echo "Migrated $count conversions\n";
    }
    
    private function insertConversion($conversionData, $userId) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO conversions (
                id, user_id, from_currency, to_currency, amount, converted_amount,
                category, package_type, package_image, required_info, status, requested_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $conversionData['id'] ?? uniqid('conv_', true),
                $userId,
                $conversionData['fromCurrency'] ?? 'DRX',
                $conversionData['toCurrency'] ?? '',
                $conversionData['amount'] ?? 0,
                $conversionData['convertedAmount'] ?? 0,
                $conversionData['category'] ?? '',
                $conversionData['packageType'] ?? '',
                $conversionData['packageImage'] ?? null,
                json_encode($conversionData['requiredInfo'] ?? []),
                $conversionData['status'] ?? 'pending',
                $conversionData['requestedAt'] ?? time() * 1000
            ]);
        } catch (Exception $e) {
            echo "Failed to insert conversion: " . $e->getMessage() . "\n";
        }
    }
    
    private function migrateConfig($config) {
        echo "Migrating config...\n";
        
        foreach ($config as $key => $value) {
            try {
                $stmt = $this->db->prepare("INSERT INTO config (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $value]);
            } catch (Exception $e) {
                echo "Failed to migrate config $key: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Config migrated\n";
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli') {
    try {
        $migrator = new FirebaseToMySQLMigrator();
        $jsonFile = $argv[1] ?? 'firebasejson-to-php.json';
        $migrator->migrate($jsonFile);
        echo "Migration completed successfully!\n";
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "This script should be run from command line: php migrate_firebase_to_mysql.php [json_file]\n";
}
?>