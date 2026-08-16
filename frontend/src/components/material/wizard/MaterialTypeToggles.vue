<template>
  <div class="slider-toggles-section">
    <!-- Verbrauchsmaterial -->
    <div class="slider-toggle-group" data-onboarding="material-wizard-toggle-consumable">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isConsumable" @change="onConsumableChange(($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--orange"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">{{ t('components.materialTypeToggles.consumableTitle') }}</span>
          <span class="toggle-desc">{{ t('components.materialTypeToggles.consumableDesc') }}</span>
        </span>
      </label>
    </div>

    <!-- Esswaren -->
    <div class="slider-toggle-group" data-onboarding="material-wizard-toggle-food">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isFood" @change="onFoodChange(($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--green"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">{{ t('components.materialTypeToggles.foodTitle') }}</span>
          <span class="toggle-desc">{{ t('components.materialTypeToggles.foodDesc') }}</span>
        </span>
      </label>
    </div>

    <p v-if="isConsumable || isFood" class="costs-hint">
      {{ t('components.materialTypeToggles.costsHintA') }}<strong>{{ t('components.materialTypeToggles.wordDetails') }}</strong
      >{{ t('components.materialTypeToggles.costsHintB') }}<strong>{{ t('components.materialTypeToggles.wordCosts') }}</strong
      >{{ t('components.materialTypeToggles.costsHintC') }}<strong>{{ t('components.materialTypeToggles.wordVe') }}</strong
      >{{ t('components.materialTypeToggles.costsHintD') }}<strong>{{ t('components.materialTypeToggles.wordQty') }}</strong
      >{{ t('components.materialTypeToggles.costsHintE') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  isConsumable: boolean
  isFood: boolean
}>()

const emit = defineEmits<{
  'update:isConsumable': [value: boolean]
  'update:isFood': [value: boolean]
}>()

function onConsumableChange(checked: boolean) {
  emit('update:isConsumable', checked)
  if (checked) {
    emit('update:isFood', false)
  }
}

function onFoodChange(checked: boolean) {
  emit('update:isFood', checked)
  if (checked) {
    emit('update:isConsumable', false)
  }
}
</script>

<style scoped>
.costs-hint {
  margin: 0.75rem 0 0;
  font-size: 0.875rem;
  color: var(--color-muted, #64748b);
  line-height: 1.45;
}
</style>
