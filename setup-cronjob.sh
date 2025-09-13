#!/bin/bash

# Script untuk setup cronjob Laravel scheduler
# Untuk menjalankan optimasi media setiap jam 11 malam

echo "🔧 Setting up cronjob untuk optimasi media..."

# Path ke direktori Laravel
LARAVEL_PATH="/var/www/web-sam"

# Path ke PHP executable (sesuaikan jika berbeda)
PHP_PATH="/usr/bin/php"

# User yang menjalankan web server (biasanya www-data untuk Apache/Nginx)
WEB_USER="www-data"

# Cronjob entry untuk Laravel scheduler
CRON_JOB="* * * * * cd $LARAVEL_PATH && $PHP_PATH artisan schedule:run >> /dev/null 2>&1"

# Cek apakah cronjob sudah ada
if crontab -u $WEB_USER -l 2>/dev/null | grep -q "artisan schedule:run"; then
    echo "⚠️  Cronjob Laravel scheduler sudah ada!"
else
    # Tambahkan cronjob
    (crontab -u $WEB_USER -l 2>/dev/null; echo "$CRON_JOB") | crontab -u $WEB_USER -
    echo "✅ Cronjob berhasil ditambahkan!"
fi

echo "📋 Cronjob yang terdaftar untuk user $WEB_USER:"
crontab -u $WEB_USER -l

echo ""
echo "📝 Jadwal optimasi media:"
echo "   - Waktu: Setiap hari jam 23:00 (11 malam)"
echo "   - Command: media:optimize --path=public"
echo "   - Log: storage/logs/media-optimization.log"
echo ""
echo "🚀 Untuk menjalankan manual: php artisan media:optimize --path=public"
echo "📊 Untuk test dry-run: php artisan media:optimize --path=public --dry-run"
