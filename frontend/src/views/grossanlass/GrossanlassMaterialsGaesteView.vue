<template>
  <div class="ga-gaeste">
    <p class="tab-intro">{{ t('grossanlass.materials.gaeste.intro') }}</p>

    <div class="ga-gaeste__toolbar">
      <div class="view-toggle" role="tablist">
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'bestand' }"
          @click="view = 'bestand'"
        >
          {{ t('grossanlass.materials.gaeste.viewBestand') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'loan' }"
          @click="view = 'loan'"
        >
          {{ t('grossanlass.materials.gaeste.viewLoan') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'sale' }"
          @click="view = 'sale'"
        >
          {{ t('grossanlass.materials.gaeste.viewSale') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'js' }"
          @click="view = 'js'"
        >
          {{ t('grossanlass.materials.gaeste.viewJs') }}
        </button>
      </div>
      <ESearchField
        v-model="query"
        class="ga-gaeste__search"
        :label="t('grossanlass.materials.gaeste.search')"
      />
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <template v-else-if="view === 'bestand'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.bestandHint') }}</p>
      <v-expansion-panels v-if="bestandGroups.length" v-model="openBestand" multiple class="ga-gaeste-accordion">
        <v-expansion-panel v-for="group in bestandGroups" :key="group.id" :value="group.id">
          <v-expansion-panel-title>
            <span class="group-title">
              <strong>{{ group.label }}</strong>
              <span class="group-count">{{ group.rows.length }}</span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ul class="loan-list">
              <li v-for="row in group.rows" :key="row.id" class="loan-card">
                <div class="loan-card__head">
                  <div>
                    <strong>{{ row.name }}</strong>
                    <span class="chip is-no">{{ t('grossanlass.materials.gaeste.campChip') }}</span>
                    <span v-if="row.share_status === 'offered' || row.share_status === 'accepted'" class="chip is-ok">
                      {{ t('grossanlass.materials.gaeste.releasedToUs') }}
                    </span>
                  </div>
                  <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
                </div>
              </li>
            </ul>
            <p v-if="!group.rows.length" class="muted">{{ t('grossanlass.materials.gaeste.emptyDeptStock') }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.gaeste.emptyGuestsTitle')"
        :description="t('grossanlass.materials.gaeste.emptyGuestsText')"
      />
    </template>

    <template v-else-if="view === 'loan'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.loanHint') }}</p>
      <v-expansion-panels v-if="loanGroups.length" v-model="openLoan" multiple class="ga-gaeste-accordion">
        <v-expansion-panel v-for="group in loanGroups" :key="group.id" :value="group.id">
          <v-expansion-panel-title>
            <span class="group-title">
              <strong>{{ group.label }}</strong>
              <span class="group-count">{{ group.rows.length }}</span>
              <span v-if="group.offeredCount" class="chip is-wait">
                {{ t('grossanlass.materials.gaeste.offeredCount', { count: group.offeredCount }) }}
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ul class="loan-list">
              <li v-for="row in group.rows" :key="row.id" class="loan-card">
                <div class="loan-card__head">
                  <div>
                    <strong>{{ row.name }}</strong>
                    <span class="chip" :class="statusClass(row.status)">
                      {{ t(`grossanlass.materials.gaeste.loanStatus.${row.status}`) }}
                    </span>
                  </div>
                  <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
                </div>
                <p v-if="row.from_label && row.to_label">
                  {{ t('grossanlass.materials.gaeste.loanWindow', { from: row.from_label, to: row.to_label }) }}
                </p>
                <div class="loan-card__actions">
                  <EButton
                    v-if="row.status === 'offered'"
                    variant="primary"
                    size="small"
                    @click="acceptLoan(row.id)"
                  >
                    {{ t('grossanlass.materials.gaeste.acceptLoan') }}
                  </EButton>
                  <EButton
                    variant="secondary"
                    size="small"
                    :disabled="!row.bookable"
                    @click="bookLoan(row)"
                  >
                    {{ t('grossanlass.materials.gaeste.bookEinsatz') }}
                  </EButton>
                </div>
              </li>
            </ul>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.gaeste.emptyLoanTitle')"
        :description="t('grossanlass.materials.gaeste.emptyLoanText')"
      />
    </template>

    <template v-else-if="view === 'sale'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.saleHint') }}</p>
      <div class="sale-form">
        <ESelect
          v-model="saleGuestId"
          :label="t('grossanlass.materials.gaeste.saleGuest')"
          :items="guestSelectItems"
          hide-details
        />
        <ESelect
          v-model="saleStockId"
          :label="t('grossanlass.materials.gaeste.saleItem')"
          :items="saleStockItems"
          hide-details
        />
        <EButton variant="primary" size="small" :disabled="!canSell" @click="sellNow">
          {{ t('grossanlass.materials.gaeste.saleConfirm') }}
        </EButton>
      </div>
      <ul v-if="filteredSales.length" class="loan-list">
        <li v-for="row in filteredSales" :key="row.id" class="loan-card">
          <div class="loan-card__head">
            <div>
              <strong>{{ row.name }}</strong>
              <span class="chip is-ok">{{ t('grossanlass.materials.gaeste.soldChip') }}</span>
            </div>
            <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
          </div>
          <p>{{ row.guest_name }}</p>
        </li>
      </ul>
      <p v-else class="muted">{{ t('grossanlass.materials.gaeste.emptySales') }}</p>
    </template>

    <template v-else>
      <div class="summary-row">
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.materials.gaeste.jsDepts') }}</span>
          <strong>{{ jsDeptCount }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.materials.gaeste.jsMissing') }}</span>
          <strong>{{ jsMissingCount }}</strong>
        </div>
        <EButton variant="primary" size="small" disabled>
          {{ t('grossanlass.materials.gaeste.jsBundle') }}
        </EButton>
      </div>
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.jsHint') }}</p>
      <v-expansion-panels v-if="filteredJs.length" v-model="openJs" multiple class="ga-gaeste-accordion">
        <v-expansion-panel v-for="article in filteredJs" :key="article.id" :value="article.id">
          <v-expansion-panel-title>
            <span class="group-title">
              <strong>{{ jsArticleTitle(article) }}</strong>
              <span class="group-count">{{ jsArticleTotal(article) }} {{ article.unit }}</span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p v-if="article.catalog_hint" class="catalog-hint">{{ article.catalog_hint }}</p>
            <table v-if="article.lines.length" class="data-table">
              <thead>
                <tr>
                  <th>{{ t('grossanlass.materials.gaeste.colDept') }}</th>
                  <th>{{ t('grossanlass.materials.gaeste.colQty') }}</th>
                  <th>{{ t('grossanlass.materials.gaeste.colStatus') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in article.lines" :key="line.department_id">
                  <td>{{ line.department_name }}</td>
                  <td>{{ jsLineQty(line) }}</td>
                  <td>
                    <span class="chip" :class="line.status === 'submitted' ? 'is-ok' : 'is-wait'">
                      {{ t(`grossanlass.materials.gaeste.jsStatus.${line.status}`) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="muted">{{ t('grossanlass.materials.gaeste.emptyGuestsText') }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.gaeste.emptyJsTitle')"
        :description="t('grossanlass.materials.gaeste.emptyJsText')"
      />
    </template>

    <GrossanlassEinsatzBookPreviewDialog
      v-model="bookOpen"
      v-model:draft="bookDraft"
      mode="einsatz"
      :wishes="[]"
      :free-picks="guestPicks"
      :resources="guestResources"
      :chauffeurs="chauffeurs"
      @confirm="onBooked"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import {
  acceptGrossanlassGuestLoan,
  getGrossanlassGaeste,
  sellGrossanlassToGuest,
  type GaGaestePayload,
  type GaGuestJsArticle,
  type GaGuestJsLine,
  type GaGuestShare,
  type GaGuestShareStatus,
} from '@/api/grossanlassGaeste'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { resourceToPickTemplate, type GaEinsatzResource } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

type GaesteView = 'bestand' | 'loan' | 'sale' | 'js'

const { t } = useI18n()
const toast = useToast()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}
const route = useRoute()
const uebersicht = useGaUebersicht()
const view = ref<GaesteView>('bestand')
const query = ref('')
const loading = ref(false)
const payload = ref<GaGaestePayload | null>(null)
const openJs = ref<string[]>([])
const openLoan = ref<string[]>([])
const openBestand = ref<string[]>([])
const bookOpen = ref(false)
const bookDraft = ref<GaBookPreviewDraft | null>(null)
const saleGuestId = ref<string | null>(null)
const saleStockId = ref<string | null>(null)

const departmentId = computed(() => String(route.params.departmentId || ''))

async function load() {
  if (!departmentId.value) return
  loading.value = true
  try {
    payload.value = await getGrossanlassGaeste(departmentId.value)
    openBestand.value = (payload.value.departments ?? []).map((row) => row.id)
    openLoan.value = [...new Set((payload.value.offers ?? []).map((row) => row.guest_department_id))]
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
    payload.value = { departments: [], offers: [], sales: [], sale_stock: [], js: { catalog_name: null, articles: [] } }
  } finally {
    loading.value = false
  }
}

onMounted(() => { void load() })

const q = computed(() => query.value.trim().toLowerCase())

const jsArticles = computed<GaGuestJsArticle[]>(() => payload.value?.js?.articles ?? [])

const filteredJs = computed(() => {
  if (!q.value) return jsArticles.value
  return jsArticles.value.filter((article) =>
    article.name.toLowerCase().includes(q.value)
    || String(article.pdf_line_no ?? '').includes(q.value)
    || article.lines.some((line) => line.department_name.toLowerCase().includes(q.value)),
  )
})

const jsDeptIds = computed(() => {
  const ids = new Set<string>()
  for (const article of jsArticles.value) {
    for (const line of article.lines) ids.add(line.department_id)
  }
  return [...ids]
})

const jsMissingCount = computed(() => {
  const missing = new Set<string>()
  for (const article of jsArticles.value) {
    for (const line of article.lines) {
      if (line.status === 'missing') missing.add(line.department_id)
    }
  }
  return missing.size
})

const jsDeptCount = computed(() => jsDeptIds.value.length)

function jsArticleTitle(article: GaGuestJsArticle): string {
  return article.pdf_line_no != null ? `${article.pdf_line_no} · ${article.name}` : article.name
}

function jsArticleTotal(article: GaGuestJsArticle): number {
  return article.lines.reduce((sum, line) => sum + (line.status === 'submitted' || line.qty > 0 ? line.qty : 0), 0)
}

function jsLineQty(line: GaGuestJsLine): string {
  if (line.status === 'submitted' || line.qty > 0) return String(line.qty)
  return '—'
}

const bestandGroups = computed(() =>
  (payload.value?.departments ?? [])
    .map((dept) => ({
      id: dept.id,
      label: dept.name,
      rows: dept.items.filter((item) => {
        if (!q.value) return true
        return item.name.toLowerCase().includes(q.value) || dept.name.toLowerCase().includes(q.value)
      }),
    }))
    .filter((group) => group.rows.length || !q.value),
)

const loanGroups = computed(() => {
  const rows = (payload.value?.offers ?? []).filter((row) => {
    if (!q.value) return true
    return row.name.toLowerCase().includes(q.value) || row.guest_name.toLowerCase().includes(q.value)
  })
  const buckets = new Map<string, { id: string; label: string; rows: GaGuestShare[] }>()
  for (const row of rows) {
    const bucket = buckets.get(row.guest_department_id) ?? {
      id: row.guest_department_id,
      label: row.guest_name,
      rows: [],
    }
    bucket.rows.push(row)
    buckets.set(row.guest_department_id, bucket)
  }
  return [...buckets.values()].map((group) => ({
    ...group,
    offeredCount: group.rows.filter((row) => row.status === 'offered').length,
  }))
})

const filteredSales = computed(() => {
  const rows = payload.value?.sales ?? []
  if (!q.value) return rows
  return rows.filter((row) =>
    row.name.toLowerCase().includes(q.value) || row.guest_name.toLowerCase().includes(q.value),
  )
})

const guestSelectItems = computed(() =>
  (payload.value?.departments ?? []).map((dept) => ({ title: dept.name, value: dept.id })),
)

const saleStockItems = computed(() =>
  (payload.value?.sale_stock ?? []).map((row) => ({
    title: `${row.name} · ${originLabel(row.origin)} · ${t('grossanlass.materials.gaeste.qtyLine', { n: row.qty })}`,
    value: row.id,
  })),
)

function originLabel(origin: string): string {
  if (origin === 'buy_resale') return String(t('grossanlass.materials.lifecycle.buy_resale'))
  if (origin === 'buy') return String(t('grossanlass.materials.lifecycle.reusable'))
  return String(t('grossanlass.materials.lifecycle.loan'))
}

const canSell = computed(() => !!saleGuestId.value && !!saleStockId.value)

const guestResources = computed<GaEinsatzResource[]>(() =>
  (payload.value?.offers ?? [])
    .filter((row) => row.bookable && row.commitment_id)
    .map((row) => ({
      id: row.commitment_id as string,
      name: `${row.name} (${row.guest_name})`,
      family: row.family,
      stayMode: 'return' as const,
      categoryId: row.family === 'vehicle' ? 'fahrzeuge' : 'infra',
      kind: row.family === 'vehicle' ? 'unique' : 'quantity',
      stock: row.qty,
      presentFromIso: row.from || '',
      presentToIso: row.to || '',
      released: true,
    })),
)

const guestPicks = computed(() =>
  guestResources.value.map((resource) => resourceToPickTemplate(resource, tr)),
)

const chauffeurs = computed(() =>
  (uebersicht.data.value?.cards ?? []).map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)

function statusClass(status: GaGuestShareStatus | string): string {
  if (status === 'accepted' || status === 'completed') return 'is-ok'
  if (status === 'declined') return 'is-no'
  return 'is-wait'
}

async function acceptLoan(id: string) {
  try {
    payload.value = await acceptGrossanlassGuestLoan(departmentId.value, id)
    toast.success(t('grossanlass.materials.gaeste.acceptedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

function bookLoan(row: GaGuestShare) {
  const pick = guestPicks.value.find((item) => item.objectId === row.commitment_id)
  bookDraft.value = pick ? { ...pick, fromWish: false } : null
  bookOpen.value = true
}

async function onBooked(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create({
      kind: 'einsatz',
      commitment_id: current.objectId || undefined,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      pending: current.hasConflict,
      has_conflict: current.hasConflict,
    })
    toast.success(t('grossanlass.materials.gaeste.bookedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function sellNow() {
  if (!saleGuestId.value || !saleStockId.value) return
  try {
    payload.value = await sellGrossanlassToGuest(departmentId.value, {
      guest_department_id: saleGuestId.value,
      commitment_id: saleStockId.value,
    })
    toast.success(t('grossanlass.materials.gaeste.soldToast'))
    saleStockId.value = null
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

</script>

<style scoped>
.ga-gaeste { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.ga-gaeste__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  margin-bottom: 14px;
}
.ga-gaeste__search { flex: 1 1 220px; min-width: min(100%, 180px); }
.view-toggle {
  display: inline-flex;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.view-toggle__btn {
  border: 0;
  background: transparent;
  padding: 8px 12px;
  font: inherit;
  font-size: 0.85rem;
  color: #64748b;
  cursor: pointer;
}
.view-toggle__btn.is-active {
  background: var(--color-primary-muted-bg, #ecfdf3);
  color: var(--color-primary-dark, #166534);
}
.summary-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}
.stat-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 12px;
  background: #fff;
  min-width: 120px;
}
.stat-label { display: block; font-size: 0.72rem; color: #64748b; }
.section-hint { margin: 0 0 12px; color: #64748b; font-size: 0.82rem; }
.catalog-hint { margin: 0 0 10px; color: #64748b; font-size: 0.8rem; }
.muted { margin: 8px 0 0; color: #64748b; font-size: 0.85rem; }
.sale-form {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: flex-end;
  margin-bottom: 14px;
}
.sale-form > * { flex: 1 1 180px; }
.ga-gaeste-accordion :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  overflow: hidden;
  margin-bottom: 10px;
  background: #fff;
}
.group-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.group-count {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 8px;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 8px; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; }
.loan-list { list-style: none; margin: 0 0 8px; padding: 0; display: grid; gap: 10px; }
.loan-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}
.loan-card p { margin: 0; color: #334155; }
.loan-card__head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}
.loan-card__head > div { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.qty { font-size: 0.8rem; color: #64748b; font-weight: 600; }
.loan-card__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.chip {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
}
.chip.is-ok { background: #dcfce7; color: #166534; }
.chip.is-wait { background: #ffedd5; color: #c2410c; }
.chip.is-no { background: #fee2e2; color: #b91c1c; }
</style>
