<?php

namespace App\Console\Commands;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class MediaOptimizerCommand extends Command
{
    /**
     * Nama dan tanda tangan dari perintah konsol.
     *
     * @var string
     */
    protected $signature = 'media:optimize 
                           {--path=public : Path disk storage yang akan dioptimasi}
                           {--images : Hanya optimasi gambar}
                           {--videos : Hanya optimasi video}
                           {--force : Paksa optimasi meskipun file sudah dioptimasi}
                           {--dry-run : Tampilkan apa yang akan dioptimasi tanpa benar-benar melakukannya}';

    /**
     * Deskripsi perintah konsol.
     *
     * @var string
     */
    protected $description = 'Optimasi file media (gambar: 70-150KB, video: maks 1MB)';

    private $stats = [
        'images_processed' => 0,
        'videos_processed' => 0,
        'images_skipped' => 0,
        'videos_skipped' => 0,
        'errors' => 0,
        'total_size_before' => 0,
        'total_size_after' => 0,
    ];

    /**
     * Jalankan perintah konsol.
     */
    public function handle()
    {
        $path = $this->option('path');
        $imagesOnly = $this->option('images');
        $videosOnly = $this->option('videos');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 Memulai optimasi media untuk disk storage: {$path}");

        if ($dryRun) {
            $this->warn('🔍 MODE UJI COBA - Tidak ada file yang akan dimodifikasi');
        }

        // Dapatkan semua file dari storage
        $files = Storage::disk($path)->allFiles();

        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];

        $this->info('📁 Ditemukan '.count($files).' file untuk dianalisis');

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filePath = Storage::disk($path)->path($file);
            $fileSize = Storage::disk($path)->size($file);

            $this->stats['total_size_before'] += $fileSize;

