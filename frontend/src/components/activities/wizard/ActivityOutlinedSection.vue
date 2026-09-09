<template>
  <fieldset :id="id" class="activity-outlined-fieldset">
    <legend class="activity-outlined-legend">
      <span v-if="icon" class="activity-outlined-legend-icon" aria-hidden="true">
        <svg
          v-if="icon === 'calendar'"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <svg
          v-else-if="icon === 'truck'"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect x="1" y="3" width="15" height="13" />
          <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
          <circle cx="5.5" cy="18.5" r="2.5" />
          <circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
      </span>
      <span class="activity-outlined-legend-text"
        >{{ title }}<span v-if="required" class="req" aria-hidden="true">*</span></span
      >
    </legend>
    <div class="activity-outlined-body">
      <slot />
    </div>
  </fieldset>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  icon?: 'calendar' | 'truck' | null
  /** z. B. für Scroll-Ziele / Anker */
  id?: string
  /** Pflichtfeld: Stern in der Legend */
  required?: boolean
}>()
</script>

<style scoped>
.activity-outlined-fieldset {
  margin: 0 0 18px;
  min-width: 0;
  min-inline-size: 0;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding: 14px 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  background: #fff;
}

.activity-outlined-legend {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 8px;
  margin: 0 0 0 6px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  background: #fff;
}

.activity-outlined-legend .req {
  color: #dc2626;
  margin-left: 2px;
}

.activity-outlined-legend-icon {
  display: flex;
  flex-shrink: 0;
  color: var(--color-primary, #059669);
}

.activity-outlined-legend-icon svg {
  width: 18px;
  height: 18px;
}

.activity-outlined-legend-text {
  line-height: 1.3;
}

.activity-outlined-body {
  min-width: 0;
}

.activity-outlined-body :deep(.activity-pill-group) {
  margin-top: 0;
}

.activity-outlined-body :deep(.form-input) {
  width: 100%;
}
</style>
