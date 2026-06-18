<?php

declare(strict_types=1);

namespace App\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\User;
use App\Service\Media\MediaPhotoNormalizer;
use App\Service\Media\MediaStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AccountingAcquisitionFollowUpReceiptService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingAcquisitionFollowUpReceiptStorageService $receiptStorage,
        private AccountingBookingReceiptStorageService $bookingReceiptStorage,
        private MediaPhotoNormalizer $photoNormalizer,
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array{receipts: list<array<string, mixed>>}
     */
    public function addReceipt(AccountingAcquisitionFollowUp $followUp, User $user, UploadedFile $file): array
    {
        if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
            throw new \InvalidArgumentException('Anschaffungs-Auftrag ist bereits erfasst');
        }

        $existing = $this->photoNormalizer->normalizeIncoming($followUp->getReceipts() ?? []);
        if (\count($existing) >= AccountingAcquisitionFollowUpReceiptStorageService::MAX_RECEIPTS) {
            throw new \InvalidArgumentException(sprintf(
                'Maximal %d Belege pro Anschaffungs-Auftrag',
                AccountingAcquisitionFollowUpReceiptStorageService::MAX_RECEIPTS,
            ));
        }

        $photo = $this->receiptStorage->store($followUp, $user, $file);
        $existing[] = $photo;
        $followUp->setReceipts($existing);
        $followUp->touchUpdatedAt();
        $this->entityManager->flush();

        return ['receipts' => $this->photoNormalizer->normalizeOutgoing($followUp->getReceipts())];
    }

    /**
     * @return array{receipts: list<array<string, mixed>>}
     */
    public function removeReceipt(AccountingAcquisitionFollowUp $followUp, string $filename): array
    {
        if ($followUp->getStatus() !== AccountingAcquisitionFollowUp::STATUS_PENDING) {
            throw new \InvalidArgumentException('Anschaffungs-Auftrag ist bereits erfasst');
        }

        $existing = $this->photoNormalizer->normalizeIncoming($followUp->getReceipts() ?? []);
        $kept = [];
        $removed = false;
        $departmentId = $followUp->getDepartment()->getId() ?? '';
        $followUpId = (string) $followUp->getId();

        foreach ($existing as $item) {
            if (($item['filename'] ?? '') === $filename) {
                $removed = true;
                $path = $this->receiptStorage->resolveFilePath($departmentId, $followUpId, $filename);
                @unlink($path);
                continue;
            }
            $kept[] = $item;
        }

        if (!$removed) {
            throw new \InvalidArgumentException('Beleg nicht gefunden');
        }

        $followUp->setReceipts($kept === [] ? null : $kept);
        $followUp->touchUpdatedAt();
        $this->entityManager->flush();

        return ['receipts' => $this->photoNormalizer->normalizeOutgoing($followUp->getReceipts())];
    }

    /**
     * Übernimmt Belege vom Follow-up in die erfasste Buchung (Dateien kopieren, URLs anpassen).
     */
    public function transferReceiptsToBooking(
        AccountingAcquisitionFollowUp $followUp,
        AccountingBooking $booking,
    ): void {
        $incoming = $this->photoNormalizer->normalizeIncoming($followUp->getReceipts() ?? []);
        if ($incoming === []) {
            return;
        }

        $departmentId = $followUp->getDepartment()->getId() ?? '';
        $followUpId = (string) $followUp->getId();
        $bookingId = (string) $booking->getId();

        $sourceDir = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_ACCOUNTING_FOLLOW_UP,
            $departmentId,
            $followUpId,
        );
        $targetDir = $this->mediaStorage->resolveContextDir(
            MediaStorageService::CONTEXT_ACCOUNTING_BOOKING,
            $departmentId,
            $bookingId,
        );

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden');
        }

        $copied = [];
        foreach ($incoming as $item) {
            $filename = (string) ($item['filename'] ?? '');
            if ($filename === '') {
                continue;
            }
            $sourcePath = $sourceDir . '/' . $filename;
            if (!is_file($sourcePath)) {
                continue;
            }
            $targetPath = $targetDir . '/' . $filename;
            if (!is_file($targetPath)) {
                if (!@copy($sourcePath, $targetPath)) {
                    continue;
                }
            }
            $item['url'] = $this->bookingReceiptStorage->buildReceiptUrl($departmentId, $bookingId, $filename);
            $item['context'] = MediaStorageService::CONTEXT_ACCOUNTING_BOOKING;
            $item['context_id'] = $bookingId;
            $copied[] = $item;
        }

        if ($copied !== []) {
            $booking->setReceipts($copied);
        }

        $this->receiptStorage->deleteAllForFollowUp($followUp);
        $followUp->setReceipts(null);
    }
}
