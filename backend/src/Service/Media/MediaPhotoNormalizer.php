<?php

declare(strict_types=1);

namespace App\Service\Media;

/**
 * Einheitliches Foto-JSON für API-Responses (Legacy-URL-Strings inklusive).
 */
class MediaPhotoNormalizer
{
    /**
     * @param list<mixed>|null $photos
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeOutgoing(?array $photos): array
    {
        if ($photos === null) {
            return [];
        }

        $result = [];
        foreach ($photos as $photo) {
            if (\is_string($photo)) {
                $result[] = [
                    'url' => $photo,
                    'legacy' => true,
                ];
                continue;
            }
            if (!\is_array($photo)) {
                continue;
            }

            $normalized = $photo;
            if (!empty($normalized['legacy'])) {
                $normalized['legacy'] = true;
            }

            // Legacy-Feldname supplier_company_id → uploaded_by_supplier_company_id
            if (
                empty($normalized['uploaded_by_supplier_company_id'])
                && !empty($normalized['supplier_company_id'])
            ) {
                $normalized['uploaded_by_supplier_company_id'] = $normalized['supplier_company_id'];
            }

            $result[] = $normalized;
        }

        return $result;
    }

    /**
     * @param list<mixed> $photos
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeIncoming(array $photos): array
    {
        $result = [];
        foreach ($photos as $photo) {
            if (\is_string($photo)) {
                $result[] = ['url' => $photo, 'legacy' => true];
            } elseif (\is_array($photo)) {
                $result[] = $photo;
            }
        }

        return $result;
    }

    /**
     * Liefert die Lieferanten-Firmen-ID aus Foto-Metadaten (neu oder legacy).
     */
    public function resolveSupplierCompanyId(array $photo): ?string
    {
        if (!empty($photo['uploaded_by_supplier_company_id'])) {
            return (string) $photo['uploaded_by_supplier_company_id'];
        }
        if (!empty($photo['supplier_company_id'])) {
            return (string) $photo['supplier_company_id'];
        }

        return null;
    }
}
