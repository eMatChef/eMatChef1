<template>
  <EDialog v-model="open" max-width="420" card-variant="outlined" persistent>
    <form class="step-up-dialog" @submit.prevent="submit">
      <h3 class="step-up-dialog__title">{{ t('layout.stepUp.title') }}</h3>
      <p class="step-up-dialog__message">
        {{ useRecovery ? t('layout.stepUp.recoveryHint') : t('layout.stepUp.totpHint') }}
      </p>
      <ETextField
        v-model="code"
        :label="useRecovery ? t('layout.stepUp.recoveryLabel') : t('layout.stepUp.codeLabel')"
        :inputmode="useRecovery ? 'text' : 'numeric'"
        autocomplete="one-time-code"
        :error-messages="error ? [error] : []"
        autofocus
      />
      <EButton variant="text" size="small" type="button" :disabled="busy" @click="toggle">
        {{ useRecovery ? t('layout.stepUp.useTotp') : t('layout.stepUp.useRecovery') }}
      </EButton>
    </form>
    <template #actions>
      <v-spacer />
      <EButton variant="secondary" :disabled="busy" @click="cancel">{{ t('common.cancel') }}</EButton>
      <EButton :loading="busy" :disabled="!code.trim()" @click="submit">{{ t('layout.stepUp.confirm') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { confirmStepUp } from '@/api/stepUp'
import { useStepUpStore } from '@/stores/stepUp'

const { t } = useI18n()
const store = useStepUpStore()

const code = ref('')
const useRecovery = ref(false)
const busy = ref(false)
const error = ref('')

const open = computed({
  get: () => store.isOpen,
  set: (value: boolean) => {
    if (!value && store.isOpen) cancel()
  },
})

watch(
  () => store.isOpen,
  (isOpen) => {
    if (isOpen) {
      code.value = ''
      error.value = ''
      useRecovery.value = false
    }
  },
)

function toggle() {
  useRecovery.value = !useRecovery.value
  code.value = ''
  error.value = ''
}

function cancel() {
  store.finish(false)
}

async function submit() {
  if (busy.value || !code.value.trim()) return
  busy.value = true
  error.value = ''
  try {
    await confirmStepUp(code.value.trim())
    code.value = ''
    store.finish(true)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || t('layout.stepUp.failed')
    code.value = ''
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.step-up-dialog {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem 1rem 0;
}

.step-up-dialog__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.step-up-dialog__message {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  opacity: 0.85;
}
</style>
