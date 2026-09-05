<?php

namespace App\Services;

class ImageUrlService
{
    /**
     * Format and normalize image paths to prevent duplicate /storage/storage/
     * and handle absolute/relative URLs consistently.
     */
    public static function format(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = trim($path);

        // Return external URLs or base64 data strings as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        // Clean leading slashes
        $cleanPath = ltrim($path, '/');

        // Normalize any duplicate storage prefixes e.g. "storage/storage/..." -> "..."
        while (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        if (empty($cleanPath)) {
            return null;
        }

        return '/storage/' . ltrim($cleanPath, '/');
    }
}
