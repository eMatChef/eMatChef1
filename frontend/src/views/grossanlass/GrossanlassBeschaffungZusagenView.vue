<template>
  <div class="zusagen-page">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.zusagen.intro') }}</p>

    <div class="zusagen-toolbar">
      <EButton variant="primary" size="small" @click="openCreate()">
        {{ t('grossanlass.beschaffung.zusagen.createAction') }}
      </EButton>
      <ESearchField
        v-model="query"
        class="zusagen-toolbar__search"
        :label="t('grossanlass.beschaffung.zusagen.search')"
      />
      <ESelect
        v-model="groupBy"
        class="zusagen-toolbar__select"
        :label="t('grossanlass.beschaffung.zusagen.groupBy')"
        :items="groupItems"
        hide-details
      />
      <ESelect
        v-model="sortBy"
        class="zusagen-toolbar__select"
        :label="t('grossanlass.beschaffung.zusagen.sort')"
        :items="sortItems"
        hide-details
      />
      <div class="zusagen-toolbar__expand">
        <EButton variant="text" size="small" @click="expandAll">
          {{ t('grossanlass.beschaffung.zusagen.expandAll') }}
        </EButton>
        <EButton variant="text" size="small" @click="collapseAll">
          {{ t('grossanlass.beschaffung.zusagen.collapseAll') }}
        </EButton>
      </div>
    </div>

    <EEmptyState
      v-if="filteredRows.length === 0"
      variant="default"
      icon="mdi-handshake-outline"
      :title="t('grossanlass.beschaffung.zusagen.noMatchTitle')"
      :description="t('grossanlass.beschaffung.zusagen.noMatchDescription')"
    />

    <v-expansion-panels
      v-else
      v-model="openGroups"
      multiple
      class="zusagen-accordion"
    >
      <v-expansion-panel
        v-for="group in grouped"
        :key="group.id"
        :value="group.id"
      >
        <v-expansion-panel-title>
          <span class="group-title">
            <strong>{{ group.label }}</strong>
            <span class="group-count">{{ group.rows.length }}</span>
            <span v-if="group.heldCount > 0" class="zusagen-badge is-held">
              {{ t('grossanlass.beschaffung.zusagen.heldCount', { count: group.heldCount }) }}
            </span>
            <span v-if="group.wideCount > 0" class="fein-badge fein-badge--wide">
              {{ t('grossanlass.planung.feinPartner.delta.wide') }}
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <ul class="zusagen-list">
            <li
              v-for="row in group.rows"
              :key="row.id"
              class="zusagen-card"
              :class="`zusagen-card--${row.delta}`"
            >
              <div class="zusagen-card__head">
                <div>
                  <strong>{{ row.name }}</strong>
                  <span class="zusagen-badge" :class="row.released ? 'is-open' : 'is-held'">
                    {{ row.released
                      ? t('grossanlass.materials.zusage.releasedShort')
                      : t('grossanlass.materials.zusage.heldShort') }}
                  </span>
                </div>
                <span class="fein-badge" :class="{ 'fein-badge--wide': row.delta === 'wide' }">
                  {{ t(`grossanlass.planung.feinPartner.delta.${row.delta}`) }}
                </span>
              </div>
              <p>{{ t('grossanlass.planung.feinPartner.partnerWindow', {
                partner: row.source,
                from: row.partnerFrom,
                to: row.partnerTo,
              }) }}</p>
              <p>
                {{ t('grossanlass.beschaffung.zusagen.handoverReturn', {
                  handover: row.handover,
                  giveback: row.giveback,
                }) }}
              </p>
              <p v-if="row.wishLabel">{{ t('grossanlass.planung.feinPartner.wishWindow', {
                wish: row.wishLabel,
                from: row.wishFrom,
                to: row.wishTo,
              }) }}</p>
              <p v-if="row.delta !== 'none'" class="zusagen-card__hint">
                {{ t(`grossanlass.planung.feinPartner.advice.${row.delta}`) }}
              </p>
              <div class="zusagen-card__actions">
                <EButton variant="secondary" size="small" @click="toggleReleased(row)">
                  {{ t('grossanlass.beschaffung.zusagen.toggleRelease') }}
                </EButton>
              </div>
            </li>
          </ul>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <GrossanlassZusageCreatePreviewDialog
      v-model="createOpen"
      :preset="createPreset"
      @created="onCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import { feinDeltaKind, formatGaIsoLabel, type GaZusageArticle, type GaZusageOrigin } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { type GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { useToast } from '@/composables/useToast'
