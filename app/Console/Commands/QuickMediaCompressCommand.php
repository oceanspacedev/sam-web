<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class QuickMediaCompressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compress:media 
                           {--folder=public : Storage folder to compress}
                           {--type=all : Type of media to compress (all, images, videos)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quick compress media files in storage/app/public';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $folder = $this->option('folder');
        $type = $this->option('type');
        
        $this->info("🔄 Starting media compression...");
        
        // Get all files
        $files = Storage::disk($folder)->allFiles();
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        
        $processed = 0;
        $errors = 0;
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filePath = Storage::disk($folder)->path($file);
            $fileSize = Storage::disk($folder)->size($file);
            
            try {
                if (in_array($extension, $imageExtensions) && ($type === 'all' || $type === 'images')) {
                    if ($this->compressImage($filePath, $fileSize)) {
                        $processed++;
                        $this->info("✅ {$file} compressed");
                    }
                } elseif (in_array($extension, $videoExtensions) && ($type === 'all' || $type === 'videos')) {
                    if ($this->compressVideo($filePath, $fileSize)) {
                        $processed++;
                        $this->info("✅ {$file} compressed");
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Error: {$file} - " . $e->getMessage());
            }
        }
        
        $this->info("\n✨ Compression completed!");
        $this->info("📊 Files processed: {$processed}");
        $this->info("❌ Errors: {$errors}");
        
        return 0;
    }
    
    /**
     * Compress image to 70-150KB
     */
    private function compressImage($filePath, $currentSize)
    {
        $targetMin = 70 * 1024;  // 70KB
        $targetMax = 150 * 1024; // 150KB
        
        // Skip if already within range
        if ($currentSize >= $targetMin && $currentSize <= $targetMax) {
            return false;
        }
        
        // Skip if too small
        if ($currentSize < $targetMin) {
            return false;
        }
        
        try {
            $image = Image::make($filePath);
            $quality = 85;
            $width = $image->width();
            $height = $image->height();
            
            // Try to compress
            for ($i = 0; $i < 5; $i++) {
                $tempPath = $filePath . '.temp.' . uniqid();
                
                try {
                    // Resize if needed
                    if ($currentSize > $targetMax * 2) {
                        $image->resize($width, $height, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }
                    
                    $image->save($tempPath, $quality);
                    
                    if (!file_exists($tempPath)) {
                        throw new \Exception("Failed to create temporary file: {$tempPath}");
                    }
                    
                    $newSize = filesize($tempPath);
                    
                    if ($newSize <= $targetMax && $newSize >= $targetMin) {
                        if (rename($tempPath, $filePath)) {
                            return true;
                        } else {
                            unlink($tempPath);
                            throw new \Exception("Failed to replace original file");
                        }
                    } elseif ($newSize > $targetMax) {
                        $quality -= 15;
                        $width = intval($width * 0.9);
                        $height = intval($height * 0.9);
                    } else {
                        if (rename($tempPath, $filePath)) {
                            return true;
                        } else {
                            unlink($tempPath);
                            throw new \Exception("Failed to replace original file");
                        }
                    }
                    
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    
                } catch (\Exception $e) {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    throw $e;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Compress video to max 1MB
     */
    private function compressVideo($filePath, $currentSize)
    {
        $targetMax = 1024 * 1024; // 1MB
        
        // Skip if already within range
        if ($currentSize <= $targetMax) {
            return false;
        }
        
        try {
            // Simple ffmpeg command
            $outputPath = $filePath . '.temp.mp4';
            
            $command = "ffmpeg -i " . escapeshellarg($filePath) . " " .
                      "-vcodec libx264 -crf 28 " .
                      "-preset medium " .
                      "-acodec aac -b:a 64k " .
                      "-vf scale=640:480:force_original_aspect_ratio=decrease " .
                      "-r 24 " .
                      "-y " . escapeshellarg($outputPath) . " 2>&1";
            
            $output = shell_exec($command);
            
            if (file_exists($outputPath)) {
                $newSize = filesize($outputPath);
                
                if ($newSize <= $targetMax) {
                    rename($outputPath, $filePath);
                    return true;
                } else {
                    unlink($outputPath);
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
