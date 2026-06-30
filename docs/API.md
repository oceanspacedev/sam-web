# SAM API Documentation

Dokumen ini dibuat dari kode yang ada di repo, terutama `routes/api.php`, controller API, FormRequest, JsonResource, handler exception, dan schema tabel aktual via Laravel `Schema::getColumnListing`. Jangan menambah field request/response di client kalau tidak tercantum di dokumen ini atau di source code terkait.

Base path API adalah `/api`. Contoh path di dokumen ditulis tanpa domain, misalnya `POST /api/login`.

## Auth, Format, Error

Semua endpoint dalam group `auth:sanctum` membutuhkan header:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

Endpoint publik saat ini:

| Method | Path |
|---|---|
| POST | `/api/login` |
| POST | `/api/login/whatsapp/request-otp` |
| POST | `/api/login/whatsapp/verify-otp` |
| POST | `/api/notif` |
| POST | `/api/test-upload` |

Mayoritas response sukses memakai envelope:

```json
{
  "meta": {
    "code": "integer",
    "status": "success",
    "message": "string"
  },
  "data": "mixed",
  "errors": null
}
```

Catatan pagination: endpoint list yang langsung mengembalikan Laravel `JsonResource::collection($paginator)` dapat menambahkan top-level `links` dan field pagination bawaan Laravel di `meta`, selain `meta.pagination` custom dari controller.

Validation error dari Laravel:

```json
{
  "meta": {
    "code": 422,
    "status": "error",
    "message": "Validation error"
  },
  "data": null,
  "errors": {
    "field": ["message"]
  }
}
```

Custom API error memakai envelope yang sama dengan `meta.status = "error"`. Authentication error memakai `401` dan message `Unauthenticated. Please login again.`. Throttle error memakai `429` dan message `Too many requests. Please slow down.`.

Catatan exception:

| Status | Sumber |
|---|---|
| 400 | `BadRequestException` untuk business rule |
| 401 | auth gagal atau `UnauthorizedException` |
| 403 | `ForbiddenException` atau `abort(403, ...)` |
| 404 | `ResourceNotFoundException` atau `ModelNotFoundException` |
| 422 | validation, upload, runtime file error |
| 429 | throttle |
| 502 | kegagalan kirim OTP WhatsApp |

## Resource Schema

Resource berikut adalah bentuk field response yang dipakai endpoint. Field relasi berbasis `whenLoaded` hanya muncul kalau controller meload relasinya.

### `UserResource`

Fields:

`id`, `username`, `nama_lengkap`, `role_id`, `tm_id`, `id_notif`, `profile_photo_path`, `profile_photo_url`, `whatsapp_number`, `nomor_whatsapp`, `whatsapp_verified_at`.

Conditional fields:

`badan_usahas[]`, `divisis[]`, `regions[]`, `clusters[]` berisi item `{id, name}`. `role` berisi `{id, name, organizational_scope_level}`. `permissions` hanya muncul saat resource adalah user yang sedang login, berisi boolean:

`can_monitor_visit`, `can_manage_user`, `can_create_user`, `can_update_user`, `can_delete_user`, `can_manage_badan_usaha`, `can_manage_division`, `can_manage_region`, `can_manage_cluster`, `can_create_badan_usaha`, `can_update_badan_usaha`, `can_delete_badan_usaha`, `can_create_division`, `can_update_division`, `can_delete_division`, `can_create_region`, `can_update_region`, `can_delete_region`, `can_create_cluster`, `can_update_cluster`, `can_delete_cluster`.

### Organization Resources

`BadanUsahaResource`: `id`, `code`, `name`, `display_name`.

`DivisionResource`: `id`, `code`, `name`, `display_name`, `badanusaha_id`, conditional `badanusaha`.

`RegionResource`: `id`, `code`, `name`, `display_name`, `badanusaha_id`, `divisi_id`, conditional `badanusaha`, `divisi`.

`ClusterResource`: `id`, `code`, `name`, `display_name`, `badanusaha_id`, `divisi_id`, `region_id`, conditional `badanusaha`, `divisi`, `region`.

Relasi organisasi memakai bentuk `{id, code, name, display_name}`.

### `OutletCompactResource`

Fields: `id`, `kode_outlet`, `nama_outlet`, `status_outlet`, `latlong`, `radius`, conditional `badanusaha`, `divisi`, `region`, `cluster`.

### `OutletResource`

Fields:

`id`, `kode_outlet`, `nama_outlet`, `alamat_outlet`, `nama_pemilik_outlet`, `nomer_tlp_outlet`, `distric`, `poto_shop_sign`, `poto_depan`, `poto_kiri`, `poto_kanan`, `poto_ktp`, `video`, `limit`, `radius`, `latlong`, `last_reset_at`, `reset_count_yearly`, `status_outlet`.

