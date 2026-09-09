<template>
  <EDialog
    v-model="dialogOpen"
    max-width="420"
    card-variant="outlined"
    :card-class="confirmCardClass"
    :persistent="isPersistent"
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
      <p
        v-if="showCountdown"
        class="global-confirm-dialog__countdown-label"
      >
        {{ t('errors.sessionExpiringCountdownLabel') }}
      </p>
      <div
        v-if="showCountdown"
        class="global-confirm-dialog__countdown"
        :class="{ 'global-confirm-dialog__countdown--urgent': remainingMs <= 15000 }"
        role="timer"
        aria-live="polite"
        aria-atomic="true"
      >
        {{ countdownDisplay }}
      </div>
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
import { computed, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import { useConfirmStore, type ConfirmVariant } from '@/stores/confirm'

const { t } = useI18n()
const confirmStore = useConfirmStore()
const remainingMs = ref(0)
let countdownTickId: ReturnType<typeof setInterval> | null = null

const dialogOpen = computed({
  get: () => confirmStore.isOpen,
  set: (open: boolean) => {
    if (!open) {
      confirmStore.cancel()
    }
  },
})

const isPersistent = computed(
  () => Boolean(confirmStore.options?.persistent || confirmStore.options?.countdownEndsAt)
)

const showCountdown = computed(() => Number(confirmStore.options?.countdownEndsAt || 0) > 0)

const countdownDisplay = computed(() => {
  const totalSec = Math.max(0, Math.ceil(remainingMs.value / 1000))
  const m = Math.floor(totalSec / 60)
  const s = totalSec % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const confirmCardClass = computed(() => {
  const variant = confirmStore.options?.variant ?? 'warning'
  return ['global-confirm-dialog-card', `global-confirm-dialog-card--${variant}`]
})

const confirmButtonVariant = computed(() =>
  confirmStore.options?.variant === 'danger' ? 'danger' : 'primary'
)

function stopCountdownTick() {
  if (countdownTickId) {
    clearInterval(countdownTickId)
    countdownTickId = null
  }
}

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

watch(
  () => confirmStore.options?.countdownEndsAt,
  (endsAt) => {
    stopCountdownTick()
    if (!endsAt) {
      remainingMs.value = 0
      return
    }
    const tick = () => {
      remainingMs.value = Math.max(0, endsAt - Date.now())
    }
    tick()
    countdownTickId = setInterval(tick, 250)
  },
  { immediate: true }
)

onUnmounted(() => {
  stopCountdownTick()
})
</script>
