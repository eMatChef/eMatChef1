<template>
  <EDialog
    :model-value="isOpen"
    :max-width="920"
    scrollable
    persistent
    card-class="js-order-dialog-card"
    @update:model-value="onDialogOpenChange"
  >
    <template #title>
      <div class="js-order-dialog__title-row">
        <span class="js-order-dialog__title-text">{{ t('activities.jsMaterial.order.modalTitle') }}</span>
        <v-btn
          icon
          variant="text"
          size="small"
          :aria-label="t('activities.jsMaterial.order.closeAria')"
          @click="closeAsCancel"
        >
          <v-icon icon="mdi-close" size="22" />
        </v-btn>
      </div>
    </template>

    <div v-if="loading" class="js-order-loading text-muted">
      {{ t('activities.jsMaterial.order.loading') }}
    </div>
    <div v-else-if="loadError" class="js-order-load-error" role="alert">
      {{ loadError }}
    </div>
    <template v-else-if="order">
      <p class="field-hint text-muted js-order-modal-intro">
        {{ t('activities.jsMaterial.order.modalIntro') }}
      </p>
      <p class="js-order-doc-links field-hint">
        <a
          :href="JS_REFERENCE_CATALOG_URL"
          target="_blank"
          rel="noopener noreferrer"
          class="js-order-doc-link"
        >
          {{ t('activities.jsMaterial.order.openReferenceCatalog') }}
        </a>
        <span class="text-muted"> · {{ t('activities.jsMaterial.order.orderFormScopeHint') }}</span>
      </p>

      <div class="js-order-stepper-meta">
        <v-chip size="small" variant="tonal" color="primary">
          {{ t('activities.jsMaterial.order.stepOf', { current: stepIndex + 1, total: STEP_COUNT }) }}
        </v-chip>
        <span
          v-if="!readOnly && autoSaveStatus !== 'idle'"
          class="js-order-autosave-status"
          :class="`js-order-autosave-status--${autoSaveStatus}`"
          role="status"
        >
          <template v-if="autoSaveStatus === 'pending' || autoSaveStatus === 'saving'">
            {{ t('common.autoSaveField.saving') }}
          </template>
          <template v-else-if="autoSaveStatus === 'saved'">
            {{ t('common.autoSaveField.saved') }}
          </template>
          <template v-else-if="autoSaveStatus === 'error'">
            {{ autoSaveError || t('common.autoSaveField.error') }}
            <button type="button" class="link-btn js-order-autosave-retry" @click="flushAutoSave">
              {{ t('common.autoSaveField.retry') }}
            </button>
          </template>
        </span>
      </div>

      <nav class="js-order-stepper" :aria-label="t('activities.jsMaterial.order.stepperAria')">
        <button
          v-for="(label, idx) in stepLabels"
          :key="idx"
          type="button"
          class="js-order-stepper-item"
          :class="{
            active: stepIndex === idx,
            done: idx < stepIndex || isStepComplete(idx),
          }"
          :aria-current="stepIndex === idx ? 'step' : undefined"
          @click="goToStep(idx)"
        >
          <span class="js-order-stepper-num">{{ idx + 1 }}</span>
          <span class="js-order-stepper-label">{{ label }}</span>
        </button>
      </nav>

      <section v-show="stepIndex === 0" class="js-order-block js-order-step-panel">
        <h4>{{ t('activities.jsMaterial.order.block1Title') }}</h4>
        <div class="js-order-grid">
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.firstName') }}</label>
            <input v-model="form.block1.first_name" type="text" class="form-input" @input="markOverridden('block1', 'first_name')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.lastName') }}</label>
            <input v-model="form.block1.last_name" type="text" class="form-input" @input="markOverridden('block1', 'last_name')" />
          </div>
          <div class="js-order-field span-2">
            <label>{{ t('activities.jsMaterial.order.fields.email') }}</label>
            <input v-model="form.block1.email" type="email" class="form-input" @input="markOverridden('block1', 'email')" />
          </div>
          <div class="js-order-field span-2">
            <label>{{ t('activities.jsMaterial.order.fields.address') }}</label>
            <input v-model="form.block1.address" type="text" class="form-input" @input="markOverridden('block1', 'address')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.postalCode') }}</label>
            <input v-model="form.block1.postal_code" type="text" class="form-input" @input="markOverridden('block1', 'postal_code')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.city') }}</label>
            <input v-model="form.block1.city" type="text" class="form-input" @input="markOverridden('block1', 'city')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.canton') }}</label>
            <input v-model="form.block1.canton" type="text" class="form-input" @input="markOverridden('block1', 'canton')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.phone') }}</label>
            <input v-model="form.block1.phone" type="text" class="form-input" @input="markOverridden('block1', 'phone')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.personNr') }}</label>
            <input v-model="form.block1.person_nr" type="text" class="form-input" @input="markOverridden('block1', 'person_nr')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.offerNumber') }}</label>
            <input v-model="form.block1.offer_number" type="text" class="form-input" @input="markOverridden('block1', 'offer_number')" />
          </div>
        </div>
      </section>

      <section v-show="stepIndex === 1" class="js-order-block js-order-step-panel">
        <h4>{{ t('activities.jsMaterial.order.block2Title') }}</h4>
        <div class="js-order-grid">
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.courseType') }}</label>
            <select v-model="form.block2.course_type" class="form-input" @change="markOverridden('block2', 'course_type')">
              <option value="">{{ t('activities.jsMaterial.order.courseTypeChoose') }}</option>
              <option value="lager">{{ t('activities.jsMaterial.order.courseTypeLager') }}</option>
              <option value="kaderbildung">{{ t('activities.jsMaterial.order.courseTypeKader') }}</option>
            </select>
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.participantCountLabel') }}</label>
            <input
              v-model.number="participantCountField"
              type="number"
              min="1"
              step="1"
              class="form-input"
              @input="markOverridden('block2', 'participant_count')"
            />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.deliveryDate') }}</label>
            <input v-model="form.block2.delivery_date" type="date" class="form-input" @input="markOverridden('block2', 'delivery_date')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.returnDate') }}</label>
            <input v-model="form.block2.return_date" type="date" class="form-input" @input="markOverridden('block2', 'return_date')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.coachFirstName') }}</label>
            <input v-model="form.block2.coach_first_name" type="text" class="form-input" @input="markOverridden('block2', 'coach_first_name')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.coachLastName') }}</label>
            <input v-model="form.block2.coach_last_name" type="text" class="form-input" @input="markOverridden('block2', 'coach_last_name')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.coachPersonNr') }}</label>
            <input v-model="form.block2.coach_person_nr" type="text" class="form-input" @input="markOverridden('block2', 'coach_person_nr')" />
          </div>
        </div>
      </section>

      <section v-show="stepIndex === 2" class="js-order-block js-order-step-panel">
        <h4>{{ t('activities.jsMaterial.order.block3Title') }}</h4>
        <div class="js-order-delivery-type">
          <label class="js-order-radio">
            <input v-model="deliveryType" type="radio" value="franko" />
            {{ t('settings.activitySettings.jsDeliveryOptions.franko') }}
          </label>
          <label class="js-order-radio">
            <input v-model="deliveryType" type="radio" value="pickup_thun" />
            {{ t('settings.activitySettings.jsDeliveryOptions.pickupThun') }}
          </label>
        </div>
        <div class="js-order-grid">
          <div class="js-order-field span-2">
            <label>{{ t('activities.jsMaterial.order.fields.venueName') }}</label>
            <input v-model="form.block3.venue_name" type="text" class="form-input" @input="markOverridden('block3', 'venue_name')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.contactFirstName') }}</label>
            <input v-model="form.block3.contact_first_name" type="text" class="form-input" @input="markOverridden('block3', 'contact_first_name')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.contactLastName') }}</label>
            <input v-model="form.block3.contact_last_name" type="text" class="form-input" @input="markOverridden('block3', 'contact_last_name')" />
          </div>
          <div class="js-order-field span-2">
            <label>{{ t('activities.jsMaterial.order.fields.address') }}</label>
            <input v-model="form.block3.address" type="text" class="form-input" @input="markOverridden('block3', 'address')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.postalCode') }}</label>
            <input v-model="form.block3.postal_code" type="text" class="form-input" @input="markOverridden('block3', 'postal_code')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.city') }}</label>
            <input v-model="form.block3.city" type="text" class="form-input" @input="markOverridden('block3', 'city')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.canton') }}</label>
            <input v-model="form.block3.canton" type="text" class="form-input" @input="markOverridden('block3', 'canton')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.deliveryPhone') }}</label>
            <input v-model="form.block3.delivery_phone" type="text" class="form-input" @input="markOverridden('block3', 'delivery_phone')" />
          </div>
          <div class="js-order-field">
            <label>{{ t('activities.jsMaterial.order.fields.campLeaderPhone') }}</label>
            <input v-model="form.block3.camp_leader_phone" type="text" class="form-input" @input="markOverridden('block3', 'camp_leader_phone')" />
          </div>
        </div>
      </section>

      <section v-show="stepIndex === 3" class="js-order-block js-order-step-panel">
        <h4>{{ t('activities.jsMaterial.order.block4Title') }}</h4>
        <p class="field-hint text-muted js-order-block4-hint">
          {{ t('activities.jsMaterial.order.block4Hint') }}
        </p>

        <div
          v-if="!readOnly && !effectiveParticipantCount"
          class="js-order-participant-required"
          role="alert"
        >
          <p class="js-order-participant-required-title">
            {{ t('activities.jsMaterial.order.participantCountRequiredTitle') }}
          </p>
          <p class="field-hint js-order-participant-required-hint">
            {{ t('activities.jsMaterial.order.participantCountRequiredHint') }}
          </p>
          <EButton size="small" variant="primary" @click="onRequestParticipantCount">
            {{ t('activities.jsMaterial.order.participantCountEnterButton') }}
          </EButton>
        </div>

        <div
          v-if="jsOrderDotationWarnings.length > 0"
          class="js-order-dotation-warnings"
          role="status"
        >
          <p class="js-order-dotation-warnings-title">
            {{ t('activities.jsMaterial.order.dotationWarningsTitle') }}
          </p>
          <ul class="js-order-dotation-warnings-list">
            <li v-for="(warning, idx) in jsOrderDotationWarnings" :key="idx">{{ warning }}</li>
          </ul>
        </div>

        <div v-if="orderItems.length === 0" class="text-muted js-order-items-empty">
          {{ t('activities.jsMaterial.order.noPositionsYet') }}
        </div>
        <table v-else class="js-order-items-table">
          <thead>
            <tr>
              <th>{{ t('common.material') }}</th>
              <th>{{ t('activities.jsMaterial.order.columnQty') }}</th>
              <th>{{ t('activities.jsMaterial.order.columnDotation') }}</th>
              <th v-if="!readOnly" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in orderItems" :key="row.material_item_id">
              <td>
                <span class="js-order-item-name">{{ row.material_name }}</span>
                <span v-if="row.pdf_form_line" class="js-order-item-pdf-line text-muted">
                  {{ t('activities.jsMaterial.order.pdfFormLine', { line: row.pdf_form_line }) }}
                </span>
              </td>
              <td>
                <input
                  v-model.number="row.quantity_ordered"
                  type="number"
                  min="0"
                  step="1"
                  class="form-input js-order-qty-input"
                  :disabled="readOnly"
                />
              </td>
              <td class="text-muted">
                {{ row.dotation_suggested != null && row.dotation_suggested >= 1 ? row.dotation_suggested : '–' }}
              </td>
              <td v-if="!readOnly">
                <button type="button" class="link-btn js-order-remove-btn" @click="removeOrderItem(row.material_item_id)">
                  {{ t('common.remove') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="!readOnly" class="js-order-catalog-toolbar">
          <div class="js-order-catalog-search-wrap">
            <input
              ref="catalogSearchInputRef"
              v-model="catalogSearch"
              type="search"
              class="form-input js-order-catalog-search"
              :placeholder="t('activities.jsMaterial.order.catalogSearchPlaceholder')"
              @focus="onCatalogSearchFocus"
              @blur="onCatalogSearchBlur"
            />
          </div>
          <EButton
            variant="secondary"
            size="small"
            :disabled="dotationLoading"
            :loading="dotationLoading"
            @click="onApplyDotation"
          >
            {{ t('activities.jsMaterial.order.applyDotationButton') }}
          </EButton>
        </div>

        <Teleport to="body">
          <Transition name="dropdown-fade">
            <div
              v-if="catalogDropdownOpen && !readOnly"
              class="js-order-catalog-dropdown"
              :style="catalogDropdownStyle"
              @mousedown.prevent
            >
              <div v-if="catalogLoading" class="js-order-catalog-dropdown-status text-muted">
                {{ t('activities.jsMaterial.order.catalogLoading') }}
              </div>
              <div v-else-if="catalogItems.length === 0" class="js-order-catalog-dropdown-status text-muted">
                {{ t('activities.jsMaterial.order.catalogLoadError') }}
              </div>
              <template v-else>
                <div class="js-order-catalog-dropdown-head">
                  {{ t('activities.jsMaterial.order.catalogPdfListTitle', { count: catalogItems.length }) }}
                  <span v-if="catalogSearchQuery" class="js-order-catalog-filter-count text-muted">
                    · {{ t('activities.jsMaterial.order.catalogFilterCount', { count: filteredCatalogItems.length }) }}
                  </span>
                </div>
                <div v-if="filteredCatalogItems.length === 0" class="js-order-catalog-dropdown-status text-muted">
                  {{ t('activities.jsMaterial.order.catalogNoMatch') }}
                </div>
                <div v-else class="activity-mat-result-list js-order-catalog-list js-order-catalog-list--compact">
                <div
                  v-for="cat in filteredCatalogItems"
                  :key="cat.id"
                  class="activity-mat-result-row"
                  :class="{
                    'already-added': isMaterialInOrder(cat.id),
                    'variant-blocked': isOtherVariantSelected(cat),
                  }"
                >
                  <div class="activity-mat-result-info">
                    <span class="activity-mat-result-name">
                      <span v-if="cat.pdf_line_order != null" class="js-order-catalog-line-num">
                        {{ cat.pdf_line_order + 1 }}
                      </span>
                      <span class="activity-js-tag">{{ t('activities.common.jsBadge') }}</span>
                      {{ cat.name }}
                    </span>
                    <span v-if="cat.pdf_form_line && cat.pdf_form_line !== cat.name" class="activity-mat-result-meta">
                      {{ t('activities.jsMaterial.order.pdfFormLine', { line: cat.pdf_form_line }) }}
                    </span>
                  </div>
                  <div class="activity-mat-result-actions">
              <span v-if="isMaterialInOrder(cat.id)" class="mat-already-badge">
                {{ t('activities.materialAvailability.inList') }}
              </span>
              <span v-else-if="isOtherVariantSelected(cat)" class="mat-variant-badge">
                {{ t('activities.jsMaterial.order.otherVariantInList') }}
              </span>
              <span v-else-if="getAddableRemaining(cat) === 0" class="mat-max-badge">
                      {{ t('activities.jsMaterial.order.maxReachedShort') }}
                    </span>
                    <template v-if="getDotationAddQty(cat) > 0 && !isOtherVariantSelected(cat)">
                      <button
                        type="button"
                        class="activity-mat-quick-btn activity-mat-set-btn js-order-dotation-btn"
                        :title="t('activities.jsMaterial.order.addSuggestedTitle', { count: getDotationAddQty(cat) })"
                        @click="onAddCatalogDotationQty(cat)"
                      >
                        +{{ getDotationAddQty(cat) }}
                      </button>
                      <span v-if="catalogIncrementOptions(cat).length > 0" class="activity-mat-btn-divider" aria-hidden="true">|</span>
                    </template>
              <button
                v-for="inc in catalogIncrementOptions(cat)"
                v-show="!isOtherVariantSelected(cat)"
                :key="inc"
                      type="button"
                      class="activity-mat-quick-btn"
                      :title="`+${inc}`"
                      @click="addCatalogQty(cat, inc)"
                    >
                      +{{ inc }}
                    </button>
                  </div>
                </div>
                </div>
              </template>
            </div>
          </Transition>
        </Teleport>
      </section>
    </template>

    <template #actions>
      <div class="js-order-dialog__footer">
        <div class="js-order-dialog__footer-start">
          <EButton variant="secondary" :disabled="saving" @click="closeAsCancel">
            {{ t('common.cancel') }}
          </EButton>
          <EButton
            v-if="stepIndex < 3"
            variant="secondary"
            :disabled="loading || saving || !order || readOnly"
            @click="onPrefill"
          >
            {{ t('activities.jsMaterial.order.prefillButton') }}
          </EButton>
          <EButton
            v-if="order?.generated_pdf_url"
            variant="secondary"
            :disabled="loading || saving || pdfGenerating"
            @click="onOpenPdf"
          >
            {{ t('activities.jsMaterial.order.openPdfButton') }}
          </EButton>
        </div>
        <div class="js-order-dialog__footer-end">
          <EButton v-if="stepIndex > 0" variant="secondary" :disabled="saving || pdfGenerating" @click="goBack">
            {{ t('common.back') }}
          </EButton>
          <EButton
            v-if="stepIndex < STEP_COUNT - 1"
            variant="secondary"
            :disabled="saving || pdfGenerating"
            @click="goNext"
          >
            {{ t('common.next') }}
          </EButton>
          <EButton
            variant="secondary"
            :loading="pdfGenerating"
            :disabled="loading || !order || readOnly || saving"
            @click="onGeneratePdf"
          >
            {{ t('activities.jsMaterial.order.generatePdfButton') }}
          </EButton>
          <EButton
            variant="primary"
            :loading="saving"
            :disabled="loading || !order || readOnly || pdfGenerating"
            @click="onSave"
          >
            {{ t('activities.jsMaterial.order.saveButton') }}
          </EButton>
        </div>
      </div>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onUnmounted, reactive, ref, toRaw, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { usePrompt } from '@/composables/usePrompt'
import { EButton, EDialog } from '@/components/form/base'
import {
  EMPTY_JS_ORDER_FORM,
  applyJsOrderDotation,
  fetchActivityJsOrderPdfBlob,
  generateActivityJsOrderPdf,
  getJsMaterialCatalog,
  loadOrCreateActivityJsOrder,
  prefillActivityJsOrder,
  saveActivityJsOrder,
  type ActivityJsOrderApi,
  type ActivityJsOrderItemApi,
  type JsCatalogItem,
  type JsOrderDeliveryType,
  type JsOrderFormData,
  type JsOrderItemSaveRow,
} from '@/api/activityJsOrder'

type JsOrderFormBlockKey = 'block1' | 'block2' | 'block3'

interface JsOrderItemDraft {
  material_item_id: string
  material_name: string
  quantity_ordered: number
  dotation_suggested: number | null
  dotation_max?: number | null
  dotation_group?: string | null
  dotation_group_max?: number | null
  dotation_round_up?: number | null
  stock_available?: number | null
  pdf_form_line?: string | null
  variant_group?: string | null
}

const CATALOG_INCREMENT_CANDIDATES = [1, 2, 3, 5, 10] as const
const CATALOG_DROPDOWN_Z_INDEX = 2600
const CATALOG_DROPDOWN_MAX_HEIGHT = 340
const AUTO_SAVE_DELAY_MS = 600
const AUTO_SAVE_SAVED_ICON_MS = 2000
const JS_REFERENCE_CATALOG_URL = '/docs/js-order/250821_JS_Leihmaterial_Katalog_DE.pdf'

type AutoSaveStatus = 'idle' | 'pending' | 'saving' | 'saved' | 'error'

const props = defineProps<{
  isOpen: boolean
  activityId: string
  departmentId: string
  activityParticipantCount?: number | null
  readOnly?: boolean
}>()

const emit = defineEmits<{
  close: []
  saved: [order: ActivityJsOrderApi]
}>()

const { t } = useI18n()
const toast = useToast()
const { prompt: promptDialog } = usePrompt()

const loading = ref(false)
const saving = ref(false)
const autoSaving = ref(false)
const autoSaveStatus = ref<AutoSaveStatus>('idle')
const autoSaveError = ref('')
const savedSnapshot = ref('')
const suppressAutoSave = ref(false)
const loadError = ref('')
const order = ref<ActivityJsOrderApi | null>(null)
const form = reactive<JsOrderFormData>(structuredClone(EMPTY_JS_ORDER_FORM))
const deliveryType = ref<JsOrderDeliveryType>('franko')
const orderItems = ref<JsOrderItemDraft[]>([])
const catalogSearch = ref('')
const catalogSearchQuery = computed(() => catalogSearch.value.trim())
const catalogItems = ref<JsCatalogItem[]>([])
const filteredCatalogItems = computed(() => {
  const q = catalogSearchQuery.value.toLowerCase()
  const sorted = [...catalogItems.value].sort(
    (a, b) => (a.pdf_line_order ?? 999) - (b.pdf_line_order ?? 999),
  )
  if (!q) return sorted
  return sorted.filter((cat) => {
    const haystack = [cat.name, cat.pdf_form_line ?? '', cat.dotation_hint ?? '']
      .join(' ')
      .toLowerCase()
    return haystack.includes(q)
  })
})
const catalogLoading = ref(false)
const catalogSearchInputRef = ref<HTMLInputElement | null>(null)
const catalogDropdownOpen = ref(false)
const catalogDropdownStyle = ref<Record<string, string>>({})
const dotationLoading = ref(false)
const pdfGenerating = ref(false)
let catalogDropdownCloseTimer: ReturnType<typeof setTimeout> | null = null
let catalogDropdownPositionBound = false
let participantPromptInFlight = false
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
let autoSaveSavedTimer: ReturnType<typeof setTimeout> | null = null

const STEP_COUNT = 4
const stepIndex = ref(0)

const stepLabels = computed(() => [
  t('activities.jsMaterial.order.steps.leadership'),
  t('activities.jsMaterial.order.steps.course'),
  t('activities.jsMaterial.order.steps.delivery'),
  t('activities.jsMaterial.order.steps.material'),
])

function trimmed(value: string | null | undefined): string {
  return (value ?? '').trim()
}

function isBlock1Complete(): boolean {
  return !!(trimmed(form.block1.first_name) && trimmed(form.block1.last_name) && trimmed(form.block1.email))
}

function isBlock2Complete(): boolean {
  return !!(
    effectiveParticipantCount.value &&
    trimmed(form.block2.delivery_date) &&
    trimmed(form.block2.return_date)
  )
}

function isBlock3Complete(): boolean {
  return !!(trimmed(form.block3.venue_name) || trimmed(form.block3.address))
}

function isStepComplete(idx: number): boolean {
  switch (idx) {
    case 0:
      return isBlock1Complete()
    case 1:
      return isBlock2Complete()
    case 2:
      return isBlock3Complete()
    case 3:
      return orderItems.value.length > 0
    default:
      return false
  }
}

function resolveInitialStepIndex(): number {
  if (orderItems.value.length > 0) return 3
  if (!isBlock1Complete()) return 0
  if (!isBlock2Complete()) return 1
  if (!isBlock3Complete()) return 2
  return 3
}

function goToStep(idx: number) {
  if (idx < 0 || idx >= STEP_COUNT) return
  stepIndex.value = idx
}

function goNext() {
  if (stepIndex.value < STEP_COUNT - 1) stepIndex.value += 1
}

function goBack() {
  if (stepIndex.value > 0) stepIndex.value -= 1
}

const effectiveParticipantCount = computed(() => {
  const fromForm = form.block2.participant_count
  if (fromForm != null && fromForm >= 1) return fromForm
  const fromActivity = props.activityParticipantCount
  if (fromActivity != null && fromActivity >= 1) return fromActivity
  const fromOrder = order.value?.participant_count
  if (fromOrder != null && fromOrder >= 1) return fromOrder
  return null
})

const jsOrderCourseType = computed(() => form.block2.course_type?.trim() || 'lager')

const jsOrderDotationWarnings = computed(() => {
  const westeTotal = getGroupTotalInOrder('rettungsweste')
  if (westeTotal < 1) return [] as string[]

  const warnings: string[] = []
  const participants = effectiveParticipantCount.value ?? 0
  const warnMax = 20

  if (westeTotal > warnMax) {
    warnings.push(
      jsOrderCourseType.value === 'kaderbildung'
        ? t('activities.jsMaterial.order.westeWarnOverTwentyKader', { count: westeTotal, max: warnMax })
        : t('activities.jsMaterial.order.westeWarnOverTwentyLager', { count: westeTotal, max: warnMax }),
    )
  }

  if (participants > 0 && westeTotal > participants) {
    warnings.push(
      t('activities.jsMaterial.order.westeWarnOverParticipants', {
        weste: westeTotal,
        participants,
      }),
    )
  }

  return warnings
})

const participantCountField = computed({
  get: () => form.block2.participant_count ?? '',
  set(v: number | string) {
    if (v === '' || v == null) {
      form.block2.participant_count = null
      return
    }
    const n = typeof v === 'number' ? v : Number.parseInt(String(v), 10)
    form.block2.participant_count = Number.isFinite(n) && n >= 1 ? n : null
  },
})

function buildSaveSnapshot(): string {
  const participant =
    form.block2.participant_count != null && form.block2.participant_count >= 1
      ? form.block2.participant_count
      : null

  return JSON.stringify({
    form: cloneForm(form),
    deliveryType: deliveryType.value,
    participant,
    items: orderItemsPayload(),
  })
}

function syncSavedSnapshot() {
  savedSnapshot.value = buildSaveSnapshot()
}

function clearAutoSaveSavedTimer() {
  if (!autoSaveSavedTimer) return
  clearTimeout(autoSaveSavedTimer)
  autoSaveSavedTimer = null
}

function scheduleAutoSaveStatusClear() {
  clearAutoSaveSavedTimer()
  autoSaveSavedTimer = setTimeout(() => {
    autoSaveSavedTimer = null
    if (autoSaveStatus.value === 'saved') {
      autoSaveStatus.value = 'idle'
    }
  }, AUTO_SAVE_SAVED_ICON_MS)
}

function scheduleAutoSave() {
  if (suppressAutoSave.value || loading.value || !order.value || props.readOnly) return
  if (buildSaveSnapshot() === savedSnapshot.value) return

  autoSaveStatus.value = 'pending'
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(() => {
    autoSaveTimer = null
    void flushAutoSave()
  }, AUTO_SAVE_DELAY_MS)
}

async function flushAutoSave(): Promise<ActivityJsOrderApi | null> {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
  if (suppressAutoSave.value || loading.value || !order.value || props.readOnly) return null
  if (buildSaveSnapshot() === savedSnapshot.value) {
    if (autoSaveStatus.value === 'pending') autoSaveStatus.value = 'idle'
    return null
  }
  if (autoSaving.value) return null

  return persistOrder({ silent: true })
}

function cloneForm(source: JsOrderFormData): JsOrderFormData {
  return structuredClone(toRaw(source))
}

function applyOrderToForm(next: ActivityJsOrderApi) {
  suppressAutoSave.value = true
  order.value = next
  Object.assign(form, cloneForm(next.form_data))
  deliveryType.value = next.delivery_type
  if (next.participant_count != null && next.participant_count >= 1) {
    form.block2.participant_count = next.participant_count
  }
  if (
    !form.block2.user_overridden.includes('participant_count') &&
    props.activityParticipantCount != null &&
    props.activityParticipantCount >= 1
  ) {
    form.block2.participant_count = props.activityParticipantCount
  }
  orderItems.value = (next.items ?? []).map((item: ActivityJsOrderItemApi) => ({
    material_item_id: item.material_item_id,
    material_name: item.material_name ?? item.material_item_id,
    quantity_ordered: item.quantity_ordered,
    dotation_suggested: item.dotation_suggested ?? null,
  }))
  enrichOrderItemsFromCatalog()
  syncSavedSnapshot()
  void nextTick(() => {
    suppressAutoSave.value = false
  })
}

function catalogItemFor(materialId: string): JsCatalogItem | undefined {
  return catalogItems.value.find((row) => row.id === materialId)
}

function limitsForCatalogItem(cat: JsCatalogItem) {
  return {
    max: cat.dotation_max ?? null,
    group: cat.dotation_group ?? null,
    groupMax: cat.dotation_group_max ?? null,
    roundUp: cat.dotation_round_up ?? null,
    stock: cat.stock_available ?? null,
  }
}

function limitsForOrderItem(row: JsOrderItemDraft) {
  const cat = catalogItemFor(row.material_item_id)
  return {
    max: row.dotation_max ?? cat?.dotation_max ?? null,
    group: row.dotation_group ?? cat?.dotation_group ?? null,
    groupMax: row.dotation_group_max ?? cat?.dotation_group_max ?? null,
    roundUp: row.dotation_round_up ?? cat?.dotation_round_up ?? null,
    stock: row.stock_available ?? cat?.stock_available ?? null,
  }
}

function getGroupTotalInOrder(group: string): number {
  return orderItems.value.reduce((sum, row) => {
    const limits = limitsForOrderItem(row)
    if (limits.group !== group) return sum
    return sum + row.quantity_ordered
  }, 0)
}

function getAddableRemaining(cat: JsCatalogItem): number | null {
  const limits = limitsForCatalogItem(cat)
  const hasLimit =
    limits.max != null ||
    (limits.group != null && limits.groupMax != null) ||
    limits.stock != null
  if (!hasLimit) return null

  const current = getOrderItem(cat.id)?.quantity_ordered ?? 0
  let remaining = Number.POSITIVE_INFINITY

  if (limits.max != null) {
    remaining = Math.min(remaining, Math.max(0, limits.max - current))
  }

  if (limits.group != null && limits.groupMax != null) {
    remaining = Math.min(remaining, Math.max(0, limits.groupMax - getGroupTotalInOrder(limits.group)))
  }

  if (limits.stock != null) {
    remaining = Math.min(remaining, Math.max(0, limits.stock - current))
  }

  return Number.isFinite(remaining) ? remaining : null
}

function catalogIncrementOptions(cat: JsCatalogItem): number[] {
  const remaining = getAddableRemaining(cat)
  if (remaining != null && remaining <= 0) return []

  const step = limitsForCatalogItem(cat).roundUp
  if (step != null && step > 1) {
    const opts: number[] = []
    for (let n = step; (remaining == null || n <= remaining) && opts.length < 4; n += step) {
      opts.push(n)
    }
    if (opts.length === 0) {
      opts.push(step)
    } else if (remaining != null && remaining > opts[opts.length - 1] && !opts.includes(remaining)) {
      opts.push(remaining)
    }
    return opts
  }

  const opts: number[] = CATALOG_INCREMENT_CANDIDATES.filter((n) => remaining == null || n <= remaining)
  if (remaining != null && remaining > 1 && !opts.includes(remaining)) {
    opts.push(remaining)
  }

  return [...new Set(opts)].sort((a, b) => a - b)
}

function getDotationAddQty(cat: JsCatalogItem): number {
  const suggested = cat.dotation_suggested
  if (suggested == null || suggested < 1) return 0
  const remaining = getAddableRemaining(cat)
  if (remaining != null && remaining <= 0) return 0
  if (remaining == null) return suggested
  return Math.min(suggested, remaining)
}

function enrichOrderItemsFromCatalog() {
  orderItems.value = orderItems.value.map((row) => {
    const cat = catalogItemFor(row.material_item_id)
    if (!cat) return row
    return {
      ...row,
      dotation_max: row.dotation_max ?? cat.dotation_max ?? null,
      dotation_group: row.dotation_group ?? cat.dotation_group ?? null,
      dotation_group_max: row.dotation_group_max ?? cat.dotation_group_max ?? null,
      dotation_round_up: row.dotation_round_up ?? cat.dotation_round_up ?? null,
      stock_available: row.stock_available ?? cat.stock_available ?? null,
      pdf_form_line: cat.pdf_form_line ?? null,
      variant_group: cat.variant_group ?? null,
    }
  })
}

function orderItemsPayload(): JsOrderItemSaveRow[] {
  return orderItems.value.map((row) => ({
    material_item_id: row.material_item_id,
    quantity_ordered: Math.max(0, Math.trunc(row.quantity_ordered) || 0),
    dotation_suggested: row.dotation_suggested,
  }))
}

function isMaterialInOrder(materialId: string): boolean {
  return orderItems.value.some((row) => row.material_item_id === materialId)
}

function findOrderItemByVariantGroup(group: string, excludeMaterialId?: string): JsOrderItemDraft | undefined {
  return orderItems.value.find((row) => {
    if (excludeMaterialId && row.material_item_id === excludeMaterialId) return false
    const cat = catalogItemFor(row.material_item_id)
    return cat?.variant_group === group
  })
}

function isOtherVariantSelected(cat: JsCatalogItem): boolean {
  if (!cat.variant_group) return false
  const existing = findOrderItemByVariantGroup(cat.variant_group, cat.id)
  return existing != null
}

function getOrderItem(materialId: string): JsOrderItemDraft | undefined {
  return orderItems.value.find((row) => row.material_item_id === materialId)
}

function addCatalogQty(cat: JsCatalogItem, qty: number) {
  if (isOtherVariantSelected(cat)) {
    return
  }

  const remaining = getAddableRemaining(cat)
  if (remaining != null && remaining <= 0) {
    toast.warning(t('activities.jsMaterial.order.maxReached'))
    return
  }

  if (cat.variant_group) {
    const other = findOrderItemByVariantGroup(cat.variant_group, cat.id)
    if (other) {
      removeOrderItem(other.material_item_id)
      toast.info(
        t('activities.jsMaterial.order.variantReplaced', {
          old: other.material_name,
          name: cat.name,
        }),
      )
    }
  }

  const requested = Math.max(1, Math.trunc(qty) || 0)
  const add = remaining != null ? Math.min(requested, remaining) : requested
  if (add < requested) {
    toast.warning(t('activities.jsMaterial.order.maxPartialAdd', { count: add }))
  }

  const limits = limitsForCatalogItem(cat)
  const existing = getOrderItem(cat.id)
  if (existing) {
    existing.quantity_ordered += add
    if (existing.dotation_suggested == null && cat.dotation_suggested != null) {
      existing.dotation_suggested = cat.dotation_suggested
    }
    return
  }
  orderItems.value.push({
    material_item_id: cat.id,
    material_name: cat.name,
    quantity_ordered: add,
    dotation_suggested: cat.dotation_suggested ?? null,
    dotation_max: limits.max,
    dotation_group: limits.group,
    dotation_group_max: limits.groupMax,
  })
}

function removeOrderItem(materialId: string) {
  orderItems.value = orderItems.value.filter((row) => row.material_item_id !== materialId)
}

async function ensureParticipantCount(): Promise<number | null> {
  if (effectiveParticipantCount.value) return effectiveParticipantCount.value
  if (participantPromptInFlight) return null

  participantPromptInFlight = true
  try {
    const raw = await promptDialog({
      title: t('activities.jsMaterial.order.participantCountRequiredTitle'),
      message: t('activities.jsMaterial.order.participantCountRequiredHint'),
      placeholder: t('activities.jsMaterial.participantCountPlaceholder'),
      defaultValue:
        form.block2.participant_count != null ? String(form.block2.participant_count) : '',
      required: true,
      confirmText: t('activities.jsMaterial.order.participantCountConfirm'),
    })
    if (!raw) return null

    const n = Number.parseInt(raw, 10)
    if (!Number.isFinite(n) || n < 1) {
      toast.error(t('activities.jsMaterial.order.participantCountInvalid'))
      return null
    }

    form.block2.participant_count = n
    markOverridden('block2', 'participant_count')
    void loadCatalog()
    return n
  } finally {
    participantPromptInFlight = false
  }
}

function onRequestParticipantCount() {
  void ensureParticipantCount()
}

async function onAddCatalogDotationQty(cat: JsCatalogItem) {
  const qty = getDotationAddQty(cat)
  if (qty < 1) {
    toast.warning(t('activities.jsMaterial.order.maxReached'))
    return
  }
  const count = await ensureParticipantCount()
  if (!count) return
  addCatalogQty(cat, qty)
}

async function loadCatalog() {
  if (!props.departmentId || props.readOnly) {
    catalogItems.value = []
    return
  }
  catalogLoading.value = true
  try {
    const result = await getJsMaterialCatalog({
      departmentId: props.departmentId,
      participantCount: effectiveParticipantCount.value,
      courseType: jsOrderCourseType.value,
      limit: 40,
    })
    catalogItems.value = result.items
    enrichOrderItemsFromCatalog()
  } catch (err) {
    console.error(err)
    catalogItems.value = []
  } finally {
    catalogLoading.value = false
  }
}

function syncCatalogDropdownPosition() {
  const el = catalogSearchInputRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 320), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove

  if (openBelow) {
    catalogDropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `min(${CATALOG_DROPDOWN_MAX_HEIGHT}px, ${Math.max(spaceBelow - 4, 80)}px)`,
      zIndex: String(CATALOG_DROPDOWN_Z_INDEX),
    }
    return
  }

  catalogDropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `min(${CATALOG_DROPDOWN_MAX_HEIGHT}px, ${Math.max(spaceAbove - 4, 80)}px)`,
    zIndex: String(CATALOG_DROPDOWN_Z_INDEX),
  }
}

