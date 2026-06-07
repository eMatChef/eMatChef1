<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\MaterialItem;
use App\Entity\WorkshopTicket;

final class WorkshopExternalCleaningService
{
    public const TENT_CLEANING_ITEM_KEY = 'waschen_impraegnieren';
    public const TENT_CLEANING_SECTION_KEY = 'sonderposten';

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed> history changes
     */
    public function applyTriage(WorkshopTicket $ticket, array $data): array
    {
        $serviceKey = trim((string) ($data['cleaning_service_key'] ?? ''));
        if ($serviceKey === '') {
            throw new \InvalidArgumentException('cleaning_service_key ist für externe Reinigung erforderlich');
        }

        $material = $ticket->getMaterialItem();
        $checklist = $ticket->getRepairChecklist();
        if (!\is_array($checklist)) {
            $checklist = [];
        }

        $checklist['cleaning_service_key'] = $serviceKey;

        if ($material->getRepairTemplateKey() !== null && $material->getRepairTemplateKey() !== '') {
            $items = $checklist['items'] ?? [];
            if (!\is_array($items)) {
                $items = [];
            }
            $items[self::TENT_CLEANING_ITEM_KEY] = ['quantity' => 1];
            $checklist['items'] = $items;
            $checklist['scope'] = 'partial';
            $checklist['active_section_key'] = self::TENT_CLEANING_SECTION_KEY;
            if (!isset($checklist['template_key']) || $checklist['template_key'] === '') {
                $checklist['template_key'] = $material->getRepairTemplateKey();
            }
        }

        $ticket->setRepairChecklist($checklist);

        return [
            'cleaning_service_key' => $serviceKey,
            'repair_checklist' => $checklist,
        ];
    }

    public function materialHasTentTemplate(MaterialItem $material): bool
    {
        $key = $material->getRepairTemplateKey();

        return $key !== null && $key !== '';
    }
}
