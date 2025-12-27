<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    private const MAX_FILE_SIZE = 5242880; // 5MB in bytes
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_WIDTH = 1200;

    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Upload and resize product image
     */
    public function uploadProductImage(UploadedFile $file, int $orderId, int $position): array
    {
        $this->validateImage($file);

        $filename = $this->generateFilename($file);
        $path = "products/{$orderId}/{$filename}";

        // Read and resize image
        $image = $this->manager->read($file);
        
        // Resize if width exceeds max
        if ($image->width() > self::MAX_WIDTH) {
            $image->scale(width: self::MAX_WIDTH);
        }

        // Save to storage
        $encoded = $image->toJpeg(quality: 85);
        Storage::disk('public')->put($path, $encoded);

        return [
            'file_path' => $path,
            'file_name' => $filename,
            'mime_type' => 'image/jpeg',
            'file_size' => Storage::disk('public')->size($path),
            'position' => $position,
        ];
    }

    /**
     * Upload evidence file (keep original quality)
     */
    public function uploadEvidence(UploadedFile $file, int $disputeId): array
    {
        $this->validateImage($file);

        $filename = $this->generateFilename($file);
        $path = "disputes/{$disputeId}/{$filename}";

        // Store original file
        $file->storeAs("disputes/{$disputeId}", $filename, 'public');

        return [
            'file_path' => $path,
            'file_name' => $filename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * Validate uploaded image
     */
    private function validateImage(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File size exceeds 5MB limit');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed');
        }
    }

    /**
     * Generate random filename
     */
    private function generateFilename(UploadedFile $file): string
    {
        return Str::random(40) . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Delete image from storage
     */
    public function deleteImage(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }
}
