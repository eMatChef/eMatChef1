<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\AuthIntent;
use App\Service\Auth\GoogleOAuthState;
use App\Service\Auth\LinkResultStore;
use App\Service\Auth\MiDataOAuthState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class AuthIntentTest extends TestCase
{
    /** @return iterable<string, array{string|null, AuthIntent}> */
    public static function redirects(): iterable
    {
        yield 'no redirect is a normal login' => [null, AuthIntent::LOGIN];
        yield 'empty' => ['', AuthIntent::LOGIN];
        yield 'root' => ['/', AuthIntent::LOGIN];
        yield 'login page' => ['/login', AuthIntent::LOGIN];
        yield 'email invitation' => ['/pending-assignment?join_code=AB12CD34&invite_role=mw&invite_email=a%40b.ch&invite_id=inv1&department_id=d1&auto_join=1', AuthIntent::INVITATION];
        yield 'invitation id only' => ['/pending-assignment?invite_id=inv1&department_id=d1', AuthIntent::INVITATION];
        yield 'QR code / join code' => ['/pending-assignment?join_code=AB12CD34', AuthIntent::JOIN_CODE];
        yield 'MiData onboarding offer' => ['/pending-assignment?midata_onboarding=0a1b2c3d4e5f', AuthIntent::MIDATA_ONBOARDING];
        yield 'MiData candidate' => ['/pending-assignment?midata_candidate=0a1b2c3d4e5f', AuthIntent::MIDATA_ONBOARDING];
        yield 'protected department route' => ['/d0000000001/materials?x=1', AuthIntent::PROTECTED_ROUTE];
        yield 'pending page without intent' => ['/pending-assignment', AuthIntent::PROTECTED_ROUTE];
        yield 'group import settings' => ['/settings/my-department?midata_group_import=d1', AuthIntent::PROTECTED_ROUTE];
    }

    #[DataProvider('redirects')]
    public function testClassifiesTheReturnContext(?string $redirect, AuthIntent $expected): void
    {
        self::assertSame($expected, AuthIntent::fromRedirect($redirect));
    }

    public function testSanitizeAcceptsOnlyInternalPathsAndStripsServerReservedParams(): void
    {
        self::assertSame('/pending-assignment?join_code=X', AuthIntent::sanitizeRedirect('/pending-assignment?join_code=X'));
        foreach (['https://evil.example', '//evil.example', '/\\evil', "/a\nb", 'javascript:alert(1)', 'pending', ''] as $bad) {
            self::assertNull(AuthIntent::sanitizeRedirect($bad), $bad);
        }
        self::assertSame('/dept?x=1', AuthIntent::sanitizeRedirect('/dept?x=1&profile_security=1&oauth=linked&provider=google&reason=denied'));
        self::assertSame('/dept', AuthIntent::sanitizeRedirect('/dept?profile_security=1'));
        self::assertSame('/dept?a=1#frag', AuthIntent::sanitizeRedirect('/dept?profile_security=1&a=1#frag'));
    }

    public function testEncodedReservedKeysAreStrippedToo(): void
    {
        self::assertSame('/dept', AuthIntent::sanitizeRedirect('/dept?profile%5Fsecurity=1'));
    }

    public function testIntentIsFixedServerSideInTheSignedStateAndNeverComesFromTheClient(): void
    {
        $google = new GoogleOAuthState('secret');
        $login = $google->issue(null);
        $invite = $google->issue('/pending-assignment?join_code=AB&invite_id=i1');
        $link = $google->issue('/dashboard', 'user1', 'sid1');

        self::assertSame(AuthIntent::LOGIN, $google->verifyDetailed($login['cookieValue'], $login['token'])['intent']);
        self::assertSame(AuthIntent::INVITATION, $google->verifyDetailed($invite['cookieValue'], $invite['token'])['intent']);
        self::assertSame(AuthIntent::LINK_IDENTITY, $google->verifyDetailed($link['cookieValue'], $link['token'])['intent']);

        $midata = new MiDataOAuthState('secret');
        $onboarding = $midata->issue('/pending-assignment?midata_onboarding=0a1b2c3d4e5f', 'user1', 'sid1');
        $profile = $midata->issue('/dashboard', 'user1', 'sid1', true);
        $normal = $midata->issue('/d0000000001/materials');
        self::assertSame(AuthIntent::MIDATA_ONBOARDING, $midata->verify($onboarding['cookieValue'], $onboarding['token'])['intent']);
        self::assertSame(AuthIntent::LINK_IDENTITY, $midata->verify($profile['cookieValue'], $profile['token'])['intent']);
        self::assertSame(AuthIntent::PROTECTED_ROUTE, $midata->verify($normal['cookieValue'], $normal['token'])['intent']);
    }

    public function testStatesRefuseToCarryTheProfileSecurityReturnMarkerFromAUserSuppliedRedirect(): void
    {
        $google = new GoogleOAuthState('secret');
        $midata = new MiDataOAuthState('secret');

        self::assertSame('/dashboard', $google->sanitizeRedirect('/dashboard?profile_security=1'));
        self::assertSame('/dashboard', $midata->sanitizeRedirect('/dashboard?profile_security=1&oauth=linked'));
    }

    public function testLinkResultStoreIsOneTimeAndBoundToTheSession(): void
    {
        $store = new LinkResultStore(new ArrayAdapter());
        $store->put('sid-a', 'google', 'linked', null);

        self::assertNull($store->take('sid-b'));
        self::assertSame(['provider' => 'google', 'status' => 'linked', 'reason' => null], $store->take('sid-a'));
        self::assertNull($store->take('sid-a'));
    }

    public function testExpiredOrTamperedIntentStateIsRejectedSoTheNormalStartPageApplies(): void
    {
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $signed = static fn (array $payload): string => (static function (string $json) use ($b64): string {
            return $b64($json) . '.' . hash_hmac('sha256', $json, 'secret');
        })((string) json_encode($payload));

        $google = new GoogleOAuthState('secret');
        $expiredGoogle = $signed(['n' => 'state-1', 'r' => '/pending-assignment?join_code=AB', 'intent' => 'invitation', 'exp' => time() - 5]);
        self::assertNull($google->verifyDetailed($expiredGoogle, 'state-1'));
        self::assertNull($google->verify($expiredGoogle, 'state-1'));

        $midata = new MiDataOAuthState('secret');
        $expiredMiData = $signed(['state' => 'state-2', 'nonce' => 'n', 'verifier' => 'v', 'redirect' => '/dashboard', 'intent' => 'protected_route', 'exp' => time() - 5]);
        self::assertNull($midata->verify($expiredMiData, 'state-2'));

        // Ein manipulierter Intent im Cookie bricht die Signatur
        $issued = $google->issue('/dashboard');
        [$payload, $signature] = explode('.', $issued['cookieValue']);
        $tampered = $b64(str_replace('protected_route', 'link_identity', (string) base64_decode(strtr($payload, '-_', '+/')))) . '.' . $signature;
        self::assertNull($google->verifyDetailed($tampered, $issued['token']));
    }

    public function testUnknownIntentValueFallsBackToTheReturnPathClassification(): void
    {
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $json = (string) json_encode(['n' => 's', 'r' => '/pending-assignment?join_code=AB', 'intent' => 'nonsense', 'exp' => time() + 60]);
        $cookie = $b64($json) . '.' . hash_hmac('sha256', $json, 'secret');

        self::assertSame(AuthIntent::JOIN_CODE, (new GoogleOAuthState('secret'))->verifyDetailed($cookie, 's')['intent']);
    }
}
