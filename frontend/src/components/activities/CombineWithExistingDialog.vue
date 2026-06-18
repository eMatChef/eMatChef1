<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="onSeparate">
      <div class="modal-dialog combine-dialog">
      <div class="cwd-head">
        <h3>{{ t('activities.combineDialog.title') }}</h3>
        <p class="cwd-intro">{{ t('activities.combineDialog.intro', { name: comboName }) }}</p>
      </div>

      <ul class="cwd-overlap-list">
        <li v-for="o in overlaps" :key="o.materialItemId" class="cwd-overlap-row">
          <span class="cwd-overlap-name">{{ o.name }}</span>
          <span class="cwd-overlap-detail text-muted">
            {{
              t('activities.combineDialog.overlapDetail', {
                existing: o.existingQty,
                combo: o.comboNeed,
              })
            }}
          </span>
        </li>
      </ul>

      <p class="cwd-explain text-muted">{{ t('activities.combineDialog.explain') }}</p>

      <div class="modal-actions cwd-actions">
        <button type="button" class="btn-secondary btn-sm" @click="onCancel">
          {{ t('common.cancel') }}
        </button>
        <button type="button" class="btn-outline btn-sm" @click="onSeparate">
          {{ t('activities.combineDialog.keepSeparate') }}
        </button>
        <button type="button" class="btn-primary btn-sm" @click="onCombine">
          {{ t('activities.combineDialog.useExisting') }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

export interface CombineOverlap {
  materialItemId: string
  name: string
  /** Bereits in der Aktivität vorhandene Menge dieses Teils (Einzelposition). */
  existingQty: number
  /** Was die Kombo zusätzlich an diesem Teil benötigt (qtyPerCombo × Anzahl). */
  comboNeed: number
}

defineProps<{
  comboName: string
  overlaps: CombineOverlap[]
}>()

const emit = defineEmits<{
  /** Vorhandene Einheit(en) für die Kombo nutzen → Einzelposition entsprechend reduzieren. */
  (e: 'combine'): void
  /** Beide getrennt buchen (Kombo zusätzlich, keine Reduktion). */
  (e: 'separate'): void
  /** Abbrechen — Kombo gar nicht hinzufügen. */
  (e: 'cancel'): void
}>()

const { t } = useI18n()

function onCombine() {
  emit('combine')
}
function onSeparate() {
  emit('separate')
}
function onCancel() {
  emit('cancel')
}
</script>

<style scoped>
.combine-dialog {
  max-width: 480px;
  width: 100%;
}
.cwd-head h3 {
  margin: 0;
  font-size: 1.05rem;
}
.cwd-intro {
  font-size: 0.85rem;
  color: var(--text-muted, #6b7280);
  margin: 0.3rem 0 0.75rem;
}
.cwd-overlap-list {
  list-style: none;
  margin: 0 0 0.75rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.cwd-overlap-row {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 8px;
  background: #faf5ff;
}
.cwd-overlap-name {
  font-weight: 600;
  font-size: 0.88rem;
}
.cwd-overlap-detail {
  font-size: 0.78rem;
}
.cwd-explain {
  font-size: 0.78rem;
  margin: 0 0 0.75rem;
}
.cwd-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.text-muted {
  color: #6b7280;
}
</style>
