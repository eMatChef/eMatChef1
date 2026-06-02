<template>
  <EDialog v-model="open" :max-width="560" :title="title">
    <div class="nc-message-detail">
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
    <template #actions>
      <template v-if="!showTask">
        <EButton variant="primary" size="small" @click="onProceed">
          {{ t('notificationsCenter.proceedToTask') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="close">
          {{ t('notificationsCenter.messageDetailClose') }}
        </EButton>
      </template>
      <template v-else>
        <slot name="task-actions" />
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
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import type { NotificationSenderDescriptor } from '@/utils/notificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'
import { EButton, EDialog } from '@/components/form/base'
import '@/styles/components/inbox-modal.css'

const props = withDefaults(
  defineProps<{
    visible: boolean
    title: string
    subject: string
    preview: string
    sender: NotificationSenderDescriptor | null
    createdAt?: string
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

const open = computed({
  get: () => props.visible,
  set: (value: boolean) => {
    if (!value) emit('close')
  },
})

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
  (isOpen) => {
    if (!isOpen) showTask.value = false
  },
)

function close() {
  emit('close')
}

function onProceed() {
  if (props.navigateOnProceed) {
    emit('open-task')
    return
  }
  showTask.value = true
}
</script>

<style scoped>
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
</style>
