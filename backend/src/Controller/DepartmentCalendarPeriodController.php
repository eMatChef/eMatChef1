<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/calendar-periods', name: 'api_department_calendar_periods_')]
class DepartmentCalendarPeriodController extends AbstractController
{
    private const MANAGER_ROLES = ['mw', 'matwart', 'dc', 'depchef'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId, Request $request): JsonResponse
    {
        $memberCheck = $this->requireMember($departmentId);
        if ($memberCheck instanceof JsonResponse) {
            return $memberCheck;
        }

        $qb = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)
            ->createQueryBuilder('p')
            ->where('p.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('p.startDate', 'DESC')
            ->addOrderBy('p.name', 'ASC');

        $years = $this->parseYearsParam($request->query->get('years'));
        if ($years !== null) {
            $min = min($years);
            $max = max($years);
            $qb
                ->andWhere('p.endDate >= :rangeStart')
                ->andWhere('p.startDate <= :rangeEnd')
                ->setParameter('rangeStart', sprintf('%d-01-01', $min))
                ->setParameter('rangeEnd', sprintf('%d-12-31', $max));
        }

        /** @var list<DepartmentCalendarPeriod> $periods */
        $periods = $qb->getQuery()->getResult();

        return new JsonResponse(array_map(fn (DepartmentCalendarPeriod $p) => $this->serialize($p), $periods));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        try {
            [$label, $name, $start, $end, $startTime, $endTime] = $this->parsePayload($data, true);
            $this->assertLabelAllowedForDepartment($department, $label);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $period = new DepartmentCalendarPeriod();
        $period->setId(IdGenerator::generate());
        $period->setDepartmentId($departmentId);
        $period->setLabel($label);
        $period->setName($name);
        $period->setStartDate($start);
        $period->setEndDate($end);
        $period->setStartTime($startTime);
        $period->setEndTime($endTime);
        $period->setCreatedByUserId($user->getId());
        $period->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($period);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($period), 201);
    }

