<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t(introKey) }}</p>
    <div class="ga-preview-actions">
      <EButton variant="primary" size="small" disabled>{{ t(addKey) }}</EButton>
    </div>
    <GrossanlassMaterialsPreviewTable :tab="tab" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassMaterialsPreviewTable from '@/views/grossanlass/GrossanlassMaterialsPreviewTable.vue'
import { EButton } from '@/components/form/base'
import type { GaMaterialsTabId } from '@/views/grossanlass/grossanlassMaterialsPreviewData'

const route = useRoute()
const { t } = useI18n()

const tab = computed<GaMaterialsTabId>(() => {
  const value = (route.meta.materialsTab as string) || 'eigen'
  if (value === 'leihweise' || value === 'fahrzeuge' || value === 'eigen') return value
  return 'eigen'
})

const introKey = computed(() => `grossanlass.materials.${tab.value}Intro`)
const addKey = computed(() => `grossanlass.materials.${tab.value}Add`)
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-actions { margin-bottom: 12px; }
</style>
