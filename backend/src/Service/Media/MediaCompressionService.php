<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bild-Kompression beim Upload: WebP, Kantenlänge und Qualität per Profil.
 */
class MediaCompressionService
{
    public const MAX_EDGE_PX = MediaCompressionProfile::DEFAULT_MAX_EDGE;
    public const JPEG_QUALITY = MediaCompressionProfile::DEFAULT_QUALITY;
    public const WEBP_QUALITY = MediaCompressionProfile::DEFAULT_QUALITY;
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> */
    public const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private MediaSettingsStore $settingsStore,
    ) {
    }

    public function assertValidUpload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Ungültige Upload-Datei');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Datei zu gross (max. 10 MB)');
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::MIME_TO_EXT[$mime])) {
            throw new \InvalidArgumentException('Nur JPEG, PNG, WebP oder GIF erlaubt');
        }

        return $mime;
    }

    /**
     * Bild oder PDF (Belege) — PDF ohne Kompression.
     */
    public function assertValidReceiptUpload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Ungültige Upload-Datei');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Datei zu gross (max. 10 MB)');
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::MIME_TO_EXT[$mime])) {
            throw new \InvalidArgumentException('Nur JPEG, PNG, WebP, GIF oder PDF erlaubt');
        }

        return $mime;
    }

    /**
     * @return array{path: string, filename_ext: string, mime: string, bytes: int, width: int, height: int}
     */
    public function storeReceiptOrImage(UploadedFile $file, string $targetPathWithoutExt): array
    {
        $mime = $this->assertValidReceiptUpload($file);
        if ($mime === 'application/pdf') {
            return $this->storeBinaryCopy($file->getPathname(), $targetPathWithoutExt, $mime);
        }

        return $this->compressAndSave($file, $targetPathWithoutExt, MediaCompressionProfile::default());
    }

    /**
     * @return array{path: string, filename_ext: string, mime: string, bytes: int, width: int, height: int}
     */
    private function storeBinaryCopy(string $sourcePath, string $targetPathWithoutExt, string $mime): array
    {
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $targetPath = $targetPathWithoutExt . '.' . $ext;
        if (!copy($sourcePath, $targetPath)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }

        return [
            'path' => $targetPath,
            'filename_ext' => $ext,
            'mime' => $mime,
            'bytes' => (int) filesize($targetPath),
            'width' => 0,
            'height' => 0,
        ];
    }

    /**
     * Speichert komprimiertes Bild unter $targetPath (ohne Extension — wird gesetzt).
     *
     * @return array{path: string, filename_ext: string, mime: string, bytes: int, width: int, height: int}
     */
    public function compressAndSave(
        UploadedFile $file,
        string $targetPathWithoutExt,
        ?MediaCompressionProfile $profile = null,
    ): array {
        $profile ??= MediaCompressionProfile::default();
        $mime = $this->assertValidUpload($file);
        $sourcePath = $file->getPathname();

        if (!$this->settingsStore->isCompressionEnabled() || !$this->canProcessWithGd($mime)) {
            return $this->storeCopy($sourcePath, $targetPathWithoutExt, $mime);
        }

        if ($mime === 'image/gif' && $this->isAnimatedGif($sourcePath)) {
            return $this->storeCopy($sourcePath, $targetPathWithoutExt, 'image/gif');
        }

        $image = $this->loadImage($sourcePath, $mime);
        if ($image === null) {
            throw new \InvalidArgumentException('Bild konnte nicht gelesen werden');
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        [$newWidth, $newHeight] = $this->scaleDimensions($width, $height, $profile->maxEdgePx);

        if ($newWidth !== $width || $newHeight !== $height) {
            $resized = \imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                \imagedestroy($image);
                throw new \RuntimeException('Bild konnte nicht skaliert werden');
            }
            \imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            \imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        [$ext, $outMime] = $this->preferredOutputFormat();
        $targetPath = $targetPathWithoutExt . '.' . $ext;

        if (!$this->saveImage($image, $targetPath, $ext, $profile->quality)) {
            \imagedestroy($image);
            throw new \RuntimeException('Bild konnte nicht gespeichert werden');
        }
        \imagedestroy($image);

        return [
            'path' => $targetPath,
            'filename_ext' => $ext,
            'mime' => $outMime,
            'bytes' => (int) filesize($targetPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Komprimiert eine bestehende Datei (Legacy ohne bytes-Metadaten).
     *
     * @return array{path: string, mime: string, bytes: int, width: int, height: int, filename_ext: string}|null
     */
    public function compressExistingFile(string $path, ?MediaCompressionProfile $profile = null): ?array
    {
        $profile ??= MediaCompressionProfile::default();
        if (!$this->settingsStore->isCompressionEnabled() || !is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        $mime = (string) ($info['mime'] ?? '');
        if (!isset(self::MIME_TO_EXT[$mime])) {
            return null;
        }

        if ($mime === 'image/gif' && $this->isAnimatedGif($path)) {
            $dimensions = $this->readDimensions($path);

            return [
                'path' => $path,
                'mime' => 'image/gif',
                'bytes' => (int) filesize($path),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'filename_ext' => 'gif',
            ];
        }

        $image = $this->loadImage($path, $mime);
        if ($image === null) {
            return null;
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        [$newWidth, $newHeight] = $this->scaleDimensions($width, $height, $profile->maxEdgePx);

        if ($newWidth !== $width || $newHeight !== $height) {
            $resized = \imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                \imagedestroy($image);

                return null;
            }
            \imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            \imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        [$ext, $outMime] = $this->preferredOutputFormat();
        $targetPath = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $ext;

        if (!$this->saveImage($image, $targetPath, $ext, $profile->quality)) {
            \imagedestroy($image);

            return null;
        }
        \imagedestroy($image);

        if ($targetPath !== $path && is_file($path)) {
            @unlink($path);
        }

        return [
            'path' => $targetPath,
            'mime' => $outMime,
            'bytes' => (int) filesize($targetPath),
            'width' => $width,
            'height' => $height,
            'filename_ext' => $ext,
        ];
    }

    /** @return array{width: int, height: int} */
    private function readDimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return ['width' => 0, 'height' => 0];
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /** @return array{0: int, 1: int} */
    public function scaleDimensions(int $width, int $height, int $maxEdgePx = self::MAX_EDGE_PX): array
    {
        $maxEdge = max($width, $height);
        if ($maxEdge <= $maxEdgePx) {
            return [$width, $height];
        }

        $ratio = $maxEdgePx / $maxEdge;

        return [
            (int) round($width * $ratio),
            (int) round($height * $ratio),
        ];
    }

    /** @return array{path: string, filename_ext: string, mime: string, bytes: int, width: int, height: int} */
    private function storeCopy(string $sourcePath, string $targetPathWithoutExt, string $mime): array
    {
        $ext = self::MIME_TO_EXT[$mime] ?? 'bin';
        $targetPath = $targetPathWithoutExt . '.' . $ext;
        if (!copy($sourcePath, $targetPath)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden');
        }
        $dimensions = $this->readDimensions($targetPath);

        return [
            'path' => $targetPath,
            'filename_ext' => $ext,
            'mime' => $mime,
            'bytes' => (int) filesize($targetPath),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ];
    }

    private function canProcessWithGd(string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => \function_exists('imagecreatefromjpeg') && \function_exists('imagejpeg'),
            'image/png' => \function_exists('imagecreatefrompng') && \function_exists('imagepng'),
            'image/webp' => \function_exists('imagecreatefromwebp') && \function_exists('imagewebp'),
            'image/gif' => \function_exists('imagecreatefromgif'),
            default => false,
        };
    }

    /** @return array{0: string, 1: string} ext + mime */
    private function preferredOutputFormat(): array
    {
        if (\function_exists('imagewebp')) {
            return ['webp', 'image/webp'];
        }
        if (\function_exists('imagejpeg')) {
            return ['jpg', 'image/jpeg'];
        }
        if (\function_exists('imagepng')) {
            return ['png', 'image/png'];
        }

        throw new \RuntimeException('Keine GD-Bildausgabe verfügbar');
    }

    private function loadImage(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg' => @\imagecreatefromjpeg($path) ?: null,
            'image/png' => @\imagecreatefrompng($path) ?: null,
            'image/webp' => \function_exists('imagecreatefromwebp') ? (@\imagecreatefromwebp($path) ?: null) : null,
            'image/gif' => @\imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $path, string $ext, int $quality = self::JPEG_QUALITY): bool
    {
        $quality = max(1, min(100, $quality));

        return match ($ext) {
            'webp' => \function_exists('imagewebp') && \imagewebp($image, $path, $quality),
            'jpg' => \function_exists('imagejpeg') && \imagejpeg($image, $path, $quality),
            'png' => \function_exists('imagepng') && \imagepng($image, $path),
            default => false,
        };
    }

    private function isAnimatedGif(string $path): bool
    {
        $content = @file_get_contents($path, false, null, 0, 1024 * 64);
        if ($content === false) {
            return false;
        }

        return preg_match('/\x00\x21\xF9\x04.{4}\x00(\x00|\xFF)/s', $content) === 1
            || substr_count($content, "\x21\xF9\x04") > 1;
    }
}
