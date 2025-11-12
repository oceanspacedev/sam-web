# 📊 Logging & Observability Guide

## Overview

Aplikasi ini menggunakan **separate log files** per feature untuk memudahkan debugging dan monitoring. Setiap controller memiliki log file tersendiri dengan automatic daily rotation.

---

## 📁 Log File Locations

Semua log files berada di: `storage/logs/`

### **Daily Rotating Logs**

| Feature | Log File Pattern | Retention | Description |
|---------|------------------|-----------|-------------|
| **Visit** | `visit-YYYY-MM-DD.log` | 30 days | Check-in/out activities |
| **Lead** | `lead-YYYY-MM-DD.log` | 30 days | Lead registration activities |
| **NOO** | `noo-YYYY-MM-DD.log` | 30 days | New Outlet Opening activities |
| **Outlet** | `outlet-YYYY-MM-DD.log` | 30 days | Outlet photo/video updates |
| **Plan Visit** | `planvisit-YYYY-MM-DD.log` | 30 days | Plan visit create/delete activities |
| **API Errors** | `api-errors-YYYY-MM-DD.log` | 60 days | All API errors (errors only) |
| **General** | `laravel-YYYY-MM-DD.log` | 14 days | Other application logs |

**Note**: Files older than retention period are automatically deleted.

---

## 🔍 Log Contents by Feature

### **1. Visit Logs** (`visit-*.log`)

**What's logged:**
- ✅ Check-in requests with payload
- ✅ Check-in success with visit_id
- ✅ Check-out requests with payload
- ✅ Check-out success with duration
- ⚠️ Check-out failed (already checked out)
- ⚠️ Check-in failed (outlet not found)

**Example Log Entries:**
```
[2025-11-12 10:30:15] local.INFO: VisitController@submit request {"user_id":123,"role_id":3,"payload":{"kode_outlet":"OL001","latlong_in":"-6.123,106.456","tipe_visit":"canvasing"}}
[2025-11-12 10:30:16] local.INFO: Visit check-in success {"visit_id":456,"user_id":123,"outlet_id":789,"kode_outlet":"OL001"}
[2025-11-12 14:45:20] local.INFO: Visit check-out success {"visit_id":456,"user_id":123,"durasi":"255 minutes"}
[2025-11-12 14:46:05] local.WARNING: Visit check-out failed: already checked out {"user_id":123,"visit_id":456}
```

---

### **2. Lead Logs** (`lead-*.log`)

**What's logged:**
- ✅ Lead store initiated with full payload
- ✅ Lead store video saved (if video uploaded)
- ✅ Lead store completed with lead_id

**Example Log Entries:**
```
[2025-11-12 11:20:30] local.INFO: Lead store initiated {"user_id":123,"role_id":3,"payload":{"nama_outlet":"Toko ABC",...}}
[2025-11-12 11:20:35] local.INFO: Lead store video saved {"user_id":123,"stored_name":"lead-20251112112035-video-a1b2c3d4e5f6g-video.mp4"}
[2025-11-12 11:20:36] local.INFO: Lead store completed {"lead_id":789,"outlet_code":"LEAD789"}
```

---

### **3. NOO Logs** (`noo-*.log`)

**What's logged:**
- ✅ NOO store initiated with full payload
- ✅ NOO store video saved (if video uploaded)
- ⚠️ NOO store missing video file (if no video)
- ⚠️ NOO store missing photo file (if photo missing)
- ✅ NOO store completed with noo_id

**Example Log Entries:**
```
[2025-11-12 13:15:20] local.INFO: NOO store initiated {"user_id":456,"role_id":3,"payload":{"nama_outlet":"Toko XYZ",...}}
[2025-11-12 13:15:22] local.WARNING: NOO store missing video file {"user_id":456,"payload_outlet":"Toko XYZ"}
[2025-11-12 13:15:25] local.INFO: NOO store completed {"noo_id":101,"outlet_name":"Toko XYZ"}
```

---

### **4. Outlet Logs** (`outlet-*.log`)

**What's logged:**
- ✅ Outlet update foto initiated
- ✅ Outlet update foto success
- ⚠️ Outlet update foto failed (outlet not found)
- ⚠️ Failed to delete outlet media

