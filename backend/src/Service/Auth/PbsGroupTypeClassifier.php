<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class PbsGroupTypeClassifier
{
    public const ORGANISATION = 'organisation';
    public const DEPARTMENT = 'department';
    public const GROUP = 'group';

    private const TYPES = [
        'Group::Bund' => self::ORGANISATION,
        'Group::Kantonalverband' => self::ORGANISATION,
        'Group::Region' => self::DEPARTMENT,
        'Group::Abteilung' => self::DEPARTMENT,
        'Group::Biber' => self::GROUP,
        'Group::Woelfe' => self::GROUP,
        'Group::Pfadi' => self::GROUP,
        'Group::Pio' => self::GROUP,
        'Group::Rover' => self::GROUP,
        'Group::Pta' => self::GROUP,
        'Group::Silverscouts' => self::GROUP,
    ];

    public function classify(string $externalType): ?string
    {
        return self::TYPES[$externalType] ?? null;
    }
}
