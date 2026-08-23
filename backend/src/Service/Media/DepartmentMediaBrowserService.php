<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\ActivityGrossanlassProcurementQuote;
use App\Entity\ActivityIssueReport;
use App\Entity\ActivityJsOrder;
use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Listet bestehende Department-Medien (Fotos + PDFs) für den Medienbrowser.
 */
class DepartmentMediaBrowserService
{
    public const KIND_PHOTOS = 'photos';
    public const KIND_DOCUMENTS = 'documents';

    private const MAX_ITEMS = 2000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(
        string $departmentId,
        string $kind = '',
        string $context = '',
        string $query = '',
    ): array {
        $items = [
            ...$this->collectMaterial($departmentId),
            ...$this->collectWorkshop($departmentId),
            ...$this->collectIssues($departmentId),
            ...$this->collectBookings($departmentId),
            ...$this->collectFollowUps($departmentId),
            ...$this->collectJsOrders($departmentId),
            ...$this->collectQuotes($departmentId),
        ];

        if ($kind === self::KIND_PHOTOS || $kind === self::KIND_DOCUMENTS) {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['kind'] === $kind,
            ));
        }

        if ($context !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['context'] === $context,
            ));
        }

        $needle = mb_strtolower(trim($query));
        if ($needle !== '') {
            $items = array_values(array_filter(
                $items,
                static function (array $item) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', [
                        (string) ($item['original_filename'] ?? ''),
                        (string) ($item['filename'] ?? ''),
                        (string) ($item['context_label'] ?? ''),
                        (string) ($item['uploaded_by_name'] ?? ''),
                    ]));

                    return str_contains($haystack, $needle);
                },
            ));
        }

        usort(
            $items,
            static fn (array $a, array $b): int => strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? '')),
        );

        $total = count($items);
        if ($total > self::MAX_ITEMS) {
            $items = array_slice($items, 0, self::MAX_ITEMS);
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $photo
     * @param list<array{kind: string, label: string, path: string}>|null $links
     *
     * @return array<string, mixed>|null
     */
    public function mapStoredFile(
        string $context,
        string $departmentId,
        string $contextId,
        string $contextLabel,
        string $sourcePath,
        array $photo,
        ?string $fallbackUploadedAt = null,
        ?array $links = null,
    ): ?array {
        $filename = $this->filenameFromPhoto($photo);
        if ($filename === null) {
            return null;
        }

        try {
            $url = $this->mediaStorage->buildPublicMediaUrl($context, $departmentId, $contextId, $filename);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $mime = (string) ($photo['mime'] ?? '');
        $isDocument = $mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf');
        $canReplace = !\in_array($context, [
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
            MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE,
            MediaStorageService::CONTEXT_GROSSANLASS_USER_CARD,
        ], true);

        $resolvedLinks = $links ?? [[
            'kind' => $this->defaultLinkKind($context),
            'label' => $contextLabel,
            'path' => $sourcePath,
        ]];

        return [
            'id' => (string) ($photo['id'] ?? $filename),
            'kind' => $isDocument ? self::KIND_DOCUMENTS : self::KIND_PHOTOS,
            'context' => $context,
            'context_id' => $contextId,
            'context_label' => $contextLabel,
            'filename' => $filename,
            'original_filename' => $this->displayOriginalFilename($context, $photo, $filename, $contextLabel),
            'url' => $url,
            'mime' => $mime !== '' ? $mime : ($isDocument ? 'application/pdf' : 'image/*'),
            'bytes' => isset($photo['bytes']) && is_numeric($photo['bytes']) ? (int) $photo['bytes'] : null,
            'width' => isset($photo['width']) && is_numeric($photo['width']) ? (int) $photo['width'] : null,
            'height' => isset($photo['height']) && is_numeric($photo['height']) ? (int) $photo['height'] : null,
            'uploaded_at' => (string) ($photo['uploaded_at'] ?? $fallbackUploadedAt ?? ''),
            'uploaded_by_name' => (string) ($photo['uploaded_by_name'] ?? ''),
            'source_path' => $sourcePath,
            'can_replace' => $canReplace,
            'can_rename' => $canReplace,
            'links' => $resolvedLinks,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function collectMaterial(string $departmentId): array
    {
        /** @var list<MaterialItem> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MaterialItem::class, 'm')
            ->where('m.departmentId = :dept')
            ->andWhere('m.photos IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $material) {
            $id = (string) $material->getId();
            $label = $material->getName();
            foreach ($this->photoNormalizer->normalizeOutgoing($material->getPhotos()) as $photo) {
                $mapped = $this->mapStoredFile(
                    MediaStorageService::CONTEXT_MATERIAL_ITEM,
                    $departmentId,
                    $id,
                    $label,
                    '/materials/' . $id,
                    $photo,
                );
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectWorkshop(string $departmentId): array
    {
        /** @var list<WorkshopTicket> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t', 'm')
            ->from(WorkshopTicket::class, 't')
            ->leftJoin('t.materialItem', 'm')
            ->where('t.departmentId = :dept')
            ->andWhere('t.photos IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $ticket) {
            $id = (string) $ticket->getId();
            $material = $ticket->getMaterialItem();
            $links = [
                [
                    'kind' => 'workshop',
                    'label' => $ticket->getTitle(),
                    'path' => '/workshop?ticket=' . rawurlencode($id),
                ],
                [
                    'kind' => 'material',
                    'label' => $material->getName(),
                    'path' => '/materials/' . $material->getId(),
                ],
            ];
            foreach ($this->photoNormalizer->normalizeOutgoing($ticket->getPhotos()) as $photo) {
                $mapped = $this->mapStoredFile(
                    MediaStorageService::CONTEXT_WORKSHOP_TICKET,
                    $departmentId,
                    $id,
                    $ticket->getTitle(),
                    '/workshop?ticket=' . rawurlencode($id),
                    $photo,
                    null,
                    $links,
                );
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectIssues(string $departmentId): array
    {
        /** @var list<ActivityIssueReport> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r', 'a', 'mi')
            ->from(ActivityIssueReport::class, 'r')
            ->innerJoin('r.activity', 'a')
            ->leftJoin('r.materialItem', 'mi')
            ->where('a.departmentId = :dept')
            ->andWhere('r.photos IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $report) {
            $id = (string) $report->getId();
            $activityId = $report->getActivityId();
            $label = $report->getActivity()->getName();
            $links = [[
                'kind' => 'activity',
                'label' => $label,
                'path' => '/activities/' . $activityId,
            ]];
            $material = $report->getMaterialItem();
            if ($material !== null) {
                $links[] = [
                    'kind' => 'material',
                    'label' => $material->getName(),
                    'path' => '/materials/' . $material->getId(),
                ];
            }
            foreach ($this->photoNormalizer->normalizeOutgoing($report->getPhotos()) as $photo) {
                $mapped = $this->mapStoredFile(
                    MediaStorageService::CONTEXT_ISSUE_REPORT,
                    $departmentId,
                    $id,
                    $label,
                    '/activities/' . $activityId,
                    $photo,
                    null,
                    $links,
                );
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectBookings(string $departmentId): array
    {
        /** @var list<AccountingBooking> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(AccountingBooking::class, 'b')
            ->where('IDENTITY(b.department) = :dept')
            ->andWhere('b.receipts IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $booking) {
            $id = (string) $booking->getId();
            $label = $booking->getReceiptLabel() ?: ('Buchung ' . $booking->getBookedAt()->format('Y-m-d'));
            foreach ($this->photoNormalizer->normalizeOutgoing($booking->getReceipts()) as $photo) {
                $mapped = $this->mapStoredFile(
                    MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
                    $departmentId,
                    $id,
                    $label,
                    '/accounting/bookings',
                    $photo,
                );
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectFollowUps(string $departmentId): array
    {
        /** @var list<AccountingAcquisitionFollowUp> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(AccountingAcquisitionFollowUp::class, 'f')
            ->where('IDENTITY(f.department) = :dept')
            ->andWhere('f.receipts IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $followUp) {
            $id = (string) $followUp->getId();
            $label = $followUp->getReceiptLabel() ?: 'Anschaffung';
            foreach ($this->photoNormalizer->normalizeOutgoing($followUp->getReceipts()) as $photo) {
                $mapped = $this->mapStoredFile(
                    MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
                    $departmentId,
                    $id,
                    $label,
                    '/accounting/bookings',
                    $photo,
                );
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectJsOrders(string $departmentId): array
    {
        /** @var list<ActivityJsOrder> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('o', 'a')
            ->from(ActivityJsOrder::class, 'o')
            ->innerJoin('o.activity', 'a')
            ->where('a.departmentId = :dept')
            ->andWhere('o.generatedPdfMediaId IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $order) {
            $mediaId = $order->getGeneratedPdfMediaId();
            $orderId = (string) $order->getId();
            if ($mediaId === null || $mediaId === '' || $orderId === '') {
                continue;
            }
            $filename = $mediaId . '.pdf';
            $activity = $order->getActivity();
            $mapped = $this->mapStoredFile(
                MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER,
                $departmentId,
                $orderId,
                $activity->getName() . ' (J+S)',
                '/activities/' . $activity->getId(),
                [
                    'id' => $mediaId,
                    'filename' => $filename,
                    'original_filename' => $filename,
                    'mime' => 'application/pdf',
                    'uploaded_at' => $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ],
            );
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function collectQuotes(string $departmentId): array
    {
        /** @var list<ActivityGrossanlassProcurementQuote> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('q', 'l')
            ->from(ActivityGrossanlassProcurementQuote::class, 'q')
            ->innerJoin('q.procurementLine', 'l')
            ->where('l.departmentId = :dept')
            ->andWhere('q.pdfFilename IS NOT NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($rows as $quote) {
            $filename = $quote->getPdfFilename();
            if ($filename === null || $filename === '') {
                continue;
            }
            $mapped = $this->mapStoredFile(
                MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE,
                $departmentId,
                $quote->getId(),
                $quote->getSupplier(),
                '/beschaffung/offerten',
                [
                    'filename' => $filename,
                    'original_filename' => $filename,
                    'mime' => 'application/pdf',
                    'uploaded_at' => $quote->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
            );
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    private function defaultLinkKind(string $context): string
    {
        return match ($context) {
            MediaStorageService::CONTEXT_MATERIAL_ITEM => 'material',
            MediaStorageService::CONTEXT_WORKSHOP_TICKET => 'workshop',
            MediaStorageService::CONTEXT_ISSUE_REPORT => 'activity',
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING => 'accounting',
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP => 'follow_up',
            MediaStorageService::CONTEXT_ACTIVITY_JS_ORDER => 'js_order',
            MediaStorageService::CONTEXT_GROSSANLASS_PROCUREMENT_QUOTE => 'quote',
            MediaStorageService::CONTEXT_GROSSANLASS_USER_CARD => 'record',
            default => 'record',
        };
    }

    /** @param array<string, mixed> $photo */
    private function filenameFromPhoto(array $photo): ?string
    {
        $filename = trim((string) ($photo['filename'] ?? ''));
        if ($filename !== '') {
            return $filename;
        }

        $url = (string) ($photo['url'] ?? '');
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $base = basename(\is_string($path) && $path !== '' ? $path : $url);

        return $base !== '' && $base !== '/' ? $base : null;
    }

    /**
     * Material hat nur ein Foto — Kameranamen wie IMG_1234 durch den Artikelnamen ersetzen.
     *
     * @param array<string, mixed> $photo
     */
    private function displayOriginalFilename(
        string $context,
        array $photo,
        string $filename,
        string $contextLabel,
    ): string {
        $original = trim((string) ($photo['original_filename'] ?? ''));
        if ($context === MediaStorageService::CONTEXT_MATERIAL_ITEM) {
            $label = trim($contextLabel);
            if ($label !== '' && ($original === '' || $original === $filename || $this->isGenericCameraFilename($original))) {
                return $label;
            }
        }

        return $original !== '' ? $original : $filename;
    }

    private function isGenericCameraFilename(string $name): bool
    {
        $base = pathinfo($name, PATHINFO_FILENAME);

        return (bool) preg_match(
            '/^(IMG|DSCN?|PXL|DCIM|screenshot|image|photo)([-_.]|$)/i',
            $base,
        );
    }
}
