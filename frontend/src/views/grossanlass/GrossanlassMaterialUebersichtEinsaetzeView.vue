<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.einsaetzeIntro') }}</p>
    <GrossanlassEinsatzPreviewPanel />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'

const { t } = useI18n()
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
</style>
