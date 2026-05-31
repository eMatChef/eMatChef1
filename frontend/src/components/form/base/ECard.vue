<template>
  <v-card
    v-bind="attrs"
    :variant="variant"
    :class="['e-card', cardClass, { 'e-card--flat': flat }]"
  >
    <slot />
  </v-card>
</template>

<script setup lang="ts">
import { useAttrs } from 'vue'

defineOptions({ inheritAttrs: false, name: 'ECard' })

withDefaults(
  defineProps<{
    variant?: 'elevated' | 'flat' | 'tonal' | 'outlined' | 'text' | 'plain'
    flat?: boolean
    cardClass?: string | string[] | Record<string, boolean>
  }>(),
  {
    variant: 'elevated',
    flat: false,
  }
)

const attrs = useAttrs()
</script>

<style scoped>
.e-card--flat {
  box-shadow: none;
}
</style>