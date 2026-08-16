<template>
  <div class="activity-material-availability-lookup">
    <div class="activity-material-scope-tabs" role="tablist" :aria-label="t('activities.materialAvailability.tabSourceAria')">
      <button
        type="button"
        class="activity-material-scope-tab"
        role="tab"
        :aria-selected="materialScopeTab === 'own'"
        :disabled="disabled"
        @click="setMaterialScope('own')"
      >
        {{ t('activities.materialAvailability.tabOwn') }}
      </button>
      <template v-if="showPartnerScopeTabs">
        <button
          type="button"
          class="activity-material-scope-tab"
          role="tab"
          :aria-selected="materialScopeTab === 'all'"
          :disabled="disabled"
          @click="setMaterialScope('all')"
        >
          {{ t('activities.materialAvailability.tabAllDepts') }}
        </button>
        <button
          v-for="p in partnerDepartments"
          :key="p.id"
          type="button"
          class="activity-material-scope-tab"
          role="tab"
          :aria-selected="materialScopeTab === 'single' && singlePartnerDepartmentId === p.id"
          :disabled="disabled"
          @click="setMaterialScope('single', p.id)"
        >
          {{ p.name }}
        </button>
      </template>
    </div>
    <p v-if="hintVariant === 'wizard'" class="field-hint text-muted activity-create-material-hint">
      {{ t('activities.materialAvailability.hintWizardIntro') }}
      <span v-if="hasPlanningPeriod" class="activity-create-material-hint-period">
        {{ t('activities.materialAvailability.hintWizardPeriod') }}
      </span>
      <span v-else class="activity-create-material-hint-period">
        {{ t('activities.materialAvailability.hintWizardNoPeriod') }}
      </span>
    </p>
    <p v-else class="field-hint text-muted activity-create-material-hint">
      {{ t('activities.materialAvailability.hintDraftIntro') }}
      <span v-if="hasPlanningPeriod" class="activity-create-material-hint-period">
        {{ t('activities.materialAvailability.hintDraftPeriod') }}
      </span>
      <span v-else class="activity-create-material-hint-period">
        {{ t('activities.materialAvailability.hintDraftNoPeriod') }}
      </span>
    </p>
    <div class="activity-material-lookup" data-onboarding="activity-create-material-search">
      <MaterialLookupInput
        ref="materialLookupRef"
        :key="`${materialLookupScopeKey}-s${searchResetKey}`"
        v-model="matSearch"
        :fetcher="materialLookupFetcher"
        :min-chars="1"
        :debounce-ms="240"
        :max-suggestions="25"
        :placeholder="t('activities.materialAvailability.searchPlaceholder')"
        input-class="activity-mat-lookup-input form-input"
        :loading-text="t('activities.wizard.form.inviteSearching')"
        :empty-text="emptySearchText"
        :show-empty-when-no-results="matSearchTrimmed.length >= 1"
        :get-result-key="(item) => item.materialItemId"
        :get-result-label="(item) => item.name"
        dropdown-max-height="min(420px, 55vh)"
      >
        <template #results="{ results, isLoading, activeIndex, setActiveIndex }">
          <div v-if="isLoading" class="mat-dropdown-loading">{{ t('activities.wizard.form.inviteSearching') }}</div>
          <div v-else-if="results.length === 0" class="mat-dropdown-empty">
            {{ emptySearchText }}
          </div>
          <div v-else class="mat-dropdown-list activity-mat-result-list">
            <template v-for="(entry, flatIndex) in flattenLookupResults(results)" :key="lookupEntryKey(entry, flatIndex)">
              <div
                v-if="entry.kind === 'divider'"
                class="activity-mat-combo-parts-divider"
                role="separator"
              >
                {{ t('activities.materialAvailability.comboComponentDivider') }}
              </div>
              <div
                v-else
                class="activity-mat-result-row"
                :class="{
                  active: activeIndex === entry.index,
                  'already-added': isAlreadyAdded(entry.mat.materialItemId),
                  'combo-component-row': isFixedComboComponentRow(entry.mat),
                  'activity-mat-result-row--clickable': canSelectRow(entry.mat),
                }"
                @click="onResultRowClick(entry.mat)"
                @mouseenter="setActiveIndex(entry.index)"
              >
                <div class="activity-mat-result-info">
                  <span class="activity-mat-result-name">
                    <span v-if="entry.mat.isConsumable" class="mat-type-icon consumable" :title="t('activities.materialAvailability.titleConsumable')"
                      >🔥</span
                    >
                    <span
                      v-else-if="entry.mat.materialType === 'physical_combo'"
                      class="mat-type-icon rental"
                      :title="t('activities.materialAvailability.titlePhysicalCombo')"
                      >{{ physicalComboIcon(entry.mat) }}</span
                    >
                    <span v-else class="mat-type-icon rental" :title="t('activities.materialAvailability.titleRental')">📦</span>
                    {{ entry.mat.name }}
                    <span
                      v-if="entry.mat.sourceDepartmentId && entry.mat.sourceDepartmentId !== departmentId && entry.mat.sourceDepartmentName"
                      class="mat-dept-badge"
                      :title="t('activities.materialAvailability.titleSourceDept', { name: entry.mat.sourceDepartmentName })"
                    >
                      {{ entry.mat.sourceDepartmentName }}
                    </span>
                    <span v-if="entry.mat.packSize && entry.mat.packUnit" class="mat-pack-badge">
                      {{ t('activities.materialAvailability.packPerShort', { n: entry.mat.packSize, unit: entry.mat.packUnit }) }}
                    </span>
                    <span
                      v-if="entry.mat.materialType === 'physical_combo'"
                      class="activity-combo-badge"
                      :title="t('activities.detail.comboPhysicalTitle')"
                    ><span aria-hidden="true">{{ COMBO_BADGE.physical }}</span> {{ t('activities.detail.comboPhysicalShort') }}</span>
                    <span
                      v-else-if="entry.mat.materialType === 'virtual_combo'"
                      class="activity-combo-badge activity-combo-badge--virtual"
                      :title="t('activities.detail.comboVirtualTitle')"
                    ><span aria-hidden="true">{{ COMBO_BADGE.virtual }}</span> {{ t('activities.detail.comboVirtualShort') }}</span>
                    <span
                      v-if="isFixedComboComponentRow(entry.mat)"
                      class="activity-mat-combo-part-badge"
                      :title="partOfCombosLabel(entry.mat)"
                    >
                      {{ t('activities.materialAvailability.comboComponentPartOf', { names: partOfCombosLabel(entry.mat) }) }}
                    </span>
                  </span>
                  <span class="activity-mat-result-meta">
                    <span class="activity-mat-stock">
                      <span :class="displayFreeStock(entry.mat) > 0 ? 'text-green' : 'text-red'">{{ displayFreeStock(entry.mat) }}</span>
                      <span class="text-muted">&nbsp;{{ t('activities.materialAvailability.freeLabel') }}</span>
                      <span
                        v-if="secondaryStockHint(entry.mat)"
                        class="text-muted activity-mat-stock-in-crates"
                      >
                        {{ secondaryStockHint(entry.mat) }}
                      </span>
                    </span>
                  </span>
                </div>
                <div class="activity-mat-result-actions">
                  <template v-if="isFixedComboComponentRow(entry.mat)">
                    <span class="mat-unavailable-badge">{{ t('activities.materialAvailability.comboComponentBookViaCombo') }}</span>
                  </template>
                  <template
                    v-else-if="
                      maxAddableQty(entry.mat) > 0 &&
                      (!isAlreadyAdded(entry.mat.materialItemId) || canRepeatAddMaterial(entry.mat.materialItemId))
                    "
                  >
                    <span v-if="isAlreadyAdded(entry.mat.materialItemId)" class="mat-already-badge">{{
                      t('activities.materialAvailability.inList')
                    }}</span>
                    <template v-if="entry.mat.packSize && entry.mat.packSize > 1">
                      <button
                        v-if="canAdd(entry.mat, entry.mat.packSize)"
                        type="button"
                        class="activity-mat-quick-btn activity-mat-set-btn"
                        :title="
                          t('activities.materialAvailability.titleAddOnePack', {
                            unit: entry.mat.packUnit || t('activities.materialAvailability.packUnitSet'),
                          })
                        "
                        :disabled="disabled"
                        @mousedown.prevent
                        @click.stop="addQty(entry.mat, entry.mat.packSize)"
                      >
                        1 {{ entry.mat.packUnit || t('activities.materialAvailability.packUnitSet') }}
                      </button>
                      <button
                        v-if="canAdd(entry.mat, entry.mat.packSize * 5)"
                        type="button"
                        class="activity-mat-quick-btn activity-mat-set-btn"
                        :title="
                          t('activities.materialAvailability.titleAddFivePacks', {
                            units: entry.mat.packUnit || t('activities.materialAvailability.packUnitSets'),
                          })
                        "
                        :disabled="disabled"
                        @mousedown.prevent
                        @click.stop="addQty(entry.mat, entry.mat.packSize * 5)"
                      >
                        5 {{ entry.mat.packUnit || t('activities.materialAvailability.packUnitSets') }}
                      </button>
                      <span class="activity-mat-btn-divider" aria-hidden="true">|</span>
                    </template>
                    <button
                      v-if="canAdd(entry.mat, 1)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+1"
                      :disabled="disabled"
                      @mousedown.prevent
                      @click.stop="addQty(entry.mat, 1)"
                    >
                      +1
                    </button>
                    <button
                      v-if="canAdd(entry.mat, 5)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+5"
                      :disabled="disabled"
                      @mousedown.prevent
                      @click.stop="addQty(entry.mat, 5)"
                    >
                      +5
                    </button>
                    <button
                      v-if="canAdd(entry.mat, 10)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+10"
                      :disabled="disabled"
                      @mousedown.prevent
                      @click.stop="addQty(entry.mat, 10)"
                    >
                      +10
                    </button>
                    <span class="activity-mat-btn-divider" aria-hidden="true">|</span>
                    <input
                      type="number"
                      class="activity-mat-custom-qty-input"
                      :min="1"
                      :max="maxAddableQty(entry.mat)"
                      :value="customAddQtyFor(entry.mat.materialItemId)"
                      :title="t('activities.materialAvailability.customQtyTitle')"
                      :aria-label="t('activities.materialAvailability.customQtyLabel')"
                      :placeholder="t('activities.materialAvailability.customQtyPlaceholder')"
                      :disabled="disabled"
                      @click.stop
                      @mousedown.stop
                      @input="onCustomAddQtyInput(entry.mat.materialItemId, ($event.target as HTMLInputElement).value)"
                      @keydown.enter.stop.prevent="addCustomQty(entry.mat)"
                    />
                    <button
                      type="button"
                      class="activity-mat-quick-btn activity-mat-custom-qty-btn"
                      :title="t('activities.materialAvailability.customQtyAdd')"
                      :disabled="disabled || !canAdd(entry.mat, customAddQtyFor(entry.mat.materialItemId))"
                      @mousedown.prevent
                      @click.stop="addCustomQty(entry.mat)"
                    >
                      +
                    </button>
                  </template>
                  <template v-else-if="isAlreadyAdded(entry.mat.materialItemId)">
                    <span class="mat-already-badge">{{ t('activities.materialAvailability.inList') }}</span>
                  </template>
                  <template v-else>
                    <span class="mat-unavailable-badge">{{ t('activities.materialAvailability.unavailable') }}</span>
                  </template>
                </div>
              </div>
            </template>
          </div>
        </template>
      </MaterialLookupInput>
    </div>

    <!-- Vorschlag: verwandtes Zubehör nach Hinzufügen einer Kombo -->
    <div
      v-if="accessorySuggestion && accessorySuggestion.accessories.length > 0"
      class="activity-accessory-suggestion"
    >
      <div class="activity-accessory-suggestion-head">
        <span class="activity-accessory-suggestion-title">
          {{ t('activities.materialAvailability.accessorySuggestTitle', { name: accessorySuggestion.comboName }) }}
        </span>
        <button
          type="button"
          class="activity-accessory-suggestion-dismiss"
          :aria-label="t('common.close')"
          @click="dismissAccessorySuggestion"
        >×</button>
      </div>
      <ul class="activity-accessory-suggestion-list">
        <li v-for="acc in accessorySuggestion.accessories" :key="acc.materialItemId" class="activity-accessory-suggestion-item">
          <span class="activity-accessory-suggestion-name">{{ acc.name }}</span>
          <span class="activity-accessory-suggestion-stock">
            {{ t('activities.materialAvailability.accessorySuggestAvailable', { n: effectiveStock(acc) }) }}
          </span>
          <button
            v-if="canAdd(acc, 1)"
            type="button"
            class="activity-accessory-suggestion-add"
            :disabled="disabled"
            @click="addAccessoryFromSuggestion(acc)"
          >
            {{ t('activities.materialAvailability.accessorySuggestAdd') }}
          </button>
        </li>
      </ul>
    </div>

    <!-- Mengen-Dialog (Zeilenklick bei >1 frei) -->
    <ActivityMaterialQuantityDialog
      v-if="quantityPickerState"
      :material-name="quantityPickerState.material.name"
      :max-quantity="quantityPickerState.maxQuantity"
      :pack-size="quantityPickerState.material.packSize"
      :pack-unit="quantityPickerState.material.packUnit"
      @confirm="onQuantityPickerConfirm"
      @cancel="onQuantityPickerCancel"
    />

    <!-- Konfigurator-Dialog (virtuelle Kombo zusammenstellen) -->
    <ComboConfiguratorDialog
      v-if="configuratorState"
      :combo-id="configuratorState.material.materialItemId"
      :combo-name="configuratorState.material.name"
      :department-id="departmentId"
      :activity-id="activityId || null"
      :start-iso="availabilityContext?.startDate ?? null"
      :end-iso="availabilityContext?.endDate ?? null"
      :initial-quantity="configuratorState.quantity"
      :standalone-quantity-by-material-item-id="standaloneQuantityByMaterialItemId"
      @confirm="onConfiguratorConfirm"
      @cancel="onConfiguratorCancel"
    />

    <!-- „Kombinieren?"-Dialog: Überlapp mit vorhandener Position -->
    <CombineWithExistingDialog
      v-if="combineState"
      :combo-name="combineState.material.name"
      :overlaps="combineState.overlaps"
      @combine="onCombineUseExisting"
      @separate="onCombineSeparate"
      @cancel="onCombineCancel"
    />

    <CombineSeparateShortageDialog
      v-if="combineSeparateShortageState"
      :combo-name="combineSeparateShortageState.material.name"
      :shortages="combineSeparateShortageState.shortages"
      @adjust-and-book="onCombineSeparateAdjustAndBook"
      @use-existing="onCombineSeparateUseExisting"
      @cancel="onCombineSeparateShortageCancel"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityApiType } from '@/api/activities'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import ComboConfiguratorDialog from '@/components/activities/ComboConfiguratorDialog.vue'
