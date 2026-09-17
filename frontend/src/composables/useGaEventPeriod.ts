import { computed, inject, provide, ref, watch, type ComputedRef, type InjectionKey } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import { parseLocalDate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

export type GaEventPeriod = {
  startIso: ComputedRef<string | null>
  endIso: ComputedRef<string | null>
  spanDates: ComputedRef<Date[]>
  defaultAnchor: ComputedRef<Date | null>
}

export const gaEventPeriodKey: InjectionKey<GaEventPeriod> = Symbol('gaEventPeriod')

function parsePeriodIso(iso: string | null): Date | null {
  if (!iso) return null
  const date = parseLocalDate(iso.includes('T') ? iso : `${iso}T00:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

export function provideGaEventPeriod(): GaEventPeriod {
  const route = useRoute()
  const authStore = useAuthStore()
  const departmentId = computed(() => String(route.params.departmentId || ''))
  const startIso = ref<string | null>(null)
  const endIso = ref<string | null>(null)

  async function load() {
    const id = departmentId.value
    if (!id || !authStore.isDepartmentGrossanlass(id)) {
      startIso.value = null
      endIso.value = null
      return
    }
    try {
      const pack = await getGrossanlassPlanung(id)
      startIso.value = pack.config.planned_event_start || null
      endIso.value = pack.config.planned_event_end || pack.config.planned_event_start || null
    } catch {
      startIso.value = null
      endIso.value = null
    }
  }

  watch(departmentId, () => { void load() }, { immediate: true })

  const spanDates = computed(() => {
    const dates: Date[] = []
    const start = parsePeriodIso(startIso.value)
    if (start) dates.push(start)
    const end = parsePeriodIso(endIso.value)
    if (end) dates.push(end)
    return dates
  })

  const defaultAnchor = computed(() => spanDates.value[0] ?? null)

  const api: GaEventPeriod = {
    startIso: computed(() => startIso.value),
    endIso: computed(() => endIso.value),
    spanDates,
    defaultAnchor,
  }
  provide(gaEventPeriodKey, api)
  return api
}

export function useGaEventPeriod(): GaEventPeriod | null {
  return inject(gaEventPeriodKey, null)
}

export function useGaEventPeriodOrEmpty(): GaEventPeriod {
  return useGaEventPeriod() ?? {
    startIso: computed(() => null),
    endIso: computed(() => null),
    spanDates: computed(() => []),
    defaultAnchor: computed(() => null),
  }
}
