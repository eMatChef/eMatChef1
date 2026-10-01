import type { InjectionKey } from 'vue'

export type GaBauauftragComposer = {
  open: () => void
  canAdd: boolean
}

export const gaBauauftragComposerKey: InjectionKey<GaBauauftragComposer> = Symbol('gaBauauftragComposer')
