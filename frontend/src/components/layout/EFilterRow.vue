<template>
  <v-row
    class="e-filter-row e-filter-row--nowrap"
    density="comfortable"
    align="end"
  >
    <slot />
  </v-row>
</template>

<script setup lang="ts">
defineOptions({ name: 'EFilterRow' })
</script>

<style scoped>
.e-filter-row {
  margin-bottom: 4px;
}

.e-filter-row--nowrap {
  flex-wrap: nowrap;
}

.e-filter-row :deep(.e-filter-row__search) {
  flex: 1 1 160px;
  min-width: 0;
  max-width: none;
}

.e-filter-row :deep(.e-filter-row__select) {
  flex: 0 0 auto;
  width: auto;
  min-width: 132px;
  max-width: 180px;
}

.e-filter-row :deep(.e-filter-row__actions) {
  flex: 0 0 auto;
  width: auto;
  white-space: nowrap;
}

.e-filter-row :deep(.e-form-field.autosave-field) {
  margin-bottom: 0;
}

.e-filter-row :deep(.e-search-field),
.e-filter-row :deep(.search-field) {
  padding-top: 10px;
}

.e-filter-row :deep(.e-filter-row__checkbox) {
  flex-shrink: 0;
}

.e-filter-row :deep(.e-filter-row__checkbox .v-label) {
  font-size: 0.8125rem;
  line-height: 1.2;
}

@media (max-width: 640px) {
  .e-filter-row--nowrap {
    flex-wrap: wrap;
  }

  .e-filter-row :deep(.e-filter-row__search) {
    flex: 1 1 100%;
  }

  .e-filter-row :deep(.e-filter-row__select) {
    flex: 1 1 calc(50% - 8px);
    max-width: none;
  }
}
</style>
