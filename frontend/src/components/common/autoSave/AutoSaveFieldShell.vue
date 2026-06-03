<template>
  <div
    class="autosave-field e-form-field"
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
          <v-icon class="autosave-disk-icon" icon="mdi-content-save" size="14" />
        </span>

        <template v-if="status === 'error'">
          <button
            type="button"
            class="autosave-action autosave-action--retry"
            :title="retryLabel"
            :aria-label="retryLabel"
            @click="$emit('retry')"
          >
            <v-icon icon="mdi-refresh" size="14" />
          </button>
          <button
            type="button"
            class="autosave-action autosave-action--cancel"
            :title="cancelLabel"
            :aria-label="cancelLabel"
            @click="$emit('cancel')"
          >
            <v-icon icon="mdi-close" size="14" />
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
