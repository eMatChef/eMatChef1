<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  itemClass?: string | Record<string, boolean> | Array<string | Record<string, boolean>>
}>()

defineEmits<{
  open: []
  dismiss: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="notification-item-wrap">
    <button type="button" class="notification-item" :class="itemClass" @click="$emit('open')">
      <slot />
    </button>
    <button
      type="button"
      class="notification-item__dismiss"
      :aria-label="t('layout.notifications.dismissAria')"
      :title="t('layout.notifications.dismissAria')"
      @click.stop="$emit('dismiss')"
    >
      ×
    </button>
  </div>
</template>

<style scoped>
.notification-item-wrap {
  position: relative;
  border-bottom: 1px solid #f3f4f6;
}

.notification-item-wrap :deep(.notification-item) {
  border-bottom: none;
  padding-right: 28px;
}

.notification-item__dismiss {
  position: absolute;
  top: 6px;
  right: 6px;
  z-index: 1;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: #9ca3af;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notification-item__dismiss:hover {
  background: #f3f4f6;
  color: #374151;
}
</style>
