<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\ActivityIssueReport;
use App\Entity\WorkshopTicket;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Retention für Werkstatt-Fotos: abgeschlossene Tickets älter als X Jahre.
 */
class MediaRetentionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
        private MediaSettingsStore $settingsStore,
        private MediaPhotoNormalizer $photoNormalizer,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function run(?int $years = null, bool $dryRun = false): MediaRetentionRunResult
    {
        $years = $years ?? $this->settingsStore->getRetentionYears();
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d years', $years));

        /** @var list<WorkshopTicket> $tickets */
        $tickets = $this->entityManager->createQueryBuilder()
            ->select('t', 'ir')
            ->from(WorkshopTicket::class, 't')
            ->leftJoin('t.issueReport', 'ir')
            ->where('t.status = :status')
            ->andWhere('t.completedAt IS NOT NULL')
            ->andWhere('t.completedAt < :cutoff')
            ->setParameter('status', WorkshopTicket::STATUS_COMPLETED)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('t.completedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $items = [];
        $ticketsProcessed = 0;
        $issueReportsProcessed = 0;
        $filesDeleted = 0;
        $bytesFreed = 0;

        foreach ($tickets as $ticket) {
            if (!$ticket instanceof WorkshopTicket) {
                continue;
            }

            $preview = $this->previewTicketCleanup($ticket);
            if ($preview['files'] === 0 && $preview['photo_count'] === 0 && !$preview['has_issue_report_photos']) {
                continue;
            }

            $items[] = $preview;

            if ($dryRun) {
                continue;
            }

            $result = $this->cleanupTicket($ticket);
            if ($result['processed']) {
                ++$ticketsProcessed;
            }
            $issueReportsProcessed += $result['issue_reports'];
            $filesDeleted += $result['files'];
            $bytesFreed += $result['bytes'];
        }

        $result = new MediaRetentionRunResult(
            ticketsMatched: \count($items),
            ticketsProcessed: $ticketsProcessed,
            issueReportsProcessed: $issueReportsProcessed,
            filesDeleted: $filesDeleted,
            bytesFreed: $bytesFreed,
            items: $items,
        );

        if (!$dryRun && ($ticketsProcessed > 0 || $issueReportsProcessed > 0)) {
            $this->entityManager->flush();
            $this->writeLog($years, $dryRun, $result);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewTicketCleanup(WorkshopTicket $ticket): array
    {
        $departmentId = $ticket->getDepartmentId();
        $ticketId = (string) $ticket->getId();
        $workshopDir = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
        );
        $stats = $this->mediaStorage->measureDirectory($workshopDir);

        foreach ($this->collectLegacySupplierCompanyIds($ticket) as $companyId) {
            $legacyDir = $this->mediaStorage->resolveLegacyWorkshopSupplierDir($companyId, $ticketId);
            $legacyStats = $this->mediaStorage->measureDirectory($legacyDir);
            $stats['files'] += $legacyStats['files'];
            $stats['bytes'] += $legacyStats['bytes'];
        }

        $issueReport = $ticket->getIssueReport();
        $issueFiles = 0;
        $issueBytes = 0;
        if ($issueReport instanceof ActivityIssueReport && ($issueReport->getPhotos() ?? []) !== []) {
            $issueDir = $this->mediaStorage->resolveContextDir(
                MediaStorageService::CONTEXT_ISSUE_REPORT,
                $departmentId,
                (string) $issueReport->getId(),
            );
            $issueStats = $this->mediaStorage->measureDirectory($issueDir);
            $issueFiles = $issueStats['files'];
            $issueBytes = $issueStats['bytes'];
        }

        return [
            'ticket_id' => $ticketId,
            'department_id' => $departmentId,
            'title' => $ticket->getTitle(),
            'completed_at' => $ticket->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'photo_count' => \count($ticket->getPhotos() ?? []),
            'files' => $stats['files'] + $issueFiles,
            'bytes' => $stats['bytes'] + $issueBytes,
            'has_issue_report_photos' => $issueFiles > 0 || ($issueReport?->getPhotos() ?? []) !== [],
        ];
    }

    /**
     * @return array{processed: bool, issue_reports: int, files: int, bytes: int}
     */
    private function cleanupTicket(WorkshopTicket $ticket): array
    {
        $departmentId = $ticket->getDepartmentId();
        $ticketId = (string) $ticket->getId();
        $files = 0;
        $bytes = 0;
        $issueReports = 0;
        $hadMedia = false;

        $workshopDir = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_WORKSHOP_TICKET,
            $departmentId,
            $ticketId,
        );
        $deleted = $this->mediaStorage->deleteDirectoryIfExists($workshopDir);
        $files += $deleted['files'];
        $bytes += $deleted['bytes'];
        $hadMedia = $hadMedia || $deleted['files'] > 0 || ($ticket->getPhotos() ?? []) !== [];

        foreach ($this->collectLegacySupplierCompanyIds($ticket) as $companyId) {
            $legacy = $this->mediaStorage->deleteLegacyWorkshopSupplierFolder($companyId, $ticketId);
            $files += $legacy['files'];
            $bytes += $legacy['bytes'];
            $hadMedia = $hadMedia || $legacy['files'] > 0;
        }

        if (($ticket->getPhotos() ?? []) !== []) {
            $ticket->setPhotos([]);
            $hadMedia = true;
        }

        $issueReport = $ticket->getIssueReport();
        if ($issueReport instanceof ActivityIssueReport && ($issueReport->getPhotos() ?? []) !== []) {
            $issueDir = $this->mediaStorage->resolveContextDir(
                MediaStorageService::CONTEXT_ISSUE_REPORT,
                $departmentId,
                (string) $issueReport->getId(),
            );
            $issueDeleted = $this->mediaStorage->deleteDirectoryIfExists($issueDir);
            $files += $issueDeleted['files'];
            $bytes += $issueDeleted['bytes'];
            $issueReport->setPhotos([]);
            $issueReport->syncPrimaryPhotoUrl();
            ++$issueReports;
            $hadMedia = true;
        }

        return [
            'processed' => $hadMedia,
            'issue_reports' => $issueReports,
            'files' => $files,
            'bytes' => $bytes,
        ];
    }

    /** @return list<string> */
    private function collectLegacySupplierCompanyIds(WorkshopTicket $ticket): array
    {
        $ids = [];
        foreach ($this->photoNormalizer->normalizeOutgoing($ticket->getPhotos()) as $photo) {
            $companyId = $this->photoNormalizer->resolveSupplierCompanyId($photo);
            if ($companyId !== null) {
                $ids[$companyId] = $companyId;
            }
        }

        $assigned = $ticket->getAssignedToSupplierCompanyId();
        if ($assigned !== null && $assigned !== '') {
            $ids[$assigned] = $assigned;
        }

        return array_values($ids);
    }

    private function writeLog(int $years, bool $dryRun, MediaRetentionRunResult $result): void
    {
        $logDir = $this->projectDir . '/var/log';
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $line = sprintf(
            "[%s] retention years=%d dry_run=%s matched=%d processed=%d issue_reports=%d files=%d bytes=%d mb=%.2f\n",
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            $years,
            $dryRun ? 'true' : 'false',
            $result->ticketsMatched,
            $result->ticketsProcessed,
            $result->issueReportsProcessed,
            $result->filesDeleted,
            $result->bytesFreed,
            $result->megabytesFreed(),
        );

        @file_put_contents($logDir . '/media_retention.log', $line, FILE_APPEND);
    }
}
