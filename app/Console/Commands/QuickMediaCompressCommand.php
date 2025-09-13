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

        $this->info('🔄 Starting media compression...');

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
                $this->error("❌ Error: {$file} - ".$e->getMessage());
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
            // Try to load image with error handling
            $image = Image::make($filePath);

            // Convert to RGB if needed (fixes some encoding issues)
            if ($image->mime() !== 'image/jpeg' && $image->mime() !== 'image/png') {
                $image->encode('jpg', 85);
            }

            $quality = 85;

            // Try to compress for a maximum of 10 attempts
            for ($i = 0; $i < 10; $i++) {
                // Work on a clone to avoid reloading the original file
                $tempImage = clone $image;
                $tempPath = $filePath.'.temp.'.uniqid().'.jpg';

                try {
                    // Adjust dimensions progressively if quality reduction is not enough
                    if ($quality < 70 && $i > 2) {
                        $scale = 1 - (($i - 2) * 0.05); // Reduce size by 5% each step after 2 attempts
                        $tempImage->resize(intval($tempImage->width() * $scale), null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                    }

                    // Save the image with the current quality
                    $tempImage->save($tempPath, $quality);

                    if (! file_exists($tempPath)) {
                        throw new \Exception("Failed to create temporary file: {$tempPath}");
                    }

                    $newSize = filesize($tempPath);

                    if ($newSize <= $targetMax) {
                        // If it's within range or smaller than min, we accept it
                        if (rename($tempPath, $filePath)) {
                            return true;
                        } else {
                            unlink($tempPath);
                            throw new \Exception('Failed to replace original file');
                        }
                    } else {
                        // Still too large, reduce quality for the next iteration
                        $quality -= 5;
                        if ($quality < 40) {
                            $quality = 40;
                        } // Set a minimum quality
                    }

                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }

                } catch (\Exception $e) {
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    throw $e; // Re-throw to be caught by the outer catch block
                }
            }

            return false; // Return false if target size not met after all attempts

        } catch (\Exception $e) {
            // Fallback: Try with GD library directly
            return $this->compressImageWithGD($filePath, $currentSize, $targetMin, $targetMax);
        }
    }

    /**
     * Fallback image compression using GD library
     */
    private function compressImageWithGD($filePath, $currentSize, $targetMin, $targetMax)
    {
        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Create image resource based on extension
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($filePath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($filePath);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($filePath);
                    break;
                default:
                    return false;
            }

            if (! $image) {
                return false;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $quality = 85;

            // Try to compress
            for ($i = 0; $i < 5; $i++) {
                $tempPath = $filePath.'.temp.'.uniqid().'.jpg';

                // Resize if needed
                if ($currentSize > $targetMax * 2) {
                    $newWidth = intval($width * 0.9);
                    $newHeight = intval($height * 0.9);
                    $resized = imagecreatetruecolor($newWidth, $newHeight);

                    // Preserve transparency for PNG
                    if ($extension === 'png') {
                        imagealphablending($resized, false);
                        imagesavealpha($resized, true);
                    }

                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resized;
                    $width = $newWidth;
                    $height = $newHeight;
                }

                // Save as JPEG
                if (imagejpeg($image, $tempPath, $quality)) {
                    $newSize = filesize($tempPath);

                    if ($newSize <= $targetMax && $newSize >= $targetMin) {
                        imagedestroy($image);
                        if (rename($tempPath, $filePath)) {
                            return true;
                        } else {
                            unlink($tempPath);

                            return false;
                        }
                    } elseif ($newSize > $targetMax) {
                        $quality -= 15;
                        if ($quality < 10) {
                            $quality = 10;
                        }
                    } else {
                        imagedestroy($image);
                        if (rename($tempPath, $filePath)) {
                            return true;
                        } else {
                            unlink($tempPath);

                            return false;
                        }
                    }

                    unlink($tempPath);
                } else {
                    imagedestroy($image);

                    return false;
                }
            }

            imagedestroy($image);

            return false;

        } catch (\Exception $e) {
            return false;
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
            $outputPath = $filePath.'.temp.mp4';

            $command = 'ffmpeg -i '.escapeshellarg($filePath).' '.
                      '-vcodec libx264 -crf 28 '.
                      '-preset medium '.
                      '-acodec aac -b:a 64k '.
                      '-vf scale=640:480:force_original_aspect_ratio=decrease '.
                      '-r 24 '.
                      '-y '.escapeshellarg($outputPath).' 2>&1';

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
