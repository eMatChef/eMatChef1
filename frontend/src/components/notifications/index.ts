export { default as NotificationSenderBlock } from './NotificationSenderBlock.vue'
export { default as InboxComposeModal } from './InboxComposeModal.vue'
export { default as InboxMessageDetailModal } from './InboxMessageDetailModal.vue'
export { default as InboxQrDetailModal } from './InboxQrDetailModal.vue'
export { default as InboxInviteDetailModal } from './InboxInviteDetailModal.vue'
export { useNotificationSender } from '@/composables/useNotificationSender'
export {
  getSenderPrimaryLine,
  type NotificationSenderDescriptor,
  type NotificationSenderKind,
} from '@/utils/notificationSender'
