<template>
  <div class="e-responsive-data-list">
    <div class="e-responsive-data-list__desktop">
      <slot name="table" />
    </div>
    <div class="e-responsive-data-list__mobile">
      <slot name="mobile" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'EResponsiveDataList' })
</script>

<style scoped>
/* Mobile-first: unter 960px Karten (#mobile), darüber Tabelle (#table) */
.e-responsive-data-list__desktop {
  display: none;
}

.e-responsive-data-list__mobile {
  display: block;
}

@media (min-width: 960px) {
  .e-responsive-data-list__desktop {
    display: block;
  }

  .e-responsive-data-list__mobile {
    display: none;
  }
}
</style>
