<template>
  <EDialog
    v-model="open"
    :max-width="420"
    :title="t('workshop.repairPartsList.quickCreateTitle')"
  >
    <p class="quick-create-hint">{{ t('workshop.repairPartsList.quickCreateHint') }}</p>
    <ETextField
      v-model="form.name"
      :label="t('workshop.repairPartsList.quickCreateName')"
      hide-details="auto"
      class="mb-3"
    />
    <ESelect
      v-model="form.packUnit"
      class="mb-3"
      :items="packUnitItems"
      :label="t('workshop.repairPartsList.quickCreateUnit')"
      hide-details="auto"
    />
    <ETextField
      v-if="form.packUnit === 'm'"
      v-model="form.lengthMeters"
      type="number"
      min="0.01"
      step="any"
      class="mb-3"
      :label="t('components.materialDetail.stockUnitLengthDialogLabel')"
      :placeholder="t('components.materialDetail.stockUnitLengthDialogPlaceholder')"
      :hint="t('components.materialDetail.stockUnitLengthDialogHint')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.initialQty"
      type="number"
      min="0.01"
      step="any"
      class="mb-3"
      :label="initialQtyLabel"
      :placeholder="initialQtyPlaceholder"
      :hint="initialQtyHint"
      hide-details="auto"
    />
    <ETextField
      v-model="form.unitCost"
      type="number"
      min="0"
      step="0.05"
      :label="unitCostLabel"
      :hint="t('workshop.repairPartsList.quickCreateUnitCostHint')"
      hide-details="auto"
    />
    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :loading="isSaving"
        :disabled="!canSubmit || isSaving"
        @click="submit"
      >
        {{ t('workshop.repairPartsList.quickCreateSubmit') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { createMaterial, type Material } from '@/api/materials'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { applyMaterialUnitSuffixToName, parseSizeLengthCm } from '@/utils/materialStockUnit'

const props = defineProps<{
  modelValue: boolean
  departmentId: string
  categoryId: string
  initialName?: string
}>()

export interface RepairPartQuickCreateResult {
  material: Material
  availableQty: number | null
  packUnit: string
}

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  created: [result: RepairPartQuickCreateResult]
}>()

const { t } = useI18n()
const toast = useToast()

const isSaving = ref(false)
const form = ref({
  name: '',
  unitCost: '',
  packUnit: 'Stk',
  initialQty: '',
  lengthMeters: '',
})

const packUnitItems = computed(() => [
  { value: 'Stk', title: t('workshop.repairPartsList.unitStk') },
  { value: 'm', title: t('workshop.repairPartsList.unitMeter') },
])

const unitLabel = computed(() => {
  const unit = form.value.packUnit || 'Stk'
  if (unit === 'Stk') return t('workshop.repairPartsList.unitStkShort')
  return unit
})

const parsedLengthMeters = computed((): number | null => {
  if (form.value.packUnit !== 'm') return null
  const raw = form.value.lengthMeters.trim()
  if (!raw) return null
  const n = Number(raw.replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  return n
})

const useMeterQtyByCount = computed(
  () => form.value.packUnit === 'm' && parsedLengthMeters.value != null,
)

const initialQtyLabel = computed(() => {
  if (useMeterQtyByCount.value) return t('components.batchModal.quantityCountLabel')
  return t('workshop.repairPartsList.quickCreateInitialQty', { unit: unitLabel.value })
})

const initialQtyPlaceholder = computed(() => {
  if (useMeterQtyByCount.value) return t('components.batchModal.qtyPlaceholderCount')
  return t('workshop.repairPartsList.quickCreateInitialQtyPlaceholder')
})

const initialQtyHint = computed(() => {
  const base = t('workshop.repairPartsList.quickCreateInitialQtyHint')
  const per = parsedLengthMeters.value
  if (useMeterQtyByCount.value && per) {
    const raw = form.value.initialQty.trim()
    const count = Number(raw.replace(',', '.'))
    if (Number.isFinite(count) && count > 0) {
      return t('components.batchModal.meterQtyTotalHint', {
        count: Math.round(count),
        per,
        total: Math.round(count * per),
      })
    }
  }
  return base
})

const unitCostLabel = computed(() =>
  t('workshop.repairPartsList.quickCreateUnitCostPer', {
    unit: form.value.packUnit === 'm' ? 'm' : unitLabel.value,
  }),
)

const parsedInitialQty = computed((): number | null => {
  const raw = form.value.initialQty.trim()
  if (!raw) return null
  const n = Number(raw.replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  if (useMeterQtyByCount.value && parsedLengthMeters.value) {
    return Math.round(n * parsedLengthMeters.value)
  }
  return n
})

const canSubmit = computed(() => {
  if (form.value.name.trim().length === 0) return false
  if (parsedInitialQty.value == null) return false
  if (form.value.packUnit === 'm' && parsedLengthMeters.value == null) return false
  return true
})

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

watch(
  () => form.value.packUnit,
  (unit) => {
    if (unit !== 'm') form.value.lengthMeters = ''
  },
)

watch(
  () => [props.modelValue, props.initialName] as const,
  ([visible, name]) => {
    if (visible) {
      form.value = {
        name: name || '',
        unitCost: '',
        packUnit: 'Stk',
        initialQty: '',
        lengthMeters: '',
      }
    }
  },
)

async function submit() {
  if (!props.departmentId || !props.categoryId || !canSubmit.value) return

  isSaving.value = true
  try {
    const packUnit = form.value.packUnit || 'Stk'
    const sizeLengthCm =
      packUnit === 'm' && parsedLengthMeters.value != null
        ? String(Math.round(parsedLengthMeters.value * 100))
        : null
    const material = await createMaterial({
      department_id: props.departmentId,
      name: applyMaterialUnitSuffixToName(
        form.value.name.trim(),
        packUnit,
        null,
        parseSizeLengthCm(sizeLengthCm),
      ),
      category_id: props.categoryId,
      tracking_type: 'bulk',
      reference_purchase_unit_chf: form.value.unitCost.trim() || null,
      pack_unit: packUnit,
      size_length: sizeLengthCm,
    })
    const initialQty = parsedInitialQty.value
    if (initialQty == null) return

    emit('created', {
      material,
      availableQty: initialQty,
      packUnit,
    })
    open.value = false
    toast.success(t('workshop.repairPartsList.quickCreateSuccess'))
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('workshop.repairPartsList.quickCreateError'))
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.quick-create-hint {
  margin: 0 0 12px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
}
</style>
