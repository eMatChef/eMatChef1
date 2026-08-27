<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Hersteller-PDFs zentral unter var/uploads/print-templates/{sha256}.pdf.
 * Mehrere Layouts teilen dieselbe Datei; Department speichert nur die Feldpositionen.
 */
final class PrintLayoutStorageService
{
    private string $legacyDir;

    private string $centralDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->legacyDir = $projectDir . '/var/uploads/print-layouts';
        $this->centralDir = $projectDir . '/var/uploads/print-templates';
    }

    public function directory(string $layoutId): string
    {
        return $this->legacyDir . '/' . $layoutId;
    }

    public function centralDirectory(): string
    {
        return $this->centralDir;
    }

    public function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (!\is_string($hash) || $hash === '') {
            throw new \RuntimeException('PDF-Hash konnte nicht berechnet werden');
        }

        return $hash;
    }

    public function centralPath(string $sha256): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new \InvalidArgumentException('Ungültiger Vorlagen-Hash');
        }

        return $this->centralDir . '/' . $sha256 . '.pdf';
    }

    /**
     * Speichert die PDF einmalig (content-addressed) und gibt den SHA-256 zurück.
     */
    public function storeTemplate(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if ($mime !== 'application/pdf' && !str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('Nur PDF-Vorlagen sind erlaubt');
        }
        $tmp = $file->getPathname();
        if ($tmp === '' || !is_file($tmp)) {
            throw new \InvalidArgumentException('PDF-Datei fehlt');
        }
        $sha = $this->hashFile($tmp);
        $dest = $this->centralPath($sha);
        if (is_file($dest)) {
            @unlink($tmp);

            return $sha;
        }
        if (!is_dir($this->centralDir) && !mkdir($this->centralDir, 0775, true) && !is_dir($this->centralDir)) {
            throw new \RuntimeException('Vorlagen-Ordner konnte nicht erstellt werden');
        }
        $file->move($this->centralDir, $sha . '.pdf');

        return $sha;
    }

    public function resolvePath(?string $layoutId, ?string $sha256, ?string $filename): string
    {
        if ($sha256 !== null && $sha256 !== '') {
            $path = $this->centralPath($sha256);
            if (is_file($path)) {
                return $path;
            }
        }
        if ($layoutId !== null && $filename === 'template.pdf') {
            $legacy = $this->directory($layoutId) . '/template.pdf';
            if (is_file($legacy)) {
                return $legacy;
            }
        }
        throw new \InvalidArgumentException('Vorlage nicht gefunden');
    }

    public function deleteIfUnreferenced(string $sha256, int $remainingRefs): void
    {
        if ($remainingRefs > 0) {
            return;
        }
        $path = $this->centralPath($sha256);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
