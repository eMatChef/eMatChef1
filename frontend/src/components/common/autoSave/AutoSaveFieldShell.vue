<template>
  <div
    class="autosave-field"
    :class="[
      spanClass,
      {
        'is-focused': isFocused,
        'is-pending': isPending && !isSaving,
        'is-saving': isSaving,
        'is-saved': showSuccessIcon,
        'is-error': status === 'error',
        'is-dirty': isDirty,
        'has-value': hasDisplayValue,
        'is-disabled': disabled,
      },
    ]"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="showLabel" class="autosave-label" :for="inputId">{{ label }}</label>

        <slot />

        <!-- Fortschritt: zwei indeterminate Balken unten (Material/Vuetify) -->
        <div v-if="showProgress" class="autosave-frame-loader" aria-hidden="true">
          <span class="autosave-frame-loader-bar autosave-frame-loader-bar--long" />
          <span class="autosave-frame-loader-bar autosave-frame-loader-bar--short" />
        </div>
      </div>

      <!--mdi-content-save auf der unteren Rahmenkante -->
      <div class="autosave-append">
        <span
          class="autosave-disk"
          :class="{ 'is-visible': showSuccessIcon && !isDirty && status !== 'error' }"
          aria-hidden="true"
          :title="savedLabel"
        >
          <svg class="autosave-disk-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path
              d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 14a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v2z"
            />
          </svg>
        </span>

        <template v-if="status === 'error'">
          <button
            type="button"
            class="autosave-action autosave-action--retry"
            :title="retryLabel"
            :aria-label="retryLabel"
            @click="$emit('retry')"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M23 4v6h-6M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
            </svg>
          </button>
          <button
            type="button"
            class="autosave-action autosave-action--cancel"
            :title="cancelLabel"
            :aria-label="cancelLabel"
            @click="$emit('cancel')"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </template>
      </div>
    </div>

    <p v-if="status === 'error' && errorMessage" class="autosave-error-hint" role="alert">
      {{ errorMessage }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AutoSaveStatus } from '@/components/common/autoSave/types'

const props = withDefaults(
  defineProps<{
    inputId: string
    label: string
    showLabel?: boolean
    spanClass?: string
    status: AutoSaveStatus
    isSaving: boolean
    isPending?: boolean
    showSuccessIcon: boolean
    isFocused: boolean
    isDirty: boolean
    hasDisplayValue: boolean
    disabled?: boolean
    errorMessage?: string
    savedLabel: string
    retryLabel: string
    cancelLabel: string
  }>(),
  {
    showLabel: true,
    spanClass: 'form-group',
    isPending: false,
    disabled: false,
    errorMessage: '',
  },
)

defineEmits<{
  retry: []
  cancel: []
}>()

const showProgress = computed(() => props.isSaving || props.isPending)
</script>
