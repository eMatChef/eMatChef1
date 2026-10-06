import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Zustand des globalen Step-up-Dialogs. Parallele Anfragen teilen sich genau einen Dialog
 * (Single-Flight): alle warten auf dasselbe Ergebnis.
 */
export const useStepUpStore = defineStore('stepUp', () => {
  const isOpen = ref(false)
  let pending: Promise<boolean> | null = null
  let resolver: ((ok: boolean) => void) | null = null

  function request(): Promise<boolean> {
    if (pending) return pending
    isOpen.value = true
    pending = new Promise<boolean>((resolve) => {
      resolver = resolve
    })
    return pending
  }

  function finish(ok: boolean): void {
    isOpen.value = false
    const resolve = resolver
    resolver = null
    pending = null
    resolve?.(ok)
  }

  return { isOpen, request, finish }
})
