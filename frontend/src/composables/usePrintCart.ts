import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  addPrintCartItem,
  addPrintCartItemsBulk,
  type AddPrintCartItemRequest,
} from '@/api/tasks'
import { usePrintCartStore } from '@/stores/printCart'

export function usePrintCart() {
  const store = usePrintCartStore()
  const toast = useToast()
  const { t } = useI18n()

  const count = computed(() => store.count)
  const formatLabel = computed(() => store.formatLabel)
  const queueHint = computed(() => {
    const n = store.count
    const format = store.formatLabel
    const cell = store.nextStartCell
    if (n > 0 && format && cell > 1) return t('printCart.hintWithFormatAndCell', { n, format, cell })
    if (n > 0 && format) return t('printCart.hintWithFormat', { n, format })
    if (n > 0) return t('printCart.hint', { n })
    if (format && cell > 1) return t('printCart.hintFormatAndCell', { format, cell })
    if (format) return t('printCart.hintFormatOnly', { format })
    return t('printCart.hintEmpty')
  })

  function toastForAdd(created: number, skipped: number) {
    const n = store.count
    const format = store.formatLabel
    const cell = store.nextStartCell
    const added = created > 0
    if (added && format && cell > 1) {
      toast.success(t('printCart.addedWithFormatAndCell', { added: created, n, format, cell }))
      return
    }
    if (added && format) {
      toast.success(t('printCart.addedWithFormat', { added: created, n, format }))
      return
    }
    if (added) {
      toast.success(t('printCart.added', { added: created, n }))
      return
    }
    if (skipped > 0 && format && cell > 1) {
      toast.info(t('printCart.alreadyWithFormatAndCell', { n, format, cell }))
      return
    }
    if (skipped > 0 && format) {
      toast.info(t('printCart.alreadyWithFormat', { n, format }))
      return
    }
    if (skipped > 0) {
      toast.info(t('printCart.already', { n }))
    }
  }

  async function addItems(items: AddPrintCartItemRequest[]): Promise<boolean> {
    const departmentId = items[0]?.department_id || store.departmentId
    if (!departmentId || !items.length) return false
    try {
      let created = 0
      let skipped = 0
      if (items.length === 1) {
        const result = await addPrintCartItem(items[0])
        created = result.created ? 1 : 0
        skipped = result.created ? 0 : 1
      } else {
        const result = await addPrintCartItemsBulk(departmentId, items)
        created = result.created_count
        skipped = result.skipped_count
      }
      await store.refresh(departmentId)
      toastForAdd(created, skipped)
      return true
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      toast.error(err.response?.data?.error || t('printCart.addError'))
      return false
    }
  }

  return {
    count,
    formatLabel,
    queueHint,
    refresh: (departmentId: string) => store.refresh(departmentId),
    setCount: (value: number) => store.setCount(value),
    addItems,
  }
}
