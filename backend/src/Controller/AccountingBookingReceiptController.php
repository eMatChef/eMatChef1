<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingBooking;
use App\Entity\User;
use App\Service\Accounting\AccountingBookingReceiptService;
use App\Service\Accounting\AccountingBookingReceiptStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/bookings/{bookingId}/receipts', name: 'api_accounting_booking_receipts_')]
class AccountingBookingReceiptController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingBookingReceiptService $receiptService,
        private AccountingBookingReceiptStorageService $receiptStorage,
    ) {
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(string $departmentId, string $bookingId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $file = $request->files->get('receipt');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'receipt ist erforderlich'], 400);
        }

        $booking = $this->requireBooking($departmentId, $bookingId);

        try {
            $result = $this->receiptService->addReceipt($booking, $this->requireUser(), $file);

            return new JsonResponse([...$result, 'message' => 'Beleg hochgeladen']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{filename}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(string $departmentId, string $bookingId, string $filename): Response
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $booking = $this->requireBooking($departmentId, $bookingId);

        try {
            $path = $this->receiptStorage->resolveFilePath($departmentId, $bookingId, $filename);
            $response = new BinaryFileResponse($path);
            $disposition = str_ends_with(strtolower($filename), '.pdf')
                ? ResponseHeaderBag::DISPOSITION_INLINE
                : ResponseHeaderBag::DISPOSITION_INLINE;
            $response->setContentDisposition($disposition, $filename);

            return $response;
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    #[Route('/{filename}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $bookingId, string $filename): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $booking = $this->requireBooking($departmentId, $bookingId);

        try {
            $result = $this->receiptService->removeReceipt($booking, $filename);

            return new JsonResponse($result);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    private function requireBooking(string $departmentId, string $bookingId): AccountingBooking
    {
        $booking = $this->entityManager->find(AccountingBooking::class, $bookingId);
        if (!$booking || $booking->getDepartment()->getId() !== $departmentId) {
            throw $this->createNotFoundException('Buchung nicht gefunden');
        }

        return $booking;
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
