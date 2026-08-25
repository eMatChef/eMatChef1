<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Hersteller-PDFs unter var/uploads/print-layouts/{layoutId}/ — bleibt bei Global-Annahme am selben Ort.
 */
final class PrintLayoutStorageService
{
    private string $baseDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->baseDir = $projectDir . '/var/uploads/print-layouts';
    }

    public function directory(string $layoutId): string
    {
        return $this->baseDir . '/' . $layoutId;
    }

    public function storeTemplate(string $layoutId, UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if ($mime !== 'application/pdf' && !str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('Nur PDF-Vorlagen sind erlaubt');
        }
        $dir = $this->directory($layoutId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Vorlagen-Ordner konnte nicht erstellt werden');
        }
        $filename = 'template.pdf';
        $file->move($dir, $filename);

        return $filename;
    }

    public function resolvePath(string $layoutId, string $filename): string
    {
        if ($filename !== 'template.pdf') {
            throw new \InvalidArgumentException('Ungültiger Dateiname');
        }
        $path = $this->directory($layoutId) . '/' . $filename;
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Vorlage nicht gefunden');
        }

        return $path;
    }

    public function deleteTemplate(string $layoutId): void
    {
        $path = $this->directory($layoutId) . '/template.pdf';
        if (is_file($path)) {
            unlink($path);
        }
    }
}
