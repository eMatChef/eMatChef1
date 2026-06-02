<template>
  <v-btn
    v-bind="attrs"
    :type="type"
    :color="resolvedColor"
    :variant="resolvedVariant"
    :disabled="disabled"
    :loading="loading"
    :block="block"
    :size="size"
    class="e-button"
  >
    <slot />
  </v-btn>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false, name: 'EButton' })

const props = withDefaults(
  defineProps<{
    /** primary | secondary (outlined) | text | danger */
    variant?: 'primary' | 'secondary' | 'text' | 'danger'
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    block?: boolean
    size?: 'x-small' | 'small' | 'default' | 'large' | 'x-large'
  }>(),
  {
    variant: 'primary',
    type: 'button',
  }
)

const attrs = useAttrs()

const resolvedColor = computed(() => {
  if (props.variant === 'danger') return 'error'
  if (props.variant === 'secondary' || props.variant === 'text') return undefined
  return 'primary'
})

const resolvedVariant = computed(() => {
  if (props.variant === 'secondary') return 'outlined'
  if (props.variant === 'text') return 'text'
  if (props.variant === 'danger') return 'outlined'
  return 'flat'
})
</script>

<style scoped>
.e-button.v-btn--variant-outlined.text-error {
  color: #b91c1c;
  border-color: #f87171;
}

.e-button.v-btn--variant-outlined.text-error:hover:not(:disabled) {
  background: #fef2f2;
}
</style>
