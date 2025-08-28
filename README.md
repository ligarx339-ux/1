# UC Coin Ultra - PHP/MySQL Backend

## Setup Instructions

### 1. Database Setup
```bash
# Create MySQL database
mysql -u root -p < setup_database.sql
```

### 2. Backend Configuration
- Update `backend/config.php` with your database credentials
- Update domain URLs in the configuration

### 3. Bot Setup
```bash
# Install Python dependencies
pip install -r requirements.txt

# Run the bot
python bot.py
```

### 4. Migration from Firebase
```bash
# Migrate existing Firebase data to MySQL
php migrate_firebase_to_mysql.php firebasejson-to-php.json
```

### 5. Frontend Configuration
- Update API_BASE_URL in `lib/api.ts` with your domain
- Update WEBAPP_URL in `bot.py` with your domain

## Features

- ✅ Secure PHP/MySQL backend
- ✅ Telegram bot authentication
- ✅ Referral system with refAuth support
- ✅ Mining system with offline support
- ✅ 30-minute minimum mining time
- ✅ 5-minute minimum claim interval
- ✅ Rate limiting and security
- ✅ Firebase to MySQL migration
- ✅ Real-time countdown timers

## Security Features

- Auth key validation on every request
- Rate limiting per IP
- SQL injection protection
- Input validation and sanitization
- Secure session management