<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Controller\PublicDisplayController;
use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DepartmentDisplaySessionService;
use App\Service\Display\DisplayAccessCodeGenerator;
use App\Service\Display\DisplayDeviceExpiryReminderService;
use App\Service\Display\DisplayRateLimiter;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\MembershipNotificationEmailResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * DB-gestützt in einer zurückgerollten Transaktion; Department b57aa6184ef5 (Demo) hat Verwalter (dc, mw).
 */
final class DisplayDeviceReminderAndMigrationTest extends TestCase
{
    private const DEPT = 'b57aa6184ef5';

    private EntityManagerInterface $em;
    private DepartmentDisplayScreenService $screens;
    private DepartmentDisplayDeviceService $devices;

    protected function setUp(): void
    {
        $kernel = new \App\Kernel('dev', false);
        $kernel->boot();
        $this->em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->beginTransaction();
        $this->screens = new DepartmentDisplayScreenService($this->em, new DisplayAccessCodeGenerator(), 'https://app.ematchef.test');
        $this->devices = new DepartmentDisplayDeviceService($this->em, $this->screens, true);
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
    }

    public function testRemindersAt14And3DaysWithoutDuplicatesAndNewCycleAfterExtension(): void
    {
        $screen = $this->screens->create(self::DEPT, 'Reminder', null)['screen'];
        $device = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING, null, new \DateTime('+10 days'))['device'];
        $mails = 0;
        $service = $this->reminderService($mails);
        $inbox = fn (): int => (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM inbox_message WHERE type = 'display_approval_expiry' AND payload::text LIKE ?", ['%' . $device->getId() . '%']);

        $marks = fn (): array => [
            $this->em->getConnection()->fetchOne('SELECT reminder14_for FROM department_display_device WHERE id = ?', [$device->getId()]),
            $this->em->getConnection()->fetchOne('SELECT reminder3_for FROM department_display_device WHERE id = ?', [$device->getId()]),
        ];

        $first = $service->process();
        self::assertGreaterThanOrEqual(1, $first['devices']);
        $after14 = $inbox();
        self::assertGreaterThan(0, $after14);
        self::assertSame($after14, $mails, 'je Empfänger eine Inbox-Nachricht und eine E-Mail (nur dieses Gerät ist fällig)');
        [$m14, $m3] = $marks();
        self::assertNotNull($m14);
        self::assertNull($m3);

        // Gleiche Frist, gleicher Zeitraum: keine Duplikate.
        $service->process();
        self::assertSame($after14, $inbox());

        // 8 Tage später: nur noch 2 Tage Restlaufzeit → zweite Erinnerung (3 Tage), danach nichts mehr.
        $later = (new \DateTime())->modify('+8 days');
        $service->process($later);
        self::assertSame($after14 * 2, $inbox());
        [, $m3] = $marks();
        self::assertNotNull($m3);
        $service->process($later);
        self::assertSame($after14 * 2, $inbox());

        // Verlängerung: neues Ablaufdatum, neuer Zyklus (14 Tage vor dem neuen Ablauf).
        $this->devices->extend($device);
        $service->process();
        self::assertSame($after14 * 2, $inbox(), 'neues Ablaufdatum liegt ausserhalb der 14 Tage');
        $service->process((new \DateTime())->modify('+80 days'));
        self::assertSame($after14 * 3, $inbox(), 'neuer Erinnerungszyklus nach Verlängerung');
    }

    public function testNoRemindersForRevokedDevicesRevokedScreensOrExpiredApprovals(): void
    {
        $screen = $this->screens->create(self::DEPT, 'Reminder2', null)['screen'];
        $revoked = $this->devices->create($screen, 'A', DepartmentDisplayDevice::VIA_PAIRING, null, new \DateTime('+5 days'))['device'];
        $this->devices->revoke($revoked);
        $this->devices->create($screen, 'B', DepartmentDisplayDevice::VIA_PAIRING, null, new \DateTime('-1 day'));
        $mails = 0;
        $this->reminderService($mails)->process();

        $count = fn (string $name): int => (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM inbox_message WHERE type = 'display_approval_expiry' AND payload::text LIKE ?",
            ['%' . $name . '%'],
        );
        self::assertSame(0, $count($revoked->getId()), 'widerrufenes Gerät');
        $expiredId = (string) $this->em->getConnection()->fetchOne("SELECT id FROM department_display_device WHERE screen_id = ? AND name = 'B'", [$screen->getId()]);
        self::assertSame(0, $count($expiredId), 'abgelaufene Freigabe');
    }

