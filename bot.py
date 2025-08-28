#!/usr/bin/env python3
import asyncio
import logging
import json
import time
import hashlib
import secrets
from typing import Optional, Dict, Any
import aiohttp
import mysql.connector
from mysql.connector import Error
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup, WebAppInfo
from telegram.ext import Application, CommandHandler, MessageHandler, filters, ContextTypes

# Configure logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Configuration
BOT_TOKEN = "7270345128:AAEuRX7lABDMBRh6lRU1d-4aFzbiIhNgOWE"
WEBAPP_URL = "https://your-domain.com"  # Replace with your domain
API_BASE_URL = "https://your-domain.com/backend/api.php"

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'database': 'uc_coin_db',
    'user': 'root',
    'password': '',
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci'
}

class DatabaseManager:
    def __init__(self):
        self.connection = None
        self.connect()
    
    def connect(self):
        try:
            self.connection = mysql.connector.connect(**DB_CONFIG)
            logger.info("Database connected successfully")
        except Error as e:
            logger.error(f"Database connection failed: {e}")
            raise
    
    def ensure_connection(self):
        if not self.connection or not self.connection.is_connected():
            self.connect()
    
    def execute_query(self, query: str, params: tuple = None):
        self.ensure_connection()
        cursor = self.connection.cursor(dictionary=True)
        try:
            cursor.execute(query, params)
            if query.strip().upper().startswith('SELECT'):
                return cursor.fetchall()
            else:
                self.connection.commit()
                return cursor.rowcount
        except Error as e:
            logger.error(f"Query execution failed: {e}")
            self.connection.rollback()
            raise
        finally:
            cursor.close()
    
    def get_user(self, user_id: str) -> Optional[Dict]:
        query = "SELECT * FROM users WHERE id = %s"
        result = self.execute_query(query, (user_id,))
        return result[0] if result else None
    
    def create_user(self, user_data: Dict) -> str:
        auth_key = secrets.token_hex(32)
        now = int(time.time() * 1000)
        
        query = """INSERT INTO users (
            id, first_name, last_name, avatar_url, auth_key,
            referred_by, joined_at, last_active
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
        
        params = (
            user_data['id'],
            user_data['first_name'],
            user_data.get('last_name', ''),
            user_data.get('avatar_url', ''),
            auth_key,
            user_data.get('referred_by', ''),
            now,
            now
        )
        
        self.execute_query(query, params)
        return auth_key
    
    def process_referral(self, referrer_id: str, referred_id: str):
        try:
            # Check if referrer exists
            if not self.get_user(referrer_id):
                return False
            
            # Check if referral already exists
            query = "SELECT id FROM referrals WHERE referrer_id = %s AND referred_id = %s"
            existing = self.execute_query(query, (referrer_id, referred_id))
            if existing:
                return False
            
            # Add referral record
            query = "INSERT INTO referrals (referrer_id, referred_id, earned) VALUES (%s, %s, %s)"
            self.execute_query(query, (referrer_id, referred_id, 200))
            
            # Update referrer's balance
            query = """UPDATE users SET 
                balance = balance + %s,
                total_earned = total_earned + %s,
                referral_count = referral_count + 1,
                xp = xp + 60
                WHERE id = %s"""
            self.execute_query(query, (200, 200, referrer_id))
            
            return True
        except Exception as e:
            logger.error(f"Referral processing failed: {e}")
            return False

# Initialize database manager
db_manager = DatabaseManager()

def generate_auth_url(user_id: str, auth_key: str, ref_id: str = None, ref_auth: str = None) -> str:
    """Generate secure authentication URL for web app"""
    params = {
        'id': user_id,
        'authKey': auth_key
    }
    
    if ref_id:
        params['ref'] = ref_id
    if ref_auth:
        params['refauth'] = ref_auth
    
    query_string = '&'.join([f"{k}={v}" for k, v in params.items()])
    return f"{WEBAPP_URL}?{query_string}"

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle /start command"""
    user = update.effective_user
    if not user:
        return
    
    user_id = str(user.id)
    ref_id = None
    ref_auth = None
    
    # Parse referral from start parameter
    if context.args:
        start_param = context.args[0]
        if start_param.startswith('ref_'):
            ref_id = start_param[4:]  # Remove 'ref_' prefix
        elif start_param.startswith('refauth_'):
            ref_auth = start_param[8:]  # Remove 'refauth_' prefix
    
    try:
        # Check if user exists
        existing_user = db_manager.get_user(user_id)
        
        if existing_user:
            # Existing user
            auth_key = existing_user['auth_key']
            
            # Update last active
            query = "UPDATE users SET last_active = %s WHERE id = %s"
            db_manager.execute_query(query, (int(time.time() * 1000), user_id))
            
            welcome_text = f"🎮 Welcome back, {user.first_name}!\n\n⛏️ Continue your DRX mining journey!"
        else:
            # New user
            user_data = {
                'id': user_id,
                'first_name': user.first_name,
                'last_name': user.last_name or '',
                'avatar_url': '',
                'referred_by': ref_id or ''
            }
            
            auth_key = db_manager.create_user(user_data)
            
            # Process referral if exists
            if ref_id and ref_id != user_id:
                success = db_manager.process_referral(ref_id, user_id)
                if success:
                    logger.info(f"Referral processed: {ref_id} -> {user_id}")
            
            welcome_text = f"🎮 Welcome to DRX Mining, {user.first_name}!\n\n⛏️ Start mining DRX coins\n💎 Get 100 DRX welcome bonus\n🎁 Complete missions for rewards\n👥 Invite friends to earn more!"
        
        # Generate secure auth URL
        auth_url = generate_auth_url(user_id, auth_key, ref_id, ref_auth)
        
        # Create inline keyboard with web app
        keyboard = InlineKeyboardMarkup([
            [InlineKeyboardButton("🎮 Open DRX Mining", web_app=WebAppInfo(url=auth_url))],
            [InlineKeyboardButton("📢 Join Channel", url="https://t.me/ligarx_boy")],
            [InlineKeyboardButton("👥 Invite Friends", switch_inline_query=f"🎮 Join DRX Mining and start earning!\n\n💎 Get 100 DRX welcome bonus\n⛏️ Mine to earn more DRX\n🎁 Complete missions for rewards\n\nJoin: https://t.me/{BOT_USERNAME}?start=ref_{user_id}")]
        ])
        
        await update.message.reply_text(
            welcome_text,
            reply_markup=keyboard,
            parse_mode='HTML'
        )
        
    except Exception as e:
        logger.error(f"Start command failed for user {user_id}: {e}")
        await update.message.reply_text(
            "❌ Something went wrong. Please try again later.",
            parse_mode='HTML'
        )

