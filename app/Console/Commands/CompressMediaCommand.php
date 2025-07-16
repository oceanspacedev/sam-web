<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

class CompressMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:compress {--path=public} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress images and videos in storage/app/public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        $force = $this->option('force');
        
        $this->info("Starting media compression for path: {$path}");
        
        // Get all files from storage/app/public
        $files = Storage::disk('public')->allFiles();
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        
        $compressedImages = 0;
        $compressedVideos = 0;
        $errors = 0;
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filePath = Storage::disk('public')->path($file);
            $fileSize = Storage::disk('public')->size($file);
            
            try {
                if (in_array($extension, $imageExtensions)) {
                    if ($this->compressImage($filePath, $fileSize, $force)) {
                        $compressedImages++;
                        $this->info("✓ Compressed image: {$file}");
                    }
                } elseif (in_array($extension, $videoExtensions)) {
                    if ($this->compressVideo($filePath, $fileSize, $force)) {
                        $compressedVideos++;
                        $this->info("✓ Compressed video: {$file}");
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("✗ Error compressing {$file}: " . $e->getMessage());
            }
        }
        
        $this->info("\n=== Compression Summary ===");
        $this->info("Images compressed: {$compressedImages}");
        $this->info("Videos compressed: {$compressedVideos}");
        $this->info("Errors: {$errors}");
        
        return 0;
    }
    
    /**
     * Compress image file
     */
    private function compressImage($filePath, $currentSize, $force = false)
    {
        $targetMin = 70 * 1024;  // 70KB
        $targetMax = 150 * 1024; // 150KB
        
        // Skip if already within target range and not forcing
        if (!$force && $currentSize >= $targetMin && $currentSize <= $targetMax) {
            return false;
        }
        
        // Skip if already smaller than minimum target
        if ($currentSize < $targetMin) {
            return false;
        }
        
        $image = Image::make($filePath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        
        // Start with quality 85
        $quality = 85;
        $width = $originalWidth;
        $height = $originalHeight;
        
        // Try different compression levels
        for ($attempt = 0; $attempt < 10; $attempt++) {
            // Create temporary file with unique name using timestamp
            $tempPath = $filePath . '.temp.' . time() . '.' . $attempt;
            
            try {
                // Create fresh image instance
                $tempImage = Image::make($filePath);
                
                // Resize if needed
                if ($width !== $originalWidth || $height !== $originalHeight) {
                    $tempImage->resize($width, $height, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
                
                // Save with specific quality
                $tempImage->save($tempPath, $quality);
                
                // Check if temp file was created successfully
                if (!file_exists($tempPath)) {
                    throw new \Exception("Failed to create temporary file: {$tempPath}");
                }
                
                $newSize = filesize($tempPath);
                
                // Check if we hit the target
                if ($newSize <= $targetMax && $newSize >= $targetMin) {
                    // Perfect! Replace original
                    if (rename($tempPath, $filePath)) {
                        return true;
                    } else {
                        unlink($tempPath);
                        throw new \Exception("Failed to replace original file");
                    }
                } elseif ($newSize > $targetMax) {
                    // Still too large, reduce quality or size
                    if ($quality > 60) {
                        $quality -= 10;
                    } else {
                        // Reduce dimensions by 10%
                        $width = intval($width * 0.9);
                        $height = intval($height * 0.9);
                        $quality = 85; // Reset quality
                    }
                } else {
                    // Too small, increase quality
                    if ($quality < 95) {
                        $quality += 5;
                    } else {
                        // Accept this result
                        if (rename($tempPath, $filePath)) {
                            return true;
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
    }
    
    /**
     * Compress video file
     */
    private function compressVideo($filePath, $currentSize, $force = false)
    {
        $targetMax = 1024 * 1024; // 1MB
        
        // Skip if already within target and not forcing
        if (!$force && $currentSize <= $targetMax) {
            return false;
        }
        
        try {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
            ]);
            
            $video = $ffmpeg->open($filePath);
            
            // Create format with compression settings
            $format = new X264('libmp3lame');
            $format->setKiloBitrate(500); // Start with 500kbps
            
            // Calculate target bitrate based on file size
            $probe = $ffmpeg->getFFProbe();
            $duration = $probe->format($filePath)->get('duration');
            $targetBitrate = intval(($targetMax * 8) / $duration / 1024 * 0.8); // 80% of theoretical max
            
            if ($targetBitrate < 200) {
                $targetBitrate = 200; // Minimum quality
            }
            
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
                return true;
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
}
