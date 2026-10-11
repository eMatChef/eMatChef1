<?php

namespace App\Controller;

use App\Entity\Department;
use App\Entity\DepartmentCalendarPeriod;
use App\Entity\DepartmentSetting;
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
            'presets' => $this->presets($department),
        ];
    }

    /**
     * Fest konfigurierte Schnellwahl (Department-Einstellung `demo_clock_presets`, vom Demo-Seed gesetzt). Ungültige Einträge
     * werden verworfen; ohne gültige Einstellung gilt die abgeleitete Schnellwahl.
     *
     * @return list<array{key: string, at: string}>|null
     */
    private function configuredPresets(Department $department): ?array
    {
        $row = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy(['departmentId' => $department->getId(), 'settingKey' => DepartmentSetting::KEY_DEMO_CLOCK_PRESETS]);
        $raw = $row instanceof DepartmentSetting ? json_decode($row->getSettingValue(), true) : null;
        if (!\is_array($raw)) {
            return null;
        }
        $allowed = ['months_before_5', 'months_before_2', 'build', 'during_event', 'teardown', 'months_after_2'];
        $out = [];
        foreach ($raw as $item) {
            $key = \is_array($item) ? ($item['key'] ?? null) : null;
            $at = \is_array($item) ? ($item['at'] ?? null) : null;
            if (\is_string($key) && \in_array($key, $allowed, true) && \is_string($at) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $at)) {
                $out[] = ['key' => $key, 'at' => $at];
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Schnellwahl der Demo-Uhr, abgeleitet aus dem Anlasszeitraum und den fixen Daten (Aufbau, Abbau) der
     * Grossanlass-Konfiguration; nichts davon ist im Frontend hinterlegt. Alle Zeiten sind Wandzeit (lokale Veranstaltungszeit).
     * Departments ohne Grossanlass-Konfiguration haben keine Schnellwahl.
     *
     * @return list<array{key: string, at: string}>
     */
    private function presets(Department $department): array
    {
        $config = $department->getGrossanlassConfig();
        if ($config === null) {
            return [];
        }
        $configured = $this->configuredPresets($department);
        if ($configured !== null) {
            return $configured;
        }
        $start = \DateTimeImmutable::createFromInterface($config->getPlannedEventStart());
        $end = $config->getPlannedEventEnd() !== null ? \DateTimeImmutable::createFromInterface($config->getPlannedEventEnd()) : $start;
        $period = function (string $label) use ($department): ?DepartmentCalendarPeriod {
            $row = $this->entityManager->getRepository(DepartmentCalendarPeriod::class)->findOneBy(['departmentId' => $department->getId(), 'label' => $label]);

            return $row instanceof DepartmentCalendarPeriod ? $row : null;
        };
        $at = static fn (\DateTimeInterface $date, \DateTimeInterface $time): \DateTimeImmutable => new \DateTimeImmutable($date->format('Y-m-d') . ' ' . $time->format('H:i:s'));

        $presets = [
            ['key' => 'months_before_5', 'at' => $start->modify('-5 months')],
            ['key' => 'months_before_2', 'at' => $start->modify('-2 months')],
        ];
        $build = $period(DepartmentCalendarPeriod::LABEL_AUFBAU);
        if ($build !== null) {
            $presets[] = ['key' => 'build', 'at' => $at($build->getStartDate(), $build->getStartTime())];
        }
        $midDay = $start->setTime(0, 0)->modify(sprintf('+%d days', intdiv((int) $start->setTime(0, 0)->diff($end->setTime(0, 0))->days, 2)));
        $presets[] = ['key' => 'during_event', 'at' => $midDay->setTime(12, 0)];
        $teardown = $period(DepartmentCalendarPeriod::LABEL_ABBAU);
        $teardownDay = $teardown !== null ? $at($teardown->getStartDate(), $teardown->getStartTime()) : $end;
        $presets[] = ['key' => 'teardown', 'at' => $teardownDay->setTime(0, 0)->modify('+1 day')->setTime(9, 0)];
        $presets[] = ['key' => 'months_after_2', 'at' => $end->modify('+2 months')];

        return array_map(static fn (array $p): array => ['key' => $p['key'], 'at' => $p['at']->format(self::WALL_CLOCK)], $presets);
    }
}
