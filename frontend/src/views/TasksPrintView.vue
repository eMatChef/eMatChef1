<template>
  <div class="tasks-print-panel">
    <div class="page-header header-content print-toolbar">
      <div class="header-left">
        <span class="print-panel-label">{{ t('common.print') }}</span>
        <span class="subtitle">{{ t('tasksPrint.subtitle') }}</span>
        <div class="print-subtabs" role="tablist">
          <button
            type="button"
            class="print-subtab"
            :class="{ 'print-subtab--on': panel === 'cart' }"
            role="tab"
            :aria-selected="panel === 'cart'"
            @click="panel = 'cart'"
          >
            {{ t('tasksPrint.tabCart') }}
          </button>
          <button
            type="button"
            class="print-subtab"
            :class="{ 'print-subtab--on': panel === 'devices' }"
            role="tab"
            :aria-selected="panel === 'devices'"
            @click="panel = 'devices'"
          >
            {{ t('tasksPrint.tabDevices') }}
          </button>
          <button
            type="button"
            class="print-subtab"
            :class="{ 'print-subtab--on': panel === 'layouts' }"
            role="tab"
            :aria-selected="panel === 'layouts'"
            @click="panel = 'layouts'"
          >
            {{ t('tasksPrint.tabLayouts') }}
          </button>
        </div>
      </div>
      <div v-if="panel === 'cart'" class="header-right">
        <button
          class="btn-outline btn-sm"
          :disabled="isLoading"
          @click="openStorageQrPdfDialog"
        >
          {{ t('tasksPrint.exportStorageQrPdf') }}
        </button>
        <button
          class="btn-outline btn-sm"
          :disabled="isLoading"
          @click="openMaterialQrPdfDialog"
        >
          {{ t('tasksPrint.exportMaterialQrPdf') }}
        </button>
        <button class="btn-outline btn-sm" :disabled="isLoading || printing || items.length === 0" @click="printAll">
          {{ t('tasksPrint.bulkPrint') }}
        </button>
        <button class="btn-outline btn-sm" :disabled="isLoading || items.length === 0" @click="markAllAsPrinted">
          {{ t('tasksPrint.markAllAsPrinted') }}
        </button>
        <button class="btn-secondary btn-sm" :disabled="isLoading || items.length === 0" @click="clearAll">
          {{ t('tasksPrint.clearAll') }}
        </button>
      </div>
    </div>

    <DepartmentPrintSettingsPanel v-if="panel === 'devices'" :department-id="departmentId" sections="devices" />
    <PrintLayoutEditor v-else-if="panel === 'layouts'" :department-id="departmentId" />

    <template v-else>
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('common.loading') }}</p>
    </div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>{{ t('tasksPrint.emptyTitle') }}</h3>
      <p>{{ t('tasksPrint.emptyDescription') }}</p>
    </div>
    <div v-else class="tasks-table-wrapper">
      <div class="layout-bar">
        <label class="layout-field">
          <span>{{ t('tasksPrint.layout') }}</span>
          <select v-model="selectedLayoutId">
            <option value="">{{ t('tasksPrint.layoutNone') }}</option>
            <option v-for="layout in layouts" :key="layout.id" :value="layout.id">
              {{ layout.name }} · {{ layout.media.name }}
            </option>
          </select>
        </label>
        <label v-if="selectedLayout" class="layout-field">
          <span>{{ t('tasksPrint.startCell') }}</span>
          <input
            v-model.number="startCell"
            type="number"
            min="1"
            :max="Math.max(1, selectedLayout.cells.length || 1)"
          />
        </label>
        <button
          v-if="selectedLayout && isBrotherQlLayout(selectedLayout)"
          type="button"
          class="btn-outline btn-sm"
          :disabled="printing"
          @click="printQl(items)"
        >
          {{ t('tasksPrint.sendQl') }}
        </button>
      </div>
      <p v-if="!selectedLayout" class="layout-fallback">{{ t('tasksPrint.noLayoutFallback') }}</p>
      <table class="tasks-table">
        <thead>
          <tr>
            <th>{{ t('tasksPrint.table.type') }}</th>
            <th>{{ t('tasksPrint.table.label') }}</th>
            <th>{{ t('tasksPrint.table.code') }}</th>
            <th>{{ t('tasksPrint.table.created') }}</th>
            <th>{{ t('tasksPrint.table.action') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ entityTypeLabel(item.entity_type) }}</td>
            <td>{{ item.label }}</td>
            <td><code>{{ item.public_code || t('tasksPrint.codeFallback') }}</code></td>
            <td>{{ formatDate(item.created_at) }}</td>
            <td class="tasks-actions-cell">
              <button type="button" class="btn-outline btn-sm" :disabled="printing" @click="printOne(item)">{{ t('common.print') }}</button>
              <button type="button" class="btn-outline btn-sm" @click="markPrinted(item.id)">{{ t('tasksPrint.markPrinted') }}</button>
              <button type="button" class="btn-secondary btn-sm" @click="removeItem(item.id)">{{ t('common.remove') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>

    <StorageLocationQrPdfDialog
      v-model="showStorageQrPdfDialog"
      :department-id="departmentId"
      pick-location
    />

    <MaterialCategoryQrPdfDialog
      v-model="showMaterialQrPdfDialog"
      :department-id="departmentId"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { useToast } from '@/composables/useToast'
import { clearPrintCart, deletePrintCartItem, getPrintCartItems, markPrintCartItemPrinted, type PrintCartItem } from '@/api/tasks'
import { listDepartmentPrintLayouts, type PrintLayout } from '@/api/printLayouts'
import { printHtmlDocument } from '@/utils/printHtml'
import StorageLocationQrPdfDialog from '@/components/storage/StorageLocationQrPdfDialog.vue'
import MaterialCategoryQrPdfDialog from '@/components/material/MaterialCategoryQrPdfDialog.vue'
import DepartmentPrintSettingsPanel from '@/components/print/DepartmentPrintSettingsPanel.vue'
import PrintLayoutEditor from '@/components/print/PrintLayoutEditor.vue'
import { downloadCartLayoutPdf, isBrotherQlLayout, printCartLayoutToQl } from '@/print/printCartLayout'
import { requestBrotherQlDevice } from '@/print/brotherQlUsb'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const isLoading = ref(false)
const printing = ref(false)
const showStorageQrPdfDialog = ref(false)
const showMaterialQrPdfDialog = ref(false)
const panel = ref<'cart' | 'devices' | 'layouts'>('cart')
const items = ref<PrintCartItem[]>([])
const layouts = ref<PrintLayout[]>([])
const selectedLayoutId = ref('')
const startCell = ref(1)
const qlDevice = ref<USBDevice | null>(null)
const departmentId = computed(() => String(route.params.departmentId || ''))
const selectedLayout = computed(() => layouts.value.find((item) => item.id === selectedLayoutId.value) || null)

function layoutStorageKey(dept: string): string {
  return `ematchef.print-layout.${dept}`
}

function escapeHtml(raw: string): string {
  return String(raw || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    const [nextItems, nextLayouts] = await Promise.all([
      getPrintCartItems(departmentId.value),
      listDepartmentPrintLayouts(departmentId.value).catch(() => [] as PrintLayout[]),
    ])
    items.value = nextItems
    layouts.value = nextLayouts.filter((item) => item.status === 'published')
    const stored = localStorage.getItem(layoutStorageKey(departmentId.value)) || ''
    selectedLayoutId.value = layouts.value.some((item) => item.id === stored)
      ? stored
      : (layouts.value[0]?.id || '')
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('tasksPrint.errors.loadCart'))
    items.value = []
  } finally {
    isLoading.value = false
  }
}

function entityTypeLabel(entityType: string): string {
  const key = `tasksPrint.entityTypes.${entityType}`
  const label = t(key)
  return label !== key ? label : entityType
}

function formatDate(value: string): string {
  if (!value) return t('tasksPrint.codeFallback')
  return new Date(value).toLocaleString('de-CH')
}

async function printWithLayout(rows: PrintCartItem[]) {
  const layout = selectedLayout.value
  if (!layout) return false
  printing.value = true
  try {
    await downloadCartLayoutPdf(departmentId.value, layout, rows, Math.max(0, startCell.value - 1))
    toast.success(t('tasksPrint.printPdfOk'))
    return true
  } catch (e: unknown) {
    toast.error((e as Error).message || t('tasksPrint.printLayoutError'))
    return true
  } finally {
    printing.value = false
  }
}

async function printQl(rows: PrintCartItem[]) {
  const layout = selectedLayout.value
  if (!layout) return
  printing.value = true
  try {
    if (!qlDevice.value) qlDevice.value = await requestBrotherQlDevice()
    await printCartLayoutToQl(qlDevice.value, layout, rows)
    toast.success(t('printLayout.qlSent'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.qlError'))
  } finally {
    printing.value = false
  }
}

async function printOne(item: PrintCartItem) {
  if (await printWithLayout([item])) return
  if (!item.public_url) return
  const qr = await QRCode.toDataURL(item.public_url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html><html><head><meta charset="utf-8" />
  <style>body{font-family:Arial,sans-serif;margin:20px}.card{max-width:360px;border:1px solid #d1d5db;border-radius:10px;padding:14px;text-align:center}img{width:240px;height:240px;object-fit:contain}.title{margin-top:10px;font-weight:700}.code{margin-top:4px;font-family:monospace;color:#4b5563}</style>
  </head><body><div class="card"><img src="${qr}" alt="${escapeHtml(t('tasksPrint.printHtml.qrAlt'))}" /><div class="title">${escapeHtml(item.label)}</div><div class="code">${escapeHtml(item.public_code || t('tasksPrint.codeFallback'))}</div></div></body></html>`)
}

async function printAll() {
  if (await printWithLayout(items.value)) return
  const rows = await Promise.all(
    items.value.map(async (item) => ({
      item,
      qr: item.public_url ? await QRCode.toDataURL(item.public_url, { width: 220, margin: 1 }) : '',
    }))
  )
  const cards = rows
    .filter(({ item }) => item.public_url)
    .map(({ item, qr }) => `
    <div class="card">
      <img src="${qr}" alt="${escapeHtml(t('tasksPrint.printHtml.qrAlt'))}" />
      <div class="title">${escapeHtml(item.label)}</div>
      <div class="code">${escapeHtml(item.public_code || t('tasksPrint.codeFallback'))}</div>
    </div>
  `).join('')
  printHtmlDocument(`<!doctype html><html><head><meta charset="utf-8" />
  <style>body{font-family:Arial,sans-serif;margin:18px}h1{margin:0 0 14px;font-size:18px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}.card{border:1px solid #d1d5db;border-radius:10px;padding:10px;text-align:center;page-break-inside:avoid}img{width:160px;height:160px;object-fit:contain}.title{margin-top:8px;font-weight:700;font-size:13px}.code{margin-top:4px;font-family:monospace;color:#4b5563;font-size:12px}</style>
  </head><body><h1>${escapeHtml(t('tasksPrint.printHtml.bulkTitle'))}</h1><div class="grid">${cards}</div></body></html>`)
}

function openStorageQrPdfDialog() {
  showStorageQrPdfDialog.value = true
}

function openMaterialQrPdfDialog() {
  showMaterialQrPdfDialog.value = true
}

async function markPrinted(id: string) {
  await markPrintCartItemPrinted(id)
  items.value = items.value.filter((x) => x.id !== id)
}

async function markAllAsPrinted() {
  const ids = items.value.map((x) => x.id)
  for (const id of ids) {
    await markPrintCartItemPrinted(id)
  }
  items.value = []
}

async function removeItem(id: string) {
  await deletePrintCartItem(id)
  items.value = items.value.filter((x) => x.id !== id)
}

async function clearAll() {
  if (!departmentId.value) return
  await clearPrintCart(departmentId.value)
  items.value = []
}

watch(selectedLayoutId, (id) => {
  if (departmentId.value) localStorage.setItem(layoutStorageKey(departmentId.value), id)
})
watch(
  () => selectedLayout.value?.cells.length,
  (n) => {
    if (!n || startCell.value > n) startCell.value = 1
  },
)

onMounted(load)
</script>

<style scoped>
.print-toolbar {
  margin-bottom: 20px;
}

.print-panel-label {
  display: block;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 2px;
}

.print-subtabs {
  display: flex;
  gap: 6px;
  margin-top: 10px;
}

.print-subtab {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 13px;
  cursor: pointer;
}

.print-subtab--on {
  border-color: #10b981;
  background: #ecfdf3;
  font-weight: 600;
}

.layout-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  margin-bottom: 12px;
}

.layout-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: #4b5563;
}

.layout-field select,
.layout-field input {
  min-height: 32px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 4px 8px;
  background: #fff;
}

.layout-fallback {
  margin: 0 0 12px;
  color: #6b7280;
  font-size: 13px;
}

.tasks-actions-cell {
  white-space: nowrap;
}
.tasks-actions-cell .btn-outline,
.tasks-actions-cell .btn-secondary {
  margin-right: 4px;
}
.tasks-actions-cell .btn-outline:last-child,
.tasks-actions-cell .btn-secondary:last-child {
  margin-right: 0;
}
</style>