async def help_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle /help command"""
    help_text = """
🎮 <b>DRX Mining Bot Help</b>

⛏️ <b>Mining:</b>
• Start mining to earn DRX coins
• Minimum mining time: 30 minutes
• Maximum mining time: 24 hours
• Claim rewards every 5+ minutes

🚀 <b>Boosts:</b>
• Mining Speed: Increase efficiency
• Claim Time: Reduce minimum wait time
• Mining Rate: Earn more DRX per second

🎯 <b>Missions:</b>
• Join channels for rewards
• Complete timer tasks
• Enter promo codes
• Earn bonus DRX

💰 <b>Wallet:</b>
• Convert DRX to UC or Stars
• Instant processing
• Secure transactions

👥 <b>Referrals:</b>
• Invite friends with your link
• Earn 200 DRX per referral
• Build your mining network

🔧 <b>Commands:</b>
/start - Start the bot
/help - Show this help
/stats - View your statistics
"""
    
    await update.message.reply_text(help_text, parse_mode='HTML')

async def stats_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle /stats command"""
    user = update.effective_user
    if not user:
        return
    
    try:
        user_data = db_manager.get_user(str(user.id))
        if not user_data:
            await update.message.reply_text("❌ User not found. Please use /start first.")
            return
        
        # Get referral stats
        query = "SELECT COUNT(*) as count, COALESCE(SUM(earned), 0) as total FROM referrals WHERE referrer_id = %s"
        ref_stats = db_manager.execute_query(query, (str(user.id),))
        ref_count = ref_stats[0]['count'] if ref_stats else 0
        ref_earned = ref_stats[0]['total'] if ref_stats else 0
        
        stats_text = f"""
📊 <b>Your Statistics</b>

💰 <b>Balance:</b> {user_data['balance']:.3f} DRX
🏆 <b>Total Earned:</b> {user_data['total_earned']:.3f} DRX
⭐ <b>Level:</b> {user_data['level_num']} (XP: {user_data['xp']})
👥 <b>Referrals:</b> {ref_count} friends (+{ref_earned} DRX)

⛏️ <b>Mining Status:</b> {'🟢 Active' if user_data['is_mining'] else '🔴 Inactive'}
📈 <b>Mining Rate:</b> {user_data['mining_rate']:.6f} DRX/sec
⏱️ <b>Min Claim Time:</b> {user_data['min_claim_time']//60} minutes

🚀 <b>Boosts:</b>
• Speed Level: {user_data['mining_speed_level']}
• Time Level: {user_data['claim_time_level']}
• Rate Level: {user_data['mining_rate_level']}

📅 <b>Joined:</b> {time.strftime('%Y-%m-%d', time.localtime(user_data['joined_at']//1000))}
"""
        
        keyboard = InlineKeyboardMarkup([
            [InlineKeyboardButton("🎮 Open Game", web_app=WebAppInfo(url=generate_auth_url(str(user.id), user_data['auth_key'])))]
        ])
        
        await update.message.reply_text(stats_text, reply_markup=keyboard, parse_mode='HTML')
        
    except Exception as e:
        logger.error(f"Stats command failed: {e}")
        await update.message.reply_text("❌ Failed to get statistics.")

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle regular messages"""
    user = update.effective_user
    if not user:
        return
    
    # Check if user exists, if not redirect to start
    user_data = db_manager.get_user(str(user.id))
    if not user_data:
        await update.message.reply_text(
            "👋 Welcome! Please use /start to begin your DRX mining journey!",
            parse_mode='HTML'
        )
        return
    
    # Generate game URL
    auth_url = generate_auth_url(str(user.id), user_data['auth_key'])
    
    keyboard = InlineKeyboardMarkup([
        [InlineKeyboardButton("🎮 Open DRX Mining", web_app=WebAppInfo(url=auth_url))]
    ])
    
    await update.message.reply_text(
        "🎮 Click the button below to open DRX Mining!",
        reply_markup=keyboard,
        parse_mode='HTML'
    )

async def error_handler(update: object, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle errors"""
    logger.error(f"Exception while handling an update: {context.error}")

def main() -> None:
    """Start the bot"""
    # Create application
    application = Application.builder().token(BOT_TOKEN).build()
    
    # Add handlers
    application.add_handler(CommandHandler("start", start_command))
    application.add_handler(CommandHandler("help", help_command))
    application.add_handler(CommandHandler("stats", stats_command))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))
    
    # Add error handler
    application.add_error_handler(error_handler)
    
    # Start the bot
    logger.info("Starting DRX Mining Bot...")
    application.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == '__main__':
    main()