import type { ActivityPeriodAvailabilityMaterial } from '@/components/activities/shared/activityAvailabilityMaterial'
import {
  createAvailabilityMaterialLookupFetcher,
  type MaterialLookupAvailabilityContext,
} from '@/composables/useMaterialLookup'
import { materialLookupContextForScopeTab, type MaterialScopeTab } from './shared/activityMaterialAvailabilityScope'
import { storageContainerIconFromPackUnit } from '@/utils/storageContainerDisplay'
import { getRelatedAccessories } from '@/api/materials'
import { fetchMaterialsAvailableForPeriodByIds } from '@/api/materialAvailabilityPeriod'
import { COMBO_BADGE } from '@/utils/comboDisplay'
import { buildVirtualComboConfigSnapshot } from '@/utils/virtualComboMaterial'
import CombineWithExistingDialog, {
  type CombineOverlap,
} from '@/components/activities/CombineWithExistingDialog.vue'
import CombineSeparateShortageDialog from '@/components/activities/CombineSeparateShortageDialog.vue'
import ActivityMaterialQuantityDialog from '@/components/activities/ActivityMaterialQuantityDialog.vue'
import {
  combinePartsFromSeparateShortages,
  computeSeparateBookShortages,
  type SeparateBookShortage,
} from '@/utils/materialPeriodDemand'

