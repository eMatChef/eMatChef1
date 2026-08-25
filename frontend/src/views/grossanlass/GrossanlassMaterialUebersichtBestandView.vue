<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.intro') }}</p>
    <GrossanlassMaterialsPreviewTable tab="uebersicht" />
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import GrossanlassMaterialsPreviewTable from '@/views/grossanlass/GrossanlassMaterialsPreviewTable.vue'

const { t } = useI18n()
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
</style>
