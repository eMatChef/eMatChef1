<template>
  <v-container
    class="page-shell"
    fluid
    :style="maxWidthStyle"
  >
    <header v-if="title || subtitle || $slots.title || $slots.subtitle || $slots.actions" class="page-shell__header">
      <div class="page-shell__header-main">
        <h1 v-if="title || $slots.title" class="page-shell__title">
          <slot name="title">{{ title }}</slot>
        </h1>
        <p v-if="subtitle || $slots.subtitle" class="page-shell__subtitle">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>
      <div v-if="$slots.actions" class="page-shell__actions">
        <slot name="actions" />
      </div>
    </header>

    <div v-if="$slots.filters" class="page-shell__filters">
      <slot name="filters" />
    </div>

    <div class="page-shell__content">
      <slot />
    </div>
  </v-container>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'PageShell' })

const props = withDefaults(
  defineProps<{
    title?: string
    subtitle?: string
    /** Entspricht bisher `.dept-page { max-width: 1400px }` */
    maxWidth?: string | number
  }>(),
  {
    maxWidth: '1400px',
  }
)

const maxWidthStyle = computed(() => {
  const value = props.maxWidth
  if (value == null || value === '') return undefined
  return {
    maxWidth: typeof value === 'number' ? `${value}px` : value,
  }
})
</script>

<style scoped>
.page-shell.v-container,
.page-shell.v-container--fluid {
  width: 100%;
  padding: 0 !important;
}

.page-shell__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.page-shell__title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text, #1a1a2e);
  margin: 0;
  line-height: 1.25;
}

.page-shell__subtitle {
  font-size: 0.875rem;
  color: var(--color-text-muted, #6b7280);
  margin: 4px 0 0;
}

.page-shell__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.page-shell__filters {
  margin-bottom: 20px;
}

.page-shell__content {
  position: relative;
}
</style>
