<template>
  <details v-if="received > 0" class="receipt-reversal" :data-testid="`receipt-reversal-${commitment.id}`">
    <summary>{{ t('grossanlass.material.wareneingang.reversal.title') }}</summary>
    <p class="receipt-reversal__hint">{{ t('grossanlass.material.wareneingang.reversal.hint') }}</p>
    <div class="receipt-reversal__form">
      <ETextField
        v-model="quantity"
        type="number"
        min="1"
        :max="received"
        :label="t('grossanlass.material.wareneingang.reversal.quantity')"
        hide-details
        density="compact"
      />
      <ETextField
        v-model="reason"
        :label="t('grossanlass.material.wareneingang.reversal.reason')"
        hide-details
        density="compact"
      />
      <EButton variant="secondary" size="small" :loading="busy" :disabled="!canSubmit" @click="submit">
        {{ t('grossanlass.material.wareneingang.reversal.submit') }}
      </EButton>
    </div>
  </details>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { reverseGrossanlassChargeReceipt, type GrossanlassCommitment } from '@/api/grossanlassCommitments'

const props = defineProps<{ commitment: GrossanlassCommitment; departmentId: string; received: number }>()
const emit = defineEmits<{ reversed: [commitment: GrossanlassCommitment] }>()

const { t } = useI18n()
const toast = useToast()
const quantity = ref('')
const reason = ref('')
const busy = ref(false)

const qty = computed(() => Number(quantity.value))
const canSubmit = computed(() => Number.isInteger(qty.value) && qty.value > 0 && qty.value <= props.received && reason.value.trim() !== '')

async function submit() {
  if (!canSubmit.value) return
  busy.value = true
  try {
    const result = await reverseGrossanlassChargeReceipt(props.departmentId, props.commitment.id, {
      quantity: qty.value,
      note: reason.value.trim(),
    })
    quantity.value = ''
    reason.value = ''
    toast.success(t('grossanlass.material.wareneingang.reversal.done'))
    emit('reversed', result.commitment)
  } catch (e: unknown) {
    toast.error((e as { response?: { data?: { error?: string } } })?.response?.data?.error || t('grossanlass.material.wareneingang.reversal.error'))
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.receipt-reversal { font-size: 0.82rem; color: #475569; }
.receipt-reversal summary { cursor: pointer; }
.receipt-reversal__hint { margin: 6px 0; color: #64748b; }
.receipt-reversal__form { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
</style>
