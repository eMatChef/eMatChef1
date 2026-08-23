<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.planung.struktur.intro') }}</p>

    <section class="ga-preview-card">
      <h3>{{ t('grossanlass.planung.struktur.modusTitle') }}</h3>
      <div class="ga-preview-chips">
        <span class="ga-chip is-active">{{ t('grossanlass.planung.struktur.modusOffen') }}</span>
        <span class="ga-chip">{{ t('grossanlass.planung.struktur.modusVerschachtelt') }}</span>
        <span class="ga-chip">{{ t('grossanlass.planung.struktur.modusParallel') }}</span>
      </div>
      <p class="ga-preview-hint">{{ t('grossanlass.planung.struktur.modusHint') }}</p>
    </section>

    <section class="ga-preview-card">
      <div class="ga-preview-card__head">
        <h3>{{ t('grossanlass.planung.struktur.unterlagerTitle') }}</h3>
        <EButton variant="secondary" size="small" disabled>
          {{ t('grossanlass.planung.struktur.addUnterlager') }}
        </EButton>
      </div>
      <p class="ga-preview-hint">{{ t('grossanlass.planung.struktur.unterlagerEmpty') }}</p>
    </section>

    <section class="ga-preview-card">
      <div class="ga-preview-card__head">
        <h3>{{ t('grossanlass.planung.struktur.participantsTitle') }}</h3>
        <EButton variant="secondary" size="small" disabled>
          {{ t('grossanlass.planung.struktur.addParticipant') }}
        </EButton>
      </div>
      <table class="ga-preview-table">
        <thead>
          <tr>
            <th>{{ t('grossanlass.planung.struktur.colDept') }}</th>
            <th>{{ t('grossanlass.planung.struktur.colStatus') }}</th>
            <th>{{ t('grossanlass.planung.struktur.colRessort') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="3" class="ga-preview-table__empty">
              {{ t('grossanlass.planung.struktur.participantsEmpty') }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>
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
  margin-bottom: 14px;
}
.ga-preview-card h3 { margin: 0 0 10px; font-size: 0.95rem; }
.ga-preview-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}
.ga-preview-card__head h3 { margin: 0; }
.ga-preview-hint { margin: 8px 0 0; font-size: 0.82rem; color: #94a3b8; }
.ga-preview-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.ga-chip {
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  font-size: 0.8rem;
  color: #64748b;
  background: #f8fafc;
}
.ga-chip.is-active {
  border-color: #86efac;
  background: #ecfdf5;
  color: #166534;
  font-weight: 600;
}
.ga-preview-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.ga-preview-table th, .ga-preview-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
}
.ga-preview-table th { background: #f8fafc; font-weight: 600; }
.ga-preview-table__empty { color: #94a3b8; }
</style>
