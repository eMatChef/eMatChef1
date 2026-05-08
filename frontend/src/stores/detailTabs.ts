import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type DetailTabType = 'material' | 'activity'

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

export const useDetailTabsStore = defineStore('detailTabs', () => {
  const tabs = ref<DetailTab[]>([])

  const hasTabs = computed(() => tabs.value.length > 0)

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
    addOrUpdateTab,
    setTabDirty,
    removeTab,
    getTab,
  }
})
