<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaterialBatch } from '@/api/materials'
import { AutoSaveField } from '@/components/common/autoSave'
import { packSalePriceFromUnitChf, unitPriceFromPackSaleChf } from '@/utils/packPricing'
import {
  averagePurchaseUnitFromBatches,
  batchPurchaseHistory,
  formatChfDisplay,
  latestPurchaseUnitFromBatches,
  purchasePriceTrendLabel,
  suggestMarkupSupplement,
} from '@/utils/consumablePricing'
import { formatChfFiveRappenString } from '@/utils/rentalPriceAmortization'

const props = withDefaults(
  defineProps<{
    batches?: MaterialBatch[]
    isConsumable?: boolean
    isFood?: boolean
    totalStock?: number
    packSize?: number | null
    packUnit?: string | null
    mode?: 'detail' | 'wizard'
    disabled?: boolean
    baselines?: {
      reference_purchase_unit_chf?: number | null
      sale_price?: number | null
      external_sale_price_chf?: number | null
      pack_sale_price_chf?: number | null
      min_stock?: number | null
    }
    saveField?: (field: string, value: unknown) => Promise<void>
  }>(),
  {
    batches: () => [],
    isConsumable: false,
    isFood: false,
    totalStock: 0,
    packSize: null,
    packUnit: null,
    mode: 'wizard',
    disabled: false,
    baselines: () => ({}),
  },
)

const salePrice = defineModel<number | null>('salePrice', { default: null })
const referencePurchaseUnitChf = defineModel<number | null>('referencePurchaseUnitChf', { default: null })
const externalSalePriceChf = defineModel<number | null>('externalSalePriceChf', { default: null })
const packSalePriceChf = defineModel<number | null>('packSalePriceChf', { default: null })
const minStock = defineModel<number | null>('minStock', { default: null })

const { t, locale } = useI18n()

const acquisitionOpen = ref(true)
const billingOpen = ref(true)
const externalOpen = ref(true)

const markupPercent = ref(25)

/** unit = Stückpreis eingeben; pack = VE-Preis eingeben (wie Chargen-Erfassung). */
const packSaleInputMode = ref<'unit' | 'pack'>('unit')

const isDetailAutoSave = computed(() => props.mode === 'detail' && !!props.saveField)

const packSalePricingAvailable = computed(() => {
  const ps = props.packSize
  const pu = props.packUnit?.trim()
  return ps != null && ps >= 2 && !!pu
})

const packUnitLabel = computed(
  () => props.packUnit?.trim() || t('components.materialCreateWizard.packUnitFallbackGeneric'),
)

const batchHistory = computed(() => batchPurchaseHistory(props.batches))
const avgPurchaseUnit = computed(() => averagePurchaseUnitFromBatches(props.batches))
const latestPurchaseUnit = computed(() => latestPurchaseUnitFromBatches(props.batches))
const priceTrend = computed(() => purchasePriceTrendLabel(props.batches))

const packSaleToUnitSaleChf = computed(() => {
  const pp = packSalePriceChf.value
  const ps = props.packSize
  if (pp == null || ps == null || ps < 2) return null
  return unitPriceFromPackSaleChf(Number(pp), Number(ps))
})

const unitSaleToPackSaleChf = computed(() => {
  const sp = salePrice.value
  const ps = props.packSize
  if (sp == null || ps == null || ps < 2) return null
  return packSalePriceFromUnitChf(Number(sp), Number(ps))
})

const derivedSalePriceHint = computed(() => {
  if (packSaleInputMode.value !== 'pack') return null
  return packSaleToUnitSaleChf.value
})

const derivedPackPriceHint = computed(() => {
  if (packSaleInputMode.value !== 'unit') return null
  return unitSaleToPackSaleChf.value
})

