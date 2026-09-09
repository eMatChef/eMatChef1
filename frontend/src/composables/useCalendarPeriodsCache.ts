import { ref } from 'vue'

/** Steigt nach Speichern Fixer Daten — Datumsfelder und Listen laden neu. */
const revision = ref(0)

export function bumpCalendarPeriodsCache(): void {
  revision.value += 1
}

export function useCalendarPeriodsCacheRevision() {
  return revision
}
