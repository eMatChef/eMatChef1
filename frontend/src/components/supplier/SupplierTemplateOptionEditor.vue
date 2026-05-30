<template>
  <div class="option-editor">
    <div class="field-row">
      <label class="field">
        <span>{{ t('supplierTemplates.fields.optionName') }}</span>
        <input v-model.trim="option.name" type="text" required maxlength="120" />
      </label>
      <label v-if="!forceDisplayMode" class="field">
        <span>{{ t('supplierTemplates.fields.displayMode') }}</span>
        <select v-model="option.display_mode">
          <option value="toggle">{{ t('supplierTemplates.displayMode.toggle') }}</option>
          <option value="group">{{ t('supplierTemplates.displayMode.group') }}</option>
        </select>
      </label>
    </div>
    <label class="checkbox-field">
      <input v-model="option.default_selected" type="checkbox" />
      <span>{{ t('supplierTemplates.fields.defaultSelected') }}</span>
    </label>

    <div class="deltas-section">
      <div class="section-header">
        <strong>{{ t('supplierTemplates.deltasTitle') }}</strong>
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('add-delta')">
          {{ t('supplierTemplates.addDelta') }}
        </button>
      </div>
      <div v-for="(delta, index) in option.deltas" :key="index" class="delta-row">
        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierTemplates.fields.componentType') }}</span>
            <input v-model.trim="delta.component_type" type="text" maxlength="60" />
          </label>
          <label class="field">
            <span>{{ t('supplierTemplates.fields.componentName') }}</span>
            <input v-model.trim="delta.name" type="text" maxlength="160" />
          </label>
          <label class="field field-narrow">
            <span>{{ t('supplierTemplates.fields.qtyDelta') }}</span>
            <input v-model.number="delta.qty_delta" type="number" step="1" />
          </label>
        </div>
        <button type="button" class="btn btn-danger btn-sm" @click="emit('remove-delta', index)">
          {{ t('supplierTemplates.removeDelta') }}
        </button>
      </div>
    </div>

    <button type="button" class="btn btn-danger btn-sm" @click="emit('remove')">
      {{ t('supplierTemplates.removeOption') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SupplierTemplateOption } from '@/api/supplierMaterialTemplates'

defineProps<{
  option: SupplierTemplateOption
  forceDisplayMode?: 'group'
}>()

const emit = defineEmits<{
  remove: []
  'add-delta': []
  'remove-delta': [index: number]
}>()

const { t } = useI18n()
</script>

<style scoped>
.option-editor {
  margin-bottom: 8px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 8px;
  flex: 1;
}

.field-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.field-narrow {
  max-width: 100px;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.deltas-section {
  margin: 8px 0 12px;
  padding: 8px;
  border: 1px dashed #d1d5db;
  border-radius: 6px;
}

.delta-row {
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.delta-row:last-child {
  border-bottom: none;
}
</style>
