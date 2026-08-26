import { defineStore } from 'pinia'
import { DEFAULT_PRINT_CONTENT, type PrintContentKey } from '@/print/layoutFields'
import type { OpenPrintJobOptions, PrintJobItem } from '@/print/printJob'

interface PrintJobState {
  isOpen: boolean
  departmentId: string
  items: PrintJobItem[]
  availableFields: PrintContentKey[]
  kind: string
  onPrinted: (() => void | Promise<void>) | null
}

export const usePrintJobStore = defineStore('printJob', {
  state: (): PrintJobState => ({
    isOpen: false,
    departmentId: '',
    items: [],
    availableFields: [...DEFAULT_PRINT_CONTENT],
    kind: 'label',
    onPrinted: null,
  }),

  actions: {
    open(options: OpenPrintJobOptions) {
      this.departmentId = options.departmentId
      this.items = options.items
      this.availableFields = options.availableFields?.length
        ? [...options.availableFields]
        : [...DEFAULT_PRINT_CONTENT]
      this.kind = options.kind || 'label'
      this.onPrinted = options.onPrinted || null
      this.isOpen = true
    },

    close() {
      this.isOpen = false
      this.onPrinted = null
      this.items = []
      this.departmentId = ''
    },
  },
})
