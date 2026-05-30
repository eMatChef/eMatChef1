<script setup lang="ts">
import { useI18n } from 'vue-i18n'

withDefaults(
  defineProps<{
    cancelDisabled?: boolean
    primaryDisabled?: boolean
    primaryLabel: string
    showCancel?: boolean
  }>(),
  {
    cancelDisabled: false,
    primaryDisabled: false,
    showCancel: true,
  },
)

defineEmits<{
  cancel: []
  primary: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="pack-modal-actions">
    <slot name="before" />
    <button
      v-if="showCancel"
      type="button"
      class="btn-outline btn-sm"
      :disabled="cancelDisabled"
      @click="$emit('cancel')"
    >
      {{ t('common.cancel') }}
    </button>
    <button
      type="button"
      class="btn-primary btn-sm"
      :disabled="primaryDisabled"
      @click="$emit('primary')"
    >
      {{ primaryLabel }}
    </button>
  </div>
</template>
