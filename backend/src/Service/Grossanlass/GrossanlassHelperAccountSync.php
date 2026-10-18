<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassHelper;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gleicht die Helferrolle der GA-Benutzerverwaltung (`membership.is_helper` oder Rolle `h`) mit dem Helferpool ab.
 * Aktive Konten mit Helferrolle erhalten genau einen Pool-Eintrag (`user_id`); ein vorhandener Eintrag ohne Konto
 * mit gleicher E-Mail wird verknüpft statt dupliziert. Fällt die Rolle weg, wird ein vom Abgleich angelegter Eintrag archiviert
 * (Zuteilungen und Historie bleiben); im Helferpool bewusst verknüpfte Einträge bleiben davon unberührt. Externe Helfer ohne Konto bleiben unberührt.
 */
final class GrossanlassHelperAccountSync
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param bool $restore archivierte Einträge von Konten mit Helferrolle wieder aktivieren (nur bei Rollenänderung,
     *                      nicht beim blossen Lesen, damit eine manuelle Archivierung nicht rückgängig gemacht wird)
     */
    public function sync(Department $department, bool $restore = false): void
    {
        if (!$department->isGrossanlass()) {
            return;
        }
        $deptId = $department->getId();

        /** @var array<string, User> $wanted */
        $wanted = [];
        foreach ($this->entityManager->getRepository(Membership::class)->findBy(['departmentId' => $deptId]) as $m) {
            $user = $m->getUser();
            if (($m->getIsHelper() || $m->getRole() === 'h') && $user->getState() === 'active' && !$user->hasSuperAdminProfile()) {
                $wanted[$user->getId()] = $user;
            }
        }

        $repo = $this->entityManager->getRepository(DepartmentGrossanlassHelper::class);
        /** @var list<DepartmentGrossanlassHelper> $all */
        $all = $repo->findBy(['departmentId' => $deptId]);
        $byUser = [];
        $unlinkedByEmail = [];
        foreach ($all as $h) {
            if ($h->getUserId() !== null) {
                $byUser[$h->getUserId()] = $h;
            } elseif ($h->getEmail() !== null && !$h->isArchived()) {
                $unlinkedByEmail[strtolower($h->getEmail())] ??= $h;
            }
        }

        $changed = false;
        foreach ($wanted as $userId => $user) {
            $helper = $byUser[$userId] ?? null;
            if ($helper === null) {
                $email = strtolower($user->getProfile()?->getEmail() ?? '');
                $helper = $email !== '' ? ($unlinkedByEmail[$email] ?? null) : null;
                if ($helper !== null) {
                    $helper->setUserId($userId);
                    unset($unlinkedByEmail[$email]);
                } else {
                    $helper = new DepartmentGrossanlassHelper(
                        GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::HELPER, DepartmentGrossanlassHelper::class),
                        $deptId,
                    );
                    $helper->setUserId($userId)->setSource(DepartmentGrossanlassHelper::SOURCE_API);
                    $this->entityManager->persist($helper);
                }
                $this->fillFromProfile($helper, $user);
                $changed = true;
            }
            if ($restore && $helper->isArchived()) {
                $helper->restore();
                $changed = true;
            }
        }

        foreach ($byUser as $userId => $helper) {
            // Nur vom Abgleich angelegte Einträge folgen der Rolle; im Helferpool bewusst verknüpfte (manuell, Import) bleiben aktiv.
            if (!isset($wanted[$userId]) && !$helper->isArchived() && $helper->getSource() === DepartmentGrossanlassHelper::SOURCE_API) {
                $helper->archive();
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function fillFromProfile(DepartmentGrossanlassHelper $helper, User $user): void
    {
        $profile = $user->getProfile();
        if ($profile === null) {
            return;
        }
        if ($helper->getFirstName() === '' && $helper->getLastName() === '') {
            $helper->setFirstName(mb_substr((string) $profile->getFirstName(), 0, 100));
            $helper->setLastName(mb_substr((string) $profile->getLastName(), 0, 100));
            if ($helper->getFirstName() === '' && $helper->getLastName() === '') {
                $helper->setFirstName(mb_substr($profile->getDisplayName(), 0, 100));
            }
        }
        if ($helper->getEmail() === null) {
            $helper->setEmail(mb_substr(strtolower($profile->getEmail()), 0, 180));
        }
    }
}
