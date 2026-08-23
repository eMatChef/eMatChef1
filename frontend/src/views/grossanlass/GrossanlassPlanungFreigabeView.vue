<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.planung.freigabe.intro') }}</p>

    <section class="ga-preview-card">
      <h3>{{ t('grossanlass.planung.freigabe.checklistTitle') }}</h3>
      <ul class="ga-preview-checks">
        <li>
          <v-icon icon="mdi-checkbox-marked-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkPeriod') }}
        </li>
        <li class="is-open">
          <v-icon icon="mdi-checkbox-blank-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkParticipants') }}
        </li>
        <li class="is-open">
          <v-icon icon="mdi-checkbox-blank-outline" size="20" />
          {{ t('grossanlass.planung.freigabe.checkRessorts') }}
        </li>
      </ul>
    </section>

    <EButton variant="primary" disabled>
      {{ t('grossanlass.planung.freigabe.publish') }}
    </EButton>
    <p class="ga-preview-hint">{{ t('grossanlass.planung.freigabe.publishHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton } from '@/components/form/base'

const { t } = useI18n()
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 16px;
  max-width: 520px;
}
.ga-preview-card h3 { margin: 0 0 12px; font-size: 0.95rem; }
.ga-preview-checks { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.ga-preview-checks li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #166534;
}
.ga-preview-checks li.is-open { color: #64748b; }
.ga-preview-hint { margin: 10px 0 0; font-size: 0.82rem; color: #94a3b8; max-width: 520px; }
</style>
