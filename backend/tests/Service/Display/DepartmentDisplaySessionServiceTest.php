<?php

declare(strict_types=1);

namespace App\Tests\Service\Display;

use App\Entity\DepartmentDisplayScreen;
use App\Service\Display\DepartmentDisplaySessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DepartmentDisplaySessionServiceTest extends TestCase
{
    public function testEachScreenGetsItsOwnHostBoundCookie(): void
    {
        $service = $this->service();
        $a = $service->createCookie($this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa'));
        $b = $service->createCookie($this->screen('dsibbbbbbbbb', 'dspbbbbbbbbb'));

        self::assertNotSame($a->getName(), $b->getName());
        self::assertNull($a->getDomain());
        self::assertTrue($a->isHttpOnly());
        self::assertSame('/api/public/display/dsiaaaaaaaaa', $a->getPath());
        self::assertGreaterThan(time() + 89 * 86400, $a->getExpiresTime());
    }

    public function testTwoScreensInOneBrowserStayValid(): void
    {
        $service = $this->service();
        $screenA = $this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa');
        $screenB = $this->screen('dsibbbbbbbbb', 'dspbbbbbbbbb');
        $a = $service->createCookie($screenA);
        $b = $service->createCookie($screenB);
        $request = new Request(cookies: [$a->getName() => $a->getValue(), $b->getName() => $b->getValue()]);

        self::assertNotNull($service->resolveScreenFromRequest($request, 'dsiaaaaaaaaa', $screenA));
        self::assertNotNull($service->resolveScreenFromRequest($request, 'dsibbbbbbbbb', $screenB));
    }

    public function testCookieOfOneScreenDoesNotOpenAnother(): void
    {
        $service = $this->service();
        $screenA = $this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa');
        $screenB = $this->screen('dsibbbbbbbbb', 'dspbbbbbbbbb');
        $a = $service->createCookie($screenA);
        // Cookie von A unter dem Namen von B eingeschleust
        $request = new Request(cookies: [DepartmentDisplaySessionService::cookieName('dsibbbbbbbbb') => $a->getValue()]);

        self::assertNull($service->resolveScreenFromRequest($request, 'dsibbbbbbbbb', $screenB));
    }

    public function testCodeRotationAndRevocationInvalidateSession(): void
    {
        $service = $this->service();
        $screen = $this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa');
        $cookie = $service->createCookie($screen);
        $request = new Request(cookies: [$cookie->getName() => $cookie->getValue()]);
        self::assertNotNull($service->resolveScreenFromRequest($request, 'dsiaaaaaaaaa', $screen));

        $screen->incrementCodeVersion();
        self::assertNull($service->resolveScreenFromRequest($request, 'dsiaaaaaaaaa', $screen));

        $screen = $this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa');
        $screen->setRevokedAt(new \DateTime());
        self::assertNull($service->resolveScreenFromRequest($request, 'dsiaaaaaaaaa', $screen));
    }

    public function testLegacyCookieIsStillAccepted(): void
    {
        $service = $this->service();
        $screen = $this->screen('dsiaaaaaaaaa', 'dspaaaaaaaaa');
        $cookie = $service->createCookie($screen);
        $request = new Request(cookies: [DepartmentDisplaySessionService::LEGACY_COOKIE_NAME => $cookie->getValue()]);

        self::assertNotNull($service->resolveScreenFromRequest($request, 'dsiaaaaaaaaa', $screen));
    }

    private function service(): DepartmentDisplaySessionService
    {
        return new DepartmentDisplaySessionService('test-secret', true);
    }

    private function screen(string $publicId, string $id): DepartmentDisplayScreen
    {
        return (new DepartmentDisplayScreen())->setId($id)->setPublicId($publicId)->setDepartmentId('deptest00001')->setName('T');
    }
}
