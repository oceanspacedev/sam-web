#!/bin/bash

# Backup dan restore script untuk cronjob media optimizer

echo "=== BACKUP DAN RESTORE CRONJOB ==="

# Fungsi untuk backup cronjob saat ini
backup_cronjob() {
    echo "📦 Membuat backup cronjob..."
    crontab -u www-data -l > /var/www/web-sam/cronjob-backup-$(date +%Y%m%d-%H%M%S).txt
    echo "✅ Backup berhasil dibuat di: cronjob-backup-$(date +%Y%m%d-%H%M%S).txt"
}

# Fungsi untuk hapus cronjob
remove_cronjob() {
    echo "🗑️  Menghapus cronjob Laravel scheduler..."
    crontab -u www-data -l | grep -v "artisan schedule:run" | crontab -u www-data -
    echo "✅ Cronjob berhasil dihapus"
}

# Fungsi untuk menampilkan status
show_status() {
    echo "📋 Status cronjob saat ini:"
    echo "=========================="
    crontab -u www-data -l 2>/dev/null || echo "Tidak ada cronjob yang terdaftar"
    echo ""
    echo "📝 Status scheduled command:"
    cd /var/www/web-sam && php artisan schedule:list
}

# Menu
case "$1" in
    "backup")
        backup_cronjob
        ;;
    "remove")
        backup_cronjob
        remove_cronjob
        ;;
    "status")
        show_status
        ;;
    *)
        echo "Usage: $0 {backup|remove|status}"
        echo ""
        echo "backup  - Backup cronjob saat ini"
        echo "remove  - Backup dan hapus cronjob"
        echo "status  - Tampilkan status cronjob dan schedule"
        ;;
esac
