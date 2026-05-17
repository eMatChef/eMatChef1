<template>
  <div v-if="message" class="modal-overlay" @click.self="emit('close')">
    <div
      class="modal-dialog nc-message-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('notificationsCenter.qrDetailTitle')"
      @click.stop
    >
      <header class="modal-header">
        <h3>{{ t('notificationsCenter.qrDetailTitle') }}</h3>
        <button type="button" class="modal-close" :aria-label="t('notificationsCenter.composeCancel')" @click="emit('close')">
          ×
        </button>
      </header>
      <div class="modal-body nc-message-detail">
        <div class="nc-message-detail__sender">
          <NotificationSenderBlock v-if="sender" :sender="sender" size="md" />
          <div>
            <div class="nc-message-detail__from">{{ senderLine }}</div>
            <time class="nc-message-detail__date">{{ formattedDate }}</time>
          </div>
        </div>
        <p class="nc-qr-detail__material">
          <strong>{{ t('notificationsCenter.tableMaterial') }}:</strong> {{ message.material_name }}
        </p>
        <div class="nc-message-detail__body">{{ message.message }}</div>
        <p v-if="message.sender_email" class="nc-qr-detail__email">
          <strong>{{ t('notificationsCenter.tableSender') }}:</strong> {{ message.sender_email }}
        </p>
      </div>
      <footer class="modal-footer nc-qr-detail__footer">
        <select
          class="nc-status-select"
          :value="message.status"
          :title="t('notificationsCenter.tableStatus')"
          @change="onStatusChange"
        >
          <option value="open">{{ t('notificationsCenter.statusOpen') }}</option>
          <option value="in_progress">{{ t('notificationsCenter.statusInProgress') }}</option>
          <option value="done">{{ t('notificationsCenter.statusDone') }}</option>
        </select>
        <button type="button" class="btn-outline btn-sm" @click="emit('open-material', message)">
          {{ t('notificationsCenter.openMaterial') }}
        </button>
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
import type { PublicFoundItemMessage, PublicFoundMessageStatus } from '@/api/publicFound'
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'

const props = defineProps<{
  message: PublicFoundItemMessage | null
}>()

const emit = defineEmits<{
  close: []
  'open-material': [message: PublicFoundItemMessage]
  'status-change': [message: PublicFoundItemMessage, status: PublicFoundMessageStatus]
}>()

const { t } = useI18n()
const { fromPublicFound } = useNotificationSender()

const sender = computed(() => (props.message ? fromPublicFound(props.message) : null))
const senderLine = computed(() => (sender.value ? getSenderPrimaryLine(sender.value) : ''))

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

function onStatusChange(ev: Event) {
  if (!props.message) return
  const el = ev.target as HTMLSelectElement
  emit('status-change', props.message, el.value as PublicFoundMessageStatus)
}
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
  margin-bottom: 12px;
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

.nc-qr-detail__material {
  margin: 0 0 12px;
  font-size: 0.9rem;
  color: #374151;
}

.nc-message-detail__body {
  font-size: 0.95rem;
  line-height: 1.55;
  color: #374151;
  white-space: pre-wrap;
  word-break: break-word;
}

.nc-qr-detail__email {
  margin: 12px 0 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.nc-qr-detail__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
</style>
