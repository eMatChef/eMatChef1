import { defineStore } from 'pinia'
import { i18n } from '@/i18n'

export type ConfirmVariant = 'info' | 'warning' | 'danger'

export interface ConfirmOptions {
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  variant?: ConfirmVariant
  /** Unix-ms: Dialog zeigt einen Live-Countdown bis zu diesem Zeitpunkt. */
  countdownEndsAt?: number
  /** Overlay-Klick schliesst den Dialog nicht (z. B. Session-Warnung). */
  persistent?: boolean
}

interface ConfirmState {
  isOpen: boolean
  options: ConfirmOptions | null
  resolve: ((value: boolean) => void) | null
}

export const useConfirmStore = defineStore('confirm', {
  state: (): ConfirmState => ({
    isOpen: false,
    options: null,
    resolve: null,
  }),

  actions: {
    show(options: ConfirmOptions | string): Promise<boolean> {
      const opts: ConfirmOptions = typeof options === 'string'
        ? { title: options, message: '' }
        : options

      return new Promise((resolve) => {
        this.options = {
          confirmText: i18n.global.t('common.confirm'),
          cancelText: i18n.global.t('common.cancel'),
          variant: 'warning',
          ...opts,
        }
        this.isOpen = true
        this.resolve = resolve
      })
    },

    confirm() {
      if (this.resolve) {
        this.resolve(true)
        this.resolve = null
      }
      this.isOpen = false
      this.options = null
    },

    cancel() {
      if (this.resolve) {
        this.resolve(false)
        this.resolve = null
      }
      this.isOpen = false
      this.options = null
    },
  },
})
