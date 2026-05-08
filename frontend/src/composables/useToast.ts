import { useToastStore, type ToastType } from '@/stores/toast'

export function useToast() {
  const toastStore = useToastStore()

  return {
    push: (message: string, options?: { type?: ToastType; duration?: number }) =>
      toastStore.push(message, options),
    success: (message: string, duration?: number) => toastStore.success(message, duration),
    error: (message: string, duration?: number) => toastStore.error(message, duration),
    warning: (message: string, duration?: number) => toastStore.warning(message, duration),
    info: (message: string, duration?: number) => toastStore.info(message, duration),
    remove: (id: number) => toastStore.remove(id),
    clearAll: () => toastStore.clearAll(),
  }
}
