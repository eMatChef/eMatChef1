<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use App\Entity\Department;
use App\Entity\PrintLayout;
use App\Entity\PrintMedia;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PrintLayoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PrintCatalogService $catalog,
        private readonly PrintLayoutStorageService $storage,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public static function defaultFields(): array
    {
        return [
            ['id' => 'qr', 'type' => 'qr', 'key' => 'public_url', 'x' => 8, 'y' => 10, 'w' => 38, 'h' => 70],
            ['id' => 'title', 'type' => 'text', 'key' => 'label', 'x' => 50, 'y' => 12, 'w' => 46, 'h' => 40],
            ['id' => 'code', 'type' => 'text', 'key' => 'public_code', 'x' => 50, 'y' => 56, 'w' => 46, 'h' => 28],
        ];
    }

    /** @return list<PrintLayout> */
    public function visibleLayouts(User $user): array
    {
        $items = $this->entityManager->getRepository(PrintLayout::class)->findBy([], ['name' => 'ASC']);
        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof PrintLayout) {
                continue;
            }
            if ($this->canSee($user, $item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return list<PrintLayout> */
    public function publishedLayoutsForUse(User $user): array
    {
        return array_values(array_filter(
            $this->visibleLayouts($user),
            static fn (PrintLayout $l) => $l->getStatus() === PrintLayout::STATUS_PUBLISHED,
        ));
    }

    public function get(string $id): ?PrintLayout
    {
        $item = $this->entityManager->getRepository(PrintLayout::class)->find($id);

        return $item instanceof PrintLayout ? $item : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(User $user, Department $department, array $data): PrintLayout
    {
        $name = trim((string) ($data['name'] ?? ''));
        $mediaId = trim((string) ($data['media_id'] ?? ''));
        if ($name === '' || $mediaId === '') {
            throw new \InvalidArgumentException('name und media_id sind erforderlich');
        }
        $media = $this->entityManager->getRepository(PrintMedia::class)->find($mediaId);
        if (!$media instanceof PrintMedia || $media->getStatus() !== PrintMedia::STATUS_PUBLISHED) {
            throw new \InvalidArgumentException('Medium nicht gefunden oder nicht freigegeben');
        }

        $layout = new PrintLayout();
        $layout->setId(IdGenerator::generateUnique($this->entityManager, PrintLayout::class));
        $layout->setName($name);
        $layout->setMedia($media);
        $layout->setDepartmentId($department->getId());
        $layout->setOrganisationId($department->getOrganisationId());
        $layout->setFields($this->normalizeFields($data['fields'] ?? self::defaultFields()));
        $layout->setIncludeTemplateOnPrint((bool) ($data['include_template_on_print'] ?? false));
        $layout->setStatus(PrintLayout::STATUS_PUBLISHED);
        $layout->setScope(PrintLayout::SCOPE_ORGANISATION);
        $layout->setGlobalRequested($this->wantsGlobal($data));
        $layout->setCreatedByUserId($user->getId());
        $this->entityManager->persist($layout);
        $this->entityManager->flush();

        return $layout;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(PrintLayout $layout, array $data): PrintLayout
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('name darf nicht leer sein');
            }
            $layout->setName($name);
        }
        if (isset($data['media_id'])) {
            $media = $this->entityManager->getRepository(PrintMedia::class)->find(trim((string) $data['media_id']));
            if (!$media instanceof PrintMedia) {
                throw new \InvalidArgumentException('Medium nicht gefunden');
            }
            $layout->setMedia($media);
        }
        if (array_key_exists('fields', $data) && \is_array($data['fields'])) {
            $layout->setFields($this->normalizeFields($data['fields']));
        }
        if (array_key_exists('include_template_on_print', $data)) {
            $layout->setIncludeTemplateOnPrint((bool) $data['include_template_on_print']);
        }
        $layout->touch();
        $this->entityManager->flush();

        return $layout;
    }

    public function delete(PrintLayout $layout): void
    {
        $sha = $layout->getTemplateSha256();
        $this->entityManager->remove($layout);
        $this->entityManager->flush();
        if ($sha !== null && $sha !== '') {
            $left = $this->countByTemplateSha($sha);
            $this->storage->deleteIfUnreferenced($sha, $left);
        }
    }

    public function attachTemplate(PrintLayout $layout, UploadedFile $file): PrintLayout
    {
        $sha = $this->storage->storeTemplate($file);
        $layout->setTemplateSha256($sha);
        $layout->setTemplateFilename($sha . '.pdf');
        $layout->touch();
        $this->entityManager->flush();

        return $layout;
    }

    /** @return list<PrintLayout> */
    public function layoutsWithTemplateSha(string $sha256, ?string $exceptLayoutId = null): array
    {
        $items = $this->entityManager->getRepository(PrintLayout::class)->findBy(
            ['templateSha256' => $sha256],
            ['name' => 'ASC'],
        );
        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof PrintLayout) {
                continue;
            }
            if ($exceptLayoutId !== null && $item->getId() === $exceptLayoutId) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    public function countByTemplateSha(string $sha256): int
    {
        return (int) $this->entityManager->getRepository(PrintLayout::class)->count(['templateSha256' => $sha256]);
    }

    /**
     * MW bietet die Vorlage allen Materialwarten an, oder ein anderer MW gibt sie frei (ohne Org/Suborg/SA).
     */
    public function shareWithMaterialwarts(User $user, PrintLayout $layout): PrintLayout
    {
        if (!$this->catalog->isPrintManager($user)) {
            throw new \RuntimeException('Keine Berechtigung');
        }
        if ($layout->getStatus() !== PrintLayout::STATUS_PUBLISHED) {
            throw new \InvalidArgumentException('Nur freigegebene Layouts können geteilt werden');
        }
        if ($layout->getScope() === PrintLayout::SCOPE_GLOBAL) {
            return $layout;
        }
        $owns = $layout->getCreatedByUserId() === $user->getId();
        if ($owns && !$layout->isGlobalRequested()) {
            $layout->setGlobalRequested(true);
            $layout->touch();
            $this->entityManager->flush();

            return $layout;
        }
        if ($owns) {
            throw new \InvalidArgumentException('Ein anderer Materialwart muss die Freigabe bestätigen');
        }
        $layout->setStatus(PrintLayout::STATUS_PUBLISHED);
        $layout->setScope(PrintLayout::SCOPE_GLOBAL);
        $layout->setOrganisationId(null);
        $layout->setGlobalRequested(false);
        $layout->setReviewedByUserId($user->getId());
        $layout->setReviewedAt(new \DateTime());
        $layout->touch();
        $this->entityManager->flush();

        return $layout;
    }

    public function copyToDepartment(User $user, Department $department, PrintLayout $source): PrintLayout
    {
        if (!$this->catalog->canManageDepartment($user, $department->getId())) {
            throw new \RuntimeException('Keine Berechtigung');
        }
        if (!$this->canSee($user, $source)) {
            throw new \RuntimeException('Layout nicht sichtbar');
        }
        $copy = new PrintLayout();
        $copy->setId(IdGenerator::generateUnique($this->entityManager, PrintLayout::class));
        $copy->setName($source->getName());
        $copy->setMedia($source->getMedia());
        $copy->setDepartmentId($department->getId());
        $copy->setOrganisationId($department->getOrganisationId());
        $copy->setFields($source->getFields());
        $copy->setTemplateFilename($source->getTemplateFilename());
        $copy->setTemplateSha256($source->getTemplateSha256());
        $copy->setIncludeTemplateOnPrint($source->includeTemplateOnPrint());
        $copy->setStatus(PrintLayout::STATUS_PUBLISHED);
        $copy->setScope(PrintLayout::SCOPE_ORGANISATION);
        $copy->setGlobalRequested(false);
        $copy->setCreatedByUserId($user->getId());
        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        return $copy;
    }

    public function requestGlobal(User $user, Department $department, PrintLayout $layout): PrintLayout
    {
        if (!$this->catalog->canManageDepartment($user, $department->getId())) {
            throw new \RuntimeException('Keine Berechtigung');
        }

        return $this->shareWithMaterialwarts($user, $layout);
    }

    public function review(User $reviewer, PrintLayout $layout, string $action): PrintLayout
    {
        $roles = $reviewer->getRoles();
        if (!PrintCatalogVisibility::isReviewer($roles)) {
            throw new \RuntimeException('Keine Berechtigung zur Prüfung');
        }
        if (!PrintCatalogVisibility::canReviewItem(
            $layout->getOrganisationId(),
            $this->catalog->organisationIdsForUser($reviewer),
            $this->catalog->canSeeAllOrganisations($reviewer),
        )) {
            throw new \RuntimeException('Keine Berechtigung für diese Organisation');
        }
        $action = strtolower(trim($action));
        if (!\in_array($action, ['approve', 'reject', 'promote_global'], true)) {
            throw new \InvalidArgumentException('action muss approve, reject oder promote_global sein');
        }
        if ($action === 'promote_global' && !PrintCatalogVisibility::isSuperAdmin($roles)) {
            throw new \RuntimeException('Nur Superadmin kann ohne Antrag global hochstufen');
        }
        $layout->setReviewedByUserId($reviewer->getId());
        $layout->setReviewedAt(new \DateTime());
        $layout->touch();
        if ($action === 'reject') {
            if ($layout->getStatus() === PrintLayout::STATUS_PUBLISHED && $layout->isGlobalRequested()) {
                $layout->setGlobalRequested(false);
            } else {
                $layout->setStatus(PrintLayout::STATUS_REJECTED);
                $layout->setGlobalRequested(false);
            }
        } else {
            $layout->setStatus(PrintLayout::STATUS_PUBLISHED);
            $layout->setScope(PrintLayout::SCOPE_GLOBAL);
            $layout->setOrganisationId(null);
            $layout->setGlobalRequested(false);
        }
        $this->entityManager->flush();

        return $layout;
    }

    public function assertCanManage(User $user, Department $department, PrintLayout $layout): void
    {
        if (!$this->catalog->canManageDepartment($user, $department->getId())) {
            throw new \RuntimeException('Keine Berechtigung');
        }
        if ($layout->getScope() === PrintLayout::SCOPE_GLOBAL) {
            if (!PrintCatalogVisibility::isSuperAdmin($user->getRoles())) {
                throw new \RuntimeException('Globale Layouts darf nur Superadmin ändern');
            }

            return;
        }
        if ($layout->getOrganisationId() !== $department->getOrganisationId()) {
            throw new \RuntimeException('Layout gehört nicht zu dieser Organisation');
        }
    }

    public function canSee(User $user, PrintLayout $layout): bool
    {
        if (
            $layout->getStatus() === PrintLayout::STATUS_PUBLISHED
            && $this->catalog->isPrintManager($user)
        ) {
            return true;
        }

        return PrintCatalogVisibility::canSeeItem(
            $layout->getStatus(),
            $layout->getScope(),
            $layout->getOrganisationId(),
            $layout->getCreatedByUserId(),
            $user->getId(),
            $this->catalog->organisationIdsForUser($user),
            PrintCatalogVisibility::isSuperAdmin($user->getRoles()) || $this->catalog->canSeeAllOrganisations($user),
        );
    }

    /** @return array<string, mixed> */
    public function serialize(PrintLayout $layout, ?float $cutLengthMm = null): array
    {
        $media = $layout->getMedia();
        $spec = PrintSheetGeometry::specFromMedia($media, $cutLengthMm);

        return [
            'id' => $layout->getId(),
            'name' => $layout->getName(),
            'media_id' => $layout->getMediaId(),
            'department_id' => $layout->getDepartmentId(),
            'organisation_id' => $layout->getOrganisationId(),
            'fields' => $layout->getFields(),
            'template_filename' => $layout->getTemplateFilename(),
            'template_sha256' => $layout->getTemplateSha256(),
            'has_template' => $layout->getTemplateSha256() !== null || $layout->getTemplateFilename() !== null,
            'include_template_on_print' => $layout->includeTemplateOnPrint(),
            'status' => $layout->getStatus(),
            'scope' => $layout->getScope(),
            'global_requested' => $layout->isGlobalRequested(),
            'created_by_user_id' => $layout->getCreatedByUserId(),
            'reviewed_at' => $layout->getReviewedAt()?->format('c'),
            'media' => $this->catalog->serializeMedia($media),
            'sheet' => $spec,
            'cells' => PrintSheetGeometry::cells($spec),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeDuplicate(PrintLayout $layout): array
    {
        return [
            'id' => $layout->getId(),
            'name' => $layout->getName(),
            'media_name' => $layout->getMedia()->getName(),
            'scope' => $layout->getScope(),
            'global_requested' => $layout->isGlobalRequested(),
            'department_id' => $layout->getDepartmentId(),
            'created_by_user_id' => $layout->getCreatedByUserId(),
            'has_template' => $layout->getTemplateSha256() !== null || $layout->getTemplateFilename() !== null,
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeFields(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return self::defaultFields();
        }
        if ($raw === []) {
            return self::defaultFields();
        }
        $out = [];
        foreach ($raw as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? 'text');
            if (!\in_array($type, ['qr', 'text'], true)) {
                $type = 'text';
            }
            $key = (string) ($item['key'] ?? 'label');
            if (!\in_array($key, ['label', 'public_url', 'public_code'], true)) {
                $key = 'label';
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                $id = $type . '_' . (count($out) + 1);
            }
            $out[] = [
                'id' => substr($id, 0, 40),
                'type' => $type,
                'key' => $key,
                'x' => $this->clampPct($item['x'] ?? 0),
                'y' => $this->clampPct($item['y'] ?? 0),
                'w' => $this->clampPct($item['w'] ?? 20, 4, 100),
                'h' => $this->clampPct($item['h'] ?? 20, 4, 100),
            ];
        }

        return $out !== [] ? $out : self::defaultFields();
    }

    private function clampPct(mixed $value, float $min = 0, float $max = 100): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return max($min, min($max, $n));
    }

    /** @param array<string, mixed> $data */
    private function wantsGlobal(array $data): bool
    {
        $raw = $data['request_global'] ?? false;

        return $raw === true || $raw === 1 || $raw === '1' || $raw === 'true';
    }
}
