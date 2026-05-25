import { useI18n } from 'vue-i18n'
import type { ActivityMwNotification } from '@/api/activityNotifications'
import {
  formatActivityMwBellSubtitle,
  formatActivityMwBellTitle,
  formatActivityMwInboxPreview,
  formatActivityMwInboxSubject,
  type ActivityNotificationTextLabels,
} from '@/utils/activityNotificationText'

export function useActivityNotificationText() {
  const { t } = useI18n()

  function activityTypeLabel(type: string): string {
    const key = `activities.types.${type}` as const
    const label = t(key)
    return label === key ? type : label
  }

  function actionForType(type: string): string {
    const key = `notificationsCenter.activityNotificationType.${type}`
    const label = t(key)
    return label === key
      ? t('notificationsCenter.activityNotificationType.activity_submitted')
      : label
  }

  function labels(): ActivityNotificationTextLabels {
    return {
      activityType: activityTypeLabel,
      actionForType,
      bellTitle: (name) => t('layout.notifications.newActivityTitle', { name }),
    }
  }

  function bellLine(entry: ActivityMwNotification): string {
    const action = actionForType(entry.type || 'activity_submitted')
    const name = entry.creator_name?.trim() || '–'
    const activity = entry.activity_name?.trim() || '–'
    return `${action}: «${activity}» · ${name}`
  }

  return {
    inboxSubject: (entry: ActivityMwNotification) => formatActivityMwInboxSubject(entry),
    inboxPreview: (entry: ActivityMwNotification) =>
      formatActivityMwInboxPreview(entry, labels()),
    bellTitle: (entry: ActivityMwNotification) => formatActivityMwBellTitle(entry, labels()),
    bellSubtitle: (entry: ActivityMwNotification) =>
      formatActivityMwBellSubtitle(entry, labels()),
    bellLine,
  }
}
