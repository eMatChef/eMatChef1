<template>
  <img
    v-if="hasImage && !failed"
    class="tpl-preview"
    :src="`/display-templates/${template.id}.jpg`"
    :alt="t('display.layout.templatePreviewAlt', { name: t(`display.layout.templates.${template.id}.name`) })"
    draggable="false"
    @error="failed = true"
  >
  <LayoutThumbnail v-else :layout="template.layout" />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LayoutTemplate } from '@/api/displayLayout'
import LayoutThumbnail from './LayoutThumbnail.vue'

/**
 * Vorschaubild einer offiziellen Vorlage: Screenshot des echten Renderers (public/display-templates/<id>.jpg, erzeugt mit
 * e2e/display-template-previews.spec.ts). Die leere Vorlage hat keinen Screenshot und zeigt das leere Raster.
 */
const props = defineProps<{ template: LayoutTemplate }>()
const { t } = useI18n()
const failed = ref(false)
const hasImage = computed(() => props.template.id.startsWith('ga_'))
</script>

<style scoped>
.tpl-preview {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #1e293b;
  background: #0b1220;
}
</style>
