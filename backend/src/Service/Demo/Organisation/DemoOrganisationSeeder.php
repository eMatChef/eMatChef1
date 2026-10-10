<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

use App\Entity\DemoSeedRecord;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\DepartmentGrossanlassParticipant;
use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Service\Auth\TotpService;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoSupplierSeedService;
use App\Service\Demo\Scenario\DemoScenarioInterface;
use App\Service\Demo\Scenario\DemoSeedLedger;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Demo\Scenario\SeedResult;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use App\Util\DemoAccounts;
use App\Util\DemoUserNames;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt die Organisationsstruktur eines Demo-Szenarios aus dem Seed-Katalog an und hält sie aktuell:
 * Organisation → Department → Gruppen → Benutzer, Mitgliedschaften, Gruppenmitgliedschaften.
 *
 * Schutzregeln (siehe docs/demo/SEED-KONZEPT.md §7.11):
 *  - Identität über Ledger-Schlüssel, nie über Anzeigenamen; Namen dienen nur als Fundstelle bei der einmaligen
 *    Übernahme innerhalb eines bereits nachweislich eigenen Departments.
 *  - Bestehende Benutzer werden nur übernommen, wenn die Adresse exakt im Katalog steht und alle ihre
 *    Mitgliedschaften zu Demo-Departments gehören. Sonst Konflikt, nichts wird verändert.
 *  - Demo-Benutzer sind reine Testdaten ohne Bezug zu echten Identitäten. Sie gehören keinem Szenario (Ledger-Bereich
 *    «demo-users»), dürfen in mehreren Demo-Departments Mitglied sein und überleben jeden Szenario-Reset.
 *  - Passwörter, Zustände, globale Rollen und Clock-Offsets werden nur bei Neuanlage gesetzt.
 *  - Nichts wird gelöscht; Katalog-Abweichungen werden gemeldet.
 */
