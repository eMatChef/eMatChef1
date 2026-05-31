<template>
  <div class="e-responsive-data-list">
    <div v-if="display.mdAndUp" class="e-responsive-data-list__desktop">
      <slot name="table" />
    </div>
    <div v-else class="e-responsive-data-list__mobile">
      <slot name="mobile" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'

defineOptions({ name: 'EResponsiveDataList' })

const display = useDisplay()
</script>
