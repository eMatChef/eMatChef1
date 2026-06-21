<template>
  <div class="tasks-print-panel">
    <div class="page-header header-content print-toolbar">
      <div class="header-left">
        <span class="print-panel-label">{{ t('common.print') }}</span>
        <span class="subtitle">{{ t('tasksPrint.subtitle') }}</span>
      </div>
      <div class="header-right">
        <button
          class="btn-outline btn-sm"
          :disabled="isLoading"
          @click="openStorageQrPdfDialog"
        >
          {{ t('tasksPrint.exportStorageQrPdf') }}
        </button>
        <button
          class="btn-outline btn-sm"
          :disabled="isLoading || isExportingPdf"
          @click="exportMaterialQrPdf"
        >
          {{ isExportingPdf ? t('tasksPrint.exportMaterialQrPdfLoading') : t('tasksPrint.exportMaterialQrPdf') }}
        </button>
        <button class="btn-outline btn-sm" :disabled="isLoading || items.length === 0" @click="printAll">
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

    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('common.loading') }}</p>
    </div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>{{ t('tasksPrint.emptyTitle') }}</h3>
      <p>{{ t('tasksPrint.emptyDescription') }}</p>
    </div>
    <div v-else class="tasks-table-wrapper">
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
              <button type="button" class="btn-outline btn-sm" @click="printOne(item)">{{ t('common.print') }}</button>
              <button type="button" class="btn-outline btn-sm" @click="markPrinted(item.id)">{{ t('tasksPrint.markPrinted') }}</button>
              <button type="button" class="btn-secondary btn-sm" @click="removeItem(item.id)">{{ t('common.remove') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <StorageLocationQrPdfDialog
      v-model="showStorageQrPdfDialog"
      :department-id="departmentId"
      pick-location
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { useToast } from '@/composables/useToast'
import { clearPrintCart, deletePrintCartItem, downloadMaterialQrPdf, getPrintCartItems, markPrintCartItemPrinted, type PrintCartItem } from '@/api/tasks'
import { printHtmlDocument } from '@/utils/printHtml'
import StorageLocationQrPdfDialog from '@/components/storage/StorageLocationQrPdfDialog.vue'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const isLoading = ref(false)
const isExportingPdf = ref(false)
const showStorageQrPdfDialog = ref(false)
const items = ref<PrintCartItem[]>([])
const departmentId = computed(() => String(route.params.departmentId || ''))

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
    items.value = await getPrintCartItems(departmentId.value)
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

async function printOne(item: PrintCartItem) {
  if (!item.public_url) return
  const qr = await QRCode.toDataURL(item.public_url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html><html><head><meta charset="utf-8" />
  <style>body{font-family:Arial,sans-serif;margin:20px}.card{max-width:360px;border:1px solid #d1d5db;border-radius:10px;padding:14px;text-align:center}img{width:240px;height:240px;object-fit:contain}.title{margin-top:10px;font-weight:700}.code{margin-top:4px;font-family:monospace;color:#4b5563}</style>
  </head><body><div class="card"><img src="${qr}" alt="${escapeHtml(t('tasksPrint.printHtml.qrAlt'))}" /><div class="title">${escapeHtml(item.label)}</div><div class="code">${escapeHtml(item.public_code || t('tasksPrint.codeFallback'))}</div></div></body></html>`)
}

async function printAll() {
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

async function exportMaterialQrPdf() {
  if (!departmentId.value || isExportingPdf.value) return
  isExportingPdf.value = true
  try {
    const blob = await downloadMaterialQrPdf(departmentId.value)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `material-qr-codes-${departmentId.value}.pdf`
    anchor.click()
    URL.revokeObjectURL(url)
    toast.success(t('tasksPrint.exportMaterialQrPdfSuccess'))
  } catch (err: any) {
    toast.error(err?.message || err?.response?.data?.error || t('tasksPrint.errors.exportMaterialQrPdf'))
  } finally {
    isExportingPdf.value = false
  }
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
