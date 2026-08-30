<template>
  <div class="ga-js">
    <p class="tab-intro">{{ t('grossanlass.materials.js.intro') }}</p>

    <div class="ga-js__toolbar">
      <ESearchField
        v-model="query"
        class="ga-js__search"
        :label="t('grossanlass.materials.js.search')"
      />
      <EButton variant="primary" size="small" @click="openAnfragen">
        {{ t('grossanlass.materials.js.openAnfragen') }}
      </EButton>
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <template v-else>
      <div class="summary-row">
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.materials.js.depts') }}</span>
          <strong>{{ jsDeptCount }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.materials.js.missing') }}</span>
          <strong>{{ jsMissingCount }}</strong>
        </div>
      </div>
      <p class="section-hint">{{ t('grossanlass.materials.js.hint') }}</p>
      <v-expansion-panels v-if="filteredJs.length" v-model="openJs" multiple class="e-accordions">
        <v-expansion-panel v-for="article in filteredJs" :key="article.id" :value="article.id">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ jsArticleTitle(article) }}
                <span class="panel-head__count">{{ jsArticleTotal(article) }} {{ article.unit }}</span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p v-if="article.catalog_hint" class="catalog-hint">{{ article.catalog_hint }}</p>
            <table v-if="article.lines.length" class="data-table">
              <thead>
                <tr>
                  <th>{{ t('grossanlass.materials.js.colDept') }}</th>
                  <th>{{ t('grossanlass.materials.js.colQty') }}</th>
                  <th>{{ t('grossanlass.materials.js.colStatus') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in article.lines" :key="line.department_id">
                  <td>{{ line.department_name }}</td>
                  <td>{{ jsLineQty(line) }}</td>
                  <td>
                    <span class="chip" :class="line.status === 'submitted' ? 'is-ok' : 'is-wait'">
                      {{ t(`grossanlass.materials.js.status.${line.status}`) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-else class="muted">{{ t('grossanlass.materials.js.emptyGuestsText') }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.js.emptyTitle')"
        :description="t('grossanlass.materials.js.emptyText')"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ESearchField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  getGrossanlassGaeste,
  type GaGaestePayload,
  type GaGuestJsArticle,
  type GaGuestJsLine,
} from '@/api/grossanlassGaeste'
import { listGrossanlassProcurementCategories } from '@/api/grossanlassProcurement'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const query = ref('')
const loading = ref(false)
const payload = ref<GaGaestePayload | null>(null)
const openJs = ref<string[]>([])

const departmentId = computed(() => String(route.params.departmentId || ''))

async function load() {
  if (!departmentId.value) return
  loading.value = true
  try {
    payload.value = await getGrossanlassGaeste(departmentId.value)
    openJs.value = (payload.value.js?.articles ?? []).map((row) => row.id)
    void listGrossanlassProcurementCategories(departmentId.value).catch(() => undefined)
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

const jsDeptCount = computed(() => {
  const ids = new Set<string>()
  for (const article of jsArticles.value) {
    for (const line of article.lines) ids.add(line.department_id)
  }
  return ids.size
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

function openAnfragen() {
  if (!departmentId.value) return
  void router.push({
    path: `/${departmentId.value}/beschaffung/anfragen`,
    query: { system: 'js' },
  })
}
</script>

<style scoped>
.ga-js { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.ga-js__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  margin-bottom: 14px;
}
.ga-js__search { flex: 1 1 220px; min-width: min(100%, 180px); }
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
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 8px; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; }
.chip {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
}
.chip.is-ok { background: #dcfce7; color: #166534; }
.chip.is-wait { background: #ffedd5; color: #c2410c; }
</style>