    public function testLegacySessionMigratesToIndependentDeviceWithoutExtendingApproval(): void
    {
        $screen = $this->screens->create(self::DEPT, 'Legacy', null)['screen'];
        $session = new DepartmentDisplaySessionService('test-secret', true, '.ematchef.test');
        $cookie = $session->createCookie($screen);
        $controller = $this->controller($session);
        $request = new Request(cookies: [$cookie->getName() => $cookie->getValue()]);

        $response = $controller->session($screen->getPublicId(), $request);
        self::assertSame(200, $response->getStatusCode());
        $names = array_map(static fn ($c) => $c->getName(), $response->headers->getCookies());
        self::assertContains(DepartmentDisplayDeviceService::COOKIE_NAME, $names);
        self::assertContains($cookie->getName(), $names, 'old per-screen cookie is cleared');
        self::assertContains(DepartmentDisplaySessionService::LEGACY_COOKIE_NAME, $names, 'old shared cookie is cleared');

        $devices = $this->em->getRepository(DepartmentDisplayDevice::class)->findBy(['screenId' => $screen->getId()]);
        self::assertCount(1, $devices);
        self::assertSame(DepartmentDisplayDevice::VIA_MIGRATED, $devices[0]->getCreatedVia());
        self::assertLessThanOrEqual(time() + 90 * 86400 + 5, $devices[0]->getApprovalExpiresAt()->getTimestamp());

        // Ungültige oder fehlende Alt-Cookies erzeugen nichts.
        self::assertSame(401, $controller->session($screen->getPublicId(), new Request())->getStatusCode());
        self::assertSame(401, $controller->session($screen->getPublicId(), new Request(cookies: [$cookie->getName() => 'x.y']))->getStatusCode());
        self::assertCount(1, $this->em->getRepository(DepartmentDisplayDevice::class)->findBy(['screenId' => $screen->getId()]));
    }

    public function testMigrationNeverOutlivesTheOldCookie(): void
    {
        $screen = $this->screens->create(self::DEPT, 'Legacy2', null)['screen'];
        $session = new DepartmentDisplaySessionService('test-secret', true, null);
        // Alt-Cookie, das in 5 Tagen abläuft
        $ref = new \ReflectionMethod($session, 'signPayload');
        $value = $ref->invoke($session, ['sid' => $screen->getId(), 'pid' => $screen->getPublicId(), 'v' => $screen->getCodeVersion(), 'exp' => time() + 5 * 86400]);
        $this->controller($session)->session($screen->getPublicId(), new Request(cookies: [DepartmentDisplaySessionService::cookieName($screen->getPublicId()) => $value]));

        $device = $this->em->getRepository(DepartmentDisplayDevice::class)->findOneBy(['screenId' => $screen->getId()]);
        self::assertNotNull($device);
        self::assertLessThanOrEqual(time() + 5 * 86400 + 5, $device->getApprovalExpiresAt()->getTimestamp());
    }

    private function controller(DepartmentDisplaySessionService $session): PublicDisplayController
    {
        return new PublicDisplayController($this->screens, $session, $this->devices, new ArrayAdapter());
    }

    private function reminderService(int &$mails): DisplayDeviceExpiryReminderService
    {
        $resolver = $this->createMock(MembershipNotificationEmailResolver::class);
        $resolver->method('effectiveEmail')->willReturn('verantwortlich@example.test');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function () use (&$mails): void {
            ++$mails;
        });
        $settings = $this->createMock(MailOutboundSettingsStore::class);
        $settings->method('getFromAddressObject')->willReturn(new Address('noreply@example.test'));

        return new DisplayDeviceExpiryReminderService($this->em, $resolver, $mailer, $settings, new NullLogger(), 'https://app.ematchef.test');
    }
}
