<template>
  <span
    class="notification-sender-block"
    :class="[`notification-sender-block--${size}`, `notification-sender-block--${sender.kind}`]"
    :title="tooltipText"
  >
    <UserAvatarBadge
      v-if="sender.kind === 'user' || sender.kind === 'department'"
      :user="sender.user!"
      :size="size"
      :show-tooltip="showTooltip && sender.kind === 'user'"
    />
    <span
      v-else
      class="notification-sender-block__icon"
      :class="iconClass"
      aria-hidden="true"
    >
      <component :is="iconComponent" />
    </span>
    <UserAvatarBadge
      v-if="sender.kind === 'system' && sender.user"
      :user="sender.user"
      :size="actorSize"
      class="notification-sender-block__actor"
      :show-tooltip="false"
    />
  </span>
</template>

<script setup lang="ts">
import { computed, defineComponent, h } from 'vue'
import { UserAvatarBadge } from '@/components/user'
import type { NotificationSenderDescriptor } from '@/utils/notificationSender'
const props = withDefaults(
  defineProps<{
    sender: NotificationSenderDescriptor
    size?: 'sm' | 'md' | 'lg'
    showTooltip?: boolean
  }>(),
  {
    size: 'sm',
    showTooltip: false,
  },
)

const actorSize = computed(() => (props.size === 'lg' ? 'sm' : 'sm'))

const iconClass = computed(() => {
  if (props.sender.kind === 'system') {
    return `notification-sender-block__icon--system notification-sender-block__icon--${props.sender.systemVariant ?? 'default'}`
  }
  if (props.sender.kind === 'task') {
    return `notification-sender-block__icon--task notification-sender-block__icon--${props.sender.taskVariant ?? 'qr_contact'}`
  }
  return ''
})

const tooltipText = computed(() => {
  if (!props.showTooltip) return undefined
  const sub = String(props.sender.sublabel ?? '').trim()
  if (sub) return `${props.sender.label} — ${sub}`
  return props.sender.label
})

const SystemIcon = defineComponent({
  render: () =>
    h(
      'svg',
      { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2' },
      [
        h('path', {
          d: 'M12 2L2 7l10 5 10-5-10-5z',
          strokeLinecap: 'round',
          strokeLinejoin: 'round',
        }),
        h('path', {
          d: 'M2 17l10 5 10-5M2 12l10 5 10-5',
          strokeLinecap: 'round',
          strokeLinejoin: 'round',
        }),
      ],
    ),
})

const TaskQrIcon = defineComponent({
  render: () =>
    h(
      'svg',
      { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2' },
      [
        h('path', {
          d: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2',
          strokeLinecap: 'round',
        }),
        h('rect', { x: '9', y: '3', width: '6', height: '4', rx: '1' }),
        h('path', { d: 'M9 12h6M9 16h4', strokeLinecap: 'round' }),
      ],
    ),
})

const iconComponent = computed(() => {
  if (props.sender.kind === 'task') return TaskQrIcon
  return SystemIcon
})
</script>

<style src="@/styles/components/notification-sender-block.css"></style>