function bindCatalogDropdownPositionListeners() {
  if (catalogDropdownPositionBound) return
  catalogDropdownPositionBound = true
  window.addEventListener('resize', syncCatalogDropdownPosition, { passive: true })
  window.addEventListener('scroll', syncCatalogDropdownPosition, { passive: true, capture: true })
}

function unbindCatalogDropdownPositionListeners() {
  if (!catalogDropdownPositionBound) return
  catalogDropdownPositionBound = false
  window.removeEventListener('resize', syncCatalogDropdownPosition)
  window.removeEventListener('scroll', syncCatalogDropdownPosition, true)
}

function closeCatalogDropdownNow() {
  if (catalogDropdownCloseTimer) {
    clearTimeout(catalogDropdownCloseTimer)
    catalogDropdownCloseTimer = null
  }
  catalogDropdownOpen.value = false
  unbindCatalogDropdownPositionListeners()
}

function onCatalogSearchFocus() {
  if (catalogDropdownCloseTimer) {
    clearTimeout(catalogDropdownCloseTimer)
    catalogDropdownCloseTimer = null
  }
  catalogDropdownOpen.value = true
  void loadCatalog()
  void nextTick(() => {
    syncCatalogDropdownPosition()
    bindCatalogDropdownPositionListeners()
  })
}

