<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Private-disk POD object storage (D7).
 * Persists relative object keys only — never expose filesystem roots to Driver DTOs.
 */
final class DriverPodStorage
{
    public const DISK = 'local';

    public const MAX_BYTES = 5_242_880; // 5 MiB

    /**
     * @var list<string>
     */
    public const ALLOWED_PHOTO_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @var list<string>
     */
    public const ALLOWED_SIGNATURE_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/json',
        'text/plain',
    ];

    public function storePhoto(string $shipmentId, UploadedFile $file): string
    {
        return $this->store($shipmentId, 'photo', $file, self::ALLOWED_PHOTO_MIME);
    }

    public function storeSignature(string $shipmentId, UploadedFile $file): string
    {
        return $this->store($shipmentId, 'signature', $file, self::ALLOWED_SIGNATURE_MIME);
    }

    /**
     * @param  list<string>  $allowedMime
     */
    private function store(string $shipmentId, string $kind, UploadedFile $file, array $allowedMime): string
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Upload is invalid.');
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Upload exceeds maximum size.');
        }

        $mime = (string) ($file->getMimeType() ?? '');
        if (! in_array($mime, $allowedMime, true)) {
            throw new InvalidArgumentException('Upload MIME type is not allowed.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/json' => 'json',
            'text/plain' => 'txt',
            default => 'bin',
        };

        $safeShipment = Str::of($shipmentId)->replace(['/', '\\', '..'], '')->toString();
        $key = 'driver-pod/'.$safeShipment.'/'.$kind.'.'.$ext;

        $stored = Storage::disk(self::DISK)->putFileAs(
            'driver-pod/'.$safeShipment,
            $file,
            $kind.'.'.$ext
        );

        if ($stored === false || $stored === null || $stored === '') {
            throw new InvalidArgumentException('Failed to store upload.');
        }

        // Normalize to forward-slash object key (never absolute path).
        return str_replace('\\', '/', (string) $stored);
    }

    /**
     * True when the key is a relative driver-pod object key (never a filesystem path).
     */
    public function isSafeObjectKey(string $key): bool
    {
        $normalized = str_replace('\\', '/', trim($key));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }
        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:/', $normalized) === 1) {
            return false;
        }

        return str_starts_with($normalized, 'driver-pod/');
    }

    public function exists(string $key): bool
    {
        if (! $this->isSafeObjectKey($key)) {
            return false;
        }

        return Storage::disk(self::DISK)->exists($key);
    }

    public function mimeType(string $key): string
    {
        if (! $this->isSafeObjectKey($key)) {
            return 'application/octet-stream';
        }

        $mime = Storage::disk(self::DISK)->mimeType($key);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }

        $ext = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    public function downloadName(string $key): string
    {
        $ext = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));
        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'bin';

        return 'proof-of-delivery.'.$safeExt;
    }
}
