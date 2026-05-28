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

      <div v-if="rows.length > 0" class="card preview-card">
        <div class="preview-toolbar">
          <h2>{{ t('settings.materialImport.previewTitle', { count: rows.length }) }}</h2>
          <div class="toolbar-actions">
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

      <div v-else class="card empty-card">
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
  parseImportFile,
  rowsToApiPayload,
  downloadTemplateCsv,
  acquiredDateFromYear,
  type MaterialImportRow,
} from '@/utils/materialImportParse'

const GLOBAL_SUPPLIER_DEPARTMENT_ID = 'GLOBAL000000'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => route.params.departmentId as string)
const activeTab = ref<'import' | 'export'>('import')
const rows = ref<MaterialImportRow[]>([])
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

function onDownloadTemplate() {
  downloadTemplateCsv(import.meta.env.BASE_URL || '/')
}

async function onFileSelected(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  fileName.value = file.name
  try {
    const parsed = await parseImportFile(file)
    if (parsed.length === 0) {
      toast.error(t('settings.materialImport.parseEmpty'))
      return
    }
    enrichWithExisting(parsed)
    rows.value = parsed
    previewRows.value = []
    toast.success(t('settings.materialImport.parseSuccess', { count: parsed.length }))
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
</style>
