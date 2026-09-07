import {
  createGrossanlassEinsatz,
  type GaUebersichtCreateResult,
  type GaUebersichtEinsatz,
} from '@/api/grossanlassUebersicht'
import {
  updateGrossanlassCommitment,
  type GrossanlassCommitment,
} from '@/api/grossanlassCommitments'

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

/** Einsatz im Übernahmefenster, damit die Abholung bei der Firma im Kalender steht. */
export async function ensureLoanPickupEinsatz(
  departmentId: string,
  article: GrossanlassCommitment,
  who: string,
  groupId?: string | null,
): Promise<GrossanlassCommitment> {
  const mode = article.item_details?.inbound_mode
  const pickup = mode === 'pickup' || (mode == null && article.origin === 'loan')
  if (!pickup) return article
  if (article.item_details?.pickup_einsatz_id) return article
  const from = article.handover_from
  const to = article.handover_to
  if (!from || !to || !departmentId) return article

  const created = await createGrossanlassEinsatz(departmentId, {
    kind: 'einsatz',
    commitment_id: article.id,
    qty: Math.max(1, article.quantity || 1),
    from,
    to,
    who,
    delivery: 'pickup',
    group_id: groupId || null,
  })
  const einsatzId = einsatzIdFromCreate(created, article.id)
  if (!einsatzId) return article

  return updateGrossanlassCommitment(departmentId, article.id, {
    item_details: {
      ...article.item_details,
      inbound_mode: 'pickup',
      pickup_einsatz_id: einsatzId,
    },
  })
}
