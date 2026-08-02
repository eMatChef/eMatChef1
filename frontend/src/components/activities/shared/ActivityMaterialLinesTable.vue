<template>
  <div
    class="activity-material-lines-table-root"
    :class="{ 'activity-material-lines-table-root--detail-draft': variant === 'detail-draft' }"
  >
    <p v-if="availabilityError" class="activity-mat-avail-error" role="alert">{{ availabilityError }}</p>
    <p
      v-if="hasAnyAvailabilityShortage && !availabilityLoading"
      class="activity-mat-avail-shortage-banner text-muted"
      role="status"
    >
      {{ t('activities.materialLinesTable.shortageBanner') }}
    </p>
    <div
      v-if="
        variant === 'detail-draft' &&
        hasAnyAvailabilityShortage &&
        !availabilityLoading &&
        !disabled &&
        !packingStageQuantityReadonly &&
        modelValue.length > 0
      "
      class="activity-mat-reconcile-bulk"
    >
      <EButton variant="secondary" size="small" @click="applyAllSuggestedQuantities">
        {{ t('activities.materialLinesTable.applyAllBulk') }}
      </EButton>
    </div>

    <div v-if="modelValue.length > 0" class="activity-material-table-wrap">
      <table class="activity-material-table" :aria-busy="availabilityLoading">
        <thead>
          <tr>
            <th scope="col" class="activity-mat-col-name">
              <button type="button" class="activity-mat-th-btn" @click="toggleSort('name')">
                {{ t('common.material') }}
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('name') }}</span>
              </button>
            </th>
            <th v-if="showSourceAndTotals" scope="col" class="activity-mat-col-source">{{ t('activities.materialLinesTable.thSource') }}</th>
            <th scope="col" class="activity-mat-col-rest">
              <button
                type="button"
                class="activity-mat-th-btn activity-mat-th-btn--narrow"
                :title="t('activities.materialLinesTable.thRestTitle')"
                @click="toggleSort('available')"
              >
                {{ t('activities.materialLinesTable.thRest') }}
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('available') }}</span>
              </button>
            </th>
            <th scope="col" class="activity-mat-col-qty">
              <button type="button" class="activity-mat-th-btn" @click="toggleSort('quantity')">
                {{ t('activities.materialLinesTable.thQty') }}
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('quantity') }}</span>
              </button>
            </th>
            <th v-if="showSourceAndTotals && showLineTotal" scope="col" class="activity-mat-col-money">{{
              t('activities.materialLinesTable.thLine')
            }}</th>
            <th scope="col" class="activity-mat-col-warn">{{ t('activities.materialLinesTable.thHint') }}</th>
            <th scope="col" class="activity-mat-col-actions"><span class="sr-only">{{ t('common.actions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="({ row, originalIndex }, displayIndex) in orderedLines"
            :key="rowKey(row, originalIndex)"
            :class="{
              'activity-mat-row--issue': lineHasIssue(row),
              'activity-mat-row--even': displayIndex % 2 === 1,
            }"
          >
            <td class="activity-mat-cell-name">
              <div class="activity-mat-name-stack">
                <span>{{ row.material_name }}</span>
                <span
                  v-if="row.material_type === 'physical_combo'"
                  class="activity-combo-badge"
                  :title="t('activities.detail.comboPhysicalTitle')"
                  ><span aria-hidden="true">{{ COMBO_BADGE.physical }}</span> {{ t('activities.detail.comboPhysicalShort') }}</span
                >
                <span
                  v-else-if="row.material_type === 'virtual_combo'"
                  class="activity-combo-badge activity-combo-badge--virtual"
                  :title="t('activities.detail.comboVirtualTitle')"
                  ><span aria-hidden="true">{{ COMBO_BADGE.virtual }}</span> {{ t('activities.detail.comboVirtualShort') }}</span
                >
                <span v-if="row.is_js_material" class="activity-mat-js-tag">J&amp;S</span>
                <span
                  v-if="row.is_replenishment"
                  class="activity-mat-replenishment-tag"
                  :title="t('activities.materialLinesTable.replenishmentBadge')"
                >
                  {{ t('activities.materialLinesTable.replenishmentBadge') }}
                </span>
                <button
                  v-if="row.is_container && row.material_type !== 'physical_combo'"
                  type="button"
                  class="activity-mat-container-tag"
                  disabled
                  :title="t('activities.materialLinesTable.containerTagTitle')"
                >
                  {{ t('activities.materialLinesTable.containerTag') }}
                </button>
                <span
                  v-if="row.tracking_type === 'serialized' || row.tracking_type === 'bulk'"
                  class="activity-mat-tracking-tag text-muted"
                  :title="row.tracking_type === 'serialized' ? t('activities.materialLinesTable.trackSerializedTitle') : t('activities.materialLinesTable.trackBulkTitle')"
                >
                  {{ row.tracking_type === 'serialized' ? t('activities.materialLinesTable.trackSerialized') : t('activities.materialLinesTable.trackBulk') }}
                </span>
                <div v-if="row.linked_container_label" class="activity-mat-combo-kiste text-muted">
                  {{ row.linked_container_label }}
                </div>
                <div
                  v-if="row.material_type === 'physical_combo'"
                  class="activity-mat-combo-content-dropdown"
                >
                  <button
                    type="button"
                    class="activity-mat-combo-content-toggle"
                    :aria-expanded="isPhysicalComboContentOpen(row, originalIndex)"
                    :aria-label="t('activities.materialLinesTable.comboContentToggleAria')"
                    @click="togglePhysicalComboContent(row, originalIndex)"
                  >
                    <span class="activity-mat-combo-content-chev" aria-hidden="true">{{
                      isPhysicalComboContentOpen(row, originalIndex) ? '▼' : '▶'
                    }}</span>
                    {{ t('activities.materialLinesTable.comboContentToggle') }}
                  </button>
                  <div
                    v-show="isPhysicalComboContentOpen(row, originalIndex)"
                    class="activity-mat-combo-content-body"
                  >
                    <p v-if="physicalComboContentLoading(row)" class="text-muted activity-mat-combo-content-empty">…</p>
                    <p
                      v-else-if="physicalComboContentLines(row).length === 0"
                      class="text-muted activity-mat-combo-content-empty"
                    >
                      {{ t('activities.materialLinesTable.comboContentEmpty') }}
                    </p>
                    <ul v-else class="activity-mat-combo-content-list">
                      <li
                        v-for="line in physicalComboContentLines(row)"
                        :key="line.id"
                        :class="{ 'activity-mat-combo-content-self': line.selfProvided }"
                      >
                        {{ line.totalQty }}× {{ line.name }}
                        <span v-if="line.selfProvided" class="text-muted">
                          · {{ t('activities.detail.comboSetSelfProvided') }}
                        </span>
                      </li>
                    </ul>
                  </div>
                </div>
                <!-- Virtuelle Kombo: Pack-Vorgabe + Set-Inhalt nur bei «zusammen» -->
                <div
                  v-if="row.material_type === 'virtual_combo'"
                  class="activity-mat-set-content"
                >
                  <div
                    v-if="virtualComboPackModeEditable"
                    class="activity-mat-pack-mode-edit"
                  >
                    <span class="activity-mat-pack-mode-edit-label text-muted">
                      {{ t('components.comboConfigurator.packModeTitle') }}
                    </span>
                    <label class="activity-mat-pack-mode-option">
                      <input
                        type="radio"
                        :name="`pack-mode-${rowKey(row, originalIndex)}`"
                        value="together"
                        :checked="effectiveVirtualComboPackMode(row) === 'together'"
                        :disabled="disabled"
                        @change="setVirtualComboPackMode(originalIndex, 'together')"
                      />
                      <span>{{ t('components.comboConfigurator.packModeTogether', { name: row.material_name }) }}</span>
                    </label>
                    <label class="activity-mat-pack-mode-option">
                      <input
                        type="radio"
                        :name="`pack-mode-${rowKey(row, originalIndex)}`"
                        value="loose"
                        :checked="effectiveVirtualComboPackMode(row) === 'loose'"
                        :disabled="disabled"
                        @change="setVirtualComboPackMode(originalIndex, 'loose')"
                      />
                      <span>{{ t('components.comboConfigurator.packModeLoose') }}</span>
                    </label>
                  </div>
                  <span
                    v-else-if="virtualComboPackModeLabel(row)"
                    class="activity-mat-pack-mode-hint text-muted"
                  >
                    {{ virtualComboPackModeLabel(row) }}
                  </span>
                  <template v-if="effectiveVirtualComboPackMode(row) === 'together' && comboSetContent(row)">
                    <div class="activity-mat-combo-content-dropdown">
                      <button
                        type="button"
                        class="activity-mat-combo-content-toggle"
                        :aria-expanded="isVirtualComboContentOpen(row, originalIndex)"
                        :aria-label="t('activities.materialLinesTable.comboContentToggleAria')"
                        @click="toggleVirtualComboContent(row, originalIndex)"
                      >
                        <span class="activity-mat-combo-content-chev" aria-hidden="true">{{
                          isVirtualComboContentOpen(row, originalIndex) ? '▼' : '▶'
                        }}</span>
                        {{ t('activities.materialLinesTable.comboContentToggle') }}
                      </button>
                      <div
                        v-show="isVirtualComboContentOpen(row, originalIndex)"
                        class="activity-mat-combo-content-body"
                      >
                        <ul class="activity-mat-combo-content-list">
                          <li
                            v-for="c in comboSetContent(row)!.resolved"
                            :key="`r-${c.component_material_id}`"
                          >
                            {{ c.total_qty }}× {{ c.name }}
                          </li>
                          <li
                            v-for="c in comboSetContent(row)!.selfProvided"
                            :key="`s-${c.component_material_id}`"
                            class="activity-mat-combo-content-self"
                          >
                            {{ c.total_qty }}× {{ c.name }}
                            <span class="text-muted">· {{ t('activities.detail.comboSetSelfProvided') }}</span>
                            <span
                              v-if="row.config_snapshot?.self_provided_acknowledged"
                              class="activity-mat-selfprovided-ack-badge"
                            >
                              {{ formatSelfProvidedAckBadge(row.config_snapshot) }}
                            </span>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </template>
                  <p
                    v-else-if="effectiveVirtualComboPackMode(row) === 'loose'"
                    class="activity-mat-pack-mode-hint text-muted"
                  >
                    {{ t('activities.detail.virtualComboLoosePartsHint') }}
                  </p>
                </div>
              </div>
            </td>
            <td v-if="showSourceAndTotals" class="activity-mat-cell-source text-muted">
              {{ row.source_department_name || t('activities.wizard.form.summaryEmpty') }}
            </td>
            <td class="activity-mat-cell-num activity-mat-cell-rest">
              <div v-if="variant === 'detail-draft'" class="activity-mat-rest-stack">
                <span v-if="availabilityLoading" class="text-muted">…</span>
                <template v-else>
                  <span class="activity-mat-rest-value">{{ formatRestCell(row) }}</span>
                  <template v-if="!disabled && !qtyRowLocked(row) && shortageForRow(row) > 0">
                    <EButton
                      variant="secondary"
                      size="x-small"
                      class="activity-mat-rest-adjust"
                      @click="applySuggestedForLine(originalIndex)"
                    >
                      {{ t('activities.materialLinesTable.adjust') }}
                    </EButton>
                  </template>
                </template>
              </div>
              <template v-else>
                <span v-if="availabilityLoading" class="text-muted">…</span>
                <span v-else>{{ formatRestCell(row) }}</span>
              </template>
            </td>
            <td class="activity-mat-cell-qty">
              <div
                v-if="qtyRowLocked(row)"
                class="activity-material-line-qty-block activity-material-line-qty-block--packing-locked"
              >
                <span class="activity-mat-qty-readonly" :title="t('activities.materialLinesTable.qtyReadonlyTitle')">{{
                  row.quantity
                }}</span>
                <span class="activity-mat-pack-hint text-muted">{{
                  qtyLockedHint(row)
                }}</span>
              </div>
              <div
                v-else
                class="activity-material-line-qty-block"
                :class="{ 'activity-material-line-qty-block--detail': variant === 'detail-draft' }"
              >
                <div
                  v-if="row.pack_size && row.pack_size > 1"
                  class="activity-material-line-row activity-material-line-row--pack"
                >
                  <button
                    v-if="canDecrementLine(row, row.pack_size)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn activity-mat-quick-btn--dec"
                    :title="'−1 ' + (row.pack_unit || t('activities.materialAvailability.packUnitSet'))"
                    :aria-label="t('activities.materialLinesTable.ariaDecPack', { unit: row.pack_unit || t('activities.materialAvailability.packUnitSet') })"
                    :disabled="disabled"
                    @mousedown.prevent="decrementLine(originalIndex, row.pack_size)"
                  >
                    −1 {{ row.pack_unit || t('activities.materialAvailability.packUnitSet') }}
                  </button>
                  <button
                    v-if="canDecrementLine(row, row.pack_size * 5)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn activity-mat-quick-btn--dec"
                    :title="'−5 ' + (row.pack_unit || t('activities.materialAvailability.packUnitSets'))"
                    :aria-label="t('activities.materialLinesTable.ariaDecPacks5', { units: row.pack_unit || t('activities.materialAvailability.packUnitSets') })"
                    :disabled="disabled"
                    @mousedown.prevent="decrementLine(originalIndex, row.pack_size * 5)"
                  >
                    −5 {{ row.pack_unit || t('activities.materialAvailability.packUnitSets') }}
                  </button>
                  <span v-if="showPackDecDivider(row)" class="activity-mat-btn-divider" aria-hidden="true">|</span>
                  <button
                    v-if="canIncrementLine(row, row.pack_size)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn"
                    :title="'1 ' + (row.pack_unit || t('activities.materialAvailability.packUnitSet'))"
                    :aria-label="t('activities.materialLinesTable.ariaIncPack', { unit: row.pack_unit || t('activities.materialAvailability.packUnitSet') })"
                    :disabled="disabled"
                    @mousedown.prevent="incrementLine(originalIndex, row.pack_size)"
                  >
                    1 {{ row.pack_unit || t('activities.materialAvailability.packUnitSet') }}
                  </button>
                  <button
                    v-if="canIncrementLine(row, row.pack_size * 5)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn"
                    :title="'5 ' + (row.pack_unit || t('activities.materialAvailability.packUnitSets'))"
                    :aria-label="t('activities.materialLinesTable.ariaIncPacks5', { units: row.pack_unit || t('activities.materialAvailability.packUnitSets') })"
                    :disabled="disabled"
                    @mousedown.prevent="incrementLine(originalIndex, row.pack_size * 5)"
                  >
                    5 {{ row.pack_unit || t('activities.materialAvailability.packUnitSets') }}
                  </button>
                </div>
                <div class="activity-material-line-row activity-material-line-row--quick">
                  <label class="activity-material-qty">
                    <span class="sr-only">{{ t('activities.materialLinesTable.srOnlyQty') }}</span>
                    <input
                      type="number"
                      :min="minQtyForRow(row)"
                      class="form-input form-input--qty"
                      :value="row.quantity"
                      :disabled="disabled"
                      @change="onQtyChange(originalIndex, $event)"
                    />
                  </label>
                  <div class="activity-material-line-btns">
                    <button
                      v-if="canDecrementLine(row, 1)"
                      type="button"
                      class="activity-mat-quick-btn activity-mat-quick-btn--dec"
                      title="−1"
                      :disabled="disabled"
                      @mousedown.prevent="decrementLine(originalIndex, 1)"
                    >
                      −1
                    </button>
                    <button
                      v-if="canDecrementLine(row, 5)"
                      type="button"
                      class="activity-mat-quick-btn activity-mat-quick-btn--dec"
                      title="−5"
                      :disabled="disabled"
                      @mousedown.prevent="decrementLine(originalIndex, 5)"
                    >
                      −5
                    </button>
                    <button
                      v-if="canDecrementLine(row, 10)"
                      type="button"
                      class="activity-mat-quick-btn activity-mat-quick-btn--dec"
                      title="−10"
                      :disabled="disabled"
                      @mousedown.prevent="decrementLine(originalIndex, 10)"
                    >
                      −10
                    </button>
                    <span v-if="showQuickDecDivider(row)" class="activity-mat-btn-divider" aria-hidden="true">|</span>
                    <button
                      v-if="canIncrementLine(row, 1)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+1"
                      :disabled="disabled"
                      @mousedown.prevent="incrementLine(originalIndex, 1)"
                    >
                      +1
                    </button>
                    <button
                      v-if="canIncrementLine(row, 5)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+5"
                      :disabled="disabled"
                      @mousedown.prevent="incrementLine(originalIndex, 5)"
                    >
                      +5
                    </button>
                    <button
                      v-if="canIncrementLine(row, 10)"
                      type="button"
                      class="activity-mat-quick-btn"
                      title="+10"
                      :disabled="disabled"
                      @mousedown.prevent="incrementLine(originalIndex, 10)"
                    >
                      +10
                    </button>
                  </div>
                </div>
              </div>
            </td>
            <td v-if="showSourceAndTotals && showLineTotal" class="activity-mat-cell-money">
              <span v-if="row.line_total != null">{{ formatMoneyCell(row.line_total) }}</span>
              <span v-else>{{ t('activities.wizard.form.summaryEmpty') }}</span>
            </td>
            <td class="activity-mat-cell-warn">
              <span
                v-if="comboFloorHint(row)"
                class="activity-mat-floor-hint text-muted"
              >
                {{ comboFloorHint(row) }}
              </span>
              <span
                v-if="!availabilityLoading && lineHasIssue(row) && variant !== 'detail-draft'"
                class="activity-mat-warn-badge"
                :title="t('activities.materialLinesTable.warnOverRestTitle')"
              >
                {{ t('activities.materialLinesTable.onlyNFree', { n: maxQtyForRow(row) ?? 0 }) }}
              </span>
            </td>
            <td class="activity-mat-cell-remove">
              <template v-if="!canRemoveLine(row)">
                <span
                  class="activity-mat-remove-na text-muted"
                  :title="removeBlockedTitle(row)"
                  >{{ t('activities.wizard.form.summaryEmpty') }}</span
                >
              </template>
              <template v-else>
                <button
                  v-if="variant === 'wizard'"
                  type="button"
                  class="activity-material-remove"
                  :title="isVirtualComboParentRow(row) ? t('activities.materialLinesTable.virtualComboRemoveTitle') : t('activities.materialLinesTable.removeLineTitle')"
                  :aria-label="t('activities.materialLinesTable.removeLineAria', { name: row.material_name })"
                  :disabled="disabled"
                  @click="emitRemove(originalIndex)"
                >
                  ×
                </button>
                <EButton
                  v-else
                  variant="secondary"
                  size="x-small"
                  class="activity-mat-remove-text"
                  :disabled="disabled || removeBusyFor(row)"
                  @click="emitRemove(originalIndex)"
                >
                  {{ t('common.remove') }}
                </EButton>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="text-muted activity-empty-lines">{{ emptyTextDisplay }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getComboComponents, type ComboComponent } from '@/api/materials'
import { fetchMaterialsAvailableForPeriodByIds } from '@/api/materialAvailabilityPeriod'
import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'
import { materialLookupContextForScopeTab, type MaterialScopeTab } from './activityMaterialAvailabilityScope'
import type { MaterialLookupAvailabilityContext } from '@/composables/useMaterialLookup'
import { COMBO_BADGE } from '@/utils/comboDisplay'
import {
  canRemoveStandaloneLine,
  isVirtualComboChildLine,
  minStandaloneQtyForLine,
  reservedQuantityByMaterialItemId,
  type VirtualComboFloorOptions,
  type VirtualComboLineContext,
} from '@/utils/virtualComboMaterial'
import {
  materialDemandShortage,
  periodCapacityForMaterial,
  restCapacityForRow,
  restDisplayDemandForRow,
  totalMaterialDemandInLines,
} from '@/utils/materialPeriodDemand'
import { EButton } from '@/components/form/base'

const props = withDefaults(
  defineProps<{
    modelValue: ActivityMaterialLine[]
    departmentId: string
    activityId?: string | null
    planningStartAt?: Date | null
    planningEndAt?: Date | null
    /** Wizard: kompakt; Detail-Entwurf: Quelle + ggf. Zeilenpreis */
    variant?: 'wizard' | 'detail-draft'
    showSourceAndTotals?: boolean
    showLineTotal?: boolean
    disabled?: boolean
    /** z. B. Entfernen läuft — Zeile deaktivieren */
    removingItemId?: string | null
    emptyText?: string
    /** Wie ActivityMaterialAvailabilityLookup (Eigen …), sonst weichen Frei-Zahlen ab */
    materialScopeTab?: MaterialScopeTab
    materialScopeHasPartners?: boolean
    materialScopeSinglePartnerId?: string | null
    /** Status packing + Nachbuch: Mengen/Entfernen nur Packliste / Packlisten-Hinzufügen */
    packingStageQuantityReadonly?: boolean
    /** Kind-Zeilen aus API (Detail): Mengen pro Material für Kombo-Floor */
    childQuantityByMaterialItemId?: Record<string, number>
    /** Gespeicherte Summe pro Material (alle activity_items inkl. versteckter Kinder) */
    savedQuantityByMaterialItemId?: Record<string, number>
    /** Virt. Kombo: pack_mode nachträglich änderbar (Detail vor «gepackt») */
    virtualComboPackModeEditable?: boolean
  }>(),
  {
    activityId: null,
    planningStartAt: null,
    planningEndAt: null,
    variant: 'wizard',
    showSourceAndTotals: false,
    showLineTotal: false,
    disabled: false,
    removingItemId: null,
    materialScopeTab: 'own',
    materialScopeHasPartners: false,
    materialScopeSinglePartnerId: null,
    packingStageQuantityReadonly: false,
    childQuantityByMaterialItemId: () => ({}),
    savedQuantityByMaterialItemId: () => ({}),
    virtualComboPackModeEditable: false,
  },
)

const { t, locale } = useI18n()

const emptyTextDisplay = computed(() => props.emptyText ?? t('activities.materialLinesTable.defaultEmpty'))

const emit = defineEmits<{
  'update:modelValue': [value: ActivityMaterialLine[]]
  'remove-line': [payload: { line: ActivityMaterialLine; index: number }]
  'pack-mode-change': [payload: { line: ActivityMaterialLine; mode: 'together' | 'loose' }]
}>()

const minQty = computed(() => (props.variant === 'wizard' ? 1 : 0))

const floorOptions = computed((): VirtualComboFloorOptions => ({
  childQuantityByMaterialItemId: props.childQuantityByMaterialItemId,
  treatComboFloorAsChildCoverage: props.variant === 'wizard',
  baseMinQty: minQty.value,
}))

function isVirtualComboParentRow(row: ActivityMaterialLine): boolean {
  return row.material_type === 'virtual_combo'
}

function isVirtualComboLooseChildRow(row: ActivityMaterialLine): boolean {
  return isVirtualComboChildLine(row)
}

function minQtyForRow(row: ActivityMaterialLine): number {
  if (isVirtualComboParentRow(row) || isVirtualComboLooseChildRow(row)) {
    return Math.max(1, row.quantity)
  }
  if (row.material_type === 'physical_combo') {
    return minQty.value
  }
  return minStandaloneQtyForLine(row, props.modelValue, floorOptions.value)
}

function comboFloorHint(row: ActivityMaterialLine): string | null {
  if (row.material_type === 'physical_combo' || row.material_type === 'virtual_combo') return null
  const minQ = minQtyForRow(row)
  if (minQ <= minQty.value) return null
  return t('activities.materialLinesTable.comboFloorHint', { min: minQ })
}

function canRemoveLine(row: ActivityMaterialLine): boolean {
  if (props.disabled || removeBusyFor(row)) return false
  if (isVirtualComboLooseChildRow(row)) return false
  if (isVirtualComboParentRow(row)) {
    return !props.packingStageQuantityReadonly
  }
  if (qtyRowLocked(row)) return false
  if (row.material_type === 'physical_combo') return true
  return canRemoveStandaloneLine(row, props.modelValue, floorOptions.value)
}

function safeDateToIso(d: Date | null | undefined): string | null {
  if (!d) return null
  if (Number.isNaN(d.getTime())) return null
  return d.toISOString()
}

const planningStartIso = computed(() => safeDateToIso(props.planningStartAt ?? null))
const planningEndIso = computed(() => safeDateToIso(props.planningEndAt ?? null))

/** Gleiche source/internalScope wie die Materialsuche */
const scopeForAvailabilityFetch = computed(() => {
  const base: Pick<
    MaterialLookupAvailabilityContext,
    'departmentId' | 'activityId' | 'startDate' | 'endDate' | 'limit'
  > = {
    departmentId: props.departmentId,
    limit: 50,
  }
  if (props.activityId) base.activityId = props.activityId
  if (planningStartIso.value && planningEndIso.value) {
    base.startDate = planningStartIso.value
    base.endDate = planningEndIso.value
  }
  const ctx = materialLookupContextForScopeTab(
    base,
    props.materialScopeTab ?? 'own',
    props.materialScopeHasPartners ?? false,
    props.materialScopeSinglePartnerId ?? null,
  )
  return {
    source: ctx.source,
    internalScope: ctx.internalScope,
    singleDepartmentId: ctx.singleDepartmentId,
    includeGlobalJs: ctx.includeGlobalJs,
  }
})

const availabilityMap = ref<Record<string, number>>({})
/** Nur erstes Laden: Zellen „…“, danach stille Hintergrund-Aktualisierung (kein Layout-Springen) */
const availabilityLoading = ref(false)
const availabilityFirstFetchDone = ref(false)
const availabilityError = ref<string | null>(null)

type SortCol = 'name' | 'available' | 'quantity'
const sortCol = ref<SortCol>(props.variant === 'detail-draft' ? 'name' : 'available')
const sortDir = ref<'asc' | 'desc'>('asc')
/** Detail-Entwurf: stabile Reihenfolge bis der Nutzer explizit sortiert. */
const userChoseSort = ref(false)

function toggleSort(col: SortCol) {
  userChoseSort.value = true
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortCol.value = col
    sortDir.value = col === 'name' ? 'asc' : 'asc'
  }
}

function sortGlyph(col: SortCol): string {
  if (sortCol.value !== col) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

function rowKey(row: ActivityMaterialLine, originalIndex: number): string {
  const base = row.activity_item_id ?? row.material_item_id
  const parent = row.parent_activity_item_id ?? ''
  return parent ? `${base}-${parent}-${originalIndex}` : `${base}-${originalIndex}`
}

/** Set-Inhalt „wie Kiste" einer gebuchten virtuellen Kombo (aus config_snapshot). */
function comboSetContent(row: ActivityMaterialLine): {
  resolved: NonNullable<NonNullable<ActivityMaterialLine['config_snapshot']>['resolved_components']>
  selfProvided: NonNullable<NonNullable<ActivityMaterialLine['config_snapshot']>['self_provided']>
} | null {
  if (row.material_type !== 'virtual_combo') return null
  const snap = row.config_snapshot
  const resolved = snap?.resolved_components ?? []
  const selfProvided = snap?.self_provided ?? []
  if (resolved.length === 0 && selfProvided.length === 0) return null
  return { resolved, selfProvided }
}

function effectiveVirtualComboPackMode(row: ActivityMaterialLine): 'together' | 'loose' {
  const mode = row.config_snapshot?.pack_mode ?? row.pack_mode
  return mode === 'together' ? 'together' : 'loose'
}

function setVirtualComboPackMode(originalIndex: number, mode: 'together' | 'loose') {
  const row = props.modelValue[originalIndex]
  if (!row || row.material_type !== 'virtual_combo' || props.disabled) return
  if (effectiveVirtualComboPackMode(row) === mode) return
  const snap = { ...(row.config_snapshot ?? {}), pack_mode: mode }
  const lines = [...props.modelValue]
  lines[originalIndex] = {
    ...lines[originalIndex],
    pack_mode: mode,
    config_snapshot: snap as NonNullable<ActivityMaterialLine['config_snapshot']>,
  }
  emit('update:modelValue', lines)
  emit('pack-mode-change', { line: lines[originalIndex], mode })
}

function virtualComboPackModeLabel(row: ActivityMaterialLine): string | null {
  if (row.material_type !== 'virtual_combo') return null
  const mode = row.config_snapshot?.pack_mode ?? row.pack_mode
  if (mode === 'together') {
    return t('activities.detail.comboPackModeTogetherHint', { name: row.material_name })
  }
  if (mode === 'loose') {
    return t('activities.detail.comboPackModeLooseHint')
  }
  return null
}

function formatSelfProvidedAckBadge(snap: NonNullable<ActivityMaterialLine['config_snapshot']>): string {
  const at = snap.self_provided_acknowledged_at
  if (!at) return t('activities.detail.comboSelfProvidedAcknowledged')
  try {
    const d = new Date(at)
    if (Number.isNaN(d.getTime())) return t('activities.detail.comboSelfProvidedAcknowledged')
    return t('activities.detail.comboSelfProvidedAcknowledgedAt', {
      date: d.toLocaleString(locale.value),
    })
  } catch {
    return t('activities.detail.comboSelfProvidedAcknowledged')
  }
}

interface PhysicalComboContentLine {
  id: string
  name: string
  totalQty: number
  selfProvided: boolean
}

const comboComponentsByMaterialId = ref<Record<string, ComboComponent[]>>({})
const comboComponentsLoadStarted = ref<Record<string, boolean>>({})
const physicalComboContentOpenKeys = ref<Set<string>>(new Set())
const virtualComboContentOpenKeys = ref<Set<string>>(new Set())

function physicalComboContentKey(row: ActivityMaterialLine, originalIndex: number): string {
  return rowKey(row, originalIndex)
}

function isPhysicalComboContentOpen(row: ActivityMaterialLine, originalIndex: number): boolean {
  return physicalComboContentOpenKeys.value.has(physicalComboContentKey(row, originalIndex))
}

function togglePhysicalComboContent(row: ActivityMaterialLine, originalIndex: number): void {
  const key = physicalComboContentKey(row, originalIndex)
  const next = new Set(physicalComboContentOpenKeys.value)
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
    void ensureComboComponentsLoaded(row.material_item_id)
  }
  physicalComboContentOpenKeys.value = next
}

function virtualComboContentKey(row: ActivityMaterialLine, originalIndex: number): string {
  return `vc-${rowKey(row, originalIndex)}`
}

function isVirtualComboContentOpen(row: ActivityMaterialLine, originalIndex: number): boolean {
  return virtualComboContentOpenKeys.value.has(virtualComboContentKey(row, originalIndex))
}

function toggleVirtualComboContent(row: ActivityMaterialLine, originalIndex: number): void {
  const key = virtualComboContentKey(row, originalIndex)
  const next = new Set(virtualComboContentOpenKeys.value)
  if (next.has(key)) {
    next.delete(key)
  } else {
    next.add(key)
  }
  virtualComboContentOpenKeys.value = next
}

function physicalComboContentLoading(row: ActivityMaterialLine): boolean {
  const id = row.material_item_id
  return !!comboComponentsLoadStarted.value[id] && comboComponentsByMaterialId.value[id] === undefined
}

async function ensureComboComponentsLoaded(materialItemId: string): Promise<void> {
  if (comboComponentsByMaterialId.value[materialItemId] !== undefined) return
  if (comboComponentsLoadStarted.value[materialItemId]) return
  comboComponentsLoadStarted.value = { ...comboComponentsLoadStarted.value, [materialItemId]: true }
  try {
    const list = await getComboComponents(materialItemId)
    comboComponentsByMaterialId.value = { ...comboComponentsByMaterialId.value, [materialItemId]: list }
  } catch {
    comboComponentsByMaterialId.value = { ...comboComponentsByMaterialId.value, [materialItemId]: [] }
  }
}

function physicalComboContentLines(row: ActivityMaterialLine): PhysicalComboContentLine[] {
  const snap = row.config_snapshot
  const resolved = snap?.resolved_components ?? []
  const selfProvided = snap?.self_provided ?? []
  if (resolved.length > 0 || selfProvided.length > 0) {
    const lines: PhysicalComboContentLine[] = []
    for (const c of resolved) {
      lines.push({
        id: `r-${c.component_material_id}`,
        name: c.name,
        totalQty: c.total_qty,
        selfProvided: false,
      })
    }
    for (const c of selfProvided) {
      lines.push({
        id: `s-${c.component_material_id}`,
        name: c.name,
        totalQty: c.total_qty,
        selfProvided: true,
      })
    }
    return lines
  }

  const comboQty = Math.max(1, row.quantity)
  const components = comboComponentsByMaterialId.value[row.material_item_id] ?? []
  return components.map((cc) => ({
    id: cc.id,
    name: (cc.component_material?.name ?? '').trim() || row.material_name,
    totalQty: comboQty * Math.max(0, Math.floor(Number(cc.qty)) || 0),
    selfProvided: cc.component_source === 'self_provided',
  }))
}

watch(
  () =>
    props.modelValue
      .filter((r) => r.material_type === 'physical_combo')
      .map((r) => r.material_item_id),
  (ids) => {
    for (const id of [...new Set(ids)]) {
      void ensureComboComponentsLoaded(id)
    }
  },
  { immediate: true },
)

function removeBusyFor(row: ActivityMaterialLine): boolean {
  return !!(row.activity_item_id && props.removingItemId === row.activity_item_id)
}

function formatMoneyCell(v: string | number): string {
  const n = typeof v === 'string' ? parseFloat(v) : v
  if (Number.isNaN(n)) return String(v)
  return `CHF ${n.toFixed(2)}`
}

/** Rohwert aus API: freie Menge (Bestand − Reservierungen laut DB, Zeitraum) */
function rawFreePoolFromApi(materialItemId: string): number | undefined {
  const v = availabilityMap.value[materialItemId]
  if (typeof v === 'number') return v
  return undefined
}

/**
 * Summen pro Material — Entwurf vs. gespeichert (Detail).
 * Nur explizite saved_quantity zählen; NICHT row.quantity als Ersatz (Wizard sonst savedSum=draftSum → max wächst mit +5).
 */
function draftAndSavedSumsForMaterial(materialItemId: string): { draftSum: number; savedSum: number } {
  const draftSum = totalMaterialDemandInLines(
    materialItemId,
    props.modelValue,
    props.childQuantityByMaterialItemId,
  )
  const savedSum =
    reservedQuantityByMaterialItemId(props.modelValue as VirtualComboLineContext[], (row) => {
      const line = row as ActivityMaterialLine
      if (typeof line.saved_quantity === 'number') return line.saved_quantity
      return line.quantity ?? 0
    })[materialItemId] ?? 0
  return { draftSum, savedSum }
}

/**
 * Freie Menge nach Abzug ungespeicherter Mengenänderungen (Detail-Entwurf).
 * API nutzt DB-Reservierungen; Entwurf kann höher/niedriger sein.
 */
function adjustedFreePoolForMaterial(materialItemId: string): number | undefined {
  const raw = rawFreePoolFromApi(materialItemId)
  if (raw === undefined) {
    const anyRow = props.modelValue.find((r) => r.material_item_id === materialItemId)
    if (anyRow && typeof anyRow.period_availability_cap === 'number') {
      const { draftSum, savedSum } = draftAndSavedSumsForMaterial(materialItemId)
      return Math.max(0, anyRow.period_availability_cap + savedSum - draftSum)
    }
    return undefined
  }
  const { draftSum, savedSum } = draftAndSavedSumsForMaterial(materialItemId)
  return Math.max(0, raw + savedSum - draftSum)
}

function savedSumForMaterial(materialItemId: string): number {
  const fromProp = props.savedQuantityByMaterialItemId[materialItemId]
  if (typeof fromProp === 'number') return fromProp
  let savedSum = 0
  for (const row of props.modelValue) {
    if (row.material_item_id !== materialItemId) continue
    if (typeof row.saved_quantity === 'number') {
      savedSum += row.saved_quantity
    }
  }
  return savedSum
}

function totalDemandForMaterial(materialItemId: string): number {
  return totalMaterialDemandInLines(
    materialItemId,
    props.modelValue,
    props.childQuantityByMaterialItemId,
  )
}

function periodCapacityForMaterialRow(materialItemId: string): number | undefined {
  const raw = rawFreePoolFromApi(materialItemId)
  const excludeCurrent = !!props.activityId
  if (raw !== undefined) {
    return periodCapacityForMaterial(materialItemId, raw, savedSumForMaterial(materialItemId), {
      excludeCurrentActivity: excludeCurrent,
    })
  }
  const anyRow = props.modelValue.find((r) => r.material_item_id === materialItemId)
  if (anyRow && typeof anyRow.period_availability_cap === 'number') {
    return periodCapacityForMaterial(
      materialItemId,
      anyRow.period_availability_cap,
      savedSumForMaterial(materialItemId),
      { excludeCurrentActivity: excludeCurrent },
    )
  }
  return undefined
}

function materialShortageFor(materialItemId: string): number {
  return materialDemandShortage(
    totalDemandForMaterial(materialItemId),
    periodCapacityForMaterialRow(materialItemId),
  )
}

/** Max. buchbare Menge im Zeitraum (API availableForPeriod; eigene Aktivität bereits ausgeschlossen). */
function maxQtyForRow(row: ActivityMaterialLine): number | undefined {
  if (isVirtualComboParentRow(row)) return undefined
  const capacity = periodCapacityForMaterialRow(row.material_item_id)
  if (capacity === undefined) return undefined
  const totalDemand = totalDemandForMaterial(row.material_item_id)
  const otherDemand = totalDemand - row.quantity
  return Math.max(0, capacity - otherDemand)
}

function shortageForRow(row: ActivityMaterialLine): number {
  if (isReplenishmentLine(row)) return 0
  if (isVirtualComboParentRow(row)) return 0
  const capacity = restCapacityForRow(
    row,
    props.modelValue,
    periodCapacityForMaterialRow(row.material_item_id),
    props.childQuantityByMaterialItemId,
  )
  if (capacity === undefined) return 0
  return materialDemandShortage(row.quantity, capacity)
}

/**
 * Nur reine Behälter-Zeilen (ohne Phys.-Kombi): Menge/Entfernen über Packliste.
 * Physische Kombination mit Behälter-Badge bleibt in der Materialliste editierbar.
 */
function lineLockedForPackListOnly(row: ActivityMaterialLine): boolean {
  return !!row.is_container && row.material_type !== 'physical_combo'
}

function isReplenishmentLine(row: ActivityMaterialLine): boolean {
  return row.is_replenishment === true
}

function qtyRowLocked(row: ActivityMaterialLine): boolean {
  if (isReplenishmentLine(row)) return true
  if (isVirtualComboParentRow(row) || isVirtualComboLooseChildRow(row)) return true
  return lineLockedForPackListOnly(row) || props.packingStageQuantityReadonly === true
}

function qtyLockedHint(row: ActivityMaterialLine): string {
  if (isReplenishmentLine(row)) {
    return t('activities.materialLinesTable.replenishmentQtyHint')
  }
  if (isVirtualComboLooseChildRow(row)) {
    return t('activities.materialLinesTable.virtualComboChildQtyHint')
  }
  if (isVirtualComboParentRow(row)) {
    return t('activities.materialLinesTable.virtualComboQtyHint')
  }
  if (lineLockedForPackListOnly(row)) {
    return t('activities.materialLinesTable.packList')
  }
  return t('activities.materialLinesTable.qtyPackingReadonlyHint')
}

function qtyLockedRemoveTitle(row: ActivityMaterialLine): string {
  if (isReplenishmentLine(row)) {
    return t('activities.materialLinesTable.replenishmentRemoveTitle')
  }
  if (isVirtualComboLooseChildRow(row)) {
    return t('activities.materialLinesTable.virtualComboChildRemoveTitle')
  }
  if (isVirtualComboParentRow(row)) {
    return t('activities.materialLinesTable.virtualComboRemoveBlockedTitle')
  }
  if (lineLockedForPackListOnly(row)) {
    return t('activities.materialLinesTable.packListRemoveTitle')
  }
  return t('activities.materialLinesTable.qtyPackingRemoveTitle')
}

function removeBlockedTitle(row: ActivityMaterialLine): string {
  if (isVirtualComboLooseChildRow(row)) {
    return t('activities.materialLinesTable.virtualComboChildRemoveTitle')
  }
  if (isVirtualComboParentRow(row)) {
    return t('activities.materialLinesTable.virtualComboRemoveBlockedTitle')
  }
  if (qtyRowLocked(row)) return qtyLockedRemoveTitle(row)
  const minQ = minQtyForRow(row)
  if (minQ > minQty.value) {
    return t('activities.materialLinesTable.comboFloorRemoveTitle', { min: minQ })
  }
  return t('activities.materialLinesTable.removeLineTitle')
}

function lineHasIssue(row: ActivityMaterialLine): boolean {
  if (availabilityLoading.value) return false
  if (qtyRowLocked(row)) return false
  return shortageForRow(row) > 0
}

/** Für Sortierung / +Buttons: verbleibender Spielraum bis zum erlaubten Maximum */
function remainingAfterSelection(row: ActivityMaterialLine): number | null {
  const max = maxQtyForRow(row)
  if (max === undefined) return null
  return Math.max(0, max - row.quantity)
}

/** Anzeige: Gesamtbedarf / maximal im Zeitraum (inkl. Set-Teile). */
function formatRestCell(row: ActivityMaterialLine): string {
  if (isReplenishmentLine(row)) {
    return t('activities.materialLinesTable.replenishmentRest')
  }
  if (isVirtualComboParentRow(row)) {
    return t('activities.wizard.form.summaryEmpty')
  }
  if (availabilityLoading.value) return '…'
  const periodCapacity = periodCapacityForMaterialRow(row.material_item_id)
  if (periodCapacity === undefined) return t('activities.wizard.form.summaryEmpty')
  const demand = restDisplayDemandForRow(
    row,
    props.modelValue,
    props.childQuantityByMaterialItemId,
  )
  const rowCapacity = restCapacityForRow(
    row,
    props.modelValue,
    periodCapacity,
    props.childQuantityByMaterialItemId,
  )
  if (rowCapacity === undefined) return t('activities.wizard.form.summaryEmpty')
  return `${demand} / ${rowCapacity}`
}

const hasAnyAvailabilityShortage = computed(() => {
  if (availabilityLoading.value) return false
  return props.modelValue.some((row) => !qtyRowLocked(row) && shortageForRow(row) > 0)
})

const orderedLines = computed(() => {
  const rows = props.modelValue.map((row, originalIndex) => ({ row, originalIndex }))
  const shortageForSort = (r: ActivityMaterialLine) =>
    qtyRowLocked(r) ? 0 : shortageForRow(r)
  const asc = sortDir.value === 'asc'

  if (props.variant === 'detail-draft' && !userChoseSort.value) {
    return [...rows].sort((x, y) => x.originalIndex - y.originalIndex)
  }

  return [...rows].sort((x, y) => {
    if (!availabilityLoading.value && props.variant !== 'detail-draft') {
      const ix = shortageForSort(x.row) > 0 ? 1 : 0
      const iy = shortageForSort(y.row) > 0 ? 1 : 0
      if (ix !== iy) return iy - ix
    }
    let c = 0
    switch (sortCol.value) {
      case 'name':
        c = (x.row.material_name || '').localeCompare(
          y.row.material_name || '',
          String(locale.value ?? '').startsWith('de') ? 'de' : 'en',
        )
        break
      case 'available': {
        const rx = remainingAfterSelection(x.row)
        const ry = remainingAfterSelection(y.row)
        c = (rx ?? -1) - (ry ?? -1)
        break
      }
      case 'quantity':
        c = x.row.quantity - y.row.quantity
        break
      default:
        c = 0
    }
    if (c !== 0) return asc ? c : -c
    const s = shortageForSort(x.row) - shortageForSort(y.row)
    if (s !== 0) return -s
    const nameCmp = (x.row.material_name || '').localeCompare(
      y.row.material_name || '',
      String(locale.value ?? '').startsWith('de') ? 'de' : 'en',
    )
    if (nameCmp !== 0) return nameCmp
    return x.originalIndex - y.originalIndex
  })
})

let refreshDebounce: ReturnType<typeof setTimeout> | null = null
let availabilityRefreshGeneration = 0
let lastAvailabilityFetchKey = ''

const availabilityFetchKey = computed(() =>
  [
    props.departmentId,
    props.activityId ?? '',
    planningStartIso.value,
    planningEndIso.value,
    props.materialScopeTab ?? 'own',
    props.materialScopeHasPartners ?? false,
    props.materialScopeSinglePartnerId ?? '',
    props.modelValue.map((r) => r.material_item_id).join('|'),
    props.modelValue.map((r) => r.saved_quantity ?? '').join('|'),
  ].join('\0'),
)

async function refreshLineAvailability(fetchKey: string) {
  const generation = ++availabilityRefreshGeneration
  const ids = [...new Set(props.modelValue.map((r) => r.material_item_id))]
  if (ids.length === 0) {
    if (generation !== availabilityRefreshGeneration) return
    availabilityMap.value = {}
    availabilityError.value = null
    availabilityFirstFetchDone.value = false
    lastAvailabilityFetchKey = fetchKey
    return
  }
  const showLoadingUi = !availabilityFirstFetchDone.value
  if (showLoadingUi) availabilityLoading.value = true
  availabilityError.value = null
  try {
    const apiRows = await fetchMaterialsAvailableForPeriodByIds({
      departmentId: props.departmentId,
      activityId: props.activityId,
      startDateIso: planningStartIso.value,
      endDateIso: planningEndIso.value,
      materialItemIds: ids,
      scope: scopeForAvailabilityFetch.value,
    })
    if (generation !== availabilityRefreshGeneration) return
    const m: Record<string, number> = { ...availabilityMap.value }
    for (const id of ids) {
      delete m[id]
    }
    for (const r of apiRows) {
      m[r.materialItemId] = typeof r.availableForPeriod === 'number' ? r.availableForPeriod : 0
    }
    /** Keine künstliche 0 bei fehlendem Treffer — sonst max = Menge, falsche „50/50“ und keine + Buttons */
    availabilityMap.value = m

    let capsChanged = false
    const nextLines = props.modelValue.map((row) => {
      const cap = m[row.material_item_id]
      if (typeof cap !== 'number' || row.period_availability_cap === cap) return row
      capsChanged = true
      return { ...row, period_availability_cap: cap }
    })
    if (capsChanged) emit('update:modelValue', nextLines)
    availabilityFirstFetchDone.value = true
    lastAvailabilityFetchKey = fetchKey
  } catch (e: unknown) {
    if (generation !== availabilityRefreshGeneration) return
    availabilityError.value =
      e && typeof e === 'object' && 'message' in e && typeof (e as Error).message === 'string'
        ? (e as Error).message
        : t('activities.materialLinesTable.availabilityLoadFailed')
  } finally {
    if (generation === availabilityRefreshGeneration && showLoadingUi) {
      availabilityLoading.value = false
    }
  }
}

/**
 * API nur bei Struktur-/Server-Sync, nicht bei reiner Mengenänderung (Entwurf).
 * Frei-Menge bei Typing kommt aus Rohwert + (saved−draft) im Client.
 */
watch(
  availabilityFetchKey,
  (fetchKey) => {
    if (fetchKey === lastAvailabilityFetchKey && availabilityFirstFetchDone.value) return
    if (refreshDebounce) clearTimeout(refreshDebounce)
    refreshDebounce = setTimeout(() => {
      refreshDebounce = null
      queueMicrotask(() => {
        void refreshLineAvailability(fetchKey)
      })
    }, 320)
  },
  { immediate: true },
)

watch(
  () => [props.departmentId, props.activityId ?? ''] as const,
  () => {
    availabilityFirstFetchDone.value = false
    lastAvailabilityFetchKey = ''
  },
)

function canIncrementLine(row: ActivityMaterialLine, delta: number): boolean {
  if (delta < 1 || props.disabled) return false
  const max = maxQtyForRow(row)
  if (max === undefined) return true
  return row.quantity + delta <= max
}

function canDecrementLine(row: ActivityMaterialLine, delta: number): boolean {
  if (delta < 1 || props.disabled) return false
  return row.quantity - delta >= minQtyForRow(row)
}

function showQuickDecDivider(row: ActivityMaterialLine): boolean {
  if (props.disabled) return false
  const hasDec = [1, 5, 10].some((d) => canDecrementLine(row, d))
  const hasInc = [1, 5, 10].some((d) => canIncrementLine(row, d))
  return hasDec && hasInc
}

function showPackDecDivider(row: ActivityMaterialLine): boolean {
  if (props.disabled || !row.pack_size || row.pack_size <= 1) return false
  const ps = row.pack_size
  const hasDec = canDecrementLine(row, ps) || canDecrementLine(row, ps * 5)
  const hasInc = canIncrementLine(row, ps) || canIncrementLine(row, ps * 5)
  return hasDec && hasInc
}

function incrementLine(idx: number, delta: number) {
  const row = props.modelValue[idx]
  if (!row || qtyRowLocked(row) || !canIncrementLine(row, delta)) return
  const max = maxQtyForRow(row)
  const maxAdd = max === undefined ? delta : Math.min(delta, Math.max(0, max - row.quantity))
  if (maxAdd < 1) return
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: lines[idx].quantity + maxAdd }
  emit('update:modelValue', lines)
}

