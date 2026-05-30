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
    <div class="activity-material-lookup">
      <MaterialLookupInput
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
            <div
              v-for="(mat, index) in results"
              :key="mat.materialItemId"
              class="activity-mat-result-row"
              :class="{
                active: activeIndex === index,
                'already-added': isAlreadyAdded(mat.materialItemId),
              }"
              @mouseenter="setActiveIndex(index)"
            >
              <div class="activity-mat-result-info">
                <span class="activity-mat-result-name">
                  <span v-if="mat.isConsumable" class="mat-type-icon consumable" :title="t('activities.materialAvailability.titleConsumable')"
                    >🔥</span
                  >
                  <span
                    v-else-if="mat.materialType === 'physical_combo'"
                    class="mat-type-icon rental"
                    :title="t('activities.materialAvailability.titlePhysicalCombo')"
                    >{{ physicalComboIcon(mat) }}</span
                  >
                  <span v-else class="mat-type-icon rental" :title="t('activities.materialAvailability.titleRental')">📦</span>
                  {{ mat.name }}
                  <span
                    v-if="mat.sourceDepartmentId && mat.sourceDepartmentId !== departmentId && mat.sourceDepartmentName"
                    class="mat-dept-badge"
                    :title="t('activities.materialAvailability.titleSourceDept', { name: mat.sourceDepartmentName })"
                  >
                    {{ mat.sourceDepartmentName }}
                  </span>
                  <span v-if="mat.packSize && mat.packUnit" class="mat-pack-badge">
                    {{ t('activities.materialAvailability.packPerShort', { n: mat.packSize, unit: mat.packUnit }) }}
                  </span>
                  <span
                    v-if="mat.materialType === 'physical_combo'"
                    class="activity-combo-badge"
                    :title="t('activities.detail.comboPhysicalTitle')"
                  >{{ t('activities.detail.comboPhysicalShort') }}</span>
                  <span
                    v-else-if="mat.materialType === 'virtual_combo'"
                    class="activity-combo-badge activity-combo-badge--virtual"
                    :title="t('activities.detail.comboVirtualTitle')"
                  >{{ t('activities.detail.comboVirtualShort') }}</span>
                </span>
                <span class="activity-mat-result-meta">
                  <span class="activity-mat-stock">
                    <span :class="effectiveStock(mat) > 0 ? 'text-green' : 'text-red'">{{ effectiveStock(mat) }}</span>
                    <span class="text-muted">&nbsp;{{ t('activities.materialAvailability.freeLabel') }}</span>
                    <span
                      v-if="(mat.stockInStorageContainers ?? 0) > 0"
                      class="text-muted activity-mat-stock-in-crates"
                    >
                      {{
                        t('activities.materialAvailability.inStorageContainersHint', {
                          n: mat.stockInStorageContainers,
                        })
                      }}
                    </span>
                    <span
                      v-else-if="(mat.stockInPhysComboKisten ?? mat.stockInContainers ?? 0) > 0"
                      class="text-muted activity-mat-stock-in-crates"
                    >
                      {{
                        t('activities.materialAvailability.inPhysComboKistenHint', {
                          n: mat.stockInPhysComboKisten ?? mat.stockInContainers,
                        })
                      }}
                    </span>
                  </span>
                </span>
              </div>
              <div class="activity-mat-result-actions">
                <template
                  v-if="
                    effectiveStock(mat) > 0 &&
                    (!isAlreadyAdded(mat.materialItemId) || repeatAddFromSearch)
                  "
                >
                  <span v-if="isAlreadyAdded(mat.materialItemId)" class="mat-already-badge">{{
                    t('activities.materialAvailability.inList')
                  }}</span>
                  <template v-if="mat.packSize && mat.packSize > 1">
                    <button
                      v-if="canAdd(mat, mat.packSize)"
                      type="button"
                      class="activity-mat-quick-btn activity-mat-set-btn"
                      :title="
                        t('activities.materialAvailability.titleAddOnePack', {
                          unit: mat.packUnit || t('activities.materialAvailability.packUnitSet'),
                        })
                      "
                      :disabled="disabled"
                      @mousedown.prevent="addQty(mat, mat.packSize)"
                    >
                      1 {{ mat.packUnit || t('activities.materialAvailability.packUnitSet') }}
                    </button>
                    <button
                      v-if="canAdd(mat, mat.packSize * 5)"
                      type="button"
                      class="activity-mat-quick-btn activity-mat-set-btn"
                      :title="
                        t('activities.materialAvailability.titleAddFivePacks', {
                          units: mat.packUnit || t('activities.materialAvailability.packUnitSets'),
                        })
                      "
                      :disabled="disabled"
                      @mousedown.prevent="addQty(mat, mat.packSize * 5)"
                    >
                      5 {{ mat.packUnit || t('activities.materialAvailability.packUnitSets') }}
                    </button>
                    <span class="activity-mat-btn-divider" aria-hidden="true">|</span>
                  </template>
                  <button
                    v-if="canAdd(mat, 1)"
                    type="button"
                    class="activity-mat-quick-btn"
                    title="+1"
                    :disabled="disabled"
                    @mousedown.prevent="addQty(mat, 1)"
                  >
                    +1
                  </button>
                  <button
                    v-if="canAdd(mat, 5)"
                    type="button"
                    class="activity-mat-quick-btn"
                    title="+5"
                    :disabled="disabled"
                    @mousedown.prevent="addQty(mat, 5)"
                  >
                    +5
                  </button>
                  <button
                    v-if="canAdd(mat, 10)"
                    type="button"
                    class="activity-mat-quick-btn"
                    title="+10"
                    :disabled="disabled"
                    @mousedown.prevent="addQty(mat, 10)"
                  >
                    +10
                  </button>
                </template>
                <template v-else-if="isAlreadyAdded(mat.materialItemId)">
                  <span class="mat-already-badge">{{ t('activities.materialAvailability.inList') }}</span>
                </template>
                <template v-else>
                  <span class="mat-unavailable-badge">{{ t('activities.materialAvailability.unavailable') }}</span>
                </template>
              </div>
            </div>
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
      @confirm="onConfiguratorConfirm"
      @cancel="onConfiguratorCancel"
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
    savedQuantityByMaterialItemId: () => ({}),
    disabled: false,
    hintVariant: 'draft',
    searchResetKey: 0,
    repeatAddFromSearch: false,
  },
)