**Example Log Entries:**
```
[2025-11-12 14:30:10] local.INFO: Outlet update foto initiated {"user_id":123,"kode_outlet":"OL001"}
[2025-11-12 14:30:15] local.INFO: Outlet update foto success {"user_id":123,"outlet_id":456,"kode_outlet":"OL001"}
```

---

### **5. Plan Visit Logs** (`planvisit-*.log`)

**What's logged:**
- ✅ Plan visit add initiated
- ✅ Plan visit add success
- ⚠️ Plan visit add failed (outlet not found, duplicate)
- ✅ Plan visit delete initiated
- ✅ Plan visit delete success (with deleted count)
- ⚠️ Plan visit delete failed (no records found)
- ✅ Plan visit delete realme initiated
- ✅ Plan visit delete realme success
- ⚠️ Plan visit delete realme failed (deadline passed, not found)

**Example Log Entries:**
```
[2025-11-12 09:00:00] local.INFO: Plan visit add initiated {"user_id":123,"payload":{"tanggal_visit":"2025-11-15","kode_outlet":"OL001"}}
[2025-11-12 09:00:01] local.INFO: Plan visit add success {"plan_visit_id":789,"user_id":123,"outlet_id":456,"tanggal_visit":"2025-11-15"}
[2025-11-12 16:00:00] local.INFO: Plan visit delete success {"user_id":123,"outlet_id":456,"deleted_count":5,"bulan":"11","tahun":"2025"}
```

---

### **6. API Errors Log** (`api-errors-*.log`)

**What's logged:**
- 🔴 All ERROR level logs from API
- 🔴 Exceptions and stack traces
- 🔴 Database errors
- 🔴 Validation failures

**Example:**
```
[2025-11-12 15:30:45] local.ERROR: Maximum execution time exceeded {"userId":789,"exception":"..."}
```

---

## 🛠️ How to Use

### **1. Monitor Specific Feature**

```bash
# Watch visit logs in real-time
tail -f storage/logs/visit-$(date +%Y-%m-%d).log

# Watch lead logs
tail -f storage/logs/lead-$(date +%Y-%m-%d).log

# Watch NOO logs
tail -f storage/logs/noo-$(date +%Y-%m-%d).log
```

### **2. Search for Specific User Activity**

```bash
# Find all visits by user_id 123
grep '"user_id":123' storage/logs/visit-*.log

# Find all leads created by user_id 456
grep '"user_id":456' storage/logs/lead-*.log
```

### **3. Count Errors Today**

```bash
# Count check-out failures today
grep "Visit check-out failed" storage/logs/visit-$(date +%Y-%m-%d).log | wc -l

# Count outlet not found errors
grep "outlet not found" storage/logs/visit-$(date +%Y-%m-%d).log | wc -l
```

### **4. Extract Specific Visit Session**

```bash
# Get all logs for visit_id 456
grep '"visit_id":456' storage/logs/visit-*.log
```

### **5. Monitor All Errors**

```bash
# Watch all API errors in real-time
tail -f storage/logs/api-errors-$(date +%Y-%m-%d).log
```

---

## 📈 Production Monitoring Tips

### **1. Daily Health Check**

```bash
#!/bin/bash
# daily-health-check.sh

TODAY=$(date +%Y-%m-%d)

echo "=== Visit Stats ==="
echo "Check-ins: $(grep -c 'Visit check-in success' storage/logs/visit-$TODAY.log)"
echo "Check-outs: $(grep -c 'Visit check-out success' storage/logs/visit-$TODAY.log)"
echo "Failed checkouts: $(grep -c 'check-out failed' storage/logs/visit-$TODAY.log)"

echo "=== Lead/NOO Stats ==="
echo "Leads: $(grep -c 'Lead store completed' storage/logs/lead-$TODAY.log)"
echo "NOOs: $(grep -c 'NOO store completed' storage/logs/noo-$TODAY.log)"

echo "=== Errors ==="
echo "Total errors: $(wc -l < storage/logs/api-errors-$TODAY.log)"
```

### **2. Alert on High Error Rate**

