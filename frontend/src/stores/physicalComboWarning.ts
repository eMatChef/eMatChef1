import { defineStore } from 'pinia'
import { fetchLinkedCombosForContainerBatchIds } from '@/utils/physicalComboContainerWarning'

interface PhysicalComboWarningState {
  isOpen: boolean
  combos: { id: string; name: string }[]
  resolve: ((proceed: boolean) => void) | null
}

export const usePhysicalComboWarningStore = defineStore('physicalComboWarning', {
  state: (): PhysicalComboWarningState => ({
    isOpen: false,
    combos: [],
    resolve: null,
  }),

  actions: {
    /**
     * @returns true = Bestandsänderung fortsetzen, false = Abbrechen (inkl. nach „Kombination öffnen“)
     */
    async confirmContainerMove(containerBatchIds: string[]): Promise<boolean> {
      const combos = await fetchLinkedCombosForContainerBatchIds(containerBatchIds)
      if (combos.length === 0) return true

      return new Promise((resolve) => {
        this.combos = combos
        this.resolve = resolve
        this.isOpen = true
      })
    },

    proceed() {
      this.resolve?.(true)
      this._close()
    },

    cancel() {
      this.resolve?.(false)
      this._close()
    },

    /** Nach Navigation zur Kombination: gleiche Wirkung wie Abbrechen für die laufende Aktion */
    abortAfterOpenCombo() {
      this.resolve?.(false)
      this._close()
    },

    _close() {
      this.isOpen = false
      this.resolve = null
      this.combos = []
    },
  },
})
