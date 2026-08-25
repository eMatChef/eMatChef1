<?php

declare(strict_types=1);

namespace App\Service\PrintCatalog;

use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;

/**
 * Sichtbarkeit von Katalog-Einträgen: global published, org published, eigene pending.
 */
final class PrintCatalogVisibility
{
    public const MANAGER_ROLES = ['mw', 'dc', 'matwart', 'depchef'];
    public const REVIEWER_ROLES = ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'];

    /** @return list<array{id: string, label: string}> */
    public static function families(): array
    {
        return [
            ['id' => PrintDeviceModel::FAMILY_BROTHER_QL, 'label' => 'Brother QL'],
            ['id' => PrintDeviceModel::FAMILY_TSC_DESKTOP, 'label' => 'TSC Desktop (DA210)'],
            ['id' => PrintDeviceModel::FAMILY_OFFICE_A4, 'label' => 'Büro Laser/Inkjet (Avery, A4–A8)'],
        ];
    }

    /** @param list<string> $userRoles */
    public static function isSuperAdmin(array $userRoles): bool
    {
        return \in_array('ROLE_SUPERADMIN', $userRoles, true);
    }

    /** @param list<string> $userRoles */
    public static function isReviewer(array $userRoles): bool
    {
        return count(array_intersect(self::REVIEWER_ROLES, $userRoles)) > 0;
    }

    public static function isManagerRole(?string $membershipRole): bool
    {
        return \in_array(strtolower(trim((string) $membershipRole)), self::MANAGER_ROLES, true);
    }

    /**
     * @param list<string> $organisationIds Organisationen des Users (aus Memberships)
     */
    public static function canSeePublished(string $scope, ?string $organisationId, array $organisationIds): bool
    {
        if ($scope === PrintMedia::SCOPE_GLOBAL) {
            return true;
        }

        return $scope === PrintMedia::SCOPE_ORGANISATION
            && $organisationId !== null
            && \in_array($organisationId, $organisationIds, true);
    }

    /**
     * @param list<string> $organisationIds
     */
    public static function canSeeItem(
        string $status,
        string $scope,
        ?string $organisationId,
        ?string $createdByUserId,
        string $viewerUserId,
        array $organisationIds,
        bool $isSuperAdmin,
    ): bool {
        if ($isSuperAdmin) {
            return true;
        }
        if ($status === PrintMedia::STATUS_REJECTED) {
            return $createdByUserId === $viewerUserId;
        }
        if ($status === PrintMedia::STATUS_PENDING) {
            return $createdByUserId === $viewerUserId
                || ($organisationId !== null && \in_array($organisationId, $organisationIds, true));
        }
        if ($status === PrintMedia::STATUS_PUBLISHED) {
            return self::canSeePublished($scope, $organisationId, $organisationIds);
        }

        return false;
    }

    /**
     * @param list<string> $organisationIds
     */
    public static function canReviewItem(?string $organisationId, array $organisationIds, bool $isSuperAdmin): bool
    {
        if ($isSuperAdmin) {
            return true;
        }

        return $organisationId !== null && \in_array($organisationId, $organisationIds, true);
    }

    public static function slugKey(string $brand, string $skuOrName): string
    {
        $raw = strtolower(trim($brand . '_' . $skuOrName));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? 'item';
        $raw = trim($raw, '_');
        if ($raw === '') {
            $raw = 'item';
        }

        return substr($raw, 0, 64);
    }

    /** @param list<string> $compatibleKeys */
    public static function mediaCompatibleWithModel(PrintDeviceModel $model, PrintMedia $media): bool
    {
        if ($model->getFamily() !== $media->getFamily()) {
            return false;
        }
        $keys = $model->getCompatibleMediaKeys();
        if ($keys === []) {
            return true;
        }
        if (\in_array($media->getCatalogKey(), $keys, true)) {
            return true;
        }

        // Org-eigene Etiketten darf der MW an Geräte derselben Familie hängen.
        return $media->getScope() === PrintMedia::SCOPE_ORGANISATION
            && $media->getStatus() === PrintMedia::STATUS_PUBLISHED;
    }
}
