<template>
  <div class="unit-choice">
    <div class="unit-choice__track" role="radiogroup" :aria-label="t('grossanlass.planung.ressorts.childKindLabel')">
      <button
        v-for="option in options"
        :key="option"
        type="button"
        class="unit-choice__option"
        :class="{ 'is-active': model === option }"
        role="radio"
        :aria-checked="model === option"
        @click="model = option"
      >
        {{ labelFor(option) }}
      </button>
    </div>
    <p class="unit-choice__hint">{{ t('grossanlass.planung.ressorts.unitChoiceHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { GrossanlassGroupKind } from '@/api/grossanlassGroups'

const model = defineModel<GrossanlassGroupKind>({ required: true })
const { t } = useI18n()

const options: GrossanlassGroupKind[] = ['ressort', 'bereich', 'teilbereich']

function labelFor(kind: GrossanlassGroupKind): string {
  if (kind === 'bereich') return t('grossanlass.planung.ressorts.kindUnterressort')
  if (kind === 'teilbereich') return t('grossanlass.planung.ressorts.kindBauprojekt')
  return t('grossanlass.planung.ressorts.kindRessort')
}
</script>

<style scoped>
.unit-choice {
  margin: 4px 0 8px;
}

.unit-choice__track {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  background: #f3f4f6;
  border-radius: 999px;
}

.unit-choice__option {
  border: 0;
  background: transparent;
  border-radius: 999px;
  padding: 8px 10px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
}

.unit-choice__option.is-active {
  background: #059669;
  color: #fff;
  font-weight: 600;
}

.unit-choice__hint {
  margin: 6px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
