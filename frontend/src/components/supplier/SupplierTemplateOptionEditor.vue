<template>
  <div class="option-editor">
    <div class="field-row">
      <ETextField
        v-model="option.name"
        :label="t('supplierTemplates.fields.optionName')"
        maxlength="120"
        hide-details="auto"
        class="field-grow"
      />
      <ESelect
        v-if="!forceDisplayMode"
        v-model="option.display_mode"
        :items="displayModeItems"
        :label="t('supplierTemplates.fields.displayMode')"
        hide-details="auto"
        class="field-grow"
      />
    </div>
    <ECheckbox
      v-model="option.default_selected"
      :label="t('supplierTemplates.fields.defaultSelected')"
      hide-details
      class="mb-2"
    />

    <div class="deltas-section">
      <div class="section-header">
        <strong>{{ t('supplierTemplates.deltasTitle') }}</strong>
        <EButton variant="secondary" size="small" @click="emit('add-delta')">
          {{ t('supplierTemplates.addDelta') }}
        </EButton>
      </div>
      <div v-for="(delta, index) in option.deltas" :key="index" class="delta-row">
        <div class="field-row">
          <ETextField
            v-model="delta.component_type"
            :label="t('supplierTemplates.fields.componentType')"
            maxlength="60"
            hide-details="auto"
            class="field-grow"
          />
          <ETextField
            v-model="delta.name"
            :label="t('supplierTemplates.fields.componentName')"
            maxlength="160"
            hide-details="auto"
            class="field-grow"
          />
          <ETextField
            v-model.number="delta.qty_delta"
            type="number"
            :label="t('supplierTemplates.fields.qtyDelta')"
            hide-details="auto"
            class="field-narrow"
          />
        </div>
        <EButton variant="danger" size="small" @click="emit('remove-delta', index)">
          {{ t('supplierTemplates.removeDelta') }}
        </EButton>
      </div>
    </div>

    <EButton variant="danger" size="small" @click="emit('remove')">
      {{ t('supplierTemplates.removeOption') }}
    </EButton>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SupplierTemplateOption } from '@/api/supplierMaterialTemplates'
import { EButton, ECheckbox, ESelect, ETextField } from '@/components/form/base'

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

const displayModeItems = computed(() => [
  { title: t('supplierTemplates.displayMode.toggle'), value: 'toggle' as const },
  { title: t('supplierTemplates.displayMode.group'), value: 'group' as const },
])
</script>

<style scoped>
.option-editor {
  margin-bottom: 8px;
}

.field-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.field-grow {
  flex: 1 1 140px;
}

.field-narrow {
  flex: 0 1 100px;
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
