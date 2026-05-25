<template>
  <div v-if="message" class="modal-overlay" @click.self="emit('close')">
    <div
      class="modal-dialog nc-message-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('notificationsCenter.messageDetailTitle')"
      @click.stop
    >
      <header class="modal-header">
        <h3>{{ t('notificationsCenter.messageDetailTitle') }}</h3>
        <button type="button" class="modal-close" :aria-label="t('notificationsCenter.composeCancel')" @click="emit('close')">
          ×
        </button>
      </header>
      <div class="modal-body nc-message-detail">
        <div class="nc-message-detail__sender">
          <NotificationSenderBlock v-if="sender && mode !== 'sent'" :sender="sender" size="md" />
          <div>
            <div class="nc-message-detail__from">{{ headerLine }}</div>
            <time class="nc-message-detail__date">{{ formattedDate }}</time>
          </div>
        </div>
        <h4 class="nc-message-detail__subject">{{ message.subject }}</h4>
        <div class="nc-message-detail__body">{{ message.message }}</div>
      </div>
      <footer class="modal-footer">
        <button type="button" class="btn-outline btn-sm" @click="emit('close')">
          {{ t('notificationsCenter.messageDetailClose') }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { UserDirectMessage, UserDirectMessageSent } from '@/api/inboxMessages'
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'

const props = withDefaults(
  defineProps<{
    message: UserDirectMessage | UserDirectMessageSent | null
    mode?: 'inbox' | 'sent'
  }>(),
  { mode: 'inbox' },
)

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const { fromUserMessage } = useNotificationSender()

const mode = computed(() => props.mode)

const sender = computed(() =>
  props.message && props.mode === 'inbox' ? fromUserMessage(props.message as UserDirectMessage) : null,
)

const headerLine = computed(() => {
  if (!props.message) return ''
  if (props.mode === 'sent') {
    const m = props.message as UserDirectMessageSent
    return t('notificationsCenter.messageDetailTo', { name: m.recipient_name || t('layout.userFallback') })
  }
  return sender.value ? getSenderPrimaryLine(sender.value) : ''
})

const formattedDate = computed(() => {
  if (!props.message?.created_at) return ''
  try {
    return new Date(props.message.created_at).toLocaleString('de-CH', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return props.message.created_at
  }
})
</script>

<style scoped>
.nc-message-detail-modal {
  width: min(560px, calc(100vw - 48px));
  padding: 0;
  overflow: hidden;
}

.nc-message-detail-modal .modal-header,
.nc-message-detail-modal .modal-body,
.nc-message-detail-modal .modal-footer {
  margin: 0;
}

.nc-message-detail__sender {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}

.nc-message-detail__from {
  font-weight: 600;
  color: #111827;
  font-size: 0.95rem;
}

.nc-message-detail__date {
  display: block;
  margin-top: 2px;
  font-size: 0.8rem;
  color: #6b7280;
}

.nc-message-detail__subject {
  margin: 0 0 12px;
  font-size: 1.05rem;
  font-weight: 600;
  color: #111827;
}

.nc-message-detail__body {
  font-size: 0.95rem;
  line-height: 1.55;
  color: #374151;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
