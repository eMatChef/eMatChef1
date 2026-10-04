<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\PbsGroupTypeClassifier;
use PHPUnit\Framework\TestCase;

final class PbsGroupTypeClassifierTest extends TestCase
{
    public function testClassifiesBundAndKantonalverbandAsOrganisationCandidates(): void
    {
        $classifier = new PbsGroupTypeClassifier();

        self::assertSame(PbsGroupTypeClassifier::ORGANISATION, $classifier->classify('Group::Bund'));
        self::assertSame(PbsGroupTypeClassifier::ORGANISATION, $classifier->classify('Group::Kantonalverband'));
    }

    public function testClassifiesRegionAndAbteilungAsDepartmentCandidates(): void
    {
        $classifier = new PbsGroupTypeClassifier();

        self::assertSame(PbsGroupTypeClassifier::DEPARTMENT, $classifier->classify('Group::Region'));
        self::assertSame(PbsGroupTypeClassifier::DEPARTMENT, $classifier->classify('Group::Abteilung'));
    }

    public function testClassifiesKnownYouthSectionsAsGroupCandidates(): void
    {
        $classifier = new PbsGroupTypeClassifier();

        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Biber'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Woelfe'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Pfadi'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Pio'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Rover'));
    }

    public function testUnknownTypeIsNotClassified(): void
    {
        self::assertNull((new PbsGroupTypeClassifier())->classify('Group::UnknownFutureType'));
    }
}
