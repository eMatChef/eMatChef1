<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassAccessRoles;
use App\Service\Grossanlass\GrossanlassGmailAccountService;
use App\Service\MembershipRoleCatalog;
use PHPUnit\Framework\TestCase;

class GrossanlassAccessRolesTest extends TestCase
{
    public function testMailboxRoles(): void
    {
        self::assertTrue(GrossanlassAccessRoles::canWorkMailbox('mw'));
        self::assertTrue(GrossanlassAccessRoles::canWorkMailbox('cmw'));
        self::assertTrue(GrossanlassAccessRoles::canWorkMailbox('komm'));
        self::assertTrue(GrossanlassAccessRoles::canWorkMailbox('spon'));
        self::assertFalse(GrossanlassAccessRoles::canWorkMailbox('dc'));
        self::assertFalse(GrossanlassAccessRoles::canWorkMailbox('u'));
    }

    public function testCmwCannotStartWaveOrConnectOrSend(): void
    {
        self::assertTrue(GrossanlassAccessRoles::canTakeInquiry('cmw'));
        self::assertFalse(GrossanlassAccessRoles::canCreateMailDrafts('cmw'));
        self::assertFalse(GrossanlassAccessRoles::canSendMail('cmw'));
        self::assertFalse(GrossanlassAccessRoles::canConnectGmail('cmw'));
    }

    public function testKommCannotTake(): void
    {
        self::assertTrue(GrossanlassAccessRoles::canWorkMailbox('komm'));
        self::assertFalse(GrossanlassAccessRoles::canTakeInquiry('komm'));
        self::assertFalse(GrossanlassAccessRoles::canCreateMailDrafts('komm'));
    }

    public function testOkLeitungHasStructureNotGmailOrProcurement(): void
    {
        self::assertTrue(GrossanlassAccessRoles::canApproveEinsatz('dc'));
        self::assertTrue(GrossanlassAccessRoles::canSeeAnlassOverview('dc'));
        self::assertFalse(GrossanlassAccessRoles::canWorkMailbox('dc'));
        self::assertFalse(GrossanlassAccessRoles::canConnectGmail('dc'));
        self::assertFalse(GrossanlassAccessRoles::canManageProcurement('dc'));
        self::assertFalse(GrossanlassAccessRoles::canReleaseTrip('dc'));
        self::assertTrue(GrossanlassAccessRoles::submitsEinsatzDirectlyFree('dc'));
    }

    public function testMwOnlyMailCampaign(): void
    {
        self::assertTrue(GrossanlassAccessRoles::canCreateMailDrafts('mw'));
        self::assertTrue(GrossanlassAccessRoles::canSendMail('mw'));
        self::assertTrue(GrossanlassAccessRoles::canConnectGmail('mw'));
    }

    public function testTakeReplyKindsAreMailboxTakeNotWave(): void
    {
        self::assertContains('nehmen', GrossanlassGmailAccountService::TAKE_REPLY_KINDS);
        self::assertContains('nicht_genommen', GrossanlassGmailAccountService::TAKE_REPLY_KINDS);
        self::assertNotContains('praezisieren', GrossanlassGmailAccountService::TAKE_REPLY_KINDS);
        self::assertNotContains('nachfassen', GrossanlassGmailAccountService::TAKE_REPLY_KINDS);
        foreach (GrossanlassGmailAccountService::TAKE_REPLY_KINDS as $kind) {
            self::assertContains($kind, GrossanlassGmailAccountService::REPLY_KINDS);
        }
    }

    public function testGrossanlassAssignmentRanks(): void
    {
        self::assertTrue(MembershipRoleCatalog::canAssign('mw', 'cmw', true));
        self::assertTrue(MembershipRoleCatalog::canAssign('cmw', 'dc', true));
        self::assertFalse(MembershipRoleCatalog::canAssign('dc', 'cmw', true));
        self::assertFalse(MembershipRoleCatalog::canAssign('komm', 'spon', true));
        self::assertTrue(MembershipRoleCatalog::canAssign('komm', 'u', true));
        self::assertFalse(MembershipRoleCatalog::isAllowed(null, 'cmw'));
    }
}
