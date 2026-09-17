<template>
  <div class="beschaffung-bestellungen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.bestellungen.intro') }}</p>

    <div v-if="!isLoading && lines.length > 0" class="orders-toolbar">
      <ESearchField
        v-model="query"
        class="orders-toolbar__search"
        :label="t('grossanlass.beschaffung.bestellungen.search')"
      />
      <ESelect
        v-model="statusFilter"
        class="orders-toolbar__select"
        :label="t('grossanlass.beschaffung.bestellungen.filter')"
        :items="statusItems"
        hide-details
      />
      <div class="orders-toolbar__expand">
        <EButton variant="text" size="small" @click="expandAll">
          {{ t('grossanlass.beschaffung.zusagen.expandAll') }}
        </EButton>
        <EButton variant="text" size="small" @click="collapseAll">
          {{ t('grossanlass.beschaffung.zusagen.collapseAll') }}
        </EButton>
      </div>
    </div>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="lines.length === 0"
      variant="default"
      icon="mdi-cart-outline"
      :title="t('grossanlass.beschaffung.bestellungen.emptyTitle')"
      :description="t('grossanlass.beschaffung.bestellungen.emptyDescription')"
    />

    <EEmptyState
      v-else-if="groups.length === 0"
      variant="search"
      icon="mdi-magnify"
      :title="t('grossanlass.beschaffung.bestellungen.noMatchTitle')"
      :description="t('grossanlass.beschaffung.bestellungen.noMatchDescription')"
    />

    <v-expansion-panels
      v-else
      v-model="openGroups"
      multiple
      class="e-accordions"
    >
      <v-expansion-panel
        v-for="group in groups"
        :key="group.id"
        :value="group.id"
      >
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ group.label }}
              <span class="panel-head__count">{{ group.lines.length }}</span>
            </span>
            <span class="panel-head__actions">
              <EButton
                variant="text"
                size="small"
                @click.stop="goOfferten(group.label, group.lines[0]?.id)"
              >
                {{ t('grossanlass.beschaffung.bestellungen.moreFromSupplier', { supplier: group.label }) }}
              </EButton>
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
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
              <span v-if="quoteSchedule(selectedQuote(line)!)" class="quote-schedule" :class="{ 'is-late': quoteIsLate(line, selectedQuote(line)!) }">
                {{ quoteSchedule(selectedQuote(line)!) }}
                <span v-if="quoteIsLate(line, selectedQuote(line)!)">{{ t('grossanlass.beschaffung.offerten.deliveryLate') }}</span>
              </span>
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
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
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
import { EButton, ESearchField, ESelect, ETextField } from '@/components/form/base'
import { resolveMediaPreviewUrl } from '@/api/media'
import { formatGaDateLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  formatChf,
  listGrossanlassProcurementLines,
  upsertGrossanlassProcurementOrder,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementQuote,
} from '@/api/grossanlassProcurement'

type StatusFilter = 'all' | 'open' | 'ordered' | 'partial'
type SupplierGroup = { id: string; label: string; lines: GrossanlassProcurementLine[] }

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const savingLineId = ref<string | null>(null)
const query = ref('')
const statusFilter = ref<StatusFilter>('all')
const openGroups = ref<string[]>([])
const orderForms = reactive<Record<string, {
  cost_chf: string
  order_ref: string
  ordered_at: string
  delivery_at: string
  notes: string
}>>({})

const focusLineId = computed(() => String(route.query.line || '').trim())

const statusItems = computed(() => [
  { title: t('grossanlass.beschaffung.bestellungen.filterAll'), value: 'all' },
  { title: t('grossanlass.beschaffung.bestellungen.filterOpen'), value: 'open' },
  { title: t('grossanlass.beschaffung.status.bestellt'), value: 'ordered' },
  { title: t('grossanlass.beschaffung.status.teilweise_erhalten'), value: 'partial' },
])

function selectedQuote(line: GrossanlassProcurementLine): GrossanlassProcurementQuote | null {
  return line.quotes.find((quote) => quote.selected) ?? null
}

