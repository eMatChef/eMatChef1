import {
  createGrossanlassEinsatz,
  type GaUebersichtCreateResult,
  type GaUebersichtEinsatz,
} from '@/api/grossanlassUebersicht'
import {
  updateGrossanlassCommitment,
  type GrossanlassCommitment,
} from '@/api/grossanlassCommitments'
import { inboundMode } from '@/views/grossanlass/gaCharge'
import { parseLocalDate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

function einsatzIdFromCreate(
  result: GaUebersichtCreateResult,
  commitmentId: string,
): string | null {
  if ('einsatz' in result && result.einsatz?.id) return result.einsatz.id
  if ('einsaetze' in result) {
    const rows = [...(result.einsaetze as GaUebersichtEinsatz[])].reverse()
    return rows.find((row) => row.object_id === commitmentId)?.id ?? null
  }
  return null
}

function toLocalIso(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

function addOneHour(iso: string): string {
  const date = parseLocalDate(iso)
  if (Number.isNaN(date.getTime())) return iso
  date.setHours(date.getHours() + 1)
  return toLocalIso(date)
}

function inboundWindow(article: GrossanlassCommitment): { from: string; to: string } | null {
  const mode = inboundMode(article)
  if (mode === 'pickup') {
    if (article.handover_from && article.handover_to) {
      return { from: article.handover_from, to: article.handover_to }
    }
  }
  const from = article.present_from || article.handover_from
  if (!from) return null
  const to = article.handover_to && article.handover_from === from
    ? article.handover_to
    : addOneHour(from)
  return { from, to }
}

/** Einsatz am Ankunftstag, damit Lieferung oder Abholung im Kalender steht. */
export async function ensureInboundEinsatz(
  departmentId: string,
  article: GrossanlassCommitment,
  who: string,
  groupId?: string | null,
): Promise<GrossanlassCommitment> {
  const mode = inboundMode(article)
  if (mode === 'pickup' && article.item_details?.pickup_einsatz_id) return article
  if (mode === 'delivery' && article.item_details?.delivery_einsatz_id) return article
  const slot = inboundWindow(article)
  if (!slot || !departmentId) return article

  const created = await createGrossanlassEinsatz(departmentId, {
    kind: 'einsatz',
    commitment_id: article.id,
    qty: Math.max(1, article.quantity || 1),
    from: slot.from,
    to: slot.to,
    who,
    delivery: 'pickup',
    group_id: groupId || null,
  })
  const einsatzId = einsatzIdFromCreate(created, article.id)
  if (!einsatzId) return article

  return updateGrossanlassCommitment(departmentId, article.id, {
    item_details: {
      ...article.item_details,
      inbound_mode: mode,
      ...(mode === 'pickup'
        ? { pickup_einsatz_id: einsatzId }
        : { delivery_einsatz_id: einsatzId }),
    },
  })
}

/** @deprecated Use ensureInboundEinsatz */
export async function ensureLoanPickupEinsatz(
  departmentId: string,
  article: GrossanlassCommitment,
  who: string,
  groupId?: string | null,
): Promise<GrossanlassCommitment> {
  return ensureInboundEinsatz(departmentId, article, who, groupId)
}
