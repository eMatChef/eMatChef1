<template>
  <div class="material-import-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.materialImport.title') }}</h1>
        <p class="subtitle">{{ t('settings.materialImport.subtitle') }}</p>
      </div>
    </div>

    <div class="tab-bar">
      <button type="button" class="tab-btn" :class="{ active: activeTab === 'import' }" @click="activeTab = 'import'">
        {{ t('settings.materialImport.tabImport') }}
      </button>
      <button type="button" class="tab-btn" :class="{ active: activeTab === 'export' }" @click="activeTab = 'export'">
        {{ t('settings.materialImport.tabExport') }}
      </button>
    </div>

    <div v-if="activeTab === 'export'" class="card export-placeholder">
      <h2>{{ t('settings.materialImport.exportTitle') }}</h2>
      <p>{{ t('settings.materialImport.exportComingSoon') }}</p>
    </div>

    <template v-else>
      <div class="card actions-card">
        <button type="button" class="btn-secondary" @click="onDownloadTemplate">
          {{ t('settings.materialImport.downloadTemplate') }}
        </button>
        <label class="btn-primary file-label">
          <input type="file" accept=".csv,.xlsx,.xls,text/csv" class="file-input" @change="onFileSelected" />
          {{ t('settings.materialImport.uploadFile') }}
        </label>
        <span v-if="fileName" class="file-name">{{ fileName }}</span>
      </div>

      <p class="hint">{{ t('settings.materialImport.uploadHint') }}</p>

      <div v-if="showMappingPanel && rawImport" class="card mapping-card">
        <h2>{{ t('settings.materialImport.mappingTitle') }}</h2>
        <p class="mapping-hint">{{ t('settings.materialImport.mappingHint') }}</p>

        <div class="mapping-row-header">
          <label>
            {{ t('settings.materialImport.headerRowLabel') }}
            <select v-model.number="headerRowIndex" class="form-select-sm" @change="onHeaderRowChange">
              <option v-for="n in headerRowOptions" :key="n" :value="n">
                {{ t('settings.materialImport.headerRowOption', { n: n + 1 }) }}
              </option>
            </select>
          </label>
          <button type="button" class="btn-secondary btn-sm" @click="resetSuggestedMapping">
            {{ t('settings.materialImport.mappingAutoDetect') }}
          </button>
        </div>

        <p class="mapping-table-hint">{{ t('settings.materialImport.mappingTableHint') }}</p>

        <div class="table-wrap source-table-wrap">
          <table class="source-mapping-table">
            <thead>
              <tr class="mapping-dropdown-row">
                <th v-for="col in tableColumnIndices" :key="`map-${col}`" class="mapping-th">
                  <select
                    class="column-field-select"
                    :class="{ 'column-field-select--mapped': columnAssignments[col] }"
                    :value="columnAssignments[col] || ''"
                    @change="onColumnFieldSelect(col, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">{{ t('settings.materialImport.mappingColumnSkip') }}</option>
                    <option
                      v-for="field in importUiFields"
                      :key="field"
                      :value="field"
                    >
                      {{ mappingFieldLabel(field) }}{{ field === 'name' ? ' *' : '' }}
                    </option>
                  </select>
                </th>
              </tr>
              <tr class="source-file-header-row">
                <th v-for="col in tableColumnIndices" :key="`hdr-${col}`">
                  <span class="col-letter">{{ excelColumnLetter(col) }}</span>
                  <span class="col-file-label">{{ fileColumnLabels[col] || '—' }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, ri) in sourcePreviewRows" :key="ri">
                <td
                  v-for="col in tableColumnIndices"
                  :key="col"
                  :class="{ 'cell-mapped': columnAssignments[col] }"
                >
                  {{ row[col] ?? '' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mapping-actions">
          <button type="button" class="btn-primary" @click="applyColumnMapping">
            {{ t('settings.materialImport.mappingApply') }}
          </button>
        </div>
      </div>

      <div v-if="rows.length > 0" class="card preview-card">
        <div class="preview-toolbar">
          <h2>{{ t('settings.materialImport.previewTitle', { count: rows.length }) }}</h2>
          <div class="toolbar-actions">
            <button
              v-if="rawImport"
              type="button"
              class="btn-secondary btn-sm"
              @click="showMappingPanel = true"
            >
              {{ t('settings.materialImport.mappingEdit') }}
            </button>
            <label class="duplicate-default">
              {{ t('settings.materialImport.duplicateDefault') }}
              <select v-model="defaultDuplicateAction" class="form-select-sm">
                <option value="add_batch">{{ t('settings.materialImport.duplicateAddBatch') }}</option>
                <option value="skip">{{ t('settings.materialImport.duplicateSkip') }}</option>
                <option value="create">{{ t('settings.materialImport.duplicateCreate') }}</option>
              </select>
            </label>
            <button type="button" class="btn-secondary btn-sm" :disabled="isBusy" @click="runDryRun">
              {{ t('settings.materialImport.validate') }}
            </button>
            <button type="button" class="btn-primary btn-sm" :disabled="isBusy || hasBlockingErrors" @click="onImportClick">
              {{ isImporting ? t('settings.materialImport.importing') : t('settings.materialImport.importSubmit') }}
            </button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="preview-table">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t('settings.materialImport.colName') }}</th>
                <th>{{ t('settings.materialImport.colQty') }}</th>
                <th>{{ t('settings.materialImport.colLength') }}</th>
                <th>{{ t('settings.materialImport.colSupplier') }}</th>
                <th>{{ t('settings.materialImport.colYear') }}</th>
                <th>{{ t('settings.materialImport.colPrice') }}</th>
                <th>{{ t('settings.materialImport.colDuplicate') }}</th>
                <th>{{ t('settings.materialImport.colStatus') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in rows"
                :key="row.row_index"
                :class="{
                  'row-error': rowErrors(idx).length > 0,
                  'row-duplicate': !!row._existingMaterialId,
                }"
              >
                <td>{{ idx + 1 }}</td>
                <td><input v-model="row.name" class="cell-input" type="text" /></td>
                <td><input v-model="row.qty" class="cell-input cell-input-narrow" type="number" min="1" /></td>
                <td><input v-model="row.size_length" class="cell-input cell-input-narrow" type="text" :placeholder="t('settings.materialImport.lengthPlaceholder')" /></td>
                <td class="supplier-cell">
                  <input
                    v-model="row.supplier_name"
                    class="cell-input"
                    type="text"
                    :list="`supplier-list-${idx}`"
                    @change="onSupplierNameChange(row)"
                  />
                  <datalist :id="`supplier-list-${idx}`">
                    <option v-for="s in supplierOptions" :key="s.id" :value="supplierLabel(s)" />
                  </datalist>
                </td>
                <td><input v-model="row.acquired_year" class="cell-input cell-input-narrow" type="text" maxlength="10" @blur="syncAcquiredOn(row)" /></td>
                <td><input v-model="row.unit_price" class="cell-input cell-input-narrow" type="text" /></td>
                <td>
                  <select v-if="row._existingMaterialId" v-model="row.duplicate_action" class="cell-select">
                    <option value="add_batch">{{ t('settings.materialImport.duplicateAddBatch') }}</option>
                    <option value="skip">{{ t('settings.materialImport.duplicateSkip') }}</option>
                    <option value="create">{{ t('settings.materialImport.duplicateCreate') }}</option>
                  </select>
                  <span v-else class="muted">—</span>
                </td>
                <td class="status-cell">
                  <span v-if="previewByIndex[idx]?.errors?.length" class="badge badge-error" :title="previewByIndex[idx].errors.join(', ')">
                    {{ t('settings.materialImport.statusError') }}
                  </span>
                  <span v-else-if="previewByIndex[idx]?.warnings?.length" class="badge badge-warn">
                    {{ t('settings.materialImport.statusWarn') }}
                  </span>
                  <span v-else-if="previewByIndex[idx]" class="badge badge-ok">
                    {{ t('settings.materialImport.statusOk') }}
                  </span>
                  <span v-else-if="row._existingMaterialId" class="badge badge-dup">
                    {{ t('settings.materialImport.statusDuplicate') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="!showMappingPanel" class="card empty-card">
        <p>{{ t('settings.materialImport.emptyHint') }}</p>
      </div>
    </template>

    <div v-if="showDuplicateDialog" class="modal-overlay" @click.self="showDuplicateDialog = false">
      <div class="modal-dialog">
        <h3>{{ t('settings.materialImport.duplicateDialogTitle') }}</h3>
        <p>{{ t('settings.materialImport.duplicateDialogBody', { count: duplicateCount }) }}</p>
        <label class="duplicate-default">
          {{ t('settings.materialImport.duplicateDefault') }}
          <select v-model="defaultDuplicateAction" class="form-select-sm">
            <option value="add_batch">{{ t('settings.materialImport.duplicateAddBatch') }}</option>
            <option value="skip">{{ t('settings.materialImport.duplicateSkip') }}</option>
            <option value="create">{{ t('settings.materialImport.duplicateCreate') }}</option>
          </select>
        </label>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="showDuplicateDialog = false">{{ t('common.cancel') }}</button>
          <button type="button" class="btn-primary" :disabled="isImporting" @click="confirmImport">
            {{ t('settings.materialImport.importSubmit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getMaterials, type Material } from '@/api/materials'
import { getAddresses, type Address } from '@/api/addresses'
import { importMaterials, type MaterialImportResultRow, type MaterialImportDuplicateAction } from '@/api/materialImport'
import {
  rowsToApiPayload,
  downloadTemplateCsv,
  acquiredDateFromYear,
  readImportMatrixFromFile,
  parseMatrixWithMapping,
  buildSuggestedMapping,
  getColumnLabels,
  excelColumnLetter,
  mappingToColumnAssignments,
  columnAssignmentsToMapping,
  getSourcePreviewRows,
  IMPORT_UI_FIELDS,
  type MaterialImportRow,
  type MaterialImportColumn,
  type ColumnMapping,
  type ColumnAssignment,
  type ImportFileRaw,
} from '@/utils/materialImportParse'

const GLOBAL_SUPPLIER_DEPARTMENT_ID = 'GLOBAL000000'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => route.params.departmentId as string)
const activeTab = ref<'import' | 'export'>('import')
const rows = ref<MaterialImportRow[]>([])
const rawImport = ref<ImportFileRaw | null>(null)
const showMappingPanel = ref(false)
const headerRowIndex = ref(0)
const columnMapping = ref<ColumnMapping>({})
const columnAssignments = ref<ColumnAssignment[]>([])
const importUiFields = IMPORT_UI_FIELDS
const fileName = ref('')
const materials = ref<Material[]>([])
const supplierOptions = ref<Address[]>([])
const defaultDuplicateAction = ref<MaterialImportDuplicateAction>('add_batch')
const previewRows = ref<MaterialImportResultRow[]>([])
const isValidating = ref(false)
const isImporting = ref(false)
const showDuplicateDialog = ref(false)

const isBusy = computed(() => isValidating.value || isImporting.value)

const previewByIndex = computed(() => {
  const map: Record<number, MaterialImportResultRow> = {}
  for (const pr of previewRows.value) {
    const idx = rows.value.findIndex((r) => r.row_index === pr.row_index)
    if (idx >= 0) map[idx] = pr
  }
  return map
})

const duplicateCount = computed(() => rows.value.filter((r) => r._existingMaterialId).length)

const fileColumnLabels = computed(() => {
  if (!rawImport.value) return [] as string[]
  return getColumnLabels(rawImport.value.matrix, headerRowIndex.value)
})

const headerRowOptions = computed(() => {
  const len = rawImport.value?.matrix.filter((r) => r.some((c) => c)).length ?? 0
  return Array.from({ length: Math.min(Math.max(len, 1), 25) }, (_, i) => i)
})

const tableColumnCount = computed(() => {
  if (!rawImport.value) return 0
  const m = rawImport.value.matrix
  let max = 0
  const from = headerRowIndex.value
  const to = Math.min(m.length, from + 25)
  for (let i = from; i < to; i++) {
    max = Math.max(max, m[i]?.length ?? 0)
  }
  return max
})

const tableColumnIndices = computed(() =>
  Array.from({ length: tableColumnCount.value }, (_, i) => i),
)

const sourcePreviewRows = computed(() => {
  if (!rawImport.value) return [] as string[][]
  return getSourcePreviewRows(rawImport.value.matrix, headerRowIndex.value, 12)
})

const hasBlockingErrors = computed(() => {
  if (rows.value.some((r) => !r.name.trim() || !(parseInt(r.qty, 10) > 0))) return true
  if (previewRows.value.some((pr) => pr.errors?.length > 0)) return true
  return false
})

function normalizeName(name: string): string {
  return name.trim().toLowerCase()
}

function enrichWithExisting(list: MaterialImportRow[]) {
  const byName = new Map<string, Material>()
  for (const m of materials.value) {
    byName.set(normalizeName(m.name), m)
  }
  for (const row of list) {
    const hit = byName.get(normalizeName(row.name))
    row._existingMaterialId = hit?.id ?? null
    row._existingMaterialName = hit?.name ?? null
    if (hit && !row.duplicate_action) {
      row.duplicate_action = defaultDuplicateAction.value
    }
  }
}

function syncAcquiredOn(row: MaterialImportRow) {
  const y = row.acquired_year.trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(y)) {
    row.acquired_on = y
    return
  }
  if (/^\d{4}$/.test(y)) {
    row.acquired_on = acquiredDateFromYear(y)
    return
  }
  row.acquired_on = ''
}

function rowErrors(idx: number): string[] {
  const row = rows.value[idx]
  const errs: string[] = []
  if (!row.name.trim()) errs.push(t('settings.materialImport.errName'))
  if (!(parseInt(row.qty, 10) > 0)) errs.push(t('settings.materialImport.errQty'))
  if (!row.acquired_on && !/^\d{4}$/.test(row.acquired_year.trim())) {
    errs.push(t('settings.materialImport.errYear'))
  }
  const pr = previewByIndex.value[idx]
  if (pr?.errors?.length) errs.push(...pr.errors)
  return errs
}

function supplierLabel(addr: Address): string {
  return (addr.name || addr.company || '').trim()
}

function onSupplierNameChange(row: MaterialImportRow) {
  const key = normalizeName(row.supplier_name)
  const hit = supplierOptions.value.find((s) => normalizeName(supplierLabel(s)) === key)
  row.supplier_id = hit?.id ?? ''
}

async function loadSuppliers() {
  const [local, global] = await Promise.all([
    getAddresses(departmentId.value, 'supplier').catch(() => ({ addresses: [] as Address[] })),
    getAddresses(GLOBAL_SUPPLIER_DEPARTMENT_ID, 'supplier').catch(() => ({ addresses: [] as Address[] })),
  ])
  const merged = [...(local.addresses || [])]
  for (const g of global.addresses || []) {
    if (!merged.some((a) => a.id === g.id)) merged.push(g)
  }
  supplierOptions.value = merged
}

async function loadMaterials() {
  materials.value = await getMaterials(departmentId.value).catch(() => [])
}

function mappingFieldLabel(field: MaterialImportColumn): string {
  const key = `settings.materialImport.mappingField.${field}` as const
  return t(key)
}

function syncColumnAssignmentsFromMapping() {
  columnAssignments.value = mappingToColumnAssignments(
    tableColumnCount.value,
    columnMapping.value,
  )
}

function onColumnFieldSelect(colIdx: number, field: string) {
  const next = [...columnAssignments.value]
  while (next.length < tableColumnCount.value) next.push('')
  const f = field as ColumnAssignment
  if (f) {
    for (let i = 0; i < next.length; i++) {
      if (i !== colIdx && next[i] === f) next[i] = ''
    }
  }
  next[colIdx] = f
  columnAssignments.value = next
  columnMapping.value = columnAssignmentsToMapping(next)
}

function onHeaderRowChange() {
  columnMapping.value = buildSuggestedMapping(fileColumnLabels.value)
  syncColumnAssignmentsFromMapping()
}

function resetSuggestedMapping() {
  columnMapping.value = { ...buildSuggestedMapping(fileColumnLabels.value) }
  syncColumnAssignmentsFromMapping()
}

function applyColumnMapping() {
  if (!rawImport.value) return
  if (columnMapping.value.name === undefined) {
    toast.error(t('settings.materialImport.mappingNameRequired'))
    return
  }
  const parsed = parseMatrixWithMapping(
    rawImport.value.matrix,
    headerRowIndex.value,
    columnMapping.value,
  )
  if (parsed.length === 0) {
    toast.error(t('settings.materialImport.parseEmpty'))
    return
  }
  enrichWithExisting(parsed)
  rows.value = parsed
  previewRows.value = []
  showMappingPanel.value = false
  toast.success(t('settings.materialImport.parseSuccess', { count: parsed.length }))
}

function onDownloadTemplate() {
  downloadTemplateCsv(import.meta.env.BASE_URL || '/')
}

async function onFileSelected(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  fileName.value = file.name
  try {
    const raw = await readImportMatrixFromFile(file)
    if (!raw.matrix.some((r) => r.some((c) => c))) {
      toast.error(t('settings.materialImport.parseEmpty'))
      return
    }
    rawImport.value = raw
    headerRowIndex.value = raw.headerRowIndex
    columnMapping.value = { ...raw.suggestedMapping }
    syncColumnAssignmentsFromMapping()
    rows.value = []
    previewRows.value = []
    showMappingPanel.value = true
    toast.info(t('settings.materialImport.mappingPleaseMap'))
  } catch (e) {
    console.error(e)
    toast.error(t('settings.materialImport.parseFailed'))
  }
  input.value = ''
}

async function runDryRun() {
  if (!rows.value.length) return
  isValidating.value = true
  try {
    for (const row of rows.value) syncAcquiredOn(row)
    const res = await importMaterials(departmentId.value, rowsToApiPayload(rows.value), {
      dryRun: true,
      defaultDuplicateAction: defaultDuplicateAction.value,
    })
    previewRows.value = res.rows || []
    if (res.stats?.errors) {
      toast.warning(t('settings.materialImport.validateWithErrors', { n: res.stats.errors }))
    } else {
      toast.success(t('settings.materialImport.validateOk'))
    }
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(msg || t('settings.materialImport.importFailed'))
  } finally {
    isValidating.value = false
  }
}

function onImportClick() {
  if (duplicateCount.value > 0) {
    showDuplicateDialog.value = true
    return
  }
  void confirmImport()
}

async function confirmImport() {
  showDuplicateDialog.value = false
  isImporting.value = true
  try {
    for (const row of rows.value) syncAcquiredOn(row)
    const res = await importMaterials(departmentId.value, rowsToApiPayload(rows.value), {
      dryRun: false,
      defaultDuplicateAction: defaultDuplicateAction.value,
    })
    const s = res.stats
    toast.success(
      t('settings.materialImport.importSuccess', {
        created: s?.created ?? 0,
        batches: s?.batches_added ?? 0,
        skipped: s?.skipped ?? 0,
      }),
    )
    await loadMaterials()
    enrichWithExisting(rows.value)
    previewRows.value = res.rows || []
    if ((s?.errors ?? 0) > 0) {
      toast.warning(t('settings.materialImport.importPartial', { n: s?.errors ?? 0 }))
    }
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(msg || t('settings.materialImport.importFailed'))
  } finally {
    isImporting.value = false
  }
}

watch(defaultDuplicateAction, (action) => {
  for (const row of rows.value) {
    if (row._existingMaterialId) row.duplicate_action = action
  }
})

onMounted(async () => {
  await Promise.all([loadMaterials(), loadSuppliers()])
})
</script>

<style scoped>
.material-import-settings {
  max-width: 1200px;
}

.settings-header {
  margin-bottom: 1.5rem;
}

.settings-header h1 {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
}

.subtitle {
  margin: 0;
  color: var(--text-muted, #6b7280);
}

.tab-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.tab-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}

.tab-btn.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1rem;
}

.actions-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.file-input {
  display: none;
}

.file-label {
  cursor: pointer;
  margin: 0;
}

.file-name {
  color: #6b7280;
  font-size: 0.875rem;
}

.hint {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0 0 1rem;
}

.preview-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.preview-toolbar h2 {
  margin: 0;
  font-size: 1.125rem;
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.duplicate-default {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.form-select-sm {
  padding: 0.35rem 0.5rem;
  border-radius: 6px;
  border: 1px solid #d1d5db;
}

.table-wrap {
  overflow-x: auto;
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.preview-table th,
.preview-table td {
  border: 1px solid #e5e7eb;
  padding: 0.35rem 0.5rem;
  vertical-align: middle;
}

.preview-table th {
  background: #f9fafb;
  text-align: left;
  white-space: nowrap;
}

.cell-input {
  width: 100%;
  min-width: 80px;
  padding: 0.25rem 0.35rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 0.875rem;
}

.cell-input-narrow {
  min-width: 56px;
  max-width: 90px;
}

.cell-select {
  font-size: 0.8rem;
  max-width: 140px;
}

.row-error {
  background: #fef2f2;
}

.row-duplicate {
  background: #fffbeb;
}

.badge {
  display: inline-block;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  font-size: 0.75rem;
}

.badge-error {
  background: #fecaca;
  color: #991b1b;
}

.badge-warn {
  background: #fde68a;
  color: #92400e;
}

.badge-ok {
  background: #bbf7d0;
  color: #166534;
}

.badge-dup {
  background: #fde68a;
  color: #92400e;
}

.muted {
  color: #9ca3af;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-dialog {
  background: #fff;
  border-radius: 12px;
  padding: 1.5rem;
  max-width: 420px;
  width: 90%;
}

.modal-dialog h3 {
  margin: 0 0 0.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

.export-placeholder h2 {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
}

.empty-card p {
  margin: 0;
  color: #6b7280;
}

.btn-sm {
  padding: 0.35rem 0.75rem;
  font-size: 0.875rem;
}

.mapping-card h2 {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
}

.mapping-hint {
  margin: 0 0 1rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.mapping-row-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.mapping-table-hint {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.source-table-wrap {
  max-height: 420px;
  overflow: auto;
  margin-bottom: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.source-mapping-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.source-mapping-table th,
.source-mapping-table td {
  border: 1px solid #e5e7eb;
  padding: 0.35rem 0.5rem;
  vertical-align: top;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mapping-dropdown-row th {
  background: #eff6ff;
  position: sticky;
  top: 0;
  z-index: 2;
}

.source-file-header-row th {
  background: #f9fafb;
  font-weight: 500;
  color: #374151;
  position: sticky;
  top: 2.6rem;
  z-index: 1;
}

.col-letter {
  display: inline-block;
  min-width: 1.25rem;
  margin-right: 0.35rem;
  font-weight: 600;
  color: #6b7280;
}

.col-file-label {
  font-weight: 400;
}

.column-field-select {
  width: 100%;
  min-width: 120px;
  max-width: 180px;
  padding: 0.35rem 0.4rem;
  border: 1px solid #93c5fd;
  border-radius: 6px;
  font-size: 0.8125rem;
  background: #fff;
}

.column-field-select--mapped {
  border-color: #2563eb;
  background: #f0f9ff;
}

.source-mapping-table td.cell-mapped {
  background: #f0fdf4;
}

.source-mapping-table tbody tr:nth-child(even) td {
  background: #fafafa;
}

.source-mapping-table tbody tr:nth-child(even) td.cell-mapped {
  background: #ecfdf5;
}

.mapping-actions {
  display: flex;
  justify-content: flex-end;
}
</style>