Conditional fields: `badanusaha`, `region`, `cluster`, `divisi`.

`actions`: `can_reset`, `can_reset_location`, `can_delete`.

### `OutletChangeArchiveResource`

Fields: `id`, `outlet_id`, `kode_outlet`, `action`, `actor`, `old_values`, `new_values`, `changed_fields`, `restored_from_id`, `restored_by_user_id`, `restored_at`, `created_at`.

`actor`: `{id, name}`.

### `RegisterCompactResource`

Fields: `id`, `kode_outlet`, `nama_outlet`, `alamat_outlet`, `status`, `type`, `keterangan`, `distric`, `latlong`, `created_at`.

Conditional fields: `badanusaha`, `divisi`, `region`, `cluster`.

### `RegisterResource`

Fields:

`id`, `kode_outlet`, `nama_outlet`, `alamat_outlet`, `nama_pemilik_outlet`, `nomer_tlp_outlet`, `nomer_wakil_outlet`, `ktp_outlet`, `distric`, `poto_shop_sign`, `poto_depan`, `poto_kiri`, `poto_kanan`, `poto_ktp`, `video`, `latlong`, `radius`, `oppo`, `vivo`, `realme`, `samsung`, `xiaomi`, `fl`, `limit`, `status`, `type`, `keterangan`, `created_by_id`, `created_by`, `rejected_at`, `rejected_by_id`, `rejected_by`, `confirmed_at`, `confirmed_by_id`, `confirmed_by`, `approved_at`, `approved_by_id`, `approved_by`, `created_at`, `updated_at`.

Conditional fields: `region`, `cluster`, `badanusaha`, `divisi`.

`actions`: `can_confirm`, `can_reject`, `can_approve`.

### `VisitCompactResource`

Fields:

`id`, `tanggal_visit`, `user_id`, `visitable_type`, `visitable_id`, `outlet_id`, `register_id`, `tipe_visit`, `check_in_time`, `check_out_time`, `transaksi`, `durasi_visit`, `picture_visit_in`, `picture_visit_out`, `latlong_in`, `latlong_out`, `laporan_visit`.

Conditional fields: `visitable`, `outlet`, `register`, `user`.

### `VisitResource`

Fields:

`id`, `tanggal_visit`, `user_id`, `visitable_type`, `visitable_id`, `outlet_id`, `register_id`, `tipe_visit`, `latlong_in`, `latlong_out`, `check_in_time`, `check_out_time`, `laporan_visit`, `durasi_visit`, `picture_visit_in`, `picture_visit_out`, `transaksi`.

Conditional fields: `visitable`, `outlet`, `register`, `user`.

### `PlanVisitCompactResource`

Fields:

`id`, `user_id`, `visitable_type`, `visitable_id`, `outlet_id`, `register_id`, `target_type`, `target_type_label`, `type_register`, `register_type`, `target_badge_label`, `schedule_scope`, `period_start`, `period_end`, `schedule_week`, `schedule_year`, `tanggal_visit`.

Conditional fields: `visitable`, `outlet`, `register`.

### `PlanVisitResource`

Fields:

`id`, `tanggal_visit`, `user_id`, `visitable_type`, `visitable_id`, `outlet_id`, `register_id`, `schedule_scope`, `period_start`, `period_end`, `schedule_week`, `schedule_year`, `realized_at`, `realized_visit_id`, `is_realized`, `created_at`, `updated_at`.

Conditional fields: `user`, `visitable`, `outlet`, `register`, `realized_visit`.

### Raw `Register` Model

Beberapa workflow register mengembalikan model Eloquent `Register` mentah, bukan `RegisterResource`. Kolom tabel aktual:

`id`, `kode_outlet`, `badanusaha_id`, `divisi_id`, `nama_outlet`, `alamat_outlet`, `nama_pemilik_outlet`, `nomer_tlp_outlet`, `nomer_wakil_outlet`, `ktp_outlet`, `distric`, `region_id`, `cluster_id`, `poto_shop_sign`, `poto_depan`, `poto_kiri`, `poto_kanan`, `poto_ktp`, `video`, `oppo`, `vivo`, `realme`, `samsung`, `xiaomi`, `fl`, `latlong`, `limit`, `status`, `type`, `rejected_at`, `confirmed_at`, `approved_at`, `keterangan`, `tm_id`, `deleted_at`, `created_at`, `updated_at`, `created_by_id`, `rejected_by_id`, `confirmed_by_id`, `approved_by_id`.

## Authentication

### `POST /api/login`

Auth: public. Content-Type: `application/json`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `version` | yes | string, must be at least `2.1.0` by `version_compare` |
| `username` | yes | string |
| `password` | yes | string |
| `notif_id` | yes | string |

