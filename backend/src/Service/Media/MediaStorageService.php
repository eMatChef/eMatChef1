<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Zentraler Speicher unter var/uploads/{departmentId}/{photos|documents}/{folder}/{contextId}/.
 */
class MediaStorageService
{
    public const CONTEXT_WORKSHOP_TICKET = 'workshop_ticket';
    public const CONTEXT_ISSUE_REPORT = 'issue_report';
    public const CONTEXT_MATERIAL_ITEM = 'material_item';
    public const CONTEXT_ACCOUNTING_BOOKING = 'accounting_booking';
    public const CONTEXT_ACCOUNTING_FOLLOW_UP = 'accounting_follow_up';
    public const CONTEXT_ACTIVITY_JS_ORDER = 'activity_js_order';
    public const CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE = 'grossanlass_procurement_quote';
    public const CONTEXT_GROSSANLASS_USER_CARD = 'grossanlass_user_card';

    public const KIND_PHOTOS = 'photos';
    public const KIND_DOCUMENTS = 'documents';

    /** @var array<string, array{kind: string, folder: string}> */
    private const CONTEXT_LAYOUT = [
        self::CONTEXT_WORKSHOP_TICKET => ['kind' => self::KIND_PHOTOS, 'folder' => 'workshop'],
        self::CONTEXT_ISSUE_REPORT => ['kind' => self::KIND_PHOTOS, 'folder' => 'issues'],
        self::CONTEXT_MATERIAL_ITEM => ['kind' => self::KIND_PHOTOS, 'folder' => 'material'],
        self::CONTEXT_ACCOUNTING_BOOKING => ['kind' => self::KIND_DOCUMENTS, 'folder' => 'accounting'],
        self::CONTEXT_ACCOUNTING_FOLLOW_UP => ['kind' => self::KIND_DOCUMENTS, 'folder' => 'accounting-followup'],
        self::CONTEXT_ACTIVITY_JS_ORDER => ['kind' => self::KIND_DOCUMENTS, 'folder' => 'activity-js-order'],
        self::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE => ['kind' => self::KIND_DOCUMENTS, 'folder' => 'grossanlass-procurement-quote'],
        self::CONTEXT_GROSSANLASS_USER_CARD => ['kind' => self::KIND_DOCUMENTS, 'folder' => 'grossanlass-user-card'],
    ];