interface InvitedPartnerDepartment {
  id: string
  name: string
}

const props = withDefaults(
  defineProps<{
    departmentId: string
    /** Gesetzt sobald ein Aktivitäts-Entwurf existiert (Wizard + Detail) */
    activityId?: string
    activityType: ActivityApiType
    planningStartIso?: string | null
    planningEndIso?: string | null
    /** Bereits gebuchte Menge pro Material-Item (Wizard-Zeilen oder Detail-API) */
    quantityByMaterialItemId: Record<string, number>
    /**
     * Menge je Material-Item, die als **eigenständige** Einzelposition gebucht ist
     * (kein Kombo-Kind, keine Kombo-Hülle). Grundlage für die „Kombinieren?"-Erkennung.
     * Fehlt sie, fällt die Erkennung auf `quantityByMaterialItemId` zurück.
     */
    standaloneQuantityByMaterialItemId?: Record<string, number>
    /** Optional: gespeicherte Summe pro Material (Detail-Entwurf), sonst wie Entwurf */
    savedQuantityByMaterialItemId?: Record<string, number>
    /** Eingeladene Departments (nur accepted → Partner-Tabs) */
    invitedDepartments?: { id: string; name?: string | null; status?: string | null }[]
    disabled?: boolean
    /** Kurztext unter den Tabs: Wizard vs. Detail */
    hintVariant?: 'wizard' | 'draft'
    /**
     * Bei Erhöhung: Sucheingabe leeren und Lookup neu mounten (z. B. Material-Schritt im Stepper).
     */
    searchResetKey?: number
    /**
     * Wenn true: bei Treffern, die schon auf der Aktivität stehen, trotzdem +1/+5/… anbieten (Detail/Packliste).
     * Wizard: false, damit „bereits in Liste“ klar bleibt.
     */
    repeatAddFromSearch?: boolean
  }>(),
  {
    activityId: '',
    planningStartIso: null,
    planningEndIso: null,
    invitedDepartments: () => [],
    standaloneQuantityByMaterialItemId: () => ({}),
    savedQuantityByMaterialItemId: () => ({}),
    disabled: false,
    hintVariant: 'draft',
    searchResetKey: 0,
    repeatAddFromSearch: false,
  },
)

