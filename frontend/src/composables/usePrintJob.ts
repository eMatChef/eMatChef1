import { usePrintJobStore } from '@/stores/printJob'
import type { OpenPrintJobOptions } from '@/print/printJob'

export type { OpenPrintJobOptions, PrintJobItem } from '@/print/printJob'

export function usePrintJob() {
  const store = usePrintJobStore()

  return {
    openPrint(options: OpenPrintJobOptions) {
      if (!options.departmentId || !options.items.length) return
      store.open(options)
    },
  }
}
