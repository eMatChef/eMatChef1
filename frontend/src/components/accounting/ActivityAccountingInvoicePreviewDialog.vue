<template>
  <EDialog
    :model-value="modelValue"
    :max-width="820"
    :title="t('accounting.invoicePreview.dialogTitle')"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <ELoadingState v-if="loading" variant="inline" :message="t('accounting.common.loading')" />
    <p v-else-if="error" class="invoice-preview-error">{{ error }}</p>
    <div v-else-if="invoice" class="invoice-a4">
      <header class="invoice-a4__header">
        <div>
          <p class="invoice-a4__eyebrow">{{ t('accounting.invoicePreview.eyebrow') }}</p>
          <h2 class="invoice-a4__title">{{ invoice.activity_name }}</h2>
          <p v-if="invoice.customer_label" class="invoice-a4__customer">
            {{ t('accounting.invoicePreview.customer', { name: invoice.customer_label }) }}
          </p>
        </div>
        <div class="invoice-a4__meta">
          <span class="invoice-a4__status" :class="`invoice-a4__status--${invoice.status}`">
            {{ statusLabel }}
          </span>
          <p class="invoice-a4__date">{{ issuedLabel }}</p>
        </div>
      </header>

      <table class="invoice-a4__table">
        <thead>
          <tr>
            <th class="col-pos">{{ t('accounting.invoicePreview.colPos') }}</th>
            <th>{{ t('accounting.invoicePreview.colLabel') }}</th>
            <th class="col-qty">{{ t('accounting.invoicePreview.colQty') }}</th>
            <th class="col-amt">{{ t('accounting.invoicePreview.colAmount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, idx) in invoice.lines" :key="`${line.kind}-${idx}`">
            <td class="col-pos">{{ idx + 1 }}</td>
            <td>
              <div class="invoice-a4__line-kind">
                <template v-if="line.expected">{{ t('activities.costs.invoiceLineExpected') }}</template>
                <template v-else-if="line.kind === 'consumption_item'">
                  {{ t('accounting.invoicePreview.kindConsumption') }}
                </template>
                <template v-else>{{ t(accountingFollowUpKindKey(line.source_kind)) }}</template>
              </div>
              <div class="invoice-a4__line-label">
                {{ line.material_name || line.label || '—' }}
              </div>
            </td>
            <td class="col-qty">
              <template v-if="line.quantity != null">{{ line.quantity }}</template>
              <template v-else>—</template>
            </td>
            <td class="col-amt">
              <template v-if="line.amount_chf != null">
                CHF {{ formatMoney(line.amount_chf) }}
                <span v-if="line.estimated" class="invoice-a4__est">
                  {{ t('activities.costs.invoiceEstimateTag') }}
                </span>
              </template>
              <template v-else>{{ t('activities.costs.invoiceAmountPending') }}</template>
            </td>
          </tr>
        </tbody>
      </table>

      <footer class="invoice-a4__footer">
        <div class="invoice-a4__totals">
          <div class="invoice-a4__total-row">
            <span>{{ t('activities.costs.invoiceTotal') }}</span>
            <strong>CHF {{ formatMoney(invoice.total_chf) }}</strong>
          </div>
          <div
            v-if="parseFloat(invoice.estimated_open_chf) > 0"
            class="invoice-a4__total-row invoice-a4__total-row--muted"
          >
            <span>{{ t('accounting.invoicePreview.estimateOpen') }}</span>
            <span>CHF {{ formatMoney(invoice.estimated_open_chf) }}</span>
          </div>
        </div>
        <p class="invoice-a4__note">{{ t('accounting.invoicePreview.note') }}</p>
      </footer>
    </div>

    <template #actions>
      <EButton variant="secondary" size="small" @click="emit('update:modelValue', false)">
        {{ t('common.close') }}
      </EButton>
      <EButton
        v-if="departmentId && invoice?.activity_id"
        variant="secondary"
        size="small"
        :to="{
          name: 'ActivityDetail',
          params: { departmentId, activityId: invoice.activity_id },
          query: { tab: 'costs' },
        }"
      >
        {{ t('accounting.invoicePreview.openActivity') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityAccountingInvoice } from '@/api/activityAccountingInvoice'
import { accountingFollowUpKindKey } from '@/utils/accountingFollowUpLabels'
import { EButton, EDialog } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'

defineOptions({ name: 'ActivityAccountingInvoicePreviewDialog' })

const props = defineProps<{
  modelValue: boolean
  invoice: ActivityAccountingInvoice | null
  loading?: boolean
  error?: string
  departmentId?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { t, te, locale } = useI18n()

const statusLabel = computed(() => {
  const status = props.invoice?.status || ''
  const key = `accounting.bookings.invoiceStatus.${status}`
  return te(key) ? t(key) : status
})

const issuedLabel = computed(() =>
  t('accounting.invoicePreview.issuedAt', {
    date: new Date().toLocaleDateString(locale.value, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }),
  }),
)

function formatMoney(v: string | number | null | undefined): string {
  const n = typeof v === 'number' ? v : parseFloat(String(v ?? '0').replace(',', '.'))
  if (!Number.isFinite(n)) return '0.00'
  return n.toFixed(2)
}
</script>

<style scoped>
.invoice-preview-error {
  margin: 0;
  color: #b91c1c;
}

.invoice-a4 {
  background: #fff;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  padding: 28px 32px 24px;
  min-height: 60vh;
  max-width: 210mm;
  margin: 0 auto;
}

.invoice-a4__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 2px solid #111827;
  margin-bottom: 18px;
}

.invoice-a4__eyebrow {
  margin: 0 0 4px;
  font-size: 12px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
}

.invoice-a4__title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.25;
}

.invoice-a4__customer {
  margin: 6px 0 0;
  font-size: 0.95rem;
}

.invoice-a4__meta {
  text-align: right;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.invoice-a4__status {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 3px 9px;
  border-radius: 999px;
  background: #f3f4f6;
  color: #374151;
}

.invoice-a4__status--blocked {
  background: #fef3c7;
  color: #92400e;
}

.invoice-a4__status--open {
  background: #dbeafe;
  color: #1e40af;
}

.invoice-a4__status--paid {
  background: #d1fae5;
  color: #065f46;
}

.invoice-a4__date {
  margin: 0;
  font-size: 13px;
  color: #6b7280;
}

.invoice-a4__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.invoice-a4__table th,
.invoice-a4__table td {
  padding: 10px 8px;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
  text-align: left;
}

.invoice-a4__table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
  font-weight: 600;
}

.col-pos {
  width: 2.5rem;
  color: #6b7280;
}

.col-qty {
  width: 4.5rem;
  text-align: right !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.col-amt {
  text-align: right !important;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.invoice-a4__line-kind {
  font-weight: 600;
}

.invoice-a4__line-label {
  margin-top: 2px;
  color: #6b7280;
  font-size: 13px;
}

.invoice-a4__est {
  display: block;
  font-size: 11px;
  color: #92400e;
}

.invoice-a4__footer {
  margin-top: 20px;
  padding-top: 14px;
  border-top: 2px solid #111827;
}

.invoice-a4__totals {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}

.invoice-a4__total-row {
  display: flex;
  gap: 24px;
  font-size: 1rem;
}

.invoice-a4__total-row--muted {
  font-size: 0.9rem;
  color: #6b7280;
}

.invoice-a4__note {
  margin: 16px 0 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.45;
}

@media (max-width: 640px) {
  .invoice-a4 {
    padding: 16px;
    min-height: auto;
  }

  .invoice-a4__header {
    flex-direction: column;
  }

  .invoice-a4__meta {
    align-items: flex-start;
    text-align: left;
  }
}
</style>
