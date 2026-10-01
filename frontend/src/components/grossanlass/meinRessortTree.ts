import type { Ref } from 'vue'
import type { GrossanlassGroup, GrossanlassGroupKind } from '@/api/grossanlassGroups'

export const MEIN_RESSORT_TREE_KEY = Symbol('meinRessortTree')

export type MeinRessortTreeApi = {
  unterressortsOf: (parent: GrossanlassGroup) => GrossanlassGroup[]
  bauprojekteOf: (parent: GrossanlassGroup) => GrossanlassGroup[]
  canCreateChild: (parent: GrossanlassGroup) => boolean
  canEditGroup: (group?: GrossanlassGroup | null) => boolean
  canDeleteGroup: (group?: GrossanlassGroup | null) => boolean
  canShareGroup: (group?: GrossanlassGroup | null) => boolean
  deletingGroupId: Ref<string | null>
  projectMaterialSummary: (groupId: string) => string
  openCreateChild: (kind: GrossanlassGroupKind, parentId?: string) => void
  openProject: (group: GrossanlassGroup) => void
  openShare: (group: GrossanlassGroup) => void
  openEditGroup: (group: GrossanlassGroup) => void
  confirmDeleteGroup: (group: GrossanlassGroup) => void
  kindLabel: (group: GrossanlassGroup) => string
  shareCaption: (group: GrossanlassGroup) => string
}
