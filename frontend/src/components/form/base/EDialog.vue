<template>
  <v-dialog
    :model-value="model"
    :max-width="maxWidth"
    :persistent="persistent"
    :scrollable="scrollable"
    @update:model-value="onUpdate"
  >
    <ECard :variant="cardVariant" :card-class="cardClass">
      <v-card-title v-if="title || $slots.title" class="e-dialog__title">
        <slot name="title">{{ title }}</slot>
      </v-card-title>
      <v-card-text class="e-dialog__body">
        <slot />
      </v-card-text>
      <v-card-actions v-if="$slots.actions" class="e-dialog__actions">
        <slot name="actions" />
      </v-card-actions>
    </ECard>
  </v-dialog>
</template>

<script setup lang="ts">
import ECard from './ECard.vue'

defineOptions({ name: 'EDialog' })

withDefaults(
  defineProps<{
    title?: string
    maxWidth?: number | string
    persistent?: boolean
    scrollable?: boolean
    cardVariant?: 'elevated' | 'flat' | 'tonal' | 'outlined' | 'text' | 'plain'
    /** Zusätzliche Klassen auf der inneren ECard (z. B. Confirm-Rahmen) */
    cardClass?: string | string[] | Record<string, boolean>
  }>(),
  {
    maxWidth: 560,
    persistent: false,
    scrollable: true,
    cardVariant: 'elevated',
  }
)

const model = defineModel<boolean>({ default: false })

function onUpdate(value: boolean) {
  model.value = value
}
</script>

<style scoped>
.e-dialog__title {
  font-weight: 700;
  line-height: 1.3;
  white-space: normal;
}

.e-dialog__body {
  padding-top: 8px;
}

.e-dialog__actions {
  padding: 8px 16px 16px;
  gap: 8px;
}
</style>
