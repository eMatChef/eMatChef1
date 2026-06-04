<template>
  <EDialog
    v-model="open"
    :max-width="560"
    :title="t('notificationsCenter.qrDetailTitle')"
  >
    <div v-if="message" class="nc-message-detail">
      <div class="nc-message-detail__sender">
        <NotificationSenderBlock v-if="sender" :sender="sender" size="md" />
        <div>
          <div class="nc-message-detail__from">{{ senderLine }}</div>
          <time class="nc-message-detail__date">{{ formattedDate }}</time>
        </div>
      </div>
      <p class="nc-qr-detail__material">
        <strong>{{ t('common.material') }}:</strong> {{ message.material_name }}
      </p>
      <div class="nc-message-detail__body">{{ message.message }}</div>
      <p v-if="message.sender_email" class="nc-qr-detail__email">
        <strong>{{ t('notificationsCenter.tableSender') }}:</strong> {{ message.sender_email }}
      </p>
      <p v-if="!showTask" class="nc-qr-detail__hint">{{ t('notificationsCenter.messageWithTaskHint') }}</p>
      <div v-else class="nc-qr-detail__task-panel">
        <p class="nc-qr-detail__task-label">{{ t('notificationsCenter.taskPanelLabel') }}</p>
        <ESelect
          :model-value="message.status"
          :items="statusItems"
          :label="t('common.status')"
          hide-details
          @update:model-value="onStatusSelect"
        />
        <p v-if="canReply" class="nc-qr-detail__reply-hint">{{ t('notificationsCenter.replyHint') }}</p>
      </div>
    </div>
    <template #actions>
      <template v-if="message && !showTask">
        <EButton variant="primary" size="small" @click="onProceedToTask">
          {{ t('notificationsCenter.proceedToTask') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="close">
          {{ t('notificationsCenter.messageDetailClose') }}
        </EButton>
      </template>
      <template v-else-if="message">
        <EButton v-if="canReply" variant="primary" size="small" :title="t('notificationsCenter.replyTitle')" @click="onReply">
          {{ t('notificationsCenter.reply') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="emit('open-material', message)">
          {{ t('notificationsCenter.openMaterial') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="close">
          {{ t('notificationsCenter.messageDetailClose') }}
        </EButton>
      </template>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PublicFoundItemMessage, PublicFoundMessageStatus } from '@/api/publicFoundMessages'
import { openPublicFoundReplyMailto } from '@/api/publicFoundMessages'
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import '@/styles/components/inbox-modal.css'

const props = withDefaults(
  defineProps<{
    message: PublicFoundItemMessage | null
    startOnTask?: boolean
    navigateOnProceed?: boolean
  }>(),
  { startOnTask: false, navigateOnProceed: false },
)

const emit = defineEmits<{
  close: []
  'open-material': [message: PublicFoundItemMessage]
  'status-change': [message: PublicFoundItemMessage, status: PublicFoundMessageStatus]
  'proceed-to-task': [message: PublicFoundItemMessage]
  'open-task': [message: PublicFoundItemMessage]
}>()

const { t } = useI18n()
const { fromPublicFound } = useNotificationSender()
const showTask = ref(false)

const open = computed({
  get: () => props.message != null,
  set: (value: boolean) => {
    if (!value) emit('close')
  },
})

const sender = computed(() => (props.message ? fromPublicFound(props.message) : null))
const senderLine = computed(() => (sender.value ? getSenderPrimaryLine(sender.value) : ''))
const canReply = computed(() => Boolean(props.message?.sender_email?.trim()))

const statusItems = computed(() => [
  { title: t('notificationsCenter.statusOpen'), value: 'open' },
  { title: t('notificationsCenter.statusInProgress'), value: 'in_progress' },
  { title: t('notificationsCenter.statusDone'), value: 'done' },
])

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

watch(
  () => [props.message?.id, props.startOnTask] as const,
  () => {
    showTask.value = props.startOnTask
  },
  { immediate: true },
)

function close() {
  emit('close')
}

function onProceedToTask() {
  if (!props.message) return
  if (props.navigateOnProceed) {
    emit('open-task', props.message)
    return
  }
  showTask.value = true
  emit('proceed-to-task', props.message)
}

function onStatusSelect(value: unknown) {
  if (!props.message) return
  emit('status-change', props.message, value as PublicFoundMessageStatus)
}

function onReply() {
  if (!props.message || !canReply.value) return
  openPublicFoundReplyMailto(props.message)
}
</script>

<style scoped>
.nc-qr-detail__material {
  margin: 0 0 12px;
  font-size: 0.9rem;
  color: #374151;
}

.nc-qr-detail__email {
  margin: 12px 0 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.nc-qr-detail__hint {
  margin: 14px 0 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.nc-qr-detail__task-panel {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.nc-qr-detail__task-label {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.nc-qr-detail__reply-hint {
  margin: 0;
  font-size: 0.8rem;
  color: #6b7280;
  line-height: 1.45;
}
</style>
