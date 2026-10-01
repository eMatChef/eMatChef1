import { ref, type Ref } from 'vue'

export type GaBauprojektPanelExpose = {
  confirmClose: () => Promise<boolean>
}

export function useBauprojektPanelClose(open: Ref<boolean>) {
  const panelRef = ref<GaBauprojektPanelExpose | null>(null)

  async function requestClose() {
    if (panelRef.value && !(await panelRef.value.confirmClose())) return
    open.value = false
  }

  return { panelRef, requestClose }
}
