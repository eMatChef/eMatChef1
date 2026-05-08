import { defineStore } from 'pinia'

/**
 * Dynamischer Titel/Beschreibung (z. B. Materialname, öffentliche Lookup-Seite).
 * Wird bei Router-Navigation zurückgesetzt; statische Werte kommen aus route.meta.
 */
export const usePageHeadStore = defineStore('pageHead', {
  state: () => ({
    dynamicTitle: null as string | null,
    dynamicDescription: null as string | null,
  }),
  actions: {
    setDynamic(title: string, description?: string) {
      this.dynamicTitle = title
      this.dynamicDescription = description ?? null
    },
    clearDynamic() {
      this.dynamicTitle = null
      this.dynamicDescription = null
    },
  },
})
