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
      Im gewählten Abhol-/Rückgabezeitraum reicht der Bestand für mindestens eine Position nicht — betroffene Zeilen stehen oben in der Liste.
    </p>
    <div
      v-if="
        variant === 'detail-draft' &&
        hasAnyAvailabilityShortage &&
        !availabilityLoading &&
        !disabled &&
        modelValue.length > 0
      "
      class="activity-mat-reconcile-bulk"
    >
      <button type="button" class="btn-outline btn-sm" @click="applyAllSuggestedQuantities">
        Alle Positionen auf maximal Verfügbares anpassen
      </button>
    </div>

    <div v-if="modelValue.length > 0" class="activity-material-table-wrap">
      <table class="activity-material-table" :aria-busy="availabilityLoading">
        <thead>
          <tr>
            <th scope="col" class="activity-mat-col-name">
              <button type="button" class="activity-mat-th-btn" @click="toggleSort('name')">
                Material
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('name') }}</span>
              </button>
            </th>
            <th v-if="showSourceAndTotals" scope="col" class="activity-mat-col-source">Quelle</th>
            <th scope="col" class="activity-mat-col-rest">
              <button
                type="button"
                class="activity-mat-th-btn activity-mat-th-btn--narrow"
                title="Menge / maximal buchbar im Zeitraum (Lager &amp; Überschneidungen)"
                @click="toggleSort('available')"
              >
                Rest
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('available') }}</span>
              </button>
            </th>
            <th scope="col" class="activity-mat-col-qty">
              <button type="button" class="activity-mat-th-btn" @click="toggleSort('quantity')">
                Menge
                <span class="activity-mat-sort-ind" aria-hidden="true">{{ sortGlyph('quantity') }}</span>
              </button>
            </th>
            <th v-if="showSourceAndTotals && showLineTotal" scope="col" class="activity-mat-col-money">Zeile</th>
            <th scope="col" class="activity-mat-col-warn">Hinweis</th>
            <th scope="col" class="activity-mat-col-actions"><span class="sr-only">Aktionen</span></th>
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
                <span v-if="row.material_type === 'physical_combo'" class="activity-mat-combo-tag" title="Physische Kombination"
                  >Phys. Kombi</span
                >
                <span
                  v-else-if="row.material_type === 'virtual_combo'"
                  class="activity-mat-combo-tag activity-mat-combo-tag--virtual"
                  title="Virtuelle Kombination"
                  >Virt. Kombi</span
                >
                <span v-if="row.is_js_material" class="activity-mat-js-tag">J&amp;S</span>
                <button
                  v-if="row.is_container"
                  type="button"
                  class="activity-mat-container-tag"
                  disabled
                  title="Behälter — kann anderen Lagerinhalt aufnehmen (Kiste, Tasche, Fass …)"
                >
                  Behälter
                </button>
                <span
                  v-if="row.tracking_type === 'serialized' || row.tracking_type === 'bulk'"
                  class="activity-mat-tracking-tag text-muted"
                  :title="
                    row.tracking_type === 'serialized'
                      ? 'Serialisiert (pro Stück / Seriennummer)'
                      : 'Mengenware (Bestand nach Menge)'
                  "
                >
                  {{ row.tracking_type === 'serialized' ? 'Serialisiert' : 'Mengenware' }}
                </span>
                <div v-if="row.linked_container_label" class="activity-mat-combo-kiste text-muted">
                  Kiste: {{ row.linked_container_label }}
                </div>
              </div>
            </td>
            <td v-if="showSourceAndTotals" class="activity-mat-cell-source text-muted">
              {{ row.source_department_name || '–' }}
            </td>
            <td class="activity-mat-cell-num activity-mat-cell-rest">
              <div v-if="variant === 'detail-draft'" class="activity-mat-rest-stack">
                <span v-if="availabilityLoading" class="text-muted">…</span>
                <template v-else>
                  <span class="activity-mat-rest-value">{{ formatRestCell(row) }}</span>
                  <template v-if="!disabled && !lineLockedForPackListOnly(row) && shortageForRow(row) > 0">
                    <button
                      type="button"
                      class="btn-outline btn-sm activity-mat-rest-adjust"
                      @click="applySuggestedForLine(originalIndex)"
                    >
                      Anpassen
                    </button>
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
                v-if="lineLockedForPackListOnly(row)"
                class="activity-material-line-qty-block activity-material-line-qty-block--packing-locked"
              >
                <span class="activity-mat-qty-readonly" title="Menge wird über die Packliste gesteuert">{{
                  row.quantity
                }}</span>
                <span class="activity-mat-pack-hint text-muted">Packliste</span>
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
                    :title="'−1 ' + (row.pack_unit || 'Set')"
                    :aria-label="'Menge um 1 ' + (row.pack_unit || 'Set') + ' verringern'"
                    :disabled="disabled"
                    @mousedown.prevent="decrementLine(originalIndex, row.pack_size)"
                  >
                    −1 {{ row.pack_unit || 'Set' }}
                  </button>
                  <button
                    v-if="canDecrementLine(row, row.pack_size * 5)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn activity-mat-quick-btn--dec"
                    :title="'−5 ' + (row.pack_unit || 'Sets')"
                    :aria-label="'Menge um 5 ' + (row.pack_unit || 'Sets') + ' verringern'"
                    :disabled="disabled"
                    @mousedown.prevent="decrementLine(originalIndex, row.pack_size * 5)"
                  >
                    −5 {{ row.pack_unit || 'Sets' }}
                  </button>
                  <span v-if="showPackDecDivider(row)" class="activity-mat-btn-divider" aria-hidden="true">|</span>
                  <button
                    v-if="canIncrementLine(row, row.pack_size)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn"
                    :title="'1 ' + (row.pack_unit || 'Set')"
                    :aria-label="'Menge um 1 ' + (row.pack_unit || 'Set') + ' erhöhen'"
                    :disabled="disabled"
                    @mousedown.prevent="incrementLine(originalIndex, row.pack_size)"
                  >
                    1 {{ row.pack_unit || 'Set' }}
                  </button>
                  <button
                    v-if="canIncrementLine(row, row.pack_size * 5)"
                    type="button"
                    class="activity-mat-quick-btn activity-mat-set-btn"
                    :title="'5 ' + (row.pack_unit || 'Sets')"
                    :aria-label="'Menge um 5 ' + (row.pack_unit || 'Sets') + ' erhöhen'"
                    :disabled="disabled"
                    @mousedown.prevent="incrementLine(originalIndex, row.pack_size * 5)"
                  >
                    5 {{ row.pack_unit || 'Sets' }}
                  </button>
                </div>
                <div class="activity-material-line-row activity-material-line-row--quick">
                  <label class="activity-material-qty">
                    <span class="sr-only">Menge</span>
                    <input
                      type="number"
                      :min="minQty"
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
              <span v-else>–</span>
            </td>
            <td class="activity-mat-cell-warn">
              <span
                v-if="!availabilityLoading && lineHasIssue(row) && variant !== 'detail-draft'"
                class="activity-mat-warn-badge"
                title="Menge über verfügbaren Rest"
              >
                Nur {{ maxQtyForRow(row) }} frei
              </span>
            </td>
            <td class="activity-mat-cell-remove">
              <template v-if="lineLockedForPackListOnly(row)">
                <span class="activity-mat-remove-na text-muted" title="Zuordnung über die Packliste (Behälter)">–</span>
              </template>
              <template v-else>
                <button
                  v-if="variant === 'wizard'"
                  type="button"
                  class="activity-material-remove"
                  title="Position entfernen"
                  :aria-label="'Position entfernen: ' + row.material_name"
                  :disabled="disabled"
                  @click="emitRemove(originalIndex)"
                >
                  ×
                </button>
                <button
                  v-else
                  type="button"
                  class="btn-outline btn-sm activity-mat-remove-text"
                  :disabled="disabled || removeBusyFor(row)"
                  @click="emitRemove(originalIndex)"
                >
                  Entfernen
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="text-muted activity-empty-lines">{{ emptyText }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { fetchMaterialsAvailableForPeriodByIds } from '@/api/materialAvailabilityPeriod'
import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'
import { materialLookupContextForScopeTab, type MaterialScopeTab } from './activityMaterialAvailabilityScope'
import type { MaterialLookupAvailabilityContext } from '@/composables/useMaterialLookup'

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
    emptyText: 'Noch keine Positionen.',
    materialScopeTab: 'own',
    materialScopeHasPartners: false,
    materialScopeSinglePartnerId: null,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: ActivityMaterialLine[]]
  'remove-line': [payload: { line: ActivityMaterialLine; index: number }]
}>()

