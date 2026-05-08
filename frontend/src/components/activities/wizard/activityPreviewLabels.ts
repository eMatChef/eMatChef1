import type { useI18n } from 'vue-i18n'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

type TFunction = ReturnType<typeof useI18n>['t']

/**
 * Kompakte Sidebar-/Übersichts-Labels, passend zu den Zeitraum-Blöcken im Formular
 * (z. B. „Lager findet statt“, „Datum und Zeit der Aktivität“ …).
 */
export function activityPreviewUsageLabel(type: ActivityCreateType, t: TFunction): string {
  if (type === 'external') {
    return t('activities.wizard.previewUsageExternal')
  }
  return t(`activities.types.${type}` as 'activities.types.activity')
}

/** Entspricht „Abholung / Rückgabe“ bzw. Material-Zeiten (ein Label für alle Typen). */
export function activityPreviewMaterialLabel(_type: ActivityCreateType, t: TFunction): string {
  return t('activities.wizard.previewMaterial')
}