function onCatalogSearchBlur() {
  if (catalogDropdownCloseTimer) clearTimeout(catalogDropdownCloseTimer)
  catalogDropdownCloseTimer = setTimeout(() => {
    catalogDropdownCloseTimer = null
    closeCatalogDropdownNow()
  }, 160)
}

async function onApplyDotation() {
  if (!props.activityId || props.readOnly) return
  const participantCount = await ensureParticipantCount()
  if (!participantCount) return

  dotationLoading.value = true
  try {
    const next = await applyJsOrderDotation(props.activityId, {
      participantCount,
    })
    applyOrderToForm(next)
    toast.success(t('activities.jsMaterial.order.applyDotationSuccess'))
    void loadCatalog()
  } catch (err: unknown) {
    console.error(err)
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error ?? t('activities.jsMaterial.order.applyDotationError'))
  } finally {
    dotationLoading.value = false
  }
}

function markOverridden(block: JsOrderFormBlockKey, field: string) {
  const list = form[block].user_overridden
  if (!list.includes(field)) {
    list.push(field)
  }
}

async function loadOrder() {
  if (!props.activityId) return
  loading.value = true
  loadError.value = ''
  try {
    const loaded = await loadOrCreateActivityJsOrder(props.activityId)
    applyOrderToForm(loaded)
    stepIndex.value = resolveInitialStepIndex()
  } catch (err) {
    console.error(err)
    loadError.value = t('activities.jsMaterial.order.loadError')
    order.value = null
  } finally {
    loading.value = false
  }
}

