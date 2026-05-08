<template>
  <div class="activity-pill-group">
    <span v-if="label != null && label !== ''" class="field-label">{{ label }}</span>
    <div class="activity-pill-bar" role="group" :aria-label="ariaLabel || label">
      <div class="activity-pill-cell activity-pill-cell--date">
        <slot name="date" />
      </div>
      <div class="activity-pill-cell activity-pill-cell--time">
        <span class="activity-pill-cell-lbl">{{ labelFrom }}</span>
        <slot name="timeFrom" />
      </div>
      <div class="activity-pill-cell activity-pill-cell--time">
        <span class="activity-pill-cell-lbl">{{ labelTo }}</span>
        <slot name="timeTo" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  /** Optional: bei leerem String kein Label über der Zeile */
  label?: string
  labelFrom: string
  labelTo: string
  /** Für Screenreader, falls label visuell gekürzt */
  ariaLabel?: string
}>()
</script>
