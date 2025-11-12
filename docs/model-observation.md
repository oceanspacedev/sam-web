# Observasi Model Domain Web SAM

## Ruang Lingkup
- Menelaah struktur domain berdasarkan dokumentasi di `README.md:90-160` dan implementasi model Eloquent di `app/Models`.
- Memeriksa keterkaitan setiap model dengan migrasi terkait di `database/migrations` serta seed awal di `database/seeders`.

## Ikhtisar Model
- Hierarki organisasi mengikuti rantai **BadanUsaha → Division → Region → Cluster → Outlet**, sebagaimana dijelaskan pada `README.md:90-140` dan konsisten dengan relasi `belongsTo/hasMany` di masing-masing model.
- `Register` (lead intake) menyimpan snapshot outlet baru, lalu melalui hook `Register::booted()` (`app/Models/Register.php:64-118`) menyalin data ke `Outlet` saat status berubah menjadi `APPROVED`.
- `Visit` dan `PlanVisit` menangani aktivitas kunjungan aktual vs rencana; keduanya mengandalkan relasi ke `User` dan `Outlet` plus soft delete untuk menjaga jejak audit.
- Role & permission ditangani melalui tabel `roles`, `permissions`, dan pivot `role_permissions`, dengan trait `HasOrganizationalScope` (`app/Traits/HasOrganizationalScope.php`) yang menerapkan filter akses berdasarkan nama role.

## Temuan Permasalahan Model

### 1. `PlanVisit::scopeFilter` Menggunakan Kolom yang Tidak Ada
- **Lokasi:** `app/Models/PlanVisit.php:25-31` vs struktur tabel pada `database/migrations/2021_08_22_075923_plan_visits.php:16-32`.
- **Masalah:** Scope melakukan `where('nama_lengkap', ...)`, padahal tabel `plan_visits` hanya memiliki `user_id`, `outlet_id`, `tanggal_visit`, dan timestamp. Query akan gagal (Unknown column) begitu scope tersebut dieksekusi.
- **Dampak:** Pencarian plan visit di Filament/API tidak dapat digunakan dan berpotensi memunculkan error 500.
- **Saran:** Ganti kriteria pencarian ke kolom yang ada (mis. `tanggal_visit`) atau join ke relasi `user()` untuk mencari berdasarkan `users.nama_lengkap`.

### 2. Relasi `Outlet::user()` Tidak Pernah Bisa Mengembalikan Data
- **Lokasi:** `app/Models/Outlet.php:71-74` dibanding definisi tabel `users` pada `database/migrations/2014_10_12_000000_create_users_table.php:16-31`.
- **Masalah:** Relasi dideklarasikan sebagai `hasMany(User::class)` tanpa foreign key eksplisit. Laravel otomatis mengasumsikan kolom `users.outlet_id`, tetapi kolom tersebut tidak ada di migrasi. Akibatnya relasi selalu menghasilkan koleksi kosong.
- **Dampak:** Setiap fitur yang mencoba menelusuri pengguna dari outlet (mis. penugasan TM/ASC per outlet) akan gagal diam-diam dan mendorong dev mengulang logika join manual.
- **Saran:** Tentukan foreign key yang benar (mis. gunakan relasi khusus atau hapus method bila penugasan outlet ditentukan via hierarki, lalu dokumentasikan cara akses yang valid).

### 3. Ketidakselarasan Nama Role antara Model dan Seeder
- **Lokasi:** Logika akses memakai nama `'ASM'`, `'ASC'`, `'DSF/DM'`, `'SUPER ADMIN'` di `app/Models/User.php:53-87` dan `app/Traits/HasOrganizationalScope.php:20-83`, sedangkan seeder awal (`database/seeders/RoleSeeder.php:17-33`) hanya membuat `'TM'`, `'ASC'`, `'DSF'`, `'AR'`, `'ADMIN'`.
- **Masalah:** Instalasi baru akan berisi role berbeda dari yang diasumsikan model. Scope akses dan filter outlet per role tidak pernah cocok kecuali data seed ditimpa manual.
- **Dampak:** Hak akses menjadi ketat ke blok `default` pada trait, membuat ASM/DSF tidak memperoleh jangkauan wilayah yang seharusnya. Ini juga mempersulit onboarding environment baru karena data seed harus disesuaikan secara manual.
- **Saran:** Seragamkan daftar role (baik rename seeder maupun update logic ke nama nyata di DB) dan pertimbangkan enum/konstanta agar dependensi string literal tersentralisasi.

### 4. `scopeWithTmAscDsf` Mengambil TM & ASC dari Role ID yang Sama
- **Lokasi:** `app/Models/Outlet.php:86-109` memakai `->where('role_id', 2)` untuk join `tm_users` dan `asc_users`, sementara seed role menunjukkan `TM` berada di ID pertama (`database/seeders/RoleSeeder.php:17-26`).
- **Masalah:** Join `tm_users` sebenarnya menarik user ASC karena memakai ID yang sama dengan join ASC. Akibatnya kolom tambahan `tm_nama_lengkap` dan `asc_nama_lengkap` identik.
- **Dampak:** Laporan/export yang mengandalkan scope tersebut (mis. `app/Filament/Exports/OutletExporter.php`) menampilkan TM yang salah, sehingga assignment lapangan menjadi rancu.
- **Saran:** Gunakan konstanta/lookup berbasis nama role untuk menentukan ID TM/ASC/DSF secara dinamis, atau tambahkan konfigurasi mapping agar tidak hardcode angka.

## Rekomendasi Lanjutan
1. Tambahkan uji model sederhana (PHPUnit) untuk memastikan setiap scope dan relasi dasar bekerja, terutama untuk kolom pencarian dan eager loading penting.
2. Dokumentasikan daftar role resmi beserta cakupan filternya di satu sumber (mis. config atau enum) sehingga trait, seeder, dan UI menggunakan referensi yang sama.
3. Audit ulang relasi antar model untuk memastikan semuanya merepresentasikan foreign key yang benar; hapus relasi yang tidak dapat dipertahankan oleh skema saat ini untuk menghindari kebingungan developer baru.
