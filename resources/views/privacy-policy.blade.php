<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Kebijakan Privasi - SAM</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --bg-primary: #0f0f11;
            --bg-secondary: #18181b;
            --bg-tertiary: #27272a;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --accent: #f59e0b;
            --accent-hover: #d97706;
            --accent-light: #fbbf24;
            --border: #27272a;
            --font-sans: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
            --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, monospace;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --bg-primary: #ffffff;
                --bg-secondary: #f4f4f5;
                --bg-tertiary: #e4e4e7;
                --text-primary: #18181b;
                --text-secondary: #52525b;
                --text-muted: #a1a1aa;
                --border: #e4e4e7;
            }
        }

        html { 
            font-family: var(--font-sans); 
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 48rem;
            margin: 0 auto;
            background: var(--bg-primary);
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .last-updated {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-family: var(--font-mono);
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--accent);
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        p {
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        ul {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
            color: var(--text-secondary);
        }

        li {
            margin-bottom: 0.5rem;
        }

        strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 3rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .divider::after {
            content: 'ENGLISH';
            background: var(--bg-primary);
            padding: 0 1rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
        }

        .contact-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-top: 2rem;
        }

        .contact-box p {
            margin-bottom: 0;
        }

        a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        a:hover {
            color: var(--accent-hover);
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            h1 { font-size: 1.75rem; }
            h2 { font-size: 1.25rem; }
            .container { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Indonesian Section -->
        <h1>Kebijakan Privasi</h1>
        <div class="last-updated">Terakhir Diperbarui: 9 Desember 2025</div>

        <p><strong>PT Media Selular Indonesia</strong> ("kami") mengoperasikan aplikasi <strong>SAM (Sales Assistant Mobile)</strong>. Aplikasi ini ditujukan khusus untuk penggunaan internal tim sales kami untuk manajemen kunjungan dan produktivitas.</p>

        <h2>1. Izin dan Pengumpulan Data</h2>
        
        <h3>a. Data Lokasi</h3>
        <p>Aplikasi SAM memerlukan izin lokasi (Fine & Coarse Location) saat aplikasi digunakan untuk memvalidasi aktivitas sales:</p>
        <ul>
            <li><strong>Validasi Kunjungan (Check-in/Check-out):</strong> Kami menggunakan lokasi Anda untuk memverifikasi bahwa Anda berada dalam radius yang diizinkan dari outlet (Geofencing) saat melakukan kunjungan.</li>
            <li><strong>Registrasi Outlet Baru (NOO):</strong> Menandai koordinat lokasi outlet baru secara otomatis saat Anda mendaftarkannya.</li>
        </ul>
        <p>Kami <strong>TIDAK</strong> melacak lokasi Anda secara terus-menerus di latar belakang. Lokasi hanya diambil saat Anda menekan tombol Check-in, Check-out, atau Simpan Lokasi.</p>

        <h3>b. Kamera dan Penyimpanan</h3>
        <p>Kami meminta akses ke kamera dan galeri untuk dokumentasi pekerjaan:</p>
        <ul>
            <li><strong>Bukti Kunjungan:</strong> Mengambil foto toko/outlet sebagai bukti kehadiran saat Check-in.</li>
            <li><strong>Dokumen Registrasi:</strong> Mengambil foto KTP, NPWP, dan fisik toko untuk keperluan verifikasi data outlet baru (NOO).</li>
        </ul>
        <p>Foto hanya diunggah ke sistem kami saat Anda mengirimkan laporan kunjungan atau registrasi.</p>

        <h3>c. Informasi Perangkat & Akun</h3>
        <p>Aplikasi ini menggunakan sistem login internal perusahaan. Kami mengumpulkan:</p>
        <ul>
            <li><strong>ID Perangkat:</strong> Untuk mengirimkan notifikasi penugasan (Push Notification via OneSignal).</li>
            <li><strong>Data Aktivitas:</strong> Riwayat kunjungan dan registrasi untuk laporan kinerja sales (Dashboard).</li>
        </ul>

        <h2>2. Keamanan Data</h2>
        <p>Semua data yang dikirimkan antara aplikasi dan server kami dienkripsi menggunakan protokol HTTPS yang aman. Kami menerapkan langkah-langkah keamanan standar industri untuk melindungi data Anda dari akses yang tidak sah.</p>

        <h2>3. Penghapusan Data (Hak Pengguna)</h2>
        <p>Sesuai dengan kebijakan Google Play, Anda berhak meminta penghapusan akun dan data terkait.</p>
        <ul>
            <li><strong>Cara Meminta Penghapusan:</strong> Silakan hubungi tim IT Support kami melalui email di <strong>irfan@completeselular.com</strong> dengan subjek "Permintaan Penghapusan Akun SAM".</li>
            <li><strong>Proses:</strong> Kami akan memverifikasi identitas Anda dan menghapus akun serta data pribadi Anda dari sistem kami dalam waktu 30 hari kerja, kecuali data transaksi yang wajib disimpan untuk keperluan audit perusahaan.</li>
        </ul>

        <h2>4. Layanan Pihak Ketiga</h2>
        <p>Aplikasi ini menggunakan layanan pihak ketiga yang mungkin mengumpulkan informasi yang digunakan untuk mengidentifikasi Anda:</p>
        <ul>
            <li>Google Play Services</li>
            <li>Google Maps Platform</li>
            <li>Firebase Analytics & Crashlytics</li>
            <li>OneSignal</li>
        </ul>

        <div class="divider"></div>

        <!-- English Section -->
        <h1>Privacy Policy</h1>
        <div class="last-updated">Last Updated: December 9, 2025</div>

        <p><strong>PT Media Selular Indonesia</strong> ("we", "us") operates the <strong>SAM (Sales Assistant Mobile)</strong> application. This app is intended for internal use by our sales team for visit management and productivity.</p>

        <h2>1. Permissions and Data Collection</h2>

        <h3>a. Location Data</h3>
        <p>SAM requires location permissions (Fine & Coarse) while the app is in use to validate sales activities:</p>
        <ul>
            <li><strong>Visit Validation (Check-in/Check-out):</strong> We use your location to verify that you are within the allowed radius of an outlet (Geofencing) when performing a visit.</li>
            <li><strong>New Outlet Registration (NOO):</strong> To automatically tag the coordinates of a new outlet when you register it.</li>
        </ul>
        <p>We do <strong>NOT</strong> track your location continuously in the background. Location is only captured when you tap Check-in, Check-out, or Save Location buttons.</p>

        <h3>b. Camera and Storage</h3>
        <p>We access your camera and gallery for work documentation:</p>
        <ul>
            <li><strong>Visit Evidence:</strong> Capturing photos of the store/outlet as proof of presence during Check-in.</li>
            <li><strong>Registration Documents:</strong> Capturing photos of ID Cards (KTP), Tax IDs (NPWP), and store physical appearance for new outlet verification.</li>
        </ul>
        <p>Photos are uploaded to our system only when you submit a visit report or registration form.</p>

        <h3>c. Device & Account Information</h3>
        <p>This app uses internal corporate login. We collect:</p>
        <ul>
            <li><strong>Device ID:</strong> To send assignment notifications (Push Notification via OneSignal).</li>
            <li><strong>Activity Data:</strong> History of visits and registrations for sales performance reporting (Dashboard).</li>
        </ul>

        <h2>2. Data Security</h2>
        <p>All data transmitted between the app and our servers is encrypted using secure HTTPS protocols.</p>

        <h2>3. Data Deletion (User Rights)</h2>
        <p>In compliance with Google Play policy, you have the right to request the deletion of your account and associated data.</p>
        <ul>
            <li><strong>How to Request Deletion:</strong> Please contact our IT Support via email at <strong>irfan@completeselular.com</strong> with the subject "SAM Account Deletion Request".</li>
            <li><strong>Process:</strong> We will verify your identity and delete your account and personal data within 30 business days, excluding transaction data required for corporate audit purposes.</li>
        </ul>

        <div class="contact-box">
            <h3>Contact Us</h3>
            <p>If you have any questions about this Privacy Policy, please contact us at: <a href="mailto:irfan@completeselular.com">irfan@completeselular.com</a></p>
        </div>
    </div>
</body>
</html>