const minQty = computed(() => (props.variant === 'wizard' ? 1 : 0))

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
const sortCol = ref<SortCol>('available')
const sortDir = ref<'asc' | 'desc'>('asc')

function toggleSort(col: SortCol) {
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
  return row.activity_item_id ? row.activity_item_id : `${row.material_item_id}-${originalIndex}`
}

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
  let draftSum = 0
  let savedSum = 0
  for (const row of props.modelValue) {
    if (row.material_item_id !== materialItemId) continue
    draftSum += row.quantity
    if (typeof row.saved_quantity === 'number') {
      savedSum += row.saved_quantity
    }
  }
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

/** Max. buchbare Menge für diese Zeile (eine Position); gleiches Material mehrfach: gemeinsamer Pool */
function maxQtyForRow(row: ActivityMaterialLine): number | undefined {
  const free = adjustedFreePoolForMaterial(row.material_item_id)
  if (free === undefined) return undefined
  return row.quantity + free
}

function shortageForRow(row: ActivityMaterialLine): number {
  const max = maxQtyForRow(row)
  if (max === undefined) return 0
  return Math.max(0, row.quantity - max)
}

/**
 * Nur reine Behälter-Zeilen (ohne Phys.-Kombi): Menge/Entfernen über Packliste.
 * Physische Kombination mit Behälter-Badge bleibt in der Materialliste editierbar.
 */
