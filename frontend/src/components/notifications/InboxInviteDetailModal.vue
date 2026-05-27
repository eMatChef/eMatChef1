<template>
  <div v-if="visible" class="modal-overlay" @click.self="emit('close')">
    <div
      class="modal-dialog nc-message-detail-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.stop
    >
      <header class="modal-header">
        <h3>{{ title }}</h3>
        <button type="button" class="modal-close" :aria-label="t('notificationsCenter.composeCancel')" @click="emit('close')">
          ×
        </button>
      </header>
      <div class="modal-body nc-message-detail">
        <div v-if="sender" class="nc-message-detail__sender">
          <NotificationSenderBlock :sender="sender" size="md" />
          <div>
            <div class="nc-message-detail__from">{{ senderLine }}</div>
            <time v-if="formattedDate" class="nc-message-detail__date">{{ formattedDate }}</time>
          </div>
        </div>
        <h4 class="nc-message-detail__subject">{{ subject }}</h4>
        <div class="nc-message-detail__body">{{ preview }}</div>
        <p v-if="!showTask" class="nc-invite-detail__hint">{{ t('notificationsCenter.messageWithTaskHint') }}</p>
        <div v-else class="nc-invite-detail__task-panel">
          <p class="nc-invite-detail__task-label">{{ t('notificationsCenter.taskPanelLabel') }}</p>
          <slot name="task" />
        </div>
      </div>
      <footer class="modal-footer nc-invite-detail__footer">
        <template v-if="!showTask">
          <button type="button" class="btn-primary btn-sm" @click="onProceed">
            {{ t('notificationsCenter.proceedToTask') }}
          </button>
          <button type="button" class="btn-outline btn-sm" @click="emit('close')">
            {{ t('notificationsCenter.messageDetailClose') }}
          </button>
        </template>
        <template v-else>
          <slot name="task-actions" />
          <button type="button" class="btn-outline btn-sm" @click="emit('close')">
            {{ t('notificationsCenter.messageDetailClose') }}
          </button>
        </template>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import type { NotificationSenderDescriptor } from '@/utils/notificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'

const props = withDefaults(
  defineProps<{
    visible: boolean
    title: string
    subject: string
    preview: string
    sender: NotificationSenderDescriptor | null
    createdAt?: string
    /** true: „Aufgabe bearbeiten“ leitet zur Aufgaben-Seite statt Modal-Umschaltung */
    navigateOnProceed?: boolean
  }>(),
  { navigateOnProceed: false },
)

const emit = defineEmits<{
  close: []
  'open-task': []
}>()

const { t } = useI18n()
const showTask = ref(false)

const senderLine = computed(() => (props.sender ? getSenderPrimaryLine(props.sender) : ''))

const formattedDate = computed(() => {
  if (!props.createdAt) return ''
  try {
    return new Date(props.createdAt).toLocaleString('de-CH', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return props.createdAt
  }
})

watch(
  () => props.visible,
  (open) => {
    if (!open) showTask.value = false
  },
)

function onProceed() {
  if (props.navigateOnProceed) {
    emit('open-task')
    return
  }
  showTask.value = true
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

.nc-message-detail__subject {
  margin: 0 0 10px;
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

.nc-invite-detail__hint {
  margin: 14px 0 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.nc-invite-detail__task-panel {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #e5e7eb;
}

.nc-invite-detail__task-label {
  margin: 0 0 10px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.nc-invite-detail__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
</style>
