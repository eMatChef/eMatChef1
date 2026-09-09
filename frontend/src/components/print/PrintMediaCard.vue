<template>
  <button
    type="button"
    class="media-card"
    :class="{ 'is-on': selected }"
    @click="$emit('select')"
  >
    <PrintSheetThumb
      :media="media"
      :spec="spec"
      :cells="cells"
      :cut-length-mm="cutLengthMm"
      :aria-label="title"
    />
    <strong class="media-card__title">{{ title }}</strong>
    <span v-if="sku" class="media-card__sku">{{ sku }}</span>
    <span class="media-card__meta">{{ sizeLabel }}</span>
    <span v-if="extra" class="media-card__meta">{{ extra }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PrintMedia } from '@/api/printCatalog'
import type { PrintSheetCell, PrintSheetSpec } from '@/api/printLayouts'
import { specFromMedia } from '@/print/sheetGeometry'
import PrintSheetThumb from '@/components/print/PrintSheetThumb.vue'

const props = defineProps<{
  media: PrintMedia
  spec?: PrintSheetSpec
  cells?: PrintSheetCell[]
  cutLengthMm?: number | null
  selected?: boolean
  title?: string
  extra?: string
}>()

defineEmits<{ select: [] }>()

const { t } = useI18n()
const spec = computed(() => props.spec || specFromMedia(props.media, props.cutLengthMm))
const title = computed(() => props.title || props.media.name)
const sku = computed(() => props.media.sku)
const sizeLabel = computed(() => {
  const w = props.media.width_mm
  const h = props.media.is_continuous
    ? (props.cutLengthMm ?? props.media.default_cut_length_mm)
    : props.media.height_mm
  if (h == null) return t('printLayout.sizeMmEndless', { w })
  return t('printLayout.sizeMm', { w, h })
})
</script>

<style scoped>
.media-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: left;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 8px;
  background: #fff;
  cursor: pointer;
  min-width: 0;
}
.media-card:hover { border-color: #93c5fd; }
.media-card.is-on {
  border-color: #2563eb;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.18);
  background: #f8fafc;
}
.media-card__title {
  font-size: 12px;
  line-height: 1.25;
  color: #0f172a;
}
.media-card__sku {
  font-size: 11px;
  font-weight: 700;
  color: #1d4ed8;
}
.media-card__meta {
  font-size: 11px;
  color: #64748b;
}
</style>
