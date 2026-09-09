import { computed, ref } from 'vue'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'

const cache = ref<Record<string, boolean>>({})
const loaded = ref<Record<string, boolean>>({})

/** Gast-Abteilungen (Teilnehmer/Freigabe). False = nur OK intern. */
export function useGrossanlassGuestDepartments(departmentId: () => string) {
  const hasGuestDepartments = computed(() => cache.value[departmentId()] === true)
  const known = computed(() => loaded.value[departmentId()] === true)

  async function refresh() {
    const id = departmentId()
    if (!id) return
    const pack = await getGrossanlassPlanung(id)
    setHasGuestDepartments(pack.config.has_guest_departments === true)
  }

  function setHasGuestDepartments(value: boolean) {
    const id = departmentId()
    if (!id) return
    cache.value = { ...cache.value, [id]: value }
    loaded.value = { ...loaded.value, [id]: true }
  }

  return { hasGuestDepartments, known, refresh, setHasGuestDepartments }
}
