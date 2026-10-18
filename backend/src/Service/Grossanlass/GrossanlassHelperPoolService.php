<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassHelper;
use App\Entity\DepartmentGrossanlassHelperAvailability;
use App\Entity\DepartmentGrossanlassSkill;
use App\Entity\Membership;
use App\Entity\User;
use App\Entity\UserDriveLicense;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Helferpool eines Grossanlasses (V1): Helfer erfassen, ändern, archivieren und ihre Verfügbarkeit in expliziten
 * Zeitfenstern pflegen. Nur GA-HV/GA-SUBHV. Helfer mit Helferrolle (Benutzerverwaltung) erscheinen automatisch ({@see GrossanlassHelperAccountSync}); externe Helfer brauchen keinen Login; nur notwendige Personendaten.
 * Nicht zu verwechseln mit {@see GrossanlassHelperService} (Helfer als Benutzerkonto einladen).
 */
final class GrossanlassHelperPoolService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GrossanlassAccessService $access,
        private readonly GrossanlassStaffingLocks $locks,
        private readonly GrossanlassHelperAccountSync $accountSync,
    ) {
    }

    /**
     * @param array{status?: string, q?: string, origin_department_id?: string, limit?: int, offset?: int} $filter
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user, array $filter = []): array
    {
        $this->assertCanManage($department, $user);
        $this->accountSync->sync($department);
        $qb = $this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->createQueryBuilder('h')
            ->where('h.departmentId = :d')->setParameter('d', $department->getId())
            ->orderBy('h.lastName', 'ASC')->addOrderBy('h.firstName', 'ASC')
            ->setMaxResults(max(1, min(500, (int) ($filter['limit'] ?? 200))))
            ->setFirstResult(max(0, (int) ($filter['offset'] ?? 0)));
        $status = (string) ($filter['status'] ?? DepartmentGrossanlassHelper::STATUS_ACTIVE);
        if ($status !== 'all') {
            $qb->andWhere('h.status = :s')->setParameter('s', $status === DepartmentGrossanlassHelper::STATUS_ARCHIVED ? $status : DepartmentGrossanlassHelper::STATUS_ACTIVE);
        }
        if (($filter['origin_department_id'] ?? '') !== '') {
            $qb->andWhere('h.originDepartmentId = :o')->setParameter('o', (string) $filter['origin_department_id']);
        }
        $q = trim((string) ($filter['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere('LOWER(h.firstName) LIKE :q OR LOWER(h.lastName) LIKE :q OR LOWER(h.email) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(addcslashes($q, '%_\\')) . '%');
        }

        return array_map(fn (DepartmentGrossanlassHelper $h) => $this->serialize($h), $qb->getQuery()->getResult());
    }

    /** @return array<string, mixed> */
    public function get(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);
        $helper = $this->find($department, $helperId);

        return $this->serialize($helper) + ['availability' => $this->availabilityOf($helperId)];
    }

    /**
     * @param array<string, mixed> $data first_name, last_name (mindestens einer), email, phone, note, origin_department_id, source
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertCanManage($department, $user);
        $helper = new DepartmentGrossanlassHelper(
            GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER, DepartmentGrossanlassHelper::class),
            $department->getId(),
        );
        $this->apply($department, $helper, $data, true);
        $this->assertNoDuplicate($department, $helper);
        $this->entityManager->persist($helper);
        $this->entityManager->flush();

        return $this->serialize($helper);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $helperId, array $data): array
    {
        $this->assertCanManage($department, $user);
        $helper = $this->find($department, $helperId);
        $this->apply($department, $helper, $data, false);
        $this->assertNoDuplicate($department, $helper);
        $this->entityManager->flush();

        return $this->serialize($helper);
    }

    /** Archivieren statt löschen; bestehende Zuteilungen bleiben, neue sind nicht mehr möglich. @return array<string, mixed> */
    public function archive(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);

        return $this->locks->transactional(function () use ($department, $helperId): array {
            $helper = $this->locks->lockHelper($department->getId(), $helperId);
            $helper->archive();
            $this->entityManager->flush();

            return $this->serialize($helper);
        });
    }

    /** @return array<string, mixed> */
    public function restore(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);

        return $this->locks->transactional(function () use ($department, $helperId): array {
            $helper = $this->locks->lockHelper($department->getId(), $helperId);
            $helper->restore();
            $this->entityManager->flush();

            return $this->serialize($helper);
        });
    }

    /** @return list<array<string, mixed>> */
    public function listAvailability(Department $department, User $user, string $helperId): array
    {
        $this->assertCanManage($department, $user);
        $this->find($department, $helperId);

        return $this->availabilityOf($helperId);
    }

    /**
     * Neues Verfügbarkeitsfenster. Überlappungen mit vorhandenen Fenstern werden abgelehnt (direkt anschliessende sind erlaubt).
     *
     * @return array<string, mixed>
     */
    public function addAvailability(Department $department, User $user, string $helperId, mixed $from, mixed $to): array
    {
        $this->assertCanManage($department, $user);
        $start = GrossanlassStaffingTime::parse($from, 'starts_at');
        $end = GrossanlassStaffingTime::parse($to, 'ends_at');
        GrossanlassStaffingTime::assertWindow($start, $end);

        return $this->locks->transactional(function () use ($department, $helperId, $start, $end): array {
            $this->locks->lockHelper($department->getId(), $helperId);
            $this->assertNoOverlap($helperId, $start, $end, null);
            $row = new DepartmentGrossanlassHelperAvailability(
                GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER_AVAILABILITY, DepartmentGrossanlassHelperAvailability::class),
                $department->getId(),
                $helperId,
                $start,
                $end,
            );
            $this->entityManager->persist($row);
            $this->entityManager->flush();

            return $this->serializeAvailability($row);
        });
    }

    /**
     * Ändert ein Fenster. Aktive Zuteilungen müssen danach weiterhin von der Verfügbarkeit gedeckt sein (sonst Konflikt `in_use`).
     *
     * @return array<string, mixed>
     */
    public function updateAvailability(Department $department, User $user, string $availabilityId, mixed $from, mixed $to): array
    {
        $this->assertCanManage($department, $user);
        $start = GrossanlassStaffingTime::parse($from, 'starts_at');
        $end = GrossanlassStaffingTime::parse($to, 'ends_at');
        GrossanlassStaffingTime::assertWindow($start, $end);

        return $this->locks->transactional(function () use ($department, $availabilityId, $start, $end): array {
            $row = $this->findAvailability($department, $availabilityId);
            $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->assertNoOverlap($row->getHelperId(), $start, $end, $row->getId());
            $row->setWindow($start, $end);
            $this->entityManager->flush();
            $this->assertAssignmentsStillCovered($row->getHelperId());

            return $this->serializeAvailability($row);
        });
    }

    public function removeAvailability(Department $department, User $user, string $availabilityId): void
    {
        $this->assertCanManage($department, $user);
        $this->locks->transactional(function () use ($department, $availabilityId): void {
            $row = $this->findAvailability($department, $availabilityId);
            $this->locks->lockHelper($department->getId(), $row->getHelperId());
            $this->entityManager->remove($row);
            $this->entityManager->flush();
            $this->assertAssignmentsStillCovered($row->getHelperId());
        });
    }

    /** @param array<string, mixed> $data */
    private function apply(Department $department, DepartmentGrossanlassHelper $helper, array $data, bool $creating): void
    {
        $has = static fn (string $k): bool => array_key_exists($k, $data);
        if ($has('user_id')) {
            $this->link($department, $helper, trim((string) ($data['user_id'] ?? '')));
        }
        // Verknüpfte Helfer: Name und E-Mail kommen aus dem zentralen Profil und sind hier nicht änderbar.
        $linked = $helper->getUserId() !== null;
        if (!$linked && ($creating || $has('first_name'))) {
            $helper->setFirstName($this->text($data['first_name'] ?? '', 100, 'Vorname'));
        }
        if (!$linked && ($creating || $has('last_name'))) {
            $helper->setLastName($this->text($data['last_name'] ?? '', 100, 'Nachname'));
        }
        if ($helper->getFirstName() === '' && $helper->getLastName() === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        if (!$linked && $has('email')) {
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) {
                throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
            }
            $helper->setEmail($email === '' ? null : $email);
        }
        if ($has('phone')) {
            $phone = $this->text($data['phone'] ?? '', 40, 'Telefon');
            $helper->setPhone($phone === '' ? null : $phone);
        }
        if ($has('note')) {
            $note = trim((string) ($data['note'] ?? ''));
            if (mb_strlen($note) > 2000) {
                throw new \InvalidArgumentException('Notiz ist zu lang');
            }
            $helper->setNote($note === '' ? null : $note);
        }
        if ($has('origin_department_id')) {
            $origin = trim((string) ($data['origin_department_id'] ?? ''));
            if ($origin !== '' && $this->entityManager->find(Department::class, $origin) === null) {
                throw new \InvalidArgumentException('Herkunfts-Department nicht gefunden');
            }
            $helper->setOriginDepartmentId($origin === '' ? null : $origin);
        }
        if ($has('occupation')) {
            $occupation = $this->text($data['occupation'] ?? '', 120, 'Beruf');
            $helper->setOccupation($occupation === '' ? null : $occupation);
        }
        if ($has('area')) {
            $area = $this->text($data['area'] ?? '', 100, 'Ressort');
            $helper->setArea($area === '' ? null : $area);
        }
        if ($has('skills')) {
            $skills = $this->sanitizeSkills($data['skills']);
            $this->ensureCatalog($department, $skills);
            $helper->setSkills($skills);
        }
        if (!$linked && $has('drive_classes') && is_array($data['drive_classes'])) {
            $helper->setDriveClasses(GrossanlassDriveCategories::sanitize($data['drive_classes']));
        }
        if (!$linked && $has('drive_valid_until')) {
            $helper->setDriveValidUntil($this->date($data['drive_valid_until']));
        }
        if ($has('qualifications')) {
            $helper->setQualifications($this->sanitizeQualifications($data['qualifications']));
        }
        if ($creating && $has('source')) {
            $source = (string) $data['source'];
            if (!in_array($source, [DepartmentGrossanlassHelper::SOURCE_MANUAL, DepartmentGrossanlassHelper::SOURCE_IMPORT], true)) {
                throw new \InvalidArgumentException('Ungültige Quelle');
            }
            $helper->setSource($source);
        }
    }

    /**
     * Verknüpft den Helfer mit einem Konto, das Mitglied dieses Departments ist (jede Rolle). Lokale Angaben (Fähigkeiten,
     * Qualifikationen, Notiz) bleiben unverändert; Name und E-Mail kommen danach aus dem Profil. Eine Verknüpfung wird hier nicht gelöst.
     */
    private function link(Department $department, DepartmentGrossanlassHelper $helper, string $userId): void
    {
        if ($userId === '' || $userId === $helper->getUserId()) {
            return;
        }
        if ($helper->getUserId() !== null) {
            throw new \InvalidArgumentException('Helfer ist bereits mit einem Benutzer verknüpft');
        }
        $member = $this->entityManager->getRepository(Membership::class)->findOneBy(['departmentId' => $department->getId(), 'user' => $userId]);
        if (!$member instanceof Membership || $member->getUser()->getState() !== 'active') {
            throw new \InvalidArgumentException('Benutzer nicht gefunden');
        }
        $other = $this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->findOneBy(['departmentId' => $department->getId(), 'userId' => $userId]);
        if ($other instanceof DepartmentGrossanlassHelper && $other->getId() !== $helper->getId()) {
            throw new \InvalidArgumentException('Für diesen Benutzer existiert bereits ein Helfer');
        }
        $helper->setUserId($userId);
        // Neuer Helfer ohne eigenen Namen: Name aus dem Profil übernehmen (Anzeige kommt danach ohnehin aus dem Profil).
        $profile = $member->getUser()->getProfile();
        if ($profile !== null && $helper->getFirstName() === '' && $helper->getLastName() === '') {
            $helper->setFirstName(mb_substr((string) $profile->getFirstName(), 0, 100))->setLastName(mb_substr((string) $profile->getLastName(), 0, 100));
            if ($helper->getFirstName() === '' && $helper->getLastName() === '') {
                $helper->setFirstName(mb_substr($profile->getDisplayName(), 0, 100));
            }
        }
    }

    /** Keine zweite Helferentität für dieselbe Person: gleiche E-Mail (extern) bzw. gleiches Konto im Department. */
    private function assertNoDuplicate(Department $department, DepartmentGrossanlassHelper $helper): void
    {
        $email = $helper->getUserId() !== null ? strtolower($this->profileEmail($helper->getUserId()) ?? '') : strtolower((string) $helper->getEmail());
        if ($email === '') {
            return;
        }
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->createQueryBuilder('h')
            ->where('h.departmentId = :d')->andWhere('LOWER(h.email) = :e')->andWhere('h.id <> :id')
            ->setParameter('d', $department->getId())->setParameter('e', $email)->setParameter('id', $helper->getId())
            ->setMaxResults(1)->getQuery()->getResult();
        // Verknüpfte Helfer führen die E-Mail im Profil, nicht in der eigenen Spalte.
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->findBy(['departmentId' => $department->getId()]) as $other) {
            if ($other->getId() !== $helper->getId() && $other->getUserId() !== null && strtolower((string) $this->profileEmail($other->getUserId())) === $email) {
                $rows[] = $other;
            }
        }
        if ($rows !== []) {
            throw new \InvalidArgumentException('Ein Helfer mit dieser E-Mail-Adresse existiert bereits');
        }
    }

    private function profileEmail(string $userId): ?string
    {
        $user = $this->entityManager->find(User::class, $userId);

        return $user instanceof User ? $user->getProfile()?->getEmail() : null;
    }

    /** @return list<string> */
    private function sanitizeSkills(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Fähigkeiten müssen eine Liste sein');
        }
        $clean = [];
        $seen = [];
        foreach ($raw as $item) {
            $name = preg_replace('/\s+/u', ' ', trim((string) $item)) ?? '';
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 80) {
                throw new \InvalidArgumentException('Fähigkeit ist zu lang');
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $name;
        }
        if (count($clean) > 50) {
            throw new \InvalidArgumentException('Zu viele Fähigkeiten');
        }

        return $clean;
    }

    /** @return list<array{label: string, valid_until: ?string}> */
    private function sanitizeQualifications(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Qualifikationen müssen eine Liste sein');
        }
        $clean = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = $this->text($item['label'] ?? '', 120, 'Qualifikation');
            if ($label === '') {
                continue;
            }
            $until = $this->date($item['valid_until'] ?? null);
            $clean[] = ['label' => $label, 'valid_until' => $until?->format('Y-m-d')];
        }
        if (count($clean) > 30) {
            throw new \InvalidArgumentException('Zu viele Qualifikationen');
        }

        return $clean;
    }

    private function date(mixed $value): ?\DateTime
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new \InvalidArgumentException('Ungültiges Datum');
        }

        return $date;
    }

    /** Fehlende Fähigkeiten in den Katalog des Departments aufnehmen (Gross-/Kleinschreibung ignoriert). @param list<string> $names */
    private function ensureCatalog(Department $department, array $names): void
    {
        if ($names === []) {
            return;
        }
        $known = array_map('mb_strtolower', array_column($this->catalogRows($department->getId()), 'name'));
        foreach ($names as $name) {
            if (!in_array(mb_strtolower($name), $known, true)) {
                $this->entityManager->persist(new DepartmentGrossanlassSkill(
                    GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::SKILL, DepartmentGrossanlassSkill::class),
                    $department->getId(),
                    $name,
                ));
                $this->entityManager->flush();
                $known[] = mb_strtolower($name);
            }
        }
    }

    /** @return list<array{id: string, name: string}> */
    private function catalogRows(string $departmentId): array
    {
        return array_map(
            static fn (DepartmentGrossanlassSkill $s) => ['id' => $s->getId(), 'name' => $s->getName()],
            $this->entityManager->getRepository(DepartmentGrossanlassSkill::class)->findBy(['departmentId' => $departmentId], ['name' => 'ASC']),
        );
    }

    /**
     * Fähigkeitenkatalog des GA-Departments: eigene Einträge plus bereits verwendete Funktionswünsche des Personalbedarfs,
     * damit Helferprofile und Bedarfe aus derselben Liste wählen. @return list<string>
     */
    public function listSkills(Department $department): array
    {
        $this->access->assertGrossanlassDepartment($department); // Lesen: jedes Mitglied (Auswahl im Bedarfsdialog), Mitgliedschaft prüft der Controller
        $names = array_column($this->catalogRows($department->getId()), 'name');
        $labels = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT label FROM department_grossanlass_staffing_need WHERE department_id = ? AND label <> ''",
            [$department->getId()],
        );
        $byKey = [];
        foreach ([...$names, ...$labels] as $name) {
            $byKey[mb_strtolower((string) $name)] ??= (string) $name;
        }
        $all = array_values($byKey);
        usort($all, static fn (string $a, string $b) => strcasecmp($a, $b));

        return $all;
    }

    /** Neue Fähigkeit im Katalog erfassen (idempotent). @return list<string> */
    public function addSkill(Department $department, User $user, mixed $name): array
    {
        $this->assertCanManage($department, $user);
        $clean = $this->sanitizeSkills([$name]);
        if ($clean === []) {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        $this->ensureCatalog($department, $clean);

        return $this->listSkills($department);
    }

    /**
     * Benutzer des Departments, die mit einem Helfer verknüpft werden können (inkl. Hinweis auf bestehende Verknüpfung).
     *
     * @return list<array{user_id: string, name: string, first_name: string, last_name: string, email: string, helper_id: ?string}>
     */
    public function linkableUsers(Department $department, User $user): array
    {
        $this->assertCanManage($department, $user);
        $linked = [];
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassHelper::class)->findBy(['departmentId' => $department->getId()]) as $h) {
            if ($h->getUserId() !== null) {
                $linked[$h->getUserId()] = $h->getId();
            }
        }
        $out = [];
        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['departmentId' => $department->getId()]) as $m) {
            $u = $m->getUser();
            if ($u->getState() !== 'active' || $u->hasSuperAdminProfile() || isset($out[$u->getId()])) {
                continue;
            }
            $p = $u->getProfile();
            $out[$u->getId()] = ['user_id' => $u->getId(), 'name' => $p?->getLegalName() ?? '', 'first_name' => (string) ($p?->getFirstName() ?? ''), 'last_name' => (string) ($p?->getLastName() ?? ''), 'email' => $p?->getEmail() ?? '', 'helper_id' => $linked[$u->getId()] ?? null];
        }
        usort($out, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return array_values($out);
    }

    /** Führerausweis aus dem zentralen Profil (verknüpfte Konten, nur lesend) oder lokal am Helfer (extern). @return array<string, mixed> */
    private function driveOf(DepartmentGrossanlassHelper $h): array
    {
        if ($h->getUserId() !== null) {
            $license = $this->entityManager->find(UserDriveLicense::class, $h->getUserId());

            return [
                'classes' => $license instanceof UserDriveLicense ? $license->getDriveClasses() : [],
                'valid_until' => $license?->getValidUntil()?->format('Y-m-d'),
                'source' => 'profile',
            ];
        }

        return ['classes' => $h->getDriveClasses(), 'valid_until' => $h->getDriveValidUntil()?->format('Y-m-d'), 'source' => 'local'];
    }

    private function text(mixed $value, int $max, string $label): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) > $max) {
            throw new \InvalidArgumentException($label . ' ist zu lang');
        }

        return $text;
    }

    private function assertNoOverlap(string $helperId, \DateTimeInterface $start, \DateTimeInterface $end, ?string $exceptId): void
    {
        foreach ($this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helperId]) as $other) {
            if ($other->getId() !== $exceptId && GrossanlassStaffingTime::overlaps($start, $end, $other->getStartsAt(), $other->getEndsAt())) {
                throw new \InvalidArgumentException('Das Fenster überschneidet sich mit einer vorhandenen Verfügbarkeit');
            }
        }
    }

    /** Nach einer Änderung muss jede aktive Zuteilung des Helfers noch von seiner Verfügbarkeit gedeckt sein. */
    private function assertAssignmentsStillCovered(string $helperId): void
    {
        $availability = array_map(
            static fn (DepartmentGrossanlassHelperAvailability $a) => [$a->getStartsAt(), $a->getEndsAt()],
            $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)->findBy(['helperId' => $helperId]),
        );
        $uncovered = [];
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT id, starts_at, ends_at FROM department_grossanlass_staffing_assignment WHERE helper_id = ? AND status NOT IN ('cancelled', 'conflict')",
            [$helperId],
        );
        foreach ($rows as $row) {
            if (!GrossanlassStaffingTime::isCovered($availability, new \DateTime($row['starts_at']), new \DateTime($row['ends_at']))) {
                $uncovered[] = trim((string) $row['id']);
            }
        }
        if ($uncovered !== []) {
            throw new GrossanlassStaffingConflict(
                'Zuteilungen des Helfers wären nicht mehr durch seine Verfügbarkeit gedeckt',
                GrossanlassStaffingConflict::KIND_IN_USE,
                ['assignment_ids' => $uncovered],
            );
        }
    }

    private function find(Department $department, string $helperId): DepartmentGrossanlassHelper
    {
        $helper = $this->entityManager->find(DepartmentGrossanlassHelper::class, $helperId);
        if (!$helper instanceof DepartmentGrossanlassHelper || $helper->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Helfer nicht gefunden');
        }

        return $helper;
    }

    private function findAvailability(Department $department, string $id): DepartmentGrossanlassHelperAvailability
    {
        $row = $this->entityManager->find(DepartmentGrossanlassHelperAvailability::class, $id);
        if (!$row instanceof DepartmentGrossanlassHelperAvailability || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Verfügbarkeit nicht gefunden');
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function availabilityOf(string $helperId): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassHelperAvailability::class)
            ->findBy(['helperId' => $helperId], ['startsAt' => 'ASC']);

        return array_map(fn (DepartmentGrossanlassHelperAvailability $a) => $this->serializeAvailability($a), $rows);
    }

    /** @return array<string, mixed> */
    private function serializeAvailability(DepartmentGrossanlassHelperAvailability $a): array
    {
        return [
            'id' => $a->getId(),
            'helper_id' => $a->getHelperId(),
            'starts_at' => $a->getStartsAt()->format('Y-m-d\TH:i:s'),
            'ends_at' => $a->getEndsAt()->format('Y-m-d\TH:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(DepartmentGrossanlassHelper $h): array
    {
        $profile = $h->getUserId() !== null ? $this->entityManager->find(User::class, $h->getUserId())?->getProfile() : null;
        $first = $profile !== null ? (string) ($profile->getFirstName() ?? '') : $h->getFirstName();
        $last = $profile !== null ? (string) ($profile->getLastName() ?? '') : $h->getLastName();
        if ($profile !== null && $first === '' && $last === '') {
            $first = $profile->getDisplayName();
        }

        return [
            'id' => $h->getId(),
            'user_id' => $h->getUserId(),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $profile?->getEmail() ?? $h->getEmail(),
            'phone' => $h->getPhone(),
            'note' => $h->getNote(),
            'occupation' => $h->getOccupation(),
            'area' => $h->getArea(),
            'skills' => $h->getSkills(),
            'drive_license' => $this->driveOf($h),
            'qualifications' => $h->getQualifications(),
            'origin_department_id' => $h->getOriginDepartmentId(),
            'has_login' => $h->getUserId() !== null,
            'source' => $h->getSource(),
            'status' => $h->getStatus(),
            'archived_at' => $h->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertCanManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManageStaffing($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Helferpool');
        }
    }
}
