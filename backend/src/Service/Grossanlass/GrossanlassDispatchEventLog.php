<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassDispatchEvent;
use App\Entity\User;
use App\Service\Clock\BusinessClock;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/** Nachvollziehbares Protokoll der Disposition (Freigabe, manuelle Ablieferung, Restfahrten, Entscheide). */
final class GrossanlassDispatchEventLog
{
    public function __construct(private EntityManagerInterface $entityManager, private ?BusinessClock $clock = null) {}

    public function record(Department $department, ?User $user, string $subjectType, string $subjectId, string $action, string $note = ''): void
    {
        $event = new DepartmentGrossanlassDispatchEvent();
        $event->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::DISPATCH_EVENT, DepartmentGrossanlassDispatchEvent::class));
        $event->setDepartment($department)->setSubject($subjectType, $subjectId)->setUserId($user?->getId())->setAction($action)->setNote(mb_substr($note, 0, 1000));
        if ($this->clock !== null) {
            $event->setCreatedAt($this->clock->now($department)); // Fachzeit: im Demo-Department die simulierte Zeit
        }
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    /** @return list<array{action: string, note: string, at: string, user: string}> */
    public function forSubject(Department $department, string $subjectType, string $subjectId, int $limit = 20): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassDispatchEvent::class)
            ->findBy(['departmentId' => $department->getId(), 'subjectType' => $subjectType, 'subjectId' => $subjectId], ['createdAt' => 'DESC'], $limit);
        $out = [];
        foreach ($rows as $row) {
            $user = $row->getUserId() !== null ? $this->entityManager->getRepository(User::class)->find($row->getUserId()) : null;
            $out[] = ['action' => $row->getAction(), 'note' => $row->getNote(), 'at' => $row->getCreatedAt()->format('Y-m-d\TH:i:s'), 'user' => $user instanceof User ? ($user->getProfile()?->getDisplayName() ?? '') : ''];
        }

        return $out;
    }
}
