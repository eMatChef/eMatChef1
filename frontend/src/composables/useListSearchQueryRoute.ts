import { watch, type Ref } from 'vue'
import type { LocationQueryRaw, RouteLocationNormalizedLoaded, Router } from 'vue-router'

/**
 * Zwei-Wege-Sync: Suchfeld ↔ ?q= in der URL (nur Listenansicht, nicht Detail).
 */
export function useListSearchQueryRoute(options: {
  searchQuery: Ref<string>
  route: RouteLocationNormalizedLoaded
  router: Router
  pathIncludes: string
  isListView: () => boolean
  /** z. B. Aktivitäten: nur Tab «Alle» */
  isSearchActive?: () => boolean
}) {
  const { searchQuery, route, router, pathIncludes, isListView, isSearchActive = () => true } = options

  function replaceQueryQ(term: string) {
    const q: LocationQueryRaw = { ...route.query }
    if (term) q.q = term
    else delete q.q
    const current = String(route.query.q ?? '')
    if (current === term) return
    router.replace({ path: route.path, query: q })
  }

  function clearSearchFromRoute() {
    searchQuery.value = ''
    if (!isListView() || !route.path.includes(pathIncludes)) return
    if (!route.query.q) return
    const q: LocationQueryRaw = { ...route.query }
    delete q.q
    router.replace({ path: route.path, query: q })
  }

  function stripQueryFromDetailRoute() {
    if (!route.query.q) return
    const q: LocationQueryRaw = { ...route.query }
    delete q.q
    router.replace({ path: route.path, query: q })
  }

  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  watch(searchQuery, () => {
    if (!route.path.includes(pathIncludes) || !isListView() || !isSearchActive()) return
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      replaceQueryQ(searchQuery.value.trim())
    }, 300)
  })

  watch(
    () => route.query.q,
    (q) => {
      if (!route.path.includes(pathIncludes) || !isListView()) return
      const next = (q as string) ?? ''
      if (searchQuery.value !== next) searchQuery.value = next
    },
    { immediate: true },
  )

  return { clearSearchFromRoute, stripQueryFromDetailRoute }
}
