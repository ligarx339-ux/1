#!/bin/bash

echo "Installing Python dependencies for Telegram bot..."

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "Python 3 is not installed. Please install Python 3 first."
    exit 1
fi

# Check if pip is installed
if ! command -v pip3 &> /dev/null; then
    echo "pip3 is not installed. Please install pip3 first."
    exit 1
fi

# Install dependencies
pip3 install -r requirements.txt

echo "Dependencies installed successfully!"
echo ""
echo "To run the bot:"
echo "python3 bot.py"
echo ""
echo "Make sure to:"
echo "1. Set up MySQL database using setup_database.sql"
echo "2. Update database credentials in backend/config.php"
echo "3. Update domain URLs in bot.py and lib/api.ts"