function decrementLine(idx: number, delta: number) {
  const row = props.modelValue[idx]
  if (!row || qtyRowLocked(row) || !canDecrementLine(row, delta)) return
  const next = Math.max(minQtyForRow(row), row.quantity - delta)
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: next }
  emit('update:modelValue', lines)
}

function onQtyChange(idx: number, e: Event) {
  const row = props.modelValue[idx]
  if (!row || qtyRowLocked(row)) return
  const raw = parseInt((e.target as HTMLInputElement).value, 10)
  let v = Number.isNaN(raw) ? minQtyForRow(row) : Math.max(minQtyForRow(row), raw)
  const max = maxQtyForRow(row)
  if (max !== undefined && v > max) v = max
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: v }
  emit('update:modelValue', lines)
}

function emitRemove(originalIndex: number) {
  const line = props.modelValue[originalIndex]
  if (!line || !canRemoveLine(line)) return
  emit('remove-line', { line, index: originalIndex })
}

function applySuggestedForLine(originalIndex: number) {
  if (props.disabled || availabilityLoading.value) return
  const row = props.modelValue[originalIndex]
  if (!row || qtyRowLocked(row)) return
  const materialId = row.material_item_id
  let shortage = materialShortageFor(materialId)
  if (shortage <= 0) return

  const minQ = minQtyForRow(row)
  const reducible = Math.max(0, row.quantity - minQ)
  const reduce = Math.min(reducible, shortage)
  if (reduce <= 0) return

  const lines = [...props.modelValue]
  lines[originalIndex] = { ...lines[originalIndex], quantity: row.quantity - reduce }
  emit('update:modelValue', lines)
}

