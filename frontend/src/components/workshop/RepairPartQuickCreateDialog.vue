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
      v-if="form.packUnit === 'Stk'"
      v-model="form.contentPerUnit"
      type="number"
      min="1"
      step="1"
      class="mb-3"
      :label="t('workshop.repairPartsList.quickCreateContentPerUnit', { unit: 'm' })"
      :placeholder="t('workshop.repairPartsList.quickCreateContentPerUnitPlaceholder')"
      :hint="t('workshop.repairPartsList.quickCreateContentPerUnitHint')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.initialQty"
      type="number"
      min="0.01"
      step="any"
      class="mb-3"
      :label="initialQtyLabel"
      :placeholder="t('workshop.repairPartsList.quickCreateInitialQtyPlaceholder')"
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
  contentPerUnit: '',
})

const packUnitItems = computed(() => [
  { value: 'Stk', title: t('workshop.repairPartsList.unitStk') },
  { value: 'm', title: t('workshop.repairPartsList.unitMeter') },
  { value: 'm²', title: t('workshop.repairPartsList.unitSqm') },
])

const unitLabel = computed(() => {
  const unit = form.value.packUnit || 'Stk'
  if (unit === 'Stk') return t('workshop.repairPartsList.unitStkShort')
  return unit
})

const initialQtyLabel = computed(() =>
  t('workshop.repairPartsList.quickCreateInitialQty', { unit: unitLabel.value }),
)

const initialQtyHint = computed(() => {
  const base = t('workshop.repairPartsList.quickCreateInitialQtyHint')
  const content = parsedContentPerUnit.value
  if (form.value.packUnit === 'Stk' && content) {
    return `${base} ${t('workshop.repairPartsList.quickCreateContentPerUnitHint')}`
  }
  return base
})

const unitCostLabel = computed(() =>
  t('workshop.repairPartsList.quickCreateUnitCostPer', { unit: unitLabel.value }),
)

const parsedInitialQty = computed((): number | null => {
  const raw = form.value.initialQty.trim()
  if (!raw) return null
  const n = Number(raw)
  if (!Number.isFinite(n) || n <= 0) return null
  return n
})

const parsedContentPerUnit = computed((): number | null => {
  if (form.value.packUnit !== 'Stk') return null
  const raw = form.value.contentPerUnit.trim()
  if (!raw) return null
  const n = Number(raw)
  if (!Number.isFinite(n) || n < 2) return null
  return Math.round(n)
})

const canSubmit = computed(
  () => form.value.name.trim().length > 0 && parsedInitialQty.value != null,
)

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

watch(
  () => [props.modelValue, props.initialName] as const,
  ([visible, name]) => {
    if (visible) {
      form.value = {
        name: name || '',
        unitCost: '',
        packUnit: 'Stk',
        initialQty: '',
        contentPerUnit: '',
      }
    }
  },
)

async function submit() {
  if (!props.departmentId || !props.categoryId || !canSubmit.value) return

  isSaving.value = true
  try {
    const material = await createMaterial({
      department_id: props.departmentId,
      name: form.value.name.trim(),
      category_id: props.categoryId,
      tracking_type: 'bulk',
      reference_purchase_unit_chf: form.value.unitCost.trim() || null,
      pack_unit: form.value.packUnit || 'Stk',
      pack_size: parsedContentPerUnit.value,
    })
    const initialQty = parsedInitialQty.value
    if (initialQty == null) return

    emit('created', {
      material,
      availableQty: initialQty,
      packUnit: form.value.packUnit || 'Stk',
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
