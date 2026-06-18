import type { WorkshopTicket, TicketStrategy } from '@/api/workshop'

export type TriageActionId =
  | 'internal_repair'
  | 'external_repair'
  | 'external_cleaning'
  | 'writeoff'
  | 'inspection'
  | 'resolve_ok'

export interface TriageActionOption {
  id: TriageActionId
  strategy?: Exclude<TicketStrategy, 'triage'>
  variant: 'primary' | 'secondary' | 'danger'
  mdiIcon: string
  requiresSupplier?: boolean
  prominent?: boolean
}

function isLossTicket(ticket: WorkshopTicket): boolean {
  return ticket.origin_issue_type === 'loss' || ticket.issue_report?.type === 'loss'
}

function isSurplusInspection(ticket: WorkshopTicket): boolean {
  return ticket.type === 'inspection' && !ticket.issue_report
}

export function getWorkshopTriageOptions(ticket: WorkshopTicket): TriageActionOption[] {
  if (ticket.type === 'cleaning') {
    return [
      {
        id: 'internal_repair',
        strategy: 'internal_repair',
        variant: 'primary',
        mdiIcon: 'mdi-broom',
      },
      {
        id: 'external_cleaning',
        strategy: 'external_cleaning',
        variant: 'secondary',
        mdiIcon: 'mdi-truck-delivery',
        requiresSupplier: true,
      },
      {
        id: 'writeoff',
        strategy: 'writeoff',
        variant: 'danger',
        mdiIcon: 'mdi-delete-outline',
      },
    ]
  }

  if (ticket.type === 'inspection') {
    if (isSurplusInspection(ticket)) {
      return [
        {
          id: 'inspection',
          strategy: 'inspection',
          variant: 'primary',
          mdiIcon: 'mdi-clipboard-check-outline',
          prominent: true,
        },
      ]
    }
    return [
      {
        id: 'resolve_ok',
        variant: 'secondary',
        mdiIcon: 'mdi-check-circle-outline',
      },
      {
        id: 'internal_repair',
        strategy: 'internal_repair',
        variant: 'primary',
        mdiIcon: 'mdi-wrench',
      },
      {
        id: 'writeoff',
        strategy: 'writeoff',
        variant: 'danger',
        mdiIcon: 'mdi-delete-outline',
      },
    ]
  }

  if (isLossTicket(ticket)) {
    return [
      {
        id: 'writeoff',
        strategy: 'writeoff',
        variant: 'danger',
        mdiIcon: 'mdi-delete-outline',
        prominent: true,
      },
      {
        id: 'internal_repair',
        strategy: 'internal_repair',
        variant: 'secondary',
        mdiIcon: 'mdi-wrench',
      },
    ]
  }

  return [
    {
      id: 'internal_repair',
      strategy: 'internal_repair',
      variant: 'primary',
      mdiIcon: 'mdi-wrench',
    },
    {
      id: 'external_repair',
      strategy: 'external_repair',
      variant: 'secondary',
      mdiIcon: 'mdi-factory',
      requiresSupplier: true,
    },
    {
      id: 'writeoff',
      strategy: 'writeoff',
      variant: 'danger',
      mdiIcon: 'mdi-delete-outline',
    },
  ]
}

export function ticketHasRepairSheet(ticket: WorkshopTicket): boolean {
  if (ticket.repair_checklist && Object.keys(ticket.repair_checklist).length > 0) {
    return true
  }
  return !!ticket.material_item.repair_template_key
}

export function ticketUsesPartsList(ticket: WorkshopTicket): boolean {
  if (ticketHasRepairSheet(ticket)) return false
  if (ticket.strategy === 'writeoff' || ticket.strategy === 'triage') return false
  return ticket.strategy === 'internal_repair'
}