async function onPrefill() {
  if (!props.activityId) return
  saving.value = true
  try {
    const next = await prefillActivityJsOrder(props.activityId)
    applyOrderToForm(next)
    stepIndex.value = resolveInitialStepIndex()
    toast.success(t('activities.jsMaterial.order.prefillSuccess'))
  } catch (err) {
    console.error(err)
    toast.error(t('activities.jsMaterial.order.prefillError'))
  } finally {
    saving.value = false
  }
}

async function persistOrder(options: { silent?: boolean } = {}): Promise<ActivityJsOrderApi | null> {
  const silent = options.silent ?? false
  if (!props.activityId || !order.value || props.readOnly) return null

  if (silent) {
    autoSaving.value = true
    autoSaveStatus.value = 'saving'
  } else {
    saving.value = true
  }

  try {
    const participant =
      form.block2.participant_count != null && form.block2.participant_count >= 1
        ? form.block2.participant_count
        : null
    const saved = await saveActivityJsOrder(props.activityId, {
      form_data: cloneForm(form),
      participant_count: participant,
      delivery_type: deliveryType.value,
      status: 'draft',
      items: orderItemsPayload(),
    })
    applyOrderToForm(saved)
    emit('saved', saved)

    if (silent) {
      autoSaveError.value = ''
      autoSaveStatus.value = 'saved'
      scheduleAutoSaveStatusClear()
    } else {
      if (saved.dotation_warnings?.length) {
        toast.warning(saved.dotation_warnings.join(' · '))
      }
      toast.success(t('activities.jsMaterial.order.saveSuccess'))
    }

    return saved
  } catch (err: unknown) {
    console.error(err)
    const e = err as { response?: { data?: { error?: string; validation_errors?: string[] } } }
    const validation = e.response?.data?.validation_errors
    const message =
      validation?.length
        ? validation.join(' · ')
        : (e.response?.data?.error ?? t('activities.jsMaterial.order.saveError'))

    if (silent) {
      autoSaveError.value = message
      autoSaveStatus.value = 'error'
    } else if (validation?.length) {
      toast.error(validation.join(' · '))
    } else {
      toast.error(message)
    }
    return null
  } finally {
    if (silent) {
      autoSaving.value = false
    } else {
      saving.value = false
    }
  }
}

