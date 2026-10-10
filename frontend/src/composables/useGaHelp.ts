import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { GA_HELP_PAGE_ROUTE, gaHelpTopicIdForRoute } from '@/config/gaHelp'

/** Modal-Zustand ist modulweit: Hilfe-Button (useHelpShortcut) und Modal (AppLayout) teilen ihn. */
const modalOpen = ref(false)

/**
 * Kontext der Grossanlass-Hilfe. Ein Kapitel gibt es nur in einem Grossanlass-Department und auf GA-Seiten;
 * sonst ist `topicId` null und die Department-Hilfe bleibt unverändert zuständig.
 */
export function useGaHelp() {
  const route = useRoute()
  const authStore = useAuthStore()

  const departmentId = computed(() => {
    const value = route.params.departmentId
    return typeof value === 'string' && value ? value : authStore.activeDepartmentId || ''
  })

  const topicId = computed(() => {
    if (!departmentId.value || !authStore.isDepartmentGrossanlass(departmentId.value)) return null
    return gaHelpTopicIdForRoute(route.name)
  })

  const isGaHelpPage = computed(() => route.name === GA_HELP_PAGE_ROUTE)

  /** Ausführliche Hilfe mit Sprung zum Kapitel. */
  function helpPageLocation(id?: string | null) {
    return {
      name: GA_HELP_PAGE_ROUTE,
      params: { departmentId: departmentId.value, ...(id ? { topic: id } : {}) },
    }
  }

  function open(): void {
    if (topicId.value) modalOpen.value = true
  }

  function close(): void {
    modalOpen.value = false
  }

  return { departmentId, topicId, isGaHelpPage, modalOpen, helpPageLocation, open, close }
}
