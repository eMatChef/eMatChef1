<template>
  <div class="tpl-render">
    <DisplayLayoutRenderer v-if="template" :layout="template.layout" :variant-id="variantId" />
    <p v-else class="tpl-render__missing">Unbekannte Vorlage</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import DisplayLayoutRenderer from '@/components/display/layout/DisplayLayoutRenderer.vue'
import templates from '@/test-fixtures/displayLayoutTemplates.json'
import type { LayoutTemplate } from '@/api/displayLayout'

/**
 * Entwicklungsansicht: rendert eine offizielle Infoscreen-Vorlage vollflächig mit dem echten Renderer (ohne Login und
 * Backend). Grundlage der reproduzierbaren Vorschaubilder (frontend/e2e/display-template-previews.spec.ts).
 */
const route = useRoute()
const template = computed(() => (templates as unknown as LayoutTemplate[]).find((t) => t.id === route.params.templateId))
const variantId = computed(() => (typeof route.query.variant === 'string' ? route.query.variant : template.value?.layout.default_variant_id ?? ''))
</script>

<style scoped>
.tpl-render {
  position: fixed;
  inset: 0;
  background: #0b1220;
}
.tpl-render__missing {
  color: #94a3b8;
  padding: 24px;
}
</style>
