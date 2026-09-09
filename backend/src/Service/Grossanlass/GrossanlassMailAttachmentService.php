<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Feste Anlass-PDFs (z. B. «Was ist ein PFF») für Anfrage-Mails.
 */
final class GrossanlassMailAttachmentService
{
    public const SETTING_KEY = 'grossanlass.mail.attachments';
    public const CONTEXT_ID = 'event';
    public const MAX_FILES = 8;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int, url: string}>
     */
    public function listPublic(Department $department): array
    {
        $departmentId = $department->getId() ?? '';
        $out = [];
        foreach ($this->records($department) as $row) {
            $out[] = [
                'id' => $row['id'],
                'filename' => $row['filename'],
                'original_filename' => $row['original_filename'],
                'mime' => $row['mime'],
                'bytes' => $row['bytes'],
                'url' => $this->mediaStorage->buildPublicMediaUrl(
                    MediaStorageService::CONTEXT_GROSSANLASS_MAIL_ATTACHMENT,
                    $departmentId,
                    self::CONTEXT_ID,
                    $row['filename'],
                ),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function previewNames(Department $department): array
    {
        $names = [];
        foreach ($this->records($department) as $row) {
            $names[] = $row['original_filename'] !== '' ? $row['original_filename'] : $row['filename'];
        }

        return $names;
    }

    /**
     * @return list<array{filename: string, mime: string, content: string}>
     */
    public function gmailPayloads(Department $department): array
    {
        $departmentId = $department->getId() ?? '';
        $out = [];
        foreach ($this->records($department) as $row) {
            try {
                $path = $this->mediaStorage->resolveStoredFilePath(
                    MediaStorageService::CONTEXT_GROSSANLASS_MAIL_ATTACHMENT,
                    $departmentId,
                    self::CONTEXT_ID,
                    $row['filename'],
                );
            } catch (\InvalidArgumentException) {
                continue;
            }
            $binary = @file_get_contents($path);
            if (!is_string($binary) || $binary === '') {
                continue;
            }
            $name = $row['original_filename'] !== '' ? $row['original_filename'] : $row['filename'];
            $out[] = [
                'filename' => $name,
                'mime' => $row['mime'] !== '' ? $row['mime'] : 'application/pdf',
                'content' => $binary,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int, url: string}>
     */
    public function store(Department $department, User $user, UploadedFile $file): array
    {
        $records = $this->records($department);
        if (count($records) >= self::MAX_FILES) {
            throw new \InvalidArgumentException('Höchstens ' . self::MAX_FILES . ' PDFs');
        }
        $mime = (string) $file->getMimeType();
        $ext = strtolower((string) $file->guessExtension());
        $original = strtolower((string) $file->getClientOriginalName());
        if ($mime !== 'application/pdf' && $ext !== 'pdf' && !str_ends_with($original, '.pdf')) {
            throw new \InvalidArgumentException('Nur PDF erlaubt');
        }

        $departmentId = $department->getId() ?? '';
        $stored = $this->mediaStorage->storeAttachment(
            MediaStorageService::CONTEXT_GROSSANLASS_MAIL_ATTACHMENT,
            self::CONTEXT_ID,
            $departmentId,
            $user,
            $file,
            ['pdf' => true, 'max_bytes' => 32 * 1024 * 1024],
        );
        $newId = IdGenerator::generate();
        $records[] = [
            'id' => $newId,
            'filename' => (string) $stored['filename'],
            'original_filename' => (string) ($stored['original_filename'] ?: 'dokument.pdf'),
            'mime' => 'application/pdf',
            'bytes' => (int) ($stored['bytes'] ?? 0),
        ];
        $this->saveRecords($department, $records);
        $public = $this->listPublic($department);
        foreach ($public as $row) {
            if ($row['id'] === $newId) {
                return $public;
            }
        }

        throw new \RuntimeException('Anhang konnte nicht gespeichert werden');
    }

    /**
     * @return list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int, url: string}>
     */
    public function delete(Department $department, string $fileId): array
    {
        $keep = [];
        $removed = null;
        foreach ($this->records($department) as $row) {
            if ($row['id'] === $fileId) {
                $removed = $row;
                continue;
            }
            $keep[] = $row;
        }
        if ($removed === null) {
            throw new \InvalidArgumentException('Anhang nicht gefunden');
        }
        $departmentId = $department->getId() ?? '';
        try {
            $path = $this->mediaStorage->resolveStoredFilePath(
                MediaStorageService::CONTEXT_GROSSANLASS_MAIL_ATTACHMENT,
                $departmentId,
                self::CONTEXT_ID,
                $removed['filename'],
            );
            @unlink($path);
        } catch (\InvalidArgumentException) {
        }
        $this->saveRecords($department, $keep);

        return $this->listPublic($department);
    }

    /**
     * @param mixed $decoded
     * @return list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int}>
     */
    public static function normalizeRecords(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            $filename = trim((string) ($item['filename'] ?? ''));
            if ($id === '' || $filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
                continue;
            }
            $original = trim((string) ($item['original_filename'] ?? ''));
            if ($original !== '') {
                $original = mb_scrub($original, 'UTF-8');
            }
            $out[] = [
                'id' => $id,
                'filename' => $filename,
                'original_filename' => $original !== '' ? $original : $filename,
                'mime' => trim((string) ($item['mime'] ?? '')) ?: 'application/pdf',
                'bytes' => isset($item['bytes']) && is_numeric($item['bytes']) ? (int) $item['bytes'] : 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int}>
     */
    private function records(Department $department): array
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::SETTING_KEY]);
        if (!$setting instanceof DepartmentSetting) {
            return [];
        }

        return self::normalizeRecords(json_decode($setting->getSettingValue(), true));
    }

    /**
     * @param list<array{id: string, filename: string, original_filename: string, mime: string, bytes: int}> $records
     */
    private function saveRecords(Department $department, array $records): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findOneBy(['departmentId' => $department->getId(), 'settingKey' => self::SETTING_KEY]);
        if (!$setting instanceof DepartmentSetting) {
            $setting = new DepartmentSetting();
            $setting->setId(IdGenerator::generateUnique($this->entityManager, DepartmentSetting::class));
            $setting->setDepartment($department);
            $setting->setSettingKey(self::SETTING_KEY);
            $this->entityManager->persist($setting);
        }
        $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('Anhang konnte nicht gespeichert werden');
        }
        $setting->setSettingValue($json);
        $setting->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }
}
