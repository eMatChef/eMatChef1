import { useDetailTabsStore } from '@/stores/detailTabs'
import { useConfirm } from '@/composables/useConfirm'
import type { ComposerTranslation } from 'vue-i18n'

export function useUnsavedLeaveGuard() {
  const detailTabsStore = useDetailTabsStore()
  const confirm = useConfirm()

  function hasUnsavedDetailTabs(): boolean {
    return detailTabsStore.tabs.some((tab) => tab.hasUnsavedChanges)
  }

  /** @returns true wenn Navigation fortgesetzt werden darf */
  async function confirmLeaveIfDirty(t: ComposerTranslation): Promise<boolean> {
    if (!hasUnsavedDetailTabs()) return true
    return confirm.confirm({
      title: t('layout.confirm.unsavedTitle'),
      message: t('layout.confirm.unsavedMessage'),
      confirmText: t('common.close'),
      cancelText: t('layout.confirm.back'),
      variant: 'warning',
    })
  }

  return { hasUnsavedDetailTabs, confirmLeaveIfDirty }
}
