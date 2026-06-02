<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'

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
    <EButton
      v-if="showCancel"
      variant="secondary"
      size="small"
      :disabled="cancelDisabled"
      @click="$emit('cancel')"
    >
      {{ t('common.cancel') }}
    </EButton>
    <EButton
      variant="primary"
      size="small"
      :disabled="primaryDisabled"
      @click="$emit('primary')"
    >
      {{ primaryLabel }}
    </EButton>
  </div>
</template>