import {
  getGrossanlassCommitments,
  updateGrossanlassCommitment,
  type GrossanlassCommitment,
} from '@/api/grossanlassCommitments'

type GroupBy = 'source' | 'family' | 'status'
type SortBy = 'name' | 'handover' | 'return' | 'status'
type ZusageRow = {
  id: string
  name: string
  source: string
  family: 'vehicle' | 'material'
  origin: GaZusageOrigin
  released: boolean
  delta: 'wide' | 'fit' | 'none'
  handoverIso: string
  returnIso: string
  partnerFrom: string
  partnerTo: string
  handover: string
  giveback: string
  wishLabel: string
  wishFrom: string
  wishTo: string
}

const AUTO_EXPAND_MAX = 8

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const createOpen = ref(false)
const createPreset = ref<Partial<GaZusageCreateDraft> | null>(null)
const query = ref('')
const groupBy = ref<GroupBy>('source')
const sortBy = ref<SortBy>('handover')
const openGroups = ref<string[]>([])
const articles = ref<GrossanlassCommitment[]>([])
const isLoading = ref(false)

const departmentId = computed(() => String(route.params.departmentId || ''))

const groupItems = computed(() => [
  { title: t('grossanlass.beschaffung.zusagen.groupByPartner'), value: 'source' },
  { title: t('grossanlass.beschaffung.zusagen.groupByFamily'), value: 'family' },
  { title: t('grossanlass.beschaffung.zusagen.groupByStatus'), value: 'status' },
])

const sortItems = computed(() => [
  { title: t('grossanlass.beschaffung.zusagen.sortName'), value: 'name' },
  { title: t('grossanlass.beschaffung.zusagen.sortHandover'), value: 'handover' },
  { title: t('grossanlass.beschaffung.zusagen.sortReturn'), value: 'return' },
  { title: t('grossanlass.beschaffung.zusagen.sortStatus'), value: 'status' },
])

function deltaOf(article: GrossanlassCommitment): 'wide' | 'fit' | 'none' {
  if (!article.wish_from || !article.wish_to || !article.present_from || !article.present_to) return 'none'
  return feinDeltaKind({
    presentFromIso: article.present_from,
    presentToIso: article.present_to,
    feinWish: {
      label: article.wish_label || '',
      ressort: '',
      fromIso: article.wish_from,
      toIso: article.wish_to,
    },
  } as GaZusageArticle)
}

const rows = computed<ZusageRow[]>(() =>
  articles.value.map((article) => ({
    id: article.id,
    name: article.name,
    source: article.source,
    family: article.family,
    origin: article.origin,
    released: article.released,
    delta: deltaOf(article),
    handoverIso: article.handover_from || '',
    returnIso: article.return_from || '',
    partnerFrom: article.present_from ? formatGaIsoLabel(article.present_from, locale.value) : '—',
    partnerTo: article.present_to ? formatGaIsoLabel(article.present_to, locale.value) : '—',
    handover: article.handover_from ? formatGaIsoLabel(article.handover_from, locale.value) : '—',
    giveback: article.return_from ? formatGaIsoLabel(article.return_from, locale.value) : '—',
    wishLabel: article.wish_label ?? '',
    wishFrom: article.wish_from ? formatGaIsoLabel(article.wish_from, locale.value) : '',
    wishTo: article.wish_to ? formatGaIsoLabel(article.wish_to, locale.value) : '',
  })),
)

const filteredRows = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = q
    ? rows.value.filter((row) =>
        [row.name, row.source, row.wishLabel].some((value) => value.toLowerCase().includes(q)),
      )
    : rows.value
  return [...list].sort(compareRows)
})

const grouped = computed(() => {
  const buckets = new Map<string, { id: string; label: string; rows: ZusageRow[] }>()
  for (const row of filteredRows.value) {
    const { id, label } = groupMeta(row)
    const bucket = buckets.get(id) ?? { id, label, rows: [] }
    bucket.rows.push(row)
    buckets.set(id, bucket)
  }
  return [...buckets.values()]
    .map((group) => ({
      ...group,
      heldCount: group.rows.filter((row) => !row.released).length,
      wideCount: group.rows.filter((row) => row.delta === 'wide').length,
    }))
    .sort((a, b) => a.label.localeCompare(b.label, locale.value))
})

