# SAM (Sales Assistant Mobile) - Backend & Admin Panel

**SAM (Sales Assistant Mobile)** adalah sistem *Sales Force Automation (SFA)* yang dirancang untuk membantu tim lapangan (Sales/Promotor) dalam melakukan manajemen outlet, kunjungan (visit), dan pelaporan aktivitas secara *real-time*.

Repository ini berisi *source code* untuk **Backend API** (yang digunakan oleh aplikasi mobile) dan **Web Admin Panel** (untuk manajemen pusat).

## 🛠 Tech Stack

Aplikasi ini dibangun menggunakan teknologi modern berbasis ekosistem Laravel:

*   **Framework**: [Laravel 12.x](https://laravel.com)
*   **Admin Panel**: [FilamentPHP v4](https://filamentphp.com)
*   **Language**: PHP 8.2+
*   **Database**: MySQL 8.0+
*   **Frontend (Admin)**: Livewire 3, TailwindCSS, Alpine.js
*   **API Authentication**: Laravel Sanctum
*   **Job Queue**: Laravel Horizon (Redis)
*   **Asset Bundler**: Vite

## 🏛 Arsitektur Sistem

### 1. Struktur Organisasi (Hierarchy)
Sistem ini menggunakan struktur wilayah yang hierarkis untuk memetakan area kerja sales. Struktur ini diimplementasikan menggunakan **Many-to-Many Relationships** (Pivot Tables) pada model `User`, memungkinkan satu user memegang banyak wilayah sekaligus.

Hierarki Wilayah:
`Badan Usaha` ➝ `Divisi` ➝ `Region` ➝ `Cluster` ➝ `Outlet`

### 2. Role & Permission (RBAC)
Sistem akses kontrol menggunakan logika **Organizational Scope**. Setiap `Role` memiliki level scope (`all`, `badanusaha`, `divisi`, `region`, `cluster`).
*   **Scope Logic**: Data yang ditampilkan kepada user difilter secara otomatis berdasarkan wilayah yang ditugaskan kepadanya (via Trait `HasOrganizationalScope`).
*   **Filament Integration**: Admin panel otomatis menyesuaikan tampilan data berdasarkan hak akses user yang sedang login.

### 3. Modul Utama
*   **User Management**: Manajemen pengguna aplikasi (Sales, Team Leader, Admin) dengan penugasan wilayah yang dinamis.
*   **Outlet Management**: Database toko/mitra. Dilengkapi dengan fitur *Lifecycle Automation* (otomatis arsip jika tidak dikunjungi dalam 30 hari).
*   **Visit Management**: Pencatatan kunjungan sales (Check-in/Check-out) dengan validasi Geolocation (LatLong) dan bukti foto.
*   **Plan Visit**: Perencanaan jadwal kunjungan sales (Harian/Mingguan).
*   **Registration**: Modul onboarding untuk mendaftarkan outlet baru dari lapangan.

## 🚀 Fitur Unggulan

*   **Automated Maintenance**: Scheduler harian (`outlets:maintain-lifecycle`) berjalan otomatis untuk membersihkan data sampah dan mengarsipkan outlet tidak aktif.
*   **Optimized API**: Endpoint API dirancang khusus untuk performa mobile apps, dengan response time cepat dan payload yang efisien.
*   **Media Management**: Sistem otomatis membersihkan file foto lama (bukti kunjungan) untuk menghemat storage server (via Trait `CleansUpMedia`).
*   **Export/Import**: Fitur export data skala besar menggunakan `Job Queue` agar tidak membebani server utama.

## 💻 Setup & Installation

1.  **Clone Repository**
    ```bash
    git clone https://github.com/CompleteLabs/web-sam.git
    cd web-sam
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install && npm run build
    ```

3.  **Environment Setup**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *Konfigurasi database dan redis di file `.env`.*

4.  **Database Migration & Seed**
    ```bash
    php artisan migrate --seed
    ```

5.  **Run Application**
    ```bash
    # Terminal 1: Web Server
    php artisan serve

    # Terminal 2: Queue Worker (Wajib untuk export/import & media processing)
    php artisan horizon
    # atau
    php artisan queue:work
    ```

## ⏰ Scheduled Tasks (Cron)

Aplikasi ini memiliki beberapa *background task* yang harus dijalankan via Scheduler:

*   `outlets:maintain-lifecycle`: Cek outlet inaktif & cleanup data.
*   `territories:cleanup-orphans`: Hapus wilayah yang tidak memiliki relasi.
*   `users:cleanup-pivots`: Bersihkan relasi user yang invalid.

Pastikan cron job server dikonfigurasi:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## 📱 API Documentation

Dokumentasi API tersedia dan digenerate otomatis menggunakan **Scramble**.
Akses di: `/docs/api` (jika diaktifkan di environment local/staging).

---
