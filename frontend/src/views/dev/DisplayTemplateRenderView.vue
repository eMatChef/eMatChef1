<template>
  <div class="tpl-render">
    <DisplayLayoutRenderer v-if="layout" :layout="layout" :variant-id="variantId" :map-data="mapData" />
    <p v-else class="tpl-render__missing">Unbekannte Vorlage</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import DisplayLayoutRenderer from '@/components/display/layout/DisplayLayoutRenderer.vue'
import templates from '@/test-fixtures/displayLayoutTemplates.json'
import mapFixture from '@/test-fixtures/displayMapData.json'
import type { DisplayMapData, LayoutDocument, LayoutTemplate } from '@/api/displayLayout'

/**
 * Entwicklungsansicht: rendert eine offizielle Infoscreen-Vorlage vollflächig mit dem echten Renderer (ohne Login und
 * Backend). Grundlage der reproduzierbaren Vorschaubilder (frontend/e2e/display-template-previews.spec.ts).
 *
 * Nur für Tests: `?map=fixture` liefert dem Karten-Widget die Testdaten aus test-fixtures/displayMapData.json,
 * `?focus=ga_site_map` zeigt das Widget allein in einer Zone (Konfiguration als JSON in `?cfg=`).
 */
const route = useRoute()
const template = computed(() => (templates as unknown as LayoutTemplate[]).find((t) => t.id === route.params.templateId))
const mapData = computed<DisplayMapData | null>(() => (route.query.map === 'fixture' ? (mapFixture as DisplayMapData) : null))

/** Einzelwidget in einer Zone (Grösse in Rasterfeldern per ?w=&h=, Rest bleibt leer) für Grössen- und Konfigurationsprüfungen. */
const focusLayout = computed<LayoutDocument | null>(() => {
  const widget = route.query.focus
  if (typeof widget !== 'string' || !/^[a-z_]+$/.test(widget)) return null
  let config: Record<string, unknown>
  try {
    config = JSON.parse(String(route.query.cfg ?? '{}'))
  } catch {
    config = {}
  }
  const w = Math.min(12, Math.max(1, Number(route.query.w) || 12))
  const h = Math.min(12, Math.max(1, Number(route.query.h) || 12))
  return {
    schema_version: 1,
    default_variant_id: 'v',
    variants: [{ id: 'v', name: 'Test', aspect_ratio: '16:9', root: { type: 'page', page_id: 'p' } }],
    pages: [{ id: 'p', name: 'Test', rows: 12, items: [{ id: 'i', x: 0, y: 0, w, h, content: { type: 'widget', widget, widget_version: 1, config } }] }],
  }
})
const layout = computed<LayoutDocument | null>(() => focusLayout.value ?? template.value?.layout ?? null)
const variantId = computed(() => (typeof route.query.variant === 'string' ? route.query.variant : layout.value?.default_variant_id ?? ''))
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