const { t, locale } = useI18n()

const emit = defineEmits<{
  'add-quantity': [
    payload: {
      material: ActivityPeriodAvailabilityMaterial
      quantity: number
      selectedOptionIds?: string[]
      packMode?: 'together' | 'loose'
      selfProvidedAcknowledged?: boolean
      resolvedStock?: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
      resolvedSelfProvided?: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
      configSnapshot?: import('@/api/activities').ComboConfigSnapshot
      combineParts?: Array<{ materialItemId: string; reduceBy: number }>
    },
  ]
  /** Tab Eigen / Partner / J&S — für dieselbe Verfügbarkeitslogik wie die Materialtabelle */
  'scope-change': [payload: { tab: MaterialScopeTab; singlePartnerDepartmentId: string | null }]
}>()

const partnerDepartments = computed((): InvitedPartnerDepartment[] =>
  (props.invitedDepartments ?? [])
    .filter((d) => (d.status ?? 'pending') === 'accepted')
    .map((d) => ({ id: d.id, name: (d.name ?? '').trim() || d.id })),
)

const showPartnerScopeTabs = computed(() => partnerDepartments.value.length > 0)

const materialScopeTab = ref<'own' | 'all' | 'single' | 'js'>('own')
const singlePartnerDepartmentId = ref<string | null>(null)

const planningStartAt = computed(() => {
  const s = props.planningStartIso
  if (!s) return null
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d
})

const planningEndAt = computed(() => {
  const s = props.planningEndIso
  if (!s) return null
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d
})

const materialLookupScopeKey = computed(
  () =>
    `${props.activityId ?? ''}-${materialScopeTab.value}-${singlePartnerDepartmentId.value ?? ''}-${planningStartAt.value?.getTime() ?? ''}-${planningEndAt.value?.getTime() ?? ''}`,
)

watch(
  () => [props.invitedDepartments, props.activityId] as const,
  () => {
    const hasPartners = partnerDepartments.value.length > 0
    if (!hasPartners && (materialScopeTab.value === 'all' || materialScopeTab.value === 'single')) {
      materialScopeTab.value = 'own'
      singlePartnerDepartmentId.value = null
    }
  },
  { deep: true },
)

function setMaterialScope(mode: 'own' | 'all' | 'single' | 'js', partnerId?: string): void {
  materialScopeTab.value = mode
  singlePartnerDepartmentId.value = mode === 'single' && partnerId ? partnerId : null
  matSearch.value = ''
  accessorySuggestion.value = null
}

const matSearch = ref('')

watch(
  () => props.searchResetKey,
  () => {
    matSearch.value = ''
  },
)

const matSearchTrimmed = computed(() => matSearch.value.trim())

const hasPlanningPeriod = computed(() => planningStartAt.value != null && planningEndAt.value != null)

const emptySearchText = computed(() =>
  matSearchTrimmed.value.length >= 1
    ? t('activities.materialAvailability.emptyForQuery', { q: matSearchTrimmed.value })
    : t('activities.empty.noMatch'),
)

const availabilityContext = computed((): MaterialLookupAvailabilityContext | null => {
  if (!props.departmentId) return null
  const base: Pick<
    MaterialLookupAvailabilityContext,
    'departmentId' | 'activityId' | 'excludeActivityId' | 'startDate' | 'endDate' | 'limit'
  > = {
    departmentId: props.departmentId,
    limit: 25,
  }
  if (props.activityId) {
    base.activityId = props.activityId
    base.excludeActivityId = props.activityId
  }
  if (planningStartAt.value && planningEndAt.value) {
    base.startDate = planningStartAt.value.toISOString()
    base.endDate = planningEndAt.value.toISOString()
  }
  return materialLookupContextForScopeTab(
    base,
    materialScopeTab.value,
    partnerDepartments.value.length > 0,
    singlePartnerDepartmentId.value,
  )
})

watch(
  () => [materialScopeTab.value, singlePartnerDepartmentId.value] as const,
  () => {
    emit('scope-change', {
      tab: materialScopeTab.value,
      singlePartnerDepartmentId: singlePartnerDepartmentId.value,
    })
  },
  { immediate: true },
)

const baseFetcher = createAvailabilityMaterialLookupFetcher(() => availabilityContext.value)

async function materialLookupFetcher(query: string): Promise<ActivityPeriodAvailabilityMaterial[]> {
  const rows = await baseFetcher(query)
  const list = (rows as ActivityPeriodAvailabilityMaterial[]).filter((m) => !m.isJsMaterial)
  return [...list].sort((a, b) =>
    (a.name || '').localeCompare(b.name || '', String(locale.value ?? '').startsWith('de') ? 'de' : 'en'),
  )
}

function physicalComboIcon(m: ActivityPeriodAvailabilityMaterial): string {
  return storageContainerIconFromPackUnit(m.linkedContainerPackUnit)
}

