<template>
  <aside v-if="printer || layout" class="job-preview" :aria-label="t('printJob.previewAria')">
    <div v-if="printer" class="preview-printer">
      <PrintDeviceThumb
        class="preview-printer__art"
        :family="printer.device_model.family"
        :label="printerTitle"
      />
      <div class="preview-printer__copy">
        <strong>{{ printer.name }}</strong>
        <span>{{ printer.device_model.brand }} {{ printer.device_model.name }}</span>
      </div>
    </div>

    <div v-if="layout && showSheet" class="preview-paper">
      <p class="preview-kicker">{{ paperKicker }}</p>
      <div class="sheet-frame" :style="{ aspectRatio: sheetAspect }">
        <svg
          class="sheet"
          :viewBox="`0 0 ${layout.sheet.sheet_width_mm} ${layout.sheet.sheet_height_mm}`"
          role="img"
          :aria-label="t('printJob.previewSheetAria')"
        >
          <rect
            class="sheet__page"
            x="0"
            y="0"
            :width="layout.sheet.sheet_width_mm"
            :height="layout.sheet.sheet_height_mm"
          />
          <rect
            v-for="cell in layout.cells"
            :key="cell.index"
            class="sheet__cell"
            :class="cellClass(cell.index)"
            :x="cell.x"
            :y="cell.y"
            :width="cell.w"
            :height="cell.h"
            :rx="cellRadius"
            @click="onCellClick(cell.index)"
          />
        </svg>
      </div>
      <p v-if="layout.cells.length > 1" class="preview-hint">{{ t('printJob.previewStartHint') }}</p>
      <p v-if="extraPages > 0" class="preview-hint">{{ t('printJob.previewPages', extraPages) }}</p>
    </div>

    <div v-if="layout" class="preview-label">
      <p class="preview-kicker">{{ t('printJob.previewLabel') }}</p>
      <div class="label-frame" :class="{ 'is-color': face.color }" :style="{ aspectRatio: labelAspect }">
        <svg
          class="label"
          :viewBox="`0 0 ${previewCell.w} ${previewCell.h}`"
          role="img"
          :aria-label="t('printJob.previewLabelAria')"
        >
          <rect
            class="label__bg"
            :class="{ 'is-color': face.color, 'is-badge': isBadge }"
            x="0.6"
            y="0.6"
            :width="previewCell.w - 1.2"
            :height="previewCell.h - 1.2"
            :rx="labelRadius"
          />
          <template v-if="isBadge">
            <text
              v-if="firstSample.event"
              class="label__kicker"
              :x="previewCell.w / 2"
              :y="previewCell.h * 0.1"
              text-anchor="middle"
            >
              {{ firstSample.event }} · eMatChef
            </text>
            <text
              v-if="firstSample.name"
              class="label__name"
              :x="previewCell.w / 2"
              :y="previewCell.h * (firstSample.event ? 0.2 : 0.12)"
              text-anchor="middle"
            >
              {{ firstSample.name }}
            </text>
            <text
              v-if="firstSample.place"
              class="label__place"
              :x="previewCell.w / 2"
              :y="previewCell.h * 0.3"
              text-anchor="middle"
            >
              {{ firstSample.place }}
            </text>
            <rect
              v-if="firstSample.public_url"
              class="label__qr"
              :x="previewCell.w * 0.32"
              :y="previewCell.h * 0.36"
              :width="previewCell.w * 0.36"
              :height="previewCell.w * 0.36"
              rx="1"
            />
            <text
              v-if="firstSample.public_code"
              class="label__code"
              :x="previewCell.w / 2"
              :y="previewCell.h * 0.82"
              text-anchor="middle"
            >
              {{ firstSample.public_code }}
            </text>
            <text
              v-if="firstSample.drive"
              class="label__drive"
              :x="previewCell.w / 2"
              :y="previewCell.h * 0.92"
              text-anchor="middle"
            >
              {{ firstSample.drive }}
            </text>
          </template>
          <g v-else>
            <g v-for="field in previewFields" :key="field.id">
              <rect
                v-if="field.type === 'qr'"
                class="label__qr"
                :x="(field.x / 100) * previewCell.w + 0.6"
                :y="(field.y / 100) * previewCell.h + 0.6"
                :width="Math.min((field.w / 100) * previewCell.w, (field.h / 100) * previewCell.h) - 1.2"
                :height="Math.min((field.w / 100) * previewCell.w, (field.h / 100) * previewCell.h) - 1.2"
                :rx="face.rounded ? 1.2 : 0.2"
              />
              <text
                v-else
                class="label__text"
                :x="(field.x / 100) * previewCell.w + 1.2"
                :y="(field.y / 100) * previewCell.h + Math.min(5, (field.h / 100) * previewCell.h * 0.45)"
                :font-size="Math.max(2.4, Math.min(5.2, (field.h / 100) * previewCell.h * 0.28))"
              >
                {{ fieldPreviewText(field) }}
              </text>
            </g>
          </g>
        </svg>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DepartmentPrintPreset } from '@/api/printCatalog'
import type { PrintLayout } from '@/api/printLayouts'
import PrintDeviceThumb from '@/components/print/PrintDeviceThumb.vue'
import { layoutKeysFromContent, layoutWithEnabledFields, type PrintContentKey } from '@/print/layoutFields'
import { defaultPrintFace, faceRadius, type PrintFace } from '@/print/printFace'
import { sampleForPrint, type PrintJobItem } from '@/print/printJob'
import { filledCellIndexesOnPage, sheetPageCount } from '@/print/sheetPlacement'

const props = defineProps<{
  printer?: DepartmentPrintPreset | null
  layout?: PrintLayout | null
  items?: PrintJobItem[]
  startCell?: number
  enabledFields?: PrintContentKey[]
  face?: PrintFace
}>()