async function onSave(): Promise<ActivityJsOrderApi | null> {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
  return persistOrder({ silent: false })
}

async function openPdfUrl(pdfUrl: string) {
  try {
    const blob = await fetchActivityJsOrderPdfBlob(pdfUrl)
    const blobUrl = URL.createObjectURL(blob)
    window.open(blobUrl, '_blank', 'noopener,noreferrer')
    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120000)
  } catch (err) {
    console.error(err)
    toast.error(t('activities.jsMaterial.order.openPdfError'))
  }
}

async function onOpenPdf() {
  const pdfUrl = order.value?.generated_pdf_url
  if (!pdfUrl) return
  await openPdfUrl(pdfUrl)
}

async function onGeneratePdf() {
  if (!props.activityId || props.readOnly) return
  pdfGenerating.value = true
  try {
    const saved = await onSave()
    if (!saved) return
    const result = await generateActivityJsOrderPdf(props.activityId)
    applyOrderToForm(result.order)
    emit('saved', result.order)
    toast.success(t('activities.jsMaterial.order.generatePdfSuccess'))
    if (result.pdf_url) {
      await openPdfUrl(result.pdf_url)
    }
  } catch (err: unknown) {
    console.error(err)
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error ?? t('activities.jsMaterial.order.generatePdfError'))
  } finally {
    pdfGenerating.value = false
  }
}