function lineLockedForPackListOnly(row: ActivityMaterialLine): boolean {
  return !!row.is_container && row.material_type !== 'physical_combo'
}

function lineHasIssue(row: ActivityMaterialLine): boolean {
  if (availabilityLoading.value) return false
  if (lineLockedForPackListOnly(row)) return false
  return shortageForRow(row) > 0
}

/** Für Sortierung: verbleibender Spielraum (gleicher Wert pro Zeile bei gleichem Material) */
function remainingAfterSelection(row: ActivityMaterialLine): number | null {
  const free = adjustedFreePoolForMaterial(row.material_item_id)
  if (free === undefined) return null
  return free
}

/** Anzeige: Menge / maximal im Zeitraum */
function formatRestCell(row: ActivityMaterialLine): string {
  if (availabilityLoading.value) return '…'
  const max = maxQtyForRow(row)
  if (max === undefined) return '–'
  return `${row.quantity} / ${max}`
}

const hasAnyAvailabilityShortage = computed(() => {
  if (availabilityLoading.value) return false
  return props.modelValue.some((row) => !lineLockedForPackListOnly(row) && shortageForRow(row) > 0)
})

const orderedLines = computed(() => {
  const rows = props.modelValue.map((row, originalIndex) => ({ row, originalIndex }))
  const getAvail = (id: string) => availabilityMap.value[id]
  const shortageForSort = (r: ActivityMaterialLine) =>
    lineLockedForPackListOnly(r) ? 0 : shortageForRow(r)
  const asc = sortDir.value === 'asc'

  return [...rows].sort((x, y) => {
    if (!availabilityLoading.value) {
      const ix = shortageForSort(x.row) > 0 ? 1 : 0
      const iy = shortageForSort(y.row) > 0 ? 1 : 0
      if (ix !== iy) return iy - ix
    }
    let c = 0
    switch (sortCol.value) {
      case 'name':
        c = (x.row.material_name || '').localeCompare(y.row.material_name || '', 'de')
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
    const nameCmp = (x.row.material_name || '').localeCompare(y.row.material_name || '', 'de')
    if (nameCmp !== 0) return nameCmp
    return x.originalIndex - y.originalIndex
  })
})

let refreshDebounce: ReturnType<typeof setTimeout> | null = null

async function refreshLineAvailability() {
  const ids = [...new Set(props.modelValue.map((r) => r.material_item_id))]
  if (ids.length === 0) {
    availabilityMap.value = {}
    availabilityError.value = null
    availabilityFirstFetchDone.value = false
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
  } catch (e: unknown) {
    availabilityError.value =
      e && typeof e === 'object' && 'message' in e && typeof (e as Error).message === 'string'
        ? (e as Error).message
        : 'Verfügbarkeit konnte nicht geladen werden.'
  } finally {
    if (showLoadingUi) availabilityLoading.value = false
  }
}

/**
 * API nur bei Struktur-/Server-Sync, nicht bei reiner Mengenänderung (Entwurf).
 * Frei-Menge bei Typing kommt aus Rohwert + (saved−draft) im Client.
 */
watch(
  () =>
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
    ] as const,
  () => {
    if (refreshDebounce) clearTimeout(refreshDebounce)
    refreshDebounce = setTimeout(() => {
      void refreshLineAvailability()
    }, 320)
  },
  { immediate: true },
)

