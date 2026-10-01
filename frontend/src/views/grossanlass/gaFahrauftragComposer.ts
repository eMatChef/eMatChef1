import type { InjectionKey } from 'vue'

export type GaFahrauftragComposer = {
  open: () => void
  canAdd: boolean
}

export const gaFahrauftragComposerKey: InjectionKey<GaFahrauftragComposer> = Symbol('gaFahrauftragComposer')