function effectiveStock(m: ActivityPeriodAvailabilityMaterial): number {
  return typeof m.availableForPeriod === 'number' ? m.availableForPeriod : 0
}

type LookupResultEntry =
  | { kind: 'material'; mat: ActivityPeriodAvailabilityMaterial; index: number }
  | { kind: 'divider' }

function isFixedComboComponentRow(m: ActivityPeriodAvailabilityMaterial): boolean {
  const memberships = m.partOfPhysicalCombos ?? []
  if (memberships.length === 0) return false
  if (effectiveStock(m) > 0) return false
  const inPhysCombo =
    (m.stockInPhysComboKisten ?? m.stockInContainers ?? 0) + (m.stockAsLinkedRefContainer ?? 0)
  return inPhysCombo > 0
}

function partOfCombosLabel(m: ActivityPeriodAvailabilityMaterial): string {
  return (m.partOfPhysicalCombos ?? [])
    .map((p) => p.comboName)
    .filter((name) => !!name)
    .join(', ')
}

function flattenLookupResults(results: ActivityPeriodAvailabilityMaterial[]): LookupResultEntry[] {
  const primary: ActivityPeriodAvailabilityMaterial[] = []
  const comboParts: ActivityPeriodAvailabilityMaterial[] = []
  for (const mat of results) {
    if (isFixedComboComponentRow(mat)) {
      comboParts.push(mat)
    } else {
      primary.push(mat)
    }
  }
  const out: LookupResultEntry[] = primary.map((mat, index) => ({ kind: 'material', mat, index }))
  if (comboParts.length > 0) {
    out.push({ kind: 'divider' })
    comboParts.forEach((mat, i) => {
      out.push({ kind: 'material', mat, index: primary.length + 1 + i })
    })
  }
  return out
}

function lookupEntryKey(entry: LookupResultEntry, flatIndex: number): string {
  if (entry.kind === 'divider') return `divider-${flatIndex}`
  return entry.mat.materialItemId
}

/** Zusatzinfo unter «X frei» — bei Phys.-Kombos einheitlich, nicht Behälter vs. Phys.-Kombi-Kisten mischen. */
function secondaryStockHint(m: ActivityPeriodAvailabilityMaterial): string | null {
  if ((m.stockInRepair ?? 0) > 0) {
    const ws = m.stockInRepairFromWorkshop ?? 0
    const batchOnly = Math.max(0, (m.stockInRepair ?? 0) - ws)
    if (ws > 0 && batchOnly === 0) {
      return t('activities.materialAvailability.inRepairWorkshopHint', { n: ws })
    }
    return t('activities.materialAvailability.inRepairHint', { n: m.stockInRepair })
  }
  if ((m.stockIssuedOut ?? 0) > 0) {
    return t('activities.materialAvailability.issuedOutHint', { n: m.stockIssuedOut })
  }
  if (m.materialType === 'physical_combo') {
    const inOwn = m.physicalComboSetsInOwnCrate ?? 0
    const total = m.totalStock ?? 0
    if (inOwn > 0 && total > 0 && inOwn < total) {
      return t('activities.materialAvailability.physicalComboSetsInCratesPartialHint', {
        n: inOwn,
        total,
      })
    }
    if (inOwn > 0) {
      return t('activities.materialAvailability.physicalComboSetsInCratesHint', { n: inOwn })
    }
    if (total > 1) {
      return t('activities.materialAvailability.physicalComboTotalSetsHint', { total })
    }
    return null
  }
  if ((m.stockInStorageContainers ?? 0) > 0) {
    return t('activities.materialAvailability.inStorageContainersHint', {
      n: m.stockInStorageContainers,
    })
  }
  const inPhys = m.stockInPhysComboKisten ?? m.stockInContainers ?? 0
  if (inPhys > 0) {
    return t('activities.materialAvailability.inPhysComboKistenHint', { n: inPhys })
  }
  const asRef = m.stockAsLinkedRefContainer ?? 0
  if (asRef > 0) {
    return t('activities.materialAvailability.inLinkedRefContainerHint', { n: asRef })
  }
  return null
}

function draftQtyFor(materialId: string): number {
  return props.quantityByMaterialItemId[materialId] ?? 0
}

/** Nur Server-Summe; kein Fallback auf Entwurf (sonst wie früher: max wächst mit jeder Mengenänderung). */
function savedQtyFor(materialId: string): number {
  const s = props.savedQuantityByMaterialItemId[materialId]
  return typeof s === 'number' ? s : 0
}

function standaloneQtyForMaterial(materialId: string): number {
  const std = props.standaloneQuantityByMaterialItemId
  if (std && Object.keys(std).length > 0) {
    return std[materialId] ?? 0
  }
  return props.quantityByMaterialItemId[materialId] ?? 0
}

function isAlreadyAdded(materialId: string): boolean {
  return draftQtyFor(materialId) > 0
}

/**
 * Extra-Menge buchbar, obwohl Material schon auf der Aktivität steht
 * (z. B. nur als lose Kombo-Teil — dann eigenständige Zusatzposition erlauben).
 */
function canRepeatAddMaterial(materialId: string): boolean {
  if (props.repeatAddFromSearch) return true
  const total = draftQtyFor(materialId)
  if (total <= 0) return false
  const standalone = standaloneQtyForMaterial(materialId)
  // Zusatzmenge erlauben, wenn nicht ausschliesslich eigenständige Zeile(n) (z. B. nur Kombo-Kind/together).
  return standalone < total
}

/** Verbleibend buchbar im Zeitraum (API schliesst eigene Aktivität aus → Entwurfsmenge abziehen). */
function maxAddableQty(m: ActivityPeriodAvailabilityMaterial): number {
  const raw = effectiveStock(m)
  const draft = draftQtyFor(m.materialItemId)
  if (props.activityId) {
    return Math.max(0, raw - draft)
  }
  const saved = savedQtyFor(m.materialItemId)
  return Math.max(0, raw + saved - draft)
}

