<template>
  <AutoSaveFieldShell
    :input-id="inputId"
    :label="t('components.materialDetail.sectionStockUnit')"
    :show-label="showLabel"
    :status="autoSave.status.value"
    :is-saving="autoSave.isSaving.value"
    :is-pending="autoSave.isPreSaving.value"
    :show-success-icon="autoSave.showSuccessIcon.value"
    :is-focused="autoSave.isFocused.value"
    :is-dirty="autoSave.isDirty.value"
    :has-display-value="true"
    :disabled="disabled"
    :error-message="autoSave.errorMessage.value"
    :saved-label="t('common.autoSaveField.saved')"
    :retry-label="t('common.autoSaveField.retry')"
    :cancel-label="t('common.autoSaveField.cancel')"
    span-class="detail-stock-unit-autosave span-full"
    @retry="autoSave.trySave"
    @cancel="onCancel"
  >
    <div class="detail-stock-unit-editor" @focusin="autoSave.handleFocus">
      <p class="section-hint">{{ t('components.materialDetail.stockUnitSectionHint') }}</p>
      <div class="stock-unit-options" role="tablist">
        <button
          v-for="opt in unitOptions"
          :key="opt.value"
          type="button"
          class="qty-entry-mode-btn"
          :class="{ active: draft.unit === opt.value }"
          :disabled="disabled || autoSave.isSaving.value"
          @click="selectUnit(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>
      <p class="form-hint">{{ unitHint }}</p>

      <div v-if="draft.unit === 'm'" class="meter-length-block mt-2">
        <p v-if="lengthMetersDisplay != null" class="form-hint meter-length-value">
          {{ t('components.materialDetail.stockUnitLengthFromDetails', { meters: lengthMetersDisplay }) }}
        </p>
        <p v-else class="form-hint is-warning">
          {{ t('components.materialDetail.stockUnitLengthRequired') }}
        </p>
      </div>

      <p v-if="packagingActive && draft.unit === 'Stk'" class="form-hint mt-2">
        {{ t('components.materialDetail.packagingHint') }}
      </p>
      <p class="form-hint mt-2">{{ nameSuffixHint }}</p>
      <p v-if="namePreview && namePreview !== materialName.trim()" class="form-hint stock-unit-name-preview">
        {{ t('components.materialDetail.stockUnitNamePreview', { name: namePreview }) }}
      </p>
    </div>
  </AutoSaveFieldShell>

  <EDialog
    v-model="lengthDialogOpen"
    :max-width="400"
    :title="t('components.materialDetail.stockUnitLengthDialogTitle')"
  >
    <p class="length-dialog-hint">{{ t('components.materialDetail.stockUnitLengthDialogHint') }}</p>
    <ETextField
      v-model="lengthMetersInput"
      type="number"
      min="0.01"
      step="any"
      class="mb-3"
      :label="t('components.materialDetail.stockUnitLengthDialogLabel')"
      :placeholder="t('components.materialDetail.stockUnitLengthDialogPlaceholder')"
      hide-details="auto"
      autofocus
    />
    <template #actions>
      <EButton variant="secondary" size="small" @click="cancelLengthDialog">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!parsedLengthMeters"
        :loading="lengthDialogSaving"
        @click="confirmLengthDialog"
      >
        {{ t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { updateMaterial, type Material } from '@/api/materials'
import { AutoSaveFieldShell } from '@/components/common/autoSave'
import { useAutoSaveField } from '@/composables/useAutoSaveField'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'
import {
  applyMaterialUnitSuffixToName,
  isPackagingUnit,
  parseSizeLengthCm,
  sizeLengthCmToMeters,
  type StockUnitOption,
} from '@/utils/materialStockUnit'

type StockUnitDraft = {
  unit: StockUnitOption
}

const props = withDefaults(
  defineProps<{
    materialId: string
    materialName: string
    packUnit?: string | null
    packSize?: number | null
    sizeLengthCm?: string | number | null
    packagingActive?: boolean
    trackingType?: string | null
    disabled?: boolean
    showLabel?: boolean
  }>(),
  { showLabel: true },
)

const emit = defineEmits<{
  saved: [material: Material]
}>()

const { t } = useI18n()
const inputId = `detail-stock-unit-${props.materialId}`

const lengthDialogOpen = ref(false)
const lengthMetersInput = ref('')
const lengthDialogSaving = ref(false)
const pendingLengthCm = ref<number | null>(null)

function packToDraft(packUnit?: string | null): StockUnitDraft {
  const pu = (packUnit || '').trim()
  if (pu === 'm') return { unit: 'm' }
  return { unit: 'Stk' }
}

function draftToKey(d: StockUnitDraft): string {
  return d.unit
}

function keyToDraft(key: string): StockUnitDraft {
  return { unit: key === 'm' ? 'm' : 'Stk' }
}

function effectiveSizeLengthCm(): number | null {
  if (pendingLengthCm.value != null) return pendingLengthCm.value
  return parseSizeLengthCm(props.sizeLengthCm)
}

function resolvePackFields(d: StockUnitDraft): { pack_unit: string | null; pack_size: number | null } {
  if (d.unit === 'm') return { pack_unit: 'm', pack_size: null }
  if (props.packagingActive && props.packSize && props.packSize >= 2 && props.packUnit) {
    return { pack_unit: props.packUnit, pack_size: props.packSize }
  }
  return { pack_unit: 'Stk', pack_size: null }
}

const draft = reactive<StockUnitDraft>(packToDraft(props.packUnit))
const saveKey = ref(draftToKey(draft))
const baselineKey = ref(saveKey.value)

const unitOptions = computed(() => {
  const base = [
    { value: 'Stk' as const, label: t('workshop.repairPartsList.unitStkShort') },
    { value: 'm' as const, label: 'm' },
  ]
  if (props.trackingType === 'serialized') return base.filter((o) => o.value === 'Stk')
  return base
})

const lengthMetersDisplay = computed(() => {
  const m = sizeLengthCmToMeters(effectiveSizeLengthCm())
  if (m == null) return null
  return Number.isInteger(m) ? String(m) : m.toFixed(2).replace(/\.?0+$/, '')
})

const unitHint = computed(() => {
  if (draft.unit === 'm') return t('components.materialDetail.stockUnitHintMeterWithLength')
  return t('components.materialCreateWizard.stockUnitHintStk')
})

const namePreview = computed(() => {
  const { pack_unit, pack_size } = resolvePackFields(draft)
  return applyMaterialUnitSuffixToName(
    props.materialName,
    pack_unit,
    pack_size,
    draft.unit === 'm' ? effectiveSizeLengthCm() : null,
  )
})

const nameSuffixHint = computed(() => {
  if (draft.unit === 'm') return t('components.materialDetail.stockUnitNameSuffixHintMWithLength')
  return t('components.materialDetail.stockUnitNameSuffixHintStk')
})

const parsedLengthMeters = computed((): number | null => {
  const raw = lengthMetersInput.value.trim()
  if (!raw) return null
  const n = Number(raw.replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  return n
})

function syncSaveKeyFromDraft() {
  saveKey.value = draftToKey(draft)
}

async function persistStockUnit(key: string, sizeLengthCmOverride?: number | null) {
  const d = keyToDraft(key)
  if (d.unit === 'm') {
    const cm = sizeLengthCmOverride ?? effectiveSizeLengthCm()
    if (cm == null) {
      throw new Error(t('components.materialDetail.stockUnitLengthRequired'))
    }
  }

  const { pack_unit, pack_size } = resolvePackFields(d)
  const sizeLengthForName = d.unit === 'm' ? (sizeLengthCmOverride ?? effectiveSizeLengthCm()) : null
  const newName = applyMaterialUnitSuffixToName(
    props.materialName,
    pack_unit,
    pack_size,
    sizeLengthForName,
  )

  const payload: Record<string, unknown> = { pack_unit, pack_size }
  if (d.unit === 'm' && sizeLengthCmOverride != null) {
    payload.size_length = normalizeMaterialMetricInput(String(sizeLengthCmOverride), 'cm')
  }
  if (newName !== props.materialName.trim()) payload.name = newName

  const updated = await updateMaterial(props.materialId, payload)
  pendingLengthCm.value = null
  Object.assign(draft, packToDraft(updated.pack_unit))
  const nextKey = draftToKey(draft)
  saveKey.value = nextKey
  baselineKey.value = nextKey
  autoSave.markBaseline(nextKey)
  emit('saved', updated)
}

const autoSave = useAutoSaveField({
  modelValue: saveKey,
  baseline: baselineKey,
  type: 'select',
  disabled: computed(() => !!props.disabled),
  save: async (value) => {
    await persistStockUnit(String(value ?? saveKey.value))
  },
})

watch(
  () => [props.packUnit, props.packSize, props.sizeLengthCm] as const,
  ([pu]) => {
    const next = packToDraft(pu)
    Object.assign(draft, next)
    const key = draftToKey(next)
    saveKey.value = key
    baselineKey.value = key
    autoSave.markBaseline(key)
  },
)

function selectUnit(unit: StockUnitOption) {
  if (draft.unit === unit) return
  if (unit === 'm' && effectiveSizeLengthCm() == null) {
    lengthMetersInput.value = ''
    lengthDialogOpen.value = true
    return
  }
  draft.unit = unit
  syncSaveKeyFromDraft()
  void autoSave.trySave()
}

function cancelLengthDialog() {
  lengthDialogOpen.value = false
  lengthMetersInput.value = ''
}

async function confirmLengthDialog() {
  const meters = parsedLengthMeters.value
  if (meters == null) return
  const cm = Math.round(meters * 100)
  lengthDialogSaving.value = true
  try {
    draft.unit = 'm'
    syncSaveKeyFromDraft()
    await persistStockUnit(saveKey.value, cm)
    lengthDialogOpen.value = false
    lengthMetersInput.value = ''
  } finally {
    lengthDialogSaving.value = false
  }
}

function onCancel() {
  const restored = keyToDraft(baselineKey.value)
  Object.assign(draft, restored)
  autoSave.revertToBaseline((value) => {
    saveKey.value = String(value ?? baselineKey.value)
  })
}
</script>

<style scoped>
.stock-unit-options {
  display: flex !important;
  flex-wrap: wrap;
  gap: 8px;
  margin: 10px 0 12px;
}

.qty-entry-mode-btn {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #374151;
  border-radius: 8px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  min-width: 48px;
  line-height: 1.2;
  appearance: none;
}

.qty-entry-mode-btn:hover:not(:disabled) {
  border-color: #93c5fd;
  background: #f8fafc;
}

.qty-entry-mode-btn.active {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
}

.qty-entry-mode-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.detail-stock-unit-editor .section-hint,
.detail-stock-unit-editor .form-hint {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.45;
  margin: 4px 0 0;
}

.form-hint.is-warning {
  color: #b45309;
}

.meter-length-value {
  font-weight: 500;
  color: #374151;
}

.length-dialog-hint {
  margin: 0 0 12px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
}

:deep(.detail-stock-unit-autosave.autosave-field) {
  margin: 0;
}

:deep(.detail-stock-unit-autosave .autosave-field-frame) {
  border: none;
  box-shadow: none;
  padding-top: 0;
}

:deep(.detail-stock-unit-autosave .autosave-append) {
  top: 0;
}
</style>