async function closeAsCancel() {
  await flushAutoSave()
  emit('close')
}

function onDialogOpenChange(value: boolean) {
  if (!value) closeAsCancel()
}

watch(
  [form, orderItems, deliveryType],
  () => {
    scheduleAutoSave()
  },
  { deep: true },
)

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      stepIndex.value = 0
      void loadOrder()
    } else {
      closeCatalogDropdownNow()
      clearAutoSaveSavedTimer()
      if (autoSaveTimer) {
        clearTimeout(autoSaveTimer)
        autoSaveTimer = null
      }
      autoSaveStatus.value = 'idle'
      autoSaveError.value = ''
    }
  },
)

watch(stepIndex, async (idx) => {
  if (idx !== 3) {
    closeCatalogDropdownNow()
    return
  }
  if (!props.isOpen || props.readOnly) return
  void loadCatalog()
  if (!effectiveParticipantCount.value) {
    await nextTick()
    await ensureParticipantCount()
  }
})

watch(catalogDropdownOpen, async (open) => {
  if (!open) return
  await nextTick()
  syncCatalogDropdownPosition()
})

onBeforeUnmount(() => {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  clearAutoSaveSavedTimer()
})

onUnmounted(() => {
  unbindCatalogDropdownPositionListeners()
  if (catalogDropdownCloseTimer) clearTimeout(catalogDropdownCloseTimer)
})

