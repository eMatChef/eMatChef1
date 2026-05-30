<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierRepairTicketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies/{companyId}/repairs', name: 'api_supplier_repairs_')]
class SupplierRepairController extends AbstractController
{
    public function __construct(
        private SupplierCompanyAccessService $accessService,
        private SupplierRepairTicketService $repairTicketService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function list(string $companyId, Request $request): JsonResponse
    {
        try {
            $this->accessService->requireRepairsAccess($this->requireUser(), $companyId);
            $status = $request->query->get('status');
            $tickets = $this->repairTicketService->listTickets(
                $companyId,
                \is_string($status) ? $status : null
            );

            return new JsonResponse(['tickets' => $tickets]);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{ticketId}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function show(string $companyId, string $ticketId): JsonResponse
    {
        try {
            $this->accessService->requireRepairsAccess($this->requireUser(), $companyId);

            return new JsonResponse([
                'ticket' => $this->repairTicketService->getTicket($companyId, $ticketId),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    #[Route('/{ticketId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function update(string $companyId, string $ticketId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $this->accessService->requireRepairsAccess($this->requireUser(), $companyId);
            $ticket = $this->repairTicketService->updateTicket($companyId, $ticketId, $data);

            return new JsonResponse(['ticket' => $ticket, 'message' => 'Ticket aktualisiert']);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{ticketId}/transition', name: 'transition', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function transition(string $companyId, string $ticketId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $this->accessService->requireRepairsAccess($this->requireUser(), $companyId);
            $ticket = $this->repairTicketService->transitionTicket($companyId, $ticketId, $data);

            return new JsonResponse(['ticket' => $ticket, 'message' => 'Status aktualisiert']);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
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
