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
      <ESelect
        v-model="kindFilter"
        class="orders-toolbar__select"
        :label="t('grossanlass.beschaffung.bestellungen.kindFilter')"
        :items="kindItems"
        hide-details
        data-testid="orders-kind-filter"
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

            <GrossanlassProcurementOrdersPanel
              :line="line"
              :department-id="departmentId()"
              @changed="onLineChanged"
            />
          </article>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import GrossanlassProcurementOrdersPanel from '@/components/grossanlass/GrossanlassProcurementOrdersPanel.vue'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import { resolveMediaPreviewUrl } from '@/api/media'
import { formatGaDateLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  formatChf,
  listGrossanlassProcurementLines,
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
const query = ref('')
const statusFilter = ref<StatusFilter>('all')
const openGroups = ref<string[]>([])
const kindFilter = ref<'all' | 'material' | 'fahrzeug'>('all')

const focusLineId = computed(() => String(route.query.line || '').trim())

const statusItems = computed(() => [
  { title: t('grossanlass.beschaffung.bestellungen.filterAll'), value: 'all' },
  { title: t('grossanlass.beschaffung.bestellungen.filterOpen'), value: 'open' },
  { title: t('grossanlass.beschaffung.status.bestellt'), value: 'ordered' },
  { title: t('grossanlass.beschaffung.status.teilweise_erhalten'), value: 'partial' },
])

const kindItems = computed(() => [
  { title: t('grossanlass.beschaffung.bestellungen.kindAll'), value: 'all' },
  { title: t('grossanlass.beschaffung.bestellungen.kindMaterial'), value: 'material' },
  { title: t('grossanlass.beschaffung.bestellungen.kindVehicle'), value: 'fahrzeug' },
])

function matchesKind(line: GrossanlassProcurementLine): boolean {
  if (kindFilter.value === 'all') return true
  // Gemischte Positionen (Material und Fahrzeug) erscheinen in beiden Ansichten.
  return line.wish_kind === kindFilter.value || line.wish_kind === 'beides'
}

function activeOrders(line: GrossanlassProcurementLine) {
  return (line.orders ?? (line.order ? [line.order] : [])).filter((o) => o.status !== 'cancelled')
}

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
  if (quote.inbound_mode === 'pickup') {
    parts.push(t('grossanlass.materials.zusage.inboundPickup'))
  } else if (quote.inbound_mode === 'delivery') {
    parts.push(t('grossanlass.materials.zusage.inboundDelivery'))
  }
  if (quote.return_needed) {
    parts.push(quote.return_at
      ? `${t('grossanlass.beschaffung.offerten.returnNeeded')} ${formatGaDateLabel(quote.return_at, locale.value)}`
      : t('grossanlass.beschaffung.offerten.returnNeeded'))
  }
  return parts.join(' · ')
}

function quoteIsLate(line: GrossanlassProcurementLine, quote: GrossanlassProcurementQuote): boolean {
  if (!quote.delivery_at || !line.need_from) return false
  return quote.delivery_at.slice(0, 10) > line.need_from.slice(0, 10)
}

function matchesStatus(line: GrossanlassProcurementLine): boolean {
  if (statusFilter.value === 'all') return true
  if (statusFilter.value === 'open') return (line.quantity_open ?? 0) > 0
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
    ...activeOrders(line).flatMap((o) => [o.order_ref, o.notes, o.supplier]),
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
    if (!matchesStatus(line) || !matchesQuery(line) || !matchesKind(line)) continue
    const label = activeOrders(line)[0]?.supplier || selectedQuote(line)?.supplier || t('grossanlass.beschaffung.offerten.supplier')
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
    path: `/${id}/ga/procurement/offers`,
    query: {
      ...(supplier ? { supplier } : {}),
      ...(lineId ? { line: lineId } : {}),
    },
  })
}

function onLineChanged(updated: GrossanlassProcurementLine) {
  const index = lines.value.findIndex((l) => l.id === updated.id)
  if (index >= 0) lines.value.splice(index, 1, updated)
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
    // Bestellbar ist jede Position mit offenem Bedarf (Offerte oder telefonische Absprache) sowie jede bereits bestellte.
    lines.value = all.filter(
      (l) => l.budget_chf != null || activeOrders(l).length > 0 || (l.quantity_open ?? 0) > 0,
    )
    await nextTick()
    expandAll()
    await scrollToFocus()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bestellungen.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

watch([query, statusFilter, kindFilter], () => {
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
</style>
