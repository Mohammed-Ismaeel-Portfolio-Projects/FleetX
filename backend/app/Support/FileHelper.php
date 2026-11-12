<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * File Helper
 * 
 * Provides file storage and manipulation utilities.
 */
class FileHelper
{
    /**
     * Upload file to storage.
     */
    public static function upload(\Illuminate\Http\UploadedFile $file, string $path): string
    {
        return $file->store($path, 'public');
    }

    /**
     * Get file URL.
     */
    public static function url(string $path): string
    {
        return Storage::url($path);
    }

    /**
     * Delete file from storage.
     */
    public static function delete(string $path): bool
    {
        return Storage::delete($path);
    }

    /**
     * Check if file exists.
     */
    public static function exists(string $path): bool
    {
        return Storage::exists($path);
    }

    /**
     * Get file size in bytes.
     */
    public static function size(string $path): int
    {
        return Storage::size($path);
    }

    /**
     * Generate unique filename.
     */
    public static function generateFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        return $basename . '_' . uniqid() . '.' . $extension;
    }
}
