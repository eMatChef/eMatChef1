import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

/**
 * Kompakte Sidebar-/Übersichts-Labels, passend zu den Zeitraum-Blöcken im Formular
 * (z. B. „Lager findet statt“, „Datum und Zeit der Aktivität“ …).
 */
export function activityPreviewUsageLabel(t: ActivityCreateType): string {
  switch (t) {
    case 'camp':
      return 'Lager'
    case 'event':
      return 'Event'
    case 'external':
      return 'Zeitraum'
    default:
      return 'Aktivität'
  }
}

/** Entspricht „Abholung / Rückgabe“ bzw. Material-Zeiten (ein Label für alle Typen). */
export function activityPreviewMaterialLabel(_t: ActivityCreateType): string {
  return 'Abholung / Rückgabe'
}