function syncCompanionSalePriceFromMode(): void {
  if (!packSalePricingAvailable.value) return
  if (packSaleInputMode.value === 'unit') {
    const derived = unitSaleToPackSaleChf.value
    if (derived != null) packSalePriceChf.value = derived
  } else {
    const derived = packSaleToUnitSaleChf.value
    if (derived != null) salePrice.value = derived
  }
}

function onPackSaleModeToggle(checked: boolean): void {
  packSaleInputMode.value = checked ? 'pack' : 'unit'
  syncCompanionSalePriceFromMode()
}

/** Beim Laden: VE-Modus wenn nur pack_sale_price_chf gesetzt ist. */
watch(
  [salePrice, packSalePriceChf, packSalePricingAvailable],
  ([sp, pp, available]) => {
    if (!available) return
    const hasUnit = sp != null && sp > 0
    const hasPack = pp != null && pp > 0
    if (!hasUnit && hasPack) packSaleInputMode.value = 'pack'
    else if (hasUnit && !hasPack) packSaleInputMode.value = 'unit'
  },
  { immediate: true },
)

/** Referenz-EK; falls leer: letzter EK aus Chargen (Standard-Berechnungsbasis). */
const referencePurchaseCalcBase = computed(() => {
  const ref = referencePurchaseUnitChf.value
  if (ref != null && ref > 0) return ref
  return latestPurchaseUnit.value
})

const externalCalcBase = computed(() => {
  const sp = salePrice.value
  if (sp != null && sp > 0) return sp
  return referencePurchaseCalcBase.value
})

const suggestedExternalSupplement = computed(() =>
  suggestMarkupSupplement(externalCalcBase.value, markupPercent.value),
)

const externalUnitTotalPreview = computed(() => {
  const base = salePrice.value
  const extra = externalSalePriceChf.value
  if (extra == null || extra <= 0) return null
  if (base == null || base <= 0) return extra
  return Math.round((base + extra) * 100) / 100
})

function formatBatchDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString(locale.value, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return iso
  }
}

async function applyReferenceFromBatches(useLatest = true): Promise<void> {
  const value = useLatest ? latestPurchaseUnit.value : avgPurchaseUnit.value
  if (value == null) return
  referencePurchaseUnitChf.value = value
  if (isDetailAutoSave.value && props.saveField) {
    await props.saveField('reference_purchase_unit_chf', value)
  }
}

/** Standard: letzten EK als Referenz übernehmen, solange Referenz noch leer ist. */
async function ensureReferenceFromLatestPurchase(): Promise<void> {
  const latest = latestPurchaseUnit.value
  if (latest == null || latest <= 0) return
  const ref = referencePurchaseUnitChf.value
  if (ref != null && ref > 0) return
  referencePurchaseUnitChf.value = latest
  if (isDetailAutoSave.value && props.saveField) {
    await props.saveField('reference_purchase_unit_chf', latest)
  }
}

watch(
  [() => props.batches, latestPurchaseUnit, referencePurchaseUnitChf],
  () => {
    void ensureReferenceFromLatestPurchase()
  },
  { immediate: true },
)

async function saveSalePriceField(value: unknown): Promise<void> {
  const num = value == null || value === '' ? null : Number(value)
  salePrice.value = Number.isFinite(num as number) ? (num as number) : null
  if (packSaleInputMode.value === 'unit') syncCompanionSalePriceFromMode()
  if (!props.saveField) return
  await props.saveField('sale_price', salePrice.value)
  if (packSaleInputMode.value === 'unit' && packSalePriceChf.value != null) {
    await props.saveField('pack_sale_price_chf', packSalePriceChf.value)
  }
}

async function savePackSalePriceField(value: unknown): Promise<void> {
  const num = value == null || value === '' ? null : Number(value)
  packSalePriceChf.value = Number.isFinite(num as number) ? (num as number) : null
  if (packSaleInputMode.value === 'pack') syncCompanionSalePriceFromMode()
  if (!props.saveField) return
  await props.saveField('pack_sale_price_chf', packSalePriceChf.value)
  if (packSaleInputMode.value === 'pack' && salePrice.value != null) {
    await props.saveField('sale_price', salePrice.value)
  }
}