Output `200`: `meta.message = "Authenticated"`, `data = {access_token, token_type: "Bearer", user: UserResource}`, `errors = null`.

### `POST /api/logout`

Auth: required.

Input: none.

Output `200`: `meta.message = "Token Revoked"`, `data` is the return value of current token deletion, `errors = null`.

### `POST /api/login/whatsapp/request-otp`

Auth: public.

Input:

| Field | Required | Rule |
|---|---:|---|
| `whatsapp_number` | yes | normalized and validated by `WhatsAppNumber` |

Output `200`: `meta.message = "OTP berhasil dikirim."`, `data = {expires_in, masked_number}`.

Output `422`: custom validation envelope for invalid, unregistered, or inactive WhatsApp number.

Output `502`: `meta.message = "Gagal mengirim OTP WhatsApp."`, `data = null`.

### `POST /api/login/whatsapp/verify-otp`

Auth: public.

Input:

| Field | Required | Rule |
|---|---:|---|
| `whatsapp_number` | yes | normalized and validated by `WhatsAppNumber` |
| `otp` | yes | 6 digits after whitespace removal |
| `version` | no | if present, must be at least `2.0.0` |
| `notif_id` | no | string, saved to user notification id when present |

Output `200`: `meta.message = "Login berhasil."`, `data = {access_token, token_type: "Bearer", user: UserResource}`.

Output `422`: custom validation envelope for invalid OTP, invalid number, inactive number, or old version.

### `POST /api/user/whatsapp/request-otp`

Auth: required.

Input:

| Field | Required | Rule |
|---|---:|---|
| `whatsapp_number` | yes unless `nomor_whatsapp` is used | normalized and validated |
| `nomor_whatsapp` | alias | accepted as alias for `whatsapp_number` |

Output `200`: `meta.message = "OTP berhasil dikirim."`, `data = {expires_in, masked_number}`.

Output `422`: custom validation envelope for invalid number or number already used.

Output `502`: `meta.message = "Gagal mengirim OTP WhatsApp."`.

### `POST /api/user/whatsapp/verify-otp`

Auth: required.

Input:

| Field | Required | Rule |
|---|---:|---|
| `whatsapp_number` | yes unless `nomor_whatsapp` is used | normalized and validated |
| `nomor_whatsapp` | alias | accepted as alias for `whatsapp_number` |
| `otp` | yes | 6 digits after whitespace removal |

Output `200`: `meta.message = "WhatsApp berhasil diverifikasi."`, `data = UserResource`.

## Current User

### `GET /api/user`

Auth: required.

Input: none.

Output `200`: `meta.message = "Data profile user berhasil diambil"`, `data = {user: UserResource}`.

### `PUT /api/user`

Auth: required. Content-Type: `application/json`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `username` | no | string, max 255, no spaces, alpha dash, unique except current user |
| `nama_lengkap` | no | string, max 255 |
| `password` | no | nullable string, min 6 |

Output `200`: `meta.message = "Profil berhasil diperbarui"`, `data = UserResource`.

### `POST /api/user/photo`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `profile_photo` | yes | file image, mimes `jpg,jpeg,png,webp`, max 10240 KB |

Output `200`: `meta.message = "Foto profil berhasil diperbarui"`, `data = UserResource`.

### `GET /api/user/stats`

Auth: required.

Input: none.

Output `200`: `meta.message = "Statistik berhasil diambil"`, `data = {month, month_id, visit_count, noo_count, lead_count}`.

### `DELETE /api/user`

Auth: required.

Input: none.

Output `200`: `meta.message = "Akun berhasil dihapus"`, `data = null`.

## User Management

Permissions are checked by Spatie/Filament permissions in controller.

### `GET /api/users`

Auth: required. Permission: `ViewAny:User`.

Query:

| Field | Required | Rule |
|---|---:|---|
| `search` | no | string, searches `nama_lengkap` and `username` |
| `per_page` | no | integer, min 1, max 100, default 25 |

Output `200`: `meta.message = "Daftar user berhasil diambil"`, `meta.pagination = {current_page, per_page, has_more_pages}`, `data = UserResource[]`.

### `GET /api/users/{id}`

Auth: required. Permission: `ViewAny:User`.

Output `200`: `meta.message = "Detail user berhasil diambil"`, `data = UserResource`.

### `POST /api/users`

