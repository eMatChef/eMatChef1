<template>
  <EDialog
    v-model="open"
    :title="t('workshop.repairSheet.previewTitle', { name: template?.name ?? '' })"
    max-width="860"
    scrollable
  >
    <div v-if="template" class="preview-toolbar">
      <ESelect
        v-model="previewMode"
        :items="modeItems"
        :label="t('workshop.repairSheet.previewMode')"
        hide-details
        density="compact"
        class="mode-select"
      />
    </div>

    <RepairSheetEditor
      v-if="template && sheetTemplate"
      v-model="previewChecklist"
      :template="sheetTemplate"
      :mode="previewMode"
      price-source="department"
    />

    <details class="json-debug">
      <summary>{{ t('workshop.repairSheet.previewJson') }}</summary>
      <pre>{{ jsonPreview }}</pre>
    </details>

    <template #actions>
      <EButton variant="secondary" @click="open = false">
        {{ t('common.close') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DepartmentRepairTemplate } from '@/api/repairTemplates'
import RepairSheetEditor from '@/components/workshop/RepairSheetEditor.vue'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import type { RepairChecklist, RepairSheetEditorMode } from '@/types/repairChecklist'
import {
  createEmptyRepairChecklist,
  departmentTemplateToSheetInput,
} from '@/types/repairChecklist'

const open = defineModel<boolean>({ default: false })
const template = defineModel<DepartmentRepairTemplate | null>('template', { default: null })

const { t } = useI18n()

const previewMode = ref<RepairSheetEditorMode>('edit')
const previewChecklist = ref<RepairChecklist>(createEmptyRepairChecklist())

const modeItems = computed(() => [
  { title: t('workshop.repairSheet.modes.edit'), value: 'edit' },
  { title: t('workshop.repairSheet.modes.readonly'), value: 'readonly' },
  { title: t('workshop.repairSheet.modes.supplier'), value: 'supplier' },
])

const sheetTemplate = computed(() =>
  template.value ? departmentTemplateToSheetInput(template.value) : null
)

const jsonPreview = computed(() => JSON.stringify(previewChecklist.value, null, 2))

watch(
  () => template.value,
  (value) => {
    previewChecklist.value = createEmptyRepairChecklist(value?.template_key)
  }
)
</script>

<style scoped>
.preview-toolbar {
  margin-bottom: 12px;
}

.mode-select {
  max-width: 240px;
}

.json-debug {
  margin-top: 16px;
  font-size: 12px;
}

.json-debug pre {
  margin: 8px 0 0;
  padding: 10px;
  background: #111827;
  color: #e5e7eb;
  border-radius: 8px;
  overflow: auto;
  max-height: 200px;
}
</style>
