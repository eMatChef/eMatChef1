<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuditLoggerTest extends TestCase
{
    /** @var list<AuditEvent> */
    private array $persisted = [];

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

    public function testSecurityEventStoresServerSideIpAndUserAgent(): void
    {
        $event = $this->logLogin($this->request('198.51.100.7', userAgent: "Mozilla/5.0\n(X11; Linux)"));

        self::assertSame('198.51.100.7', $event->getIpAddress());
        self::assertSame('Mozilla/5.0 (X11; Linux)', $event->getUserAgent());
    }

    public function testForwardedForFromUntrustedClientIsIgnored(): void
    {
        $event = $this->logLogin($this->request('198.51.100.7', forwardedFor: '1.2.3.4'));

        self::assertSame('198.51.100.7', $event->getIpAddress());
    }

    public function testForwardedForFromTrustedProxyIsUsedButClientSuppliedPrefixIsNot(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR);

        $viaProxy = $this->logLogin($this->request('10.0.0.5', forwardedFor: '203.0.113.9'));
        $spoofed = $this->logLogin($this->request('10.0.0.5', forwardedFor: '1.2.3.4, 203.0.113.9'));
        $fromOutside = $this->logLogin($this->request('198.51.100.7', forwardedFor: '1.2.3.4'));

        self::assertSame('203.0.113.9', $viaProxy->getIpAddress());
        self::assertSame('203.0.113.9', $spoofed->getIpAddress());
        self::assertSame('198.51.100.7', $fromOutside->getIpAddress());
    }

    public function testOtherForwardedHeadersAreNotTrusted(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR);
        $request = $this->request('10.0.0.5', forwardedFor: '203.0.113.9');
        $request->headers->set('X-Real-IP', '9.9.9.9');
        $request->headers->set('Forwarded', 'for=8.8.8.8');

        self::assertSame('203.0.113.9', $this->logLogin($request)->getIpAddress());
    }

    public function testNoRequestMeansNoIp(): void
    {
        $event = $this->logLogin(null);

        self::assertNull($event->getIpAddress());
        self::assertNull($event->getUserAgent());
    }

    public function testNonSecurityEventsDoNotRecordRequestContext(): void
    {
        $logger = $this->logger($this->request('198.51.100.7'));
        $logger->log('membership', 'm1', 'department_renamed', null, null, null, []);

        self::assertNull($this->persisted[0]->getIpAddress());
        self::assertNull($this->persisted[0]->getUserAgent());
    }

    public function testSensitiveChangeKeysAreRedacted(): void
    {
        $logger = $this->logger(null);
        $logger->log('user', 'u1', 'user_password_changed', null, null, null, [
            'password' => ['old' => 'hunter2', 'new' => 'hunter3'],
            'totp_secret' => ['old' => null, 'new' => 'JBSWY3DP'],
            'refresh_token' => ['old' => 'abc', 'new' => null],
            'source' => ['old' => null, 'new' => 'profile_change'],
        ]);

        $changes = $this->persisted[0]->getChanges();
        $json = json_encode($changes, JSON_THROW_ON_ERROR);
        foreach (['hunter2', 'hunter3', 'JBSWY3DP', '"abc"'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
        self::assertSame('profile_change', $changes['source']['new']);
    }

    public function testProfileUpdatesStoreFieldNamesOnly(): void
    {
        $logger = $this->logger(null);
        $logger->log('profile', 'p1', 'profile_updated', null, null, null, [
            'first_name' => ['old' => 'Anna', 'new' => 'Berta'],
            'language' => ['old' => 'de', 'new' => 'fr'],
        ]);

        self::assertSame(['first_name' => ['changed' => true], 'language' => ['changed' => true]], $this->persisted[0]->getChanges());
    }

    private function logLogin(?Request $request): AuditEvent
    {
        $this->logger($request)->log('user', 'u1', 'login_success', null, (new User())->setId('u1'), null, [
            'auth_method' => ['old' => null, 'new' => 'password'],
        ]);

        return $this->persisted[\count($this->persisted) - 1];
    }

    private function logger(?Request $request): AuditLogger
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $stack = new RequestStack();
        if ($request !== null) {
            $stack->push($request);
        }

        return new AuditLogger($em, $stack);
    }

    private function request(string $remote, ?string $forwardedFor = null, string $userAgent = 'Test'): Request
    {
        $server = ['REMOTE_ADDR' => $remote, 'HTTP_USER_AGENT' => $userAgent];
        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        return Request::create('/api/test', 'GET', [], [], [], $server);
    }
}
