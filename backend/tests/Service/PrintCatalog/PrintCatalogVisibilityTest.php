<?php

declare(strict_types=1);

namespace App\Tests\Service\PrintCatalog;

use App\Entity\PrintDeviceModel;
use App\Entity\PrintMedia;
use App\Service\PrintCatalog\PrintCatalogVisibility;
use PHPUnit\Framework\TestCase;

class PrintCatalogVisibilityTest extends TestCase
{
    public function testGlobalPublishedIsVisibleToEveryone(): void
    {
        $this->assertTrue(PrintCatalogVisibility::canSeePublished(PrintMedia::SCOPE_GLOBAL, null, []));
    }

    public function testOrgPublishedOnlyForSameOrganisation(): void
    {
        $this->assertTrue(PrintCatalogVisibility::canSeePublished(
            PrintMedia::SCOPE_ORGANISATION,
            'orgaaaaaaaa',
            ['orgaaaaaaaa'],
        ));
        $this->assertFalse(PrintCatalogVisibility::canSeePublished(
            PrintMedia::SCOPE_ORGANISATION,
            'orgaaaaaaaa',
            ['orgbbbbbbbb'],
        ));
    }

    public function testPendingVisibleToCreatorAndOrgReviewer(): void
    {
        $this->assertTrue(PrintCatalogVisibility::canSeeItem(
            PrintMedia::STATUS_PENDING,
            PrintMedia::SCOPE_ORGANISATION,
            'orgaaaaaaaa',
            'usercreator1',
            'usercreator1',
            [],
            false,
        ));
        $this->assertTrue(PrintCatalogVisibility::canSeeItem(
            PrintMedia::STATUS_PENDING,
            PrintMedia::SCOPE_ORGANISATION,
            'orgaaaaaaaa',
            'usercreator1',
            'userreview01',
            ['orgaaaaaaaa'],
            false,
        ));
        $this->assertFalse(PrintCatalogVisibility::canSeeItem(
            PrintMedia::STATUS_PENDING,
            PrintMedia::SCOPE_ORGANISATION,
            'orgaaaaaaaa',
            'usercreator1',
            'userother000',
            ['orgbbbbbbbb'],
            false,
        ));
    }

    public function testOnlySuperadminPromotesGloballyViaReviewGate(): void
    {
        $this->assertTrue(PrintCatalogVisibility::canReviewItem('orgaaaaaaaa', [], true));
        $this->assertFalse(PrintCatalogVisibility::canReviewItem('orgaaaaaaaa', ['orgbbbbbbbb'], false));
        $this->assertTrue(PrintCatalogVisibility::canReviewItem('orgaaaaaaaa', ['orgaaaaaaaa'], false));
    }

    public function testMediaMustMatchFamilyAndOptionalKeys(): void
    {
        $model = new PrintDeviceModel();
        $model->setFamily(PrintDeviceModel::FAMILY_BROTHER_QL);
        $model->setCompatibleMediaKeys(['brother_dk_22225']);

        $ok = new PrintMedia();
        $ok->setFamily(PrintDeviceModel::FAMILY_BROTHER_QL);
        $ok->setCatalogKey('brother_dk_22225');

        $wrongFamily = new PrintMedia();
        $wrongFamily->setFamily(PrintDeviceModel::FAMILY_OFFICE_A4);
        $wrongFamily->setCatalogKey('brother_dk_22225');

        $wrongKey = new PrintMedia();
        $wrongKey->setFamily(PrintDeviceModel::FAMILY_BROTHER_QL);
        $wrongKey->setCatalogKey('brother_dk_11209');
        $wrongKey->setScope(PrintMedia::SCOPE_GLOBAL);
        $wrongKey->setStatus(PrintMedia::STATUS_PUBLISHED);

        $orgOwn = new PrintMedia();
        $orgOwn->setFamily(PrintDeviceModel::FAMILY_BROTHER_QL);
        $orgOwn->setCatalogKey('brother_dk_custom');
        $orgOwn->setScope(PrintMedia::SCOPE_ORGANISATION);
        $orgOwn->setStatus(PrintMedia::STATUS_PUBLISHED);

        $this->assertTrue(PrintCatalogVisibility::mediaCompatibleWithModel($model, $ok));
        $this->assertFalse(PrintCatalogVisibility::mediaCompatibleWithModel($model, $wrongFamily));
        $this->assertFalse(PrintCatalogVisibility::mediaCompatibleWithModel($model, $wrongKey));
        $this->assertTrue(PrintCatalogVisibility::mediaCompatibleWithModel($model, $orgOwn));
    }
}
