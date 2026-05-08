import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchPublicSitePages } from '@/api/sitePages'

export const useSiteContentStore = defineStore('siteContent', () => {
  const pages = ref<Record<string, Record<string, unknown>>>({})
  const loaded = ref(false)
  const loadError = ref<string | null>(null)

  async function ensureLoaded(): Promise<void> {
    if (loaded.value) return
    loadError.value = null
    try {
      const data = await fetchPublicSitePages()
      for (const p of data.pages) {
        pages.value[p.slug] = p.content
      }
      loaded.value = true
    } catch (e) {
      loadError.value = e instanceof Error ? e.message : 'Laden fehlgeschlagen'
    }
  }

  function getContent(slug: string): Record<string, unknown> {
    return pages.value[slug] || {}
  }

  /** Öffentliche Inhalte neu laden (z. B. nach Speichern im Admin). */
  async function refresh(): Promise<void> {
    loadError.value = null
    try {
      const data = await fetchPublicSitePages()
      for (const p of data.pages) {
        pages.value[p.slug] = p.content
      }
      loaded.value = true
    } catch (e) {
      loadError.value = e instanceof Error ? e.message : 'Laden fehlgeschlagen'
    }
  }

  return { pages, loaded, loadError, ensureLoaded, getContent, refresh }
})
