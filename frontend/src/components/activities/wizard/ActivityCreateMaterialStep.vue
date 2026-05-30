<template>
  <div class="activity-create-material-step">
    <ActivityMaterialAvailabilityLookup
      :department-id="departmentId"
      :activity-id="activityId ?? ''"
      :activity-type="activityType"
      :planning-start-iso="planningStartIso"
      :planning-end-iso="planningEndIso"
      :quantity-by-material-item-id="quantityByMaterialItemIdFromLines"
      :invited-departments="invitedDepartmentsForLookup"
      :search-reset-key="props.materialSearchResetKey"
      hint-variant="wizard"
      @add-quantity="onAvailabilityAddQuantity"
      @scope-change="onMaterialLookupScopeChange"
    />

    <ActivityMaterialLinesTable
      class="activity-mat-lines-table-wizard"
      :model-value="modelValue"
      :department-id="departmentId"
      :activity-id="activityId"
      :planning-start-at="planningStartAt"
      :planning-end-at="planningEndAt"
      :material-scope-tab="materialLookupScopeTab"
      :material-scope-has-partners="hasAcceptedPartnerTabs"
      :material-scope-single-partner-id="materialLookupSinglePartnerId"
      variant="wizard"
      :empty-text="t('activities.wizard.materialEmptyLines')"
      @update:model-value="emit('update:modelValue', $event)"
      @remove-line="onRemoveLine"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityApiType } from '@/api/activities'
import ActivityMaterialAvailabilityLookup from '@/components/activities/ActivityMaterialAvailabilityLookup.vue'
import ActivityMaterialLinesTable from '@/components/activities/shared/ActivityMaterialLinesTable.vue'
import type { ActivityPeriodAvailabilityMaterial } from '@/components/activities/shared/activityAvailabilityMaterial'
import type { ActivityCreateType, ActivityMaterialLine } from '@/composables/useActivityCreateWizard'
import type { MaterialScopeTab } from '@/components/activities/shared/activityMaterialAvailabilityScope'

/** Nur angenommene Einladungen — Tab „Partner“ und API single/both */
interface InvitedPartnerDepartment {
  id: string
  name: string
}

const props = withDefaults(
  defineProps<{
    departmentId: string
    activityType: ActivityCreateType
    activityId?: string | null
    invitedPartnerDepartments?: InvitedPartnerDepartment[]
    planningStartAt?: Date | null
    planningEndAt?: Date | null
    modelValue: ActivityMaterialLine[]
    /** Erhöhen, um die Materialsuche zurückzusetzen (Wizard: Schritt Material) */
    materialSearchResetKey?: number
  }>(),
  {
    activityId: null,
    invitedPartnerDepartments: () => [],
    planningStartAt: null,
    planningEndAt: null,
    materialSearchResetKey: 0,
  },
)

const { t } = useI18n()

const emit = defineEmits<{
  'update:modelValue': [value: ActivityMaterialLine[]]
}>()

const materialLookupScopeTab = ref<MaterialScopeTab>('own')
const materialLookupSinglePartnerId = ref<string | null>(null)

function onMaterialLookupScopeChange(payload: {
  tab: MaterialScopeTab
  singlePartnerDepartmentId: string | null
}) {
  materialLookupScopeTab.value = payload.tab
  materialLookupSinglePartnerId.value = payload.singlePartnerDepartmentId
}

/** Einladungen mit Status accepted (wie in der Lookup-Komponente) */
const hasAcceptedPartnerTabs = computed(
  () => (props.invitedPartnerDepartments ?? []).length > 0,
)

const activityType = computed((): ActivityApiType => props.activityType as ActivityApiType)

const planningStartIso = computed(() => props.planningStartAt?.toISOString() ?? null)
const planningEndIso = computed(() => props.planningEndAt?.toISOString() ?? null)

const quantityByMaterialItemIdFromLines = computed(() => {
  const m: Record<string, number> = {}
  for (const row of props.modelValue) {
    m[row.material_item_id] = (m[row.material_item_id] ?? 0) + row.quantity
  }
  return m
})

const invitedDepartmentsForLookup = computed(() =>
  (props.invitedPartnerDepartments ?? []).map((d) => ({
    id: d.id,
    name: d.name,
    status: 'accepted' as const,
  })),
)

function onRemoveLine({ index }: { line: ActivityMaterialLine; index: number }) {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, i) => i !== index),
  )
}

function onAvailabilityAddQuantity(payload: { material: ActivityPeriodAvailabilityMaterial; quantity: number; selectedOptionIds?: string[] }) {
  addQty(payload.material, payload.quantity, payload.selectedOptionIds)
}

function effectiveStock(m: ActivityPeriodAvailabilityMaterial): number {
  return typeof m.availableForPeriod === 'number' ? m.availableForPeriod : 0
}

function addQty(m: ActivityPeriodAvailabilityMaterial, qty: number, selectedOptionIds?: string[]) {
  const raw = effectiveStock(m)
  let draftSum = 0
  let savedSum = 0
  for (const l of props.modelValue) {
    if (l.material_item_id !== m.materialItemId) continue
    draftSum += l.quantity
    if (typeof l.saved_quantity === 'number') {
      savedSum += l.saved_quantity
    }
  }
  /** Freie Menge laut API, korrigiert wenn Entwurf ≠ gespeichert (Detail) */
  const adjustedFree = Math.max(0, raw + savedSum - draftSum)
  const add = Math.min(qty, adjustedFree)
  if (add < 1) return

  const lines = [...props.modelValue]
  const i = lines.findIndex((l) => l.material_item_id === m.materialItemId)
  if (i >= 0) {
    lines[i] = {
      ...lines[i],
      quantity: lines[i].quantity + add,
      period_availability_cap: lines[i].period_availability_cap ?? raw,
      pack_size: lines[i].pack_size ?? m.packSize ?? undefined,
      pack_unit: lines[i].pack_unit ?? m.packUnit ?? undefined,
      // Konfigurator: zuletzt gewählte Konfiguration übernehmen (eine Eltern-Zeile je Kombo).
      ...(selectedOptionIds ? { material_type: m.materialType ?? undefined, selected_option_ids: selectedOptionIds } : {}),
    }
  } else {
    lines.push({
      material_item_id: m.materialItemId,
      material_name: m.name,
      material_type: m.materialType ?? undefined,
      quantity: add,
      period_availability_cap: raw,
      pack_size: m.packSize ?? undefined,
      pack_unit: m.packUnit ?? undefined,
      ...(selectedOptionIds ? { selected_option_ids: selectedOptionIds } : {}),
    })
  }
  emit('update:modelValue', lines)
}
</script>

<style scoped>
.activity-mat-lines-table-wizard {
  margin-top: 12px;
}
</style>