function applyAllSuggestedQuantities() {
  if (props.disabled || availabilityLoading.value) return
  let changed = false
  const lines = props.modelValue.map((row) => ({ ...row }))
  const materialIds = [...new Set(lines.map((r) => r.material_item_id))]

  for (const materialId of materialIds) {
    let shortage = materialShortageFor(materialId)
    if (shortage <= 0) continue

    const indices = lines
      .map((row, index) => ({ row, index }))
      .filter(({ row }) => !qtyRowLocked(row) && row.material_item_id === materialId)
      .sort((a, b) => b.row.quantity - a.row.quantity)

    for (const { row, index } of indices) {
      if (shortage <= 0) break
      const minQ = minQtyForRow(row)
      const reducible = Math.max(0, row.quantity - minQ)
      const reduce = Math.min(reducible, shortage)
      if (reduce <= 0) continue
      lines[index] = { ...lines[index], quantity: row.quantity - reduce }
      shortage -= reduce
      changed = true
    }
  }

  if (changed) emit('update:modelValue', lines)
}
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.activity-mat-avail-error {
  margin: 0 0 10px;
  font-size: 13px;
  color: #b45309;
}

.activity-mat-avail-shortage-banner {
  margin: 0 0 10px;
  font-size: 13px;
}

.activity-mat-reconcile-bulk {
  margin: 0 0 10px;
}

