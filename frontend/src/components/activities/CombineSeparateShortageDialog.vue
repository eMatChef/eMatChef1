<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="emit('cancel')">
      <div class="modal-dialog combine-separate-shortage-dialog">
        <div class="css-head">
          <h3>{{ t('activities.combineSeparateShortage.title') }}</h3>
          <p class="css-intro">{{ t('activities.combineSeparateShortage.intro', { name: comboName }) }}</p>
        </div>

        <ul class="css-list">
          <li v-for="s in shortages" :key="s.materialItemId" class="css-row">
            <span class="css-name">{{ s.name }}</span>
            <span class="css-detail text-muted">
              {{
                t('activities.combineSeparateShortage.lineDetail', {
                  total: s.totalAfter,
                  available: s.available,
                })
              }}
            </span>
            <span v-if="s.suggestedStandaloneReduce > 0" class="css-adjust text-muted">
              {{
                t('activities.combineSeparateShortage.reduceStandalone', {
                  n: s.suggestedStandaloneReduce,
                  from: s.standaloneQty,
                  to: s.standaloneQty - s.suggestedStandaloneReduce,
                })
              }}
            </span>
            <span v-if="s.remainingShortage > 0" class="css-blocker">
              {{
                t('activities.combineSeparateShortage.remainingBlock', {
                  n: s.remainingShortage,
                })
              }}
            </span>
          </li>
        </ul>

        <p v-if="canAdjust" class="css-explain text-muted">
          {{ t('activities.combineSeparateShortage.explainAdjust') }}
        </p>
        <p v-else class="css-explain css-explain--warn">
          {{ t('activities.combineSeparateShortage.explainBlocked') }}
        </p>

        <div class="modal-actions css-actions">
          <button type="button" class="btn-secondary btn-sm" @click="emit('cancel')">
            {{ t('common.cancel') }}
          </button>
          <button v-if="canAdjust" type="button" class="btn-outline btn-sm" @click="emit('use-existing')">
            {{ t('activities.combineDialog.useExisting') }}
          </button>
          <button
            v-if="canAdjust"
            type="button"
            class="btn-primary btn-sm"
            @click="emit('adjust-and-book')"
          >
            {{ t('activities.combineSeparateShortage.adjustAndBook') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SeparateBookShortage } from '@/utils/materialPeriodDemand'

const props = defineProps<{
  comboName: string
  shortages: SeparateBookShortage[]
}>()

const emit = defineEmits<{
  'adjust-and-book': []
  'use-existing': []
  cancel: []
}>()

const { t } = useI18n()

const canAdjust = computed(() =>
  props.shortages.every((s) => s.remainingShortage <= 0),
)
</script>

<style scoped>
.combine-separate-shortage-dialog {
  max-width: 520px;
  width: 100%;
}
.css-head h3 {
  margin: 0;
  font-size: 1.05rem;
}
.css-intro {
  font-size: 0.85rem;
  color: var(--text-muted, #6b7280);
  margin: 0.3rem 0 0.75rem;
}
.css-list {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.css-row {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 8px;
  background: #fff7ed;
}
.css-name {
  font-weight: 600;
  font-size: 0.88rem;
}
.css-detail,
.css-adjust {
  font-size: 0.78rem;
}
.css-blocker {
  font-size: 0.78rem;
  color: #b45309;
  font-weight: 600;
}
.css-explain {
  font-size: 0.78rem;
  margin: 0 0 0.75rem;
}
.css-explain--warn {
  color: #b45309;
}
.css-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.text-muted {
  color: #6b7280;
}
</style>