/** Anzeige «X frei» — nach Abzug der bereits auf dieser Aktivität gebuchten Menge. */
function displayFreeStock(m: ActivityPeriodAvailabilityMaterial): number {
  return maxAddableQty(m)
}

function canSelectRow(m: ActivityPeriodAvailabilityMaterial): boolean {
  if (props.disabled) return false
  if (isFixedComboComponentRow(m)) return false
  if (m.materialType === 'virtual_combo' && isAlreadyAdded(m.materialItemId)) return false
  if (isAlreadyAdded(m.materialItemId) && !canRepeatAddMaterial(m.materialItemId)) return false
  return maxAddableQty(m) >= 1
}

function onResultRowClick(m: ActivityPeriodAvailabilityMaterial) {
  if (!canSelectRow(m)) return
  const max = maxAddableQty(m)
  dismissMaterialLookupDropdown()
  if (max <= 1) {
    addQty(m, 1)
    return
  }
  quantityPickerState.value = { material: m, maxQuantity: max }
}

const quantityPickerState = ref<{
  material: ActivityPeriodAvailabilityMaterial
  maxQuantity: number
} | null>(null)

function onQuantityPickerConfirm(qty: number) {
  const state = quantityPickerState.value
  quantityPickerState.value = null
  if (!state) return
  addQty(state.material, qty)
}

function onQuantityPickerCancel() {
  quantityPickerState.value = null
}

function canAdd(m: ActivityPeriodAvailabilityMaterial, qty: number): boolean {
  if (props.disabled || qty < 1) return false
  if (m.materialType === 'virtual_combo' && isAlreadyAdded(m.materialItemId)) return false
  return maxAddableQty(m) >= qty
}

const customAddQtyByMaterialId = ref<Record<string, number>>({})

function customAddQtyFor(materialId: string): number {
  const v = customAddQtyByMaterialId.value[materialId]
  return typeof v === 'number' && Number.isFinite(v) && v >= 1 ? Math.floor(v) : 1
}

function onCustomAddQtyInput(materialId: string, raw: string) {
  const n = Number(raw)
  customAddQtyByMaterialId.value = {
    ...customAddQtyByMaterialId.value,
    [materialId]: Number.isFinite(n) ? n : 1,
  }
}

function addCustomQty(m: ActivityPeriodAvailabilityMaterial) {
  addQty(m, customAddQtyFor(m.materialItemId))
  customAddQtyByMaterialId.value = { ...customAddQtyByMaterialId.value, [m.materialItemId]: 1 }
}

function addQty(m: ActivityPeriodAvailabilityMaterial, qty: number) {
  if (props.disabled) return
  if (m.materialType === 'virtual_combo' && isAlreadyAdded(m.materialItemId)) return
  const add = Math.min(qty, maxAddableQty(m))
  if (add < 1) return
  // Virtuelle Kombo → Konfigurator-Dialog (Gruppen/Toggles wählen + live Verfügbarkeit), Zeilenmodell B.
  if (m.materialType === 'virtual_combo') {
    openConfigurator(m, add)
    return
  }
  emit('add-quantity', { material: m, quantity: add })
  matSearch.value = ''
  if (m.materialType === 'physical_combo') {
    void loadAccessorySuggestion(m)
  }
}

// ── Konfigurator-Dialog (virtuelle Kombo) ──
const configuratorState = ref<{ material: ActivityPeriodAvailabilityMaterial; quantity: number } | null>(null)
const materialLookupRef = ref<InstanceType<typeof MaterialLookupInput> | null>(null)

function dismissMaterialLookupDropdown() {
  materialLookupRef.value?.closeDropdown()
  materialLookupRef.value?.resetLookup()
  matSearch.value = ''
}

function openConfigurator(m: ActivityPeriodAvailabilityMaterial, qty: number) {
  dismissMaterialLookupDropdown()
  configuratorState.value = { material: m, quantity: qty }
}

function onConfiguratorConfirm(payload: {
  selectedOptionIds: string[]
  quantity: number
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
}) {
  const state = configuratorState.value
  configuratorState.value = null
  if (!state) return

  const addState = {
    material: state.material,
    quantity: payload.quantity,
    selectedOptionIds: payload.selectedOptionIds,
    packMode: payload.packMode,
    selfProvidedAcknowledged: payload.selfProvidedAcknowledged,
    resolvedStock: payload.resolvedStock,
    resolvedSelfProvided: payload.resolvedSelfProvided,
  }

  const overlaps = detectCombineOverlaps(payload.resolvedStock, payload.quantity)
  if (overlaps.length > 0) {
    dismissMaterialLookupDropdown()
    combineState.value = { ...addState, overlaps }
    return
  }

  void proceedComboAddWithStockCheck(addState)
}

function onConfiguratorCancel() {
  configuratorState.value = null
}

// ── „Kombinieren?"-Dialog (Überlapp Kombo-Teil ↔ vorhandene Einzelposition) ──
const combineState = ref<{
  material: ActivityPeriodAvailabilityMaterial
  quantity: number
  selectedOptionIds: string[]
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  overlaps: CombineOverlap[]
} | null>(null)

/** Eigenständige Einzelpositionen (Reduktions-Grundlage), Fallback auf Gesamtmenge. */
function standaloneQtyFor(materialId: string): number {
  const std = props.standaloneQuantityByMaterialItemId
  if (std && Object.keys(std).length > 0) {
    return std[materialId] ?? 0
  }
  return props.quantityByMaterialItemId[materialId] ?? 0
}

function detectCombineOverlaps(
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>,
  comboQty: number,
): CombineOverlap[] {
  const result: CombineOverlap[] = []
  for (const part of resolvedStock) {
    const existing = standaloneQtyFor(part.materialItemId)
    if (existing <= 0) continue
    const comboNeed = Math.max(0, part.qtyPerCombo) * Math.max(1, comboQty)
    if (comboNeed <= 0) continue
    result.push({ materialItemId: part.materialItemId, name: part.name, existingQty: existing, comboNeed })
  }
  return result
}

