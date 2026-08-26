<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use App\Entity\Department;
use App\Entity\DepartmentPrintPreset;
use App\Entity\Membership;
use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class PrintCatalogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    /** @return list<string> */
    public function organisationIdsForUser(User $user): array
    {
        $accessible = $this->adminCapabilities->getAccessibleOrganisationIds($user);
        if ($accessible === null) {
            return [];
        }

        return $accessible;
    }

    public function canSeeAllOrganisations(User $user): bool
    {
        return $this->adminCapabilities->getAccessibleOrganisationIds($user) === null;
    }

    public function findDepartment(string $departmentId): ?Department
    {
        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);

        return $dept instanceof Department ? $dept : null;
    }

    public function membership(User $user, string $departmentId): ?Membership
    {
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        return $membership instanceof Membership ? $membership : null;
    }

    public function canManageDepartment(User $user, string $departmentId): bool
    {
        if (PrintCatalogVisibility::isReviewer($user->getRoles())) {
            return true;
        }
        $membership = $this->membership($user, $departmentId);

        return $membership !== null && PrintCatalogVisibility::isManagerRole($membership->getRole());
    }

    public function isPrintManager(User $user): bool
    {
        if (PrintCatalogVisibility::isReviewer($user->getRoles())) {
            return true;
        }
        $memberships = $this->entityManager->getRepository(Membership::class)->findBy([
            'userId' => $user->getId(),
        ]);
        foreach ($memberships as $membership) {
            if ($membership instanceof Membership && PrintCatalogVisibility::isManagerRole($membership->getRole())) {
                return true;
            }
        }

        return false;
    }

    public function isDepartmentMember(User $user, string $departmentId): bool
    {
        if (PrintCatalogVisibility::isReviewer($user->getRoles())) {
            return true;
        }

        return $this->membership($user, $departmentId) !== null;
    }

    /**
     * @return list<PrintDeviceModel>
     */
    public function visibleModels(User $user): array
    {
        $items = $this->entityManager->getRepository(PrintDeviceModel::class)->findBy([], ['brand' => 'ASC', 'name' => 'ASC']);
        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof PrintDeviceModel) {
                continue;
            }
            if ($this->canSeeCatalogItem(
                $user,
                $item->getStatus(),
                $item->getScope(),
                $item->getOrganisationId(),
                $item->getCreatedByUserId(),
            )) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<PrintMedia>
     */
    public function visibleMedia(User $user): array
    {
        $items = $this->entityManager->getRepository(PrintMedia::class)->findBy([], ['brand' => 'ASC', 'sku' => 'ASC']);
        $out = [];
        foreach ($items as $item) {
            if (!$item instanceof PrintMedia) {
                continue;
            }
            if ($this->canSeeCatalogItem(
                $user,
                $item->getStatus(),
                $item->getScope(),
                $item->getOrganisationId(),
                $item->getCreatedByUserId(),
            )) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return list<PrintDeviceModel> */
    public function publishedModelsForPresets(User $user): array
    {
        return array_values(array_filter(
            $this->visibleModels($user),
            static fn (PrintDeviceModel $m) => $m->getStatus() === PrintDeviceModel::STATUS_PUBLISHED,
        ));
    }

    /** @return list<PrintMedia> */
    public function publishedMediaForPresets(User $user): array
    {
        return array_values(array_filter(
            $this->visibleMedia($user),
            static fn (PrintMedia $m) => $m->getStatus() === PrintMedia::STATUS_PUBLISHED,
        ));
    }

    public function uniqueCatalogKey(string $base, string $entityClass): string
    {
        $key = $base;
        $n = 2;
        while ($this->entityManager->getRepository($entityClass)->findOneBy(['catalogKey' => $key])) {
            $suffix = '_' . $n;
            $key = substr($base, 0, 64 - strlen($suffix)) . $suffix;
            ++$n;
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function proposeOrCreateModel(User $user, ?Department $department, array $data, bool $asGlobal): PrintDeviceModel
    {
        $family = trim((string) ($data['family'] ?? ''));
        $brand = trim((string) ($data['brand'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if (!\in_array($family, PrintDeviceModel::FAMILIES, true) || $brand === '' || $name === '') {
            throw new \InvalidArgumentException('family, brand und name sind erforderlich');
        }
        $keys = $data['compatible_media_keys'] ?? [];
        if (!\is_array($keys)) {
            $keys = [];
        }
        $keys = array_values(array_filter(array_map(static fn ($k) => trim((string) $k), $keys)));

        $model = new PrintDeviceModel();
        $model->setId(IdGenerator::generateUnique($this->entityManager, PrintDeviceModel::class));
        $model->setCatalogKey($this->uniqueCatalogKey(PrintCatalogVisibility::slugKey($brand, $name), PrintDeviceModel::class));
        $model->setFamily($family);
        $model->setBrand($brand);
        $model->setName($name);
        $model->setCompatibleMediaKeys($keys);
        $model->setCreatedByUserId($user->getId());

        if ($asGlobal) {
            $model->setStatus(PrintDeviceModel::STATUS_PUBLISHED);
            $model->setScope(PrintDeviceModel::SCOPE_GLOBAL);
            $model->setOrganisationId(null);
            $model->setGlobalRequested(false);
        } else {
            if (!$department instanceof Department) {
                throw new \InvalidArgumentException('Department erforderlich');
            }
            $model->setStatus(PrintDeviceModel::STATUS_PUBLISHED);
            $model->setScope(PrintDeviceModel::SCOPE_ORGANISATION);
            $model->setOrganisationId($department->getOrganisationId());
            $model->setGlobalRequested($this->wantsGlobalReview($data));
        }

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function proposeOrCreateMedia(User $user, ?Department $department, array $data, bool $asGlobal): PrintMedia
    {
        $family = trim((string) ($data['family'] ?? ''));
        $brand = trim((string) ($data['brand'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if (!\in_array($family, PrintDeviceModel::FAMILIES, true) || $brand === '' || $sku === '' || $name === '') {
            throw new \InvalidArgumentException('family, brand, sku und name sind erforderlich');
        }
        $width = $this->parseMm($data['width_mm'] ?? null);
        if ($width === null || $width <= 0) {
            throw new \InvalidArgumentException('width_mm muss grösser 0 sein');
        }
        $continuous = (bool) ($data['is_continuous'] ?? false);
        $height = $this->parseMm($data['height_mm'] ?? null);
        if (!$continuous && ($height === null || $height <= 0)) {
            throw new \InvalidArgumentException('height_mm ist für Stanzetiketten Pflicht');
        }

        $media = new PrintMedia();
        $media->setId(IdGenerator::generateUnique($this->entityManager, PrintMedia::class));
        $media->setCatalogKey($this->uniqueCatalogKey(PrintCatalogVisibility::slugKey($brand, $sku), PrintMedia::class));
        $media->setFamily($family);
        $media->setBrand($brand);
        $media->setSku($sku);
        $media->setName($name);
        $media->setWidthMm(number_format($width, 2, '.', ''));
        $media->setHeightMm($height !== null ? number_format($height, 2, '.', '') : null);
        $media->setCols(max(1, (int) ($data['cols'] ?? 1)));
        $media->setRows(max(1, (int) ($data['rows'] ?? 1)));
        $media->setIsContinuous($continuous);
        $cut = isset($data['default_cut_length_mm']) ? (int) $data['default_cut_length_mm'] : null;
        $media->setDefaultCutLengthMm($cut !== null && $cut > 0 ? $cut : null);
        $media->setCreatedByUserId($user->getId());

        if ($asGlobal) {
            $media->setStatus(PrintMedia::STATUS_PUBLISHED);
            $media->setScope(PrintMedia::SCOPE_GLOBAL);
            $media->setOrganisationId(null);
            $media->setGlobalRequested(false);
        } else {
            if (!$department instanceof Department) {
                throw new \InvalidArgumentException('Department erforderlich');
            }
            $media->setStatus(PrintMedia::STATUS_PUBLISHED);
            $media->setScope(PrintMedia::SCOPE_ORGANISATION);
            $media->setOrganisationId($department->getOrganisationId());
            $media->setGlobalRequested($this->wantsGlobalReview($data));
        }

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateModel(PrintDeviceModel $model, array $data): PrintDeviceModel
    {
        if (isset($data['family'])) {
            $family = trim((string) $data['family']);
            if (!\in_array($family, PrintDeviceModel::FAMILIES, true)) {
                throw new \InvalidArgumentException('Ungültige family');
            }
            $model->setFamily($family);
        }
        if (isset($data['brand'])) {
            $model->setBrand(trim((string) $data['brand']));
        }
        if (isset($data['name'])) {
            $model->setName(trim((string) $data['name']));
        }
        if (array_key_exists('compatible_media_keys', $data) && \is_array($data['compatible_media_keys'])) {
            $keys = array_values(array_filter(array_map(static fn ($k) => trim((string) $k), $data['compatible_media_keys'])));
            $model->setCompatibleMediaKeys($keys);
        }
        $model->touch();
        $this->entityManager->flush();

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMedia(PrintMedia $media, array $data): PrintMedia
    {
        if (isset($data['family'])) {
            $family = trim((string) $data['family']);
            if (!\in_array($family, PrintDeviceModel::FAMILIES, true)) {
                throw new \InvalidArgumentException('Ungültige family');
            }
            $media->setFamily($family);
        }
        if (isset($data['brand'])) {
            $media->setBrand(trim((string) $data['brand']));
        }
        if (isset($data['sku'])) {
            $media->setSku(trim((string) $data['sku']));
        }
        if (isset($data['name'])) {
            $media->setName(trim((string) $data['name']));
        }
        if (array_key_exists('width_mm', $data)) {
            $width = $this->parseMm($data['width_mm']);
            if ($width === null || $width <= 0) {
                throw new \InvalidArgumentException('width_mm muss grösser 0 sein');
            }
            $media->setWidthMm(number_format($width, 2, '.', ''));
        }
        if (array_key_exists('height_mm', $data)) {
            $height = $this->parseMm($data['height_mm']);
            $media->setHeightMm($height !== null ? number_format($height, 2, '.', '') : null);
        }
        if (isset($data['cols'])) {
            $media->setCols(max(1, (int) $data['cols']));
        }
        if (isset($data['rows'])) {
            $media->setRows(max(1, (int) $data['rows']));
        }
        if (array_key_exists('is_continuous', $data)) {
            $media->setIsContinuous((bool) $data['is_continuous']);
        }
        if (array_key_exists('default_cut_length_mm', $data)) {
            $cut = $data['default_cut_length_mm'] === null || $data['default_cut_length_mm'] === ''
                ? null
                : (int) $data['default_cut_length_mm'];
            $media->setDefaultCutLengthMm($cut !== null && $cut > 0 ? $cut : null);
        }
        if (isset($data['shape'])) {
            $shape = trim((string) $data['shape']);
            $media->setShape($shape === 'round' ? 'round' : 'rect');
        }
        foreach (['sheet_width_mm' => 'setSheetWidthMm', 'sheet_height_mm' => 'setSheetHeightMm', 'margin_top_mm' => 'setMarginTopMm', 'margin_left_mm' => 'setMarginLeftMm', 'gap_x_mm' => 'setGapXMm', 'gap_y_mm' => 'setGapYMm'] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $parsed = $this->parseMm($data[$key]);
                $media->{$setter}($parsed !== null ? number_format($parsed, 2, '.', '') : null);
            }
        }
        $media->touch();
        $this->entityManager->flush();

        return $media;
    }

    public function reviewModel(User $reviewer, PrintDeviceModel $model, string $action): PrintDeviceModel
    {
        $this->assertCanReview($reviewer, $model->getOrganisationId());
        $this->applyReview($model, $reviewer, $action, static function (PrintDeviceModel $item, string $status, string $scope): void {
            $item->setStatus($status);
            $item->setScope($scope);
            if ($scope === PrintDeviceModel::SCOPE_GLOBAL) {
                $item->setOrganisationId(null);
            }
        });
        $this->entityManager->flush();

        return $model;
    }

    public function requestGlobalForModel(User $user, Department $department, PrintDeviceModel $model): PrintDeviceModel
    {
        $this->assertCanRequestGlobal($user, $department, $model->getScope(), $model->getOrganisationId());
        $this->markGlobalRequested($model);
        $this->entityManager->flush();

        return $model;
    }

    public function requestGlobalForMedia(User $user, Department $department, PrintMedia $media): PrintMedia
    {
        $this->assertCanRequestGlobal($user, $department, $media->getScope(), $media->getOrganisationId());
        $this->markGlobalRequested($media);
        $this->entityManager->flush();

        return $media;
    }

    public function reviewMedia(User $reviewer, PrintMedia $media, string $action): PrintMedia
    {
        $this->assertCanReview($reviewer, $media->getOrganisationId());
        $this->applyReview($media, $reviewer, $action, static function (PrintMedia $item, string $status, string $scope): void {
            $item->setStatus($status);
            $item->setScope($scope);
            if ($scope === PrintMedia::SCOPE_GLOBAL) {
                $item->setOrganisationId(null);
            }
        });
        $this->entityManager->flush();

        return $media;
    }

    /**
     * @return list<DepartmentPrintPreset>
     */
    public function listPresets(string $departmentId): array
    {
        return $this->entityManager->getRepository(DepartmentPrintPreset::class)->findBy(
            ['departmentId' => $departmentId],
            ['isDefault' => 'DESC', 'name' => 'ASC'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPreset(User $user, Department $department, array $data): DepartmentPrintPreset
    {
        $name = trim((string) ($data['name'] ?? ''));
        $modelId = trim((string) ($data['device_model_id'] ?? ''));
        $mediaId = trim((string) ($data['media_id'] ?? ''));
        if ($name === '' || $modelId === '' || $mediaId === '') {
            throw new \InvalidArgumentException('name, device_model_id und media_id sind erforderlich');
        }
        $model = $this->entityManager->getRepository(PrintDeviceModel::class)->find($modelId);
        $media = $this->entityManager->getRepository(PrintMedia::class)->find($mediaId);
        if (!$model instanceof PrintDeviceModel || !$media instanceof PrintMedia) {
            throw new \InvalidArgumentException('Gerät oder Medium nicht gefunden');
        }
        if ($model->getStatus() !== PrintDeviceModel::STATUS_PUBLISHED || $media->getStatus() !== PrintMedia::STATUS_PUBLISHED) {
            throw new \InvalidArgumentException('Nur freigegebene Geräte und Medien können Favoriten werden');
        }
        if (!PrintCatalogVisibility::mediaCompatibleWithModel($model, $media)) {
            throw new \InvalidArgumentException('Medium passt nicht zum gewählten Gerät');
        }
        $cut = isset($data['cut_length_mm']) ? (int) $data['cut_length_mm'] : $media->getDefaultCutLengthMm();
        if (!$media->isContinuous()) {
            $cut = null;
        } elseif ($cut === null || $cut < 10) {
            throw new \InvalidArgumentException('Für Endlosetiketten ist eine Schnittlänge nötig');
        }

        $preset = new DepartmentPrintPreset();
        $preset->setId(IdGenerator::generateUnique($this->entityManager, DepartmentPrintPreset::class));
        $preset->setDepartment($department);
        $preset->setName($name);
        $preset->setDeviceModel($model);
        $preset->setMedia($media);
        $preset->setCutLengthMm($cut);
        $preset->setCreatedByUserId($user->getId());
        $makeDefault = (bool) ($data['is_default'] ?? false) || $this->listPresets($department->getId()) === [];
        if ($makeDefault) {
            $this->clearDefaults($department->getId());
            $preset->setIsDefault(true);
        }

        $this->entityManager->persist($preset);
        $this->entityManager->flush();

        return $preset;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePreset(DepartmentPrintPreset $preset, array $data): DepartmentPrintPreset
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('name darf nicht leer sein');
            }
            $preset->setName($name);
        }
        if (isset($data['device_model_id']) || isset($data['media_id'])) {
            $modelId = trim((string) ($data['device_model_id'] ?? $preset->getDeviceModelId()));
            $mediaId = trim((string) ($data['media_id'] ?? $preset->getMediaId()));
            $model = $this->entityManager->getRepository(PrintDeviceModel::class)->find($modelId);
            $media = $this->entityManager->getRepository(PrintMedia::class)->find($mediaId);
            if (!$model instanceof PrintDeviceModel || !$media instanceof PrintMedia) {
                throw new \InvalidArgumentException('Gerät oder Medium nicht gefunden');
            }
            if (!PrintCatalogVisibility::mediaCompatibleWithModel($model, $media)) {
                throw new \InvalidArgumentException('Medium passt nicht zum gewählten Gerät');
            }
            $preset->setDeviceModel($model);
            $preset->setMedia($media);
        }
        if (array_key_exists('cut_length_mm', $data)) {
            $media = $preset->getMedia();
            if ($media->isContinuous()) {
                $cut = (int) $data['cut_length_mm'];
                if ($cut < 10) {
                    throw new \InvalidArgumentException('Schnittlänge zu klein');
                }
                $preset->setCutLengthMm($cut);
            } else {
                $preset->setCutLengthMm(null);
            }
        }
        if (array_key_exists('is_default', $data) && $data['is_default']) {
            $this->clearDefaults($preset->getDepartmentId());
            $preset->setIsDefault(true);
        }
        $preset->touch();
        $this->entityManager->flush();

        return $preset;
    }

    public function deletePreset(DepartmentPrintPreset $preset): void
    {
        $deptId = $preset->getDepartmentId();
        $wasDefault = $preset->isDefault();
        $this->entityManager->remove($preset);
        $this->entityManager->flush();
        if ($wasDefault) {
            $rest = $this->listPresets($deptId);
            if ($rest !== []) {
                $rest[0]->setIsDefault(true);
                $rest[0]->touch();
                $this->entityManager->flush();
            }
        }
    }

    /** @return array<string, mixed> */
    public function serializeModel(PrintDeviceModel $model): array
    {
        return [
            'id' => $model->getId(),
            'catalog_key' => $model->getCatalogKey(),
            'family' => $model->getFamily(),
            'brand' => $model->getBrand(),
            'name' => $model->getName(),
            'compatible_media_keys' => $model->getCompatibleMediaKeys(),
            'status' => $model->getStatus(),
            'scope' => $model->getScope(),
            'organisation_id' => $model->getOrganisationId(),
            'created_by_user_id' => $model->getCreatedByUserId(),
            'global_requested' => $model->isGlobalRequested(),
            'reviewed_at' => $model->getReviewedAt()?->format('c'),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeMedia(PrintMedia $media): array
    {
        return [
            'id' => $media->getId(),
            'catalog_key' => $media->getCatalogKey(),
            'family' => $media->getFamily(),
            'brand' => $media->getBrand(),
            'sku' => $media->getSku(),
            'name' => $media->getName(),
            'width_mm' => (float) $media->getWidthMm(),
            'height_mm' => $media->getHeightMm() !== null ? (float) $media->getHeightMm() : null,
            'cols' => $media->getCols(),
            'rows' => $media->getRows(),
            'is_continuous' => $media->isContinuous(),
            'default_cut_length_mm' => $media->getDefaultCutLengthMm(),
            'shape' => $media->getShape(),
            'sheet_width_mm' => $media->getSheetWidthMm() !== null ? (float) $media->getSheetWidthMm() : null,
            'sheet_height_mm' => $media->getSheetHeightMm() !== null ? (float) $media->getSheetHeightMm() : null,
            'margin_top_mm' => $media->getMarginTopMm() !== null ? (float) $media->getMarginTopMm() : null,
            'margin_left_mm' => $media->getMarginLeftMm() !== null ? (float) $media->getMarginLeftMm() : null,
            'gap_x_mm' => $media->getGapXMm() !== null ? (float) $media->getGapXMm() : null,
            'gap_y_mm' => $media->getGapYMm() !== null ? (float) $media->getGapYMm() : null,
            'status' => $media->getStatus(),
            'scope' => $media->getScope(),
            'organisation_id' => $media->getOrganisationId(),
            'created_by_user_id' => $media->getCreatedByUserId(),
            'global_requested' => $media->isGlobalRequested(),
            'reviewed_at' => $media->getReviewedAt()?->format('c'),
        ];
    }

    /** @return array<string, mixed> */
    public function serializePreset(DepartmentPrintPreset $preset): array
    {
        return [
            'id' => $preset->getId(),
            'department_id' => $preset->getDepartmentId(),
            'name' => $preset->getName(),
            'device_model_id' => $preset->getDeviceModelId(),
            'media_id' => $preset->getMediaId(),
            'cut_length_mm' => $preset->getCutLengthMm(),
            'is_default' => $preset->isDefault(),
            'device_model' => $this->serializeModel($preset->getDeviceModel()),
            'media' => $this->serializeMedia($preset->getMedia()),
        ];
    }

    private function clearDefaults(string $departmentId): void
    {
        foreach ($this->listPresets($departmentId) as $preset) {
            if ($preset->isDefault()) {
                $preset->setIsDefault(false);
                $preset->touch();
            }
        }
    }

    private function assertCanReview(User $reviewer, ?string $organisationId): void
    {
        $roles = $reviewer->getRoles();
        if (!PrintCatalogVisibility::isReviewer($roles)) {
            throw new \RuntimeException('Keine Berechtigung zur Prüfung');
        }
        if (!PrintCatalogVisibility::canReviewItem(
            $organisationId,
            $this->organisationIdsForUser($reviewer),
            $this->canSeeAllOrganisations($reviewer),
        )) {
            throw new \RuntimeException('Keine Berechtigung für diese Organisation');
        }
    }

    /**
     * @param callable(object, string, string): void $apply
     */
    private function applyReview(object $item, User $reviewer, string $action, callable $apply): void
    {
        $action = strtolower(trim($action));
        if (!\in_array($action, ['approve', 'reject', 'promote_global'], true)) {
            throw new \InvalidArgumentException('action muss approve, reject oder promote_global sein');
        }
        if ($action === 'promote_global' && !PrintCatalogVisibility::isSuperAdmin($reviewer->getRoles())) {
            throw new \RuntimeException('Nur Superadmin kann ohne Antrag global hochstufen');
        }
        $now = new \DateTime();
        if ($item instanceof PrintDeviceModel) {
            $item->setReviewedByUserId($reviewer->getId());
            $item->setReviewedAt($now);
            $item->touch();
        } elseif ($item instanceof PrintMedia) {
            $item->setReviewedByUserId($reviewer->getId());
            $item->setReviewedAt($now);
            $item->touch();
        }
        if ($action === 'reject') {
            if ($this->isPublishedOrgGlobalRequest($item)) {
                $this->setGlobalRequestedFlag($item, false);

                return;
            }
            $apply($item, PrintMedia::STATUS_REJECTED, PrintMedia::SCOPE_ORGANISATION);
            $this->setGlobalRequestedFlag($item, false);

            return;
        }
        $apply($item, PrintMedia::STATUS_PUBLISHED, PrintMedia::SCOPE_GLOBAL);
        $this->setGlobalRequestedFlag($item, false);
        if ($item instanceof PrintMedia) {
            $this->wirePublishedMediaToFamilyModels($item);
        }
    }

    /** @param array<string, mixed> $data */
    private function wantsGlobalReview(array $data): bool
    {
        $raw = $data['request_global'] ?? false;

        return $raw === true || $raw === 1 || $raw === '1' || $raw === 'true';
    }

    private function assertCanRequestGlobal(User $user, Department $department, string $scope, ?string $organisationId): void
    {
        if (!$this->canManageDepartment($user, $department->getId())) {
            throw new \RuntimeException('Keine Berechtigung');
        }
        if ($scope !== PrintMedia::SCOPE_ORGANISATION) {
            throw new \InvalidArgumentException('Nur organisationsinterne Einträge können global beantragt werden');
        }
        if ($organisationId === null || $organisationId !== $department->getOrganisationId()) {
            throw new \RuntimeException('Eintrag gehört nicht zu dieser Organisation');
        }
    }

    private function markGlobalRequested(PrintDeviceModel|PrintMedia $item): void
    {
        if ($item->getStatus() === PrintMedia::STATUS_REJECTED) {
            throw new \InvalidArgumentException('Abgelehnte Einträge können nicht erneut global beantragt werden');
        }
        $item->setStatus(PrintMedia::STATUS_PUBLISHED);
        $item->setGlobalRequested(true);
        $item->touch();
    }

    private function isPublishedOrgGlobalRequest(object $item): bool
    {
        if (!$item instanceof PrintDeviceModel && !$item instanceof PrintMedia) {
            return false;
        }

        return $item->getStatus() === PrintMedia::STATUS_PUBLISHED
            && $item->getScope() === PrintMedia::SCOPE_ORGANISATION
            && $item->isGlobalRequested();
    }

    private function setGlobalRequestedFlag(object $item, bool $value): void
    {
        if ($item instanceof PrintDeviceModel || $item instanceof PrintMedia) {
            $item->setGlobalRequested($value);
        }
    }

    private function wirePublishedMediaToFamilyModels(PrintMedia $media): void
    {
        $models = $this->entityManager->getRepository(PrintDeviceModel::class)->findBy([
            'family' => $media->getFamily(),
            'status' => PrintDeviceModel::STATUS_PUBLISHED,
            'scope' => PrintDeviceModel::SCOPE_GLOBAL,
        ]);
        foreach ($models as $model) {
            if (!$model instanceof PrintDeviceModel) {
                continue;
            }
            $keys = $model->getCompatibleMediaKeys();
            if ($keys === []) {
                continue;
            }
            if (\in_array($media->getCatalogKey(), $keys, true)) {
                continue;
            }
            $keys[] = $media->getCatalogKey();
            $model->setCompatibleMediaKeys($keys);
            $model->touch();
        }
    }

    private function canSeeCatalogItem(
        User $user,
        string $status,
        string $scope,
        ?string $organisationId,
        ?string $createdByUserId,
    ): bool {
        return PrintCatalogVisibility::canSeeItem(
            $status,
            $scope,
            $organisationId,
            $createdByUserId,
            $user->getId(),
            $this->organisationIdsForUser($user),
            PrintCatalogVisibility::isSuperAdmin($user->getRoles()) || $this->canSeeAllOrganisations($user),
        );
    }

    private function parseMm(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
