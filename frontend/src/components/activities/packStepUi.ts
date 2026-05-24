import {
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'

/** Kisten-Kartenmodus — Design-Vorlage: Gepackt → Am Event (`PackWarehouseIssueContainerCard`). */
export type PackContainerCardMode =
  | 'confirmed_packed_target'
  | 'warehouse_issue'
  | 'warehouse_issue_mirror'
  | 'at_event_return'
  | 'at_event_return_mirror'

export interface PackCrateSectionPreset {
  sectionClass?: string
  titleKey: string
  hintKey?: string
  ariaKey: string
  atEventSelect?: boolean
  showContainersHeading?: boolean
  emptyHintKey?: string
  cardMode: PackContainerCardMode
  containerDomIdPrefix?: string
}

export interface PackMirrorSectionPreset {
  sectionClass: string
  titleKey: string
  cratesHintKey?: string
  cratesAriaKey: string
  looseSectionClass?: string
  looseTitleKey?: string
  cardMode: PackContainerCardMode
  containerDomIdPrefix?: string
}

/** Rechtes Kisten-Panel — Bestätigt → Gepackt (Ziel zum Einpacken). */
export const PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT: PackCrateSectionPreset = {
  titleKey: 'activities.packList.sectionKisten',
  hintKey: 'activities.packList.selectCrateHint',
  ariaKey: 'activities.packList.ariaContainersThisList',
  sectionClass: 'pack-workflow-section--confirmed-crates-right',
  cardMode: 'confirmed_packed_target',
}

/** Rechtes Lose-Panel — Bestätigt → Gepackt (gepackte lose Mengen). */
export const PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE: PackMirrorSectionPreset = {
  sectionClass: 'pack-workflow-section--lose',
  titleKey: 'activities.packList.sectionLoose',
  cratesAriaKey: 'activities.packList.ariaContainersThisList',
  cardMode: 'confirmed_packed_target',
}

/** Linkes Kisten-Panel — Vorlage aus Gepackt → Am Event. */
export const PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT: PackCrateSectionPreset = {
  titleKey: 'activities.packList.sectionKisten',
  ariaKey: 'activities.packList.ariaContainersThisList',
  emptyHintKey: 'activities.packList.hintNoContainersIssue',
  showContainersHeading: true,
  cardMode: 'warehouse_issue',
}

/** Linkes Kisten-Panel — Am Event → Retour (Spiegel der Ausgabe-Stufe). */
export const PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT: PackCrateSectionPreset = {
  titleKey: 'activities.packList.sectionKisten',
  hintKey: 'activities.packList.hintReturnCratesOnLeft',
  ariaKey: 'activities.packList.ariaContainersAtEventReturn',
  sectionClass: 'pack-workflow-section--return-kisten-left',
  atEventSelect: true,
  cardMode: 'at_event_return',
}

/** Rechtes Spiegel-Panel — Bereits ans Event (Gepackt → Am Event). */
export const PACK_MIRROR_SECTION_FORWARD_AT_EVENT: PackMirrorSectionPreset = {
  sectionClass: 'pack-workflow-section--at-event',
  titleKey: 'activities.packList.sectionAlreadyAtEvent',
  cratesHintKey: 'activities.packList.selectCrateAtEventHint',
  cratesAriaKey: 'activities.packList.ariaContainersAtEventMirror',
  looseSectionClass: 'pack-workflow-section--at-event-loose',
  looseTitleKey: 'activities.packList.sectionLoose',
  cardMode: 'warehouse_issue_mirror',
  containerDomIdPrefix: 'pack-container-at-event-',
}

/** Linkes Kisten-Panel — Retour → Ausgepackt (MW: retournierte Kisten noch einräumen). */
export const PACK_CRATE_SECTION_UNPACK_WAREHOUSE_LEFT: PackCrateSectionPreset = {
  titleKey: 'activities.packList.sectionKisten',
  hintKey: 'activities.packList.hintUnpackCratesOnLeft',
  ariaKey: 'activities.packList.ariaContainersUnpackLeft',
  sectionClass: 'pack-workflow-section--unpack-kisten-left',
  cardMode: 'at_event_return',
}

/** Rechtes Spiegel-Panel — Bereits eingelagert (Retour → Ausgepackt). */
export const PACK_MIRROR_SECTION_UNPACK_STORED: PackMirrorSectionPreset = {
  sectionClass: 'pack-workflow-section--unpack-stored-mirror',
  titleKey: 'activities.packList.sectionAlreadyStored',
  cratesHintKey: 'activities.packList.hintUnpackCratesStoredRight',
  cratesAriaKey: 'activities.packList.ariaContainersStoredMirror',
  looseSectionClass: 'pack-workflow-section--stored-loose',
  looseTitleKey: 'activities.packList.sectionStoredLoose',
  cardMode: 'at_event_return_mirror',
  containerDomIdPrefix: 'pack-container-stored-',
}

/** Rechtes Spiegel-Panel — Bereits retourniert (Am Event → Retour). */
export const PACK_MIRROR_SECTION_RETURN_DONE: PackMirrorSectionPreset = {
  sectionClass: 'pack-workflow-section--returned-mirror',
  titleKey: 'activities.packList.sectionAlreadyReturned',
  cratesAriaKey: 'activities.packList.ariaContainersReturnedMirror',
  looseSectionClass: 'pack-workflow-section--returned-loose',
  looseTitleKey: 'activities.packList.sectionLoose',
  cardMode: 'at_event_return_mirror',
  containerDomIdPrefix: 'pack-container-returned-',
}

export function packCrateSectionPresetForLeft(stage: PackStage): PackCrateSectionPreset | null {
  if (isPackForwardToEventStage(stage)) return PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT
  if (isPackReturnStage(stage)) return PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT
  if (isPackUnpackStage(stage)) return PACK_CRATE_SECTION_UNPACK_WAREHOUSE_LEFT
  return null
}

export function packCrateSectionPresetForRight(stage: PackStage): PackCrateSectionPreset | null {
  if (isPackConfirmedStage(stage)) return PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT
  return null
}

export function packMirrorSectionPresetForRight(stage: PackStage): PackMirrorSectionPreset | null {
  if (isPackConfirmedStage(stage)) return PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE
  if (isPackForwardToEventStage(stage)) return PACK_MIRROR_SECTION_FORWARD_AT_EVENT
  if (isPackReturnStage(stage)) return PACK_MIRROR_SECTION_RETURN_DONE
  if (isPackUnpackStage(stage)) return PACK_MIRROR_SECTION_UNPACK_STORED
  return null
}

export type WorkflowStatusConfirmKind = 'at_event' | 'returned'

export const WORKFLOW_STATUS_CONFIRM_CONFIG: Record<
  WorkflowStatusConfirmKind,
  {
    toastNothingKey: string
    confirmTitleKey: string
    confirmProceedKey: string
    pendingVariant: 'status' | 'return'
  }
> = {
  at_event: {
    toastNothingKey: 'activities.packList.toastNothingAtEventYet',
    confirmTitleKey: 'activities.packList.confirmWorkflowStatusTitle',
    confirmProceedKey: 'activities.packList.confirmWorkflowStatusProceed',
    pendingVariant: 'status',
  },
  returned: {
    toastNothingKey: 'activities.packList.toastNothingAtEventForReturnYet',
    confirmTitleKey: 'activities.packList.confirmReturnWorkflowStatusTitle',
    confirmProceedKey: 'activities.packList.confirmReturnWorkflowStatusProceed',
    pendingVariant: 'return',
  },
}
