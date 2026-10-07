<?php

declare(strict_types=1);

namespace App\Service\Support;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A verified alternative to the generic support process, e.g. an open MiData onboarding offer.
 * Excluded users do not appear as "Benutzer ohne Zuordnung" and their legacy automatic requests stay hidden.
 */
#[AutoconfigureTag(self::TAG)]
interface UnassignedUserQueueExclusion
{
    public const TAG = 'app.unassigned_user_queue_exclusion';

    /**
     * @param list<string> $userIds
     *
     * @return list<string> the given user IDs that must not appear in the unassigned-user queue
     */
    public function excludedUserIds(array $userIds): array;
}