watch(effectiveParticipantCount, () => {
  if (props.isOpen && !props.readOnly) void loadCatalog()
})

watch(jsOrderCourseType, () => {
  if (props.isOpen && !props.readOnly && stepIndex.value === 3) void loadCatalog()
})

watch(
  () => props.activityParticipantCount,
  (count) => {
    if (count == null || count < 1) return
    if (form.block2.user_overridden.includes('participant_count')) return
    if (form.block2.participant_count !== count) {
      form.block2.participant_count = count
      if (props.isOpen && stepIndex.value === 3) void loadCatalog()
    }
  },
)
</script>

<style scoped>
.js-order-dialog__title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
}

.js-order-dialog__title-text {
  font-weight: 700;
  line-height: 1.3;
}

.js-order-dialog__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
}

.js-order-dialog__footer-start,
.js-order-dialog__footer-end {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.js-order-modal-intro {
  margin: 0 0 8px;
}

.js-order-doc-links {
  margin: 0 0 12px;
  font-size: 13px;
}

.js-order-doc-link {
  color: #1d4ed8;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.js-order-stepper-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.js-order-autosave-status {
  font-size: 13px;
  color: #6b7280;
}

.js-order-autosave-status--saved {
  color: #047857;
  font-weight: 500;
}

.js-order-autosave-status--error {
  color: #b91c1c;
}

.js-order-autosave-retry {
  margin-left: 6px;
  font-size: 13px;
}

.js-order-stepper {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.js-order-stepper-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #fff;
  color: #374151;
  font-size: 12px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.js-order-stepper-item:hover {
  border-color: #93c5fd;
  background: #f8fafc;
}

.js-order-stepper-item.active {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
}

.js-order-stepper-item.done:not(.active) .js-order-stepper-num {
  background: #dcfce7;
  color: #166534;
}

.js-order-stepper-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #f3f4f6;
  font-size: 11px;
  font-weight: 600;
}

.js-order-stepper-item.active .js-order-stepper-num {
  background: #2563eb;
  color: #fff;
}

.js-order-stepper-label {
  font-weight: 500;
}

.js-order-step-panel {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.js-order-loading {
  font-size: 13px;
}

.js-order-block {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.js-order-block:last-of-type {
  border-bottom: none;
}

.js-order-block h4 {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.js-order-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}

.js-order-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.js-order-field.span-2 {
  grid-column: span 2;
}

.js-order-field label {
  font-size: 12px;
  font-weight: 500;
  color: #374151;
}

.js-order-delivery-type {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 12px;
}

.js-order-radio {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
}

.js-order-load-error {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 13px;
}

.js-order-phase-hint {
  margin: 0;
}

.js-order-block4-hint {
  margin: 0 0 12px;
}

.js-order-participant-required {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  background: #fffbeb;
}

.js-order-participant-required-title {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 600;
  color: #92400e;
}

.js-order-participant-required-hint {
  margin: 0 0 12px;
  color: #78350f;
}

.js-order-dotation-warnings {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  background: #fffbeb;
}

.js-order-dotation-warnings-title {
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
  color: #92400e;
}

.js-order-dotation-warnings-list {
  margin: 0;
  padding-left: 18px;
  color: #78350f;
  font-size: 13px;
}

.js-order-dotation-warnings-list li + li {
  margin-top: 4px;
}

.js-order-catalog-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-top: 16px;
}

.js-order-catalog-search-wrap {
  flex: 1;
  min-width: 200px;
}

.js-order-catalog-search {
  width: 100%;
}

.js-order-catalog-dropdown-head {
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #f3f4f6;
  background: #f9fafb;
}

.js-order-catalog-line-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  margin-right: 6px;
  padding: 0 4px;
  border-radius: 4px;
  background: #e5e7eb;
  color: #374151;
  font-size: 11px;
  font-weight: 600;
}

.js-order-catalog-list--compact .activity-mat-result-row {
  padding: 6px 10px;
  align-items: center;
}

.js-order-catalog-list--compact .activity-mat-result-meta {
  font-size: 12px;
  color: #6b7280;
}

.js-order-catalog-dropdown {
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14);
}