.activity-material-table-wrap {
  width: 100%;
  overflow-x: auto;
}

.activity-material-table {
  width: 100%;
  table-layout: auto;
  border-collapse: collapse;
  font-size: 13px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.activity-material-table th {
  text-align: left;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  padding: 0;
  vertical-align: bottom;
  font-weight: 600;
  color: #374151;
}

.activity-mat-th-btn {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 4px;
  width: 100%;
  padding: 10px 12px;
  margin: 0;
  border: none;
  background: transparent;
  font: inherit;
  font-weight: 600;
  color: inherit;
  cursor: pointer;
  text-align: left;
}

.activity-mat-th-btn:hover {
  background: #f3f4f6;
}

.activity-mat-sort-ind {
  font-size: 11px;
  color: #059669;
  font-weight: 700;
}

/** Artikelname: bis max. breit umbrechen; kurze Namen ziehen die Spalte nicht künstlich auf */
.activity-mat-col-name {
  min-width: 0;
  max-width: 26rem;
}

/** Menge: wächst mit dem Inhalt (Buttons) und nutzt den restlichen Platz */
.activity-mat-col-qty {
  min-width: 14rem;
}

/** Rest: stabile Breite (verhindert „Springen“ bei … / Zahl / Menge/max) */
.activity-mat-col-rest {
  width: 1%;
  min-width: 7.5rem;
  white-space: nowrap;
  text-align: right;
}

.activity-mat-th-btn--narrow {
  padding-left: 8px;
  padding-right: 8px;
  justify-content: flex-end;
  text-align: right;
}

.activity-material-table th.activity-mat-col-warn {
  padding: 10px 6px;
  vertical-align: bottom;
  line-height: 1.2;
  white-space: nowrap;
}

.activity-mat-name-stack {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}

.activity-mat-combo-kiste {
  width: 100%;
  flex-basis: 100%;
  font-size: 12px;
  margin: 0;
}

.activity-mat-combo-content-dropdown {
  width: 100%;
  flex-basis: 100%;
  margin-top: 2px;
}

.activity-mat-combo-content-toggle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  border-radius: 4px;
  border: 1px solid var(--color-primary-muted-border, #a7f3d0);
  background: var(--color-primary-muted-bg, #ecfdf5);
  color: var(--color-primary-dark, #047857);
  cursor: pointer;
  font-family: inherit;
}

.activity-mat-combo-content-toggle:hover {
  background: var(--color-primary-subtle-bg, #d1fae5);
}

.activity-mat-combo-content-chev {
  font-size: 9px;
  line-height: 1;
}

.activity-mat-combo-content-body {
  margin-top: 4px;
  padding: 6px 10px;
  border-left: 2px solid var(--color-primary-light, #10b981);
  background: var(--color-primary-muted-bg, #ecfdf5);
  border-radius: 0 6px 6px 0;
}

.activity-mat-combo-content-list {
  margin: 0;
  padding-left: 16px;
  font-size: 12px;
  color: #4b5563;
}

.activity-mat-combo-content-list li {
  line-height: 1.5;
}

.activity-mat-combo-content-self {
  font-style: italic;
}

.activity-mat-combo-content-empty {
  margin: 0;
  font-size: 12px;
}

.activity-mat-set-content {
  width: 100%;
  flex-basis: 100%;
  margin-top: 4px;
  padding: 6px 10px;
  border-left: 2px solid var(--color-primary-muted-border, #a7f3d0);
  background: var(--color-primary-muted-bg, #ecfdf5);
  border-radius: 0 6px 6px 0;
}

.activity-mat-set-title {
  display: block;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 2px;
}

.activity-mat-set-list {
  margin: 0;
  padding-left: 16px;
  font-size: 12px;
  color: #4b5563;
}

.activity-mat-set-list li {
  line-height: 1.5;
}

.activity-mat-set-self {
  font-style: italic;
}

.activity-mat-pack-mode-hint {
  display: block;
  margin: 0.15rem 0 0.35rem;
  font-size: 0.78rem;
}

.activity-mat-pack-mode-edit {
  margin: 0.35rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.activity-mat-pack-mode-edit-label {
  font-size: 0.78rem;
  font-weight: 600;
}

.activity-mat-pack-mode-option {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  font-size: 0.78rem;
  line-height: 1.35;
  cursor: pointer;
}

.activity-mat-pack-mode-option input {
  margin-top: 0.15rem;
  flex-shrink: 0;
}

.activity-mat-selfprovided-ack-badge {
  display: inline-block;
  margin-left: 0.35rem;
  padding: 0.05rem 0.4rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-style: normal;
  background: #fef3c7;
  color: #92400e;
}

.activity-mat-col-source {
  width: 1%;
  max-width: 12rem;
  min-width: 0;
  white-space: nowrap;
}

.activity-mat-col-storage {
  max-width: 9rem;
  min-width: 5rem;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}

.activity-mat-cell-storage {
  font-size: 13px;
  max-width: 10rem;
  word-break: break-word;
  vertical-align: top;
}

.activity-mat-col-money {
  white-space: nowrap;
}

/** Hinweis: nur so breit wie Inhalt (Badge bricht bei Bedarf um) */
.activity-mat-col-warn {
  width: 1%;
  max-width: 11rem;
  min-width: 0;
}

.activity-mat-col-actions {
  width: 2.75rem;
  min-width: 2.75rem;
  max-width: 2.75rem;
  box-sizing: border-box;
}

/** Detail-Entwurf: Text-Button „Entfernen“ statt × */
.activity-material-lines-table-root--detail-draft .activity-mat-col-actions {
  width: auto;
  min-width: 5.5rem;
  max-width: none;
}

.activity-material-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.activity-material-table tbody tr {
  background: #ffffff;
}

.activity-material-table tbody tr.activity-mat-row--even:not(.activity-mat-row--issue) {
  background: #f3f4f6;
}

.activity-material-table tbody tr:last-child td {
  border-bottom: none;
}

.activity-mat-row--issue:not(.activity-mat-row--even) {
  background: #fffbeb;
}

.activity-mat-row--issue.activity-mat-row--even {
  background: #fef3c7;
}

.activity-mat-cell-name {
  font-weight: 500;
  color: #111827;
  vertical-align: top;
  white-space: normal;
  word-break: break-word;
  overflow-wrap: anywhere;
  hyphens: auto;
  line-height: 1.4;
  max-width: 26rem;
  min-width: 0;
}

.activity-mat-cell-source {
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 12rem;
}

.activity-mat-js-tag {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  font-size: 10px;
  font-weight: 600;
  border-radius: 4px;
  background: #eef2ff;
  color: #4338ca;
}

.activity-mat-replenishment-tag {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  font-size: 10px;
  font-weight: 600;
  border-radius: 4px;
  background: #fff7ed;
  color: #c2410c;
  border: 1px solid #fed7aa;
}

.activity-mat-container-tag {
  display: inline-block;
  margin-left: 4px;
  padding: 1px 7px;
  font-size: 10px;
  font-weight: 600;
  line-height: 1.35;
  border-radius: 4px;
  border: 1px solid #d6d3d1;
  background: #fafaf9;
  color: #57534e;
  cursor: default;
  font-family: inherit;
  opacity: 1;
}

.activity-mat-container-tag:disabled {
  pointer-events: none;
}

.activity-mat-tracking-tag {
  display: inline-block;
  padding: 1px 6px;
  font-size: 10px;
  font-weight: 600;
  border-radius: 4px;
  background: #f3f4f6;
  color: #6b7280;
}

.activity-mat-cell-num {
  font-variant-numeric: tabular-nums;
  color: #374151;
}

.activity-mat-cell-rest {
  text-align: right;
  white-space: nowrap;
  padding-left: 6px;
  padding-right: 6px;
  width: 1%;
}

.activity-material-lines-table-root--detail-draft .activity-mat-col-rest,
.activity-material-lines-table-root--detail-draft .activity-mat-cell-rest {
  white-space: normal;
  vertical-align: top;
}

.activity-mat-rest-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  max-width: 11rem;
}

.activity-mat-rest-value {
  display: inline-block;
  min-width: 6.5rem;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.activity-mat-rest-max {
  font-size: 11px;
  line-height: 1.2;
}

.activity-mat-rest-adjust {
  padding: 2px 8px;
  font-size: 11px;
  line-height: 1.2;
}

.activity-mat-cell-warn {
  font-size: 11px;
  line-height: 1.35;
  min-width: 0;
  width: 1%;
  max-width: 11rem;
  padding-left: 6px;
  padding-right: 6px;
}

.activity-mat-floor-hint {
  display: block;
  font-size: 11px;
  line-height: 1.35;
  margin-bottom: 4px;
}

.activity-mat-warn-badge {
  display: block;
  padding: 3px 5px;
  border-radius: 5px;
  background: #fef3c7;
  color: #92400e;
  line-height: 1.35;
  max-width: 100%;
  white-space: normal;
  word-break: break-word;
  hyphens: auto;
}

.activity-mat-cell-qty {
  min-width: 12rem;
}

.activity-material-line-qty-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  min-width: 0;
  max-width: 100%;
}

.activity-material-line-qty-block--packing-locked {
  align-items: flex-start;
  gap: 2px;
}

.activity-mat-qty-readonly {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: #374151;
}

.activity-mat-pack-hint {
  font-size: 11px;
  line-height: 1.2;
}

.activity-mat-remove-na {
  display: inline-block;
  min-width: 1.25rem;
  text-align: center;
  font-size: 13px;
}

/** Detailansicht: Steuerung links an Rest/Hinweis statt weit rechts im Zellenrest */
.activity-material-line-qty-block--detail {
  align-items: flex-start;
}

.activity-material-line-qty-block--detail .activity-material-line-row--pack,
.activity-material-line-qty-block--detail .activity-material-line-row--quick {
  justify-content: flex-start;
}

.activity-material-line-qty-block--detail .activity-material-line-btns {
  justify-content: flex-start;
}

.activity-material-line-row--pack {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 4px 6px;
  width: 100%;
}

.activity-material-line-row--quick {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  width: 100%;
}

.activity-material-line-btns {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 4px 5px;
  flex: 0 1 auto;
  min-width: 0;
}

/** Wie Materialsuche (Dropdown): grüne Akzente */
.activity-mat-quick-btn,
.activity-mat-set-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  min-height: 28px;
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  color: #059669;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  box-shadow: none;
  cursor: pointer;
  transition:
    background 0.12s ease,
    border-color 0.12s ease,
    color 0.12s ease,
    box-shadow 0.12s ease;
}

@media (hover: hover) and (pointer: fine) {
  .activity-mat-quick-btn:hover:not(:disabled),
  .activity-mat-set-btn:hover:not(:disabled) {
    background: #ecfdf5;
    border-color: #059669;
    color: #047857;
  }
}

.activity-mat-quick-btn:active:not(:disabled),
.activity-mat-set-btn:active:not(:disabled) {
  background: #d1fae5;
  transform: translateY(1px);
}

.activity-mat-quick-btn:focus-visible,
.activity-mat-set-btn:focus-visible {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 3px rgb(5 150 105 / 22%);
}

.activity-mat-quick-btn:disabled,
.activity-mat-set-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.activity-mat-set-btn {
  min-width: auto;
  font-weight: 500;
}

/** Minus: schwaches Rot, Plus bleibt grün */
.activity-mat-quick-btn--dec {
  color: #c2410c;
  background: #fff5f5;
  border-color: #fecdd3;
}

@media (hover: hover) and (pointer: fine) {
  .activity-mat-quick-btn--dec:hover:not(:disabled) {
    background: #ffe4e6;
    border-color: #fb7185;
    color: #b91c1c;
  }
}

.activity-mat-quick-btn--dec:focus-visible {
  border-color: #f43f5e;
  box-shadow: 0 0 0 3px rgb(244 63 94 / 18%);
}

.activity-mat-btn-divider {
  color: #d1d5db;
  font-size: 11px;
  user-select: none;
  padding: 0 2px;
}

.activity-material-qty {
  flex: 0 0 auto;
}

.activity-material-qty .form-input--qty {
  width: 64px;
  padding: 5px 7px;
  font-variant-numeric: tabular-nums;
  border-radius: 6px;
  transition:
    border-color 0.12s ease,
    box-shadow 0.12s ease;
}

.activity-material-qty .form-input--qty:focus-visible {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 3px rgb(5 150 105 / 20%);
}

@media (hover: hover) and (pointer: fine) {
  .activity-material-qty .form-input--qty:hover:not(:disabled):not(:focus-visible) {
    border-color: #9ca3af;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 6%);
  }
}

.activity-material-remove {
  width: 36px;
  height: 36px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 1.2rem;
  line-height: 1;
  color: #6b7280;
  cursor: pointer;
}

@media (hover: hover) and (pointer: fine) {
  .activity-material-remove:hover:not(:disabled) {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
  }
}

.activity-material-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.activity-mat-remove-text {
  white-space: nowrap;
}

.activity-empty-lines {
  margin: 0;
  font-size: 13px;
}

.text-muted {
  color: #6b7280;
}
</style>
