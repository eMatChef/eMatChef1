<?php

namespace App\Controller;

use App\Config\LanguageConfig;
use App\Entity\Profile;
use App\Entity\User;
use App\Entity\AdminJoinRequest;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\Auth\CrossSubdomainAuthCookies;
use App\Service\OrganisationUserPickerFilter;
use App\Service\TurnstileVerifier;
use App\Service\VerificationEmailService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const PASSWORD_RESET_CODE_TTL_MINUTES = 10;
    private const PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS = 60;
    private const PASSWORD_RESET_MAX_REQUESTS_PER_HOUR = 5;
    private const PASSWORD_RESET_MAX_ATTEMPTS = 5;
    private const PASSWORD_RESET_LOCK_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ProfileRepository $profileRepository,
        private UserRepository $userRepository,
        private VerificationEmailService $verificationEmailService,
        private AuditLogger $auditLogger,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private ExtractorInterface $refreshTokenExtractor,
        private CacheItemPoolInterface $cache,
        private TurnstileVerifier $turnstileVerifier,
        private LanguageConfig $languageConfig,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
        private CrossSubdomainAuthCookies $authCookies,
    ) {}

    /**
     * Login-Endpoint - wird von json_login Firewall abgefangen
     */
    #[Route('/login_check', name: 'login_check', methods: ['POST'])]
    public function login(): void
    {
        // Diese Methode sollte niemals erreicht werden!
        throw new \LogicException('This method should be intercepted by the json_login firewall.');
    }

    /**
     * Logout – invalidiert Refresh-Token auf dem Server (da LogoutEvent bei security: false nicht ausgelöst wird)
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $tokenString = $this->refreshTokenExtractor->getRefreshToken($request, 'refresh_token');

        if (null !== $tokenString) {
            $refreshToken = $this->refreshTokenManager->get($tokenString);
            if (null !== $refreshToken) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        $response = new JsonResponse([
            'message' => 'Erfolgreich abgemeldet',
        ]);

        $this->authCookies->clearAuthCookies($response);
        $this->authCookies->setLogoutMarker($response);

        return $response;
    }

    #[Route('/session', name: 'session', methods: ['GET'])]
    public function session(): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht angemeldet'], 401);
        }

        $profile = $user->getProfile();
        if (!$profile) {
            return new JsonResponse(['error' => 'Profil nicht gefunden'], 404);
        }

        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.department', 'd')
            ->addSelect('d')
            ->where('m.userId = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();

        $departments = [];
        $primaryDepartment = null;
        foreach ($memberships as $m) {
            $department = $m->getDepartment();
            $deptData = [
                'id' => $department->getId(),
                'name' => $department->getName(),
                'organisation_id' => $department->getOrganisationId(),
                'role' => $m->getRole(),
                'is_primary' => $m->getIsPrimary(),
            ];
            $departments[] = $deptData;
            if ($m->getIsPrimary() || !$primaryDepartment) {
                $primaryDepartment = $deptData;
            }
        }

        if (!$primaryDepartment && \count($departments) > 0) {
            $primaryDepartment = $departments[0];
        }

        $allowedIds = array_map(static fn (array $d): string => $d['id'], $departments);
        $storedLastUsedId = $user->getLastUsedDepartmentId();
        $lastUsedResolved = null;
        if ($storedLastUsedId !== null && \in_array($storedLastUsedId, $allowedIds, true)) {
            $lastUsedResolved = $storedLastUsedId;
        } elseif ($primaryDepartment !== null) {
            $lastUsedResolved = $primaryDepartment['id'];
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'state' => $user->getState(),
                'profile_id' => $user->getProfileId(),
                'last_used_department' => $lastUsedResolved,
            ],
            'profile' => [
                'id' => $profile->getId(),
                'email' => $profile->getEmail(),
                'first_name' => $profile->getFirstName() ?? null,
                'last_name' => $profile->getLastName() ?? null,
                'nickname' => $profile->getNickname() ?? null,
                'avatar_initials' => $profile->getAvatarInitials() ?? null,
                'pending_email' => $user->getPendingEmail() ?? null,
                'language' => $profile->getLanguage(),
                'roles' => $profile->getRoles(),
                'background_color' => $profile->getBackgroundColor() ?? null,
                'text_color' => $profile->getTextColor() ?? null,
            ],
            'departments' => $departments,
            'primary_department' => $primaryDepartment ? $primaryDepartment['id'] : null,
            'last_used_department' => $lastUsedResolved,
        ]);
    }

    private function resolveAuthenticatedUser(): ?User
    {
        $securityUser = $this->getUser();
        if ($securityUser instanceof User) {
            return $securityUser;
        }
        if (!$securityUser instanceof UserInterface) {
            return null;
        }

        // Fallback: bei manchen Firewalls ist das Security-Objekt nicht direkt die Entity.
        if (method_exists($securityUser, 'getProfileId')) {
            $profileId = (string) $securityUser->getProfileId();
            if ($profileId !== '') {
                $resolved = $this->userRepository->findOneBy(['profileId' => $profileId]);
                if ($resolved instanceof User) {
                    return $resolved;
                }
            }
        }

        $identifier = trim((string) $securityUser->getUserIdentifier());
        if ($identifier === '') {
            return null;
        }

        $resolved = $this->userRepository->findOneByProfileId($identifier);
        if ($resolved instanceof User) {
            return $resolved;
        }

        return $this->userRepository->findOneBy(['id' => $identifier]);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $language = $this->languageConfig->normalize((string) ($data['language'] ?? $this->languageConfig->getDefaultLanguage()));
        $acceptTerms = (bool) ($data['acceptTerms'] ?? false);
        $requestedOrganisationId = trim((string) ($data['requestedOrganisationId'] ?? ''));
        $requestedDepartmentName = trim((string) ($data['requestedDepartmentName'] ?? ''));
        $honeypotWebsite = trim((string) ($data['website'] ?? ''));
        $turnstileToken = trim((string) ($data['turnstileToken'] ?? ''));

        // Honeypot: wenn gefüllt -> sehr wahrscheinlich Bot
        if ($honeypotWebsite !== '') {
            return new JsonResponse(['error' => 'Ungueltige Anfrage'], 400);
        }

        $clientIp = (string) ($request->getClientIp() ?? 'unknown');

        // Cloudflare Turnstile (optional; TURNSTILE_SKIP_VERIFY=1 nur lokal/Test)
        if ($this->turnstileVerifier->mustValidateCaptcha()) {
            if ($turnstileToken === '' || !$this->turnstileVerifier->verify($turnstileToken, $clientIp !== 'unknown' ? $clientIp : null)) {
                return new JsonResponse(['error' => 'Captcha-Verifikation fehlgeschlagen. Bitte erneut versuchen.'], 400);
            }
        }

        // Rate limit: pro IP + pro E-Mail (simple Cache-basierte Drosselung)
        if (!$this->allowRegistrationAttempt($clientIp, $email)) {
            return new JsonResponse(['error' => 'Zu viele Registrierungen. Bitte spaeter erneut versuchen.'], 429);
        }

        if ($firstName === '' || $lastName === '') {
            return new JsonResponse(['error' => 'Vorname und Nachname sind erforderlich'], 400);
        }

        if ($requestedOrganisationId === '' || $requestedDepartmentName === '') {
            return new JsonResponse(['error' => 'Organisation und Abteilungsname sind erforderlich'], 400);
        }

        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'E-Mail und Passwort sind erforderlich'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Ungueltige E-Mail-Adresse'], 400);
        }

        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'Das Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        if (!$acceptTerms) {
            return new JsonResponse(['error' => 'Nutzungsbedingungen muessen akzeptiert werden'], 400);
        }

        if (!$this->languageConfig->isSupported($language)) {
            return new JsonResponse(['error' => 'Ungueltige Sprache'], 400);
        }

        if ($this->profileRepository->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Diese E-Mail-Adresse ist bereits registriert'], 409);
        }

        /** @var Organisation|null $org */
        $org = $this->entityManager->getRepository(Organisation::class)->find($requestedOrganisationId);
        if (!$org) {
            return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
        }
        if (!OrganisationUserPickerFilter::isVisibleForUserPickers($org)) {
            return new JsonResponse(['error' => 'Organisation nicht verfuegbar'], 400);
        }
        $orgAllowedLanguages = $org->getAllowedLanguages();
        if (is_array($orgAllowedLanguages) && count($orgAllowedLanguages) > 0 && !in_array($language, $orgAllowedLanguages, true)) {
            return new JsonResponse(['error' => 'Sprache fuer diese Organisation nicht erlaubt'], 400);
        }

        $profile = new Profile();
        $profile->setId(IdGenerator::generateUnique($this->entityManager, Profile::class));
        $profile->setEmail($email);
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setNickname($nickname !== '' ? $nickname : null);
        $profile->setLanguage($language);
        $profile->setRoles(['ROLE_USER']);

        $user = new User();
        $user->setId(IdGenerator::generateUnique($this->entityManager, User::class));
        $user->setProfileId($profile->getId());
        $user->setProfile($profile);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setState('active');
        $user->setEmailVerified(false);
        $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $user->setEmailVerificationExpiresAt((new \DateTime())->modify('+10 days'));

        $this->entityManager->persist($profile);
        $this->entityManager->persist($user);

        // Direkt Admin-Join-Request erstellen (damit Admins den Wunsch sehen)
        $adminRequest = new AdminJoinRequest();
        $adminRequest->setId(IdGenerator::generateUnique($this->entityManager, AdminJoinRequest::class));
        $adminRequest->setUser($user);
        $adminRequest->setRequestedDepartmentName($requestedDepartmentName);
        $adminRequest->setRequestedOrganisationId($requestedOrganisationId);
        $adminRequest->setStatus('pending');
        $this->entityManager->persist($adminRequest);

        $this->entityManager->flush();
        try {
            $this->verificationEmailService->sendVerificationEmail($user);
        } catch (\Throwable $e) {
            // Bei unzustellbarer Mail Registrierung zurueckrollen,
            // damit kein unbestaetigter "Zombie-Account" bestehen bleibt.
            $this->entityManager->remove($user);
            $this->entityManager->remove($profile);
            $this->entityManager->remove($adminRequest);
            $this->entityManager->flush();

            return new JsonResponse([
                'error' => 'Verifikationsmail konnte nicht zugestellt werden. Bitte E-Mail-Adresse pruefen.'
            ], 400);
        }

        $this->auditLogger->log(
            'user',
            $user->getId(),
            'user_created_self',
            null,
            $user,
            null,
            [
                'source' => ['old' => null, 'new' => 'self_registration'],
                'profile_id' => ['old' => null, 'new' => $profile->getId()],
                'email' => ['old' => null, 'new' => $profile->getEmail()],
                'state' => ['old' => null, 'new' => $user->getState()],
                'email_verified' => ['old' => null, 'new' => $user->isEmailVerified()],
            ]
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Konto erstellt. Bitte bestaetigen Sie Ihre E-Mail-Adresse ueber den Link in der E-Mail (gueltig 10 Tage).'
        ], 201);
    }

    private function allowRegistrationAttempt(string $clientIp, string $email): bool
    {
        try {
            // Limits: IP = 10/h, Email = 3/h
            $ipKey = 'auth_register_ip_' . hash('sha256', $clientIp);
            $mailKey = 'auth_register_mail_' . hash('sha256', $email);

            $ipCount = $this->incrementRateCounter($ipKey, 3600);
            if ($ipCount > 10) {
                return false;
            }

            if ($email !== '') {
                $mailCount = $this->incrementRateCounter($mailKey, 3600);
                if ($mailCount > 3) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            // Cache nicht beschreibbar — Registrierung nicht wegen Infrastruktur blockieren.
            return true;
        }
    }

    private function incrementRateCounter(string $key, int $ttlSeconds): int
    {
        $item = $this->cache->getItem($key);
        $current = 0;
        if ($item->isHit()) {
            $val = $item->get();
            $current = is_numeric($val) ? (int) $val : 0;
        }

        $next = $current + 1;
        $item->set($next);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);

        return $next;
    }

    #[Route('/verify', name: 'verify', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        $token = trim((string) $request->query->get('token', ''));
        if ($token === '') {
            return new JsonResponse(['error' => 'Token fehlt'], 400);
        }

        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);
        if (!$user) {
            return new JsonResponse(['error' => 'Ungueltiger Verifikationslink'], 400);
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (!$expiresAt || $expiresAt < new \DateTime()) {
            return new JsonResponse(['error' => 'Verifikationslink ist abgelaufen'], 410);
        }

        $pendingEmail = trim((string) ($user->getPendingEmail() ?? ''));
        if ($pendingEmail !== '') {
            $existing = $this->profileRepository->findOneBy(['email' => strtolower($pendingEmail)]);
            if ($existing && $existing->getId() !== $user->getProfileId()) {
                return new JsonResponse(['error' => 'Diese E-Mail-Adresse ist bereits vergeben'], 409);
            }

            $profile = $user->getProfile();
            if (!$profile) {
                return new JsonResponse(['error' => 'Profil nicht gefunden'], 404);
            }

            $oldEmail = strtolower($profile->getEmail());
            $profile->setEmail(strtolower($pendingEmail));
            $profile->setUpdatedAt(new \DateTime());
            $user->setPendingEmail(null);
            $user->setEmailVerificationToken(null);
            $user->setEmailVerificationExpiresAt(null);
            $this->auditLogger->log(
                'profile',
                $profile->getId(),
                'profile_email_change_confirmed',
                null,
                $user,
                null,
                [
                    'email' => ['old' => $oldEmail, 'new' => strtolower($pendingEmail)],
                ]
            );
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Neue E-Mail-Adresse bestaetigt. Deine Anmeldung bleibt aktiv.'
            ]);
        }

        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'E-Mail bestaetigt. Sie koennen sich nun anmelden.'
        ]);
    }

    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            return new JsonResponse(['error' => 'E-Mail ist erforderlich'], 400);
        }

        $profile = $this->profileRepository->findOneBy(['email' => $email]);
        if (!$profile) {
            return new JsonResponse(['success' => true]);
        }

        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        if (!$user || $user->isEmailVerified()) {
            return new JsonResponse(['success' => true]);
        }

        $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $user->setEmailVerificationExpiresAt((new \DateTime())->modify('+10 days'));
        $this->entityManager->flush();
        $this->verificationEmailService->sendVerificationEmail($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Neue Verifikationsmail wurde gesendet.'
        ]);
    }

    #[Route('/password-reset/request', name: 'password_reset_request', methods: ['POST'])]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        $publicMessage = 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Sicherheitscode gesendet.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $profile = $this->profileRepository->findOneBy(['email' => $email]);
        if (!$profile) {
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        if (!$user) {
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $now = new \DateTime();
        $lockedUntil = $user->getPasswordResetLockedUntil();
        if ($lockedUntil && $lockedUntil > $now) {
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $windowStartedAt = $user->getPasswordResetWindowStartedAt();
        $windowExpired = !$windowStartedAt || $windowStartedAt < (clone $now)->modify('-1 hour');
        if ($windowExpired) {
            $user->setPasswordResetWindowStartedAt(clone $now);
            $user->setPasswordResetRequestCount(0);
        }

        if ($user->getPasswordResetRequestCount() >= self::PASSWORD_RESET_MAX_REQUESTS_PER_HOUR) {
            $user->setPasswordResetLockedUntil((clone $now)->modify('+1 hour'));
            $this->entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $lastRequestedAt = $user->getPasswordResetLastRequestedAt();
        if ($lastRequestedAt && $lastRequestedAt > (clone $now)->modify('-' . self::PASSWORD_RESET_REQUEST_COOLDOWN_SECONDS . ' seconds')) {
            return new JsonResponse(['success' => true, 'message' => $publicMessage]);
        }

        $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $expiresAt = (clone $now)->modify('+' . self::PASSWORD_RESET_CODE_TTL_MINUTES . ' minutes');

        $user->setPasswordResetCodeHash($this->hashPasswordResetCode($email, $code));
        $user->setPasswordResetExpiresAt($expiresAt);
        $user->setPasswordResetLastRequestedAt(clone $now);
        $user->setPasswordResetAttemptCount(0);
        $user->setPasswordResetLockedUntil(null);
        $user->setPasswordResetRequestCount($user->getPasswordResetRequestCount() + 1);
        $this->entityManager->flush();

        try {
            $this->verificationEmailService->sendPasswordResetCode($user, $code, $expiresAt);
        } catch (\Throwable $e) {
            // Keine Details an Client geben, um Account-Enumeration zu vermeiden.
        }

        return new JsonResponse(['success' => true, 'message' => $publicMessage]);
    }

    #[Route('/password-reset/confirm', name: 'password_reset_confirm', methods: ['POST'])]
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Ungueltige Anfrage'], 400);
        }
        if (!preg_match('/^[0-9A-F]{6}$/', $code)) {
            return new JsonResponse(['error' => 'Code muss 6-stellig und hexadezimal sein'], 400);
        }
        if (strlen($newPassword) < 8) {
            return new JsonResponse(['error' => 'Das Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        $profile = $this->profileRepository->findOneBy(['email' => $email]);
        if (!$profile) {
            return new JsonResponse(['error' => 'Code ungueltig oder abgelaufen'], 400);
        }

        $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
        if (!$user) {
            return new JsonResponse(['error' => 'Code ungueltig oder abgelaufen'], 400);
        }

        $now = new \DateTime();
        $lockedUntil = $user->getPasswordResetLockedUntil();
        if ($lockedUntil && $lockedUntil > $now) {
            return new JsonResponse(['error' => 'Zu viele Fehlversuche. Bitte spaeter erneut anfordern.'], 429);
        }

        $expiresAt = $user->getPasswordResetExpiresAt();
        $storedHash = $user->getPasswordResetCodeHash();
        if (!$storedHash || !$expiresAt || $expiresAt < $now) {
            return new JsonResponse(['error' => 'Code ungueltig oder abgelaufen'], 400);
        }

        if ($user->getPasswordResetAttemptCount() >= self::PASSWORD_RESET_MAX_ATTEMPTS) {
            $user->setPasswordResetCodeHash(null);
            $user->setPasswordResetExpiresAt(null);
            $user->setPasswordResetLockedUntil((clone $now)->modify('+' . self::PASSWORD_RESET_LOCK_MINUTES . ' minutes'));
            $this->entityManager->flush();
            return new JsonResponse(['error' => 'Zu viele Fehlversuche. Bitte neuen Code anfordern.'], 429);
        }

        $providedHash = $this->hashPasswordResetCode($email, $code);
        if (!hash_equals($storedHash, $providedHash)) {
            $attempts = $user->getPasswordResetAttemptCount() + 1;
            $user->setPasswordResetAttemptCount($attempts);
            if ($attempts >= self::PASSWORD_RESET_MAX_ATTEMPTS) {
                $user->setPasswordResetCodeHash(null);
                $user->setPasswordResetExpiresAt(null);
                $user->setPasswordResetLockedUntil((clone $now)->modify('+' . self::PASSWORD_RESET_LOCK_MINUTES . ' minutes'));
            }
            $this->entityManager->flush();
            return new JsonResponse(['error' => 'Code ungueltig oder abgelaufen'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setPasswordResetCodeHash(null);
        $user->setPasswordResetExpiresAt(null);
        $user->setPasswordResetAttemptCount(0);
        $user->setPasswordResetLockedUntil(null);
        $user->setPasswordResetLastRequestedAt(null);
        $user->setPasswordResetWindowStartedAt(null);
        $user->setPasswordResetRequestCount(0);

        $this->auditLogger->log(
            'user',
            $user->getId(),
            'user_password_reset',
            null,
            $user,
            null,
            [
                'source' => ['old' => null, 'new' => 'password_reset_code'],
            ]
        );
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Passwort wurde erfolgreich zurueckgesetzt.'
        ]);
    }

    private function hashPasswordResetCode(string $email, string $code): string
    {
        return hash('sha256', strtolower($email) . '|' . strtoupper($code) . '|' . $this->appSecret);
    }
}
