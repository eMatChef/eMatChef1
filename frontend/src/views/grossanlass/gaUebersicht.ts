import { computed, inject, provide, ref, watch, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  createGrossanlassEinsatz,
  getGrossanlassUebersicht,
  issueGrossanlassEinsatz,
  updateGrossanlassEinsatz,
  updateGrossanlassUebersichtCommitment,
  type GaUebersichtCreatePayload,
  type GaUebersichtPayload,
} from '@/api/grossanlassUebersicht'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaPreviewEinsatz, GaPreviewWishTemplate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

export type GaUebersichtStore = {
  loading: Ref<boolean>
  error: Ref<string | null>
  data: Ref<GaUebersichtPayload | null>
  load: () => Promise<void>
  apply: (payload: GaUebersichtPayload) => void
  create: (payload: GaUebersichtCreatePayload) => Promise<void>
  issue: (id: string, userId?: string) => Promise<void>
  updateEinsatz: (
    id: string,
    data: { packed?: boolean; trip_released?: boolean; status?: string },
  ) => Promise<void>
  togglePacked: (commitmentId: string, packed: boolean) => Promise<void>
  markReturned: (commitmentId: string) => Promise<void>
  bookingRows: () => GaPreviewEinsatz[]
  wishTemplates: ComputedRef<GaPreviewWishTemplate[]>
}

export const gaUebersichtKey: InjectionKey<GaUebersichtStore> = Symbol('gaUebersicht')

const empty = (): GaUebersichtPayload => ({
  einsaetze: [],
  orders: [],
  conflicts: [],
  issues: [],
  pack: [],
  returns: [],
  cards: [],
  wishes: [],
  issued_by_object: {},
  places: [],
})

function toPreview(
  row: GaUebersichtPayload['einsaetze'][number],
  locale: string,
): GaPreviewEinsatz {
  return {
    id: row.id,
    objectId: row.object_id,
    objectName: row.object_name,
    kind: row.einsatz_kind,
    qty: row.qty,
    stock: row.stock,
    fromIso: row.from,
    toIso: row.to,
    fromLabel: formatGaIsoLabel(row.from, locale),
    toLabel: formatGaIsoLabel(row.to, locale),
    ressort: row.ressort,
    status: row.status,
    who: row.who,
    conflictId: row.conflict_id,
    barRole: 'einsatz',
    delivery: row.delivery ?? 'pickup',
    tripReleased: !!row.trip_released,
    packed: row.packed,
    chauffeurUserId: row.chauffeur_user_id,
    destinationPlaceId: row.destination_place_id,
    place: row.place,
  }
}

export function createGaUebersichtStore(
  departmentId: ComputedRef<string>,
  locale: Ref<string> | ComputedRef<string>,
): GaUebersichtStore {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const data = ref<GaUebersichtPayload | null>(null)

  function apply(payload: GaUebersichtPayload) {
    data.value = payload
  }

  async function load() {
    if (!departmentId.value) return
    loading.value = true
    error.value = null
    try {
      data.value = await getGrossanlassUebersicht(departmentId.value)
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      error.value = err.response?.data?.error || 'load-error'
      data.value = empty()
    } finally {
      loading.value = false
    }
  }

  async function create(payload: GaUebersichtCreatePayload) {
    if (!departmentId.value) return
    const result = await createGrossanlassEinsatz(departmentId.value, payload)
    if ('einsaetze' in result && Array.isArray(result.einsaetze)) {
      apply(result)
      return
    }
    await load()
  }

  async function issue(id: string, userId?: string) {
    if (!departmentId.value) return
    apply(await issueGrossanlassEinsatz(departmentId.value, id, { user_id: userId }))
  }

  async function updateEinsatz(
    id: string,
    data: { packed?: boolean; trip_released?: boolean; status?: string },
  ) {
    if (!departmentId.value) return
    apply(await updateGrossanlassEinsatz(departmentId.value, id, data))
  }

  async function togglePacked(commitmentId: string, packed: boolean) {
    if (!departmentId.value) return
    apply(await updateGrossanlassUebersichtCommitment(departmentId.value, commitmentId, { packed }))
  }

  async function markReturned(commitmentId: string) {
    if (!departmentId.value) return
    apply(await updateGrossanlassUebersichtCommitment(departmentId.value, commitmentId, {
      returned_to_firm: true,
    }))
  }

  function bookingRows(): GaPreviewEinsatz[] {
    const loc = locale.value
    return (data.value?.einsaetze ?? []).map((row) => toPreview(row, loc))
  }

  const wishTemplates = computed<GaPreviewWishTemplate[]>(() =>
    (data.value?.wishes ?? [])
      .filter((wish) => wish.object_id)
      .map((wish) => ({
        id: wish.id,
        label: wish.label,
        objectId: wish.object_id,
        objectName: wish.object_name,
        kind: wish.kind,
        qty: wish.qty,
        stock: wish.stock,
        fromIso: wish.from,
        toIso: wish.to,
        fromLabel: formatGaIsoLabel(wish.from, locale.value),
        toLabel: formatGaIsoLabel(wish.to, locale.value),
        ressort: wish.ressort,
        who: wish.who,
        hasConflict: false,
        groupId: wish.group_id,
      })),
  )

  watch(departmentId, () => { void load() }, { immediate: true })

  return {
    loading,
    error,
    data,
    load,
    apply,
    create,
    issue,
    updateEinsatz,
    togglePacked,
    markReturned,
    bookingRows,
    wishTemplates,
  }
}

export function provideGaUebersicht(): GaUebersichtStore {
  const route = useRoute()
  const { locale } = useI18n()
  const departmentId = computed(() => String(route.params.departmentId || ''))
  const store = createGaUebersichtStore(departmentId, locale)
  provide(gaUebersichtKey, store)
  return store
}

export function useGaUebersicht(): GaUebersichtStore {
  const injected = inject(gaUebersichtKey, null)
  if (injected) return injected
  return provideGaUebersicht()
}
