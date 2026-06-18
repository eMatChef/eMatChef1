<template>
  <div v-if="visible" class="repair-sheet-panel">
    <div class="panel-header">
      <div>
        <div class="modal-section-title">{{ t('workshop.repairSheetPanel.title') }}</div>
        <p v-if="isReadonly && isExternalStrategy" class="panel-hint">
          {{ t('workshop.repairSheetPanel.externalReadonlyHint') }}
        </p>
      </div>
      <EButton
        v-if="!isReadonly"
        variant="primary"
        size="small"
        :loading="isSaving"
        :disabled="!hasChanges || isSaving"
        @click="saveChecklist"
      >
        {{ isSaving ? t('common.saving') : t('common.save') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('workshop.repairSheetPanel.loading')"
    />

    <RepairSheetEditor
      v-else-if="sheetTemplate"
      v-model="checklist"
      :template="sheetTemplate"
      :mode="editorMode"
      :price-source="priceSource"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { updateWorkshopTicket, type WorkshopTicket } from '@/api/workshop'
import { getDepartmentRepairTemplates } from '@/api/repairTemplates'
import {
  listDepartmentSupplierRepairTemplates,
  supplierTemplateToSheetInput,
} from '@/api/supplierRepairTemplates'
import RepairSheetEditor from '@/components/workshop/RepairSheetEditor.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'
import { ticketHasRepairSheet } from '@/composables/useWorkshopTriageOptions'
import {
  createEmptyRepairChecklist,
  departmentTemplateToSheetInput,
  normalizeRepairChecklist,
  type RepairChecklist,
  type RepairSheetEditorMode,
  type RepairSheetPriceSource,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'

const props = defineProps<{
  ticket: WorkshopTicket
  departmentId: string
}>()

const emit = defineEmits<{
  updated: [ticket: WorkshopTicket]
}>()

const { t } = useI18n()
const toast = useToast()

const isLoading = ref(false)
const isSaving = ref(false)
const sheetTemplate = ref<RepairSheetTemplateInput | null>(null)
const checklist = ref<RepairChecklist>(createEmptyRepairChecklist())
const savedChecklistJson = ref('')

const visible = computed(() => {
  if (!ticketHasRepairSheet(props.ticket)) return false
  if (props.ticket.strategy === 'writeoff' || props.ticket.strategy === 'triage') return false
  if (props.ticket.strategy === 'external_cleaning') return false
  return ['internal_repair', 'external_repair'].includes(props.ticket.strategy)
})

const isExternalStrategy = computed(() =>
  ['external_repair', 'external_cleaning'].includes(props.ticket.strategy)
)

const isReadonly = computed(
  () =>
    isExternalStrategy.value ||
    props.ticket.status === 'completed' ||
    props.ticket.status === 'cancelled'
)

const editorMode = computed((): RepairSheetEditorMode =>
  isReadonly.value ? 'readonly' : 'edit'
)

const priceSource = computed((): RepairSheetPriceSource =>
  isExternalStrategy.value ? 'supplier' : 'department'
)

const hasChanges = computed(
  () => JSON.stringify(checklist.value) !== savedChecklistJson.value
)

watch(
  () => [
    props.ticket.id,
    props.ticket.strategy,
    props.ticket.repair_checklist,
    props.ticket.assigned_to_supplier_company?.id,
  ],
  () => {
    void loadTemplate()
  },
  { immediate: true, deep: true }
)

async function loadTemplate() {
  if (!visible.value || !props.departmentId) {
    sheetTemplate.value = null
    return
  }

  const templateKey = props.ticket.material_item.repair_template_key
  if (!templateKey) return

  isLoading.value = true
  try {
    let match = null as Awaited<ReturnType<typeof getDepartmentRepairTemplates>>[number] | null

    if (isExternalStrategy.value && props.ticket.assigned_to_supplier_company?.id) {
      const supplierTemplates = await listDepartmentSupplierRepairTemplates(
        props.departmentId,
        props.ticket.assigned_to_supplier_company.id,
      )
      const supplierMatch = supplierTemplates.find((tpl) => tpl.template_key === templateKey)
      if (supplierMatch) {
        sheetTemplate.value = supplierTemplateToSheetInput(supplierMatch)
        checklist.value = normalizeRepairChecklist(props.ticket.repair_checklist, sheetTemplate.value)
        savedChecklistJson.value = JSON.stringify(checklist.value)
        return
      }
    }

    const templates = await getDepartmentRepairTemplates(props.departmentId)
    match = templates.find((tpl) => tpl.template_key === templateKey) ?? null
    if (!match) {
      sheetTemplate.value = null
      return
    }
    sheetTemplate.value = departmentTemplateToSheetInput(match)
    checklist.value = normalizeRepairChecklist(props.ticket.repair_checklist, sheetTemplate.value)
    savedChecklistJson.value = JSON.stringify(checklist.value)
  } catch (err) {
    console.error('Failed to load repair sheet template:', err)
    sheetTemplate.value = null
  } finally {
    isLoading.value = false
  }
}

async function saveChecklist() {
  if (!props.ticket.id || isReadonly.value) return
  isSaving.value = true
  try {
    const updated = await updateWorkshopTicket(props.ticket.id, {
      repair_checklist: checklist.value,
    })
    savedChecklistJson.value = JSON.stringify(checklist.value)
    emit('updated', updated)
    toast.success(t('workshop.repairSheetPanel.toastSaved'))
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('workshop.repairSheetPanel.toastSaveError'))
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.repair-sheet-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.panel-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
