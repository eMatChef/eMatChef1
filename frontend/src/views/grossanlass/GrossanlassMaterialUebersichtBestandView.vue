<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.intro') }}</p>
    <div class="ga-preview-actions">
      <EButton
        variant="secondary"
        size="small"
        :class="{ 'is-on': vehiclesOnly }"
        @click="toggleVehicles"
      >
        {{ t('grossanlass.materials.filterVehicles') }}
      </EButton>
    </div>
    <GrossanlassMaterialsPreviewTable tab="uebersicht" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import GrossanlassMaterialsPreviewTable from '@/views/grossanlass/GrossanlassMaterialsPreviewTable.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const vehiclesOnly = computed(() => String(route.query.family || '') === 'vehicle')

function toggleVehicles() {
  void router.replace({
    path: route.path,
    query: vehiclesOnly.value ? {} : { family: 'vehicle' },
  })
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
.ga-preview-actions { margin: -8px 0 12px; }
.ga-preview-actions .is-on { font-weight: 700; }
</style>
