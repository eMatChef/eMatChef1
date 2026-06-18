<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\InventoryTask;
use App\Entity\Membership;
use App\Entity\User;
use App\Entity\WorkshopTicket;
use App\Service\Inventory\InventoryTaskValidator;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/inventory-tasks', name: 'api_inventory_tasks_')]
class InventoryTaskController extends AbstractController
{
    private const MANAGER_ROLES = ['mw', 'matwart', 'dc', 'depchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryTaskValidator $validator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        if (!\is_string($departmentId) || $departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $memberCheck = $this->requireMember($departmentId);
        if ($memberCheck instanceof JsonResponse) {
            return $memberCheck;
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(InventoryTask::class, 't')
            ->where('t.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('t.updatedAt', 'DESC');

        $status = $request->query->get('status');
        if (\is_string($status) && $status !== '') {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        $workshopTicketId = $request->query->get('workshop_ticket_id');
        if (\is_string($workshopTicketId) && $workshopTicketId !== '') {
            $qb->andWhere('t.workshopTicketId = :workshopTicketId')
                ->setParameter('workshopTicketId', $workshopTicketId);
        }

        $items = [];
        foreach ($qb->getQuery()->getResult() as $task) {
            if ($task instanceof InventoryTask) {
                $items[] = $this->serialize($task);
            }
        }

        return new JsonResponse(['tasks' => $items]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(string $id): JsonResponse
    {
        $task = $this->findTask($id);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $memberCheck = $this->requireMember($task->getDepartmentId());
        if ($memberCheck instanceof JsonResponse) {
            return $memberCheck;
        }

        return new JsonResponse(['task' => $this->serialize($task, true)]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId === '') {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            $title = $this->validator->validateTitle((string) ($data['title'] ?? ''));
            $linesJson = $this->validator->normalizeLinesJson(
                \is_array($data['lines_json'] ?? null) ? $data['lines_json'] : null,
            );
            $status = trim((string) ($data['status'] ?? InventoryTask::STATUS_OPEN));
            $this->validator->validateStatus($status);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $task = new InventoryTask();
        $task->setId(IdGenerator::generate12WithPrefix('iv'));
        $task->setDepartment($department);
        $task->setTitle($title);
        $task->setStatus($status);
        $task->setLinesJson($linesJson);
        $task->setCreatedByUserId($user->getId());

        $workshopTicketId = trim((string) ($data['workshop_ticket_id'] ?? ''));
        if ($workshopTicketId !== '') {
            $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find($workshopTicketId);
            if (!$ticket instanceof WorkshopTicket || $ticket->getDepartmentId() !== $departmentId) {
                return new JsonResponse(['error' => 'Workshop-Ticket nicht gefunden'], 404);
            }
            if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INSPECTION) {
                return new JsonResponse(['error' => 'Nur Inspektions-Tickets können verknüpft werden'], 400);
            }
            $task->setWorkshopTicket($ticket);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse(['task' => $this->serialize($task, true), 'message' => 'Inventur-Aufgabe erstellt'], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $task = $this->findTask($id);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $user = $this->requireManager($task->getDepartmentId());
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            if (\array_key_exists('title', $data)) {
                $task->setTitle($this->validator->validateTitle((string) $data['title']));
            }
            if (\array_key_exists('status', $data)) {
                $status = trim((string) $data['status']);
                $this->validator->validateStatus($status);
                $task->setStatus($status);
            }
            if (\array_key_exists('lines_json', $data)) {
                if (!\is_array($data['lines_json'])) {
                    throw new \InvalidArgumentException('lines_json muss ein Objekt sein');
                }
                $task->setLinesJson($this->validator->normalizeLinesJson($data['lines_json']));
            }
            if (\array_key_exists('workshop_ticket_id', $data)) {
                $workshopTicketId = $data['workshop_ticket_id'];
                if ($workshopTicketId === null || $workshopTicketId === '') {
                    $task->setWorkshopTicket(null);
                } else {
                    $ticket = $this->entityManager->getRepository(WorkshopTicket::class)->find((string) $workshopTicketId);
                    if (!$ticket instanceof WorkshopTicket || $ticket->getDepartmentId() !== $task->getDepartmentId()) {
                        return new JsonResponse(['error' => 'Workshop-Ticket nicht gefunden'], 404);
                    }
                    if ($ticket->getStrategy() !== WorkshopTicket::STRATEGY_INSPECTION) {
                        return new JsonResponse(['error' => 'Nur Inspektions-Tickets können verknüpft werden'], 400);
                    }
                    $task->setWorkshopTicket($ticket);
                }
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $task->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse(['task' => $this->serialize($task, true), 'message' => 'Inventur-Aufgabe aktualisiert']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $task = $this->findTask($id);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $managerCheck = $this->requireManager($task->getDepartmentId());
        if ($managerCheck instanceof JsonResponse) {
            return $managerCheck;
        }

        if ($task->getStatus() === InventoryTask::STATUS_COMPLETED) {
            return new JsonResponse(['error' => 'Abgeschlossene Inventur-Aufgaben können nicht gelöscht werden'], 400);
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Inventur-Aufgabe gelöscht']);
    }

    /** @return array<string, mixed> */
    private function serialize(InventoryTask $task, bool $detailed = false): array
    {
        $result = [
            'id' => $task->getId(),
            'department_id' => $task->getDepartmentId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus(),
            'status_label' => $task->getStatusLabel(),
            'workshop_ticket_id' => $task->getWorkshopTicketId(),
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($detailed) {
            $result['lines_json'] = $task->getLinesJson();
            $result['created_by_user_id'] = $task->getCreatedByUserId();
        }

        return $result;
    }

    private function findTask(string $id): InventoryTask|JsonResponse
    {
        $task = $this->entityManager->getRepository(InventoryTask::class)->find($id);
        if (!$task instanceof InventoryTask) {
            return new JsonResponse(['error' => 'Inventur-Aufgabe nicht gefunden'], 404);
        }

        return $task;
    }

    private function requireMember(string $departmentId): true|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        return true;
    }

    private function requireManager(string $departmentId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership || !$this->isManagerRole($membership->getRole())) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        return $user;
    }

    private function isManagerRole(string $role): bool
    {
        return \in_array(strtolower(trim($role)), self::MANAGER_ROLES, true);
    }
}