Auth: required. Permission: `Create:User`. Content-Type: `application/json`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `username` | yes | string, unique, max 255, no spaces, alpha dash |
| `nama_lengkap` | yes | string, max 255 |
| `password` | yes | string, min 6 |
| `role_id` | yes | integer, exists in `roles.id` |
| `id_notif` | no | nullable string, max 255 |
| `badanusaha_ids` | conditional | nullable array of existing non-deleted badan usaha ids |
| `divisi_ids` | conditional | nullable array of existing non-deleted division ids |
| `region_ids` | conditional | nullable array of existing non-deleted region ids |
| `cluster_ids` | conditional | nullable array of existing non-deleted cluster ids |

Organizational ids are validated by rule when provided. Controller also enforces required assignments based on the target role scope after fallback resolution.

Output `200`: `meta.message = "User berhasil dibuat"`, `data = UserResource`.

### `PUT /api/users/{id}`

Auth: required. Permission: `Update:User`. Content-Type: `application/json`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `username` | no | string, max 255, no spaces, alpha dash, unique except route user |
| `nama_lengkap` | no | string, max 255 |
| `password` | no | nullable string, min 6 |
| `role_id` | no | integer, exists in `roles.id` |
| `id_notif` | no | nullable string, max 255 |
| `badanusaha_ids` | conditional | nullable array of existing non-deleted ids |
| `divisi_ids` | conditional | nullable array of existing non-deleted ids |
| `region_ids` | conditional | nullable array of existing non-deleted ids |
| `cluster_ids` | conditional | nullable array of existing non-deleted ids |

Output `200`: `meta.message = "User berhasil diupdate"`, `data = UserResource`.

### `DELETE /api/users/{id}`

Auth: required. Permission: `Delete:User`.

Input: none.

Output `200`: `meta.message = "User berhasil dihapus"`, `data = null`.

Business rule: deleting self returns `400` with message `Tidak dapat menghapus akun sendiri`.

## Outlet

### `GET /api/outlet`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `page` | no | integer, min 1 |
| `search` | no | string |
| `per_page` | no | integer, min 1, default 10, max effective 50 |
| `status_outlet` | no | one of `MAINTAIN`, `MAINTANCE`, `UNMAINTAIN`, `UNMAINTANCE`, `UNPRODUCTIVE` |
| `badanusaha_id` | no | integer, min 1 |
| `divisi_id` | no | integer, min 1 |
| `region_id` | no | integer, min 1 |
| `cluster_id` | no | integer, min 1 |
| `lat` | no | numeric |
| `lng` | no | numeric |

Output `200` without `lat/lng`: `meta.message = "berhasil"`, `meta.pagination = {current_page, per_page, total, last_page}`, `data = OutletCompactResource[]` when `compact=true`, otherwise `OutletResource[]`.

Output `200` with both `lat` and `lng`: same message, no pagination, `data = resource[]`.

### `GET /api/outlet/{id}`

Auth: required.

Output `200`: `meta.message = "berhasil"`, `data = OutletResource`.

### `POST /api/outlet/{id}`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `alamat_outlet` | no | nullable string, max 2000 |
| `nama_pemilik_outlet` | no | nullable string, max 255 |
| `nomer_tlp_outlet` | no | nullable string, max 50 |
| `latlong` | no | nullable string, regex `latitude,longitude` |
| `poto_shop_sign` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `poto_depan` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `poto_kanan` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `poto_kiri` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `poto_ktp` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `photo0` to `photo4` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `photos` | no | array, max 5 files |
| `photos.*` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `video` | no | file validated by `VideoMimeOrSignature`, max 51200 KB |

Output `200`: `meta.message = "Outlet berhasil diupdate"`, `meta.archive_id` may be null or integer, `data = OutletResource`.

### `PATCH /api/outlet/{id}/reset`

Auth: required. Permission/gate: outlet reset.

Input: none.

Output `200`: `meta.message = "Media outlet berhasil direset"`, `meta.archive_id`, `data = null`.

Business rule: reset cooldown 30 days, max 4 resets per year. Violations return `400` with data including reset timing/count fields.

### `PATCH /api/outlet/{id}/reset-location`

Auth: required. Permission/gate: outlet reset location.

Input: none.

Output `200`: `meta.message = "Lokasi outlet dan data pendukung berhasil direset"`, `data = {last_reset_at, reset_count_yearly, archive_id}`.

### `GET /api/outlet/{id}/archives`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `page` | no | integer, min 1 |
| `per_page` | no | integer, min 1, max 50, default 10 |

Output `200`: `meta.message = "Riwayat perubahan outlet berhasil diambil"`, `meta.pagination = {current_page, per_page, total, last_page}`, `data = OutletChangeArchiveResource[]`.

### `PATCH /api/outlet/{id}/archives/{archiveId}/restore`

Auth: required. Permission: `Reset:Outlet` or `Update:Outlet`.

Input: none.

Output `200`: `meta.message = "Data outlet berhasil direstore dari arsip"`, `meta.archive_id`, `meta.restored_from_id`, `data = OutletResource`.

