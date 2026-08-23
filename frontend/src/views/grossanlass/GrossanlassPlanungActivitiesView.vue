<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.planung.activities.intro') }}</p>

    <div class="ga-preview-actions">
      <EButton variant="secondary" size="small" disabled>{{ t('grossanlass.planung.activities.addAufbau') }}</EButton>
      <EButton variant="secondary" size="small" disabled>{{ t('grossanlass.planung.activities.addAbbau') }}</EButton>
      <EButton variant="secondary" size="small" disabled>{{ t('grossanlass.planung.activities.addVorevent') }}</EButton>
    </div>

    <article
      v-for="row in rows"
      :key="row.role"
      class="ga-preview-card"
    >
      <div class="ga-preview-card__head">
        <div>
          <span class="ga-chip is-active">{{ row.roleLabel }}</span>
          <strong class="ga-preview-name">{{ row.name }}</strong>
        </div>
        <span class="ga-preview-range">{{ row.range }}</span>
      </div>
      <p class="ga-preview-hint">{{ row.hint }}</p>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton } from '@/components/form/base'

const { t } = useI18n()

const rows = computed(() => [
  {
    role: 'anlass',
    roleLabel: t('grossanlass.planung.activities.roleAnlass'),
    name: t('grossanlass.planung.activities.mainName'),
    range: t('grossanlass.planung.activities.fromWizard'),
    hint: t('grossanlass.planung.activities.mainHint'),
  },
  {
    role: 'aufbau',
    roleLabel: t('grossanlass.planung.activities.roleAufbau'),
    name: t('grossanlass.planung.activities.placeholderName'),
    range: '—',
    hint: t('grossanlass.planung.activities.aufbauHint'),
  },
])
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.ga-preview-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 10px;
}
.ga-preview-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.ga-preview-name { margin-left: 8px; }
.ga-preview-range { font-size: 0.82rem; color: #64748b; }
.ga-preview-hint { margin: 8px 0 0; font-size: 0.82rem; color: #94a3b8; }
.ga-chip {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #ecfdf5;
  color: #166534;
}
</style>
