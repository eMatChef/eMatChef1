import type { useI18n } from 'vue-i18n'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

type TFunction = ReturnType<typeof useI18n>['t']

export function activityTypeLabel(type: ActivityCreateType, t: TFunction): string {
  return t(`activities.types.${type}` as 'activities.types.activity')
}
