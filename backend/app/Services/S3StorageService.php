<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class S3StorageService
{
    protected string $disk;
    protected string $basePath;

    public function __construct()
    {
        // Use 's3' disk if configured, otherwise fallback to 'public'
        $this->disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $this->basePath = 'evidence';
    }

    /**
     * Upload file to S3
     */
    public function uploadEvidence(UploadedFile $file, int $userId, int $disputeId): array
    {
        try {
            // Generate unique filename
            $filename = $this->generateFilename($file);
            $path = "{$this->basePath}/user-{$userId}/dispute-{$disputeId}/{$filename}";

            // Store file
            $storedPath = Storage::disk($this->disk)->putFileAs(
                dirname($path),
                $file,
                basename($path),
                'public'
            );

            // Get URL
            $url = $this->disk === 's3' 
                ? Storage::disk($this->disk)->url($storedPath)
                : Storage::disk($this->disk)->url($storedPath);

            return [
                'success' => true,
                'path' => $storedPath,
                'url' => $url,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload multiple evidence files
     */
    public function uploadMultipleEvidence(array $files, int $userId, int $disputeId): array
    {
        $results = [];

        foreach ($files as $file) {
            $results[] = $this->uploadEvidence($file, $userId, $disputeId);
        }

        return $results;
    }

    /**
     * Delete evidence file
     */
    public function deleteEvidence(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get evidence file URL
     */
    public function getEvidenceUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);
        
        return $basename . '-' . time() . '-' . Str::random(8) . '.' . $extension;
    }

    /**
     * Validate evidence file
     */
    public function validateEvidence(UploadedFile $file): array
    {
        $maxSize = config('filesystems.evidence_max_size', 10240); // KB
        $allowedTypes = explode(',', config('filesystems.evidence_allowed_types', 'jpg,jpeg,png,pdf,mp4'));

        $errors = [];

        // Check file size
        if ($file->getSize() > ($maxSize * 1024)) {
            $errors[] = "File size exceeds maximum allowed size of {$maxSize}KB";
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = "File type '{$extension}' is not allowed. Allowed types: " . implode(', ', $allowedTypes);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
