<script setup lang="ts">
import { IconCross } from '@/components/icons'

withDefaults(
  defineProps<{
    okActive?: boolean
    problemActive?: boolean
    okTitle?: string
    okAriaLabel?: string
    problemTitle?: string
    problemAriaLabel?: string
    disabled?: boolean
    /** Nur ✓ (z. B. «passt» bei Soll=Ist) */
    okOnly?: boolean
  }>(),
  {
    okActive: false,
    problemActive: false,
    okTitle: '',
    okAriaLabel: '',
    problemTitle: '',
    problemAriaLabel: '',
    disabled: false,
    okOnly: false,
  },
)

defineEmits<{
  ok: []
  problem: []
}>()
</script>

<template>
  <div class="pack-shell-check-toggle" role="group">
    <button
      type="button"
      class="pack-shell-check-btn pack-shell-check-btn--ok"
      :class="{ 'pack-shell-check-btn--active': okActive }"
      :title="okTitle"
      :aria-label="okAriaLabel || okTitle"
      :disabled="disabled"
      @click="$emit('ok')"
    >
      ✓
    </button>
    <button
      v-if="!okOnly"
      type="button"
      class="pack-shell-check-btn pack-shell-check-btn--problem"
      :class="{ 'pack-shell-check-btn--active': problemActive }"
      :title="problemTitle"
      :aria-label="problemAriaLabel || problemTitle"
      :disabled="disabled"
      @click="$emit('problem')"
    >
      <IconCross class="pack-shell-check-btn__icon" />
    </button>
  </div>
</template>
