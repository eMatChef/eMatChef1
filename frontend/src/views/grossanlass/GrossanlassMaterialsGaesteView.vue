<template>
  <div class="ga-gaeste">
    <GrossanlassPreviewBanner />
    <p class="tab-intro">{{ t('grossanlass.materials.gaeste.intro') }}</p>

    <div class="ga-gaeste__toolbar">
      <div class="view-toggle" role="tablist">
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'js' }"
          @click="view = 'js'"
        >
          {{ t('grossanlass.materials.gaeste.viewJs') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'loan' }"
          @click="view = 'loan'"
        >
          {{ t('grossanlass.materials.gaeste.viewLoan') }}
        </button>
      </div>
      <ESearchField
        v-model="query"
        class="ga-gaeste__search"
        :label="t('grossanlass.materials.gaeste.search')"
      />
    </div>

    <template v-if="view === 'js'">
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

      <v-expansion-panels v-model="openJs" multiple class="ga-gaeste-accordion">
        <v-expansion-panel v-for="article in filteredJs" :key="article.id" :value="article.id">
          <v-expansion-panel-title>
            <span class="group-title">
              <strong>{{ article.name }}</strong>
              <span class="group-count">{{ jsArticleTotal(article) }} {{ article.unit }}</span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="catalog-hint">{{ article.catalogHint }}</p>
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ t('grossanlass.materials.gaeste.colDept') }}</th>
                  <th>{{ t('grossanlass.materials.gaeste.colQty') }}</th>
                  <th>{{ t('grossanlass.materials.gaeste.colStatus') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in article.lines" :key="line.departmentId">
                  <td>{{ line.departmentName }}</td>
                  <td>{{ line.status === 'submitted' ? line.qty : '—' }}</td>
                  <td>
                    <span class="chip" :class="line.status === 'submitted' ? 'is-ok' : 'is-wait'">
                      {{ t(`grossanlass.materials.gaeste.jsStatus.${line.status}`) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>

    <template v-else>
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.loanHint') }}</p>

      <v-expansion-panels v-model="openLoan" multiple class="ga-gaeste-accordion">
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
                <p>{{ t('grossanlass.materials.gaeste.loanWindow', { from: row.fromLabel, to: row.toLabel }) }}</p>
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
                    @click="bookLoan(row.id)"
                  >
                    {{ t('grossanlass.materials.gaeste.bookEinsatz') }}
                  </EButton>
                </div>
              </li>
            </ul>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>

    <GrossanlassEinsatzBookPreviewDialog
      v-model="bookOpen"
      v-model:draft="bookDraft"
      mode="einsatz"
      :wishes="[]"
      :free-picks="guestPicks"
      @confirm="onBooked"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ESearchField } from '@/components/form/base'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import {
  createGuestJsArticles,
  jsArticleTotal,
  type GaGuestJsArticle,
  type GaGuestLoan,
} from '@/views/grossanlass/grossanlassGaestePreviewData'
import {
  acceptedGuestLoanResources,
  listGuestLoans,
  setGuestLoanStatus,
} from '@/views/grossanlass/grossanlassGaestePreviewStore'
import { guestJsQty, isGuestJsSubmitted } from '@/views/grossanlass/grossanlassChainPreviewStore'
import { resourceToPickTemplate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

type GaesteView = 'js' | 'loan'

const { t } = useI18n()
const toast = useToast()
const view = ref<GaesteView>('js')
const query = ref('')
const openJs = ref<string[]>([])
const openLoan = ref<string[]>([])
const bookOpen = ref(false)
const bookDraft = ref<GaBookPreviewDraft | null>(null)

function tr(key: string, values?: Record<string, string | number>) {
  return values ? String(t(key, values)) : String(t(key))
}

const jsArticles = computed(() =>
  createGuestJsArticles(tr).map((article) => ({
    ...article,
    lines: article.lines.map((line) => {
      if (!isGuestJsSubmitted(line.departmentId)) return line
      return {
        ...line,
        qty: guestJsQty(article.id, line.departmentId, line.qty),
        status: 'submitted' as const,
      }
    }),
  })),
)
const loans = computed(() => listGuestLoans(tr))

const filteredJs = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = jsArticles.value
  if (!q) return list
  return list.filter((article) =>
    article.name.toLowerCase().includes(q)
    || article.lines.some((line) => line.departmentName.toLowerCase().includes(q)),
  )
})

const jsDeptIds = computed(() => {
  const ids = new Set<string>()
  for (const article of jsArticles.value) {
    for (const line of article.lines) ids.add(line.departmentId)
  }
  return [...ids]
})

const jsMissingCount = computed(() => {
  const missing = new Set<string>()
  for (const article of jsArticles.value) {
    for (const line of article.lines) {
      if (line.status === 'missing') missing.add(line.departmentId)
    }
  }
  return missing.size
})

const jsDeptCount = computed(() => jsDeptIds.value.length)

const filteredLoans = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return loans.value
  return loans.value.filter((row) =>
    row.name.toLowerCase().includes(q) || row.departmentName.toLowerCase().includes(q),
  )
})

const loanGroups = computed(() => {
  const buckets = new Map<string, { id: string; label: string; rows: GaGuestLoan[] }>()
  for (const row of filteredLoans.value) {
    const bucket = buckets.get(row.departmentId) ?? {
      id: row.departmentId,
      label: row.departmentName,
      rows: [],
    }
    bucket.rows.push(row)
    buckets.set(row.departmentId, bucket)
  }
  return [...buckets.values()].map((group) => ({
    ...group,
    offeredCount: group.rows.filter((row) => row.status === 'offered').length,
  }))
})

const guestPicks = computed(() =>
  acceptedGuestLoanResources(tr).map((resource) => resourceToPickTemplate(resource, tr)),
)

function statusClass(status: GaGuestLoan['status']): string {
  if (status === 'accepted') return 'is-ok'
  if (status === 'declined') return 'is-no'
  return 'is-wait'
}

function acceptLoan(id: string) {
  setGuestLoanStatus(id, 'accepted')
  toast.success(t('grossanlass.materials.gaeste.acceptedToast'))
}

function bookLoan(id: string) {
  const pick = guestPicks.value.find((item) => item.objectId === id)
  bookDraft.value = pick ? { ...pick, fromWish: false } : null
  bookOpen.value = true
}

function onBooked() {
  toast.success(t('grossanlass.materials.gaeste.bookedToast'))
}

openJs.value = createGuestJsArticles(tr).map((article: GaGuestJsArticle) => article.id)
openLoan.value = [...new Set(listGuestLoans(tr).map((row) => row.departmentId))]
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