function groupMeta(row: ZusageRow): { id: string; label: string } {
  if (groupBy.value === 'family') {
    return {
      id: row.family,
      label: row.family === 'vehicle'
        ? t('grossanlass.materials.zusage.familyVehicle')
        : t('grossanlass.materials.zusage.familyMaterial'),
    }
  }
  if (groupBy.value === 'status') {
    return {
      id: row.released ? 'released' : 'held',
      label: row.released
        ? t('grossanlass.materials.zusage.releasedShort')
        : t('grossanlass.materials.zusage.heldShort'),
    }
  }
  return { id: `source:${row.source}`, label: row.source }
}

function compareRows(a: ZusageRow, b: ZusageRow): number {
  if (sortBy.value === 'handover') {
    return a.handoverIso.localeCompare(b.handoverIso) || a.name.localeCompare(b.name, locale.value)
  }
  if (sortBy.value === 'return') {
    return a.returnIso.localeCompare(b.returnIso) || a.name.localeCompare(b.name, locale.value)
  }
  if (sortBy.value === 'status') {
    return Number(a.released) - Number(b.released) || a.name.localeCompare(b.name, locale.value)
  }
  return a.name.localeCompare(b.name, locale.value)
}

function defaultOpenIds(): string[] {
  const ids = grouped.value.map((group) => group.id)
  if (query.value.trim()) return ids
  if (filteredRows.value.length <= AUTO_EXPAND_MAX) return ids
  return grouped.value
    .filter((group) => group.heldCount > 0 || group.wideCount > 0)
    .map((group) => group.id)
}

function expandAll() {
  openGroups.value = grouped.value.map((group) => group.id)
}

function collapseAll() {
  openGroups.value = []
}

function openCreate(preset?: Partial<GaZusageCreateDraft>) {
  createPreset.value = preset ?? { origin: 'loan' }
  createOpen.value = true
}

async function toggleReleased(row: ZusageRow) {
  if (!departmentId.value) return
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, row.id, { released: !row.released })
    articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
    toast.success(t('grossanlass.beschaffung.zusagen.releasedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    articles.value = await getGrossanlassCommitments(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    isLoading.value = false
    openGroups.value = defaultOpenIds()
  }
}

function onCreated() {
  void router.replace({ path: route.path, query: {} })
  void load()
}

function presetFromQuery(): Partial<GaZusageCreateDraft> | null {
  const name = String(route.query.name || '').trim()
  const source = String(route.query.partner || '').trim()
  if (!name && !source) return null
  const family = route.query.family === 'vehicle' ? 'vehicle' : 'material'
  return {
    name,
    source,
    family,
    origin: 'loan',
    fromLineId: String(route.query.line || '') || undefined,
  }
}

watch([groupBy, query], () => {
  openGroups.value = defaultOpenIds()
})

onMounted(() => {
  void load()
  const preset = presetFromQuery()
  if (preset) openCreate(preset)
})
</script>

<style scoped>
.zusagen-page { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.zusagen-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: flex-end;
  margin-bottom: 14px;
}
.zusagen-toolbar__search { flex: 1 1 200px; min-width: min(100%, 180px); }
.zusagen-toolbar__select { flex: 0 1 180px; min-width: 150px; }
.zusagen-toolbar__expand {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-left: auto;
}
.zusagen-accordion :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  overflow: hidden;
  margin-bottom: 10px;
  background: #fff;
}
.zusagen-accordion :deep(.v-expansion-panel-title) {
  min-height: 52px;
  font-size: 0.9rem;
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
.zusagen-list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: grid;
  gap: 10px;
}
.zusagen-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}
.zusagen-card p { margin: 0; color: #334155; }
.zusagen-card__head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}
.zusagen-card__head > div {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.zusagen-badge,
.fein-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
}
.zusagen-badge.is-held { background: #ffedd5; color: #c2410c; }
.zusagen-badge.is-open { background: #dcfce7; color: #166534; }
.fein-badge { background: #e2e8f0; color: #334155; }
.fein-badge--wide,
.zusagen-card--wide .fein-badge { background: #ffedd5; color: #c2410c; }
.zusagen-card--fit .fein-badge { background: #dcfce7; color: #166534; }
.zusagen-card__hint { color: #9a3412; font-weight: 600; }
.zusagen-card__actions { margin-top: 4px; }
</style>
