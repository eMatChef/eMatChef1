<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bild-Kompression beim Upload: max. 1920 px, WebP/JPEG ~85 %.
 */
class MediaCompressionService
{
    public const MAX_EDGE_PX = 1920;
    public const JPEG_QUALITY = 85;
    public const WEBP_QUALITY = 85;
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> */
    public const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
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
     * Speichert komprimiertes Bild unter $targetPath (ohne Extension — wird gesetzt).
     *
     * @return array{path: string, filename_ext: string, mime: string, bytes: int, width: int, height: int}
     */
    public function compressAndSave(UploadedFile $file, string $targetPathWithoutExt): array
    {
        $mime = $this->assertValidUpload($file);
        $sourcePath = $file->getPathname();

        if (!$this->settingsStore->isCompressionEnabled()) {
            $ext = self::MIME_TO_EXT[$mime];
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

        if ($mime === 'image/gif' && $this->isAnimatedGif($sourcePath)) {
            $ext = 'gif';
            $targetPath = $targetPathWithoutExt . '.' . $ext;
            if (!copy($sourcePath, $targetPath)) {
                throw new \RuntimeException('Datei konnte nicht gespeichert werden');
            }
            $dimensions = $this->readDimensions($targetPath);

            return [
                'path' => $targetPath,
                'filename_ext' => $ext,
                'mime' => 'image/gif',
                'bytes' => (int) filesize($targetPath),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ];
        }

        $image = $this->loadImage($sourcePath, $mime);
        if ($image === null) {
            throw new \InvalidArgumentException('Bild konnte nicht gelesen werden');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        [$newWidth, $newHeight] = $this->scaleDimensions($width, $height);

        if ($newWidth !== $width || $newHeight !== $height) {
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized === false) {
                imagedestroy($image);
                throw new \RuntimeException('Bild konnte nicht skaliert werden');
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        [$ext, $outMime] = $this->preferredOutputFormat();
        $targetPath = $targetPathWithoutExt . '.' . $ext;

        if (!$this->saveImage($image, $targetPath, $ext)) {
            imagedestroy($image);
            throw new \RuntimeException('Bild konnte nicht gespeichert werden');
        }
        imagedestroy($image);

        return [
            'path' => $targetPath,
            'filename_ext' => $ext,
            'mime' => $outMime,
            'bytes' => (int) filesize($targetPath),
            'width' => $width,
            'height' => $height,
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
    private function scaleDimensions(int $width, int $height): array
    {
        $maxEdge = max($width, $height);
        if ($maxEdge <= self::MAX_EDGE_PX) {
            return [$width, $height];
        }

        $ratio = self::MAX_EDGE_PX / $maxEdge;

        return [
            (int) round($width * $ratio),
            (int) round($height * $ratio),
        ];
    }

    /** @return array{0: string, 1: string} ext + mime */
    private function preferredOutputFormat(): array
    {
        if (\function_exists('imagewebp')) {
            return ['webp', 'image/webp'];
        }

        return ['jpg', 'image/jpeg'];
    }

    private function loadImage(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => \function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $path, string $ext): bool
    {
        return match ($ext) {
            'webp' => \function_exists('imagewebp') && imagewebp($image, $path, self::WEBP_QUALITY),
            'jpg' => imagejpeg($image, $path, self::JPEG_QUALITY),
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
