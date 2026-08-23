<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.chain.packIntro') }}</p>

    <section v-for="phase in phases" :key="phase.id" class="card">
      <h3>{{ t(`grossanlass.chain.packPhase.${phase.id}`) }}</h3>
      <label v-for="row in phase.rows" :key="row.id" class="pack-line">
        <input type="checkbox" :checked="row.packed" @change="togglePacked(row.id)">
        <span>
          <strong>{{ row.name }}</strong>
          <span class="qty">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }}</span>
        </span>
      </label>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { listPackLines, togglePacked } from '@/views/grossanlass/grossanlassChainPreviewStore'

const { t } = useI18n()
const lines = computed(() => listPackLines())
const phases = computed(() =>
  (['aufbau', 'anlass'] as const).map((id) => ({
    id,
    rows: lines.value.filter((row) => row.phase === id),
  })),
)
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 14px;
}
.card h3 { margin: 0 0 10px; font-size: 0.95rem; }
.pack-line {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 8px 0;
  border-top: 1px solid #f1f5f9;
  font-size: 0.9rem;
}
.qty { display: block; color: #64748b; font-size: 0.78rem; }
</style>
