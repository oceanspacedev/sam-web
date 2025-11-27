# 🚀 Production Deployment Guide - Permission System Update

Panduan ini untuk deploy perubahan permission system dari development ke production server.

## ✅ Prerequisites

1. Backup database production terlebih dahulu
2. Export database production terbaru ke file `.sql`
3. Upload file SQL ke server
4. Pastikan semua file code sudah di-commit dan di-push

## 📋 Deployment Steps

### 1. Backup Database (PENTING!)

```bash
# Di production server
mysqldump -u username -p database_name > backup_before_migration_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Pull Latest Code

```bash
cd /path/to/web-sam
git pull origin v2-laravel12
```

### 3. Update Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Run Migrations

```bash
php artisan migrate --force
```

**Expected output:**
```
✓ 2025_11_19_142159_create_visits_archives_table
✓ 2025_11_19_142200_create_plan_visits_archives_table
✓ 2025_11_19_142652_create_registers_archives_table
✓ 2025_11_19_144829_add_organizational_scope_level_to_roles_table
✓ 2025_11_19_153133_make_organizational_fields_nullable_in_users_table
✓ 2025_11_19_153623_create_user_organizational_pivot_tables
✓ 2025_11_19_160356_drop_deprecated_organizational_columns_from_users_table
✓ 2025_11_19_225015_drop_filter_type_and_filter_data_from_roles_table
✓ 2025_11_20_154355_create_outlets_archives_table
✓ 2025_11_27_081000_add_guard_name_to_permissions_and_roles_tables
✓ 2025_11_27_082000_create_spatie_pivot_tables
✓ 2025_11_27_083000_rename_role_permissions_table
✓ 2025_12_01_000100_add_parent_role_id_to_roles_table
```

### 5. Clear All Caches

```bash
php artisan optimize:clear
php artisan filament:clear-cached-components
```

### 6. Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

### 7. Restart Services

```bash
# Restart PHP-FPM (adjust based on your server)
sudo systemctl restart php8.4-fpm

# Restart queue workers
php artisan queue:restart

# Restart Horizon (if using)
php artisan horizon:terminate
```

## 🔍 Verification Steps

### 1. Check Database Schema

```bash
php artisan tinker
```

```php
// Check roles table has new columns
\DB::select("SHOW COLUMNS FROM roles WHERE Field IN ('guard_name', 'parent_role_id', 'organizational_scope_level')");

// Check permissions table has guard_name
\DB::select("SHOW COLUMNS FROM permissions WHERE Field = 'guard_name'");

// Check tables exist
\DB::select("SHOW TABLES LIKE '%has_%'");
// Should show: model_has_permissions, model_has_roles, role_has_permissions

exit
```

### 2. Test User Permissions

```bash
php artisan tinker
```

```php
// Test SUPER ADMIN user
$user = \App\Models\User::where('username', 'appdev')->first();
echo "Role: " . $user->roles->first()?->name . "\n";
echo "Total permissions: " . $user->getAllPermissions()->count() . "\n";
echo "Can view outlets: " . ($user->can('view_any_outlet') ? 'YES' : 'NO') . "\n";
echo "Can view users: " . ($user->can('view_any_user') ? 'YES' : 'NO') . "\n";

exit
```

### 3. Login Test

1. Login sebagai user dengan role `SUPER ADMIN`
2. Verifikasi semua menu resources terlihat:
   - Dashboard ✓
   - Badan Usaha ✓
   - Divisions ✓
   - Regions ✓
   - Clusters ✓
   - Outlets ✓
   - Registers ✓
   - Plan Visits ✓
   - Visits ✓
   - Users ✓
   - Roles ✓
   - API Docs ✓

## 📊 Migration Summary

### Database Changes

**Tables Added:**
- `model_has_permissions` (Spatie)
- `model_has_roles` (Spatie)
- `visits_archives`
- `plan_visits_archives`
- `registers_archives`
- `outlets_archives`
- `user_badan_usaha` (pivot)
- `user_division` (pivot)
- `user_region` (pivot)
- `user_cluster` (pivot)

**Tables Modified:**
- `roles` - Added: `guard_name`, `parent_role_id`, `organizational_scope_level`
- `permissions` - Added: `guard_name`, `description`
- `role_has_permissions` - Renamed from `role_permissions`, added `id` and timestamps
- `users` - Removed: `badanusaha_id`, `divisi_id`, `region_id`, `cluster_id`

### Config Changes

**File:** `config/filament-shield.php`
```php
'super_admin' => [
    'name' => 'SUPER ADMIN', // Changed from 'super_admin'
],
```

### Code Statistics
- 21 files formatted
- 19 style issues fixed
- 13 tests passed
- 85 permissions migrated
- 346 role-permission assignments
- 555 user-role assignments

## 🐛 Troubleshooting

### Issue: Menu tidak muncul setelah login

**Solution:**
```bash
php artisan optimize:clear
php artisan filament:clear-cached-components
# Logout dan login ulang
```

### Issue: Permission denied errors

**Check:**
1. User punya role yang benar via `model_has_roles`
2. Role punya permissions via `role_has_permissions`
3. Guard name = 'web' di semua permissions dan roles

```bash
php artisan tinker
```

```php
$user = \App\Models\User::find(USER_ID);
echo "Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
echo "Permissions: " . $user->getAllPermissions()->count() . "\n";
```

### Issue: Migration fails

**Rollback steps:**
```bash
# Restore from backup
mysql -u username -p database_name < backup_before_migration_TIMESTAMP.sql

# Re-run migrations one by one with verbose output
php artisan migrate --step --force
```

## 📞 Support

Jika ada masalah:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check database untuk data consistency
3. Verify config cache is cleared
4. Test permissions via tinker

## ✨ Post-Deployment Checklist

- [ ] Database backup created
- [ ] Migrations completed successfully
- [ ] Caches cleared
- [ ] Production optimizations applied
- [ ] Services restarted
- [ ] SUPER ADMIN can access all menus
- [ ] Regular users can access their assigned resources
- [ ] No errors in logs
- [ ] Permission checks working correctly
- [ ] API endpoints functioning
- [ ] Tests passing (if running tests in production)

---

**Last Updated:** 2025-01-03
**Version:** v2-laravel12
**Tested On:** PHP 8.4.14, Laravel 12, MySQL 8.0
