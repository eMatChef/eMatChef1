import { defineStore } from 'pinia'
import { i18n } from '@/i18n'

export interface PromptOptions {
  title: string
  message?: string
  placeholder?: string
  defaultValue?: string
  confirmText?: string
  cancelText?: string
  required?: boolean
}

interface PromptState {
  isOpen: boolean
  options: PromptOptions | null
  inputValue: string
  resolve: ((value: string | null) => void) | null
}

export const usePromptStore = defineStore('prompt', {
  state: (): PromptState => ({
    isOpen: false,
    options: null,
    inputValue: '',
    resolve: null,
  }),

  actions: {
    show(options: PromptOptions): Promise<string | null> {
      return new Promise((resolve) => {
        this.options = {
          confirmText: i18n.global.t('common.confirm'),
          cancelText: i18n.global.t('common.cancel'),
          required: false,
          ...options,
        }
        this.inputValue = options.defaultValue ?? ''
        this.isOpen = true
        this.resolve = resolve
      })
    },

    confirm() {
      if (this.options?.required && !this.inputValue.trim()) {
        return // Pflichtfeld leer – Dialog offen lassen
      }
      if (this.resolve) {
        this.resolve(this.inputValue.trim() || null)
        this.resolve = null
      }
      this.isOpen = false
      this.options = null
      this.inputValue = ''
    },

    cancel() {
      if (this.resolve) {
        this.resolve(null)
        this.resolve = null
      }
      this.isOpen = false
      this.options = null
      this.inputValue = ''
    },
  },
})