            try {
                if (in_array($extension, $imageExtensions) && ! $videosOnly) {
                    $this->processImage($file, $filePath, $fileSize, $force, $dryRun);
                } elseif (in_array($extension, $videoExtensions) && ! $imagesOnly) {
                    $this->processVideo($file, $filePath, $fileSize, $force, $dryRun);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->newLine();
                $this->error("❌ Error memproses {$file}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->showSummary();

        return 0;
    }

    /**
     * Proses file gambar
     */
    private function processImage($file, $filePath, $currentSize, $force, $dryRun)
    {
        $targetMin = 70 * 1024;  // 70KB
        $targetMax = 150 * 1024; // 150KB

        // Lewati jika sudah dalam rentang target dan tidak dipaksa
        if (! $force && $currentSize >= $targetMin && $currentSize <= $targetMax) {
            $this->stats['images_skipped']++;

            return;
        }

        // Lewati jika sudah lebih kecil dari target minimum
        if ($currentSize < $targetMin) {
            $this->stats['images_skipped']++;

            return;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("🖼️  Akan mengoptimasi gambar: {$file} (".$this->formatBytes($currentSize).')');

            return;
        }

        $newSize = $this->optimizeImage($filePath, $currentSize, $targetMin, $targetMax);

        if ($newSize !== false) {
            $this->stats['images_processed']++;
            $this->stats['total_size_after'] += $newSize;
            $this->newLine();
            $this->info("✅ Gambar dioptimasi: {$file} (".$this->formatBytes($currentSize).' → '.$this->formatBytes($newSize).')');
        } else {
            $this->stats['images_skipped']++;
            $this->stats['total_size_after'] += $currentSize;
        }
    }

    /**
     * Proses file video
     */
    private function processVideo($file, $filePath, $currentSize, $force, $dryRun)
    {
        $targetMax = 1024 * 1024; // 1MB

        // Lewati jika sudah dalam target dan tidak dipaksa
        if (! $force && $currentSize <= $targetMax) {
            $this->stats['videos_skipped']++;

            return;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("🎥 Akan mengoptimasi video: {$file} (".$this->formatBytes($currentSize).')');

            return;
        }

        $newSize = $this->optimizeVideo($filePath, $currentSize, $targetMax);

        if ($newSize !== false) {
            $this->stats['videos_processed']++;
            $this->stats['total_size_after'] += $newSize;
            $this->newLine();
            $this->info("✅ Video dioptimasi: {$file} (".$this->formatBytes($currentSize).' → '.$this->formatBytes($newSize).')');
        } else {
            $this->stats['videos_skipped']++;
            $this->stats['total_size_after'] += $currentSize;
        }
    }

    /**
     * Optimasi file gambar
     */
    private function optimizeImage($filePath, $currentSize, $targetMin, $targetMax)
    {
        try {
            // Periksa apakah file ada dan dapat dibaca
            if (! file_exists($filePath) || ! is_readable($filePath)) {
                throw new \Exception("File tidak ditemukan atau tidak dapat dibaca: {$filePath}");
            }

            // Mulai dengan kualitas 85
            $quality = 85;
            $originalWidth = null;
            $originalHeight = null;

            // Coba tingkat kompresi yang berbeda
            for ($attempt = 0; $attempt < 10; $attempt++) {
                // Buat file sementara dengan nama unik dan ekstensi yang benar
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $tempPath = $filePath.'.temp.'.time().$attempt.'.'.$extension;

                try {
                    // Buat instance gambar baru setiap kali
                    $image = Image::make($filePath);

                    // Dapatkan dimensi asli pada percobaan pertama
                    if ($originalWidth === null) {
                        $originalWidth = $image->width();
                        $originalHeight = $image->height();
                    }

                    // Hitung dimensi resize jika diperlukan
                    if ($quality <= 60) {
                        // Kurangi dimensi 10% setiap kali kualitas di bawah 60
                        $reductionFactor = 0.9 - (($attempt - 5) * 0.1);
                        $newWidth = intval($originalWidth * $reductionFactor);
                        $newHeight = intval($originalHeight * $reductionFactor);

                        if ($newWidth > 50 && $newHeight > 50) {
                            $image->resize($newWidth, $newHeight, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                        }
                    }

                    // Simpan dengan kualitas saat ini, secara eksplisit mengatur format untuk keamanan
                    $image->save($tempPath, $quality, $extension);

                    // Periksa apakah file temp berhasil dibuat
                    if (! file_exists($tempPath)) {
                        throw new \Exception("Gagal membuat file sementara: {$tempPath}");
                    }

                    $newSize = filesize($tempPath);

                    // Periksa apakah kita mencapai target
                    if ($newSize <= $targetMax && $newSize >= $targetMin) {
                        // Sempurna! Ganti file asli
                        if (rename($tempPath, $filePath)) {
                            return $newSize;
                        } else {
                            unlink($tempPath);
                            throw new \Exception('Gagal mengganti file asli');
                        }
                    } elseif ($newSize > $targetMax) {
                        // Masih terlalu besar, kurangi kualitas
                        if ($quality > 60) {
                            $quality -= 10;
                        } else {
                            $quality = 60; // Pertahankan minimum
                        }
                    } else {
                        // Terlalu kecil, tingkatkan kualitas
                        if ($quality < 95) {
                            $quality += 5;
                        } else {
                            // Terima hasil ini
                            if (rename($tempPath, $filePath)) {
                                return $newSize;
                            } else {
                                unlink($tempPath);
                                throw new \Exception('Gagal mengganti file asli');
                            }
                        }
                    }

                    // Bersihkan file temp
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }

                } catch (\Exception $e) {
                    // Bersihkan file temp pada pengecualian apa pun
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    throw $e;
                }
            }

            return false;

        } catch (\Exception $e) {
            // Bersihkan file temp apa pun
            $pattern = $filePath.'.temp.*';
            foreach (glob($pattern) as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            throw $e;
        }
    }

    /**
     * Optimasi file video
     */
    private function optimizeVideo($filePath, $currentSize, $targetMax)
    {
        try {
            // Periksa apakah ffmpeg tersedia
            if (! $this->checkFFmpegAvailable()) {
                throw new \Exception('FFmpeg tidak tersedia pada sistem ini');
            }

            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($filePath);

            // Dapatkan informasi video
            $probe = $ffmpeg->getFFProbe();
            $duration = $probe->format($filePath)->get('duration');

            // Hitung target bitrate
            $targetBitrate = intval(($targetMax * 8) / $duration / 1024 * 0.8); // 80% dari maksimum teoritis

            if ($targetBitrate < 200) {
                $targetBitrate = 200; // Kualitas minimum
            }

            // Buat format dengan pengaturan kompresi
            $format = new X264('libmp3lame');
            $format->setKiloBitrate($targetBitrate);

            // Atur opsi codec video untuk kompresi yang lebih baik
            $format->setAdditionalParameters([
                '-preset', 'medium',
                '-crf', '28',
                '-vf', 'scale=640:480:force_original_aspect_ratio=decrease,pad=640:480:(ow-iw)/2:(oh-ih)/2',
                '-r', '24', // 24 fps
            ]);

            // Buat file output sementara
            $tempPath = $filePath.'.temp.mp4';

            $video->save($format, $tempPath);

            $newSize = filesize($tempPath);

            if ($newSize <= $targetMax) {
                // Berhasil! Ganti file asli
                rename($tempPath, $filePath);

                return $newSize;
            } else {
                // Masih terlalu besar, bersihkan
                unlink($tempPath);

                return false;
            }

        } catch (\Exception $e) {
            // Bersihkan file temp jika ada
            $tempPath = $filePath.'.temp.mp4';
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }
    }

    /**
     * Periksa apakah FFmpeg tersedia
     */
    private function checkFFmpegAvailable()
    {
        $output = shell_exec('which ffmpeg');

        return ! empty($output);
    }

    /**
     * Format bytes ke format yang dapat dibaca manusia
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Tampilkan ringkasan optimasi
     */
    private function showSummary()
    {
        $this->newLine(2);
        $this->info('📊 RINGKASAN OPTIMASI');
        $this->info('========================');
        $this->info('🖼️  Gambar diproses: '.$this->stats['images_processed']);
        $this->info('🎥 Video diproses: '.$this->stats['videos_processed']);
        $this->info('⏭️  Gambar dilewati: '.$this->stats['images_skipped']);
        $this->info('⏭️  Video dilewati: '.$this->stats['videos_skipped']);
        $this->info('❌ Error: '.$this->stats['errors']);

        $sizeBefore = $this->formatBytes($this->stats['total_size_before']);
        $sizeAfter = $this->formatBytes($this->stats['total_size_after']);
        $saved = $this->formatBytes($this->stats['total_size_before'] - $this->stats['total_size_after']);
        $percentage = $this->stats['total_size_before'] > 0 ?
            round((($this->stats['total_size_before'] - $this->stats['total_size_after']) / $this->stats['total_size_before']) * 100, 2) : 0;

        $this->info("💾 Total ukuran sebelum: {$sizeBefore}");
        $this->info("💾 Total ukuran sesudah: {$sizeAfter}");
        $this->info("🎯 Ruang tersimpan: {$saved} ({$percentage}%)");
        $this->newLine();
    }
}
