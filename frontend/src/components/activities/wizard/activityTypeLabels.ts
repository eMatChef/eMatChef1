import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

const LABELS: Record<ActivityCreateType, string> = {
  activity: 'Aktivität',
  camp: 'Lager',
  event: 'Event',
  external: 'Extern',
}

export function activityTypeLabel(t: ActivityCreateType): string {
  return LABELS[t]
}
