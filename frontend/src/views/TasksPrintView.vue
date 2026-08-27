<template>
  <div class="tasks-print-panel">
    <div class="page-header header-content print-toolbar">
      <div class="header-left">
        <span class="print-panel-label">{{ t('common.print') }}</span>
        <span class="subtitle">{{ t('tasksPrint.subtitle') }}</span>
        <PrintCartQueueHint class="print-queue-hint" />
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

    <PrintLayoutEditor v-if="panel === 'layouts'" :department-id="departmentId" />

    <template v-else>
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('common.loading') }}</p>
    </div>
    <template v-else>
      <div v-if="items.length === 0" class="empty-state">
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
    </template>
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
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { usePrintJob } from '@/composables/usePrintJob'
import { usePrintCart } from '@/composables/usePrintCart'
import { clearPrintCart, deletePrintCartItem, getPrintCartItems, markPrintCartItemPrinted, type PrintCartItem } from '@/api/tasks'
import StorageLocationQrPdfDialog from '@/components/storage/StorageLocationQrPdfDialog.vue'
import MaterialCategoryQrPdfDialog from '@/components/material/MaterialCategoryQrPdfDialog.vue'
import PrintLayoutEditor from '@/components/print/PrintLayoutEditor.vue'
import PrintCartQueueHint from '@/components/print/PrintCartQueueHint.vue'
import type { PrintJobItem } from '@/print/printJob'

const route = useRoute()
const toast = useToast()
const { openPrint } = usePrintJob()
const { setCount: setPrintCartCount } = usePrintCart()
const { t } = useI18n()
const isLoading = ref(false)
const showStorageQrPdfDialog = ref(false)
const showMaterialQrPdfDialog = ref(false)
const panel = ref<'cart' | 'layouts'>('cart')
const items = ref<PrintCartItem[]>([])
const departmentId = computed(() => String(route.params.departmentId || ''))

function toPrintItem(item: PrintCartItem): PrintJobItem | null {
  const url = String(item.public_url || '').trim()
  if (!url) return null
  return {
    label: item.label,
    public_code: item.public_code,
    public_url: url,
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    items.value = await getPrintCartItems(departmentId.value)
    setPrintCartCount(items.value.length)
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('tasksPrint.errors.loadCart'))
    items.value = []
    setPrintCartCount(0)
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

function printOne(item: PrintCartItem) {
  const row = toPrintItem(item)
  if (!row || !departmentId.value) {
    toast.info(t('tasksPrint.noPublicUrl'))
    return
  }
  openPrint({ departmentId: departmentId.value, items: [row], kind: 'label' })
}

function printAll() {
  if (!departmentId.value) return
  const rows = items.value.map(toPrintItem).filter((row): row is PrintJobItem => !!row)
  if (!rows.length) {
    toast.info(t('tasksPrint.noPublicUrl'))
    return
  }
  openPrint({ departmentId: departmentId.value, items: rows, kind: 'label' })
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
  setPrintCartCount(items.value.length)
}

async function markAllAsPrinted() {
  const ids = items.value.map((x) => x.id)
  for (const id of ids) {
    await markPrintCartItemPrinted(id)
  }
  items.value = []
  setPrintCartCount(0)
}

async function removeItem(id: string) {
  await deletePrintCartItem(id)
  items.value = items.value.filter((x) => x.id !== id)
  setPrintCartCount(items.value.length)
}

async function clearAll() {
  if (!departmentId.value) return
  await clearPrintCart(departmentId.value)
  items.value = []
  setPrintCartCount(0)
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

.print-queue-hint {
  margin-top: 4px;
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
