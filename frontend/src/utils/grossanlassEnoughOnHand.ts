export type EnoughOnHandSource = 'stock' | 'commitment'

export type EnoughOnHandValue = {
  enough: boolean
  source: EnoughOnHandSource | null
  detail: string
  refId: string | null
}

export type EnoughOnHandFields = {
  enough_on_hand?: boolean
  enough_on_hand_source?: EnoughOnHandSource | string | null
  enough_on_hand_detail?: string | null
  enough_on_hand_ref_id?: string | null
}

type Translate = (key: string, values?: Record<string, unknown>) => string

export function emptyEnoughOnHand(): EnoughOnHandValue {
  return { enough: false, source: null, detail: '', refId: null }
}

export function enoughOnHandFromApi(fields: EnoughOnHandFields | null | undefined): EnoughOnHandValue {
  const source = normalizeSource(fields?.enough_on_hand_source)
  return {
    enough: Boolean(fields?.enough_on_hand),
    source,
    detail: (fields?.enough_on_hand_detail || '').trim(),
    refId: fields?.enough_on_hand_ref_id || null,
  }
}

export function enoughOnHandFromTemplate(wish: {
  enoughOnHand?: boolean
  enoughOnHandSource?: EnoughOnHandSource | string | null
  enoughOnHandDetail?: string | null
  enoughOnHandRefId?: string | null
}): EnoughOnHandValue {
  return enoughOnHandFromApi({
    enough_on_hand: wish.enoughOnHand,
    enough_on_hand_source: wish.enoughOnHandSource,
    enough_on_hand_detail: wish.enoughOnHandDetail,
    enough_on_hand_ref_id: wish.enoughOnHandRefId,
  })
}

export function enoughOnHandToPayload(value: EnoughOnHandValue): EnoughOnHandFields {
  if (!value.enough) {
    return {
      enough_on_hand: false,
      enough_on_hand_source: null,
      enough_on_hand_detail: null,
      enough_on_hand_ref_id: null,
    }
  }
  return {
    enough_on_hand: true,
    enough_on_hand_source: value.source,
    enough_on_hand_detail: value.detail.trim() || null,
    enough_on_hand_ref_id: value.source === 'commitment' ? value.refId : null,
  }
}

export function validateEnoughOnHand(value: EnoughOnHandValue, t: Translate): string | null {
  if (!value.enough) return null
  if (value.source !== 'stock' && value.source !== 'commitment') {
    return t('grossanlass.wishes.enoughOnHandSourceRequired')
  }
  if (value.source === 'commitment' && !value.refId) {
    return t('grossanlass.wishes.enoughOnHandZusageRequired')
  }
  return null
}

export function enoughOnHandBadgeLabel(fields: EnoughOnHandFields, t: Translate): string {
  if (!fields.enough_on_hand) return ''
  if (fields.enough_on_hand_source === 'stock') {
    const detail = (fields.enough_on_hand_detail || '').trim()
    if (detail && detail !== 'Eigenbestand') {
      return t('grossanlass.wishes.enoughOnHandBadgeFrom', { source: detail })
    }
    return t('grossanlass.wishes.enoughOnHandBadgeStock')
  }
  const detail = (fields.enough_on_hand_detail || '').trim()
  if (detail) {
    return t('grossanlass.wishes.enoughOnHandBadgeFrom', { source: detail })
  }
  return t('grossanlass.wishes.enoughOnHandBadge')
}

export function enoughOnHandBadgeFromValue(value: EnoughOnHandValue, t: Translate): string {
  return enoughOnHandBadgeLabel(enoughOnHandToPayload(value), t)
}

function normalizeSource(raw: string | null | undefined): EnoughOnHandSource | null {
  return raw === 'stock' || raw === 'commitment' ? raw : null
}