    private string $uploadsBaseDir;
    private string $legacyWorkshopSupplierDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        private MediaCompressionService $compressionService,
    ) {
        $this->uploadsBaseDir = $projectDir . '/var/uploads';
        $this->legacyWorkshopSupplierDir = $projectDir . '/var/uploads/workshop/supplier';
    }

    /**
     * @param array{
     *     url?: string,
     *     url_builder?: callable(string $filename): string,
     *     uploaded_by_supplier_company_id?: string,
     *     original_filename?: string
     * } $options
     *
     * @return array{
     *     id: string,
     *     filename: string,
     *     url: string,
     *     uploaded_at: string,
     *     uploaded_by_id: string,
     *     uploaded_by_name: string,
     *     original_filename: string,
     *     context: string,
     *     context_id: string,
     *     department_id: string,
     *     bytes: int,
     *     width: int,
     *     height: int,
     *     mime: string,
     *     uploaded_by_supplier_company_id?: string
     * }
     */
    public function store(
        string $context,
        string $contextId,
        string $departmentId,
        User $user,
        UploadedFile $file,
        array $options,
    ): array {
        $this->assertContext($context);
        $this->assertSafePathSegment($contextId);
        $this->assertSafePathSegment($departmentId);

        $filenameBase = $this->buildFilenameBase($user);
        $targetDir = $this->resolveContextDir($context, $departmentId, $contextId);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden');
        }

        $compressed = $this->compressionService->compressAndSave(
            $file,
            $targetDir . '/' . $filenameBase,
            MediaCompressionProfile::forContext($context),
        );

        $filename = $filenameBase . '.' . $compressed['filename_ext'];

        $urlBuilder = $options['url_builder'] ?? null;
        if (\is_callable($urlBuilder)) {
            $url = (string) $urlBuilder($filename);
        } else {
            $url = $this->buildPublicMediaUrl($context, $departmentId, $contextId, $filename);
        }

        $photo = [
            'id' => bin2hex(random_bytes(8)),
            'filename' => $filename,
            'url' => $url,
            'uploaded_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'uploaded_by_id' => (string) $user->getId(),
            'uploaded_by_name' => $this->displayUserName($user),
            'original_filename' => $this->sanitizeOriginalFilename(
                (string) ($options['original_filename'] ?? $file->getClientOriginalName()),
            ),
            'context' => $context,
            'context_id' => $contextId,
            'department_id' => $departmentId,
            'bytes' => $compressed['bytes'],
            'width' => $compressed['width'],
            'height' => $compressed['height'],
            'mime' => $compressed['mime'],
        ];

        if (!empty($options['uploaded_by_supplier_company_id'])) {
            $photo['uploaded_by_supplier_company_id'] = (string) $options['uploaded_by_supplier_company_id'];
        }

        return $photo;
    }

    /**
     * Beleg-Anhang (Bild komprimiert, PDF unverändert).
     *
     * @param array{url?: string, url_builder?: callable(string $filename): string} $options
     *
     * @return array<string, mixed>
     */
    public function storeAttachment(
        string $context,
        string $contextId,
        string $departmentId,
        User $user,
        UploadedFile $file,
        array $options,
    ): array {
        $this->assertContext($context);
        $this->assertSafePathSegment($contextId);
        $this->assertSafePathSegment($departmentId);

        $filenameBase = $this->buildFilenameBase($user);
        $targetDir = $this->resolveContextDir($context, $departmentId, $contextId);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden');
        }

        $stored = $this->compressionService->storeReceiptOrImage(
            $file,
            $targetDir . '/' . $filenameBase,
        );

        $filename = $filenameBase . '.' . $stored['filename_ext'];

        $urlBuilder = $options['url_builder'] ?? null;
        if (\is_callable($urlBuilder)) {
            $url = (string) $urlBuilder($filename);
        } else {
            $url = $this->buildPublicMediaUrl($context, $departmentId, $contextId, $filename);
        }

        return [
            'id' => bin2hex(random_bytes(8)),
            'filename' => $filename,
            'url' => $url,
            'uploaded_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'uploaded_by_id' => (string) $user->getId(),
            'uploaded_by_name' => $this->displayUserName($user),
            'original_filename' => $this->sanitizeOriginalFilename((string) $file->getClientOriginalName()),
            'context' => $context,
            'context_id' => $contextId,
            'department_id' => $departmentId,
            'bytes' => $stored['bytes'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'mime' => $stored['mime'],
        ];
    }

    public function resolveWorkshopTicketFilePath(
        string $departmentId,
        string $ticketId,
        string $filename,
        ?string $legacySupplierCompanyId = null,
    ): string {
        $this->assertSafePathSegment($departmentId);
        $this->assertSafePathSegment($ticketId);
        $this->assertSafeFilename($filename);

        $primaryPath = $this->resolveContextDir(self::CONTEXT_WORKSHOP_TICKET, $departmentId, $ticketId) . '/' . $filename;
        if (is_file($primaryPath)) {
            return $primaryPath;
        }

        $legacyContextFirst = $this->resolveLegacyContextFirstDir(self::CONTEXT_WORKSHOP_TICKET, $departmentId, $ticketId) . '/' . $filename;
        if (is_file($legacyContextFirst)) {
            return $legacyContextFirst;
        }

        if ($legacySupplierCompanyId !== null && $legacySupplierCompanyId !== '') {
            $legacyPath = $this->resolveLegacyWorkshopSupplierDir($legacySupplierCompanyId, $ticketId) . '/' . $filename;
            if (is_file($legacyPath)) {
                return $legacyPath;
            }
        }

        throw new \InvalidArgumentException('Datei nicht gefunden');
    }

    public function deleteStoredFile(
        string $context,
        string $departmentId,
        string $contextId,
        string $filename,
        ?string $legacySupplierCompanyId = null,
    ): void {
        $this->assertSafeFilename($filename);

        $candidates = [
            $this->resolveContextDir($context, $departmentId, $contextId) . '/' . $filename,
            $this->resolveLegacyContextFirstDir($context, $departmentId, $contextId) . '/' . $filename,
        ];
        if ($context === self::CONTEXT_WORKSHOP_TICKET && $legacySupplierCompanyId !== null && $legacySupplierCompanyId !== '') {
            $candidates[] = $this->resolveLegacyWorkshopSupplierDir($legacySupplierCompanyId, $contextId) . '/' . $filename;
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function deleteContextFolder(string $context, string $departmentId, string $contextId): void
    {
        $this->assertContext($context);
        $this->assertSafePathSegment($departmentId);
        $this->assertSafePathSegment($contextId);

        $dir = $this->resolveContextDir($context, $departmentId, $contextId);
        $this->deleteDirectoryIfExists($dir);
        $this->deleteDirectoryIfExists($this->resolveLegacyContextFirstDir($context, $departmentId, $contextId));
    }

    /**
     * @return array{files: int, bytes: int}
     */
    public function deleteDirectoryIfExists(string $dir): array
    {
        if (!is_dir($dir)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $stats = $this->measureDirectory($dir);
        $this->removeDirectoryRecursive($dir);

        return $stats;
    }

    /**
     * @return array{files: int, bytes: int}
     */
    public function deleteLegacyWorkshopSupplierFolder(string $supplierCompanyId, string $ticketId): array
    {
        $dir = $this->resolveLegacyWorkshopSupplierDir($supplierCompanyId, $ticketId);

        return $this->deleteDirectoryIfExists($dir);
    }

    /**
     * @return array{files: int, bytes: int}
     */
    public function measureDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            ++$files;
            $bytes += (int) $file->getSize();
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    public function getUploadsBaseDir(): string
    {
        return $this->uploadsBaseDir;
    }

    public function buildFilenameBase(User $user): string
    {
        $timestamp = (new \DateTimeImmutable())->format('YmdHis');
        $userPart = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $user->getId()) ?: 'user';
        $random = bin2hex(random_bytes(4));

        return sprintf('%s_%s_%s', $timestamp, $userPart, $random);
    }

    public function resolveContextDir(string $context, string $departmentId, string $contextId): string
    {
        $this->assertContext($context);
        $this->assertSafePathSegment($departmentId);
        $this->assertSafePathSegment($contextId);

        $layout = self::CONTEXT_LAYOUT[$context];

        return $this->uploadsBaseDir . '/' . $departmentId . '/' . $layout['kind'] . '/' . $layout['folder'] . '/' . $contextId;
    }

    /** Altes Layout: var/uploads/{folder}/{departmentId}/{contextId}/ */
    public function resolveLegacyContextFirstDir(string $context, string $departmentId, string $contextId): string
    {
        $this->assertContext($context);
        $this->assertSafePathSegment($departmentId);
        $this->assertSafePathSegment($contextId);

        $folder = self::CONTEXT_LAYOUT[$context]['folder'];

        return $this->uploadsBaseDir . '/' . $folder . '/' . $departmentId . '/' . $contextId;
    }

    public function resolveStoredFilePath(
        string $context,
        string $departmentId,
        string $contextId,
        string $filename,
    ): string {
        $this->assertSafeFilename($filename);

        $primary = $this->resolveContextDir($context, $departmentId, $contextId) . '/' . $filename;
        if (is_file($primary)) {
            return $primary;
        }

        $legacy = $this->resolveLegacyContextFirstDir($context, $departmentId, $contextId) . '/' . $filename;
        if (is_file($legacy)) {
            return $legacy;
        }

        throw new \InvalidArgumentException('Datei nicht gefunden');
    }

    public function buildPublicMediaUrl(
        string $context,
        string $departmentId,
        string $contextId,
        string $filename,
    ): string {
        $this->assertContext($context);
        $this->assertSafePathSegment($departmentId);
        $this->assertSafePathSegment($contextId);
        $this->assertSafeFilename($filename);

        $layout = self::CONTEXT_LAYOUT[$context];

        return sprintf(
            '/media/%s/%s/%s/%s/%s',
            rawurlencode($departmentId),
            rawurlencode($layout['kind']),
            rawurlencode($layout['folder']),
            rawurlencode($contextId),
            rawurlencode($filename),
        );
    }

    public function contextFromKindAndFolder(string $kind, string $folder): string
    {
        foreach (self::CONTEXT_LAYOUT as $context => $layout) {
            if ($layout['kind'] === $kind && $layout['folder'] === $folder) {
                return $context;
            }
        }

        throw new \InvalidArgumentException('Ungültiger Medien-Ordner');
    }

    public function resolveLegacyWorkshopSupplierDir(string $supplierCompanyId, string $ticketId): string
    {
        $this->assertSafePathSegment($supplierCompanyId);
        $this->assertSafePathSegment($ticketId);

        return $this->legacyWorkshopSupplierDir . '/' . $supplierCompanyId . '/' . $ticketId;
    }

    public function buildSupplierPhotoUrl(string $supplierCompanyId, string $ticketId, string $filename): string
    {
        return sprintf(
            '/api/supplier-companies/%s/repairs/%s/photos/%s',
            rawurlencode($supplierCompanyId),
            rawurlencode($ticketId),
            rawurlencode($filename),
        );
    }

    public function buildWorkshopPhotoUrl(string $ticketId, string $filename): string
    {
        return sprintf(
            '/api/workshop/tickets/%s/photos/%s',
            rawurlencode($ticketId),
            rawurlencode($filename),
        );
    }

    private function assertContext(string $context): void
    {
        if (!isset(self::CONTEXT_LAYOUT[$context])) {
            throw new \InvalidArgumentException('Ungültiger Medien-Kontext');
        }
    }

    public function assertSafePathSegment(string $value): void
    {
        if ($value === '' || str_contains($value, '/') || str_contains($value, '\\') || str_contains($value, '..')) {
            throw new \InvalidArgumentException('Ungültiger Pfad');
        }
    }

    public function assertSafeFilename(string $filename): void
    {
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            throw new \InvalidArgumentException('Ungültiger Dateiname');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new \InvalidArgumentException('Ungültiger Dateiname');
        }
    }

    public function sanitizeOriginalFilename(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $base) ?? 'upload';

        return mb_substr(trim($base) ?: 'upload', 0, 200);
    }

    private function displayUserName(User $user): string
    {
        $profile = $user->getProfile();
        if ($profile && trim($profile->getDisplayName()) !== '') {
            return trim($profile->getDisplayName());
        }

        return trim($user->getEmail() ?? 'Unbekannt');
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
