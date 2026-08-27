<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.chain.packIntro') }}</p>
    <section v-for="phase in phases" :key="phase.id" class="card">
      <h3>{{ t(`grossanlass.chain.packPhase.${phase.id}`) }}</h3>
      <label v-for="row in phase.rows" :key="row.id" class="pack-line">
        <input type="checkbox" :checked="row.packed" @change="onToggle(row.id, !row.packed)">
        <span>
          <strong>{{ row.name }}</strong>
          <span class="qty">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }}</span>
        </span>
      </label>
      <p v-if="!phase.rows.length" class="empty">{{ t('grossanlass.chain.issueEmpty') }}</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'

const { t } = useI18n()
const toast = useToast()
const uebersicht = useGaUebersicht()
const lines = computed(() => uebersicht.data.value?.pack ?? [])
const phases = computed(() =>
  (['aufbau', 'anlass'] as const).map((id) => ({
    id,
    rows: lines.value.filter((row) => row.phase === id),
  })),
)

async function onToggle(id: string, packed: boolean) {
  try {
    await uebersicht.togglePacked(id, packed)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #fff; }
.pack-line { display: flex; gap: 10px; align-items: center; padding: 6px 0; cursor: pointer; }
.qty { margin-left: 8px; color: #64748b; font-size: 0.8rem; }
.empty { color: #64748b; font-size: 0.85rem; }
h3 { margin: 0 0 8px; font-size: 0.95rem; }
</style>