function onCombineUseExisting() {
  const state = combineState.value
  combineState.value = null
  if (!state) return
  const combineParts = state.overlaps.map((o) => ({
    materialItemId: o.materialItemId,
    reduceBy: Math.min(o.existingQty, o.comboNeed),
  }))
  emitAddQuantityFromCombineState(state, combineParts)
}

function existingComboQtyFor(materialId: string): number {
  const total = props.quantityByMaterialItemId[materialId] ?? 0
  return Math.max(0, total - standaloneQtyFor(materialId))
}

function emitAddQuantityFromCombineState(
  state: {
    material: ActivityPeriodAvailabilityMaterial
    quantity: number
    selectedOptionIds: string[]
    packMode: 'together' | 'loose'
    selfProvidedAcknowledged: boolean
    resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
    resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  },
  combineParts?: Array<{ materialItemId: string; reduceBy: number }>,
): void {
  emit('add-quantity', {
    material: state.material,
    quantity: state.quantity,
    selectedOptionIds: state.selectedOptionIds,
    packMode: state.packMode,
    selfProvidedAcknowledged: state.selfProvidedAcknowledged,
    resolvedStock: state.resolvedStock,
    resolvedSelfProvided: state.resolvedSelfProvided,
    configSnapshot: buildVirtualComboConfigSnapshot({
      quantity: state.quantity,
      selectedOptionIds: state.selectedOptionIds,
      resolvedStock: state.resolvedStock,
      resolvedSelfProvided: state.resolvedSelfProvided,
      packMode: state.packMode,
      selfProvidedAcknowledged: state.selfProvidedAcknowledged,
    }),
    combineParts,
  })
  matSearch.value = ''
  void loadAccessorySuggestion(state.material)
}

async function fetchAvailabilityByMaterialId(
  materialItemIds: string[],
): Promise<Record<string, number>> {
  if (materialItemIds.length === 0) return {}
  const ctx = availabilityContext.value
  const rows = await fetchMaterialsAvailableForPeriodByIds({
    departmentId: props.departmentId,
    activityId: props.activityId || null,
    startDateIso: ctx?.startDate ?? null,
    endDateIso: ctx?.endDate ?? null,
    materialItemIds,
    scope: ctx
      ? {
          source: ctx.source,
          internalScope: ctx.internalScope,
          singleDepartmentId: ctx.singleDepartmentId,
          includeGlobalJs: ctx.includeGlobalJs,
        }
      : null,
  })
  const out: Record<string, number> = {}
  for (const row of rows) {
    out[row.materialItemId] = effectiveStock(row)
  }
  return out
}

async function separateBookShortagesForState(state: {
  quantity: number
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
}): Promise<SeparateBookShortage[]> {
  const materialIds = [...new Set(state.resolvedStock.map((p) => p.materialItemId))]
  const availabilityById = await fetchAvailabilityByMaterialId(materialIds)
  return computeSeparateBookShortages(
    state.resolvedStock.map((p) => ({
      materialItemId: p.materialItemId,
      name: p.name,
      qtyPerCombo: p.qtyPerCombo,
    })),
    state.quantity,
    {
      standaloneQtyFor: standaloneQtyFor,
      existingComboQtyFor: existingComboQtyFor,
      rawAvailableFor: (id) => availabilityById[id],
      savedQtyFor: (id) => props.savedQuantityByMaterialItemId[id] ?? 0,
      excludeCurrentActivity: !!props.activityId,
    },
  )
}

const combineSeparateShortageState = ref<{
  material: ActivityPeriodAvailabilityMaterial
  quantity: number
  selectedOptionIds: string[]
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  shortages: SeparateBookShortage[]
} | null>(null)

async function proceedComboAddWithStockCheck(state: {
  material: ActivityPeriodAvailabilityMaterial
  quantity: number
  selectedOptionIds: string[]
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
  resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
  resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
}): Promise<void> {
  try {
    const shortages = await separateBookShortagesForState(state)
    if (shortages.length > 0) {
      combineSeparateShortageState.value = { ...state, shortages }
      return
    }
  } catch {
    return
  }
  emitAddQuantityFromCombineState(state)
  matSearch.value = ''
  void loadAccessorySuggestion(state.material)
}

async function onCombineSeparate() {
  const state = combineState.value
  if (!state) return
  combineState.value = null
  await proceedComboAddWithStockCheck(state)
}

function onCombineSeparateShortageCancel() {
  combineSeparateShortageState.value = null
}

function onCombineSeparateUseExisting() {
  const state = combineSeparateShortageState.value
  combineSeparateShortageState.value = null
  if (!state) return
  const overlapParts = state.resolvedStock
    .map((p) => {
      const existing = standaloneQtyFor(p.materialItemId)
      if (existing <= 0) return null
      const comboNeed = Math.max(0, p.qtyPerCombo) * Math.max(1, state.quantity)
      return { materialItemId: p.materialItemId, reduceBy: Math.min(existing, comboNeed) }
    })
    .filter((p): p is { materialItemId: string; reduceBy: number } => p != null && p.reduceBy > 0)
  emitAddQuantityFromCombineState(state, overlapParts)
}

function onCombineSeparateAdjustAndBook() {
  const state = combineSeparateShortageState.value
  combineSeparateShortageState.value = null
  if (!state) return
  if (state.shortages.some((s) => s.remainingShortage > 0)) return
  const combineParts = combinePartsFromSeparateShortages(state.shortages)
  emitAddQuantityFromCombineState(state, combineParts.length > 0 ? combineParts : undefined)
}

function onCombineCancel() {
  combineState.value = null
}

// ── Vorschlag: verwandtes Zubehör nach Hinzufügen einer Kombo ──
const accessorySuggestion = ref<{
  comboId: string
  comboName: string
  accessories: ActivityPeriodAvailabilityMaterial[]
} | null>(null)
let accessorySuggestionToken = 0