### `DELETE /api/outlet/{id}`

Auth: required.

Input: none.

Output `200`: `meta.message = "Outlet berhasil dihapus"`, `data = null`.

## Visit

### `GET /api/visit`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `period` | no | one of `today`, `day`, `week`, `month`, default `today` |
| `date` | no | date |
| `year` | no | integer 2000 to 2100 |
| `month` | no | integer 1 to 12 |
| `week` | no | integer 1 to 53 |
| `date_from` | no | date |
| `date_to` | no | date, after or equal `date_from` |
| `per_page` | no | integer, min 1, max 100, default 25 |

Filter priority: `date_from/date_to`, then `year/month`, then `period`.

Output `200`: `meta.message = "fetch visit succes"`, `meta.pagination = {current_page, per_page, total, last_page, has_more_pages}`, `data = VisitCompactResource[]` or `VisitResource[]`.

### `GET /api/visit/monitor`

Auth: required. Permission: `ViewAny:Visit`.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `date` | no | date, default today |
| `per_page` | no | integer, min 1, max 100, default 50 |

Output `200`: `meta.message = "fetch monitoring visit success"`, `meta.pagination = {current_page, per_page, has_more_pages}`, `data = VisitCompactResource[]` or `VisitResource[]`.

### `GET /api/visit/{id}`

Auth: required.

Output `200`: `meta.message = "fetch visit detail success"`, `data = VisitResource`.

If user cannot monitor all visits, controller restricts detail to own visits.

### `GET /api/visit/targets`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `search` | no | string |
| `context` | no | one of `extracall`, `planned`, default `extracall` |
| `lat` | required with `lng` | numeric, between -90 and 90 |
| `lng` | required with `lat` | numeric, between -180 and 180 |
| `nearby_radius_km` | no | numeric, between 5 and 10, default 10 |
| `limit` | no | integer, min 1, max 100, default 10 |

Output `200`: `meta.message = "berhasil mendapatkan target visit"`, `data = target[]`.

Outlet target fields:

`id`, `type`, `target_type`, `target_type_label`, `kode`, `nama`, `latlong`, `alamat`, `distric`, `badanusaha`, `divisi`, `region`, `cluster`, `plan_visit_min_days`, `radius`.

Register target adds: `type_register`, `register_type`.

### `POST /api/visit/checkin`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `outlet_id` | yes unless `register_id` is sent | nullable integer, exists in `outlets.id`; prohibited if `register_id` is sent |
| `register_id` | yes unless `outlet_id` is sent | nullable integer, exists in `registers.id`; prohibited if `outlet_id` is sent |
| `picture_visit` | yes | image file `jpg,jpeg,png`, max 10240 KB |
| `latlong_in` | yes | string, regex `latitude,longitude` |
| `tipe_visit` | yes | one of `PLANNED`, `EXTRACALL` |

Output `200`: `meta.message = "Check-in berhasil"`, `data = VisitResource`.

Business rules include: no active same-day visit, planned visit must exist today when `tipe_visit=PLANNED`, approved/rejected registers cannot be visited as register targets, LEAD visit limit is 4 per month.

### `POST /api/visit/{id}/checkout`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `latlong_out` | yes | string, regex `latitude,longitude` |
| `laporan_visit` | yes | string, max 3000 |
| `picture_visit` | yes | image file `jpg,jpeg,png`, max 10240 KB |
| `transaksi` | yes | one of `YES`, `NO` |

Output `200`: `meta.message = "Check-out berhasil"`, `data = VisitResource`.

## Plan Visit

### `GET /api/planvisit`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `period` | no | one of `today`, `day`, `week`, `month`, default `today` |
| `date` | no | date |
| `date_from` | no | date |
| `date_to` | no | date, after or equal `date_from` |
| `bulan` | no | integer 1 to 12 |
| `tahun` | no | integer 2000 to 2100 |
| `month` | no | integer 1 to 12 |
| `year` | no | integer 2000 to 2100 |
| `week` | no | integer 1 to 53 |
| `per_page` | no | integer, min 1, max 100, default 100 |

Filter priority: `bulan/tahun` or `month/year` without `week`, then `date_from/date_to`, then `period`.

Output `200`: `meta.message` can be `"berhasil"` or `"ok"` depending branch, `meta.pagination = {current_page, per_page, total, last_page, has_more_pages}`, `data = PlanVisitCompactResource[]` or `PlanVisitResource[]`.

### `POST /api/planvisit`

Auth: required. Content-Type: `application/json`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `tanggal_visit` | yes | date |
| `outlet_id` | yes unless `register_id` is sent | nullable integer, exists in `outlets.id`; prohibited if `register_id` is sent |
| `register_id` | yes unless `outlet_id` is sent | nullable integer, exists in `registers.id`; prohibited if `outlet_id` is sent |

