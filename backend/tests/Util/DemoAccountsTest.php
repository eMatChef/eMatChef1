<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Command\CreateRoleUsersCommand;
use App\Service\Bootstrap\DemoSupplierSeedService;
use App\Util\DemoAccounts;
use OTPHP\Factory;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

final class DemoAccountsTest extends TestCase
{
    public function testAllAccountsUseTheDemoDomainAndAreUnique(): void
    {
        $emails = array_column(DemoAccounts::all(), 'email');

        self::assertSame('demo.ematchef.ch', DemoAccounts::domain());
        self::assertCount(\count(array_unique($emails)), $emails);
        foreach (DemoAccounts::all() as $account) {
            self::assertSame($account['key'] . '@demo.ematchef.ch', $account['email']);
            self::assertTrue(DemoAccounts::isDemoEmail($account['email']));
            self::assertStringEndsWith('@ematchef.ch', $account['legacyEmail']);
        }
        self::assertSame('orgchef@demo.ematchef.ch', DemoAccounts::email('orgchef'));
        self::assertSame('organisationschef@ematchef.ch', DemoAccounts::legacyEmail('orgchef'));
        self::assertFalse(DemoAccounts::isDemoEmail('user@ematchef.ch'));
    }

    public function testSeedsAndPasswordFollowTheCatalogue(): void
    {
        self::assertSame(DemoAccounts::email('supplier'), DemoSupplierSeedService::EMAIL);
        self::assertSame(DemoAccounts::password(), CreateRoleUsersCommand::DEMO_PASSWORD);
    }

    public function testOnlyGlobalAdminDemoAccountsHaveFixedTotp(): void
    {
        $withTotp = [];
        foreach (DemoAccounts::all() as $account) {
            if (isset($account['totpSecret'])) {
                $withTotp[] = $account['key'];
            }
        }

        self::assertSame(['superadmin', 'orgchef', 'suborgchef'], $withTotp);
        self::assertNull(DemoAccounts::totpSecretForEmail(DemoAccounts::email('matwart')));
        self::assertNull(DemoAccounts::otpauthUri(DemoAccounts::email('user')));
        $secrets = array_map(static fn (string $k): ?string => DemoAccounts::totpSecretForEmail(DemoAccounts::email($k)), $withTotp);
        self::assertCount(3, array_unique($secrets));
        foreach ($secrets as $secret) {
            self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', (string) $secret);
        }
    }

    public function testOtpauthUriIsDerivedFromTheSameSecret(): void
    {
        foreach (['superadmin', 'orgchef', 'suborgchef'] as $key) {
            $email = DemoAccounts::email($key);
            $uri = (string) DemoAccounts::otpauthUri($email);

            $otp = Factory::loadFromProvisioningUri($uri);
            self::assertInstanceOf(TOTP::class, $otp);
            self::assertSame(DemoAccounts::totpSecretForEmail($email), $otp->getSecret());
            self::assertSame($email, $otp->getLabel());
            self::assertSame(DemoAccounts::totpIssuer(), $otp->getIssuer());
            self::assertSame(6, $otp->getDigits());
            self::assertSame(30, $otp->getPeriod());
        }
    }
}