function dismissAccessorySuggestion() {
  accessorySuggestion.value = null
}

async function loadAccessorySuggestion(combo: ActivityPeriodAvailabilityMaterial) {
  const token = ++accessorySuggestionToken
  try {
    const links = await getRelatedAccessories(combo.materialItemId)
    if (token !== accessorySuggestionToken) return
    const ids = links.map((l) => l.accessory_material.id)
    if (ids.length === 0) {
      accessorySuggestion.value = null
      return
    }
    const ctx = availabilityContext.value
    const rows = await fetchMaterialsAvailableForPeriodByIds({
      departmentId: props.departmentId,
      activityId: props.activityId || null,
      startDateIso: ctx?.startDate ?? null,
      endDateIso: ctx?.endDate ?? null,
      materialItemIds: ids,
      scope: ctx
        ? {
            source: ctx.source,
            internalScope: ctx.internalScope,
            singleDepartmentId: ctx.singleDepartmentId,
            includeGlobalJs: ctx.includeGlobalJs,
          }
        : null,
    })
    if (token !== accessorySuggestionToken) return
    // Nur verfügbares Zubehör vorschlagen (Reihenfolge wie verknüpft).
    const byId = new Map(rows.map((r) => [r.materialItemId, r]))
    const available = ids
      .map((id) => byId.get(id))
      .filter((r): r is ActivityPeriodAvailabilityMaterial => !!r && effectiveStock(r) >= 1)
    if (available.length === 0) {
      accessorySuggestion.value = null
      return
    }
    accessorySuggestion.value = {
      comboId: combo.materialItemId,
      comboName: combo.name,
      accessories: available,
    }
  } catch {
    if (token === accessorySuggestionToken) accessorySuggestion.value = null
  }
}

function addAccessoryFromSuggestion(acc: ActivityPeriodAvailabilityMaterial) {
  if (props.disabled) return
  const add = maxAddableQty(acc)
  if (add < 1) return
  emit('add-quantity', { material: acc, quantity: 1 })
}
</script>

<style scoped>
.activity-material-availability-lookup {
  width: 100%;
}

.activity-accessory-suggestion {
  margin-top: 12px;
  padding: 12px 14px;
  border: 1px solid #c7d2fe;
  background: #eef2ff;
  border-radius: 10px;
}

.activity-accessory-suggestion-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.activity-accessory-suggestion-title {
  font-size: 13px;
  font-weight: 600;
  color: #3730a3;
}

.activity-accessory-suggestion-dismiss {
  border: none;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  color: #6b7280;
  cursor: pointer;
}

.activity-accessory-suggestion-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.activity-accessory-suggestion-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.activity-accessory-suggestion-name {
  font-size: 13px;
  font-weight: 500;
  color: #1f2937;
  flex: 1 1 auto;
}

.activity-accessory-suggestion-stock {
  font-size: 12px;
  color: #4b5563;
}

.activity-accessory-suggestion-add {
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #4f46e5;
  border-radius: 6px;
  background: #4f46e5;
  color: #fff;
  cursor: pointer;
}

.activity-accessory-suggestion-add:hover:not(:disabled) {
  background: #4338ca;
}

.activity-accessory-suggestion-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.activity-material-scope-tabs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}

.activity-material-scope-tab {
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #fff;
  color: #374151;
  cursor: pointer;
  line-height: 1.2;
}

.activity-material-scope-tab:hover:not(:disabled) {
  border-color: #059669;
  color: #059669;
}

.activity-material-scope-tab[aria-selected='true'] {
  border-color: #059669;
  background: #ecfdf5;
  color: #047857;
}

.activity-material-scope-tab:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.activity-create-material-hint {
  margin: 0 0 10px;
  font-size: 12px;
}

.activity-create-material-hint-period {
  display: block;
  margin-top: 6px;
}

.activity-material-lookup {
  margin-bottom: 14px;
}

.activity-material-lookup :deep(.activity-mat-lookup-input) {
  padding-left: 35px;
}

.activity-mat-result-list {
  display: flex;
  flex-direction: column;
}

.activity-mat-result-row.combo-component-row {
  background: #fafafa;
}

.activity-mat-combo-parts-divider {
  padding: 8px 12px 4px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #6b7280;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.activity-mat-combo-part-badge {
  display: inline-block;
  margin-left: 4px;
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  color: var(--color-primary-dark, #047857);
  background: var(--color-primary-subtle-bg, #d1fae5);
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

.activity-mat-result-row--clickable {
  cursor: pointer;
}

.activity-mat-result-row--clickable:hover {
  background: #f0fdf4;
}

.activity-mat-result-row:last-child {
  border-bottom: none;
}

.activity-mat-result-row.active {
  background: #f0fdf4;
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

.text-green {
  color: #059669;
  font-weight: 600;
}

.text-red {
  color: #dc2626;
  font-weight: 600;
}

.text-muted {
  color: #6b7280;
}

.mat-type-icon {
  margin-right: 2px;
}

.mat-pack-badge {
  margin-left: 6px;
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}

.mat-dept-badge {
  margin-left: 6px;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  background: #f1f5f9;
  color: #475569;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
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

.activity-mat-quick-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.activity-mat-set-btn {
  min-width: auto;
}

.activity-mat-custom-qty-input {
  width: 3.25rem;
  padding: 5px 6px;
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  text-align: center;
}

.activity-mat-custom-qty-input:focus {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 2px rgb(5 150 105 / 15%);
}

.activity-mat-custom-qty-btn {
  min-width: 32px;
  padding-inline: 8px;
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

.mat-unavailable-badge {
  font-size: 12px;
  color: #9ca3af;
}

.mat-dropdown-loading,
.mat-dropdown-empty {
  padding: 12px 14px;
  color: #6b7280;
  font-size: 13px;
}
</style>
