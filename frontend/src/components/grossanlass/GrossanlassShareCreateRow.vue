<template>
  <div class="share-create-row" @click.stop>
    <ETextField
      :model-value="model"
      :label="t('grossanlass.planung.ressorts.nameLabelUnterressort')"
      :placeholder="t('grossanlass.planung.ressorts.namePlaceholderUnterressort')"
      hide-details
      autofocus
      @update:model-value="model = String($event ?? '')"
      @keydown.enter.prevent="emit('submit')"
    />
    <EButton variant="secondary" size="small" :disabled="saving" @click="emit('cancel')">
      {{ t('common.cancel') }}
    </EButton>
    <EButton
      variant="primary"
      size="small"
      :disabled="!model.trim() || saving"
      :loading="saving"
      @click="emit('submit')"
    >
      {{ t('grossanlass.planung.ressorts.shareCreateSubmit') }}
    </EButton>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'

defineOptions({ name: 'GrossanlassShareCreateRow' })

const model = defineModel<string>({ default: '' })
defineProps<{
  saving?: boolean
}>()
const emit = defineEmits<{
  submit: []
  cancel: []
}>()

const { t } = useI18n()
</script>

<style scoped>
.share-create-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px;
  margin: 8px 0 10px;
  padding: 8px 10px;
  border: 1px dashed #99f6e4;
  border-radius: 8px;
  background: #f0fdfa;
}

.share-create-row :deep(.e-form-field) {
  flex: 1 1 180px;
  min-width: 0;
  margin-bottom: 0;
}
</style>