.js-order-catalog-dropdown-status {
  padding: 12px 14px;
  font-size: 13px;
}

.js-order-catalog-list {
  border: none;
  border-radius: 0;
  max-height: none;
  overflow: visible;
  margin-bottom: 0;
}

.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.activity-mat-result-list {
  display: flex;
  flex-direction: column;
}

.activity-mat-result-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px 12px;
  padding: 10px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.activity-mat-result-row:last-child {
  border-bottom: none;
}

.activity-mat-result-row.variant-blocked {
  opacity: 0.72;
}

.js-order-pdf-form-line {
  display: block;
  color: #1d4ed8;
  font-weight: 500;
}

.mat-variant-badge {
  font-size: 12px;
  color: #6b7280;
  white-space: nowrap;
}

.js-order-item-name {
  display: block;
  font-weight: 500;
}

.js-order-item-pdf-line {
  display: block;
  font-size: 12px;
  margin-top: 2px;
}

.activity-mat-result-row.already-added {
  opacity: 0.92;
}

.activity-mat-result-info {
  flex: 1;
  min-width: 140px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.activity-mat-result-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.activity-mat-result-meta {
  font-size: 12px;
  color: #6b7280;
}

.js-order-catalog-suggested {
  color: #047857;
  font-weight: 500;
}

.activity-mat-result-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  justify-content: flex-end;
}

.activity-mat-quick-btn {
  min-width: 40px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #fff;
  color: #059669;
  cursor: pointer;
  line-height: 1.2;
}

.activity-mat-quick-btn:hover:not(:disabled) {
  background: #ecfdf5;
  border-color: #059669;
}

.activity-mat-set-btn {
  min-width: auto;
}

.js-order-dotation-btn {
  border-color: #6ee7b7;
  background: #ecfdf5;
}

.activity-mat-btn-divider {
  color: #d1d5db;
  font-size: 12px;
  user-select: none;
}

.mat-already-badge {
  font-size: 12px;
  color: #059669;
  font-weight: 600;
}

.mat-max-badge {
  font-size: 12px;
  color: #b45309;
  font-weight: 600;
}

.js-order-catalog-remaining {
  color: #6b7280;
}

.js-order-catalog-loading {
  font-size: 13px;
  margin-bottom: 12px;
}

.js-order-items-empty {
  font-size: 13px;
  margin: 8px 0;
}

.js-order-items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.js-order-items-table th,
.js-order-items-table td {
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.js-order-items-table th {
  font-weight: 600;
  color: #374151;
  background: #f9fafb;
}

.js-order-qty-input {
  width: 80px;
}

.js-order-remove-btn {
  font-size: 12px;
}

.link-btn {
  background: none;
  border: none;
  padding: 0;
  color: #2563eb;
  cursor: pointer;
  text-decoration: underline;
}
</style>