Output `200`: `meta.message = "berhasil"`, `data = PlanVisitResource`.

Business rules include min plan day from system settings, duplicate plan prevention, and register visit permission.

### `DELETE /api/planvisit/{id}`

Auth: required.

Input body: none. `{id}` must be a numeric plan visit id.

Output `200`: `meta.message = "berhasil"`, `data = 1`.
Output `404`: `meta.status = "error"`, `meta.message = "Plan visit tidak ditemukan"`, `data = null`, `errors = null`.
Output `400`: `meta.status = "error"`, `meta.message = "Plan visit mingguan tidak dapat dihapus"`, `data = null`, `errors = null`.

Business rules: plan visit must belong to the authenticated user. Weekly plan visits cannot be deleted through this endpoint.

## Registers

### `GET /api/registers`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `per_page` | no | integer, min 1, max 100 |

Output `200`: `meta.message = "fetch register success"`, `data = RegisterCompactResource[]` or `RegisterResource[]`. If `per_page` is present, `meta.pagination = {current_page, per_page, total, last_page, has_more_pages}`.

### `GET /api/registers/all`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `search` | no | string, searches `nama_outlet`, `kode_outlet`, `distric` |
| `status` | no | one of `lead`, `pending`, `confirmed`, `approved`, `rejected` |
| `per_page` | no | integer, min 1, max 100 |

Output `200`: same shape as `GET /api/registers`.

### `GET /api/registers/pending`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `compact` | no | boolean, default true |
| `per_page` | no | integer, min 1, max 100 |

Output `200`: `meta.message = "fetch register success"`, `data = RegisterCompactResource[]` or `RegisterResource[]`. Includes pagination only when `per_page` is sent.

### `GET /api/registers/{id}`

Auth: required.

Output `200`: `meta.message = "berhasil"`, `data = RegisterResource`.

### `POST /api/registers/leads`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `nama_outlet` | yes | string, max 255 |
| `alamat_outlet` | yes | string |
| `nama_pemilik` | yes | string, max 255 |
| `nomer_pemilik` | yes | string, max 50 |
| `nomer_perwakilan` | no | nullable string, max 50 |
| `distric` | yes | string, max 255 |
| `latlong` | yes | string, regex `latitude,longitude` |
| `oppo` | yes | integer, min 0, max 99 |
| `vivo` | yes | integer, min 0, max 99 |
| `samsung` | yes | integer, min 0, max 99 |
| `xiaomi` | yes | integer, min 0, max 99 |
| `realme` | yes | integer, min 0, max 99 |
| `fl` | yes | integer, min 0, max 99 |
| `ktpnpwp` | no | nullable string, max 255 |
| `badanusaha_id` | conditional | nullable integer, existing non-deleted badan usaha |
| `divisi_id` | conditional | nullable integer, existing non-deleted division |
| `region_id` | conditional | nullable integer, existing non-deleted region |
| `cluster_id` | conditional | nullable integer, existing non-deleted cluster |
| `bu` | conditional | nullable string, max 255, code/name fallback |
| `div` | conditional | nullable string, max 255, code/name fallback |
| `reg` | conditional | nullable string, max 255, code/name fallback |
| `clus` | conditional | nullable string, max 255, code/name fallback |
| `photo0` to `photo3` | no | image file `jpg,jpeg,png`, max 10240 KB |
| `video` | no | file validated by `VideoMimeOrSignature`, max 51200 KB |

Controller requires complete organizational hierarchy after resolving request values and current user defaults.

Output `200`: `meta.message = "berhasil menambahkan LEAD " + nama_outlet`, `data = null`.

### `POST /api/registers/noos`

Auth: required. Content-Type: `multipart/form-data`.

Input: same as lead, except `ktpnpwp` is required and `photo0` to `photo4` are accepted.

Output `200`: `meta.message = "berhasil menambahkan register " + nama_outlet`, `data = null`.

### `PATCH /api/registers/{id}/upgrade`

