import type { InjectionKey } from 'vue'
import type { GaBookPreviewMode } from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'

export type GaEinsatzComposer = {
  open: (mode: GaBookPreviewMode) => void
}

export const gaEinsatzComposerKey: InjectionKey<GaEinsatzComposer> = Symbol('gaEinsatzComposer')
