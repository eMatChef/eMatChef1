import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type DetailTabType = 'material' | 'activity' | 'workshop'

export const DETAIL_TAB_TYPE_ORDER: DetailTabType[] = ['material', 'activity', 'workshop']

/** Ab dieser Anzahl Tabs werden sie nach Typ gruppiert angezeigt */
export const DETAIL_TAB_GROUP_MIN_COUNT = 5

export interface DetailTab {
  id: string
  type: DetailTabType
  label: string
  departmentId: string
  path: string
  hasUnsavedChanges: boolean
  /** Zeitstempel, wann dirty wurde (für 5-Min-Erinnerung) */
  dirtySince?: number
}

export interface DetailTabGroup {
  type: DetailTabType
  tabs: DetailTab[]
}

export function shouldGroupDetailTabs(tabs: DetailTab[]): boolean {
  if (tabs.length >= DETAIL_TAB_GROUP_MIN_COUNT) return true
  const types = new Set(tabs.map((t) => t.type))
  return types.size >= 2 && tabs.length >= 3
}

export function groupDetailTabs(tabs: DetailTab[]): DetailTabGroup[] {
  const groups: DetailTabGroup[] = []
  for (const type of DETAIL_TAB_TYPE_ORDER) {
    const groupTabs = tabs.filter((t) => t.type === type)
    if (groupTabs.length > 0) {
      groups.push({ type, tabs: groupTabs })
    }
  }
  return groups
}

export function listPathForDetailTab(tab: Pick<DetailTab, 'type' | 'departmentId'>): string {
  const base = `/${tab.departmentId}`
  switch (tab.type) {
    case 'material':
      return `${base}/materials`
    case 'activity':
      return `${base}/activities`
    case 'workshop':
      return `${base}/workshop`
    default:
      return base
  }
}

export function ticketIdFromWorkshopTabPath(path: string): string | null {
  const q = path.includes('?') ? path.slice(path.indexOf('?') + 1) : ''
  return new URLSearchParams(q).get('ticket')
}

export const useDetailTabsStore = defineStore('detailTabs', () => {
  const tabs = ref<DetailTab[]>([])

  const hasTabs = computed(() => tabs.value.length > 0)
  const useGroupedLayout = computed(() => shouldGroupDetailTabs(tabs.value))
  const tabGroups = computed(() => groupDetailTabs(tabs.value))

  function addOrUpdateTab(tab: Omit<DetailTab, 'hasUnsavedChanges'>) {
    const existing = tabs.value.find(
      (t) => t.type === tab.type && t.id === tab.id && t.departmentId === tab.departmentId
    )
    if (existing) {
      existing.label = tab.label
      existing.path = tab.path
      return existing
    }
    const newTab: DetailTab = { ...tab, hasUnsavedChanges: false }
    tabs.value = [...tabs.value, newTab]
    return newTab
  }

  function setTabDirty(tabId: string, type: DetailTabType, departmentId: string, dirty: boolean) {
    const idx = tabs.value.findIndex(
      (t) => t.id === tabId && t.type === type && t.departmentId === departmentId
    )
    if (idx === -1) return
    const tab = tabs.value[idx]
    const dirtySince = dirty ? (tab.dirtySince ?? Date.now()) : undefined
    tabs.value = tabs.value.map((t, i) =>
      i === idx ? { ...t, hasUnsavedChanges: dirty, dirtySince } : t
    )
  }

  function removeTab(tabId: string, type: DetailTabType, departmentId: string) {
    tabs.value = tabs.value.filter(
      (t) => !(t.id === tabId && t.type === type && t.departmentId === departmentId)
    )
  }

  function getTab(tabId: string, type: DetailTabType, departmentId: string): DetailTab | undefined {
    return tabs.value.find(
      (t) => t.id === tabId && t.type === type && t.departmentId === departmentId
    )
  }

  return {
    tabs,
    hasTabs,
    useGroupedLayout,
    tabGroups,
    addOrUpdateTab,
    setTabDirty,
    removeTab,
    getTab,
  }
})