```bash
#!/bin/bash
# error-alert.sh

TODAY=$(date +%Y-%m-%d)
ERROR_COUNT=$(wc -l < storage/logs/api-errors-$TODAY.log 2>/dev/null || echo 0)

if [ $ERROR_COUNT -gt 100 ]; then
    echo "ALERT: High error rate detected - $ERROR_COUNT errors today"
    # Send notification (email, slack, etc)
fi
```

### **3. Export Logs for Analysis**

```bash
# Export last 7 days of visit logs
cat storage/logs/visit-$(date -d '7 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '6 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '5 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '4 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '3 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '2 days ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date -d '1 day ago' +%Y-%m-%d).log \
    storage/logs/visit-$(date +%Y-%m-%d).log \
    > visit-last-7-days.log
```

---

## ⚙️ Configuration

Log channels dikonfigurasi di: `config/logging.php`

```php
'visit' => [
    'driver' => 'daily',
    'path' => storage_path('logs/visit.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 30,
],
```

**Customize retention:**
- Edit `'days' => 30` to change retention period
- Example: `'days' => 60` keeps logs for 60 days

---

## 🚨 Troubleshooting

### **Issue: Log files not created**

**Solution**:
```bash
# Ensure storage/logs directory is writable
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs
```

### **Issue: Log files too large**

**Solution**: Daily rotation automatically manages file size. Adjust retention days if needed:
```php
// config/logging.php
'days' => 14, // Reduce from 30 to 14 days
```

### **Issue: Missing logs**

**Check**:
1. Verify `LOG_LEVEL` in `.env` (should be `debug` or `info`)
2. Check file permissions
3. Verify log channel is correctly configured

---

## 📊 Log Analysis Examples

### **1. Most Active Users (Visits)**

```bash
grep "Visit check-in success" storage/logs/visit-$(date +%Y-%m-%d).log | \
  grep -o '"user_id":[0-9]*' | \
  sort | uniq -c | sort -rn | head -10
```

### **2. Average Visit Duration**

```bash
grep "Visit check-out success" storage/logs/visit-$(date +%Y-%m-%d).log | \
  grep -o '"durasi":"[0-9]* minutes"' | \
  grep -o '[0-9]*' | \
  awk '{sum+=$1; count++} END {print sum/count " minutes"}'
```

### **3. Error Rate per Hour**

```bash
grep ERROR storage/logs/api-errors-$(date +%Y-%m-%d).log | \
  cut -d' ' -f1-2 | cut -d':' -f1-2 | \
  uniq -c
```

---

## 🔐 Security Notes

- ⚠️ Log files may contain **sensitive data** (user_id, location, etc.)
- 🔒 Ensure proper file permissions: `chmod 640 storage/logs/*.log`
- 🔒 Restrict access to authorized personnel only
- 🗑️ Implement log rotation and retention policies
- 🔐 Consider log encryption for compliance requirements

---

## 📝 Summary

**Files Modified:**
- ✅ `config/logging.php` - Added 5 custom channels (visit, lead, noo, outlet, planvisit)
- ✅ `app/Http/Controllers/API/VisitController.php` - Using `Log::channel('visit')`
- ✅ `app/Http/Controllers/API/RegisterController.php` - Using `Log::channel('lead')` & `Log::channel('noo')`
- ✅ `app/Http/Controllers/API/OutletController.php` - Using `Log::channel('outlet')`
- ✅ `app/Http/Controllers/API/PlanVisitController.php` - Using `Log::channel('planvisit')`

**Log Files Created Daily:**
- 📄 `storage/logs/visit-YYYY-MM-DD.log`
- 📄 `storage/logs/lead-YYYY-MM-DD.log`
- 📄 `storage/logs/noo-YYYY-MM-DD.log`
- 📄 `storage/logs/outlet-YYYY-MM-DD.log`
- 📄 `storage/logs/planvisit-YYYY-MM-DD.log`
- 📄 `storage/logs/api-errors-YYYY-MM-DD.log`

**Benefits:**
- ✅ Easy to isolate issues by feature
- ✅ Better performance (smaller files)
- ✅ Automatic rotation and cleanup
- ✅ Easier log analysis and monitoring
- ✅ Better observability for debugging

---

**Need Help?**
- Laravel Logging Docs: https://laravel.com/docs/logging
- Monolog Documentation: https://github.com/Seldaek/monolog
