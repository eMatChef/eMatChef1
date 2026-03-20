<template>
  <div class="slider-toggles-section">
    <!-- Verbrauchsmaterial -->
    <div class="slider-toggle-group">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isConsumable" @change="$emit('update:isConsumable', ($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--orange"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">Verbrauchsmaterial</span>
          <span class="toggle-desc">Fackeln, Gaskartuschen, Einweggeschirr, Kerzen etc.</span>
        </span>
      </label>
      <transition name="slide-down">
        <div v-if="isConsumable" class="slider-details consumable-details">
          <div class="slider-hint">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>Wird bei Ausgabe sofort vom Bestand abgezogen.</span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Verkaufspreis (CHF/Stk.)</label>
              <div class="price-input">
                <span class="currency">Fr.</span>
                <input :model-value="salePrice" type="number" step="0.05" min="0" class="form-input" placeholder="0.00" @input="$emit('update:salePrice', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)" />
              </div>
              <p class="field-hint">Wird bei externen Vermietungen berechnet</p>
            </div>
            <div class="form-group">
              <label>Mindestbestand (optional)</label>
              <input :model-value="minStock" type="number" min="0" class="form-input" placeholder="z.B. 10" @input="$emit('update:minStock', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)" />
              <p class="field-hint">Warnung bei Unterschreitung</p>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- Esswaren -->
    <div class="slider-toggle-group">
      <label class="toggle-label">
        <span class="toggle-wrapper">
          <input type="checkbox" :checked="isFood" @change="$emit('update:isFood', ($event.target as HTMLInputElement).checked)" class="toggle-input" />
          <span class="toggle-slider toggle-slider--green"></span>
        </span>
        <span class="toggle-text">
          <span class="toggle-title">Esswaren</span>
          <span class="toggle-desc">Lebensmittel, Getränke, Snacks etc.</span>
        </span>
      </label>
      <transition name="slide-down">
        <div v-if="isFood" class="slider-details food-details">
          <div class="slider-hint">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>Wird im Tab "Esswaren" angezeigt. Haltbarkeitsdaten können pro Charge erfasst werden.</span>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  isConsumable: boolean
  isFood: boolean
  salePrice?: number | null
  minStock?: number | null
}>()
defineEmits<{
  'update:isConsumable': [value: boolean]
  'update:isFood': [value: boolean]
  'update:salePrice': [value: number | null]
  'update:minStock': [value: number | null]
}>()
</script>
