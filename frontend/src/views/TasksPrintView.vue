<template>
  <div class="tasks-print-panel">
    <div class="page-header header-content print-toolbar">
      <div class="header-left">
        <span class="print-panel-label">Drucken</span>
        <span class="subtitle">Druckkorb für QR-Etiketten (DB-basiert)</span>
      </div>
      <div class="header-right">
        <button class="btn-outline btn-sm" :disabled="isLoading || items.length === 0" @click="printAll">
          Sammeldruck
        </button>
        <button class="btn-outline btn-sm" :disabled="isLoading || items.length === 0" @click="markAllAsPrinted">
          Alle als gedruckt markieren
        </button>
        <button class="btn-secondary btn-sm" :disabled="isLoading || items.length === 0" @click="clearAll">
          Leeren
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Druckkorb wird geladen...</p>
    </div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>Keine Druckaufgaben</h3>
      <p>Es sind keine Einträge im Druckkorb.</p>
    </div>
    <div v-else class="tasks-table-wrapper">
      <table class="tasks-table">
        <thead>
          <tr>
            <th>Typ</th>
            <th>Bezeichnung</th>
            <th>Code</th>
            <th>Erstellt</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.entity_type }}</td>
            <td>{{ item.label }}</td>
            <td><code>{{ item.public_code || '-' }}</code></td>
            <td>{{ formatDate(item.created_at) }}</td>
            <td class="tasks-actions-cell">
              <button type="button" class="btn-outline btn-sm" @click="printOne(item)">Drucken</button>
              <button type="button" class="btn-outline btn-sm" @click="markPrinted(item.id)">Gedruckt</button>
              <button type="button" class="btn-secondary btn-sm" @click="removeItem(item.id)">Entfernen</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
import { useToast } from '@/composables/useToast'
import { clearPrintCart, deletePrintCartItem, getPrintCartItems, markPrintCartItemPrinted, type PrintCartItem } from '@/api/tasks'
import { printHtmlDocument } from '@/utils/printHtml'

const route = useRoute()
const toast = useToast()
const isLoading = ref(false)
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
    toast.error(err?.response?.data?.error || 'Druckkorb konnte nicht geladen werden.')
    items.value = []
  } finally {
    isLoading.value = false
  }
}

function formatDate(value: string): string {
  if (!value) return '-'
  return new Date(value).toLocaleString('de-CH')
}

async function printOne(item: PrintCartItem) {
  if (!item.public_url) return
  const qr = await QRCode.toDataURL(item.public_url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html><html><head><meta charset="utf-8" />
  <style>body{font-family:Arial,sans-serif;margin:20px}.card{max-width:360px;border:1px solid #d1d5db;border-radius:10px;padding:14px;text-align:center}img{width:240px;height:240px;object-fit:contain}.title{margin-top:10px;font-weight:700}.code{margin-top:4px;font-family:monospace;color:#4b5563}</style>
  </head><body><div class="card"><img src="${qr}" alt="QR" /><div class="title">${escapeHtml(item.label)}</div><div class="code">${escapeHtml(item.public_code || '-')}</div></div></body></html>`)
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
      <img src="${qr}" alt="QR" />
      <div class="title">${escapeHtml(item.label)}</div>
      <div class="code">${escapeHtml(item.public_code || '-')}</div>
    </div>
  `).join('')
  printHtmlDocument(`<!doctype html><html><head><meta charset="utf-8" />
  <style>body{font-family:Arial,sans-serif;margin:18px}h1{margin:0 0 14px;font-size:18px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}.card{border:1px solid #d1d5db;border-radius:10px;padding:10px;text-align:center;page-break-inside:avoid}img{width:160px;height:160px;object-fit:contain}.title{margin-top:8px;font-weight:700;font-size:13px}.code{margin-top:4px;font-family:monospace;color:#4b5563;font-size:12px}</style>
  </head><body><h1>Druckkorb - QR-Codes</h1><div class="grid">${cards}</div></body></html>`)
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
