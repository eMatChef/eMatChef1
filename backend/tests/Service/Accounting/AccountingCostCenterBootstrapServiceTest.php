<?php

declare(strict_types=1);

namespace App\Tests\Service\Accounting;

use App\Entity\AccountingAcquisitionFollowUp;
use App\Entity\AccountingBooking;
use App\Entity\AccountingCostCenterRule;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use PHPUnit\Framework\TestCase;

class AccountingCostCenterBootstrapServiceTest extends TestCase
{
    public function testCostCenterKeysAreUniqueAndComplete(): void
    {
        $keys = array_keys(AccountingCostCenterBootstrapService::COST_CENTERS);
        $this->assertSame(
            ['general', 'material', 'repair', 'rental', 'consumption'],
            $keys,
        );

        foreach (AccountingCostCenterBootstrapService::COST_CENTERS as $def) {
            $this->assertNotSame('', trim($def['name']));
            $this->assertIsInt($def['sort_order']);
            $this->assertIsArray($def['aliases']);
        }
    }

    public function testRulesCoverAllConfigurableSourceKinds(): void
    {
        $kinds = array_column(AccountingCostCenterBootstrapService::RULES, 'source_kind');
        sort($kinds);

        $expected = AccountingCostCenterRule::SOURCE_KINDS;
        $sortedExpected = $expected;
        sort($sortedExpected);

        $this->assertSame($sortedExpected, $kinds);
    }

    public function testRulesReferenceValidCostCenterKeysAndEnums(): void
    {
        foreach (AccountingCostCenterBootstrapService::RULES as $rule) {
            $this->assertArrayHasKey($rule['cost_center_key'], AccountingCostCenterBootstrapService::COST_CENTERS);
            $this->assertContains($rule['source_kind'], AccountingCostCenterRule::SOURCE_KINDS);

            if ($rule['entry_type'] !== null) {
                $this->assertContains($rule['entry_type'], AccountingBooking::ENTRY_TYPES);
            }
            if ($rule['payment_method'] !== null) {
                $this->assertContains($rule['payment_method'], AccountingBooking::PAYMENT_METHODS);
            }
        }
    }

    public function testSupplierInvoiceDefaultsForPurchases(): void
    {
        $byKind = [];
        foreach (AccountingCostCenterBootstrapService::RULES as $rule) {
            $byKind[$rule['source_kind']] = $rule;
        }

        $this->assertSame(
            AccountingBooking::PAYMENT_SUPPLIER,
            $byKind[AccountingAcquisitionFollowUp::SOURCE_BATCH]['payment_method'],
        );
        $this->assertSame(
            'material',
            $byKind[AccountingAcquisitionFollowUp::SOURCE_BATCH]['cost_center_key'],
        );
        $this->assertSame(
            'rental',
            $byKind[AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL]['cost_center_key'],
        );
        $this->assertNull($byKind[AccountingAcquisitionFollowUp::SOURCE_ACTIVITY_RENTAL]['payment_method']);
    }
}
