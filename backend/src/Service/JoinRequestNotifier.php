<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JoinRequest;

interface JoinRequestNotifier
{
    public function notifyJoinRequestCreated(JoinRequest $joinRequest): void;
}