watch(
  () => [props.departmentId, props.activityId ?? ''] as const,
  () => {
    availabilityFirstFetchDone.value = false
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
  return row.quantity - delta >= minQty.value
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
  if (!row || lineLockedForPackListOnly(row) || !canIncrementLine(row, delta)) return
  const max = maxQtyForRow(row)
  const maxAdd = max === undefined ? delta : Math.min(delta, Math.max(0, max - row.quantity))
  if (maxAdd < 1) return
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: lines[idx].quantity + maxAdd }
  emit('update:modelValue', lines)
}

function decrementLine(idx: number, delta: number) {
  const row = props.modelValue[idx]
  if (!row || lineLockedForPackListOnly(row) || !canDecrementLine(row, delta)) return
  const next = Math.max(minQty.value, row.quantity - delta)
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: next }
  emit('update:modelValue', lines)
}

function onQtyChange(idx: number, e: Event) {
  const row = props.modelValue[idx]
  if (!row || lineLockedForPackListOnly(row)) return
  const raw = parseInt((e.target as HTMLInputElement).value, 10)
  let v = Number.isNaN(raw) ? minQty.value : Math.max(minQty.value, raw)
  const max = maxQtyForRow(row)
  if (max !== undefined && v > max) v = max
  const lines = [...props.modelValue]
  lines[idx] = { ...lines[idx], quantity: v }
  emit('update:modelValue', lines)
}

function emitRemove(originalIndex: number) {
  const line = props.modelValue[originalIndex]
  if (!line || lineLockedForPackListOnly(line)) return
  emit('remove-line', { line, index: originalIndex })
}

function applySuggestedForLine(originalIndex: number) {
  if (props.disabled || availabilityLoading.value) return
  const row = props.modelValue[originalIndex]
  if (!row || lineLockedForPackListOnly(row)) return
  const max = maxQtyForRow(row)
  if (max === undefined) return
  const nextQty = Math.min(row.quantity, max)
  if (nextQty === row.quantity) return
  const lines = [...props.modelValue]
  lines[originalIndex] = { ...lines[originalIndex], quantity: nextQty }
  emit('update:modelValue', lines)
}

function applyAllSuggestedQuantities() {
  if (props.disabled || availabilityLoading.value) return
  let changed = false
  const lines = props.modelValue.map((row) => {
    if (lineLockedForPackListOnly(row)) return row
    const max = maxQtyForRow(row)
    if (max === undefined) return row
    const nextQty = Math.min(row.quantity, max)
    if (nextQty !== row.quantity) {
      changed = true
      return { ...row, quantity: nextQty }
    }
    return row
  })
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

.activity-mat-combo-tag {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #ede9fe;
  color: #5b21b6;
  flex-shrink: 0;
}

.activity-mat-combo-tag--virtual {
  background: #f3e8ff;
  color: #7c3aed;
}

.activity-mat-combo-kiste {
  width: 100%;
  flex-basis: 100%;
  font-size: 12px;
  margin: 0;
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
