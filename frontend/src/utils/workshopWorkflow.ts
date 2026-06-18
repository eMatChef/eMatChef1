import type { TicketPhase, WorkshopTicket } from '@/api/workshop'
import { getTicketDisplayPhase } from '@/utils/workshopPhase'
import { normalizeRepairPartsList } from '@/types/repairPartsList'

export type InternalWorkflowStepId =
  | 'plan'
  | 'procure'
  | 'ready'
  | 'work'
  | 'done'

export interface InternalWorkflowStep {
  id: InternalWorkflowStepId
  phaseKeys: TicketPhase[]
  optional?: boolean
}

export type WorkflowPrimaryAction =
  | 'triage'
  | 'advance_ready'
  | 'advance_work'
  | 'complete'
  | 'send_supplier'
  | 'accept_loss'
  | 'waiting_parts'
  | 'order_parts'
  | null

export function getInternalWorkflowSteps(ticket: WorkshopTicket): InternalWorkflowStep[] {
  const steps: InternalWorkflowStep[] = [
    { id: 'plan', phaseKeys: ['planning'] },
    { id: 'procure', phaseKeys: ['ordered'], optional: !hasPurchaseLines(ticket) },
    { id: 'ready', phaseKeys: ['ready'] },
    { id: 'work', phaseKeys: ['in_progress'] },
    { id: 'done', phaseKeys: ['completed'] },
  ]

  return steps
}

export function hasPurchaseLines(ticket: WorkshopTicket): boolean {
  const lines = normalizeRepairPartsList(ticket.parts_used)
  return lines.some((line) => line.source === 'purchase')
}

export function hasOpenPurchase(ticket: WorkshopTicket): boolean {
  const lines = normalizeRepairPartsList(ticket.parts_used)
  return lines.some(
    (line) =>
      line.source === 'purchase'
      && (line.status === 'planned' || line.status === 'ordered'),
  )
}

export function hasOrderedPurchase(ticket: WorkshopTicket): boolean {
  const lines = normalizeRepairPartsList(ticket.parts_used)
  return lines.some((line) => line.source === 'purchase' && line.status === 'ordered')
}

export function getWorkflowStepState(
  step: InternalWorkflowStep,
  ticket: WorkshopTicket,
): 'done' | 'current' | 'upcoming' | 'skipped' {
  const phase = getTicketDisplayPhase(ticket)

  if (phase === 'completed') {
    return 'done'
  }

  if (step.optional && step.id === 'procure' && !hasPurchaseLines(ticket)) {
    if (['ready', 'in_progress'].includes(phase)) return 'skipped'
    if (phase === 'planning') return 'skipped'
    return 'skipped'
  }

  const stepOrder: InternalWorkflowStepId[] = ['plan', 'procure', 'ready', 'work', 'done']
  const currentIndex = resolveCurrentStepIndex(ticket)
  const stepIndex = stepOrder.indexOf(step.id)

  if (stepIndex < currentIndex) return 'done'
  if (stepIndex === currentIndex) return 'current'
  return 'upcoming'
}

function resolveCurrentStepIndex(ticket: WorkshopTicket): number {
  const phase = getTicketDisplayPhase(ticket)

  if (phase === 'in_progress') return 3
  if (phase === 'ready') return 2
  if (phase === 'ordered') return 1
  if (phase === 'planning') return 0

  return 0
}

export function getWorkflowPrimaryAction(ticket: WorkshopTicket): WorkflowPrimaryAction {
  if (ticket.strategy === 'triage') return 'triage'

  if (['external_repair', 'external_cleaning'].includes(ticket.strategy)) {
    return null
  }

  const phase = getTicketDisplayPhase(ticket)

  if (phase === 'planning') {
    if (hasOrderedPurchase(ticket)) return 'waiting_parts'
    if (hasOpenPurchase(ticket)) return 'order_parts'
    return 'advance_ready'
  }

  if (phase === 'ordered') {
    return 'waiting_parts'
  }

  if (phase === 'ready') {
    return 'advance_work'
  }

  if (phase === 'in_progress') {
    return 'complete'
  }

  return null
}
