<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassUserCard;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GrossanlassUserCardService
{
    private string $publicQrBaseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassUserCardDriveProofStorageService $proofStorage,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
        #[Autowire('%env(APP_PUBLIC_QR_URL)%')] private string $appPublicQrUrl,
    ) {
        $trimmedQr = trim($this->appPublicQrUrl);
        $this->publicQrBaseUrl = $trimmedQr !== ''
            ? rtrim($trimmedQr, '/')
            : rtrim($this->appFrontendUrl, '/');
    }

    public function buildPublicUrl(string $publicCode): string
    {
        return $this->publicQrBaseUrl . '/i/c/' . rawurlencode($publicCode);
    }

    /**
     * Öffentliche Ausweis-Seite (ohne Fahrdokument / User-ID).
     *
     * @return array<string, mixed>|null
     */
    public function resolvePublicByCode(string $publicCode): ?array
    {
        $normalized = trim($publicCode);
        if ($normalized === '') {
            return null;
        }

        $card = $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)
            ->findOneBy(['publicCode' => $normalized]);
        if (!$card instanceof DepartmentGrossanlassUserCard) {
            return null;
        }

        $people = $this->collectMembers($card->getDepartment());
        $person = $people[$card->getUserId()] ?? [
            'user' => $card->getUser(),
            'ressort' => '',
            'role' => '',
        ];

        return $this->serializePublic($card, $person);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCards(Department $department): array
    {
        $people = $this->collectMembers($department);
        $cards = [];
        foreach ($people as $person) {
            $cards[] = $this->serialize($this->ensureCard($department, $person['user']), $person);
        }

        $this->entityManager->flush();

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateCard(Department $department, User $actor, string $userId, array $data): array
    {
        if (!$this->access->canManagePlanung($actor, $department)) {
            throw new \RuntimeException('Keine Berechtigung für User-Karten');
        }

        $people = $this->collectMembers($department);
        if (!isset($people[$userId])) {
            throw new \InvalidArgumentException('Person ist in keinem Ressort');
        }

        $card = $this->ensureCard($department, $people[$userId]['user']);
        if (array_key_exists('drive_classes', $data)) {
            if (!is_array($data['drive_classes'])) {
                throw new \InvalidArgumentException('drive_classes muss eine Liste sein');
            }
            $card->setDriveClasses(GrossanlassDriveCategories::sanitize($data['drive_classes']));
            if ($card->getDriveClasses() === []) {
                $this->clearVerification($card);
            }
        }
        if (!empty($data['verify_in_person'])) {
            $this->confirmInPerson($card, $actor);
        }
        if (!empty($data['verify_document'])) {
            $this->confirmDocument($card, $actor);
        }
        if (!empty($data['revoke_verification'])) {
            $this->clearVerification($card);
        }
        if (array_key_exists('may_drive', $data) && !array_key_exists('drive_classes', $data)
            && empty($data['verify_in_person']) && empty($data['verify_document']) && empty($data['revoke_verification'])) {
            // Legacy-Checkbox: ohne Klassen kein bestätigtes Fahrrecht.
            if (!(bool) $data['may_drive']) {
                $this->clearVerification($card);
            }
        }
        $this->refreshMayDrive($card);
        if (!empty($data['print'])) {
            $card->setCardPrintedAt(new \DateTime());
        }
        $this->entityManager->flush();

        return $this->serialize($card, $people[$userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadDriveProof(Department $department, User $actor, string $userId, UploadedFile $file): array
    {
        if (!$this->access->canManagePlanung($actor, $department)) {
            throw new \RuntimeException('Keine Berechtigung für User-Karten');
        }
        $people = $this->collectMembers($department);
        if (!isset($people[$userId])) {
            throw new \InvalidArgumentException('Person ist in keinem Ressort');
        }

        $card = $this->ensureCard($department, $people[$userId]['user']);
        $previous = $card->getDriveDocumentFilename();
        $stored = $this->proofStorage->store($department, $people[$userId]['user'], $actor, $file);
        if ($previous !== '' && $previous !== $stored['filename']) {
            $this->proofStorage->deleteFile($department->getId(), $userId, $previous);
        }
        $card->setDriveDocumentFilename($stored['filename']);
        $card->setDriveDocumentOriginalName($stored['original_filename']);
        $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_DOCUMENT);
        if (!$card->isDriveVerified()) {
            $card->setDriveVerified(false);
            $card->setDriveVerifiedAt(null);
            $card->setDriveVerifiedById(null);
        }
        $this->refreshMayDrive($card);
        $this->entityManager->flush();

        return $this->serialize($card, $people[$userId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteDriveProof(Department $department, User $actor, string $userId): array
    {
        if (!$this->access->canManagePlanung($actor, $department)) {
            throw new \RuntimeException('Keine Berechtigung für User-Karten');
        }
        $people = $this->collectMembers($department);
        if (!isset($people[$userId])) {
            throw new \InvalidArgumentException('Person ist in keinem Ressort');
        }

        $card = $this->ensureCard($department, $people[$userId]['user']);
        $filename = $card->getDriveDocumentFilename();
        if ($filename !== '') {
            $this->proofStorage->deleteFile($department->getId(), $userId, $filename);
        }
        $card->setDriveDocumentFilename('');
        $card->setDriveDocumentOriginalName('');
        if ($card->getDriveProofKind() === GrossanlassDriveCategories::PROOF_DOCUMENT) {
            $card->setDriveProofKind(
                $card->isDriveVerified()
                    ? GrossanlassDriveCategories::PROOF_IN_PERSON
                    : GrossanlassDriveCategories::PROOF_NONE
            );
        }
        $this->entityManager->flush();

        return $this->serialize($card, $people[$userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function printMissing(Department $department, User $actor): array
    {
        if (!$this->access->canManagePlanung($actor, $department)) {
            throw new \RuntimeException('Keine Berechtigung für User-Karten');
        }

        $now = new \DateTime();
        foreach ($this->listCards($department) as $row) {
            if ($row['printed']) {
                continue;
            }
            $card = $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)->find([
                'departmentId' => $department->getId(),
                'userId' => $row['user_id'],
            ]);
            if ($card instanceof DepartmentGrossanlassUserCard) {
                $card->setCardPrintedAt($now);
            }
        }
        $this->entityManager->flush();

        return $this->listCards($department);
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertForMember(Department $department, User $user, string $ressortName, string $role, bool $mayDrive): array
    {
        $card = $this->ensureCard($department, $user);
        if (!$mayDrive) {
            $this->clearVerification($card);
            $this->refreshMayDrive($card);
        }
        $this->entityManager->flush();

        return $this->serialize($card, [
            'user' => $user,
            'ressort' => $ressortName,
            'role' => $role,
        ]);
    }

    private function confirmInPerson(DepartmentGrossanlassUserCard $card, User $actor): void
    {
        if ($card->getDriveClasses() === []) {
            throw new \InvalidArgumentException('Zuerst Fahrklassen setzen');
        }
        $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_IN_PERSON);
        $card->setDriveVerified(true);
        $card->setDriveVerifiedAt(new \DateTime());
        $card->setDriveVerifiedById($actor->getId());
    }

    private function confirmDocument(DepartmentGrossanlassUserCard $card, User $actor): void
    {
        if ($card->getDriveClasses() === []) {
            throw new \InvalidArgumentException('Zuerst Fahrklassen setzen');
        }
        if ($card->getDriveDocumentFilename() === '') {
            throw new \InvalidArgumentException('Kein Scan hinterlegt');
        }
        $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_DOCUMENT);
        $card->setDriveVerified(true);
        $card->setDriveVerifiedAt(new \DateTime());
        $card->setDriveVerifiedById($actor->getId());
    }

    private function clearVerification(DepartmentGrossanlassUserCard $card): void
    {
        $card->setDriveVerified(false);
        $card->setDriveVerifiedAt(null);
        $card->setDriveVerifiedById(null);
        if ($card->getDriveDocumentFilename() === '') {
            $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_NONE);
        } else {
            $card->setDriveProofKind(GrossanlassDriveCategories::PROOF_DOCUMENT);
        }
    }

    private function refreshMayDrive(DepartmentGrossanlassUserCard $card): void
    {
        $card->setMayDrive($card->isDriveVerified() && $card->getDriveClasses() !== []);
    }

    private function ensureCard(Department $department, User $user): DepartmentGrossanlassUserCard
    {
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassUserCard::class)->find([
            'departmentId' => $department->getId(),
            'userId' => $user->getId(),
        ]);
        if ($existing instanceof DepartmentGrossanlassUserCard) {
            if (!GrossanlassIdGenerator::matches($existing->getPublicCode(), GrossanlassIdGenerator::USER_CARD)) {
                $existing->setPublicCode($this->makeCode());
            }

            return $existing;
        }

        $card = new DepartmentGrossanlassUserCard();
        $card->setDepartment($department);
        $card->setUser($user);
        $card->setPublicCode($this->makeCode());
        $this->entityManager->persist($card);

        return $card;
    }

    /**
     * @return array<string, array{user: User, ressort: string, role: string}>
     */
    private function collectMembers(Department $department): array
    {
        $groups = $this->entityManager->getRepository(Group::class)->findBy(
            ['departmentId' => $department->getId()],
            ['sortOrder' => 'ASC', 'name' => 'ASC'],
        );
        $groupById = [];
        foreach ($groups as $group) {
            $groupById[$group->getId()] = $group;
        }

        $memberships = $this->entityManager->getRepository(GroupMembership::class)
            ->createQueryBuilder('gm')
            ->innerJoin('gm.group', 'g')
            ->where('g.departmentId = :deptId')
            ->setParameter('deptId', $department->getId())
            ->getQuery()
            ->getResult();

        $people = [];
        foreach ($memberships as $membership) {
            if (!$membership instanceof GroupMembership) {
                continue;
            }
            $user = $membership->getUser();
            $group = $groupById[$membership->getGroupId()] ?? $membership->getGroup();
            $existing = $people[$user->getId()] ?? null;
            $prefer = $existing === null || ($group->getParentId() === null && $existing['group']->getParentId() !== null);
            if (!$prefer) {
                continue;
            }
            $people[$user->getId()] = [
                'user' => $user,
                'group' => $group,
                'ressort' => $group->getName(),
                'role' => $membership->getRoleLabel(),
            ];
        }

        return $people;
    }

    /**
     * @param array{user: User, ressort: string, role: string} $person
     * @return array<string, mixed>
     */
    private function serialize(DepartmentGrossanlassUserCard $card, array $person): array
    {
        $profile = $person['user']->getProfile();
        $filename = $card->getDriveDocumentFilename();
        $document = null;
        if ($filename !== '') {
            $document = [
                'filename' => $filename,
                'original_name' => $card->getDriveDocumentOriginalName() ?: $filename,
                'url' => $this->proofStorage->buildUrl(
                    $card->getDepartmentId(),
                    $card->getUserId(),
                    $filename,
                ),
            ];
        }

        $code = $card->getPublicCode();

        return [
            'user_id' => $person['user']->getId(),
            'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'event_name' => $card->getDepartment()->getName(),
            'ressort' => $person['ressort'],
            'role' => $person['role'],
            'code' => $code,
            'qr_url' => $this->buildPublicUrl($code),
            'may_drive' => $card->getMayDrive(),
            'drive_classes' => $card->getDriveClasses(),
            'drive_proof_kind' => $card->getDriveProofKind(),
            'drive_verified' => $card->isDriveVerified(),
            'drive_verified_at' => $card->getDriveVerifiedAt()?->format('c'),
            'drive_verified_by_name' => $this->verifierName($card->getDriveVerifiedById()),
            'drive_has_extra_regulation' => GrossanlassDriveCategories::hasExtraRegulation($card->getDriveClasses()),
            'drive_document' => $document,
            'printed' => $card->getCardPrintedAt() !== null,
            'printed_at' => $card->getCardPrintedAt()?->format('c'),
        ];
    }

    private function verifierName(?string $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = $this->entityManager->find(User::class, $userId);
        if (!$user instanceof User) {
            return null;
        }

        return $user->getProfile()?->getDisplayName();
    }

    /**
     * @param array{user: User, ressort: string, role: string} $person
     * @return array<string, mixed>
     */
    private function serializePublic(DepartmentGrossanlassUserCard $card, array $person): array
    {
        $profile = $person['user']->getProfile();
        $code = $card->getPublicCode();

        return [
            'code' => $code,
            'entity_type' => 'user_card',
            'public_url' => $this->buildPublicUrl($code),
            'event' => [
                'name' => $card->getDepartment()->getName(),
            ],
            'person' => [
                'name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            ],
            'ressort' => $person['ressort'],
            'role' => $person['role'],
            'may_drive' => $card->getMayDrive(),
            'drive_classes' => $card->getDriveClasses(),
            'department' => [
                'id' => $card->getDepartmentId(),
                'name' => $card->getDepartment()->getName(),
            ],
        ];
    }

    private function makeCode(): string
    {
        return GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::USER_CARD,
            DepartmentGrossanlassUserCard::class,
            'publicCode',
        );
    }
}
