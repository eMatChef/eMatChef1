<?php

namespace App\Service\Display;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DepartmentDisplayScreenService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DisplayAccessCodeGenerator $accessCodeGenerator,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
    ) {
    }

    public function canManageDepartment(User $user, string $departmentId): bool
    {
        if (\in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if ($membership === null) {
            return false;
        }

        $role = strtolower((string) $membership->getRole());
        $managerRoles = ['mw', 'matwart', 'dc', 'depchef', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'];

        return \in_array($role, $managerRoles, true);
    }

    /**
     * @return list<DepartmentDisplayScreen>
     */
    public function listForDepartment(string $departmentId, bool $includeRevoked = false): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(DepartmentDisplayScreen::class, 's')
            ->where('s.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('s.createdAt', 'DESC');

        if (!$includeRevoked) {
            $qb->andWhere('s.revokedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{screen: DepartmentDisplayScreen, access_code: string}
     */
    public function create(string $departmentId, string $name, ?User $createdBy): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Department nicht gefunden.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich.');
        }

        $accessCode = $this->accessCodeGenerator->generate(8);
        $screen = new DepartmentDisplayScreen();
        $screen->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, DepartmentDisplayScreen::class, 'dsp'));
        $screen->setDepartmentId($departmentId);
        $screen->setName($name);
        $screen->setPublicId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, DepartmentDisplayScreen::class, 'dsi', 'publicId'));
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->setCreatedByUserId($createdBy?->getId());
        $screen->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($screen);
        $this->entityManager->flush();

        return ['screen' => $screen, 'access_code' => $accessCode];
    }

    /**
     * @param array{subtitle_text?: ?string, show_activities?: bool, show_workshop?: bool} $data
     */
    public function updateSettings(DepartmentDisplayScreen $screen, array $data): DepartmentDisplayScreen
    {
        if ($screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist widerrufen.');
        }

        if (\array_key_exists('subtitle_text', $data)) {
            $raw = $data['subtitle_text'];
            if ($raw === null || trim((string) $raw) === '') {
                $screen->setSubtitleText(null);
            } else {
                $text = trim((string) $raw);
                if (mb_strlen($text) > 500) {
                    throw new \InvalidArgumentException('Untertitel darf maximal 500 Zeichen lang sein.');
                }
                $screen->setSubtitleText($text);
            }
        }

        $showActivities = \array_key_exists('show_activities', $data)
            ? (bool) $data['show_activities']
            : $screen->isShowActivities();
        $showWorkshop = \array_key_exists('show_workshop', $data)
            ? (bool) $data['show_workshop']
            : $screen->isShowWorkshop();

        if (!$showActivities && !$showWorkshop) {
            throw new \InvalidArgumentException('Mindestens ein Bereich (Anlässe oder Werkstatt) muss aktiv sein.');
        }

        $screen->setShowActivities($showActivities);
        $screen->setShowWorkshop($showWorkshop);
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $screen;
    }

    /**
     * @return array{screen: DepartmentDisplayScreen, access_code: string}
     */
    public function rotateAccessCode(DepartmentDisplayScreen $screen): array
    {
        if ($screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist widerrufen.');
        }

        $accessCode = $this->accessCodeGenerator->generate(8);
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->incrementCodeVersion();
        $screen->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return ['screen' => $screen, 'access_code' => $accessCode];
    }

    public function revoke(DepartmentDisplayScreen $screen): void
    {
        $screen->setRevokedAt(new \DateTime());
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function verifyAccessCode(DepartmentDisplayScreen $screen, string $code): bool
    {
        if ($screen->isRevoked()) {
            return false;
        }

        $normalized = $this->accessCodeGenerator->normalize($code);
        if (!$this->accessCodeGenerator->isValidFormat($normalized)) {
            return false;
        }

        return password_verify($normalized, $screen->getAccessCodeHash());
    }

    public function touchLastUsed(DepartmentDisplayScreen $screen): void
    {
        $screen->setLastUsedAt(new \DateTime());
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function findByPublicId(string $publicId): ?DepartmentDisplayScreen
    {
        return $this->entityManager->getRepository(DepartmentDisplayScreen::class)->findOneBy([
            'publicId' => $publicId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForSettings(DepartmentDisplayScreen $screen): array
    {
        return [
            'id' => $screen->getId(),
            'department_id' => $screen->getDepartmentId(),
            'name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
            'display_url' => $this->buildDisplayUrl($screen->getPublicId()),
            'subtitle_text' => $screen->getSubtitleText(),
            'show_activities' => $screen->isShowActivities(),
            'show_workshop' => $screen->isShowWorkshop(),
            'access_code_hint' => $screen->getAccessCodeHint(),
            'code_version' => $screen->getCodeVersion(),
            'revoked_at' => $screen->getRevokedAt()?->format('c'),
            'last_used_at' => $screen->getLastUsedAt()?->format('c'),
            'created_at' => $screen->getCreatedAt()->format('c'),
            'updated_at' => $screen->getUpdatedAt()->format('c'),
        ];
    }

    public function buildDisplayUrl(string $publicId): string
    {
        $origin = trim($this->appFrontendUrl);
        if ($origin === '') {
            return '/display/' . rawurlencode($publicId);
        }

        return rtrim($origin, '/') . '/display/' . rawurlencode($publicId);
    }

    private function hashAccessCode(string $code): string
    {
        $normalized = $this->accessCodeGenerator->normalize($code);

        return password_hash($normalized, PASSWORD_DEFAULT);
    }
}
