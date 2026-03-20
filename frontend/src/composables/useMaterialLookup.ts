import { onUnmounted, ref } from 'vue'
import apiClient from '@/api/apiClient'
import { getMaterials } from '@/api/materials'

export interface MaterialLookupAvailabilityContext {
  departmentId: string
  activityId?: string
  excludeActivityId?: string
  startDate?: string
  endDate?: string
  source?: 'all' | 'internal' | 'js'
  includeGlobalJs?: boolean
  limit?: number
}

export interface UseMaterialLookupOptions<T> {
  fetcher: (query: string) => Promise<T[]>
  minChars?: number
  debounceMs?: number
  maxSuggestions?: number
  closeDelayMs?: number
}

export function createBasicMaterialLookupFetcher(
  departmentIdGetter: () => string,
  options?: {
    onlyWithStock?: boolean
  }
) {
  return async (query: string) => {
    const departmentId = departmentIdGetter()
    if (!departmentId) return []
    const materials = await getMaterials(departmentId, { search: query })
    const withStock = options?.onlyWithStock ? materials.filter((m) => (m.total_stock || 0) > 0) : materials
    return withStock.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de'))
  }
}

export function createAvailabilityMaterialLookupFetcher(
  contextGetter: () => MaterialLookupAvailabilityContext | null
) {
  return async (query: string) => {
    const ctx = contextGetter()
    if (!ctx?.departmentId) return []
    const params: Record<string, unknown> = {
      departmentId: ctx.departmentId,
      search: query,
      limit: ctx.limit ?? 20,
    }
    if (ctx.activityId) params.activityId = ctx.activityId
    if (ctx.excludeActivityId) params.excludeActivityId = ctx.excludeActivityId
    if (ctx.startDate && ctx.endDate) {
      params.startDate = ctx.startDate
      params.endDate = ctx.endDate
    }
    if (ctx.source) params.source = ctx.source
    if (ctx.includeGlobalJs !== undefined) params.includeGlobalJs = ctx.includeGlobalJs

    const response = await apiClient.get('/api/materials/available-for-period', { params })
    return Array.isArray(response.data) ? response.data : []
  }
}

export function useMaterialLookup<T>(options: UseMaterialLookupOptions<T>) {
  const minChars = options.minChars ?? 1
  const debounceMs = options.debounceMs ?? 220
  const maxSuggestions = options.maxSuggestions ?? 5
  const closeDelayMs = options.closeDelayMs ?? 160

  const query = ref('')
  const results = ref<T[]>([])
  const isLoading = ref(false)
  const isOpen = ref(false)
  const error = ref('')
  const activeIndex = ref(-1)

  let debounceTimer: ReturnType<typeof setTimeout> | null = null
  let closeTimer: ReturnType<typeof setTimeout> | null = null
  let requestToken = 0

  async function runSearch(rawInput: string) {
    const trimmed = rawInput.trim()
    if (trimmed.length < minChars) {
      results.value = []
      isLoading.value = false
      error.value = ''
      activeIndex.value = -1
      return
    }

    const token = ++requestToken
    isLoading.value = true
    error.value = ''

    try {
      const loaded = await options.fetcher(trimmed)
      if (token !== requestToken) return
      results.value = loaded.slice(0, maxSuggestions)
      activeIndex.value = results.value.length > 0 ? 0 : -1
    } catch (err: any) {
      if (token !== requestToken) return
      results.value = []
      activeIndex.value = -1
      error.value = err?.message || 'Suche fehlgeschlagen'
    } finally {
      if (token === requestToken) {
        isLoading.value = false
      }
    }
  }

  function clearDebounceTimer() {
    if (!debounceTimer) return
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  function clearCloseTimer() {
    if (!closeTimer) return
    clearTimeout(closeTimer)
    closeTimer = null
  }

  function scheduleSearch(rawInput: string) {
    clearDebounceTimer()
    debounceTimer = setTimeout(() => {
      runSearch(rawInput)
    }, debounceMs)
  }

  function onInput(rawInput: string) {
    query.value = rawInput
    isOpen.value = true
    scheduleSearch(rawInput)
  }

  function onFocus() {
    clearCloseTimer()
    isOpen.value = true
    if (query.value.trim().length >= minChars) {
      scheduleSearch(query.value)
    }
  }

  function onBlur() {
    clearCloseTimer()
    closeTimer = setTimeout(() => {
      isOpen.value = false
      activeIndex.value = -1
    }, closeDelayMs)
  }

  function closeNow() {
    clearCloseTimer()
    isOpen.value = false
    activeIndex.value = -1
  }

  function setActiveIndex(index: number) {
    if (!results.value.length) {
      activeIndex.value = -1
      return
    }
    const next = Math.max(0, Math.min(index, results.value.length - 1))
    activeIndex.value = next
  }

  function moveActive(step: 1 | -1) {
    if (!results.value.length) return
    if (activeIndex.value < 0) {
      activeIndex.value = 0
      return
    }
    const len = results.value.length
    activeIndex.value = (activeIndex.value + step + len) % len
  }

  function selectAt(index: number): T | null {
    if (index < 0 || index >= results.value.length) return null
    const picked = results.value[index]
    closeNow()
    return picked
  }

  function onKeydown(event: KeyboardEvent): T | null {
    if (event.key === 'Escape') {
      closeNow()
      return null
    }
    if (!isOpen.value) return null
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      moveActive(1)
      return null
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      moveActive(-1)
      return null
    }
    if (event.key === 'Enter') {
      if (!results.value.length) return null
      event.preventDefault()
      const index = activeIndex.value >= 0 ? activeIndex.value : 0
      return selectAt(index)
    }
    return null
  }

  function reset() {
    clearDebounceTimer()
    clearCloseTimer()
    query.value = ''
    results.value = []
    isLoading.value = false
    isOpen.value = false
    error.value = ''
    activeIndex.value = -1
  }

  onUnmounted(() => {
    clearDebounceTimer()
    clearCloseTimer()
  })

  return {
    query,
    results,
    isLoading,
    isOpen,
    error,
    activeIndex,
    runSearch,
    onInput,
    onFocus,
    onBlur,
    onKeydown,
    closeNow,
    reset,
    setActiveIndex,
    selectAt,
  }
}
