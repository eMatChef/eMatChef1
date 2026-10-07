<?php

declare(strict_types=1);

namespace App\Service\Auth;

final readonly class MiDataMaterialwartVerification
{
    /**
     * @param list<HitobitoGroup> $regions Region levels ordered from the Kantonalverband down to the Abteilung
     */
    public function __construct(
        public MiDataMaterialwartVerificationStatus $status,
        public ?string $externalPersonId = null,
        public ?HitobitoGroup $department = null,
        public array $regions = [],
        public ?HitobitoGroup $kantonalverband = null,
        public ?HitobitoGroup $bund = null,
        public ?HitobitoRole $role = null,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->status === MiDataMaterialwartVerificationStatus::CONFIRMED
            && $this->externalPersonId !== null
            && $this->department !== null
            && $this->kantonalverband !== null
            && $this->bund !== null
            && $this->role !== null;
    }
}