    #[Route('/{periodId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $periodId, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $period = $this->findPeriod($departmentId, $periodId);
        if ($period instanceof JsonResponse) {
            return $period;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        try {
            [$label, $name, $start, $end, $startTime, $endTime] = $this->parsePayload(
                array_merge([
                    'label' => $period->getLabel(),
                    'name' => $period->getName(),
                    'start_date' => $period->getStartDate()->format('Y-m-d'),
                    'end_date' => $period->getEndDate()->format('Y-m-d'),
                    'start_time' => $period->getStartTime()->format('H:i'),
                    'end_time' => $period->getEndTime()->format('H:i'),
                ], $data),
                true,
            );
            $this->assertLabelAllowedForDepartment($department, $label);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $period->setLabel($label);
        $period->setName($name);
        $period->setStartDate($start);
        $period->setEndDate($end);
        $period->setStartTime($startTime);
        $period->setEndTime($endTime);
        $period->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return new JsonResponse($this->serialize($period));
    }

    #[Route('/{periodId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $periodId): JsonResponse
    {
        $managerCheck = $this->requireManager($departmentId);
        if ($managerCheck instanceof JsonResponse) {
            return $managerCheck;
        }

        $period = $this->findPeriod($departmentId, $periodId);
        if ($period instanceof JsonResponse) {
            return $period;
        }

        $this->entityManager->remove($period);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

  /**
   * @return array<string, mixed>
   */
    private function serialize(DepartmentCalendarPeriod $period): array
    {
        return [
            'id' => $period->getId(),
            'department_id' => $period->getDepartmentId(),
            'label' => $period->getLabel(),
            'name' => $period->getName(),
            'start_date' => $period->getStartDate()->format('Y-m-d'),
            'end_date' => $period->getEndDate()->format('Y-m-d'),
            'start_time' => $period->getStartTime()->format('H:i'),
            'end_time' => $period->getEndTime()->format('H:i'),
            'created_by_user_id' => $period->getCreatedByUserId(),
            'created_at' => $period->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $period->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: string, 1: string, 2: \DateTimeImmutable, 3: \DateTimeImmutable, 4: \DateTimeImmutable, 5: \DateTimeImmutable}
     */
    private function parsePayload(array $data, bool $requireAll): array
    {
        $label = isset($data['label']) ? trim((string) $data['label']) : '';
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $startRaw = isset($data['start_date']) ? trim((string) $data['start_date']) : '';
        $endRaw = isset($data['end_date']) ? trim((string) $data['end_date']) : '';
        $startTimeRaw = isset($data['start_time']) ? trim((string) $data['start_time']) : '00:00';
        $endTimeRaw = isset($data['end_time']) ? trim((string) $data['end_time']) : '23:59';

        if ($requireAll && ($label === '' || $name === '' || $startRaw === '' || $endRaw === '')) {
            throw new \InvalidArgumentException('label, name, start_date und end_date sind erforderlich');
        }

        if ($label !== '' && !\in_array($label, DepartmentCalendarPeriod::ALLOWED_LABELS, true)) {
            throw new \InvalidArgumentException('Ungültige Art (label)');
        }

        if ($name !== '' && mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Name darf maximal 120 Zeichen lang sein');
        }

        $start = $this->parseDate($startRaw, 'start_date');
        $end = $this->parseDate($endRaw, 'end_date');
        $startTime = $this->parseTime($startTimeRaw, 'start_time');
        $endTime = $this->parseTime($endTimeRaw, 'end_time');

        $startAt = $start->setTime((int) $startTime->format('H'), (int) $startTime->format('i'), 0);
        $endAt = $end->setTime((int) $endTime->format('H'), (int) $endTime->format('i'), 0);
        if ($startAt > $endAt) {
            throw new \InvalidArgumentException('Beginn darf nicht nach Ende liegen');
        }

        return [$label, $name, $start, $end, $startTime, $endTime];
    }

    private function assertLabelAllowedForDepartment(Department $department, string $label): void
    {
        $isGaModule = \in_array($label, DepartmentCalendarPeriod::GROSSANLASS_MODULE_LABELS, true);
        if ($isGaModule && !$department->isGrossanlass()) {
            throw new \InvalidArgumentException('Dieses Zeitmodul ist nur in Grossanlass-Departments erlaubt');
        }
        if ($department->isGrossanlass()) {
            if (\in_array($label, [DepartmentCalendarPeriod::LABEL_SCHOOL_VACATION, DepartmentCalendarPeriod::LABEL_CAMP_WEEK], true)) {
                throw new \InvalidArgumentException('Schulferien und Lagerwoche sind in Grossanlass-Departments nicht verfügbar');
            }
        }
    }

    private function parseDate(string $raw, string $field): \DateTimeImmutable
    {
        if ($raw === '') {
            throw new \InvalidArgumentException($field.' ist erforderlich');
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if (!$d || $d->format('Y-m-d') !== $raw) {
            throw new \InvalidArgumentException($field.' muss YYYY-MM-DD sein');
        }

        return $d;
    }

    private function parseTime(string $raw, string $field): \DateTimeImmutable
    {
        if ($raw === '') {
            throw new \InvalidArgumentException($field.' ist erforderlich');
        }
        $normalized = strlen($raw) === 5 ? $raw.':00' : $raw;
        $t = \DateTimeImmutable::createFromFormat('H:i:s', $normalized);
        if (!$t || $t->format('H:i:s') !== $normalized) {
            $t = \DateTimeImmutable::createFromFormat('H:i', $raw);
        }
        if (!$t) {
            throw new \InvalidArgumentException($field.' muss HH:MM sein');
        }

        return $t;
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

    private function findPeriod(string $departmentId, string $periodId): DepartmentCalendarPeriod|JsonResponse
    {
        $period = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->find($periodId);
        if (!$period || $period->getDepartmentId() !== $departmentId) {
            return new JsonResponse(['error' => 'Zeitraum nicht gefunden'], 404);
        }

        return $period;
    }

    /**
     * @return list<int>|null
     */
    private function parseYearsParam(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $n = (int) $p;
            if ($n >= 2000 && $n <= 2050) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out !== [] ? $out : null;
    }
}
