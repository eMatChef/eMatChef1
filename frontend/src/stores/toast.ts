import { defineStore } from 'pinia'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface ToastItem {
  id: number
  type: ToastType
  message: string
  duration: number
}

interface ToastState {
  items: ToastItem[]
  nextId: number
}

const toastTimers = new Map<number, ReturnType<typeof setTimeout>>()
const MAX_TOASTS = 5

export const useToastStore = defineStore('toast', {
  state: (): ToastState => ({
    items: [],
    nextId: 1,
  }),

  actions: {
    push(message: string, options?: { type?: ToastType; duration?: number }) {
      const id = this.nextId++
      const type = options?.type ?? 'info'
      const duration = options?.duration ?? 3500

      // Ältesten Toast entfernen wenn Limit erreicht
      if (this.items.length >= MAX_TOASTS) {
        const oldest = this.items[0]
        if (oldest) this.remove(oldest.id)
      }

      this.items.push({ id, type, message, duration })

      if (duration > 0) {
        const timer = setTimeout(() => {
          this.remove(id)
        }, duration)
        toastTimers.set(id, timer)
      }

      return id
    },

    success(message: string, duration = 3000) {
      return this.push(message, { type: 'success', duration })
    },

    error(message: string, duration = 5000) {
      return this.push(message, { type: 'error', duration })
    },

    warning(message: string, duration = 4000) {
      return this.push(message, { type: 'warning', duration })
    },

    info(message: string, duration = 3500) {
      return this.push(message, { type: 'info', duration })
    },

    remove(id: number) {
      const idx = this.items.findIndex(t => t.id === id)
      if (idx !== -1) {
        this.items.splice(idx, 1)
      }

      const timer = toastTimers.get(id)
      if (timer) {
        clearTimeout(timer)
        toastTimers.delete(id)
      }
    },

    clearAll() {
      for (const timer of toastTimers.values()) {
        clearTimeout(timer)
      }
      toastTimers.clear()
      this.items = []
    },
  },
})
