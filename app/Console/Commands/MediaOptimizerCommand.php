<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

class MediaOptimizerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:optimize 
                           {--path=public : The storage disk path to optimize}
                           {--images : Only optimize images}
                           {--videos : Only optimize videos}
                           {--force : Force optimization even if file is already optimized}
                           {--dry-run : Show what would be optimized without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize media files (images: 70-150KB, videos: max 1MB)';

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
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        $imagesOnly = $this->option('images');
        $videosOnly = $this->option('videos');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        
        $this->info("🚀 Starting media optimization for storage disk: {$path}");
        
        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No files will be modified");
        }
        
        // Get all files from storage
        $files = Storage::disk($path)->allFiles();
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        
        $this->info("📁 Found " . count($files) . " files to analyze");
        
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filePath = Storage::disk($path)->path($file);
            $fileSize = Storage::disk($path)->size($file);
            
            $this->stats['total_size_before'] += $fileSize;
            
            try {
                if (in_array($extension, $imageExtensions) && !$videosOnly) {
                    $this->processImage($file, $filePath, $fileSize, $force, $dryRun);
                } elseif (in_array($extension, $videoExtensions) && !$imagesOnly) {
                    $this->processVideo($file, $filePath, $fileSize, $force, $dryRun);
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->newLine();
                $this->error("❌ Error processing {$file}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->showSummary();
        
        return 0;
    }
    
    /**
     * Process image file
     */
    private function processImage($file, $filePath, $currentSize, $force, $dryRun)
    {
        $targetMin = 70 * 1024;  // 70KB
        $targetMax = 150 * 1024; // 150KB
        
        // Skip if already within target range and not forcing
        if (!$force && $currentSize >= $targetMin && $currentSize <= $targetMax) {
            $this->stats['images_skipped']++;
            return;
        }
        
        // Skip if already smaller than minimum target
        if ($currentSize < $targetMin) {
            $this->stats['images_skipped']++;
            return;
        }
        
        if ($dryRun) {
            $this->newLine();
            $this->info("🖼️  Would optimize image: {$file} (" . $this->formatBytes($currentSize) . ")");
            return;
        }
        
        $newSize = $this->optimizeImage($filePath, $currentSize, $targetMin, $targetMax);
        
        if ($newSize !== false) {
            $this->stats['images_processed']++;
            $this->stats['total_size_after'] += $newSize;
            $this->newLine();
            $this->info("✅ Optimized image: {$file} (" . $this->formatBytes($currentSize) . " → " . $this->formatBytes($newSize) . ")");
        } else {
            $this->stats['images_skipped']++;
            $this->stats['total_size_after'] += $currentSize;
        }
    }
    
    /**
     * Process video file
     */
    private function processVideo($file, $filePath, $currentSize, $force, $dryRun)
    {
        $targetMax = 1024 * 1024; // 1MB
        
        // Skip if already within target and not forcing
        if (!$force && $currentSize <= $targetMax) {
            $this->stats['videos_skipped']++;
            return;
        }
        
        if ($dryRun) {
            $this->newLine();
            $this->info("🎥 Would optimize video: {$file} (" . $this->formatBytes($currentSize) . ")");
            return;
        }
        
        $newSize = $this->optimizeVideo($filePath, $currentSize, $targetMax);
        
        if ($newSize !== false) {
            $this->stats['videos_processed']++;
            $this->stats['total_size_after'] += $newSize;
            $this->newLine();
            $this->info("✅ Optimized video: {$file} (" . $this->formatBytes($currentSize) . " → " . $this->formatBytes($newSize) . ")");
        } else {
            $this->stats['videos_skipped']++;
            $this->stats['total_size_after'] += $currentSize;
        }
    }
    
    /**
     * Optimize image file
     */
    private function optimizeImage($filePath, $currentSize, $targetMin, $targetMax)
    {
        try {
            // Check if file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new \Exception("File not found or not readable: {$filePath}");
            }
            
            // Start with quality 85
            $quality = 85;
            $originalWidth = null;
            $originalHeight = null;
            
            // Try different compression levels
            for ($attempt = 0; $attempt < 10; $attempt++) {
                // Create temporary file with unique name
                $tempPath = $filePath . '.temp.' . time() . $attempt;
                
                try {
                    // Create fresh image instance each time
                    $image = Image::make($filePath);
                    
                    // Get original dimensions on first attempt
                    if ($originalWidth === null) {
                        $originalWidth = $image->width();
                        $originalHeight = $image->height();
                    }
                    
                    // Calculate resize dimensions if needed
                    if ($quality <= 60) {
                        // Reduce dimensions by 10% each time we go below quality 60
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
                    
                    // Save with current quality (keep original format)
                    $image->save($tempPath, $quality);
                    
                    // Check if temp file was created successfully
                    if (!file_exists($tempPath)) {
                        throw new \Exception("Failed to create temporary file: {$tempPath}");
                    }
                    
                    $newSize = filesize($tempPath);
                    
                    // Check if we hit the target
                    if ($newSize <= $targetMax && $newSize >= $targetMin) {
                        // Perfect! Replace original
                        if (rename($tempPath, $filePath)) {
                            return $newSize;
                        } else {
                            unlink($tempPath);
                            throw new \Exception("Failed to replace original file");
                        }
                    } elseif ($newSize > $targetMax) {
                        // Still too large, reduce quality
                        if ($quality > 60) {
                            $quality -= 10;
                        } else {
                            $quality = 60; // Keep at minimum
                        }
                    } else {
                        // Too small, increase quality
                        if ($quality < 95) {
                            $quality += 5;
                        } else {
                            // Accept this result
                            if (rename($tempPath, $filePath)) {
                                return $newSize;
                            } else {
                                unlink($tempPath);
                                throw new \Exception("Failed to replace original file");
                            }
                        }
                    }
                    
                    // Clean up temp file
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    
                } catch (\Exception $e) {
                    // Clean up temp file on any exception
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    throw $e;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Clean up any temp files
            $pattern = $filePath . '.temp.*';
            foreach (glob($pattern) as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            throw $e;
        }
    }
    
    /**
     * Optimize video file
     */
    private function optimizeVideo($filePath, $currentSize, $targetMax)
    {
        try {
            // Check if ffmpeg is available
            if (!$this->checkFFmpegAvailable()) {
                throw new \Exception('FFmpeg is not available on this system');
            }
            
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($filePath);
            
            // Get video information
            $probe = $ffmpeg->getFFProbe();
            $duration = $probe->format($filePath)->get('duration');
            
            // Calculate target bitrate
            $targetBitrate = intval(($targetMax * 8) / $duration / 1024 * 0.8); // 80% of theoretical max
            
            if ($targetBitrate < 200) {
                $targetBitrate = 200; // Minimum quality
            }
            
            // Create format with compression settings
            $format = new X264('libmp3lame');
            $format->setKiloBitrate($targetBitrate);
            
            // Set video codec options for better compression
            $format->setAdditionalParameters([
                '-preset', 'medium',
                '-crf', '28',
                '-vf', 'scale=640:480:force_original_aspect_ratio=decrease,pad=640:480:(ow-iw)/2:(oh-ih)/2',
                '-r', '24' // 24 fps
            ]);
            
            // Create temporary output file
            $tempPath = $filePath . '.temp.mp4';
            
            $video->save($format, $tempPath);
            
            $newSize = filesize($tempPath);
            
            if ($newSize <= $targetMax) {
                // Success! Replace original
                rename($tempPath, $filePath);
                return $newSize;
            } else {
                // Still too large, clean up
                unlink($tempPath);
                return false;
            }
            
        } catch (\Exception $e) {
            // Clean up temp file if exists
            $tempPath = $filePath . '.temp.mp4';
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }
    }
    
    /**
     * Check if FFmpeg is available
     */
    private function checkFFmpegAvailable()
    {
        $output = shell_exec('which ffmpeg');
        return !empty($output);
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Show optimization summary
     */
    private function showSummary()
    {
        $this->newLine(2);
        $this->info("📊 OPTIMIZATION SUMMARY");
        $this->info("========================");
        $this->info("🖼️  Images processed: " . $this->stats['images_processed']);
        $this->info("🎥 Videos processed: " . $this->stats['videos_processed']);
        $this->info("⏭️  Images skipped: " . $this->stats['images_skipped']);
        $this->info("⏭️  Videos skipped: " . $this->stats['videos_skipped']);
        $this->info("❌ Errors: " . $this->stats['errors']);
        
        $sizeBefore = $this->formatBytes($this->stats['total_size_before']);
        $sizeAfter = $this->formatBytes($this->stats['total_size_after']);
        $saved = $this->formatBytes($this->stats['total_size_before'] - $this->stats['total_size_after']);
        $percentage = $this->stats['total_size_before'] > 0 ? 
            round((($this->stats['total_size_before'] - $this->stats['total_size_after']) / $this->stats['total_size_before']) * 100, 2) : 0;
        
        $this->info("💾 Total size before: {$sizeBefore}");
        $this->info("💾 Total size after: {$sizeAfter}");
        $this->info("🎯 Space saved: {$saved} ({$percentage}%)");
        $this->newLine();
    }
}
