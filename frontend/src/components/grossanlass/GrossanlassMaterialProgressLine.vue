<template>
  <p
    class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs leading-5"
    :aria-label="t('grossanlass.materialProgress.title')"
  >
    <template v-for="(segment, index) in segments" :key="segment.key">
      <span v-if="index > 0" class="text-slate-300" aria-hidden="true">·</span>
      <span :class="toneClass[segment.tone]">
        {{ t(`grossanlass.materialProgress.${segment.key}`, { n: segment.value }) }}
      </span>
    </template>
  </p>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GaMaterialProgressQuantities } from '@/api/grossanlassUebersicht'
import { materialProgressSegments, type GaProgressSegment } from '@/utils/grossanlassMaterialProgress'

const props = defineProps<{ item: GaMaterialProgressQuantities }>()
const { t } = useI18n()

const segments = computed(() => materialProgressSegments(props.item))

const toneClass: Record<GaProgressSegment['tone'], string> = {
  base: 'text-slate-600',
  done: 'font-semibold text-emerald-700',
  flow: 'text-sky-700',
  warn: 'font-semibold text-amber-700',
}
</script>
