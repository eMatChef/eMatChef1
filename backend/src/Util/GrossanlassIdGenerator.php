<?php

declare(strict_types=1);

namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Grossanlass-IDs: immer 12 Zeichen über den zentralen {@see IdGenerator},
 * mit festem Prefix pro Entitätstyp.
 */
final class GrossanlassIdGenerator
{
    public const ROUND = 'round';
    public const FORM = 'form';
    public const FORM_FIELD = 'form_field';
    public const WISH_RESPONSE = 'wish_response';
    public const WISH_LINE = 'wish_line';
    public const WISH_VALUE = 'wish_value';
    public const INQUIRY = 'inquiry';
    public const COMMITMENT = 'commitment';
    public const PROCUREMENT_LINE = 'procurement_line';
    public const PROCUREMENT_CATEGORY = 'procurement_category';
    public const PROCUREMENT_QUOTE = 'procurement_quote';
    public const PROCUREMENT_ORDER = 'procurement_order';
    public const USER_CARD = 'user_card';
    public const GROUP = 'group';
    public const WORKSHOP_CASE = 'workshop_case';
    public const GMAIL_UNMATCHED = 'gmail_unmatched';
    public const EINSATZ = 'einsatz';
    public const GUEST_SHARE = 'guest_share';
    public const COST = 'cost';
    public const BUDGET = 'budget';
    public const PLACE = 'place';
    /** Öffentlicher QR-Code für GA-Orte (`/i/ga/…`). Interne ID bleibt PLACE (`pl`). */
    public const PLACE_PUBLIC = 'place_public';
    public const MAP = 'map';
    public const PACK = 'pack';
    public const PACK_LINE = 'pack_line';
    public const TASK = 'task';
    public const VEHICLE_NEED = 'vehicle_need';
    public const GROUP_SHARE = 'group_share';
    public const CHARGE_MOVEMENT = 'charge_movement';
    public const HELPER = 'helper';
    public const HELPER_AVAILABILITY = 'helper_availability';
    public const SKILL = 'skill';
    public const STAFFING_NEED = 'staffing_need';
    public const STAFFING_ASSIGNMENT = 'staffing_assignment';
    public const STAFFING_LOG = 'staffing_log';
    public const TASK_STEP = 'task_step';
    public const TASK_EVENT = 'task_event';
    public const DEPENDENCY = 'dependency';
    public const ORDER_EVENT = 'order_event';
    public const ARCHIVE_LOG = 'archive_log';
    public const TOUR = 'tour';
    public const TOUR_ITEM = 'tour_item';
    public const TOUR_STOP = 'tour_stop';
    public const TOUR_EVENT = 'tour_event';
    public const DISPATCH_EVENT = 'dispatch_event';
    public const CHANGE_REQUEST = 'change_request';
    public const EXT_SOURCE = 'ext_source';
    public const EXT_PAYLOAD = 'ext_payload';
    public const EXT_RECORD = 'ext_record';
    public const EXT_RUN = 'ext_run';
    public const EXT_CHANGE = 'ext_change';

    /** @var array<string, string> */
    public const PREFIXES = [
        self::ROUND => 'gr',
        self::FORM => 'gf',
        self::FORM_FIELD => 'ff',
        self::WISH_RESPONSE => 'wr',
        self::WISH_LINE => 'gw',
        self::WISH_VALUE => 'wv',
        self::INQUIRY => 'iq',
        self::COMMITMENT => 'zs',
        self::PROCUREMENT_LINE => 'gp',
        self::PROCUREMENT_CATEGORY => 'gc',
        self::PROCUREMENT_QUOTE => 'gq',
        self::PROCUREMENT_ORDER => 'go',
        self::USER_CARD => 'uc',
        self::GROUP => 'grp',
        self::WORKSHOP_CASE => 'wk',
        self::GMAIL_UNMATCHED => 'gu',
        self::EINSATZ => 'ei',
        self::GUEST_SHARE => 'gs',
        self::COST => 'ko',
        self::BUDGET => 'kb',
        self::PLACE => 'pl',
        self::PLACE_PUBLIC => 'ga',
        self::MAP => 'gm',
        self::PACK => 'pk',
        self::PACK_LINE => 'pn',
        self::TASK => 'gt',
        self::VEHICLE_NEED => 'gv',
        self::GROUP_SHARE => 'gl',
        self::CHARGE_MOVEMENT => 'cm',
        self::HELPER => 'gh',
        self::HELPER_AVAILABILITY => 'gi',
        self::SKILL => 'sk',
        self::STAFFING_NEED => 'sn',
        self::STAFFING_ASSIGNMENT => 'sz',
        self::STAFFING_LOG => 'sl',
        self::TASK_STEP => 'gk',
        self::TASK_EVENT => 'ge',
        self::DEPENDENCY => 'gy',
        self::ORDER_EVENT => 'gz',
        self::ARCHIVE_LOG => 'gx',
        self::TOUR => 'tu',
        self::TOUR_ITEM => 'tj',
        self::TOUR_STOP => 'tp',
        self::TOUR_EVENT => 'tv',
        self::DISPATCH_EVENT => 'dv',
        self::CHANGE_REQUEST => 'dq',
        self::EXT_SOURCE => 'xs',
        self::EXT_PAYLOAD => 'xp',
        self::EXT_RECORD => 'xr',
        self::EXT_RUN => 'xu',
        self::EXT_CHANGE => 'xc',
    ];

    public static function prefix(string $kind): string
    {
        if (!isset(self::PREFIXES[$kind])) {
            throw new \InvalidArgumentException('Unbekannter Grossanlass-ID-Typ: ' . $kind);
        }

        return self::PREFIXES[$kind];
    }

    public static function unique(
        EntityManagerInterface $em,
        string $kind,
        string $entityClass,
        string $field = 'id',
    ): string {
        return IdGenerator::generate12UniqueWithPrefix($em, $entityClass, self::prefix($kind), $field);
    }

    /** Nested JSON-IDs ohne eigene Tabelle (z. B. Zusage-Leistungen). */
    public static function hex(): string
    {
        return IdGenerator::generate();
    }

    public static function matches(string $id, string $kind): bool
    {
        $prefix = self::prefix($kind);
        $hexLen = 12 - strlen($prefix);
        $pattern = '/^' . preg_quote($prefix, '/') . '[0-9a-f]{' . $hexLen . '}$/i';

        return preg_match($pattern, $id) === 1;
    }
}
