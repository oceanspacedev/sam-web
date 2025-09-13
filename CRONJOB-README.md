# Dokumentasi Cronjob Media Optimizer

## Deskripsi
Cronjob ini menjalankan optimasi media (gambar dan video) secara otomatis setiap hari pada jam 23:00 (11 malam).

## Jadwal
- **Waktu**: Setiap hari jam 23:00 (11 malam)
- **Command**: `media:optimize --path=public`
- **Log File**: `storage/logs/media-optimization.log`

## Setup

### 1. Automatic Setup (Recommended)
Jalankan script setup yang sudah disediakan:
```bash
sudo ./setup-cronjob.sh
```

### 2. Manual Setup
Jika ingin setup manual, tambahkan cronjob berikut:

```bash
# Edit crontab untuk user www-data
sudo crontab -u www-data -e

# Tambahkan baris berikut:
* * * * * cd /var/www/web-sam && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Fitur Cronjob

### Konfigurasi di Laravel Scheduler
- `withoutOverlapping()`: Mencegah job berjalan bersamaan
- `runInBackground()`: Menjalankan di background
- `appendOutputTo()`: Menyimpan log output

### Target Optimasi
- **Gambar**: 70-150KB (jpg, jpeg, png, webp, gif)
- **Video**: Maksimal 1MB (mp4, avi, mov, wmv, flv, mkv)

## Monitoring

### Cek Status Cronjob
```bash
# Lihat cronjob yang aktif
sudo crontab -u www-data -l

# Cek log optimasi
tail -f storage/logs/media-optimization.log

# Cek log Laravel scheduler
tail -f storage/logs/laravel.log
```

### Test Manual
```bash
# Test dry-run (tidak mengubah file)
php artisan media:optimize --path=public --dry-run

# Jalankan optimasi sekarang
php artisan media:optimize --path=public

# Optimasi hanya gambar
php artisan media:optimize --path=public --images

# Optimasi hanya video
php artisan media:optimize --path=public --videos

# Paksa optimasi file yang sudah dioptimasi
php artisan media:optimize --path=public --force
```

## Troubleshooting

### Cronjob Tidak Berjalan
1. Cek apakah cron service aktif:
   ```bash
   sudo systemctl status cron
   ```

2. Cek permission file:
   ```bash
   ls -la /var/www/web-sam/artisan
   ```

3. Cek path PHP:
   ```bash
   which php
   ```

### Error FFmpeg
Jika error video optimization, install FFmpeg:
```bash
sudo apt update
sudo apt install ffmpeg
```

### Permission Issues
Pastikan ownership yang benar:
```bash
sudo chown -R www-data:www-data /var/www/web-sam/storage
sudo chmod -R 775 /var/www/web-sam/storage
```

## Log Files
- **Media Optimization**: `storage/logs/media-optimization.log`
- **Laravel Application**: `storage/logs/laravel.log`
- **System Cron**: `/var/log/syslog` atau `/var/log/cron.log`