const { t, locale } = useI18n()

const emit = defineEmits<{
  'add-quantity': [payload: { material: ActivityPeriodAvailabilityMaterial; quantity: number; selectedOptionIds?: string[] }]
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

function draftQtyFor(materialId: string): number {
  return props.quantityByMaterialItemId[materialId] ?? 0
}

/** Nur Server-Summe; kein Fallback auf Entwurf (sonst wie früher: max wächst mit jeder Mengenänderung). */
function savedQtyFor(materialId: string): number {
  const s = props.savedQuantityByMaterialItemId[materialId]
  return typeof s === 'number' ? s : 0
}

function isAlreadyAdded(materialId: string): boolean {
  return draftQtyFor(materialId) > 0
}

function canAdd(m: ActivityPeriodAvailabilityMaterial, qty: number): boolean {
  if (props.disabled || qty < 1) return false
  const raw = effectiveStock(m)
  const draft = draftQtyFor(m.materialItemId)
  const saved = savedQtyFor(m.materialItemId)
  const adjustedFree = Math.max(0, raw + saved - draft)
  return adjustedFree >= qty
}

function addQty(m: ActivityPeriodAvailabilityMaterial, qty: number) {
  if (props.disabled) return
  const raw = effectiveStock(m)
  const draft = draftQtyFor(m.materialItemId)
  const saved = savedQtyFor(m.materialItemId)
  const adjustedFree = Math.max(0, raw + saved - draft)
  const add = Math.min(qty, adjustedFree)
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

function openConfigurator(m: ActivityPeriodAvailabilityMaterial, qty: number) {
  configuratorState.value = { material: m, quantity: qty }
}

function onConfiguratorConfirm(payload: { selectedOptionIds: string[]; quantity: number }) {
  const state = configuratorState.value
  configuratorState.value = null
  if (!state) return
  emit('add-quantity', {
    material: state.material,
    quantity: payload.quantity,
    selectedOptionIds: payload.selectedOptionIds,
  })
  matSearch.value = ''
  void loadAccessorySuggestion(state.material)
}

function onConfiguratorCancel() {
  configuratorState.value = null
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
  const raw = effectiveStock(acc)
  const draft = draftQtyFor(acc.materialItemId)
  const saved = savedQtyFor(acc.materialItemId)
  const adjustedFree = Math.max(0, raw + saved - draft)
  if (adjustedFree < 1) return
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
