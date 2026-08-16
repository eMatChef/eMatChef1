<?php

declare(strict_types=1);

namespace App\Tests\Service\Onboarding;

use App\Entity\Activity;
use App\Service\MaterialAvailabilityReservationQuery;
use App\Service\Onboarding\OnboardingSandboxService;
use App\Service\Onboarding\OnboardingSandboxVisibility;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OnboardingSandboxServiceTest extends TestCase
{
    public function testStatusesForIssueReturnAutoApprovesCamp(): void
    {
        $statuses = OnboardingSandboxService::statusesForTour('issue-return');
        $this->assertSame(Activity::STATUS_APPROVED, $statuses['activity']);
        $this->assertSame(Activity::STATUS_APPROVED, $statuses['camp']);
    }

    public function testStatusesForHandoffUsesAtEvent(): void
    {
        $statuses = OnboardingSandboxService::statusesForTour('issue-handoff');
        $this->assertSame(Activity::STATUS_AT_EVENT, $statuses['activity']);
        $this->assertSame(Activity::STATUS_AT_EVENT, $statuses['camp']);
    }

    public function testStatusesForApproveUsesSubmitted(): void
    {
        $statuses = OnboardingSandboxService::statusesForTour('activity-approve');
        $this->assertSame(Activity::STATUS_SUBMITTED, $statuses['camp']);
    }

    public function testStatusesForCreateUsesDraft(): void
    {
        $statuses = OnboardingSandboxService::statusesForTour('activity-create');
        $this->assertSame(Activity::STATUS_DRAFT, $statuses['activity']);
        $this->assertSame(Activity::STATUS_DRAFT, $statuses['camp']);
    }

    public function testDefaultEnsureApprovesForPackChain(): void
    {
        $statuses = OnboardingSandboxService::statusesForTour(null);
        $this->assertSame(Activity::STATUS_APPROVED, $statuses['camp']);
    }

    public function testCreateToursDoNotPreCreate(): void
    {
        $this->assertTrue(OnboardingSandboxService::isCreateTour('activity-create'));
        $this->assertTrue(OnboardingSandboxService::isCreateTour('activity-camp-create'));
        $this->assertFalse(OnboardingSandboxService::isCreateTour('issue-return'));
        $this->assertSame('demo_activity', OnboardingSandboxService::ACTIVITY_NAME);
        $this->assertSame('demo_camp', OnboardingSandboxService::CAMP_NAME);
        $this->assertSame('demo_venue', OnboardingSandboxService::VENUE_NAME);
    }

    public function testPackSeedStageForHandoffAndStore(): void
    {
        $this->assertSame('issued', OnboardingSandboxService::packSeedStageForTour('issue-handoff'));
        $this->assertSame('returned', OnboardingSandboxService::packSeedStageForTour('activity-store'));
        $this->assertNull(OnboardingSandboxService::packSeedStageForTour('issue-return'));
        $this->assertNull(OnboardingSandboxService::packSeedStageForTour('activity-create'));
    }

    public function testVisibilityExcludeByDefault(): void
    {
        $request = Request::create('/api/activities', 'GET');
        $this->assertFalse(OnboardingSandboxVisibility::includeFromRequest($request));

        [$dql] = OnboardingSandboxVisibility::activityListConstraint('a', false, 'user1');
        $this->assertStringContainsString('onboardingSandbox = false', $dql);
    }

    public function testVisibilityIncludeOwnSandboxActivities(): void
    {
        $request = Request::create('/api/activities', 'GET', [
            OnboardingSandboxVisibility::QUERY_PARAM => '1',
        ]);
        $this->assertTrue(OnboardingSandboxVisibility::includeFromRequest($request));

        [$dql, $params] = OnboardingSandboxVisibility::activityListConstraint('a', true, 'user1');
        $this->assertStringContainsString('createdByUserId', $dql);
        $this->assertSame('user1', $params['onboardingSandboxUserId']);
    }

    public function testVisibilityHeaderEnablesInclude(): void
    {
        $request = Request::create('/api/materials', 'GET');
        $request->headers->set(OnboardingSandboxVisibility::HEADER_NAME, 'issue-return');
        $this->assertTrue(OnboardingSandboxVisibility::includeFromRequest($request));
    }

    public function testKitHiddenWithoutInclude(): void
    {
        $this->assertStringContainsString(
            'onboardingSandbox = false',
            OnboardingSandboxVisibility::kitListConstraint('m', false),
        );
        $this->assertStringContainsString(
            'onboardingSandbox = true',
            OnboardingSandboxVisibility::kitListConstraint('m', true),
        );
        $this->assertStringContainsString(
            'onboarding_sandbox',
            OnboardingSandboxVisibility::kitSqlConstraint('mi', true),
        );
    }

    public function testAvailabilitySqlIgnoresSandboxReservations(): void
    {
        $sql = MaterialAvailabilityReservationQuery::lateralReservedQtySql(true, '');
        $this->assertStringContainsString('onboarding_sandbox', $sql);
        $this->assertSame(2, substr_count($sql, 'COALESCE(a.onboarding_sandbox, false) = false'));
    }

    public function testAccountingSyncSkipsOnboardingSandbox(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/../src/Service/ActivityAccountingCostService.php'
        );
        $this->assertIsString($src);
        $this->assertMatchesRegularExpression(
            '/function syncActivityAccountingFollowUps\(Activity \$activity\): void\s*\{\s*\/\/[^\n]*\s*if \(\$activity->isOnboardingSandbox\(\)\)/',
            $src
        );
    }

    public function testInboxSkipsOnboardingSandboxActivities(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/../src/Service/InboxMessageService.php');
        $this->assertIsString($src);
        $this->assertStringContainsString('skipOnboardingSandboxActivity', $src);
        $this->assertStringContainsString('function notifyActivitySubmitted', $src);
        $this->assertGreaterThanOrEqual(
            8,
            substr_count($src, 'skipOnboardingSandboxActivity($activity)')
        );
    }
}