const emit = defineEmits<{
  'update:startCell': [value: number]
}>()

const { t } = useI18n()

const printer = computed(() => props.printer || null)
const layout = computed(() => props.layout || null)
const items = computed(() => props.items || [])
const startIndex = computed(() => Math.max(0, (props.startCell || 1) - 1))
const enabled = computed(() => props.enabledFields || [])
const face = computed(() => props.face || defaultPrintFace('label'))
const isBadge = computed(() => face.value.design === 'badge')

const printerTitle = computed(() => {
  if (!printer.value) return ''
  return `${printer.value.name} · ${printer.value.device_model.brand} ${printer.value.device_model.name}`
})

const paperKicker = computed(() => {
  const item = layout.value
  if (!item) return ''
  return t('printJob.previewSheet')
})

const sheetAspect = computed(() => {
  const spec = layout.value?.sheet
  if (!spec) return '1 / 1'
  return `${spec.sheet_width_mm} / ${spec.sheet_height_mm}`
})

const jobLayout = computed(() => {
  const item = layout.value
  if (!item) return null
  return layoutWithEnabledFields(item, layoutKeysFromContent(enabled.value))
})

const previewFields = computed(() => jobLayout.value?.fields || [])
const previewCell = computed(() => {
  const cells = layout.value?.cells || []
  return cells[startIndex.value] || cells[0] || { x: 0, y: 0, w: 50, h: 30, col: 0, row: 0, index: 0 }
})
const labelRadius = computed(() => faceRadius(face.value, previewCell.value.w, previewCell.value.h))
const labelAspect = computed(() => `${previewCell.value.w} / ${previewCell.value.h}`)
const cellRadius = computed(() => {
  const spec = layout.value?.sheet
  if (spec?.shape === 'round') return Math.min(previewCell.value.w, previewCell.value.h) / 2
  if (face.value.rounded) return Math.min(1.8, previewCell.value.w / 6)
  return Math.min(0.4, previewCell.value.w / 12)
})
const showSheet = computed(() => (layout.value?.cells.length || 0) > 1)

const filledOnFirstPage = computed(() => {
  const n = layout.value?.cells.length || 0
  if (!n) return new Set<number>()
  return new Set(filledCellIndexesOnPage(items.value.length, n, startIndex.value, 0))
})

const extraPages = computed(() => {
  const n = layout.value?.cells.length || 0
  if (!n) return 0
  return Math.max(0, sheetPageCount(items.value.length, n, startIndex.value) - 1)
})

const firstSample = computed(() => {
  const row = items.value[0]
  if (!row) return { label: '', public_url: '', public_code: '' }
  return sampleForPrint(row, enabled.value)
})

function cellClass(index: number) {
  if (filledOnFirstPage.value.has(index)) {
    return index === startIndex.value ? 'is-start is-fill' : 'is-fill'
  }
  if (index < startIndex.value) return 'is-used'
  return 'is-free'
}

function onCellClick(index: number) {
  if ((layout.value?.cells.length || 0) <= 1) return
  emit('update:startCell', index + 1)
}

function fieldPreviewText(field: { key: string }): string {
  const sample = firstSample.value
  if (field.key === 'public_code') return sample.public_code || t('printLayout.field.code')
  const line = sample.label.split('\n')[0]?.trim()
  return line || t('printLayout.field.title')
}
</script>

<style scoped>
.job-preview {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}
.preview-printer {
  display: flex;
  align-items: center;
  gap: 10px;
}
.preview-printer__art {
  width: 76px;
  flex-shrink: 0;
}
.preview-printer__copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.preview-printer__copy strong {
  font-size: 13px;
  color: #0f172a;
  line-height: 1.25;
}
.preview-printer__copy span {
  font-size: 12px;
  color: #64748b;
  line-height: 1.3;
}
.preview-paper,
.preview-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.preview-kicker {
  margin: 0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}
.sheet-frame,
.label-frame {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
  overflow: hidden;
}
.sheet,
.label {
  display: block;
  width: 100%;
  height: auto;
}
.sheet__page {
  fill: #fff;
  stroke: #cbd5e1;
  stroke-width: 0.7;
}
.label__bg {
  fill: #fff;
  stroke: #111827;
  stroke-width: 0.7;
}
.label__bg.is-color {
  stroke: #166534;
}
.label__bg.is-badge.is-color {
  fill: #ecfdf3;
}
.label__qr {
  fill: #0f172a;
}
.label__text,
.label__name,
.label__place,
.label__code,
.label__kicker,
.label__drive {
  font-family: sans-serif;
}
.label__text,
.label__name {
  fill: #111827;
  font-weight: 800;
}
.label__kicker,
.label__drive {
  fill: #111827;
  font-size: 3.2px;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.label-frame.is-color .label__kicker,
.label-frame.is-color .label__drive {
  fill: #166534;
}
.label__place,
.label__code {
  fill: #64748b;
  font-size: 3.1px;
}
.label__name {
  font-size: 5.2px;
}
.sheet__cell {
  fill: #f8fafc;
  stroke: #94a3b8;
  stroke-width: 0.35;
  cursor: pointer;
}
.sheet__cell.is-used {
  fill: #e2e8f0;
  stroke: #94a3b8;
  stroke-dasharray: 1.2 0.8;
  cursor: pointer;
}
.sheet__cell.is-fill {
  fill: #bbf7d0;
  stroke: #16a34a;
  stroke-width: 0.55;
}
.sheet__cell.is-start {
  stroke: #15803d;
  stroke-width: 0.9;
}
.sheet__cell.is-free:hover,
.sheet__cell.is-used:hover {
  fill: #dcfce7;
}
.preview-hint {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.35;
}
</style>
