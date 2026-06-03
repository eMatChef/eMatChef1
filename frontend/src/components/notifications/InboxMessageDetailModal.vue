<template>
  <EDialog
    v-model="open"
    :max-width="560"
    :title="t('notificationsCenter.messageDetailTitle')"
  >
    <div v-if="message" class="nc-message-detail">
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
    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('notificationsCenter.messageDetailClose') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { UserDirectMessage, UserDirectMessageSent } from '@/api/inboxMessages'
import NotificationSenderBlock from './NotificationSenderBlock.vue'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'
import { EButton, EDialog } from '@/components/form/base'
import '@/styles/components/inbox-modal.css'

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

const open = computed({
  get: () => props.message != null,
  set: (value: boolean) => {
    if (!value) emit('close')
  },
})

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

function close() {
  emit('close')
}
</script>
