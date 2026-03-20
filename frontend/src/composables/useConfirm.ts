import { useConfirmStore, type ConfirmOptions } from '@/stores/confirm'

export function useConfirm() {
  const confirmStore = useConfirmStore()

  return {
    confirm: (options: ConfirmOptions | string): Promise<boolean> =>
      confirmStore.show(options),
  }
}