class DemoOrganisationSeeder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagedSeedApplier $applier,
        private DemoOrganisationCatalog $catalog,
        private UserPasswordHasherInterface $passwordHasher,
        private TotpService $totpService,
        private DemoGrossanlassSeedService $grossanlassSeed,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
        private DemoSupplierSeedService $supplierSeed,
    ) {
    }

    public function sync(DemoScenarioInterface $scenario, SeedContext $context): SeedResult
    {
        $key = $scenario->key();
        $cat = $this->catalog->scenario($key);
        $version = $this->catalog->version();

        if ($context->isDryRun()) {
            return $this->plan($key, $cat, $context);
        }

        $report = new SyncReport();
        $expected = [];

        // Gemeinsame Demo-Benutzer: nur anlegen/ergänzen, nie einem Szenario zugeordnet, nie gelöscht.
        $shared = $context->sharedUsers();
        foreach ($this->catalog->sharedAccounts() as $account) {
            $this->ensureSharedAccount($shared, $report, $account, $version);
        }
        $users = [];
        foreach ($cat['members'] as $member) {
            $users[$member['account']] = $this->ensureUser($shared, $report, $member['account'], $version);
        }
        // Orgchef/Suborgchef brauchen keine Mitgliedschaft im Szenario, aber das Konto für den Verwaltungsbereich.
        foreach ($cat['adminScopes'] ?? [] as $scope) {
            $users[$scope['account']] ??= $this->ensureUser($shared, $report, $scope['account'], $version);
        }

        $department = $context->department();
        if ($department === null) {
            $owner = null;
            foreach ($users as $user) {
                if ($user instanceof User) {
                    $owner = $user;
                    break;
                }
            }
            if ($owner === null) {
                $report->warnings[] = 'Department nicht angelegt: kein Benutzer des Szenarios verfügbar (siehe Konflikte).';

                return SeedResult::ok('Department nicht angelegt', $report->created, $report->unchanged, $report->notes());
            }
            $organisation = $this->ensureOrganisation($context, $report, $key, $cat['organisation']['name'], $version);
            $expected[] = $key . ':organisation';
            $department = $this->createDepartment($scenario, $organisation, $owner, $cat['department']['name']);
            $department->setDemoScenarioKey($key);
            $this->entityManager->flush();
            $context = $context->withDepartment($department);
        } elseif ($context->findRecord($key . ':organisation') !== null) {
            $expected[] = $key . ':organisation';
        }

        $this->ensureDepartmentRecord($context, $report, $key, $department, $cat['department']['name'], $version);
        $expected[] = $key . ':department';

        if (isset($cat['grossanlass']) && $scenario->expectsGrossanlass()) {
            $expected[] = $key . ':config';
            $this->ensureGrossanlassConfig($context, $report, $key, $department, $cat['grossanlass'], $users, $version);
        }

        $departments = [DemoOrganisationCatalog::MAIN => $department];
        foreach ($cat['departments'] ?? [] as $def) {
            $expected[] = $key . ':department:' . $def['key'];
            $extra = $this->ensureExtraDepartment($context, $report, $key, $department, $def, $departments, $version);
            if ($extra instanceof Department) {
                $departments[$def['key']] = $extra;
            }
        }
        if (isset($cat['department']['parent'])) {
            $expected[] = $key . ':department-parent';
            $this->ensureMainParent($context, $report, $key, $department, $departments[$cat['department']['parent']] ?? null, $version);
        }

        $groups = [];
        foreach ($cat['groups'] as $def) {
            $expected[] = $key . ':group:' . $def['key'];
            $groups[$def['key']] = $this->ensureGroup($context, $report, $key, $department, $def, $groups, $version);
        }

        // Früher fälschlich gesäte Mitgliedschaften zuerst entfernen (nur unverändert vom Seed), damit die
        // Primär-Prüfung der folgenden Mitgliedschaften den bereinigten Stand sieht.
        foreach ($cat['retiredMembers'] ?? [] as $retired) {
            $this->retireMembership($context, $report, $key, $departments, $retired);
            // Bewusst im Katalog behandelt: bleibt sie bestehen (manuell geändert), genügt der Hinweis, keine «Verwaist»-Meldung.
            $expected[] = self::membershipKey($key, $retired['department'] ?? null, (string) $retired['account']);
        }

        foreach ($cat['members'] as $member) {
            $user = $users[$member['account']] ?? null;
            $target = $departments[$member['department'] ?? DemoOrganisationCatalog::MAIN] ?? null;
            if (!$user instanceof User || !$target instanceof Department) {
                continue;
            }
            $membershipKey = self::membershipKey($key, $member['department'] ?? null, $member['account']);
            $expected[] = $membershipKey;
            $this->ensureMembership($context, $report, $membershipKey, $target, $user, $member, $version);
            foreach ($member['groups'] ?? [] as $gm) {
                $group = $groups[$gm['group']] ?? null;
                $expected[] = $key . ':groupmember:' . $member['account'] . ':' . $gm['group'];
                if ($group instanceof Group) {
                    $this->ensureGroupMembership($context, $report, $key, $user, $group, $member['account'], $gm, $version);
                }
            }
        }

        foreach ($cat['participants'] ?? [] as $participantKey) {
            $guest = $departments[$participantKey] ?? null;
            $expected[] = $key . ':participant:' . $participantKey;
            if ($guest instanceof Department) {
                $this->ensureParticipant($context, $report, $key, $participantKey, $department, $guest, $version);
            }
        }

        foreach ($cat['adminScopes'] ?? [] as $scope) {
            $user = $users[$scope['account']] ?? null;
            $rootIds = [];
            foreach ($scope['roots'] ?? [] as $rootKey) {
                if (($departments[$rootKey] ?? null) instanceof Department) {
                    $rootIds[] = $departments[$rootKey]->getId();
                }
            }
            $organisationIds = ($scope['organisation'] ?? false) === true ? [$department->getOrganisationId()] : [];
            $expected[] = $key . ':adminscope:' . $scope['account'];
            if ($user instanceof User && \count($rootIds) === \count($scope['roots'] ?? [])) {
                $universe = array_merge([$department->getOrganisationId()], array_map(static fn (Department $d): string => $d->getId(), array_values($departments)));
                $this->ensureAdminScope($context, $report, $key, $scope['account'], $user, $rootIds, $organisationIds, $universe, $version);
            }
        }

        $this->ensureLogisticsGroup($department, $cat['groups'], $groups);
        $this->entityManager->flush();

        foreach ($context->records() as $record) {
            if (!\in_array($record->getSeedKey(), $expected, true)) {
                $report->orphans[] = $record->getSeedKey();
            }
        }

        return SeedResult::ok($report->summary(), $report->created + $report->recreated, $report->unchanged + $report->updated + $report->adopted, $report->notes());
    }

    /** @return list<string> Verstösse (fehlende Seed-Daten, kaputte Struktur); Abweichungen sind keine Verstösse */
    public function verify(DemoScenarioInterface $scenario, SeedContext $context): array
    {
        $department = $context->department();
        if ($department === null) {
            return [];
        }
        $key = $scenario->key();
        $cat = $this->catalog->scenario($key);
        $violations = [];

        $shared = $context->sharedUsers();
        foreach ($cat['members'] as $m) {
            $userRecord = $shared->findRecord(DemoSeedLedger::SHARED_USERS . ':user:' . $m['account']);
            if ($userRecord === null || !$this->entityExists($userRecord)) {
                $violations[] = sprintf('Demo-Benutzer «%s» fehlt (Sync legt ihn an).', $m['account']);
            }
        }

        foreach ($cat['adminScopes'] ?? [] as $scope) {
            $userRecord = $shared->findRecord(DemoSeedLedger::SHARED_USERS . ':user:' . $scope['account']);
            if ($userRecord === null || !$this->entityExists($userRecord)) {
                $violations[] = sprintf('Demo-Benutzer «%s» fehlt (Sync legt ihn an).', $scope['account']);
            }
        }

        $required = [$key . ':department'];
        foreach ($cat['departments'] ?? [] as $d) {
            $required[] = $key . ':department:' . $d['key'];
        }
        foreach ($cat['participants'] ?? [] as $participantKey) {
            $required[] = $key . ':participant:' . $participantKey;
        }
        foreach ($cat['adminScopes'] ?? [] as $scope) {
            $required[] = $key . ':adminscope:' . $scope['account'];
        }
        foreach ($cat['groups'] as $g) {
            $required[] = $key . ':group:' . $g['key'];
        }
        foreach ($cat['members'] as $m) {
            $required[] = self::membershipKey($key, $m['department'] ?? null, $m['account']);
            foreach ($m['groups'] ?? [] as $gm) {
                $required[] = $key . ':groupmember:' . $m['account'] . ':' . $gm['group'];
            }
        }

        $records = [];
        foreach ($context->records() as $r) {
            $records[$r->getSeedKey()] = $r;
        }
        foreach ($required as $seedKey) {
            $record = $records[$seedKey] ?? null;
            if ($record === null) {
                $violations[] = sprintf('Seed-Eintrag «%s» fehlt (Sync ergänzt ihn).', $seedKey);
            } elseif (!$this->entityExists($record)) {
                $violations[] = sprintf('Datensatz zu «%s» existiert nicht mehr (Sync legt ihn neu an).', $seedKey);
            }
        }

        foreach ($cat['groups'] as $g) {
            $record = $records[$key . ':group:' . $g['key']] ?? null;
            $group = $record ? $this->entityManager->find(Group::class, $record->getEntityId()) : null;
            if ($group instanceof Group && $group->getDepartmentId() !== $department->getId()) {
                $violations[] = sprintf('Gruppe «%s» liegt nicht im Szenario-Department.', $g['key']);
            }
        }
        foreach ($cat['members'] as $m) {
            $record = $records[self::membershipKey($key, $m['department'] ?? null, $m['account'])] ?? null;
            $expectedDepartmentId = $department->getId();
            if (isset($m['department'])) {
                $expectedDepartmentId = ($records[$key . ':department:' . $m['department']] ?? null)?->getEntityId();
            }
            if ($record !== null && ($expectedDepartmentId === null || !str_ends_with($record->getEntityId(), ':' . $expectedDepartmentId))) {
                $violations[] = sprintf('Mitgliedschaft von «%s» liegt nicht im vorgesehenen Department.', $m['account']);
            }
            if (($m['role'] ?? '') === 'mw' && \in_array(DemoAccounts::accountByKey($m['account'])['role'] ?? '', ['sa', 'org', 'sub'], true)) {
                $violations[] = sprintf('«%s» hat eine globale Rolle und darf keine automatische MW-Mitgliedschaft haben.', $m['account']);
            }
        }

        return $violations;
    }

    /** @param array<string, mixed> $cat */
    private function plan(string $key, array $cat, SeedContext $context): SeedResult
    {
        $seedKeys = [$key . ':organisation', $key . ':department'];
        $sharedKeys = [];
        foreach ($cat['departments'] ?? [] as $d) {
            $seedKeys[] = $key . ':department:' . $d['key'];
        }
        foreach ($cat['participants'] ?? [] as $participantKey) {
            $seedKeys[] = $key . ':participant:' . $participantKey;
        }
        foreach ($cat['adminScopes'] ?? [] as $scope) {
            $seedKeys[] = $key . ':adminscope:' . $scope['account'];
            $sharedKeys[] = DemoSeedLedger::SHARED_USERS . ':user:' . $scope['account'];
        }
        foreach ($cat['groups'] as $g) {
            $seedKeys[] = $key . ':group:' . $g['key'];
        }
        foreach ($cat['members'] as $m) {
            $sharedKeys[] = DemoSeedLedger::SHARED_USERS . ':user:' . $m['account'];
            $seedKeys[] = self::membershipKey($key, $m['department'] ?? null, $m['account']);
            foreach ($m['groups'] ?? [] as $gm) {
                $seedKeys[] = $key . ':groupmember:' . $m['account'] . ':' . $gm['group'];
            }
        }
        $missing = array_values(array_filter($seedKeys, fn (string $k): bool => $context->findRecord($k) === null));
        $shared = $context->sharedUsers();
        foreach (array_unique(array_merge($sharedKeys, array_map(static fn (string $a): string => DemoSeedLedger::SHARED_USERS . ':user:' . $a, $this->catalog->sharedAccounts()))) as $k) {
            $seedKeys[] = $k;
            if ($shared->findRecord($k) === null) {
                $missing[] = $k;
            }
        }

        return SeedResult::ok(
            sprintf('Dry-Run: %d Einträge fehlen (würden angelegt oder übernommen), %d vorhanden.', \count($missing), \count($seedKeys) - \count($missing)),
            0,
            \count($seedKeys) - \count($missing),
            \array_map(static fn (string $k): string => 'fehlt: ' . $k, $missing),
        );
    }

    private function ensureOrganisation(SeedContext $context, SyncReport $report, string $key, string $name, string $version): Organisation
    {
        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':organisation',
            create: function (): Organisation {
                $o = new Organisation();
                $o->setId(IdGenerator::generateUnique($this->entityManager, Organisation::class));

                return $o;
            },
            find: fn (string $id): ?object => $this->entityManager->find(Organisation::class, $id),
            idOf: static fn (object $o): string => (string) $o->getId(),
            read: static fn (object $o): array => ['name' => $o->getName()],
            write: static function (object $o, array $d): void {
                $o->setName((string) $d['name']);
            },
            desired: ['name' => $name],
            global: true,
        ), $version);

        return $entity instanceof Organisation ? $entity : throw new \LogicException('Organisation konnte nicht angelegt werden');
    }

    private function createDepartment(DemoScenarioInterface $scenario, Organisation $organisation, User $owner, string $name): Department
    {
        if ($scenario->expectsGrossanlass()) {
            // Bestehender Grossanlass-Rahmen (Config, Kalender, Haupt-Aktivität, Kostenstellen, Uhr am Ausgangspunkt)
            return $this->grossanlassSeed->ensureDepartment($organisation, $owner, $name);
        }

        return $this->createPlainDepartment($organisation, $name);
    }

    /** Normales Demo-Department (kein Grossanlass-Rahmen, kein Szenario-Schlüssel). */
    private function createPlainDepartment(Organisation $organisation, string $name): Department
    {
        $department = new Department();
        $department->setId(IdGenerator::generateUnique($this->entityManager, Department::class));
        $department->setName($name);
        $department->setOrganisation($organisation);
        $department->setDemoMode(true);
        $this->entityManager->persist($department);
        $this->entityManager->flush();
        $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($this->entityManager, $department);
        $this->workshopSparePartsCategoryBootstrap->ensure($department);

        return $department;
    }

    /**
     * Zusätzliches Demo-Department der Hierarchie (z. B. Kantonalverband, Gast-Abteilung): gleiche Organisation wie das
     * Szenario-Department, demo_mode, aber ohne Szenario-Schlüssel (der gehört genau einem Department je Szenario).
     * Eigentum ausschliesslich über den Ledger-Eintrag; vorhandene gleichnamige Demo-Departments werden nur übernommen,
     * wenn sie in derselben Organisation liegen, demo_mode haben und noch keinem Eintrag gehören.
     *
     * @param array<string, mixed>       $def
     * @param array<string, Department>  $departments
     */
    private function ensureExtraDepartment(SeedContext $context, SyncReport $report, string $key, Department $main, array $def, array $departments, string $version): ?Department
    {
        $parent = isset($def['parent']) ? ($departments[$def['parent']] ?? null) : null;
        if (isset($def['parent']) && !$parent instanceof Department) {
            $report->warnings[] = sprintf('Department «%s» übersprungen: Eltern-Department fehlt.', $def['key']);

            return null;
        }
        $organisation = $main->getOrganisation();

        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':department:' . $def['key'],
            create: fn (): Department => $this->createPlainDepartment($organisation, (string) $def['name']),
            find: fn (string $id): ?object => $this->entityManager->find(Department::class, $id),
            idOf: static fn (object $d): string => (string) $d->getId(),
            read: static fn (object $d): array => ['name' => $d->getName(), 'parent_id' => $d->getParentId()],
            write: function (object $d, array $v): void {
                $d->setName((string) $v['name']);
                $d->setParent($v['parent_id'] !== null ? $this->entityManager->find(Department::class, $v['parent_id']) : null);
            },
            desired: ['name' => (string) $def['name'], 'parent_id' => $parent?->getId()],
            adopt: fn (): ?object => $this->adoptExtraDepartment($organisation, (string) $def['name']),
        ), $version);

        return $entity instanceof Department ? $entity : null;
    }

    private function adoptExtraDepartment(Organisation $organisation, string $name): ?Department
    {
        $department = $this->entityManager->getRepository(Department::class)->findOneBy(['organisationId' => $organisation->getId(), 'name' => $name]);
        if (!$department instanceof Department || !$department->isDemoMode() || $department->getDemoScenarioKey() !== null) {
            return null;
        }
        $taken = $this->entityManager->getRepository(DemoSeedRecord::class)->findOneBy(['entityClass' => Department::class, 'entityId' => $department->getId()]);

        return $taken === null ? $department : null;
    }

    /**
     * Parent des Szenario-Departments (Hierarchie). Ein bereits anders gesetzter Parent wird nie überschrieben,
     * sondern als Konflikt gemeldet.
     */
    private function ensureMainParent(SeedContext $context, SyncReport $report, string $key, Department $main, ?Department $parent, string $version): void
    {
        if (!$parent instanceof Department) {
            $report->warnings[] = 'Parent des Szenario-Departments nicht gesetzt: Eltern-Department fehlt.';

            return;
        }
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':department-parent',
            create: static fn (): object => throw new \LogicException('Das Szenario-Department wird nie neu angelegt'),
            find: fn (string $id): ?object => $this->entityManager->find(Department::class, $id),
            idOf: static fn (object $d): string => (string) $d->getId(),
            read: static fn (object $d): array => ['parent_id' => $d->getParentId()],
            write: function (object $d, array $v): void {
                $d->setParent($v['parent_id'] !== null ? $this->entityManager->find(Department::class, $v['parent_id']) : null);
            },
            desired: ['parent_id' => $parent->getId()],
            adopt: static function () use ($main, $parent): object {
                if ($main->getParentId() !== null && $main->getParentId() !== $parent->getId()) {
                    throw new OwnershipConflictException(sprintf('«%s» hat bereits einen anderen Parent; er wird nicht verändert.', $main->getName()));
                }

                return $main;
            },
        ), $version);
    }

    private function ensureDepartmentRecord(SeedContext $context, SyncReport $report, string $key, Department $department, string $name, string $version): void
    {
        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':department',
            create: static fn (): object => throw new \LogicException('Department wird vor dem Ledger angelegt'),
            find: fn (string $id): ?object => $this->entityManager->find(Department::class, $id),
            idOf: static fn (object $d): string => (string) $d->getId(),
            read: static fn (object $d): array => ['name' => $d->getName()],
            write: static function (object $d, array $v): void {
                $d->setName((string) $v['name']);
            },
            desired: ['name' => $name],
            // Das Department trägt bereits den Szenario-Schlüssel und demo_mode (Eigentum nachgewiesen).
            adopt: static fn (): object => $department,
            resettable: true,
        ), $version);
    }

    private function ensureUser(SeedContext $context, SyncReport $report, string $account, string $version): ?User
    {
        $def = DemoAccounts::accountByKey($account);
        $email = $def['email'];
        $first = DemoUserNames::firstNameForEmail($email);

        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: DemoSeedLedger::SHARED_USERS . ':user:' . $account,
            create: function () use ($email, $def): User {
                $profile = new Profile();
                $profile->setId(IdGenerator::generateUnique($this->entityManager, Profile::class));
                $profile->setEmail($email);
                $profile->setRoles($this->profileRoles((string) $def['role']));
                $this->entityManager->persist($profile);

                $user = new User();
                $user->setId(IdGenerator::generateUnique($this->entityManager, User::class));
                $user->setProfileId($profile->getId());
                $user->setProfile($profile);
                $user->setState('active');
                $user->setEmailVerified(true);
                $user->setPassword($this->passwordHasher->hashPassword($user, DemoAccounts::password()));

                return $user;
            },
            find: fn (string $id): ?object => $this->entityManager->find(User::class, $id),
            idOf: static fn (object $u): string => (string) $u->getId(),
            read: static fn (object $u): array => [
                'first_name' => $u->getProfile()?->getFirstName(),
                'last_name' => $u->getProfile()?->getLastName(),
                'nickname' => $u->getProfile()?->getNickname(),
            ],
            write: static function (object $u, array $d): void {
                $u->getProfile()?->setFirstName($d['first_name'])->setLastName($d['last_name'])->setNickname($d['nickname']);
            },
            desired: ['first_name' => $first, 'last_name' => (string) $def['label'], 'nickname' => $first],
            global: true,
            adopt: fn (): ?object => $this->adoptUser($email, $report),
            afterCreate: function (object $u) use ($email): void {
                // Test-TOTP der globalen Demo-Admins nur bei Neuanlage.
                $secret = DemoAccounts::totpSecretForEmail($email);
                if ($secret !== null && $u instanceof User) {
                    $this->totpService->provisionFixedSecret($u, $secret);
                }
            },
        ), $version);

        return $entity instanceof User ? $entity : null;
    }

    /**
     * Übernahme eines bestehenden Kontos nur mit eindeutigem Nachweis: exakte Katalogadresse auf der reservierten
     * Demo-Domain und entweder (a) Passwort = öffentliches Demo-Passwort oder (b) Mitgliedschaften ausschliesslich in
     * Demo-Departments (oder keine). Mitgliedschaften in fremden Departments werden nie verändert (nur gemeldet).
     * Passwort und Zustand werden nie angefasst.
     */
    private function adoptUser(string $email, SyncReport $report): ?User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if (!$profile instanceof Profile) {
            return null;
        }
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
        if (!$user instanceof User) {
            throw new OwnershipConflictException(sprintf('Profil «%s» existiert ohne Benutzer.', $email));
        }
        $foreign = [];
        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['userId' => $user->getId()]) as $membership) {
            $dept = $this->entityManager->find(Department::class, $membership->getDepartmentId());
            if (!$dept instanceof Department || !$dept->isDemoMode()) {
                $foreign[] = $dept?->getName() ?? $membership->getDepartmentId();
            }
        }
        if ($foreign !== []) {
            if (!$this->passwordHasher->isPasswordValid($user, DemoAccounts::password())) {
                throw new OwnershipConflictException(sprintf('«%s» ist Mitglied von «%s» (kein Demo-Department) und hat nicht das Demo-Passwort; Benutzer wird nicht übernommen.', $email, implode(', ', $foreign)));
            }
            $report->warnings[] = sprintf('«%s» bleibt unverändert Mitglied von «%s» (kein Demo-Department).', $email, implode(', ', $foreign));
        }

        return $user;
    }

    /**
     * Konten ohne Department-Mitgliedschaft (Lieferant). Bei Neuanlage über den bestehenden Seed-Dienst
     * (Benutzer, Testfirma, Mitgliedschaft); ein vorhandenes Konto wird nur verbucht, nie verändert.
     */
    private function ensureSharedAccount(SeedContext $shared, SyncReport $report, string $account, string $version): void
    {
        $email = DemoAccounts::email($account);
        $this->applier->ensure($shared, $report, new ManagedSpec(
            seedKey: DemoSeedLedger::SHARED_USERS . ':user:' . $account,
            create: function (): User {
                $created = $this->supplierSeed->ensure(null);

                return $created;
            },
            find: fn (string $id): ?object => $this->entityManager->find(User::class, $id),
            idOf: static fn (object $u): string => (string) $u->getId(),
            read: static fn (object $u): array => [],
            write: static function (object $u, array $d): void {
            },
            desired: [],
            global: true,
            adopt: fn (): ?object => $this->adoptUser($email, $report),
        ), $version);
    }

    /**
     * @param array<string, mixed>        $def
     * @param array<string, Group|null>   $groups
     */
    private function ensureGroup(SeedContext $context, SyncReport $report, string $key, Department $department, array $def, array $groups, string $version): ?Group
    {
        $parent = isset($def['parent']) ? ($groups[$def['parent']] ?? null) : null;
        if (isset($def['parent']) && !$parent instanceof Group) {
            $report->warnings[] = sprintf('Gruppe «%s» übersprungen: Elterngruppe fehlt.', $def['key']);

            return null;
        }
        $parentId = $parent?->getId();

        $entity = $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':group:' . $def['key'],
            create: function () use ($department): Group {
                $g = new Group();
                $g->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, Group::class, 'grp'));
                $g->setDepartment($department);

                return $g;
            },
            find: fn (string $id): ?object => $this->entityManager->find(Group::class, $id),
            idOf: static fn (object $g): string => (string) $g->getId(),
            read: static fn (object $g): array => [
                'name' => $g->getName(),
                'parent_id' => $g->getParentId(),
                'sort' => $g->getSortOrder(),
                'kind' => $g->getGrossanlassKind(),
                'description' => $g->getDescription(),
            ],
            write: function (object $g, array $d): void {
                $g->setName((string) $d['name']);
                $g->setParent($d['parent_id'] !== null ? $this->entityManager->find(Group::class, $d['parent_id']) : null);
                $g->setSortOrder((int) $d['sort']);
                $g->setGrossanlassKind($d['kind']);
                $g->setDescription($d['description']);
            },
            desired: [
                'name' => (string) $def['name'],
                'parent_id' => $parentId,
                'sort' => (int) $def['sort'],
                'kind' => $def['kind'] ?? null,
                'description' => $def['description'] ?? null,
            ],
            // Einmalige Übernahme einer gleichnamigen, noch nicht verbuchten Gruppe im bereits eigenen Department.
            adopt: fn (): ?object => $this->adoptGroup($department, (string) $def['name'], $parentId),
        ), $version);

        return $entity instanceof Group ? $entity : null;
    }

    private function adoptGroup(Department $department, string $name, ?string $parentId): ?Group
    {
        $group = $this->entityManager->getRepository(Group::class)->findOneBy(['departmentId' => $department->getId(), 'name' => $name, 'parentId' => $parentId]);
        if (!$group instanceof Group) {
            return null;
        }
        $taken = $this->entityManager->getRepository(DemoSeedRecord::class)->findOneBy(['entityClass' => Group::class, 'entityId' => $group->getId()]);

        return $taken === null ? $group : null;
    }

    /** @param array<string, mixed> $member */
    private function ensureMembership(SeedContext $context, SyncReport $report, string $seedKey, Department $department, User $user, array $member, string $version): void
    {
        $primary = (bool) ($member['primary'] ?? false) && !$this->hasOtherPrimary($user, $department);
        $find = function (string $id) use ($department): ?object {
            [$userId] = explode(':', $id, 2);

            return $this->entityManager->getRepository(Membership::class)->findOneBy(['userId' => $userId, 'departmentId' => $department->getId()]);
        };

        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $seedKey,
            create: static function () use ($user, $department): Membership {
                $m = new Membership();
                $m->setUser($user);
                $m->setDepartment($department);

                return $m;
            },
            find: $find,
            idOf: static fn (object $m): string => $m->getUserId() . ':' . $m->getDepartmentId(),
            read: static fn (object $m): array => ['role' => $m->getRole(), 'is_primary' => $m->getIsPrimary()],
            write: static function (object $m, array $d): void {
                $m->setRole((string) $d['role']);
                $m->setIsPrimary((bool) $d['is_primary']);
            },
            desired: ['role' => (string) $member['role'], 'is_primary' => $primary],
            adopt: fn (): ?object => $find($user->getId() . ':'),
            resettable: true,
        ), $version);
    }

    /**
     * @param array<string, mixed> $gm
     */
    private function ensureGroupMembership(SeedContext $context, SyncReport $report, string $key, User $user, Group $group, string $account, array $gm, string $version): void
    {
        $find = fn (string $id): ?object => $this->entityManager->getRepository(GroupMembership::class)->findOneBy(['userId' => $user->getId(), 'groupId' => $group->getId()]);

        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':groupmember:' . $account . ':' . $gm['group'],
            create: static function () use ($user, $group): GroupMembership {
                $row = new GroupMembership();
                $row->setUser($user);
                $row->setGroup($group);

                return $row;
            },
            find: $find,
            idOf: static fn (object $r): string => $r->getUserId() . ':' . $r->getGroupId(),
            read: static fn (object $r): array => ['role' => $r->getRole(), 'is_primary' => $r->getIsPrimary()],
            write: static function (object $r, array $d): void {
                $r->setRole((string) $d['role']);
                $r->setIsPrimary((bool) $d['is_primary']);
            },
            desired: ['role' => (string) $gm['role'], 'is_primary' => (bool) ($gm['primary'] ?? false)],
            adopt: fn (): ?object => $find(''),
        ), $version);
    }

    /**
     * GA-Typ (Lager/Event) und Einrichtungsstand des Grossanlasses. Dreiwege-Vergleich wie alle verwalteten Daten: eine von
     * Hand freigegebene (oder geänderte) Einrichtung wird nicht zurückgesetzt, sondern als Abweichung gemeldet. Ein
     * vorhandenes Department ohne Ledger-Eintrag wird nur übernommen, wenn der Typ noch der unveränderte Standard ist.
     *
     * @param array<string, mixed>     $def   {guestActivityType: camp|event, setupReleased: bool}
     * @param array<string, User|null> $users
     */
    private function ensureGrossanlassConfig(SeedContext $context, SyncReport $report, string $key, Department $department, array $def, array $users, string $version): void
    {
        $config = $department->getGrossanlassConfig();
        if (!$config instanceof DepartmentGrossanlassConfig) {
            $report->warnings[] = sprintf('Grossanlass-Konfiguration von «%s» fehlt; GA-Typ und Einrichtungsstand nicht gesetzt.', $key);

            return;
        }
        $desired = [
            'guest_activity_type' => (string) $def['guestActivityType'],
            'setup_released' => (bool) ($def['setupReleased'] ?? true),
        ];
        $owner = null;
        foreach ($users as $user) {
            if ($user instanceof User) {
                $owner = $user;
                break;
            }
        }

        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':config',
            create: static fn (): object => throw new \LogicException('Die Grossanlass-Konfiguration wird mit dem Department angelegt.'),
            find: fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassConfig::class, $id),
            idOf: static fn (object $c): string => (string) $c->getDepartmentId(),
            read: static fn (object $c): array => [
                'guest_activity_type' => $c->getGuestActivityType(),
                'setup_released' => $c->isSetupReleased(),
            ],
            write: static function (object $c, array $d) use ($owner): void {
                $c->setGuestActivityType((string) $d['guest_activity_type']);
                if ((bool) $d['setup_released'] && !$c->isSetupReleased()) {
                    $c->setSetupReleased(new \DateTime(), $owner?->getId());
                } elseif (!(bool) $d['setup_released'] && $c->isSetupReleased()) {
                    $c->setSetupReleased(null);
                }
            },
            desired: $desired,
            adopt: static function () use ($config, $desired): object {
                if ($config->getGuestActivityType() !== $desired['guest_activity_type']
                    && $config->getGuestActivityType() !== DepartmentGrossanlassConfig::GUEST_ACTIVITY_CAMP
                ) {
                    throw new OwnershipConflictException('Der GA-Typ wurde manuell geändert und wird nicht überschrieben.');
                }

                return $config;
            },
            resettable: true,
        ), $version);
    }

    /**
     * @param list<array<string, mixed>> $defs
     * @param array<string, Group|null>  $groups
     */
    private function ensureLogisticsGroup(Department $department, array $defs, array $groups): void
    {
        $config = $department->getGrossanlassConfig();
        if ($config === null || $config->getLogisticsGroup() !== null) {
            return; // einmalig setzen, nie überschreiben
        }
        foreach ($defs as $def) {
            if (($def['logistics'] ?? false) && ($groups[$def['key']] ?? null) instanceof Group) {
                $config->setLogisticsGroup($groups[$def['key']]);

                return;
            }
        }
    }

    /** Seed-Schlüssel einer Mitgliedschaft; im Szenario-Department unverändert (bestehende Ledger-Einträge bleiben gültig). */
    public static function membershipKey(string $scenarioKey, ?string $departmentKey, string $account): string
    {
        return $departmentKey === null || $departmentKey === DemoOrganisationCatalog::MAIN
            ? $scenarioKey . ':membership:' . $account
            : $scenarioKey . ':membership:' . $departmentKey . ':' . $account;
    }

    /**
     * Entfernt eine früher fälschlich gesäte Mitgliedschaft (z. B. automatische MW-Rolle eines Superadmins), aber nur,
     * wenn der Ledger-Eintrag sie als vom Seed geschrieben ausweist und sie seither unverändert ist und keine
     * Gruppenmitgliedschaften im Department hat. Alles andere bleibt unangetastet und wird gemeldet.
     *
     * @param array<string, Department> $departments
     * @param array<string, mixed>      $retired
     */
    private function retireMembership(SeedContext $context, SyncReport $report, string $key, array $departments, array $retired): void
    {
        $departmentKey = $retired['department'] ?? DemoOrganisationCatalog::MAIN;
        $seedKey = self::membershipKey($key, $departmentKey, (string) $retired['account']);
        $record = $context->findRecord($seedKey);
        if ($record === null) {
            return; // nie vom Seed angelegt: nichts zu bereinigen
        }
        [$userId, $departmentId] = array_pad(explode(':', $record->getEntityId(), 2), 2, '');
        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);
        if ($membership instanceof Membership) {
            $current = ManagedSeedApplier::hash(['role' => $membership->getRole(), 'is_primary' => $membership->getIsPrimary()]);
            if ($record->getManagedHash() !== $current) {
                $report->warnings[] = sprintf('Mitgliedschaft «%s» ist nicht mehr im Katalog, wurde aber manuell geändert und bleibt bestehen.', $seedKey);

                return;
            }
            $department = $departments[$departmentKey] ?? null;
            $hasGroups = $department instanceof Department && $this->entityManager->getRepository(GroupMembership::class)->createQueryBuilder('gm')
                ->innerJoin('gm.group', 'g')
                ->where('gm.userId = :user')->andWhere('g.departmentId = :department')
                ->setParameter('user', $userId)->setParameter('department', $department->getId())
                ->select('count(gm.userId)')->getQuery()->getSingleScalarResult() > 0;
            if ($hasGroups) {
                $report->warnings[] = sprintf('Mitgliedschaft «%s» ist nicht mehr im Katalog, hat aber Gruppenmitgliedschaften und bleibt bestehen.', $seedKey);

                return;
            }
            $this->entityManager->remove($membership);
        }
        $this->entityManager->remove($record);
        $this->entityManager->flush();
        ++$report->retired;
    }

    /** Gast-Department eines Grossanlasses: angenommene Teilnahme (Planungsdaten, kein Prozessablauf). */
    private function ensureParticipant(SeedContext $context, SyncReport $report, string $key, string $participantKey, Department $host, Department $guest, string $version): void
    {
        if (!$host->isGrossanlass()) {
            $report->warnings[] = sprintf('Teilnehmer «%s» übersprungen: «%s» ist kein Grossanlass.', $guest->getName(), $host->getName());

            return;
        }
        $find = fn (string $id): ?object => $this->entityManager->find(DepartmentGrossanlassParticipant::class, $id);

        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':participant:' . $participantKey,
            create: function () use ($host, $guest): DepartmentGrossanlassParticipant {
                $row = new DepartmentGrossanlassParticipant();
                $row->setId(IdGenerator::generateUnique($this->entityManager, DepartmentGrossanlassParticipant::class));
                $row->setHostDepartment($host);
                $row->setGuestDepartment($guest);
                $row->setInvitedAt(new \DateTime());
                $row->setDecidedAt(new \DateTime());

                return $row;
            },
            find: $find,
            idOf: static fn (object $r): string => (string) $r->getId(),
            read: static fn (object $r): array => ['status' => $r->getStatus()],
            write: static function (object $r, array $v): void {
                $r->setStatus((string) $v['status']);
            },
            desired: ['status' => DepartmentGrossanlassParticipant::STATUS_ACCEPTED],
            adopt: fn (): ?object => $this->entityManager->getRepository(DepartmentGrossanlassParticipant::class)->findOneBy(['hostDepartmentId' => $host->getId(), 'guestDepartmentId' => $guest->getId()]),
        ), $version);
    }

    /**
     * Verwaltungszuständigkeit von Orgchef/Suborgchef: Department-Wurzeln (Unterbaum inklusive) und/oder die Organisation
     * des Szenarios im Admin-Scope des Profils. Ein Profil kann Scopes mehrerer Szenarien tragen; jedes Szenario verwaltet
     * nur die Einträge innerhalb seines eigenen Bereichs ($universe: seine Organisation und Departments), fremde und
     * manuell ergänzte Einträge bleiben unberührt. Ein dort bereits abweichend gesetzter Eintrag wird nie überschrieben.
     *
     * @param list<string> $rootIds
     * @param list<string> $organisationIds
     * @param list<string> $universe
     */
    private function ensureAdminScope(SeedContext $context, SyncReport $report, string $key, string $account, User $user, array $rootIds, array $organisationIds, array $universe, string $version): void
    {
        $profile = $user->getProfile();
        if (!$profile instanceof Profile) {
            return;
        }
        sort($rootIds);
        sort($organisationIds);
        $ownIds = array_flip($universe);
        $read = static function (object $p) use ($ownIds): array {
            $scope = (array) ($p->getAdminCapabilities()['scope'] ?? []);
            $own = static function (string $field) use ($scope, $ownIds): array {
                $ids = array_values(array_filter(array_map('strval', (array) ($scope[$field] ?? [])), static fn (string $id): bool => isset($ownIds[$id])));
                sort($ids);

                return $ids;
            };

            return ['department_root_ids' => $own('department_root_ids'), 'organisation_ids' => $own('organisation_ids')];
        };

        $this->applier->ensure($context, $report, new ManagedSpec(
            seedKey: $key . ':adminscope:' . $account,
            create: static fn (): object => throw new \LogicException('Das Profil wird nie über den Admin-Scope angelegt'),
            find: fn (string $id): ?object => $this->entityManager->find(Profile::class, $id),
            idOf: static fn (object $p): string => (string) $p->getId(),
            read: $read,
            write: static function (object $p, array $v) use ($ownIds): void {
                $caps = $p->getAdminCapabilities() ?? [];
                $scope = \is_array($caps['scope'] ?? null) ? $caps['scope'] : [];
                foreach (['department_root_ids', 'organisation_ids'] as $field) {
                    $foreign = array_filter(array_map('strval', (array) ($scope[$field] ?? [])), static fn (string $id): bool => !isset($ownIds[$id]));
                    $scope[$field] = array_values(array_unique(array_merge($foreign, $v[$field])));
                }
                $caps['scope'] = $scope;
                $p->setAdminCapabilities($caps);
            },
            desired: ['department_root_ids' => $rootIds, 'organisation_ids' => $organisationIds],
            global: true,
            adopt: static function () use ($profile, $read, $rootIds, $organisationIds): object {
                $current = $read($profile);
                $empty = $current['department_root_ids'] === [] && $current['organisation_ids'] === [];
                if (!$empty && ($current['department_root_ids'] !== $rootIds || $current['organisation_ids'] !== $organisationIds)) {
                    throw new OwnershipConflictException(sprintf('«%s» hat bereits einen anderen Verwaltungsbereich in dieser Demo-Organisation; er wird nicht verändert.', $profile->getEmail()));
                }

                return $profile;
            },
        ), $version);
    }

    private function hasOtherPrimary(User $user, Department $department): bool
    {
        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['userId' => $user->getId(), 'isPrimary' => true]) as $other) {
            if ($other->getDepartmentId() !== $department->getId()) {
                return true;
            }
        }

        return false;
    }

    private function entityExists(DemoSeedRecord $record): bool
    {
        $id = $record->getEntityId();

        return match ($record->getEntityClass()) {
            Membership::class => $this->entityManager->getRepository(Membership::class)->findOneBy(['userId' => explode(':', $id)[0], 'departmentId' => explode(':', $id)[1] ?? '']) !== null,
            GroupMembership::class => $this->entityManager->getRepository(GroupMembership::class)->findOneBy(['userId' => explode(':', $id)[0], 'groupId' => explode(':', $id)[1] ?? '']) !== null,
            default => $this->entityManager->find($record->getEntityClass(), $id) !== null,
        };
    }

    /** @return list<string> */
    private function profileRoles(string $role): array
    {
        return match ($role) {
            'sa' => ['ROLE_USER', 'ROLE_SUPERADMIN', 'ROLE_WEBADMIN'],
            'org' => ['ROLE_USER', 'ROLE_ORGANISATIONSCHEF'],
            'sub' => ['ROLE_USER', 'ROLE_SUBORGCHEF'],
            default => ['ROLE_USER'],
        };
    }
}
