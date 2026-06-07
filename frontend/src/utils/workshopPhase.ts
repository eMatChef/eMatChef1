import type { TicketPhase, WorkshopTicket } from '@/api/workshop'

export type TicketDisplayPhase = TicketPhase | 'triage'

export const KANBAN_PHASES: TicketDisplayPhase[] = [
  'triage',
  'planning',
  'ordered',
  'ready',
  'in_progress',
  'awaiting_quote',
  'completed',
]

export const STATS_PHASES: TicketDisplayPhase[] = [
  'triage',
  'planning',
  'in_progress',
  'awaiting_quote',
  'completed',
  'cancelled',
]

export function getTicketDisplayPhase(ticket: WorkshopTicket): TicketDisplayPhase {
  if (
    ticket.strategy === 'triage'
    && ticket.phase !== 'completed'
    && ticket.phase !== 'cancelled'
  ) {
    return 'triage'
  }

  return (ticket.phase ?? 'triage') as TicketDisplayPhase
}

export function isTerminalPhase(ticket: WorkshopTicket): boolean {
  const phase = getTicketDisplayPhase(ticket)
  return phase === 'completed' || phase === 'cancelled'
}

export function isActivePhase(ticket: WorkshopTicket): boolean {
  return !isTerminalPhase(ticket)
}
