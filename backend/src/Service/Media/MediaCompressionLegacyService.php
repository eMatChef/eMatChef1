<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\WorkshopTicket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Komprimiert Legacy-Fotos ohne bytes-Metadaten in der DB.
 */
class MediaCompressionLegacyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
        private MediaCompressionService $compressionService,
        private MediaPhotoNormalizer $photoNormalizer,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return array{entities: int, photos: int, compressed: int, skipped: int, bytes_before: int, bytes_after: int}
     */
    public function run(bool $dryRun = false): array
    {
        $stats = [
            'entities' => 0,
            'photos' => 0,
            'compressed' => 0,
            'skipped' => 0,
            'bytes_before' => 0,
            'bytes_after' => 0,
        ];

        /** @var list<WorkshopTicket> $tickets */
        $tickets = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(WorkshopTicket::class, 't')
            ->where('t.photos IS NOT NULL')
            ->getQuery()
            ->getResult();

        foreach ($tickets as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }

            $photos = $ticket->getPhotos() ?? [];
            if ($photos === []) {
                continue;
            }

            $changed = false;
            $updated = [];
            $departmentId = $ticket->getDepartmentId();
            $ticketId = (string) $ticket->getId();
            foreach ($photos as $photo) {
                if (!\is_array($photo)) {
                    $updated[] = $photo;
                    continue;
                }

                if (isset($photo['bytes']) && is_numeric($photo['bytes'])) {
                    $updated[] = $photo;
                    continue;
                }

                ++$stats['photos'];
                $filename = (string) ($photo['filename'] ?? '');
                if ($filename === '') {
                    ++$stats['skipped'];
                    $updated[] = $photo;
                    continue;
                }

                $path = $this->resolveWorkshopPhotoPath($ticket, $filename, $photo);
                if ($path === null || !is_file($path)) {
                    ++$stats['skipped'];
                    $updated[] = $photo;
                    continue;
                }

                $before = (int) filesize($path);
                $stats['bytes_before'] += $before;

                if ($dryRun) {
                    ++$stats['compressed'];
                    $stats['bytes_after'] += $before;
                    $updated[] = $photo;
                    continue;
                }

                $compressed = $this->compressionService->compressExistingFile(
                    $path,
                    MediaCompressionProfile::workshop(),
                );
                if ($compressed === null) {
                    ++$stats['skipped'];
                    $updated[] = $photo;
                    continue;
                }

                $photo['bytes'] = $compressed['bytes'];
                $photo['width'] = $compressed['width'];
                $photo['height'] = $compressed['height'];
                $photo['mime'] = $compressed['mime'];
                $newFilename = pathinfo($compressed['path'], PATHINFO_BASENAME);
                if ($newFilename !== $filename) {
                    $photo['filename'] = $newFilename;
                    $photo['url'] = $this->mediaStorage->buildPublicMediaUrl(
                        MediaStorageService::CONTEXT_WORKSHOP_TICKET,
                        $departmentId,
                        $ticketId,
                        $newFilename,
                    );
                }

                ++$stats['compressed'];
                $stats['bytes_after'] += $compressed['bytes'];
                $updated[] = $photo;
                $changed = true;
            }

            if ($changed) {
                ++$stats['entities'];
                $ticket->setPhotos($updated);
            }
        }

        if (!$dryRun && $stats['entities'] > 0) {
            $this->entityManager->flush();
            $this->writeLog($dryRun, $stats);
        }

        return $stats;
    }

    /** @param array<string, mixed> $photo */
    private function resolveWorkshopPhotoPath(WorkshopTicket $ticket, string $filename, array $photo): ?string
    {
        $departmentId = $ticket->getDepartmentId();
        $ticketId = (string) $ticket->getId();

        $primary = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
        ) . '/' . $filename;
        if (is_file($primary)) {
            return $primary;
        }

        $companyId = $this->photoNormalizer->resolveSupplierCompanyId($photo)
            ?? $ticket->getAssignedToSupplierCompanyId();
        if ($companyId !== null && $companyId !== '') {
            $legacy = $this->mediaStorage->resolveLegacyWorkshopSupplierDir($companyId, $ticketId) . '/' . $filename;
            if (is_file($legacy)) {
                return $legacy;
            }
        }

        return null;
    }

    /** @param array<string, int> $stats */
    private function writeLog(bool $dryRun, array $stats): void
    {
        $logDir = $this->projectDir . '/var/log';
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $saved = max(0, $stats['bytes_before'] - $stats['bytes_after']);
        $line = sprintf(
            "[%s] compress-legacy dry_run=%s entities=%d photos=%d compressed=%d skipped=%d saved_bytes=%d\n",
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            $dryRun ? 'true' : 'false',
            $stats['entities'],
            $stats['photos'],
            $stats['compressed'],
            $stats['skipped'],
            $saved,
        );

        @file_put_contents($logDir . '/media_retention.log', $line, FILE_APPEND);
    }
}
