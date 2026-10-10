<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Entity\DepartmentDisplayScreen;
use App\Entity\DisplayPairingRequest;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use App\Service\Display\DisplayAccessCodeGenerator;
use App\Service\Display\DisplayPairingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Läuft gegen die lokale DB, jeder Test in einer Transaktion, die zurückgerollt wird.
 */
final class DisplayPairingServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private bool $canManage = true;

    protected function setUp(): void
    {
        $kernel = new \App\Kernel('dev', false);
        $kernel->boot();
        $this->em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
    }

    public function testPairingIsSingleUseAndTvOnlyGetsSessionAfterApproval(): void
    {
        $screen = $this->createScreen();
        $created = $this->service()->create();
        $request = $created['request'];

        self::assertNotSame($created['token'], $request->getTokenHash());
        self::assertSame(hash('sha256', $created['token']), $request->getTokenHash());
        self::assertNull($this->findScreenIn($this->service()->poll($request->getId(), $created['poll_secret'])));
        self::assertSame('pending', $this->service()->poll($request->getId(), $created['poll_secret'])['status']);

        self::assertSame(DisplayPairingService::APPROVE_OK, $this->service()->approve($created['token'], $this->user(), $screen->getId()));
        self::assertSame(DisplayPairingService::APPROVE_GONE, $this->service()->approve($created['token'], $this->user(), $screen->getId()));

        $polled = $this->service()->poll($request->getId(), $created['poll_secret']);
        self::assertSame('approved', $polled['status']);
        self::assertSame($screen->getId(), $polled['screen']->getId());

        $again = $this->service()->poll($request->getId(), $created['poll_secret']);
        self::assertSame('expired', $again['status']);
        self::assertArrayNotHasKey('screen', $again);
    }

    public function testTvCannotCompleteSomeoneElsesPairing(): void
    {
        $screen = $this->createScreen();
        $mine = $this->service()->create();
        $other = $this->service()->create();
        $this->service()->approve($other['token'], $this->user(), $screen->getId());

        self::assertNull($this->service()->poll($other['request']->getId(), $mine['poll_secret']));
        self::assertNull($this->service()->poll($other['request']->getId(), $other['token']));
        self::assertNull($this->service()->poll('doesnotexist', $mine['poll_secret']));
    }

    public function testUnauthorizedUserCannotPair(): void
    {
        $screen = $this->createScreen();
        $created = $this->service()->create();
        $this->canManage = false;

        self::assertSame(DisplayPairingService::APPROVE_FORBIDDEN, $this->service()->approve($created['token'], $this->user(), $screen->getId()));
        self::assertSame('pending', $this->service()->poll($created['request']->getId(), $created['poll_secret'])['status']);
    }

    public function testExpiredRequestCannotBeApprovedOrPolled(): void
    {
        $screen = $this->createScreen();
        $created = $this->service()->create();
        $this->em->getConnection()->executeStatement(
            "UPDATE display_pairing_request SET expires_at = NOW() - INTERVAL '1 minute' WHERE id = ?",
            [$created['request']->getId()],
        );
        $this->em->clear();

        self::assertSame(DisplayPairingService::APPROVE_GONE, $this->service()->approve($created['token'], $this->user(), $screen->getId()));
        self::assertSame('expired', $this->service()->poll($created['request']->getId(), $created['poll_secret'])['status']);
    }

    public function testRevokedScreenIsNotIssuedAfterApproval(): void
    {
        $screen = $this->createScreen();
        $created = $this->service()->create();
        $this->service()->approve($created['token'], $this->user(), $screen->getId());
        $screen->setRevokedAt(new \DateTime());
        $this->em->flush();

        self::assertSame('revoked', $this->service()->poll($created['request']->getId(), $created['poll_secret'])['status']);
    }

    public function testRevokedScreenCannotBeApproved(): void
    {
        $screen = $this->createScreen();
        $screen->setRevokedAt(new \DateTime());
        $this->em->flush();
        $created = $this->service()->create();

        self::assertSame(DisplayPairingService::APPROVE_NOT_FOUND, $this->service()->approve($created['token'], $this->user(), $screen->getId()));
    }

    public function testPairUrlUsesAppOriginAndPathToken(): void
    {
        self::assertMatchesRegularExpression('#^https?://[^/]+/connect-display/abc$#', $this->service()->buildPairUrl('abc'));
    }

    /** @param array<string, mixed>|null $result */
    private function findScreenIn(?array $result): ?DepartmentDisplayScreen
    {
        return $result['screen'] ?? null;
    }

    private function service(): DisplayPairingService
    {
        $test = $this;
        $screenService = $this->getMockBuilder(DepartmentDisplayScreenService::class)
            ->setConstructorArgs([$this->em, new DisplayAccessCodeGenerator(), 'https://app.ematchef.test'])
            ->onlyMethods(['canManageDepartment'])
            ->getMock();
        $screenService->method('canManageDepartment')->willReturnCallback(fn () => $test->canManage);

        return new DisplayPairingService($this->em, $screenService, 'https://app.ematchef.test');
    }

    private function user(): User
    {
        return (new User())->setId('usrtestpair1');
    }

    private function createScreen(): DepartmentDisplayScreen
    {
        $screen = (new DepartmentDisplayScreen())
            ->setId('dsptest' . substr(bin2hex(random_bytes(3)), 0, 5))
            ->setPublicId('dsitest' . substr(bin2hex(random_bytes(3)), 0, 5))
            ->setDepartmentId('deptest00001')
            ->setName('Test')
            ->setAccessCodeHash('x');
        $this->em->persist($screen);
        $this->em->flush();

        return $screen;
    }
}