function quoteSchedule(quote: GrossanlassProcurementQuote): string {
  const parts: string[] = []
  if (quote.delivery_at) {
    parts.push(t('grossanlass.beschaffung.offerten.onSite', {
      date: formatGaDateLabel(quote.delivery_at, locale.value),
    }))
  }
  if (quote.lead_days != null) {
    parts.push(t('grossanlass.beschaffung.offerten.leadDaysShort', { count: quote.lead_days }))
  }
  return parts.join(' · ')
}

function quoteIsLate(line: GrossanlassProcurementLine, quote: GrossanlassProcurementQuote): boolean {
  if (!quote.delivery_at || !line.need_from) return false
  return quote.delivery_at.slice(0, 10) > line.need_from.slice(0, 10)
}

function matchesStatus(line: GrossanlassProcurementLine): boolean {
  if (statusFilter.value === 'all') return true
  if (statusFilter.value === 'open') return !line.order || line.status === 'budgetiert'
  if (statusFilter.value === 'ordered') return line.status === 'bestellt'
  return line.status === 'teilweise_erhalten'
}

function searchHaystack(line: GrossanlassProcurementLine): string {
  const quote = selectedQuote(line)
  return [
    line.label,
    line.group_name,
    line.location,
    line.category_name,
    line.category_parent_name,
    line.notes,
    quote?.supplier,
    quote?.notes,
    line.order?.order_ref,
    line.order?.notes,
  ].filter(Boolean).join(' ').toLowerCase()
}

function matchesQuery(line: GrossanlassProcurementLine): boolean {
  const needle = query.value.trim().toLowerCase()
  if (!needle) return true
  return searchHaystack(line).includes(needle)
}

const groups = computed<SupplierGroup[]>(() => {
  const buckets = new Map<string, SupplierGroup>()
  for (const line of lines.value) {
    if (!matchesStatus(line) || !matchesQuery(line)) continue
    const quote = selectedQuote(line)
    const label = quote?.supplier || t('grossanlass.beschaffung.offerten.supplier')
    const id = `s:${label.toLowerCase()}`
    const bucket = buckets.get(id) ?? { id, label, lines: [] }
    bucket.lines.push(line)
    buckets.set(id, bucket)
  }
  return [...buckets.values()].sort((a, b) => a.label.localeCompare(b.label, locale.value))
})

function expandAll() {
  openGroups.value = groups.value.map((group) => group.id)
}

function collapseAll() {
  openGroups.value = []
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
  const quote = selectedQuote(line)
  orderForms[line.id] = {
    cost_chf: line.order ? String(line.order.cost_chf) : line.budget_chf != null ? String(line.budget_chf) : '',
    order_ref: line.order?.order_ref || '',
    ordered_at: toDateInput(line.order?.ordered_at),
    delivery_at: line.order?.delivery_at
      ? toDateInput(line.order.delivery_at)
      : quote?.delivery_at
        ? toDateInput(quote.delivery_at)
        : '',
    notes: line.order?.notes || '',
  }
}

function groupIdForLine(lineId: string): string | null {
  const group = groups.value.find((item) => item.lines.some((line) => line.id === lineId))
  return group?.id ?? null
}

async function scrollToFocus() {
  const id = focusLineId.value
  if (!id) return
  const groupId = groupIdForLine(id)
  if (groupId && !openGroups.value.includes(groupId)) {
    openGroups.value = [...openGroups.value, groupId]
  }
  await nextTick()
  document.getElementById(`order-line-${id}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' })
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
    expandAll()
    await scrollToFocus()
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

watch([query, statusFilter], () => {
  expandAll()
})

watch(focusLineId, () => {
  void scrollToFocus()
})

onMounted(load)
</script>

<style scoped>
.beschaffung-bestellungen { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.orders-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: flex-end;
  margin-bottom: 14px;
}
.orders-toolbar__search { flex: 1 1 200px; min-width: min(100%, 180px); }
.orders-toolbar__select { flex: 0 1 200px; min-width: 160px; }
.orders-toolbar__expand {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-left: auto;
}
.line-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; background: #fff; }
.line-card + .line-card { margin-top: 10px; }
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
.quote-schedule { font-size: 0.78rem; color: #475569; }
.quote-schedule.is-late { color: #b45309; font-weight: 600; }
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
