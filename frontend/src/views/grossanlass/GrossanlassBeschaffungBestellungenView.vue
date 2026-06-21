<template>
  <div class="beschaffung-bestellungen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.bestellungen.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="lines.length === 0"
      variant="default"
      icon="mdi-cart-outline"
      :title="t('grossanlass.beschaffung.bestellungen.emptyTitle')"
      :description="t('grossanlass.beschaffung.bestellungen.emptyDescription')"
    />

    <div v-else class="lines-list">
      <article v-for="line in lines" :key="line.id" class="line-card">
        <GrossanlassProcurementLineSummary :line="line" />

        <form class="order-form" @submit.prevent="saveOrder(line)">
          <ETextField
            v-model="orderForms[line.id].cost_chf"
            type="number"
            min="0"
            step="0.05"
            :label="t('grossanlass.beschaffung.bestellungen.costChf')"
            hide-details
            density="compact"
          />
          <ETextField
            v-model="orderForms[line.id].order_ref"
            :label="t('grossanlass.beschaffung.bestellungen.orderRef')"
            hide-details
            density="compact"
          />
          <ETextField
            v-model="orderForms[line.id].ordered_at"
            type="date"
            :label="t('grossanlass.beschaffung.bestellungen.orderedAt')"
            hide-details
            density="compact"
          />
          <ETextField
            v-model="orderForms[line.id].notes"
            :label="t('grossanlass.beschaffung.bestellungen.notes')"
            hide-details
            density="compact"
          />
          <EButton
            variant="primary"
            size="small"
            type="submit"
            :loading="savingLineId === line.id"
            :disabled="!orderForms[line.id].cost_chf"
          >
            {{ line.order ? t('common.save') : t('grossanlass.beschaffung.bestellungen.placeOrder') }}
          </EButton>
        </form>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  listGrossanlassProcurementLines,
  upsertGrossanlassProcurementOrder,
  type GrossanlassProcurementLine,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const savingLineId = ref<string | null>(null)
const orderForms = reactive<Record<string, { cost_chf: string; order_ref: string; ordered_at: string; notes: string }>>({})

function toDateInput(iso: string | undefined): string {
  if (!iso) return new Date().toISOString().slice(0, 10)
  return iso.slice(0, 10)
}

function ensureForm(line: GrossanlassProcurementLine) {
  orderForms[line.id] = {
    cost_chf: line.order ? String(line.order.cost_chf) : line.budget_chf != null ? String(line.budget_chf) : '',
    order_ref: line.order?.order_ref || '',
    ordered_at: toDateInput(line.order?.ordered_at),
    notes: line.order?.notes || '',
  }
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    const all = await listGrossanlassProcurementLines(departmentId())
    lines.value = all.filter(
      (l) => l.budget_chf != null && ['budgetiert', 'bestellt', 'teilweise_erhalten'].includes(l.status),
    )
    lines.value.forEach(ensureForm)
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

async function saveOrder(line: GrossanlassProcurementLine) {
  const form = orderForms[line.id]
  if (!form.cost_chf) return
  savingLineId.value = line.id
  try {
    await upsertGrossanlassProcurementOrder(departmentId(), line.id, {
      cost_chf: Number(form.cost_chf),
      order_ref: form.order_ref.trim() || null,
      notes: form.notes.trim() || null,
      ordered_at: `${form.ordered_at}T12:00:00`,
    })
    toast.success(t('grossanlass.beschaffung.bestellungen.saveSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorSave'))
  } finally {
    savingLineId.value = null
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-bestellungen { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.lines-list { display: flex; flex-direction: column; gap: 12px; }
.line-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; background: #fff; }
.order-form {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #e5e7eb;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
  align-items: start;
}
</style>
