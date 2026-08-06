<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\User;
use App\Service\Accounting\AccountingAcquisitionFollowUpReceiptService;
use App\Service\Accounting\AccountingAcquisitionFollowUpReceiptStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/acquisition-followups/{followUpId}/receipts', name: 'api_accounting_acquisition_followup_receipts_')]
class AccountingAcquisitionFollowUpReceiptController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountingAcquisitionFollowUpReceiptService $receiptService,
        private AccountingAcquisitionFollowUpReceiptStorageService $receiptStorage,
    ) {
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(string $departmentId, string $followUpId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $file = $request->files->get('receipt');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'receipt ist erforderlich'], 400);
        }

        $followUp = $this->requireFollowUp($departmentId, $followUpId);

        try {
            $result = $this->receiptService->addReceipt($followUp, $this->requireUser(), $file);

            return new JsonResponse([...$result, 'message' => 'Beleg hochgeladen']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{filename}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(string $departmentId, string $followUpId, string $filename): Response
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $this->requireFollowUp($departmentId, $followUpId);

        try {
            $path = $this->receiptStorage->resolveFilePath($departmentId, $followUpId, $filename);
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
    public function delete(string $departmentId, string $followUpId, string $filename): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $followUp = $this->requireFollowUp($departmentId, $followUpId);

        try {
            $result = $this->receiptService->removeReceipt($followUp, $filename);

            return new JsonResponse($result);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    private function requireFollowUp(string $departmentId, string $followUpId): AccountingAcquisitionFollowUp
    {
        $followUp = $this->entityManager->find(AccountingAcquisitionFollowUp::class, $followUpId);
        if (!$followUp || $followUp->getDepartment()->getId() !== $departmentId) {
            throw $this->createNotFoundException('Buchhaltungs-Auftrag nicht gefunden');
        }

        return $followUp;
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
