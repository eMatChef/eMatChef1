<template>
  <button
    type="button"
    class="password-reveal-toggle"
    :aria-label="visible ? t('login.hidePassword') : t('login.showPassword')"
    :aria-pressed="visible"
    @click.stop.prevent="emit('toggle')"
  >
    <v-icon :icon="visible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'" size="20" />
  </button>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  visible: boolean
}>()

const emit = defineEmits<{
  toggle: []
}>()

const { t } = useI18n()
</script>

<style scoped>
.password-reveal-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  background-color: transparent;
  box-shadow: none;
  color: #6b7280;
  cursor: pointer;
  line-height: 0;
}

.password-reveal-toggle :deep(.v-icon) {
  background: transparent !important;
  background-color: transparent !important;
}

.password-reveal-toggle:hover,
.password-reveal-toggle:focus-visible {
  color: #111827;
}

.password-reveal-toggle:focus-visible {
  outline: 2px solid var(--color-primary, #059669);
  outline-offset: 2px;
  border-radius: 4px;
}
</style>
