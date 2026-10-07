<template>
  <div class="verlauf">
    <EEmptyState v-if="!rows.length" variant="generic" icon="mdi-history" :title="t('grossanlass.ausgabe.history.emptyTitle')" :description="t('grossanlass.ausgabe.history.emptyText')" />
    <ul class="timeline">
      <li v-for="row in rows" :key="row.id" class="entry" :class="`entry--${row.kind}`">
        <span class="entry__icon"><v-icon :icon="ICON[row.kind]" size="20" /></span>
        <div class="entry__body">
          <strong>{{ row.person }}</strong>
          <span>
            {{ t(`grossanlass.ausgabe.history.${row.kind}`) }}:
            {{ row.qty }}× {{ row.article }}<template v-if="row.context"> · {{ t('grossanlass.ausgabe.order') }}: {{ row.context }}</template>
          </span>
          <small v-if="row.note">{{ row.note }}</small>
        </div>
        <time class="entry__time">{{ row.at.toLocaleString(locale, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</time>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useGaAusgabeMock } from './gaAusgabeMock'

const { t, locale } = useI18n()
const { history } = useGaAusgabeMock()
const rows = computed(() => history.value)
const ICON = { issue: 'mdi-export-variant', return: 'mdi-keyboard-return', transfer: 'mdi-swap-horizontal', reassign: 'mdi-folder-swap-outline' } as const
</script>

<style scoped>
.timeline {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.entry {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.entry__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #f1f5f9;
}
.entry--return .entry__icon {
  background: #dcfce7;
}
.entry__body {
  display: flex;
  flex-direction: column;
  font-size: 0.9rem;
}
.entry__body small,
.entry__time {
  color: #64748b;
  font-size: 0.8rem;
}
@media (max-width: 720px) {
  .entry {
    grid-template-columns: 40px minmax(0, 1fr);
  }
  .entry__time {
    grid-column: 2;
  }
}
</style>
