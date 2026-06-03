<?php

declare(strict_types=1);

namespace App\Service\Accounting;

use App\Entity\AccountingBooking;
use App\Entity\User;
use App\Service\Media\MediaPhotoNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AccountingBookingReceiptService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingBookingReceiptStorageService $receiptStorage,
        private MediaPhotoNormalizer $photoNormalizer,
    ) {
    }

    /**
     * @return array{receipts: list<array<string, mixed>>}
     */
    public function addReceipt(AccountingBooking $booking, User $user, UploadedFile $file): array
    {
        $existing = $this->photoNormalizer->normalizeIncoming($booking->getReceipts() ?? []);
        if (\count($existing) >= AccountingBookingReceiptStorageService::MAX_RECEIPTS) {
            throw new \InvalidArgumentException(sprintf(
                'Maximal %d Belege pro Buchung',
                AccountingBookingReceiptStorageService::MAX_RECEIPTS,
            ));
        }

        $photo = $this->receiptStorage->store($booking, $user, $file);
        $existing[] = $photo;
        $booking->setReceipts($existing);
        $booking->touchUpdatedAt();
        $this->entityManager->flush();

        return ['receipts' => $this->photoNormalizer->normalizeOutgoing($booking->getReceipts())];
    }

    /**
     * @return array{receipts: list<array<string, mixed>>}
     */
    public function removeReceipt(AccountingBooking $booking, string $filename): array
    {
        $existing = $this->photoNormalizer->normalizeIncoming($booking->getReceipts() ?? []);
        $kept = [];
        $removed = false;
        foreach ($existing as $item) {
            if (($item['filename'] ?? '') === $filename) {
                $removed = true;
                $path = $this->receiptStorage->resolveFilePath(
                    $booking->getDepartment()->getId() ?? '',
                    (string) $booking->getId(),
                    $filename,
                );
                @unlink($path);
                continue;
            }
            $kept[] = $item;
        }

        if (!$removed) {
            throw new \InvalidArgumentException('Beleg nicht gefunden');
        }

        $booking->setReceipts($kept === [] ? null : $kept);
        $booking->touchUpdatedAt();
        $this->entityManager->flush();

        return ['receipts' => $this->photoNormalizer->normalizeOutgoing($booking->getReceipts())];
    }
}
