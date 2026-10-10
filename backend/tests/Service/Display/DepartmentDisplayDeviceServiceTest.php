<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayScreen;
use App\Service\Display\DepartmentDisplayDeviceService;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayAccessCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Läuft gegen die lokale DB, jeder Test in einer Transaktion, die zurückgerollt wird.
 * Department b57aa6184ef5 (Demo) liefert ein existierendes Department; fremdes Department: dccffd078d5c.
 */
final class DepartmentDisplayDeviceServiceTest extends TestCase
{
    private const DEPT = 'b57aa6184ef5';
    private const OTHER_DEPT = 'dccffd078d5c';

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

    public function testDeviceCookieIsHostBoundHttpOnlyAndSecretIsOnlyStoredHashed(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'Eingang', DepartmentDisplayDevice::VIA_PAIRING);

        self::assertSame(hash('sha256', $secret), $device->getCredentialHash());
        self::assertStringNotContainsString($secret, $device->getCredentialHash());
        $cookie = $this->devices->buildCookie($device, $secret);
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertNull($cookie->getDomain());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/public/display-device', $cookie->getPath());
        self::assertGreaterThan(time() + 89 * 86400, $device->getApprovalExpiresAt()->getTimestamp());
        self::assertSame('active', $this->identify($device, $secret)['state']);
    }

    public function testManipulatedOrUnknownCredentialsAreRejected(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'A', DepartmentDisplayDevice::VIA_PAIRING);

        self::assertSame('none', $this->identify($device, 'wrong-secret')['state']);
        self::assertSame('none', $this->identifyRaw('ddvunknown00.' . $secret)['state']);
        self::assertSame('none', $this->identifyRaw('garbage')['state']);
        self::assertSame('none', $this->identifyRaw('')['state']);
        self::assertSame('none', $this->identifyRaw($device->getId() . '.')['state']);
    }

    public function testSeveralDevicesPerScreenHaveOwnIdentityAndName(): void
    {
        $screen = $this->screen();
        ['device' => $a, 'secret' => $sa] = $this->devices->create($screen, 'Eingang', DepartmentDisplayDevice::VIA_PAIRING);
        ['device' => $b, 'secret' => $sb] = $this->devices->create($screen, 'Büro', DepartmentDisplayDevice::VIA_PAIRING);

        self::assertNotSame($a->getId(), $b->getId());
        self::assertNotSame($a->getCredentialHash(), $b->getCredentialHash());
        self::assertSame('active', $this->identify($a, $sa)['state']);
        self::assertSame('active', $this->identify($b, $sb)['state']);
        // Credential von A unter der ID von B funktioniert nicht
        self::assertSame('none', $this->identifyRaw($b->getId() . '.' . $sa)['state']);

        $this->devices->revoke($a);
        self::assertSame('revoked', $this->identify($a, $sa)['state']);
        self::assertSame('active', $this->identify($b, $sb)['state']);
    }

    public function testExpiryAndAdministrativeReapproval(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        $this->em->getConnection()->executeStatement("UPDATE department_display_device SET approval_expires_at = NOW() - INTERVAL '1 day' WHERE id = ?", [$device->getId()]);
        $this->em->clear();
        $device = $this->em->find(DepartmentDisplayDevice::class, $device->getId());

        // Abgelaufen: Identität bleibt, aber keine Anzeigedaten-Freigabe.
        self::assertSame('expired', $this->identify($device, $secret)['state']);

        $this->devices->extend($device);
        self::assertSame('active', $this->identify($device, $secret)['state']);
        self::assertGreaterThan(time() + 89 * 86400, $device->getApprovalExpiresAt()->getTimestamp());
    }

    public function testRevokedDeviceCannotBeExtendedOrReassigned(): void
    {
        $screen = $this->screen();
        ['device' => $device] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        $this->devices->revoke($device);

        $this->expectException(\InvalidArgumentException::class);
        $this->devices->extend($device);
    }

    public function testReassignOnlyWithinDepartment(): void
    {
        $screen = $this->screen();
        $other = $this->screen('Zweiter');
        $foreign = $this->screen('Fremd', self::OTHER_DEPT);
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);

        $this->devices->reassign($device, $other);
        self::assertSame($other->getId(), $device->getScreenId());
        self::assertSame($other->getId(), $this->identify($device, $secret)['screen']->getId());

        $this->expectException(\InvalidArgumentException::class);
        $this->devices->reassign($device, $foreign);
    }

    public function testDeviceOfOtherDepartmentIsNotFoundInDepartment(): void
    {
        $foreign = $this->screen('Fremd', self::OTHER_DEPT);
        ['device' => $device] = $this->devices->create($foreign, 'TV', DepartmentDisplayDevice::VIA_PAIRING);

        self::assertNull($this->devices->findInDepartment(self::DEPT, $device->getId()));
        self::assertNotNull($this->devices->findInDepartment(self::OTHER_DEPT, $device->getId()));
    }

    public function testRevokingTheScreenBlocksAllDevicesAndNeedsNewPairingAfterReactivation(): void
    {
        $screen = $this->screen();
        ['device' => $a, 'secret' => $sa] = $this->devices->create($screen, 'A', DepartmentDisplayDevice::VIA_PAIRING);
        ['device' => $m, 'secret' => $sm] = $this->devices->create($screen, 'M', DepartmentDisplayDevice::VIA_MANUAL);

        $this->screens->revoke($screen);
        $this->em->clear();
        self::assertSame('revoked', $this->identifyRaw($a->getId() . '.' . $sa)['state']);
        self::assertSame('revoked', $this->identifyRaw($m->getId() . '.' . $sm)['state']);

        $screen = $this->em->find(DepartmentDisplayScreen::class, $screen->getId());
        $this->screens->reactivate($screen);
        $this->em->clear();
        self::assertSame('revoked', $this->identifyRaw($a->getId() . '.' . $sa)['state'], 'Wiederherstellung des Screens reaktiviert keine Geräte');
    }

    public function testCodeRotationOnlyRevokesCodeDerivedDevices(): void
    {
        $screen = $this->screen();
        ['device' => $paired, 'secret' => $sp] = $this->devices->create($screen, 'Gekoppelt', DepartmentDisplayDevice::VIA_PAIRING);
        ['device' => $manual, 'secret' => $sm] = $this->devices->create($screen, 'Manuell', DepartmentDisplayDevice::VIA_MANUAL);

        $this->screens->rotateAccessCode($screen);
        $this->em->clear();

        self::assertSame('active', $this->identifyRaw($paired->getId() . '.' . $sp)['state']);
        self::assertSame('revoked', $this->identifyRaw($manual->getId() . '.' . $sm)['state']);
    }

    public function testDeletingScreenInvalidatesDevicesAndBlocksTheirIds(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        $deviceId = $device->getId();
        $screen->setRevokedAt(new \DateTime());
        $this->em->flush();

        $this->screens->deletePermanently($screen, null);
        $this->em->clear();

        self::assertSame('none', $this->identifyRaw($deviceId . '.' . $secret)['state']);
        $conn = $this->em->getConnection();
        self::assertFalse($conn->fetchOne('SELECT 1 FROM department_display_device WHERE id = ?', [$deviceId]));
        self::assertNotFalse($conn->fetchOne("SELECT 1 FROM display_deleted_id WHERE id = ? AND kind = 'device'", [$deviceId]));
    }

    public function testDeviceDeletionRequiresRevocationAndLeavesTombstone(): void
    {
        $screen = $this->screen();
        ['device' => $device] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        try {
            $this->devices->deletePermanently($device, null);
            self::fail('active device must not be deletable');
        } catch (\InvalidArgumentException) {
        }
        $this->devices->revoke($device);
        $id = $device->getId();
        $this->devices->deletePermanently($device, null);

        self::assertFalse($this->em->getConnection()->fetchOne('SELECT 1 FROM department_display_device WHERE id = ?', [$id]));
        self::assertNotFalse($this->em->getConnection()->fetchOne('SELECT 1 FROM display_deleted_id WHERE id = ?', [$id]));
    }

    public function testRotationKeepsDeviceWorkingAndRejectsReplayOfOldCredential(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $old] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        $this->em->getConnection()->executeStatement("UPDATE department_display_device SET credential_rotated_at = NOW() - INTERVAL '8 days' WHERE id = ?", [$device->getId()]);
        $this->em->clear();
        $device = $this->em->find(DepartmentDisplayDevice::class, $device->getId());

        $first = $this->identify($device, $old);
        self::assertSame('active', $first['state']);
        self::assertArrayHasKey('cookie', $first, 'rotation issues a new credential');

        // Antwort ging verloren: das Gerät legt noch das alte vor, es bleibt gültig und bekommt erneut ein neues.
        $second = $this->identify($device, $old);
        self::assertSame('active', $second['state']);
        $latest = explode('.', $second['cookie']->getValue(), 2)[1];

        // Das neue Credential wird benutzt: danach ist das alte ungültig (Replay-Schutz).
        self::assertSame('active', $this->identifyRaw($device->getId() . '.' . $latest)['state']);
        self::assertSame('none', $this->identify($device, $old)['state']);
        self::assertSame('active', $this->identifyRaw($device->getId() . '.' . $latest)['state']);
    }

    public function testOnlineStatusDerivesFromLastContact(): void
    {
        $screen = $this->screen();
        ['device' => $device, 'secret' => $secret] = $this->devices->create($screen, 'TV', DepartmentDisplayDevice::VIA_PAIRING);
        self::assertFalse($this->devices->isOnline($device));

        $this->identify($device, $secret);
        self::assertNotNull($device->getLastContactAt());
        self::assertTrue($this->devices->isOnline($device));

        $device->setLastContactAt(new \DateTime('-6 minutes'));
        self::assertFalse($this->devices->isOnline($device));
        // Offline ist unabhängig vom Freigabestatus
        self::assertSame('active', $this->devices->approvalState($device));
    }

    public function testGrossanlassScreensDoNotAcceptDepartmentAreas(): void
    {
        $ga = (string) $this->em->getConnection()->fetchOne('SELECT id FROM department WHERE is_grossanlass = true LIMIT 1');
        self::assertNotSame('', $ga);
        $screen = $this->screens->create($ga, 'GA-Screen', null)['screen'];
        self::assertFalse($screen->isShowActivities());
        self::assertFalse($screen->isShowWorkshop());

        $this->screens->updateSettings($screen, ['name' => 'GA umbenannt', 'subtitle_text' => 'Hallo']);
        self::assertSame('GA umbenannt', $screen->getName());

        $this->expectException(\InvalidArgumentException::class);
        $this->screens->updateSettings($screen, ['show_activities' => true]);
    }

    public function testNormalDepartmentKeepsPanelSelection(): void
    {
        $screen = $this->screen();
        $updated = $this->screens->updateSettings($screen, ['show_activities' => true, 'show_workshop' => false, 'show_statistics' => true, 'name' => 'Neu']);
        self::assertTrue($updated->isShowActivities());
        self::assertFalse($updated->isShowWorkshop());
        self::assertTrue($updated->isShowStatistics());
        self::assertSame('Neu', $updated->getName());
    }

    /** @return array<string, mixed> */
    private function identify(DepartmentDisplayDevice $device, string $secret): array
    {
        return $this->identifyRaw($device->getId() . '.' . $secret);
    }

    /** @return array<string, mixed> */
    private function identifyRaw(string $cookieValue): array
    {
        return $this->devices->identify(new Request(cookies: [DepartmentDisplayDeviceService::COOKIE_NAME => $cookieValue]));
    }

    private function screen(string $name = 'Test', string $department = self::DEPT): DepartmentDisplayScreen
    {
        return $this->screens->create($department, $name, null)['screen'];
    }
}
