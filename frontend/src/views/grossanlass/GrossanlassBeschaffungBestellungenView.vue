<template>
  <div class="beschaffung-bestellungen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.bestellungen.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="groups.length === 0"
      variant="default"
      icon="mdi-cart-outline"
      :title="t('grossanlass.beschaffung.bestellungen.emptyTitle')"
      :description="t('grossanlass.beschaffung.bestellungen.emptyDescription')"
    />

    <div v-else class="supplier-list">
      <section v-for="group in groups" :key="group.id" class="supplier-card">
        <header class="supplier-card__head">
          <h3>{{ group.label }}</h3>
          <EButton variant="text" size="small" @click="goOfferten(group.label, group.lines[0]?.id)">
            {{ t('grossanlass.beschaffung.bestellungen.moreFromSupplier', { supplier: group.label }) }}
          </EButton>
        </header>
        <article
          v-for="line in group.lines"
          :id="`order-line-${line.id}`"
          :key="line.id"
          class="line-card"
          :class="{ 'is-focus': focusLineId === line.id }"
        >
          <GrossanlassProcurementLineSummary :line="line" />

          <div v-if="selectedQuote(line)" class="quote-box">
            <strong>{{ t('grossanlass.beschaffung.bestellungen.selectedQuote') }}</strong>
            <span>{{ selectedQuote(line)?.supplier }} · {{ formatChf(selectedQuote(line)?.amount_chf ?? 0) }}</span>
            <p v-if="selectedQuote(line)?.notes" class="quote-notes">{{ selectedQuote(line)?.notes }}</p>
            <div class="quote-box__actions">
              <EButton variant="text" size="small" @click="viewQuote(line)">
                {{ t('grossanlass.beschaffung.zusagen.viewQuote') }}
              </EButton>
              <a
                v-if="selectedQuote(line)?.pdf_url"
                :href="resolvePdfUrl(selectedQuote(line)!.pdf_url!)"
                target="_blank"
                rel="noopener"
                class="quote-pdf-link"
              >
                {{ t('grossanlass.beschaffung.offerten.viewPdf') }}
              </a>
            </div>
          </div>

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
              v-model="orderForms[line.id].delivery_at"
              type="date"
              :label="t('grossanlass.beschaffung.bestellungen.deliveryAt')"
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
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import { EButton, ETextField } from '@/components/form/base'
import { resolveMediaPreviewUrl } from '@/api/media'
import {
  formatChf,
  listGrossanlassProcurementLines,
  upsertGrossanlassProcurementOrder,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementQuote,
} from '@/api/grossanlassProcurement'

type SupplierGroup = { id: string; label: string; lines: GrossanlassProcurementLine[] }

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const savingLineId = ref<string | null>(null)
const orderForms = reactive<Record<string, {
  cost_chf: string
  order_ref: string
  ordered_at: string
  delivery_at: string
  notes: string
}>>({})

const focusLineId = computed(() => String(route.query.line || '').trim())

const groups = computed<SupplierGroup[]>(() => {
  const buckets = new Map<string, SupplierGroup>()
  for (const line of lines.value) {
    const quote = selectedQuote(line)
    const label = quote?.supplier || t('grossanlass.beschaffung.offerten.supplier')
    const id = `s:${label.toLowerCase()}`
    const bucket = buckets.get(id) ?? { id, label, lines: [] }
    bucket.lines.push(line)
    buckets.set(id, bucket)
  }
  return [...buckets.values()].sort((a, b) => a.label.localeCompare(b.label, locale.value))
})

function selectedQuote(line: GrossanlassProcurementLine): GrossanlassProcurementQuote | null {
  return line.quotes.find((quote) => quote.selected) ?? null
}

function resolvePdfUrl(url: string): string {
  return resolveMediaPreviewUrl(url)
}

function viewQuote(line: GrossanlassProcurementLine) {
  const quote = selectedQuote(line)
  if (quote?.pdf_url) {
    window.open(resolvePdfUrl(quote.pdf_url), '_blank', 'noopener')
    return
  }
  goOfferten(quote?.supplier || '', line.id)
}

function goOfferten(supplier: string, lineId?: string) {
  const id = departmentId()
  if (!id) return
  void router.push({
    path: `/${id}/beschaffung/offerten`,
    query: {
      ...(supplier ? { supplier } : {}),
      ...(lineId ? { line: lineId } : {}),
    },
  })
}

function toDateInput(iso: string | undefined): string {
  if (!iso) return new Date().toISOString().slice(0, 10)
  return iso.slice(0, 10)
}

function ensureForm(line: GrossanlassProcurementLine) {
  orderForms[line.id] = {
    cost_chf: line.order ? String(line.order.cost_chf) : line.budget_chf != null ? String(line.budget_chf) : '',
    order_ref: line.order?.order_ref || '',
    ordered_at: toDateInput(line.order?.ordered_at),
    delivery_at: line.order?.delivery_at ? toDateInput(line.order.delivery_at) : '',
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
    await nextTick()
    if (focusLineId.value) {
      document.getElementById(`order-line-${focusLineId.value}`)?.scrollIntoView({ block: 'center' })
    }
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
      delivery_at: form.delivery_at ? `${form.delivery_at}T12:00:00` : null,
    })
    toast.success(t('grossanlass.beschaffung.bestellungen.saveSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorSave'))
  } finally {
    savingLineId.value = null
  }
}

watch(focusLineId, async (id) => {
  if (!id) return
  await nextTick()
  document.getElementById(`order-line-${id}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' })
})

onMounted(load)
</script>

<style scoped>
.beschaffung-bestellungen { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.supplier-list { display: flex; flex-direction: column; gap: 16px; }
.supplier-card { display: grid; gap: 10px; }
.supplier-card__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}
.supplier-card__head h3 { margin: 0; font-size: 1rem; }
.line-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; background: #fff; }
.line-card.is-focus { border-color: #86efac; box-shadow: 0 0 0 2px #dcfce7; }
.quote-box {
  margin-top: 10px;
  padding: 8px 10px;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  background: #eff6ff;
  display: grid;
  gap: 4px;
  font-size: 0.85rem;
}
.quote-notes { margin: 0; color: #64748b; font-size: 0.78rem; }
.quote-box__actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.quote-pdf-link { font-size: 0.78rem; color: #2563eb; }
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
