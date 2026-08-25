<template>
  <section class="card">
    <div class="card-head">
      <h2>{{ t('printLayout.title') }}</h2>
      <EButton v-if="allowManage" variant="primary" size="small" @click="createOpen = true">
        {{ t('printLayout.add') }}
      </EButton>
    </div>
    <p class="muted">{{ t('printLayout.hint') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('printLayout.loading')" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <EEmptyState
      v-else-if="layouts.length === 0"
      variant="generic"
      compact
      :title="t('printLayout.emptyTitle')"
      :description="t('printLayout.empty')"
      icon="mdi-view-dashboard-outline"
    />

    <div v-else class="layout-work">
      <ul class="layout-list">
        <li
          v-for="item in layouts"
          :key="item.id"
          class="layout-item"
          :class="{ 'is-on': selected?.id === item.id }"
        >
          <button type="button" class="layout-pick" @click="selectLayout(item)">
            <strong>{{ item.name }}</strong>
            <span class="meta">{{ item.media.name }}</span>
            <span class="chip" :class="item.global_requested ? 'chip--pending' : item.scope === 'organisation' ? 'chip--org' : ''">
              {{ item.global_requested ? t('printSettings.status.pendingGlobal') : t(`printSettings.scope.${item.scope}`) }}
            </span>
          </button>
        </li>
      </ul>

      <div v-if="selected" class="editor">
        <div class="editor-toolbar">
          <label v-if="canEditSelected && isOfficeSheet" class="file-btn">
            {{ t('printLayout.uploadPdf') }}
            <input type="file" accept="application/pdf" class="sr-only" @change="onPdf" />
          </label>
          <ECheckbox
            v-if="isOfficeSheet"
            v-model="includeTemplate"
            :label="t('printLayout.includeTemplate')"
            hide-details
            :disabled="!canEditSelected"
            @update:model-value="persistInclude"
          />
          <EButton v-if="canEditSelected && !selected.global_requested" variant="secondary" size="small" @click="requestGlobal">
            {{ t('printSettings.requestGlobal') }}
          </EButton>
          <EButton v-if="canEditSelected" variant="text" size="small" @click="saveFields">{{ t('common.save') }}</EButton>
          <EButton v-if="canEditSelected" variant="danger" size="small" @click="removeSelected">{{ t('common.remove') }}</EButton>
        </div>

        <div class="sheet-wrap" :style="{ aspectRatio: sheetAspect }">
          <canvas ref="underlayEl" class="underlay" />
          <svg
            class="sheet"
            :viewBox="`0 0 ${selected.sheet.sheet_width_mm} ${selected.sheet.sheet_height_mm}`"
            role="img"
            :aria-label="t('printLayout.sheetAria')"
          >
            <rect
              v-for="cell in selected.cells"
              :key="cell.index"
              :x="cell.x"
              :y="cell.y"
              :width="cell.w"
              :height="cell.h"
              :rx="selected.sheet.shape === 'round' ? cell.w / 2 : 0"
              class="cell"
              :class="{ 'cell--start': cell.index === startIndex }"
              @click="startIndex = cell.index"
            />
            <g
              v-for="field in selected.fields"
              :key="field.id"
              class="field"
              :class="{ 'field--on': field.id === selectedFieldId }"
              @pointerdown="beginDrag($event, field)"
            >
              <rect
                :x="previewCell.x + (field.x / 100) * previewCell.w"
                :y="previewCell.y + (field.y / 100) * previewCell.h"
                :width="(field.w / 100) * previewCell.w"
                :height="(field.h / 100) * previewCell.h"
              />
              <text
                :x="previewCell.x + (field.x / 100) * previewCell.w + 1.5"
                :y="previewCell.y + (field.y / 100) * previewCell.h + Math.min(6, (field.h / 100) * previewCell.h - 1)"
                class="field-label"
              >
                {{ field.type === 'qr' ? 'QR' : field.key }}
              </text>
            </g>
          </svg>
        </div>
        <p class="muted">{{ t('printLayout.startHint', { n: startIndex + 1, total: selected.cells.length }) }}</p>
        <p v-if="isTscDesktop" class="muted">{{ t('printLayout.tscHint') }}</p>

        <div v-if="isBrotherQl" class="ql-row">
          <EButton variant="secondary" size="small" :disabled="!usbOk" @click="connectQl">
            {{ t('printLayout.connectQl') }}
          </EButton>
          <span v-if="qlStatus" class="meta">{{ t('printLayout.qlStatus', { width: qlStatus.mediaWidthMm ?? '–', type: qlStatus.mediaType }) }}</span>
          <span v-else-if="!usbOk" class="meta">{{ t('printLayout.usbHint') }}</span>
        </div>

        <div class="print-row">
          <EButton variant="primary" size="small" :loading="printing" @click="printSample">{{ t('printLayout.printSample') }}</EButton>
          <EButton v-if="qlDevice && isBrotherQl" variant="secondary" size="small" :loading="printing" @click="printQlSample">
            {{ t('printLayout.printQl') }}
          </EButton>
        </div>
      </div>
    </div>

    <EDialog v-model="createOpen" :title="t('printLayout.add')" :max-width="480">
      <div class="dialog-grid">
        <ETextField v-model="createForm.name" :label="t('printLayout.name')" hide-details />
        <ESelect v-model="createForm.media_id" :label="t('printSettings.media')" :items="mediaItems" hide-details />
        <ECheckbox v-model="createForm.request_global" :label="t('printSettings.requestGlobalNow')" hide-details />
      </div>
      <template #actions>
        <EButton variant="text" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!createForm.name.trim() || !createForm.media_id" @click="createLayout">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EButton from '@/components/form/base/EButton.vue'
import ECheckbox from '@/components/form/base/ECheckbox.vue'
import EDialog from '@/components/form/base/EDialog.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getDepartmentPrintCatalog, type PrintMedia } from '@/api/printCatalog'
import {
  createDepartmentPrintLayout,
  deleteDepartmentPrintLayout,
  fetchPrintLayoutTemplateBytes,
  listDepartmentPrintLayouts,
  requestDepartmentPrintLayoutGlobal,
  updateDepartmentPrintLayout,
  uploadPrintLayoutTemplate,
  type PrintLayout,
  type PrintLayoutField,
} from '@/api/printLayouts'
import { printCanvasToBrotherQl, readBrotherQlStatus, requestBrotherQlDevice, webUsbSupported, type BrotherQlStatus } from '@/print/brotherQlUsb'
import { buildLayoutPdf, downloadPdfBytes, renderPdfPageToCanvas } from '@/print/renderPrintLayout'

const props = defineProps<{ departmentId: string }>()

const { t } = useI18n()
const toast = useToast()

const loading = ref(false)
const printing = ref(false)
const loadError = ref('')
const layouts = ref<PrintLayout[]>([])
const media = ref<PrintMedia[]>([])
const selected = ref<PrintLayout | null>(null)
const selectedFieldId = ref('qr')
const startIndex = ref(0)
const createOpen = ref(false)
const includeTemplate = ref(false)
const underlayEl = ref<HTMLCanvasElement | null>(null)
const usbOk = webUsbSupported()
const qlDevice = ref<USBDevice | null>(null)
const qlStatus = ref<BrotherQlStatus | null>(null)

const createForm = reactive({ name: '', media_id: '', request_global: false })

const canPropose = ref(false)
const allowManage = computed(() => canPropose.value)
const canEditSelected = computed(
  () => allowManage.value && selected.value?.scope !== 'global',
)
const mediaItems = computed(() => media.value.map((item) => ({ title: item.name, value: item.id })))
const previewCell = computed(() => {
  const layout = selected.value
  if (!layout) return { x: 0, y: 0, w: 1, h: 1, col: 0, row: 0, index: 0 }
  return layout.cells[startIndex.value] || layout.cells[0]
})
const sheetAspect = computed(() => {
  const spec = selected.value?.sheet
  if (!spec) return '1 / 1'
  return `${spec.sheet_width_mm} / ${spec.sheet_height_mm}`
})
const selectedFamily = computed(() => selected.value?.media.family || '')
const isOfficeSheet = computed(() => selectedFamily.value === 'office_a4')
const isBrotherQl = computed(() => selectedFamily.value === 'brother_ql')
const isTscDesktop = computed(() => selectedFamily.value === 'tsc_desktop')

async function load() {
  if (!props.departmentId) return
  loading.value = true
  loadError.value = ''
  try {
    const [nextLayouts, catalog] = await Promise.all([
      listDepartmentPrintLayouts(props.departmentId),
      getDepartmentPrintCatalog(props.departmentId),
    ])
    layouts.value = nextLayouts
    media.value = catalog.published_media
    canPropose.value = catalog.can_propose
    if (selected.value) {
      selected.value = nextLayouts.find((item) => item.id === selected.value?.id) || nextLayouts[0] || null
    } else {
      selected.value = nextLayouts[0] || null
    }
    includeTemplate.value = selected.value?.include_template_on_print || false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('printLayout.loadError')
  } finally {
    loading.value = false
  }
}

function selectLayout(item: PrintLayout) {
  selected.value = item
  includeTemplate.value = item.include_template_on_print
  startIndex.value = 0
}

async function loadUnderlay() {
  const canvas = underlayEl.value
  const layout = selected.value
  if (!canvas || !layout?.has_template) {
    if (canvas) {
      canvas.width = 1
      canvas.height = 1
    }
    return
  }
  try {
    const bytes = await fetchPrintLayoutTemplateBytes(props.departmentId, layout.id)
    const width = canvas.parentElement?.clientWidth || 480
    await renderPdfPageToCanvas(bytes, canvas, width)
  } catch {
    /* preview optional */
  }
}

async function createLayout() {
  try {
    const created = await createDepartmentPrintLayout(props.departmentId, {
      name: createForm.name.trim(),
      media_id: createForm.media_id,
      request_global: createForm.request_global,
    })
    createOpen.value = false
    createForm.name = ''
    toast.success(t('printLayout.saveSuccess'))
    await load()
    selected.value = created
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function saveFields() {
  if (!selected.value) return
  try {
    selected.value = await updateDepartmentPrintLayout(props.departmentId, selected.value.id, {
      fields: selected.value.fields,
      include_template_on_print: includeTemplate.value,
    })
    toast.success(t('printLayout.saveSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function persistInclude() {
  if (!selected.value || !canEditSelected.value) return
  try {
    selected.value = await updateDepartmentPrintLayout(props.departmentId, selected.value.id, {
      include_template_on_print: includeTemplate.value,
    })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function onPdf(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !selected.value) return
  try {
    selected.value = await uploadPrintLayoutTemplate(props.departmentId, selected.value.id, file)
    toast.success(t('printLayout.pdfOk'))
    await loadUnderlay()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function requestGlobal() {
  if (!selected.value) return
  try {
    selected.value = await requestDepartmentPrintLayoutGlobal(props.departmentId, selected.value.id)
    toast.success(t('printSettings.requestGlobalSuccess'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

async function removeSelected() {
  if (!selected.value) return
  try {
    await deleteDepartmentPrintLayout(props.departmentId, selected.value.id)
    selected.value = null
    toast.success(t('printLayout.deleted'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('printLayout.saveError'))
  }
}

function beginDrag(event: PointerEvent, field: PrintLayoutField) {
  if (!selected.value || !canEditSelected.value) return
  selectedFieldId.value = field.id
  const svg = (event.currentTarget as SVGElement).ownerSVGElement
  if (!svg) return
  const cell = previewCell.value
  const startX = event.clientX
  const startY = event.clientY
  const orig = { ...field }
  const onMove = (move: PointerEvent) => {
    const box = svg.getBoundingClientRect()
    const dx = ((move.clientX - startX) / box.width) * selected.value!.sheet.sheet_width_mm
    const dy = ((move.clientY - startY) / box.height) * selected.value!.sheet.sheet_height_mm
    field.x = Math.max(0, Math.min(100 - field.w, orig.x + (dx / cell.w) * 100))
    field.y = Math.max(0, Math.min(100 - field.h, orig.y + (dy / cell.h) * 100))
  }
  const onUp = () => {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
  }
  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
  event.preventDefault()
}

async function templateBytes(): Promise<ArrayBuffer | null> {
  if (!selected.value?.has_template) return null
  try {
    return await fetchPrintLayoutTemplateBytes(props.departmentId, selected.value.id)
  } catch {
    return null
  }
}

async function printSample() {
  if (!selected.value) return
  printing.value = true
  try {
    const bytes = await buildLayoutPdf({
      layout: selected.value,
      startIndex: startIndex.value,
      templateBytes: await templateBytes(),
      items: [
        { label: 'Beispiel Material', public_url: 'https://app.ematchef.test/i/demo', public_code: 'DEMO-001' },
        { label: 'Zweites Etikett', public_url: 'https://app.ematchef.test/i/demo2', public_code: 'DEMO-002' },
      ],
    })
    downloadPdfBytes(bytes, `${selected.value.name}.pdf`)
    toast.success(t('printLayout.pdfReady'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.saveError'))
  } finally {
    printing.value = false
  }
}

async function connectQl() {
  try {
    const device = await requestBrotherQlDevice()
    qlDevice.value = device
    qlStatus.value = await readBrotherQlStatus(device)
    toast.success(t('printLayout.qlConnected'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.qlError'))
  }
}

async function printQlSample() {
  if (!qlDevice.value || !selected.value) return
  printing.value = true
  try {
    const cell = previewCell.value
    const canvas = document.createElement('canvas')
    const dpi = 300
    canvas.width = Math.max(32, Math.round((cell.w / 25.4) * dpi))
    canvas.height = Math.max(32, Math.round((cell.h / 25.4) * dpi))
    const ctx = canvas.getContext('2d')
    if (!ctx) throw new Error('Canvas')
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.fillStyle = '#111'
    ctx.font = `${Math.round(canvas.height * 0.12)}px sans-serif`
    ctx.fillText('eMatChef', 8, canvas.height / 2)
    await printCanvasToBrotherQl(qlDevice.value, canvas)
    toast.success(t('printLayout.qlSent'))
  } catch (e: unknown) {
    toast.error((e as Error).message || t('printLayout.qlError'))
  } finally {
    printing.value = false
  }
}

watch(
  () => selected.value?.id,
  async () => {
    const n = selected.value?.cells.length || 1
    if (startIndex.value >= n) startIndex.value = 0
    await nextTick()
    await loadUnderlay()
  },
)
watch(() => props.departmentId, () => { void load() })
onMounted(() => { void load() })

defineExpose({ layouts, selected, startIndex, load })
</script>

<style scoped>
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.card h2 { margin: 0; font-size: 16px; }
.muted { color: #6b7280; font-size: 14px; }
.error { color: #b91c1c; }
.layout-work { display: grid; grid-template-columns: minmax(180px, 240px) 1fr; gap: 16px; margin-top: 12px; }
.layout-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.layout-pick { width: 100%; text-align: left; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; background: #fff; cursor: pointer; }
.layout-item.is-on .layout-pick { border-color: #10b981; background: #ecfdf5; }
.meta { display: block; color: #64748b; font-size: 12px; }
.chip { display: inline-block; margin-top: 4px; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; }
.chip--org { background: #e0e7ff; color: #3730a3; }
.chip--pending { background: #ffedd5; color: #c2410c; }
.editor-toolbar, .ql-row, .print-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
.sheet-wrap { position: relative; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f8fafc; }
.underlay { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: fill; opacity: 0.45; pointer-events: none; }
.sheet { position: relative; width: 100%; height: auto; display: block; background: transparent; }
.cell { fill: rgba(255,255,255,0.35); stroke: #94a3b8; stroke-width: 0.35; cursor: pointer; }
.cell--start { stroke: #059669; stroke-width: 0.9; fill: rgba(16,185,129,0.12); }
.field rect { fill: rgba(37,99,235,0.18); stroke: #2563eb; stroke-width: 0.35; cursor: grab; }
.field--on rect { stroke: #1d4ed8; stroke-width: 0.6; }
.field-label { font-size: 3.2px; fill: #1e3a8a; pointer-events: none; }
.file-btn { display: inline-flex; align-items: center; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: 13px; cursor: pointer; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
.dialog-grid { display: flex; flex-direction: column; gap: 12px; }
@media (max-width: 800px) {
  .layout-work { grid-template-columns: 1fr; }
}
</style>
