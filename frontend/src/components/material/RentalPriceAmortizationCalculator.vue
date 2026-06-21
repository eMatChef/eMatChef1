<template>
  <div class="rental-amort-card">
    <h3 class="rental-amort-title">{{ t('components.rentalAmortization.title') }}</h3>
    <p class="rental-amort-hint">{{ t('components.rentalAmortization.introHint') }}</p>
    <p class="rental-amort-hint">
      {{ t('components.rentalAmortization.midHint', { ctx: contextPhrase }) }}
    </p>
    <p class="rental-amort-hint">{{ t('components.rentalAmortization.outroHint') }}</p>
    <div class="rental-amort-basis-row">
      <span>
        {{ historicalLineLabel }}
        <strong v-if="historicalBasisChf != null">Fr. {{ formatChfDisplay(historicalBasisChf) }}</strong>
        <strong v-else>—</strong>
      </span>
    </div>
    <div v-if="historicalBasisChf != null" class="rental-amort-basis-row">
      <span>
        {{ t('components.rentalAmortization.piecesLine', { n: effectivePieceCount }) }}
        <strong>Fr. {{ historicalPerPieceDisplay }}</strong>
      </span>
    </div>
    <div class="rental-amort-replace-estimate">
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelInflationPct') }}</label>
        <input
          v-model.number="rentalReplacementInflationPercent"
          type="number"
          min="-20"
          max="30"
          step="0.1"
          class="form-input"
        />
      </div>
      <div class="rental-amort-replace-estimate__text">
        <span v-if="replacementBasisEstimateChf != null">
          <span>{{ t('components.rentalAmortization.estimateLabel') }}</span>
          <strong>Fr. {{ formatChfDisplay(replacementBasisEstimateChf) }}</strong>
          <template v-if="effectivePieceCount > 1">
            <span class="rental-amort-muted">{{
              t('components.rentalAmortization.perPieceMuted', {
                amount: formatChfDisplay(replacementPerPieceChf),
              })
            }}</span>
          </template>
        </span>
        <span v-else class="rental-amort-muted">{{ estimateEmptyHint }}</span>
        <button
          v-if="replacementBasisEstimateChf != null"
          type="button"
          class="btn-outline btn-sm"
          @click="applyReplacementEstimateToBasis"
        >
          {{ t('components.rentalAmortization.btnApplyEstimate') }}
        </button>
      </div>
    </div>
    <div class="rental-amort-grid">
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelManualBasis') }}</label>
        <div class="input-with-prefix">
          <span class="prefix">Fr.</span>
          <input
            v-model="rentalCalcBasisOverride"
            type="text"
            class="form-input"
            :placeholder="manualBasisPlaceholder"
          />
        </div>
        <span class="rental-amort-muted rental-amort-field-hint">{{ t('components.rentalAmortization.fieldHintManual') }}</span>
      </div>
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelYears') }}</label>
        <input v-model.number="rentalCalcYears" type="number" min="1" step="1" class="form-input" />
      </div>
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelDaysInt') }}</label>
        <input v-model.number="rentalCalcDaysInternalPerYear" type="number" min="0" step="1" class="form-input" />
      </div>
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelDaysExt') }}</label>
        <input v-model.number="rentalCalcDaysExternalPerYear" type="number" min="0" step="1" class="form-input" />
      </div>
      <div class="form-group">
        <label>{{ t('components.rentalAmortization.labelMarkup') }}</label>
        <input v-model.number="rentalCalcMarkupPercent" type="number" min="0" step="5" class="form-input" />
      </div>
    </div>
    <div v-if="rentalPreview" class="rental-amort-preview">
      <dl>
        <dt>{{ t('components.rentalAmortization.previewDtYears') }}</dt>
        <dd>{{ rentalPreview.yearsToReplacement }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtInt') }}</dt>
        <dd>{{ rentalPreview.internalDaysPerYear }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtExt') }}</dt>
        <dd>{{ rentalPreview.externalDaysPerYear }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtSum') }}</dt>
        <dd>{{ rentalPreview.totalDaysPerYear }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtBreakeven') }}</dt>
        <dd>Fr. {{ rentalPreview.dailyBreakEven }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtDay') }}</dt>
        <dd>Fr. {{ rentalPreview.day }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtWeek') }}</dt>
        <dd>Fr. {{ rentalPreview.week }}</dd>
        <dt>{{ t('components.rentalAmortization.previewDtMonth') }}</dt>
        <dd>Fr. {{ rentalPreview.month }}</dd>
        <template v-if="rentalPreview.pieceCountUsed > 1 && rentalPreview.dayTotalAllPieces">
          <dt>{{ t('components.rentalAmortization.previewDtAllDay', { n: rentalPreview.pieceCountUsed }) }}</dt>
          <dd>Fr. {{ rentalPreview.dayTotalAllPieces }}</dd>
          <dt>{{ t('components.rentalAmortization.previewDtWeekTotal') }}</dt>
          <dd>Fr. {{ rentalPreview.weekTotalAllPieces }}</dd>
          <dt>{{ t('components.rentalAmortization.previewDtMonthTotal') }}</dt>
          <dd>Fr. {{ rentalPreview.monthTotalAllPieces }}</dd>
          <dt>{{ t('components.rentalAmortization.previewDtBreakevenTotal') }}</dt>
          <dd>Fr. {{ rentalPreview.dailyBreakEvenTotalAllPieces }}</dd>
        </template>
        <dt>{{ t('components.rentalAmortization.previewDtPlanDays') }}</dt>
        <dd>{{ rentalPreview.totalRentalDays }}</dd>
      </dl>
      <div class="rental-amort-actions">
        <button type="button" class="btn-primary btn-sm" @click="applyRentalPriceSuggestion">
          {{ t('components.rentalAmortization.btnApplySuggestion') }}
        </button>
      </div>
    </div>
    <p v-else class="rental-amort-hint">
      {{ invalidPreviewHint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import type { RentalAmortizationDefaults } from '@/api/departmentSettings'
import {
  suggestRentalPricesFromAmortization,
  projectReplacementBasisChf,
  formatChfFiveRappenString,
  roundChfToFiveRappen,
  type RentalCalcParams,
} from '@/utils/rentalPriceAmortization'

const props = withDefaults(
  defineProps<{
    /** Summe historische Anschaffung (Chargen oder Wizard: Stückpreis × Menge) */
    historicalBasisChf: number | null
    /** Stückzahl zur Aufteilung (Anschaffung pro Stück); fehlt → 1 */
    pieceCount?: number | null
    /** `batches` = Detailansicht; `wizard` = Material anlegen */
    context?: 'batches' | 'wizard' | 'combo'
    /** Abteilungs-Standards (aus Department-Settings) */
    defaults: RentalAmortizationDefaults
    /** Gespeicherte Eingaben pro Material; null = nur Standards anzeigen */
    modelValue?: RentalCalcParams | null
  }>(),
  { context: 'batches', modelValue: null }
)

const emit = defineEmits<{
  apply: [payload: { day: string; week: string; month: string }]
  'update:modelValue': [value: RentalCalcParams | null]
}>()

const { t } = useI18n()
const toast = useToast()

const rentalCalcBasisOverride = ref('')
const rentalCalcYears = ref(5)
const rentalCalcDaysInternalPerYear = ref(30)
const rentalCalcDaysExternalPerYear = ref(0)
const rentalCalcMarkupPercent = ref(0)
const rentalReplacementInflationPercent = ref(0.2)

const suppressEmit = ref(false)
const emitReady = ref(false)
let lastEmittedJson = ''

function applyFromProps() {
  const d = props.defaults
  const m = props.modelValue
  suppressEmit.value = true
  rentalReplacementInflationPercent.value = m?.price_increase_percent_per_year ?? d.priceIncreasePercentPerYear
  rentalCalcYears.value = m?.years_to_replacement ?? d.yearsToReplacement
  rentalCalcDaysInternalPerYear.value = m?.internal_days_per_year ?? d.internalDaysPerYear
  rentalCalcDaysExternalPerYear.value = m?.external_days_per_year ?? d.externalDaysPerYear
  rentalCalcMarkupPercent.value = m?.markup_percent ?? d.markupPercent
  rentalCalcBasisOverride.value = m?.basis_override != null && m.basis_override !== '' ? String(m.basis_override) : ''
  void nextTick(() => {
    suppressEmit.value = false
  })
}

function buildPayload(): RentalCalcParams {
  const basisRaw = rentalCalcBasisOverride.value.trim().replace(',', '.')
  return {
    basis_override: basisRaw !== '' ? basisRaw : null,
    price_increase_percent_per_year: Number(rentalReplacementInflationPercent.value),
    years_to_replacement: Math.round(Number(rentalCalcYears.value)),
    internal_days_per_year: Math.round(Number(rentalCalcDaysInternalPerYear.value)),
    external_days_per_year: Math.round(Number(rentalCalcDaysExternalPerYear.value)),
    markup_percent: Number(rentalCalcMarkupPercent.value),
  }
}

watch(
  () => [props.modelValue, props.defaults] as const,
  () => {
    applyFromProps()
    void nextTick(() => {
      lastEmittedJson = JSON.stringify(buildPayload())
    })
  },
  { deep: true, immediate: true }
)

onMounted(() => {
  void nextTick(() => {
    emitReady.value = true
    lastEmittedJson = JSON.stringify(buildPayload())
  })
})

watch(
  [
    rentalCalcBasisOverride,
    rentalCalcYears,
    rentalCalcDaysInternalPerYear,
    rentalCalcDaysExternalPerYear,
    rentalCalcMarkupPercent,
    rentalReplacementInflationPercent,
  ],
  () => {
    if (!emitReady.value || suppressEmit.value) return
    const j = JSON.stringify(buildPayload())
    if (j === lastEmittedJson) return
    lastEmittedJson = j
    emit('update:modelValue', buildPayload())
  },
  { deep: true }
)

const contextPhrase = computed(() => {
  if (props.context === 'wizard') return t('components.rentalAmortization.contextWizard')
  if (props.context === 'combo') return t('components.rentalAmortization.contextCombo')
  return t('components.rentalAmortization.contextBatches')
})

const historicalLineLabel = computed(() => {
  if (props.context === 'wizard') return t('components.rentalAmortization.historicalLineWizard')
  if (props.context === 'combo') return t('components.rentalAmortization.historicalLineCombo')
  return t('components.rentalAmortization.historicalLineBatches')
})

const manualBasisPlaceholder = computed(() => {
  if (props.context === 'wizard') return t('components.rentalAmortization.manualPhWizard')
  if (props.context === 'combo') return t('components.rentalAmortization.manualPhCombo')
  return t('components.rentalAmortization.manualPhBatches')
})

const estimateEmptyHint = computed(() => {
  if (props.context === 'wizard') return t('components.rentalAmortization.estimateEmptyWizard')
  if (props.context === 'combo') return t('components.rentalAmortization.estimateEmptyCombo')
  return t('components.rentalAmortization.estimateEmptyBatches')
})

const invalidPreviewHint = computed(() => {
  if (props.context === 'wizard') return t('components.rentalAmortization.invalidWizard')
  if (props.context === 'combo') return t('components.rentalAmortization.invalidCombo')
  return t('components.rentalAmortization.invalidBatches')
})

const effectivePieceCount = computed(() => {
  const p = props.pieceCount
  const n = Number(p)
  return Number.isFinite(n) && n > 0 ? Math.max(1, Math.floor(n)) : 1
})

const historicalPerPieceDisplay = computed(() => {
  const h = props.historicalBasisChf
  const q = effectivePieceCount.value
  if (h == null || h <= 0) return '—'
  return formatChfFiveRappenString(h / q)
})

const replacementBasisEstimateChf = computed((): number | null => {
  const h = props.historicalBasisChf
  const y = Number(rentalCalcYears.value)
  const p = Number(rentalReplacementInflationPercent.value)
  if (h == null || h <= 0) return null
  if (!Number.isFinite(y) || y <= 0) return null
  if (!Number.isFinite(p)) return null
  return projectReplacementBasisChf(h, y, p)
})

const replacementPerPieceChf = computed((): number | null => {
  const r = replacementBasisEstimateChf.value
  const q = effectivePieceCount.value
  if (r == null || q <= 1) return null
  return roundChfToFiveRappen(r / q)
})

const effectiveRentalBasisChf = computed((): number | null => {
  const basisRaw = rentalCalcBasisOverride.value.trim().replace(',', '.')
  if (basisRaw !== '') {
    const n = Number(basisRaw)
    if (Number.isFinite(n) && n > 0) return n
  }
  const a = props.historicalBasisChf
  return a != null && a > 0 ? a : null
})

const rentalPreview = computed(() => {
  const b = effectiveRentalBasisChf.value
  if (b == null) return null
  const years = Number(rentalCalcYears.value)
  const intD = Number(rentalCalcDaysInternalPerYear.value)
  const extD = Number(rentalCalcDaysExternalPerYear.value)
  const markup = Number(rentalCalcMarkupPercent.value)
  if (!Number.isFinite(years) || years <= 0) return null
  if (!Number.isFinite(intD) || intD < 0) return null
  if (!Number.isFinite(extD) || extD < 0) return null
  if (intD + extD <= 0) return null
  return suggestRentalPricesFromAmortization({
    basisChf: b,
    yearsToReplacement: years,
    internalDaysPerYear: intD,
    externalDaysPerYear: extD,
    markupPercent: Number.isFinite(markup) && markup >= 0 ? markup : 0,
    pieceCount: effectivePieceCount.value,
  })
})

function formatChfDisplay(n: number | null | undefined): string {
  if (n == null || !Number.isFinite(Number(n))) return '—'
  return formatChfFiveRappenString(Number(n))
}

function applyReplacementEstimateToBasis() {
  const v = replacementBasisEstimateChf.value
  if (v == null) return
  rentalCalcBasisOverride.value = v.toFixed(2)
  toast.success(t('components.rentalAmortization.toastBasisFromEstimate'))
}

function applyRentalPriceSuggestion() {
  const p = rentalPreview.value
  if (!p) {
    toast.warning(t('components.rentalAmortization.toastNoValidSuggestion'))
    return
  }
  emit('apply', { day: p.day, week: p.week, month: p.month })
}
</script>

<style scoped>
.rental-amort-card {
  margin-bottom: 20px;
  padding: 14px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.rental-amort-title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 6px 0;
}

.rental-amort-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 12px 0;
  line-height: 1.45;
}

.rental-amort-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px 16px;
  align-items: end;
}

.rental-amort-basis-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  align-items: baseline;
  margin-bottom: 12px;
  font-size: 13px;
  color: #334155;
}

.rental-amort-basis-row strong {
  color: #0f172a;
}

.rental-amort-preview {
  margin-top: 12px;
  padding: 10px 12px;
  background: #fff;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  color: #334155;
}

.rental-amort-preview dl {
  margin: 0;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 16px;
}

.rental-amort-preview dt {
  font-weight: 600;
  color: #475569;
}

.rental-amort-preview dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
}

.rental-amort-actions {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.rental-amort-muted {
  font-size: 12px;
  color: #64748b;
}

.rental-amort-field-hint {
  display: block;
  margin-top: 4px;
}

.rental-amort-replace-estimate {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  align-items: flex-end;
  margin-bottom: 16px;
  padding: 12px 14px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.rental-amort-replace-estimate .form-group {
  margin: 0;
  min-width: 140px;
}

.rental-amort-replace-estimate__text {
  flex: 1;
  min-width: 200px;
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  align-items: center;
  font-size: 13px;
  color: #334155;
  line-height: 1.4;
}

:deep(.form-input) {
  width: 100%;
}

:deep(.input-with-prefix) {
  display: flex;
  align-items: stretch;
}

:deep(.input-with-prefix .prefix) {
  display: flex;
  align-items: center;
  padding: 0 10px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-right: none;
  border-radius: 8px 0 0 8px;
  font-size: 13px;
  color: #64748b;
}

:deep(.input-with-prefix .form-input) {
  border-radius: 0 8px 8px 0;
}
</style>
