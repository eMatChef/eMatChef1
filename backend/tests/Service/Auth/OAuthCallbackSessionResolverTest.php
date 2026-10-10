<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\User;
use App\Entity\UserSession;
use App\Enum\AuthMethod;
use App\Repository\UserSessionRepository;
use App\Service\Auth\OAuthCallbackSessionResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OAuthCallbackSessionResolverTest extends TestCase
{
    private User $user;

    private UserSession $session;

    /** @var array<string, mixed>|\Throwable */
    private mixed $decoded = [];

    protected function setUp(): void
    {
        $this->user = (new User())->setId('user00000001')->setProfileId('p0000000001')->setState('active');
        $this->session = new UserSession($this->user, AuthMethod::PASSWORD, 'UA');
        $this->decoded = ['username' => $this->user->getUserIdentifier(), 'sid' => $this->session->getId()];
    }

    public function testValidCookieResolvesTheSession(): void
    {
        self::assertSame($this->session, $this->resolver()->resolve($this->request('tok')));
    }

    public function testMissingExpiredOrTamperedCookieResolvesNothing(): void
    {
        self::assertNull($this->resolver()->resolve($this->request(null)));

        $this->decoded = new \RuntimeException('expired');
        self::assertNull($this->resolver()->resolve($this->request('expired')));

        $this->decoded = new \RuntimeException('invalid signature');
        self::assertNull($this->resolver()->resolve($this->request('tampered')));
    }

    public function testRevokedForeignUnknownAndInactiveSessionsResolveNothing(): void
    {
        $this->session->revoke('test');
        self::assertNull($this->resolver()->resolve($this->request('tok')));

        $this->session = new UserSession($this->user, AuthMethod::PASSWORD, 'UA');
        $this->decoded = ['username' => 'someone-else', 'sid' => $this->session->getId()];
        self::assertNull($this->resolver()->resolve($this->request('tok')), 'Token-User passt nicht zur Sitzung');

        $this->decoded = ['username' => $this->user->getUserIdentifier(), 'sid' => 'unknown-sid'];
        self::assertNull($this->resolver()->resolve($this->request('tok')));

        $this->decoded = ['username' => $this->user->getUserIdentifier()];
        self::assertNull($this->resolver()->resolve($this->request('tok')), 'Legacy-JWT ohne sid');

        $this->decoded = ['username' => $this->user->getUserIdentifier(), 'sid' => $this->session->getId()];
        $this->user->setState('inactive');
        self::assertNull($this->resolver()->resolve($this->request('tok')));
    }

    private function resolver(): OAuthCallbackSessionResolver
    {
        $encoder = $this->createMock(JWTEncoderInterface::class);
        $encoder->method('decode')->willReturnCallback(function (): array {
            if ($this->decoded instanceof \Throwable) {
                throw $this->decoded;
            }

            return $this->decoded;
        });
        $sessions = $this->createMock(UserSessionRepository::class);
        $sessions->method('findOneById')->willReturnCallback(fn (string $id): ?UserSession => $id === $this->session->getId() ? $this->session : null);

        return new OAuthCallbackSessionResolver($encoder, $sessions);
    }

    private function request(?string $cookie): Request
    {
        $request = Request::create('/api/auth/google/callback');
        if ($cookie !== null) {
            $request->cookies->set('BEARER', $cookie);
        }

        return $request;
    }
}
