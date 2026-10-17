<template>
  <EDialog
    v-model="open"
    :max-width="520"
    :title="t('grossanlass.beschaffung.bedarf.takeover.quantityTitle')"
  >
    <template v-if="info">
      <dl class="qty-facts" data-testid="quantity-confirm-facts">
        <div>
          <dt>{{ t('grossanlass.beschaffung.bedarf.takeover.qtySource') }}</dt>
          <dd>{{ info.source_quantity }}</dd>
        </div>
        <div>
          <dt>{{ t('grossanlass.beschaffung.bedarf.takeover.qtyCurrent') }}</dt>
          <dd>{{ info.current_quantity }}</dd>
        </div>
        <div>
          <dt>{{ t('grossanlass.beschaffung.bedarf.takeover.qtyAdded') }}</dt>
          <dd>+ {{ info.added_quantity }}</dd>
        </div>
        <div v-if="!info.frozen">
          <dt>{{ t('grossanlass.beschaffung.bedarf.takeover.qtyProposed') }}</dt>
          <dd>{{ info.proposed_quantity }}</dd>
        </div>
      </dl>

      <p v-if="info.frozen" class="qty-note" data-testid="quantity-confirm-frozen">
        {{ t('grossanlass.beschaffung.bedarf.takeover.qtyFrozen') }}
      </p>
      <template v-else>
        <ETextField
          v-model="total"
          type="number"
          min="1"
          :label="t('grossanlass.beschaffung.bedarf.takeover.qtyTotal')"
          hide-details="auto"
          data-testid="quantity-confirm-total"
        />
        <p class="qty-note">{{ t('grossanlass.beschaffung.bedarf.takeover.qtyTotalHint') }}</p>
      </template>
    </template>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!canConfirm"
        data-testid="quantity-confirm-submit"
        @click="submit"
      >
        {{ info?.frozen
          ? t('grossanlass.beschaffung.bedarf.takeover.qtyConfirmFrozen')
          : t('grossanlass.beschaffung.bedarf.takeover.qtyConfirm') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import type { QuantityConfirmationInfo } from '@/utils/grossanlassProcurementTakeover'

const props = defineProps<{ info: QuantityConfirmationInfo | null }>()
const emit = defineEmits<{
  /** quantity = bewusst bestätigte Gesamtmenge; null bei eingefrorener Position (Menge bleibt). */
  confirm: [quantity: number | null]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()
const total = ref('')

watch(
  () => [open.value, props.info] as const,
  ([visible, info]) => {
    if (visible && info) total.value = String(info.proposed_quantity ?? info.current_quantity)
  },
  { immediate: true },
)

const canConfirm = computed(() => {
  if (!props.info) return false
  if (props.info.frozen) return true
  const value = Number(total.value)
  return Number.isInteger(value) && value >= 1
})

function submit() {
  if (!props.info || !canConfirm.value) return
  open.value = false
  emit('confirm', props.info.frozen ? null : Number(total.value))
}
</script>

<style scoped>
.qty-facts { display: grid; gap: 6px; margin: 0 0 12px; }
.qty-facts div { display: flex; justify-content: space-between; gap: 12px; font-size: 0.88rem; }
.qty-facts dt { color: #475569; }
.qty-facts dd { margin: 0; font-weight: 600; }
.qty-note { margin: 8px 0 0; font-size: 0.78rem; color: #64748b; }
</style>
