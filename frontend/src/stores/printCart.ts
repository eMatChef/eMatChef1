import { defineStore } from 'pinia'
import { getPrintCartItems } from '@/api/tasks'
import { formatPrintChoice, loadPrintChoiceLabels, loadStoredNextStartCell } from '@/print/printChoice'

export const usePrintCartStore = defineStore('printCart', {
  state: () => ({
    departmentId: '',
    count: 0,
    loaded: false,
    formatLabel: '',
    nextStartCell: 0,
  }),

  actions: {
    syncFormat(departmentId: string) {
      this.formatLabel = formatPrintChoice(loadPrintChoiceLabels(departmentId))
      const cell = loadStoredNextStartCell(departmentId)
      this.nextStartCell = cell > 1 ? cell : 0
    },

    async refresh(departmentId: string) {
      if (!departmentId) {
        this.departmentId = ''
        this.count = 0
        this.loaded = false
        this.formatLabel = ''
        this.nextStartCell = 0
        return
      }
      this.departmentId = departmentId
      this.syncFormat(departmentId)
      try {
        const items = await getPrintCartItems(departmentId)
        this.count = items.length
        this.loaded = true
      } catch {
        this.count = 0
        this.loaded = false
      }
    },

    setCount(count: number) {
      this.count = Math.max(0, count)
      this.loaded = true
    },
  },
})
