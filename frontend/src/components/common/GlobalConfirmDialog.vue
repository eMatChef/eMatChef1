<template>
  <EDialog
    v-model="dialogOpen"
    max-width="420"
    card-variant="outlined"
    :card-class="confirmCardClass"
  >
    <div
      v-if="confirmStore.options"
      class="global-confirm-dialog"
      :class="`global-confirm-dialog--${confirmStore.options.variant}`"
    >
      <div class="global-confirm-dialog__icon" aria-hidden="true">
        <v-icon :icon="iconForVariant(confirmStore.options.variant)" size="28" />
      </div>
      <h3 class="global-confirm-dialog__title">{{ confirmStore.options.title }}</h3>
      <p v-if="confirmStore.options.message" class="global-confirm-dialog__message">
        {{ confirmStore.options.message }}
      </p>
    </div>

    <template v-if="confirmStore.options" #actions>
      <v-spacer />
      <EButton variant="secondary" @click="confirmStore.cancel()">
        {{ confirmStore.options.cancelText }}
      </EButton>
      <EButton :variant="confirmButtonVariant" @click="confirmStore.confirm()">
        {{ confirmStore.options.confirmText }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { EButton, EDialog } from '@/components/form/base'
import { useConfirmStore, type ConfirmVariant } from '@/stores/confirm'

const confirmStore = useConfirmStore()

const dialogOpen = computed({
  get: () => confirmStore.isOpen,
  set: (open: boolean) => {
    if (!open) {
      confirmStore.cancel()
    }
  },
})

const confirmCardClass = computed(() => {
  const variant = confirmStore.options?.variant ?? 'warning'
  return ['global-confirm-dialog-card', `global-confirm-dialog-card--${variant}`]
})

const confirmButtonVariant = computed(() =>
  confirmStore.options?.variant === 'danger' ? 'danger' : 'primary'
)

function iconForVariant(variant: ConfirmVariant): string {
  switch (variant) {
    case 'danger':
      return 'mdi-close-circle'
    case 'warning':
      return 'mdi-alert'
    default:
      return 'mdi-information'
  }
}
</script>