Auth: required. Content-Type: `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `id` | from route | required integer, exists in `registers.id` |
| `noktp` | yes | string, max 20 |
| `photo` | yes | image file `jpg,jpeg,png`, max 10240 KB |

Output `200`: `meta.message = "berhasil menambahkan Lead " + request nama_outlet`, `data = null`.

Note: controller message uses `request->nama_outlet`, but `UpgradeLeadRequest` does not validate `nama_outlet`.

Business rule: target register must have `type = LEAD`; controller changes it to `NOO`.

### `PATCH /api/registers/{id}/confirm`

Auth: required. Authorization policy: `confirm`.

Input JSON:

| Field | Required | Rule |
|---|---:|---|
| `id` | yes | integer, exists in `registers.id`; current FormRequest expects this in the body |
| `status` | yes | one of `CONFIRMED`, `PENDING` |
| `limit` | yes | integer, min 0 |
| `kode_outlet` | yes | string, max 50, no whitespace |

Output `200`: `meta.message = "berhasil update"`, `data = raw Register model`.

Business rules: register must be `type = NOO` and current status must be `PENDING`.

### `PATCH /api/registers/{id}/approve`

Auth: required. Authorization policy: `approve`.

Input JSON:

| Field | Required | Rule |
|---|---:|---|
| `id` | yes | integer, exists in `registers.id`; current FormRequest expects this in the body |
| `status` | yes | exactly `APPROVED` |
| `duplicate_resolution` | no | one of `branch`, `override`, `reject`; default `branch` |

Output `200`: `meta.message = "berhasil update"`, `meta.outlet_id`, `meta.duplicate_resolution`, `meta.final_kode_outlet`, `meta.archive_id`, `data = raw Register model`.

Business rules: register must be `type = NOO`, status must be `CONFIRMED`, and `kode_outlet` must exist before approval.

### `PATCH /api/registers/{id}/reject`

Auth: required. Authorization policy: `reject`.

Input JSON:

| Field | Required | Rule |
|---|---:|---|
| `id` | yes | integer, exists in `registers.id`; current FormRequest expects this in the body |
| `status` | yes | exactly `REJECTED` |
| `alasan` | yes | string, max 500 |

Output `200`: `meta.message = "berhasil update"`, `data = raw Register model`.

Business rules: register must be `type = NOO`; already `APPROVED` or `REJECTED` registers cannot be rejected again.

### `GET /api/divisions/{id}/register-fields`

Auth: required.

Query:

| Field | Required | Rule |
|---|---:|---|
| `applies_to` | no | string, filters exact `applies_to` or `both` |

Output `200`: this endpoint does not use the standard envelope. It returns:

```json
{
  "data": [
    {
      "id": "mixed",
      "name": "mixed",
      "label": "mixed",
      "type": "mixed",
      "options": "mixed",
      "is_required": "mixed",
      "applies_to": "mixed"
    }
  ]
}
```

## Master Data

All endpoints below require auth.

### `GET /api/badanusaha`

Input: none.

Output `200`: `meta.message = "berhasil"`, `data = BadanUsahaResource[]`.

### `GET /api/divisi`

Query:

| Field | Required | Rule |
|---|---:|---|
| `bu` | no | badan usaha id, code, or name |

Output `200`: `meta.message = "berhasil"`, `data = DivisionResource[]`.

### `GET /api/region`

Query:

| Field | Required | Rule |
|---|---:|---|
| `bu` | no | badan usaha id, code, or name |
| `div` | no | division id, code, or name |

Output `200`: `meta.message = "berhasil"`, `data = RegionResource[]`.

### `GET /api/cluster`

Query:

| Field | Required | Rule |
|---|---:|---|
| `bu` | no | badan usaha id, code, or name |
| `div` | no | division id, code, or name |
| `reg` | no | region id, code, or name |

Output `200`: `meta.message = "berhasil"`, `data = ClusterResource[]`.

### `GET /api/form-options`

Query:

| Field | Required | Rule |
|---|---:|---|
| `role_id` | no | integer, exists in `roles.id` |
| `include_options` | no | boolean, default true |

Output `200`: `meta.message = "berhasil"`, `data = {scope_level, fields}`.

`fields` has keys `badanusaha`, `divisi`, `region`, `cluster`. Each field has `visible`, `required`, `options`.

Options are organization resources from `OrganizationalName::resource`, with parent ids included for division, region, and cluster options.

### `GET /api/roles`

Input: none.

Output `200`: `meta.message = "berhasil"`, `data = role[]` with fields `{id, name}`.

## Organization Management

All endpoints below require auth and permission checks. List endpoints use simple pagination with `meta.pagination = {current_page, per_page, has_more_pages}`. Delete endpoints return `data = null`.

Validation note: `code` is normalized with `OrganizationalName::formatCode`, `name` is trimmed, and `code`/`name` must be unique under the same parent.

### Badan Usaha Management

| Method | Path | Input | Output |
|---|---|---|---|
| GET | `/api/management/badanusaha` | query `search?: string`, `per_page?: integer 1..100` | `BadanUsahaResource[]`, message `Daftar badan usaha berhasil diambil` |
| GET | `/api/management/badanusaha/{id}` | none | `BadanUsahaResource`, message `Detail badan usaha berhasil diambil` |
| POST | `/api/management/badanusaha` | JSON `code` required string max 255, `name` required string max 255 | `201`, `BadanUsahaResource`, message `Badan usaha berhasil dibuat` |
| PUT | `/api/management/badanusaha/{id}` | JSON `code` sometimes required string max 255, `name` sometimes required string max 255 | `BadanUsahaResource`, message `Badan usaha berhasil diperbarui` |
| DELETE | `/api/management/badanusaha/{id}` | none | `data = null`, message `Badan usaha berhasil dihapus` |

### Division Management

| Method | Path | Input | Output |
|---|---|---|---|
| GET | `/api/management/divisi` | query `search?: string`, `badanusaha_id?: integer`, `per_page?: integer 1..100` | `DivisionResource[]`, message `Daftar divisi berhasil diambil` |
| GET | `/api/management/divisi/{id}` | none | `DivisionResource`, message `Detail divisi berhasil diambil` |
| POST | `/api/management/divisi` | JSON `badanusaha_id` required existing non-deleted id, `code` required string max 255, `name` required string max 255 | `201`, `DivisionResource`, message `Divisi berhasil dibuat` |
| PUT | `/api/management/divisi/{id}` | JSON `badanusaha_id`, `code`, `name` all optional but if present required | `DivisionResource`, message `Divisi berhasil diperbarui` |
| DELETE | `/api/management/divisi/{id}` | none | `data = null`, message `Divisi berhasil dihapus` |

### Region Management

| Method | Path | Input | Output |
|---|---|---|---|
| GET | `/api/management/region` | query `search?: string`, `badanusaha_id?: integer`, `divisi_id?: integer`, `per_page?: integer 1..100` | `RegionResource[]`, message `Daftar region berhasil diambil` |
| GET | `/api/management/region/{id}` | none | `RegionResource`, message `Detail region berhasil diambil` |
| POST | `/api/management/region` | JSON `divisi_id` required existing non-deleted id, `code` required string max 255, `name` required string max 255 | `201`, `RegionResource`, message `Region berhasil dibuat` |
| PUT | `/api/management/region/{id}` | JSON `divisi_id`, `code`, `name` all optional but if present required | `RegionResource`, message `Region berhasil diperbarui` |
| DELETE | `/api/management/region/{id}` | none | `data = null`, message `Region berhasil dihapus` |

### Cluster Management

| Method | Path | Input | Output |
|---|---|---|---|
| GET | `/api/management/cluster` | query `search?: string`, `badanusaha_id?: integer`, `divisi_id?: integer`, `region_id?: integer`, `per_page?: integer 1..100` | `ClusterResource[]`, message `Daftar cluster berhasil diambil` |
| GET | `/api/management/cluster/{id}` | none | `ClusterResource`, message `Detail cluster berhasil diambil` |
| POST | `/api/management/cluster` | JSON `region_id` required existing non-deleted id, `code` required string max 255, `name` required string max 255 | `201`, `ClusterResource`, message `Cluster berhasil dibuat` |
| PUT | `/api/management/cluster/{id}` | JSON `region_id`, `code`, `name` all optional but if present required | `ClusterResource`, message `Cluster berhasil diperbarui` |
| DELETE | `/api/management/cluster/{id}` | none | `data = null`, message `Cluster berhasil dihapus` |

Delete business rule: deletion is blocked when the record still has dependencies such as child organization records, outlets, registers, users, or system settings. Error is `400`.

## Utility and Legacy

### `POST /api/test-upload`

Auth: public. Content-Type: usually `multipart/form-data`.

Input:

| Field | Required | Rule |
|---|---:|---|
| `file` | no | uploaded file; when present passed to `FileUploadService::uploadImageOptimized` |
| `type` | no | string, default `photo` |

Output `200`: `meta.message = "Upload accepted"`, `data = null`.

Output `429`: `meta.message = "Rate limit exceeded"`, `data = {retry_after}`.

### `POST /api/notif`

Route exists, but the target method is `SendNotif::sendMessage($content, array $id)`. It does not read a `Request` object and does not return a JSON response. Because the route has no path parameters and the method requires scalar/array arguments, there is no reliable request body contract documented in the current code.

Do not build a client integration against `/api/notif` until the controller signature is changed to accept `Request` and returns a JSON envelope.

## Source Files

Route source: `routes/api.php`.

Request validation source:

`app/Http/Requests/API/*.php` and `app/Http/Requests/API/Management/*.php`.

Response/resource source:

`app/Http/Controllers/API/*.php`, `app/Http/Controllers/SettingController.php`, `app/Http/Resources/*.php`, `app/Http/Resources/*/*.php`.

Exception envelope source:

`bootstrap/app.php` and `app/Exceptions/Api/*.php`.
