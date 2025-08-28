<?php
// Security headers
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ALLOW-FROM https://web.telegram.org');
header('Access-Control-Allow-Origin: https://web.telegram.org');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/panel_errors.log');
error_reporting(E_ALL);

// Static auth key
$authKey = 'TangaSecureAdminAccessKey1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopq';

// Initialize SQLite
try {
    $db = new PDO('sqlite:' . __DIR__ . '/bot_data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('SQLite Error: ' . $e->getMessage());
    exit('Database error');
}

// Authentication
$auth = $_GET['auth'] ?? '';
$adminId = $_GET['adminId'] ?? '';
if ($auth !== $authKey || empty($adminId)) {
    http_response_code(403);
    exit('Access denied');
}

try {
    $stmt = $db->prepare("SELECT admin_id FROM admins WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    if ($stmt->fetchColumn() === false && $adminId !== '6547102814') {
        http_response_code(403);
        exit('Not an admin');
    }
} catch (PDOException $e) {
    error_log('Admin Check Error: ' . $e->getMessage());
    exit('Admin verification failed');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_config'])) {
            $settings = [
                'firebase_url' => filter_var($_POST['firebase_url'] ?? '', FILTER_SANITIZE_URL),
                'firebase_secret' => filter_var($_POST['firebase_secret'] ?? '', FILTER_SANITIZE_STRING),
                'mini_app_url' => filter_var($_POST['mini_app_url'] ?? '', FILTER_SANITIZE_URL),
                'panel_url' => filter_var($_POST['panel_url'] ?? '', FILTER_SANITIZE_URL),
                'avatar_url' => filter_var($_POST['avatar_url'] ?? '', FILTER_SANITIZE_URL)
            ];
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO config (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)");
            foreach ($settings as $key => $value) {
                if (!empty($value)) {
                    $stmt->execute([$key, $value, $adminId, date('c')]);
                }
            }
            $success = 'Configuration updated successfully!';
        } elseif (isset($_POST['create_podcast'])) {
            $title = filter_var(trim($_POST['title'] ?? ''), FILTER_SANITIZE_STRING);
            $content = filter_var(trim($_POST['content'] ?? ''), FILTER_SANITIZE_STRING);
            $target_type = $_POST['target_type'] ?? '';
            $target_id = filter_var(trim($_POST['target_id'] ?? ''), FILTER_SANITIZE_STRING);
            
            if (empty($title) || empty($content) || !in_array($target_type, ['all', 'last_day', 'last_week', 'last_month', 'specific'])) {
                throw new Exception('Invalid podcast data: Title, content, and valid target type are required');
            }
            if ($target_type === 'specific' && empty($target_id)) {
                throw new Exception('Target ID required for specific user');
            }
            
            $imageUrl = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('Image size exceeds 2MB');
                }
                if (!in_array($_FILES['image']['type'], ['image/jpeg', 'image/jpg'])) {
                    throw new Exception('Image must be JPEG');
                }
                $uploadDir = __DIR__ . '/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $imageFile = $uploadDir . $adminId . '_podcast_' . time() . '.jpg';
                if (move_uploaded_file($_FILES['image']['tmp_name'], $imageFile)) {
                    $stmt = $db->query("SELECT setting_value FROM config WHERE setting_key = 'avatar_url'");
                    $avatarUrl = $stmt->fetchColumn() ?: 'https://c694.coresuz.ru/avatars';
                    $imageUrl = $avatarUrl . '/' . basename($imageFile);
                } else {
                    error_log('Failed to upload podcast image for admin ' . $adminId);
                }
            }
            
            $buttonType = $_POST['button_type'] ?? '';
            $buttonText = $buttonType ? filter_var(trim($_POST['button_text'] ?? ''), FILTER_SANITIZE_STRING) : '';
            $buttonUrl = $buttonType ? filter_var(trim($_POST['button_url'] ?? ''), FILTER_SANITIZE_URL) : '';
            
            if ($buttonType && (empty($buttonText) || empty($buttonUrl) || !filter_var($buttonUrl, FILTER_VALIDATE_URL))) {
                throw new Exception('Invalid button data: Button text and valid URL are required');
            }
            
            $stmt = $db->prepare("INSERT INTO podcasts (title, content, image_url, button_type, button_text, button_url, sent_by, created_at, target_type, target_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $content,
                $imageUrl,
                $buttonType,
                $buttonText,
                $buttonUrl,
                $adminId,
                time(),
                $target_type,
                $target_id,
                'pending'
            ]);
            $success = 'Podcast created and will be available for sending via the bot!';
        } elseif (isset($_POST['add_admin'])) {
            $newAdminId = filter_var(trim($_POST['new_admin_id'] ?? ''), FILTER_SANITIZE_STRING);
            if (!empty($newAdminId)) {
                $stmt = $db->prepare("INSERT OR IGNORE INTO admins (admin_id, added_by, added_at) VALUES (?, ?, ?)");
                $stmt->execute([$newAdminId, $adminId, date('c')]);
                $success = 'Sub-admin added successfully!';
            } else {
                $error = 'Invalid admin ID';
            }
        } elseif (isset($_POST['remove_admin'])) {
            $removeAdminId = filter_var(trim($_POST['remove_admin_id'] ?? ''), FILTER_SANITIZE_STRING);
            if (!empty($removeAdminId) && $removeAdminId !== '6547102814') {
                $stmt = $db->prepare("DELETE FROM admins WHERE admin_id = ?");
                $stmt->execute([$removeAdminId]);
                $success = 'Sub-admin removed successfully!';
            } else {
                $error = 'Cannot remove mega admin';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch data
try {
    $configData = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM config");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configData[$row['setting_key']] = $row['setting_value'];
    }
    
    $podcasts = $db->query("SELECT * FROM podcasts ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $admins = $db->query("SELECT * FROM admins ORDER BY added_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $podcastCount = $db->query("SELECT COUNT(*) FROM podcasts")->fetchColumn();
    $recentActivities = $db->query("SELECT user_id, activity_type, activity_date FROM statistics ORDER BY activity_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching data: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tanga Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', sans-serif;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 100%;
            padding: 1rem;
        }
        @media (min-width: 640px) {
            .container {
                max-width: 640px;
                margin: 0 auto;
            }
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background-color: #fff;
        }
        .form-input:invalid, .form-textarea:invalid, .form-select:invalid {
            border-color: #ef4444;
        }
        .form-button {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }
        @media (max-width: 640px) {
            th, td {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            border-radius: 0.5rem;
            max-width: 90%;
            max-height: 80%;
            overflow-y: auto;
            padding: 1.5rem;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 1.25rem;
            cursor: pointer;
        }
        .error-text {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container mx-auto">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Tanga Admin Panel</h1>
        
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Statistics</h2>
            <p class="text-sm text-gray-600"><strong>Total Users:</strong> <?= htmlspecialchars($userCount) ?></p>
            <p class="text-sm text-gray-600"><strong>Total Podcasts:</strong> <?= htmlspecialchars($podcastCount) ?></p>
            <button id="view-activities" class="form-button bg-blue-500 text-white hover:bg-blue-600 mt-4">View Recent Activities</button>
        </div>
        
        <!-- Configuration Form -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Update Configuration</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="update_config" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Firebase URL</label>
                    <input type="url" name="firebase_url" value="<?= htmlspecialchars($configData['firebase_url'] ?? '') ?>" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Firebase Secret</label>
                    <input type="text" name="firebase_secret" value="<?= htmlspecialchars($configData['firebase_secret'] ?? '') ?>" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mini App URL</label>
                    <input type="url" name="mini_app_url" value="<?= htmlspecialchars($configData['mini_app_url'] ?? '') ?>" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Panel URL</label>
                    <input type="url" name="panel_url" value="<?= htmlspecialchars($configData['panel_url'] ?? '') ?>" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Avatar URL</label>
                    <input type="url" name="avatar_url" value="<?= htmlspecialchars($configData['avatar_url'] ?? '') ?>" class="form-input">
                </div>
                <button type="submit" class="form-button bg-blue-500 text-white hover:bg-blue-600">Update</button>
            </form>
        </div>
        
        <!-- Podcast Creation Form -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Create Podcast</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="return validatePodcastForm()">
                <input type="hidden" name="create_podcast" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" required class="form-input" maxlength="100">
                    <p class="error-text hidden" id="title-error">Title is required and must be 100 characters or less</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea name="content" required rows="4" class="form-textarea" maxlength="1000"></textarea>
                    <p class="error-text hidden" id="content-error">Content is required and must be 1000 characters or less</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Image (Optional, max 2MB, JPEG)</label>
                    <input type="file" name="image" accept="image/jpeg,image/jpg" class="form-input">
                    <p class="error-text hidden" id="image-error">Image must be a JPEG and not exceed 2MB</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button Type (Optional)</label>
                    <select name="button_type" class="form-select" onchange="toggleButtonFields()">
                        <option value="">No Button</option>
                        <option value="web_app">Mini App</option>
                        <option value="inline_url">Regular URL</option>
                    </select>
                </div>
                <div id="button-text-field" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Button Text</label>
                    <input type="text" name="button_text" class="form-input" maxlength="64">
                    <p class="error-text hidden" id="button-text-error">Button text is required and must be 64 characters or less</p>
                </div>
                <div id="button-url-field" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Button URL</label>
                    <input type="url" name="button_url" class="form-input">
                    <p class="error-text hidden" id="button-url-error">Button URL must be valid</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Target Type</label>
                    <select name="target_type" required class="form-select" onchange="toggleTargetId()">
                        <option value="all">All Users</option>
                        <option value="last_day">Last Day Online</option>
                        <option value="last_week">Last Week Online</option>
                        <option value="last_month">Last Month Online</option>
                        <option value="specific">Specific User</option>
                    </select>
                </div>
                <div id="target-id-field" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Target ID (for specific user)</label>
                    <input type="text" name="target_id" class="form-input">
                    <p class="error-text hidden" id="target-id-error">Target ID is required for specific user</p>
                </div>
                <button type="submit" class="form-button bg-blue-500 text-white hover:bg-blue-600">Create Podcast</button>
            </form>
        </div>
        
        <!-- Admin Management -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Manage Admins</h2>
            <form method="POST" class="space-y-4 mb-4">
                <input type="hidden" name="add_admin" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Add Sub-admin (User ID)</label>
                    <input type="text" name="new_admin_id" required class="form-input">
                </div>
                <button type="submit" class="form-button bg-green-500 text-white hover:bg-green-600">Add Sub-admin</button>
            </form>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="remove_admin" value="1">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Remove Sub-admin (User ID)</label>
                    <input type="text" name="remove_admin_id" required class="form-input">
                </div>
                <button type="submit" class="form-button bg-red-500 text-white hover:bg-red-600">Remove Sub-admin</button>
            </form>
            <h3 class="text-lg font-semibold mt-6 mb-2 text-gray-800">Current Admins</h3>
            <div class="table-container">
                <table class="border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border p-2">Admin ID</th>
                            <th class="border p-2">Added By</th>
                            <th class="border p-2">Added At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td class="border p-2"><?= htmlspecialchars($admin['admin_id']) ?></td>
                                <td class="border p-2"><?= htmlspecialchars($admin['added_by']) ?></td>
                                <td class="border p-2"><?= htmlspecialchars($admin['added_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Podcast List -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Recent Podcasts</h2>
            <div class="table-container">
                <table class="border border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border p-2">ID</th>
                            <th class="border p-2">Title</th>
                            <th class="border p-2">Target</th>
                            <th class="border p-2">Created At</th>
                            <th class="border p-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($podcasts as $podcast): ?>
                            <tr>
                                <td class="border p-2"><?= htmlspecialchars($podcast['podcast_id']) ?></td>
                                <td class="border p-2"><?= htmlspecialchars($podcast['title']) ?></td>
                                <td class="border p-2"><?= htmlspecialchars($podcast['target_type'] === 'specific' ? $podcast['target_id'] : ucwords(str_replace('_', ' ', $podcast['target_type']))) ?></td>
                                <td class="border p-2"><?= date('Y-m-d H:i:s', $podcast['created_at']) ?></td>
                                <td class="border p-2"><?= htmlspecialchars($podcast['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Activities Modal -->
        <div id="activities-modal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Recent Activities</h3>
                <ul class="list-disc pl-5 text-sm text-gray-600">
                    <?php foreach ($recentActivities as $activity): ?>
                        <li>ID: <?= htmlspecialchars($activity['user_id']) ?> | <?= htmlspecialchars($activity['activity_type']) ?> | <?= date('Y-m-d H:i:s', $activity['activity_date']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <script>
        window.Telegram.WebApp.ready();
        window.Telegram.WebApp.expand();
        window.Telegram.WebApp.MainButton.hide();
        
        const modal = document.getElementById('activities-modal');
        const openModalBtn = document.getElementById('view-activities');
        const closeModalBtn = document.querySelector('.modal-close');
        
        openModalBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
        
        closeModalBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        function toggleButtonFields() {
            const buttonType = document.querySelector('select[name="button_type"]').value;
            const buttonTextField = document.getElementById('button-text-field');
            const buttonUrlField = document.getElementById('button-url-field');
            const buttonTextInput = document.querySelector('input[name="button_text"]');
            const buttonUrlInput = document.querySelector('input[name="button_url"]');
            const buttonTextError = document.getElementById('button-text-error');
            const buttonUrlError = document.getElementById('button-url-error');
            
            if (buttonType) {
                buttonTextField.classList.remove('hidden');
                buttonUrlField.classList.remove('hidden');
                buttonTextInput.required = true;
                buttonUrlInput.required = true;
            } else {
                buttonTextField.classList.add('hidden');
                buttonUrlField.classList.add('hidden');
                buttonTextInput.required = false;
                buttonUrlInput.required = false;
                buttonTextError.classList.add('hidden');
                buttonUrlError.classList.add('hidden');
            }
        }
        
        function toggleTargetId() {
            const targetType = document.querySelector('select[name="target_type"]').value;
            const targetIdField = document.getElementById('target-id-field');
            const targetIdInput = document.querySelector('input[name="target_id"]');
            const targetIdError = document.getElementById('target-id-error');
            
            if (targetType === 'specific') {
                targetIdField.classList.remove('hidden');
                targetIdInput.required = true;
            } else {
                targetIdField.classList.add('hidden');
                targetIdInput.required = false;
                targetIdError.classList.add('hidden');
            }
        }
        
        function validatePodcastForm() {
            let isValid = true;
            const titleInput = document.querySelector('input[name="title"]');
            const contentInput = document.querySelector('textarea[name="content"]');
            const imageInput = document.querySelector('input[name="image"]');
            const buttonType = document.querySelector('select[name="button_type"]').value;
            const buttonTextInput = document.querySelector('input[name="button_text"]');
            const buttonUrlInput = document.querySelector('input[name="button_url"]');
            const targetType = document.querySelector('select[name="target_type"]').value;
            const targetIdInput = document.querySelector('input[name="target_id"]');
            
            const titleError = document.getElementById('title-error');
            const contentError = document.getElementById('content-error');
            const imageError = document.getElementById('image-error');
            const buttonTextError = document.getElementById('button-text-error');
            const buttonUrlError = document.getElementById('button-url-error');
            const targetIdError = document.getElementById('target-id-error');
            
            // Reset error states
            titleError.classList.add('hidden');
            contentError.classList.add('hidden');
            imageError.classList.add('hidden');
            buttonTextError.classList.add('hidden');
            buttonUrlError.classList.add('hidden');
            targetIdError.classList.add('hidden');
            titleInput.classList.remove('border-red-500');
            contentInput.classList.remove('border-red-500');
            imageInput.classList.remove('border-red-500');
            buttonTextInput.classList.remove('border-red-500');
            buttonUrlInput.classList.remove('border-red-500');
            targetIdInput.classList.remove('border-red-500');
            
            // Validate title
            if (!titleInput.value || titleInput.value.length > 100) {
                titleError.classList.remove('hidden');
                titleInput.classList.add('border-red-500');
                isValid = false;
            }
            
            // Validate content
            if (!contentInput.value || contentInput.value.length > 1000) {
                contentError.classList.remove('hidden');
                contentInput.classList.add('border-red-500');
                isValid = false;
            }
            
            // Validate image
            if (imageInput.files.length > 0) {
                const file = imageInput.files[0];
                if (file.size > 2 * 1024 * 1024 || !file.type.includes('image/jpeg')) {
                    imageError.classList.remove('hidden');
                    imageInput.classList.add('border-red-500');
                    isValid = false;
                }
            }
            
            // Validate button fields
            if (buttonType) {
                if (!buttonTextInput.value || buttonTextInput.value.length > 64) {
                    buttonTextError.classList.remove('hidden');
                    buttonTextInput.classList.add('border-red-500');
                    isValid = false;
                }
                if (!buttonUrlInput.value || !isValidUrl(buttonUrlInput.value)) {
                    buttonUrlError.classList.remove('hidden');
                    buttonUrlInput.classList.add('border-red-500');
                    isValid = false;
                }
            }
            
            // Validate target ID
            if (targetType === 'specific' && !targetIdInput.value) {
                targetIdError.classList.remove('hidden');
                targetIdInput.classList.add('border-red-500');
                isValid = false;
            }
            
            return isValid;
        }
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    </script>
</body>
</html>
<?php
$db = null;
?>