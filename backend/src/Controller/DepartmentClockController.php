<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Service\GroupAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Department-Fachzeit (BusinessClock): Zustand lesen, Demo-/Dev-Zeitreise.
 *
 * Zeitreise nur für Mitglieder des Departments und nur, wenn das Department im Demo-Modus
 * (auch ohne Grossanlass) oder die Umgebung nicht prod ist — die Department-ID kommt aus der Route und wird gegen
 * die Mitgliedschaft geprüft, nie aus Frontend-State.
 */
#[Route('/api/departments/{departmentId}/clock', name: 'api_department_clock_')]
class DepartmentClockController extends AbstractController
{
    private const MAX_TRAVEL_YEARS = 5;

    /**
     * Wie alle Grossanlass-Zeiten (starts_at, planned_event_start …): naive Wandzeit ohne Zeitzone.
     * Die Fachzeit wird gegen diese Werte verglichen, daher darf `now` keine Zeitzone tragen.
     */
    private const WALL_CLOCK = 'Y-m-d\TH:i:s';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BusinessClock $clock,
        private GroupAccessService $groupAccess,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $departmentId): JsonResponse
    {
        $ctx = $this->resolve($departmentId);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        return new JsonResponse($this->describe($ctx));
    }

    #[Route('', name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function put(string $departmentId, Request $request): JsonResponse
    {
        $ctx = $this->resolve($departmentId, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $data = json_decode($request->getContent(), true);
        $raw = is_array($data) ? ($data['now'] ?? null) : null;
        if (!is_string($raw) || $raw === '') {
            return new JsonResponse(['error' => 'Feld "now" (Datum/Zeit) fehlt'], 400);
        }
        try {
            $target = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Ungültiges Datum'], 400);
        }
        $real = $this->clock->realNow();
        $limit = self::MAX_TRAVEL_YEARS * 366 * 86400;
        if (abs($target->getTimestamp() - $real->getTimestamp()) > $limit) {
            return new JsonResponse(['error' => 'Datum ausserhalb des erlaubten Bereichs'], 400);
        }

        $this->clock->travelTo($ctx, $target);
        $this->entityManager->flush();

        return new JsonResponse($this->describe($ctx));
    }

    #[Route('', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId): JsonResponse
    {
        $ctx = $this->resolve($departmentId, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $this->clock->reset($ctx);
        $this->entityManager->flush();

        return new JsonResponse($this->describe($ctx));
    }

    private function resolve(string $departmentId, bool $forTravel = false): Department|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }
        if (!$this->groupAccess->userHasDepartmentMembership($user->getId(), $departmentId)) {
            return new JsonResponse(['error' => 'Kein Zugriff auf diese Abteilung'], 403);
        }
        if ($forTravel && !$this->clock->supportsTravel($department)) {
            return new JsonResponse(['error' => 'Zeitreise ist für dieses Department nicht erlaubt'], 403);
        }

        return $department;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Department $department): array
    {
        $real = $this->clock->realNow();
        $now = $this->clock->now($department);

        return [
            'mode' => $this->clock->mode($department),
            'now' => $now->format(self::WALL_CLOCK),
            'real_now' => $real->format(self::WALL_CLOCK),
            'offset_seconds' => $this->clock->offsetSeconds($department),
            'can_travel' => $this->clock->supportsTravel($department),
        ];
    }
}
