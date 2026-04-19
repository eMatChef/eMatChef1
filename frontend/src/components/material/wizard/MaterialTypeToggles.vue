<template>
  <div class="slider-toggles-section">
    <!-- Verbrauchsmaterial -->
    <div class="slider-toggle-group">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isConsumable" @change="onConsumableChange(($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--orange"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">Verbrauchsmaterial</span>
          <span class="toggle-desc">Fackeln, Gaskartuschen, Einweggeschirr, Kerzen etc.</span>
        </span>
      </label>
    </div>

    <!-- Esswaren -->
    <div class="slider-toggle-group">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isFood" @change="onFoodChange(($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--green"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">Esswaren</span>
          <span class="toggle-desc">Lebensmittel, Getränke, Snacks etc.</span>
        </span>
      </label>
    </div>

    <p v-if="isConsumable || isFood" class="costs-hint">
      Preise im Schritt <strong>Details</strong> unter <strong>Kosten</strong>.
      <strong>Verpackungseinheit</strong> bei der <strong>Menge</strong>, sobald eine Anzahl eingetragen ist.
    </p>
  </div>
</template>

<script setup lang="ts">
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
