import { useI18n } from 'vue-i18n'
import type { ActivityMwNotification } from '@/api/activityNotifications'
import type {
  PendingDepartmentActivityInvite,
  ReceivedDepartmentInviteNotification,
} from '@/api/joinRequests'
import type { UserDirectMessage } from '@/api/inboxMessages'
import type { PublicFoundItemMessage } from '@/api/publicFoundMessages'
import {
  senderFromActivityInvite,
  senderFromActivityMw,
  senderFromDepartmentInvite,
  senderFromPublicFound,
  senderFromUserMessage,
  type NotificationSenderDescriptor,
  type NotificationSenderLabels,
} from '@/utils/notificationSender'

export function useNotificationSender() {
  const { t } = useI18n()

  const labels: NotificationSenderLabels = {
    systemDefault: t('notificationsCenter.senderSystemDefault'),
    systemActivity: t('notificationsCenter.senderSystemActivity'),
    taskQrSource: t('notificationsCenter.senderTaskQr'),
    externalSenderFallback: t('notificationsCenter.externalSenderFallback'),
  }

  return {
    fromActivityMw: (entry: ActivityMwNotification): NotificationSenderDescriptor =>
      senderFromActivityMw(entry, labels),
    fromDepartmentInvite: (
      inv: ReceivedDepartmentInviteNotification,
    ): NotificationSenderDescriptor => senderFromDepartmentInvite(inv),
    fromPublicFound: (msg: PublicFoundItemMessage): NotificationSenderDescriptor =>
      senderFromPublicFound(msg, labels),
    fromActivityInvite: (
      invite: PendingDepartmentActivityInvite,
    ): NotificationSenderDescriptor => senderFromActivityInvite(invite),
    fromUserMessage: (msg: UserDirectMessage): NotificationSenderDescriptor =>
      senderFromUserMessage(msg),
  }
}