function onWizardUnitSaleInput(): void {
  if (packSaleInputMode.value === 'unit') syncCompanionSalePriceFromMode()
}

function onWizardPackSaleInput(): void {
  if (packSaleInputMode.value === 'pack') syncCompanionSalePriceFromMode()
}

async function applySuggestedExternalSupplement(): Promise<void> {
  const s = suggestedExternalSupplement.value
  if (s == null) return
  externalSalePriceChf.value = s
  if (isDetailAutoSave.value && props.saveField) {
    await props.saveField('external_sale_price_chf', s)
  }
}

function save(field: string) {
  return async (value: unknown) => {
    if (!props.saveField) return
    await props.saveField(field, value)
  }
}
</script>

<template>
  <div class="consumable-pricing-panel">
    <p class="consumable-pricing-panel__lead text-muted">
      {{ t('components.consumablePricing.lead') }}
    </p>

    <div v-if="isConsumable" class="costs-hint-banner">
      <span>{{ t('components.materialDetail.costsConsumableBanner') }}</span>
    </div>
    <div v-if="isFood" class="costs-hint-banner costs-hint-banner--food">
      <span>{{ t('components.materialDetail.costsFoodBanner') }}</span>
    </div>

    <!-- Accordion 1: Anschaffung -->
    <div class="rental-accordion-item">
      <button
        type="button"
        class="rental-accordion-trigger"
        :aria-expanded="acquisitionOpen"
        @click="acquisitionOpen = !acquisitionOpen"
      >
        <v-icon
          class="rental-accordion-chevron"
          :icon="acquisitionOpen ? 'mdi-chevron-down' : 'mdi-chevron-right'"
          size="small"
          aria-hidden="true"
        />
        <span class="rental-accordion-title">{{ t('components.consumablePricing.accordionAcquisition') }}</span>
        <span v-if="latestPurchaseUnit != null" class="rental-accordion-badge">
          {{ formatChfDisplay(latestPurchaseUnit) }} {{ t('components.materialDetail.currencyFr') }}
        </span>
      </button>
      <div v-show="acquisitionOpen" class="rental-accordion-body">
        <p class="rental-accordion-intro">{{ t('components.consumablePricing.acquisitionIntro') }}</p>

        <dl v-if="avgPurchaseUnit != null || latestPurchaseUnit != null" class="consumable-pricing-stats">
          <div v-if="avgPurchaseUnit != null" class="consumable-pricing-stat">
            <dt>{{ t('components.consumablePricing.avgPurchaseUnit') }}</dt>
            <dd>CHF {{ formatChfDisplay(avgPurchaseUnit) }}</dd>
          </div>
          <div v-if="latestPurchaseUnit != null" class="consumable-pricing-stat">
            <dt>{{ t('components.consumablePricing.latestPurchaseUnit') }}</dt>
            <dd>CHF {{ formatChfDisplay(latestPurchaseUnit) }}</dd>
          </div>
          <div v-if="priceTrend" class="consumable-pricing-stat consumable-pricing-stat--wide">
            <dt>{{ t('components.consumablePricing.priceTrend') }}</dt>
            <dd>CHF {{ priceTrend }}</dd>
          </div>
        </dl>

        <p v-if="batchHistory.length === 0" class="text-muted consumable-pricing-empty">
          {{ t('components.consumablePricing.noBatchPrices') }}
        </p>
        <div v-else class="consumable-pricing-batch-table">
          <div class="consumable-pricing-batch-row consumable-pricing-batch-row--head">
            <span>{{ t('components.consumablePricing.colDate') }}</span>
            <span>{{ t('components.consumablePricing.colQty') }}</span>
            <span>{{ t('components.consumablePricing.colUnitPrice') }}</span>
          </div>
          <div v-for="row in batchHistory.slice(0, 8)" :key="row.id" class="consumable-pricing-batch-row">
            <span>{{ formatBatchDate(row.acquired_on) }}</span>
            <span>{{ row.qty }}</span>
            <span>CHF {{ formatChfFiveRappenString(row.unit_price ?? 0) }}</span>
          </div>
        </div>

        <div v-if="!disabled" class="consumable-pricing-actions">
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="latestPurchaseUnit == null"
            @click="applyReferenceFromBatches(true)"
          >
            {{ t('components.consumablePricing.applyLatestToReference') }}
          </button>
          <button
            type="button"
            class="btn-outline btn-sm"
            :disabled="avgPurchaseUnit == null"
            @click="applyReferenceFromBatches(false)"
          >
            {{ t('components.consumablePricing.applyAvgToReference') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Accordion 2: Preise (intern) -->
    <div class="rental-accordion-item">
      <button
        type="button"
        class="rental-accordion-trigger"
        :aria-expanded="billingOpen"
        @click="billingOpen = !billingOpen"
      >
        <v-icon
          class="rental-accordion-chevron"
          :icon="billingOpen ? 'mdi-chevron-down' : 'mdi-chevron-right'"
          size="small"
          aria-hidden="true"
        />
        <span class="rental-accordion-title">{{ t('components.consumablePricing.accordionPrices') }}</span>
        <span v-if="salePrice != null && salePrice > 0" class="rental-accordion-badge">
          {{ formatChfDisplay(salePrice) }} {{ t('components.materialDetail.currencyFr') }}
        </span>
      </button>
      <div v-show="billingOpen" class="rental-accordion-body">
        <p class="rental-accordion-intro">{{ t('components.consumablePricing.pricesIntro') }}</p>

        <!-- Detail: AutoSave -->
        <div v-if="isDetailAutoSave" class="form-grid">
          <AutoSaveField
            v-model="referencePurchaseUnitChf"
            :baseline="baselines.reference_purchase_unit_chf"
            :label="t('components.materialDetail.labelRefPurchase')"
            type="number"
            :step="0.05"
            :min="0"
            :disabled="disabled"
            :save="save('reference_purchase_unit_chf')"
          />

          <template v-if="packSalePricingAvailable">
            <div class="form-group span-full pack-sale-mode-section">
              <div class="slider-toggle-group pack-toggle-inline">
                <label class="toggle-label">
                  <span class="toggle-wrapper">
                    <input
                      type="checkbox"
                      class="toggle-input"
                      :checked="packSaleInputMode === 'pack'"
                      :disabled="disabled"
                      @change="onPackSaleModeToggle(($event.target as HTMLInputElement).checked)"
                    />
                    <span class="toggle-slider toggle-slider--blue"></span>
                  </span>
                  <span class="toggle-text">
                    <span class="toggle-title">{{ t('components.consumablePricing.packSaleModePackTitle') }}</span>
                    <span class="toggle-desc">{{ t('components.consumablePricing.packSaleModePackDesc') }}</span>
                  </span>
                </label>
              </div>
              <AutoSaveField
                v-if="packSaleInputMode === 'unit'"
                v-model="salePrice"
                :baseline="baselines.sale_price"
                :label="t('components.materialDetail.labelSalePrice')"
                type="number"
                :step="0.05"
                :min="0"
                :disabled="disabled"
                span-class="form-group span-full"
                :save="saveSalePriceField"
              />
              <AutoSaveField
                v-else
                v-model="packSalePriceChf"
                :baseline="baselines.pack_sale_price_chf"
                :label="t('components.materialDetail.labelPackSalePerUnit')"
                type="number"
                :step="0.05"
                :min="0"
                :disabled="disabled"
                span-class="form-group span-full"
                :save="savePackSalePriceField"
              />
              <p v-if="derivedPackPriceHint != null" class="form-hint pack-sale-derived-hint">
                {{
                  t('components.consumablePricing.hintDerivedPackPrice', {
                    price: derivedPackPriceHint.toFixed(2),
                    packSize: packSize,
                    packUnit: packUnitLabel,
                  })
                }}
              </p>
              <p v-else-if="derivedSalePriceHint != null" class="form-hint pack-sale-derived-hint">
                {{
                  t('components.materialDetail.packSaleCalcLine', {
                    packPrice: packSalePriceChf != null ? Number(packSalePriceChf).toFixed(2) : '—',
                    packUnit: packUnitLabel,
                    packSize: packSize,
                    unitPrice: derivedSalePriceHint.toFixed(2),
                  })
                }}
              </p>
            </div>
          </template>
          <AutoSaveField
            v-else
            v-model="salePrice"
            :baseline="baselines.sale_price"
            :label="t('components.materialDetail.labelSalePrice')"
            type="number"
            :step="0.05"
            :min="0"
            :disabled="disabled"
            :save="saveSalePriceField"
          />

          <AutoSaveField
            v-if="isConsumable"
            v-model="minStock"
            :baseline="baselines.min_stock"
            :label="t('components.materialDetail.labelMinStock')"
            type="number"
            :min="0"
            :disabled="disabled"
            :save="save('min_stock')"
          />
        </div>

        <!-- Wizard: direkte Inputs -->
        <div v-else class="form-grid">
          <div class="form-group">
            <label>
              {{ t('components.materialDetail.labelRefPurchase') }}
              <span class="field-required-star">*</span>
            </label>
            <div class="input-with-prefix">
              <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
              <input
                v-model.number="referencePurchaseUnitChf"
                type="number"
                step="0.05"
                min="0"
                class="form-input"
                :placeholder="t('components.materialDetail.phPriceZero')"
              />
            </div>
            <p class="form-hint">{{ t('components.materialDetail.hintRefPurchase') }}</p>
          </div>

          <template v-if="packSalePricingAvailable">
            <div class="form-group span-full pack-sale-mode-section">
              <div class="slider-toggle-group pack-toggle-inline">
                <label class="toggle-label">
                  <span class="toggle-wrapper">
                    <input
                      type="checkbox"
                      class="toggle-input"
                      :checked="packSaleInputMode === 'pack'"
                      @change="onPackSaleModeToggle(($event.target as HTMLInputElement).checked)"
                    />
                    <span class="toggle-slider toggle-slider--blue"></span>
                  </span>
                  <span class="toggle-text">
                    <span class="toggle-title">{{ t('components.consumablePricing.packSaleModePackTitle') }}</span>
                    <span class="toggle-desc">{{ t('components.consumablePricing.packSaleModePackDesc') }}</span>
                  </span>
                </label>
              </div>
              <div v-if="packSaleInputMode === 'unit'" class="form-group">
                <label>
                  {{ t('components.materialDetail.labelSalePrice') }}
                  <span class="field-required-star">*</span>
                </label>
                <div class="input-with-prefix">
                  <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                  <input
                    v-model.number="salePrice"
                    type="number"
                    step="0.05"
                    min="0"
                    class="form-input"
                    :placeholder="t('components.materialDetail.phPriceZero')"
                    @input="onWizardUnitSaleInput"
                  />
                </div>
                <p class="form-hint">{{ t('components.materialDetail.hintSalePerPiece') }}</p>
              </div>
              <div v-else class="form-group">
                <label>{{ t('components.materialDetail.labelPackSalePerUnit') }}</label>
                <div class="input-with-prefix">
                  <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                  <input
                    v-model.number="packSalePriceChf"
                    type="number"
                    step="0.05"
                    min="0"
                    class="form-input"
                    :placeholder="t('components.materialDetail.phPriceZero')"
                    @input="onWizardPackSaleInput"
                  />
                </div>
              </div>
              <p v-if="derivedPackPriceHint != null" class="form-hint pack-sale-derived-hint">
                {{
                  t('components.consumablePricing.hintDerivedPackPrice', {
                    price: derivedPackPriceHint.toFixed(2),
                    packSize: packSize,
                    packUnit: packUnitLabel,
                  })
                }}
              </p>
              <p v-else-if="derivedSalePriceHint != null" class="form-hint pack-sale-derived-hint">
                {{
                  t('components.materialDetail.packSaleCalcLine', {
                    packPrice: packSalePriceChf != null ? Number(packSalePriceChf).toFixed(2) : '—',
                    packUnit: packUnitLabel,
                    packSize: packSize,
                    unitPrice: derivedSalePriceHint.toFixed(2),
                  })
                }}
              </p>
            </div>
          </template>
          <div v-else class="form-group">
            <label>
              {{ t('components.materialDetail.labelSalePrice') }}
              <span class="field-required-star">*</span>
            </label>
            <div class="input-with-prefix">
              <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
              <input
                v-model.number="salePrice"
                type="number"
                step="0.05"
                min="0"
                class="form-input"
                :placeholder="t('components.materialDetail.phPriceZero')"
              />
            </div>
            <p class="form-hint">{{ t('components.materialDetail.hintSalePerPiece') }}</p>
          </div>

          <div v-if="isConsumable" class="form-group">
            <label>
              {{ t('components.materialDetail.labelMinStock') }}
              <span class="optional">{{ t('components.materialDetail.optionalParen') }}</span>
            </label>
            <input
              v-model.number="minStock"
              type="number"
              min="0"
              class="form-input"
              :placeholder="t('components.materialDetail.packSizePlaceholder')"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Accordion 3: Extern (Zusatz + Rechner) -->
    <div class="rental-accordion-item">
      <button
        type="button"
        class="rental-accordion-trigger"
        :aria-expanded="externalOpen"
        @click="externalOpen = !externalOpen"
      >
        <v-icon
          class="rental-accordion-chevron"
          :icon="externalOpen ? 'mdi-chevron-down' : 'mdi-chevron-right'"
          size="small"
          aria-hidden="true"
        />
        <span class="rental-accordion-title">{{ t('components.consumablePricing.accordionExternal') }}</span>
        <span v-if="externalSalePriceChf != null && externalSalePriceChf > 0" class="rental-accordion-badge">
          +{{ formatChfDisplay(externalSalePriceChf) }} {{ t('components.materialDetail.currencyFr') }}
        </span>
      </button>
      <div v-show="externalOpen" class="rental-accordion-body">
        <p class="rental-accordion-intro">{{ t('components.consumablePricing.externalIntro') }}</p>

        <div v-if="!disabled" class="rental-amort-card consumable-pricing-calc">
          <h4 class="rental-amort-title">{{ t('components.consumablePricing.externalCalculatorTitle') }}</h4>
          <p class="rental-amort-hint">{{ t('components.consumablePricing.externalCalculatorHint') }}</p>
          <div class="consumable-pricing-calc__row">
            <label class="consumable-pricing-calc__label" for="consumable-external-markup">
              {{ t('components.consumablePricing.markupPercent') }}
            </label>
            <input
              id="consumable-external-markup"
              v-model.number="markupPercent"
              type="number"
              min="0"
              step="1"
              class="form-input consumable-pricing-calc__input"
            />
          </div>
          <p v-if="externalCalcBase != null" class="form-hint">
            {{
              t('components.consumablePricing.externalCalculatorPreview', {
                base: formatChfDisplay(externalCalcBase),
                markup: markupPercent,
                extra: formatChfDisplay(suggestedExternalSupplement),
              })
            }}
          </p>
          <p v-else class="form-hint text-muted">{{ t('components.consumablePricing.externalCalculatorNeedsBase') }}</p>
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="suggestedExternalSupplement == null"
            @click="applySuggestedExternalSupplement"
          >
            {{ t('components.consumablePricing.applySuggestedExternal') }}
          </button>
        </div>

        <AutoSaveField
          v-if="isDetailAutoSave"
          v-model="externalSalePriceChf"
          :baseline="baselines.external_sale_price_chf"
          :label="t('components.consumablePricing.externalSalePrice')"
          type="number"
          :step="0.05"
          :min="0"
          :disabled="disabled"
          span-class="form-group consumable-pricing-external-field"
          :save="save('external_sale_price_chf')"
        />
        <div v-else class="form-group consumable-pricing-external-field">
          <label>{{ t('components.consumablePricing.externalSalePrice') }}</label>
          <div class="input-with-prefix">
            <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
            <input
              v-model.number="externalSalePriceChf"
              type="number"
              step="0.05"
              min="0"
              class="form-input"
              :placeholder="t('components.materialDetail.phPriceZero')"
            />
          </div>
        </div>

        <p class="form-hint">{{ t('components.consumablePricing.externalSaleHint') }}</p>
        <p v-if="externalUnitTotalPreview != null" class="form-hint consumable-pricing-external-total">
          {{
            t('components.consumablePricing.externalTotalPreview', {
              base: formatChfDisplay(salePrice),
              extra: formatChfDisplay(externalSalePriceChf),
              total: formatChfDisplay(externalUnitTotalPreview),
            })
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.consumable-pricing-panel__lead {
  margin: 0 0 12px;
  font-size: 14px;
  line-height: 1.45;
}

.consumable-pricing-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  margin: 0 0 12px;
}

.consumable-pricing-stat {
  margin: 0;
}

.consumable-pricing-stat dt {
  margin: 0;
  font-size: 12px;
  color: #64748b;
}

.consumable-pricing-stat dd {
  margin: 2px 0 0;
  font-size: 15px;
  font-weight: 600;
}

.consumable-pricing-stat--wide {
  flex: 1 1 100%;
}

.consumable-pricing-batch-table {
  margin: 0 0 12px;
  font-size: 13px;
}

.consumable-pricing-batch-row {
  display: grid;
  grid-template-columns: 1fr 4rem 6rem;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #e2e8f0;
}

.consumable-pricing-batch-row--head {
  font-weight: 600;
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.consumable-pricing-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.consumable-pricing-calc {
  margin: 0 0 16px;
}

.consumable-pricing-calc__row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.consumable-pricing-calc__label {
  font-size: 13px;
  min-width: 8rem;
}

.consumable-pricing-calc__input {
  max-width: 5rem;
}

.consumable-pricing-external-field {
  max-width: 100%;
}

.consumable-pricing-external-total {
  font-weight: 500;
  color: #334155;
}

.consumable-pricing-empty {
  margin: 0 0 12px;
  font-size: 13px;
}

.pack-sale-mode-section {
  margin-top: 4px;
}

.pack-sale-derived-hint {
  margin: 4px 0 0;
  font-weight: 500;
  color: #475569;
}

.slider-toggle-group {
  margin-bottom: 8px;
}

.pack-toggle-inline {
  padding-top: 4px;
}

.toggle-label {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  padding: 10px 12px;
  border-radius: 8px;
  transition: background-color 0.15s;
}

.toggle-label:hover {
  background-color: #f9fafb;
}

.toggle-wrapper {
  position: relative;
  flex-shrink: 0;
  width: 44px;
  height: 24px;
  margin-top: 2px;
}

.toggle-input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #d1d5db;
  transition: 0.2s;
  border-radius: 24px;
}

.toggle-slider::before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.toggle-input:checked + .toggle-slider::before {
  transform: translateX(20px);
}

.toggle-input:checked + .toggle-slider--blue {
  background-color: #3b82f6;
}

.toggle-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toggle-title {
  font-weight: 600;
  font-size: 14px;
  color: #1f2937;
}

.toggle-desc {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.4;
}

/* Gleiches Erscheinungsbild wie RentalPriceAmortizationCalculator */
.rental-amort-card {
  padding: 14px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.rental-amort-title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 6px;
}

.rental-amort-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 12px;
  line-height: 1.45;
}
</style>
