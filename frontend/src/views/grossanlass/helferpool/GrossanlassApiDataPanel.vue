<template>
  <div class="api" data-testid="ga-api-data">
    <p class="api__intro">{{ t('grossanlass.helferpool.api.intro') }}</p>
    <p v-if="loadError" class="api__error" role="alert">{{ loadError }}</p>

    <div class="api__bar">
      <EButton variant="primary" data-testid="ga-api-add" @click="openForm(null)"><v-icon icon="mdi-api" start size="18" />{{ t('grossanlass.helferpool.api.add') }}</EButton>
    </div>
    <EEmptyState v-if="!loading && !sources.length" variant="generic" icon="mdi-api" :title="t('grossanlass.helferpool.api.emptyTitle')" :description="t('grossanlass.helferpool.api.emptyText')" />

    <article v-for="source in sources" :key="source.id" class="src" :class="{ 'src--open': selected?.id === source.id }" :data-testid="`ga-api-source-${source.id}`">
      <header class="src__head">
        <div>
          <strong>{{ source.name }}</strong>
          <small v-if="source.has_url">{{ source.base_url }}{{ source.endpoint_path ? `/${source.endpoint_path.replace(/^\//, '')}` : '' }}</small>
          <small v-else>{{ t('grossanlass.helferpool.api.importOnly') }}</small>
        </div>
        <v-chip size="small" variant="tonal" :color="statusColor(source)">{{ t(`grossanlass.helferpool.api.status.${source.connection_status}`) }}</v-chip>
      </header>
      <p v-if="source.last_error" class="src__error">{{ source.last_error }}</p>
      <dl class="src__meta">
        <div><dt>{{ t('grossanlass.helferpool.api.lastSync') }}</dt><dd>{{ stamp(source.last_success_sync_at) }}</dd></div>
        <div><dt>{{ t('grossanlass.helferpool.api.lastPayload') }}</dt><dd>{{ stamp(source.latest_payload?.last_seen_at ?? null) }}</dd></div>
        <div><dt>{{ t('grossanlass.helferpool.api.records') }}</dt><dd>{{ source.record_counts.active ?? 0 }} / {{ source.record_counts.cancelled ?? 0 }} / {{ source.record_counts.removed ?? 0 }}</dd></div>
      </dl>
      <div class="src__actions">
        <EButton variant="secondary" :disabled="!source.has_url || busy" data-testid="ga-api-test" @click="act(source, 'test')">{{ t('grossanlass.helferpool.api.test') }}</EButton>
        <EButton variant="secondary" :disabled="!source.has_url || busy" data-testid="ga-api-fetch" @click="act(source, 'fetch')">{{ t('grossanlass.helferpool.api.fetch') }}</EButton>
        <EButton variant="primary" :disabled="busy || (!source.has_url && !source.latest_payload)" data-testid="ga-api-sync" @click="act(source, 'sync')">{{ t('grossanlass.helferpool.api.sync') }}</EButton>
        <EButton variant="text" @click="select(source)">{{ selected?.id === source.id ? t('grossanlass.helferpool.api.close') : t('grossanlass.helferpool.api.open') }}</EButton>
        <EButton variant="text" @click="openForm(source)"><v-icon icon="mdi-pencil-outline" size="18" /><span class="sr-only">{{ t('common.edit') }}</span></EButton>
      </div>

      <section v-if="selected?.id === source.id" class="detail">
        <v-tabs v-model="section" density="compact" color="primary">
          <v-tab value="json">{{ t('grossanlass.helferpool.api.section.json') }}</v-tab>
          <v-tab value="mapping" data-testid="ga-api-tab-mapping">{{ t('grossanlass.helferpool.api.section.mapping') }}</v-tab>
          <v-tab value="preview">{{ t('grossanlass.helferpool.api.section.preview') }}</v-tab>
          <v-tab value="history">{{ t('grossanlass.helferpool.api.section.history') }}</v-tab>
        </v-tabs>

        <!-- JSON-Antwort -->
        <div v-if="section === 'json'" class="pane">
          <template v-if="payload">
            <p class="muted">{{ t('grossanlass.helferpool.api.payloadMeta', { origin: t(`grossanlass.helferpool.api.origin.${payload.origin}`), when: stamp(payload.fetched_at), kb: Math.max(1, Math.round(payload.byte_size / 1024)), seen: payload.seen_count }) }}</p>
            <div class="pane__toggle">
              <EButton :variant="jsonView === 'tree' ? 'primary' : 'secondary'" @click="jsonView = 'tree'">{{ t('grossanlass.helferpool.api.viewTree') }}</EButton>
              <EButton :variant="jsonView === 'raw' ? 'primary' : 'secondary'" @click="jsonView = 'raw'">{{ t('grossanlass.helferpool.api.viewRaw') }}</EButton>
            </div>
            <p v-if="!parsed.ok" class="api__error">{{ t('grossanlass.helferpool.api.invalidJson') }}</p>
            <pre v-else-if="jsonView === 'raw'" class="raw" data-testid="ga-api-raw">{{ rawText }}</pre>
            <div v-else class="tree" data-testid="ga-api-tree">
              <p class="muted">{{ recordLists.length ? t('grossanlass.helferpool.api.listsFound') : t('grossanlass.helferpool.api.noLists') }}</p>
              <ul class="lists">
                <li v-for="list in recordLists" :key="list.path">
                  <button type="button" :class="{ 'is-active': list.path === (draft.records_path ?? '') }" @click="draft.records_path = list.path">
                    <code>{{ list.path || t('grossanlass.helferpool.api.root') }}</code> · {{ t('grossanlass.helferpool.api.recordCount', { n: list.count }) }}
                  </button>
                </li>
              </ul>
              <table class="leaves">
                <thead><tr><th>{{ t('grossanlass.helferpool.api.jsonField') }}</th><th>{{ t('grossanlass.helferpool.api.sample') }}</th><th>{{ t('grossanlass.helferpool.api.mappedTo') }}</th></tr></thead>
                <tbody>
                  <tr v-for="leaf in leaves" :key="leaf.path">
                    <td><code>{{ leaf.path }}</code></td>
                    <td>{{ leaf.sample }}</td>
                    <td>{{ mappedTargets(leaf.path) || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          <p v-else class="muted">{{ t('grossanlass.helferpool.api.noPayload') }}</p>

          <div class="import">
            <ETextarea v-model="importText" :label="t('grossanlass.helferpool.api.importLabel')" :rows="5" data-testid="ga-api-import-text" />
            <EButton variant="secondary" :disabled="busy || !importText.trim()" data-testid="ga-api-import" @click="importJson">{{ t('grossanlass.helferpool.api.import') }}</EButton>
          </div>
        </div>

        <!-- Zuordnung -->
        <div v-else-if="section === 'mapping'" class="pane">
          <p class="muted">{{ t('grossanlass.helferpool.api.mappingHint') }}</p>
          <ETextField v-model="draft.records_path" :label="t('grossanlass.helferpool.api.recordsPath')" :hint="t('grossanlass.helferpool.api.recordsPathHint')" persistent-hint />
          <table class="map" data-testid="ga-api-mapping">
            <thead><tr><th>{{ t('grossanlass.helferpool.api.jsonField') }}</th><th>{{ t('grossanlass.helferpool.api.target') }}</th></tr></thead>
            <tbody>
              <tr v-for="target in TARGETS" :key="target.key" :class="{ 'is-missing': target.required && !draft.fields?.[target.key] }">
                <td>
                  <ESelect
                    :model-value="draft.fields?.[target.key] ?? null"
                    :items="pathItems(target.key)"
                    :placeholder="t('grossanlass.helferpool.api.ignore')"
                    clearable
                    hide-details
                    :data-testid="`ga-api-map-${target.key}`"
                    @update:model-value="(v: string | null) => setField(target.key, v)"
                  />
                </td>
                <td><strong>{{ t(`grossanlass.helferpool.api.target_${target.key}`) }}</strong><span v-if="target.required" class="req"> *</span></td>
              </tr>
            </tbody>
          </table>
          <ETextField v-model="cancelledText" :label="t('grossanlass.helferpool.api.cancelledValues')" :hint="t('grossanlass.helferpool.api.cancelledHint')" persistent-hint />
          <div class="map__extra">
            <ETextField v-model="draft.total_path" :label="t('grossanlass.helferpool.api.totalPath')" />
            <ETextField v-model="draft.next_path" :label="t('grossanlass.helferpool.api.nextPath')" />
          </div>
          <ESwitch v-model="snapshotComplete" :label="t('grossanlass.helferpool.api.snapshotComplete')" />
          <p class="muted">{{ t('grossanlass.helferpool.api.snapshotHint') }}</p>
          <ul v-if="missingRequired.length" class="problems" data-testid="ga-api-missing">
            <li v-for="m in missingRequired" :key="m">{{ t('grossanlass.helferpool.api.missingRequired', { field: m }) }}</li>
          </ul>
          <div class="pane__toggle">
            <EButton variant="primary" :disabled="busy" data-testid="ga-api-save-mapping" @click="saveMapping(false)">{{ t('grossanlass.helferpool.api.saveMapping') }}</EButton>
            <EButton variant="secondary" :disabled="busy || !payload" data-testid="ga-api-preview" @click="saveMapping(true)">{{ t('grossanlass.helferpool.api.saveAndPreview') }}</EButton>
          </div>
        </div>

        <!-- Vorschau -->
        <div v-else-if="section === 'preview'" class="pane" data-testid="ga-api-preview-pane">
          <p v-if="!result" class="muted">{{ t('grossanlass.helferpool.api.noPreview') }}</p>
          <template v-else>
            <p class="banner" :class="result.dry_run ? 'banner--info' : 'banner--ok'">{{ result.dry_run ? t('grossanlass.helferpool.api.previewNote') : t('grossanlass.helferpool.api.syncedNote') }}</p>
            <div class="counts">
              <span v-for="key in COUNT_KEYS" :key="key" class="count" :class="{ 'count--bad': key === 'conflicts' && result.counts.conflicts > 0 }"><strong>{{ result.counts[key] }}</strong>{{ t(`grossanlass.helferpool.api.count.${key}`) }}</span>
              <span v-if="result.resolved" class="count"><strong>{{ result.resolved }}</strong>{{ t('grossanlass.helferpool.api.count.resolved') }}</span>
            </div>
            <ul v-if="result.errors.length" class="problems" data-testid="ga-api-issues">
              <li v-for="(issue, i) in result.errors" :key="i" :class="`is-${issue.level}`">{{ issueText(issue) }}</li>
            </ul>
            <div v-if="result.conflicts.length" class="conf">
              <strong>{{ t('grossanlass.helferpool.api.conflictsTitle') }}</strong>
              <ul>
                <li v-for="c in result.conflicts" :key="c.assignment_id">{{ t(`grossanlass.helferpool.api.conflict.${c.detail}`, { name: c.helper, when: windowLabel(c.starts_at, c.ends_at, locale) }) }}</li>
              </ul>
            </div>
            <table v-if="result.items.length" class="leaves">
              <thead><tr><th>{{ t('grossanlass.helferpool.api.target_booking_id') }}</th><th>{{ t('grossanlass.helferpool.api.action') }}</th><th>{{ t('grossanlass.helferpool.api.target_helper_name') }}</th><th>{{ t('grossanlass.helferpool.api.target_mission') }}</th><th>{{ t('grossanlass.helferpool.api.shift') }}</th></tr></thead>
              <tbody>
                <tr v-for="item in result.items" :key="item.key + item.action">
                  <td><code>{{ item.key }}</code></td>
                  <td>{{ t(`grossanlass.helferpool.api.actions.${item.action}`) }}
                    <small v-for="c in item.changes" :key="c.field" class="chg">{{ c.field }}: {{ String(c.old ?? '—') }} → {{ String(c.new ?? '—') }}</small>
                  </td>
                  <td>{{ item.helper }}</td>
                  <td>{{ item.mission ?? '—' }}</td>
                  <td>{{ item.shift_start && item.shift_end ? windowLabel(item.shift_start, item.shift_end, locale) : '—' }}</td>
                </tr>
              </tbody>
            </table>
            <div v-if="result.dry_run && result.outcome !== 'failed'" class="pane__toggle">
              <EButton variant="primary" :disabled="busy" data-testid="ga-api-apply" @click="applyPreview">{{ t('grossanlass.helferpool.api.apply') }}</EButton>
            </div>
          </template>
        </div>

        <!-- Verlauf -->
        <div v-else class="pane" data-testid="ga-api-history">
          <p v-if="!runs.length" class="muted">{{ t('grossanlass.helferpool.api.noRuns') }}</p>
          <ul class="runs">
            <li v-for="run in runs" :key="run.id">
              <div class="runs__head">
                <strong>{{ stamp(run.started_at) }}</strong>
                <v-chip size="x-small" variant="tonal" :color="run.outcome === 'ok' ? 'success' : run.outcome === 'partial' ? 'warning' : 'error'">{{ t(`grossanlass.helferpool.api.outcome.${run.outcome}`) }}</v-chip>
                <span class="muted">{{ source.name }}</span>
              </div>
              <span class="muted">{{ COUNT_KEYS.map((k) => `${t(`grossanlass.helferpool.api.count.${k}`)}: ${run.counts[k]}`).join(' · ') }}</span>
              <small v-for="(issue, i) in run.errors.slice(0, 5)" :key="i" class="chg">{{ issueText(issue) }}</small>
            </li>
          </ul>
        </div>
      </section>
    </article>

    <EDialog v-model="formOpen" :max-width="560" :title="editing ? t('grossanlass.helferpool.api.editTitle') : t('grossanlass.helferpool.api.addTitle')">
      <form class="form" @submit.prevent="saveSource">
        <ETextField v-model="form.name" :label="t('grossanlass.helferpool.api.name')" autofocus data-testid="ga-api-form-name" />
        <ETextField v-model="form.base_url" :label="t('grossanlass.helferpool.api.baseUrl')" placeholder="https://" :hint="t('grossanlass.helferpool.api.baseUrlHint')" persistent-hint data-testid="ga-api-form-url" />
        <ETextField v-model="form.endpoint_path" :label="t('grossanlass.helferpool.api.endpoint')" :hint="t('grossanlass.helferpool.api.endpointHint')" persistent-hint />
        <ESelect v-model="form.auth_type" :items="authItems" :label="t('grossanlass.helferpool.api.auth')" hide-details />
        <ETextField v-if="form.auth_type === 'header'" v-model="form.auth_header" :label="t('grossanlass.helferpool.api.authHeader')" placeholder="X-API-Key" />
        <ETextField v-if="form.auth_type === 'basic'" v-model="form.auth_user" :label="t('grossanlass.helferpool.api.authUser')" autocomplete="off" />
        <template v-if="form.auth_type !== 'none'">
          <ETextField
            v-model="form.secret"
            type="password"
            autocomplete="new-password"
            :label="form.auth_type === 'basic' ? t('grossanlass.helferpool.api.password') : t('grossanlass.helferpool.api.secret')"
            :placeholder="editing?.has_secret ? t('grossanlass.helferpool.api.secretKeep') : ''"
            :hint="t('grossanlass.helferpool.api.secretHint')"
            persistent-hint
            data-testid="ga-api-form-secret"
          />
          <ECheckbox v-if="editing?.has_secret" v-model="form.clear_secret" :label="t('grossanlass.helferpool.api.secretClear')" />
        </template>
        <p v-if="formError" class="api__error" role="alert">{{ formError }}</p>
      </form>
      <template #actions>
        <EButton variant="text" @click="formOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="busy || !form.name.trim()" data-testid="ga-api-form-save" @click="saveSource">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createGaExtSource,
  fetchGaExtPayload,
  getGaExtPayload,
  importGaExtJson,
  listGaExtRuns,
  listGaExtSources,
  previewGaExtSync,
  saveGaExtMapping,
  syncGaExtSource,
  testGaExtSource,
  updateGaExtSource,
  type GaExtAuthType,
  type GaExtIssue,
  type GaExtMapping,
  type GaExtPayload,
  type GaExtRun,
  type GaExtSource,
  type GaExtSyncResult,
  type GaExtTarget,
} from '@/api/grossanlassExternalSources'
import { EButton, ECheckbox, ESelect, ESwitch, ETextarea, ETextField } from '@/components/form/base'
import EDialog from '@/components/form/base/EDialog.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import { findRecordLists, leafPaths, parseJson, valueAt } from './gaExternalJson'
import { windowLabel } from './gaHelferUi'

const props = defineProps<{ departmentId: string }>()
const emit = defineEmits<{ changed: [] }>()

const { t, locale } = useI18n()
const toast = useToast()

const TARGETS: { key: GaExtTarget; required: boolean }[] = [
  { key: 'booking_id', required: true },
  { key: 'helper_key', required: false },
  { key: 'helper_name', required: false },
  { key: 'helper_first_name', required: false },
  { key: 'helper_last_name', required: false },
  { key: 'helper_email', required: false },
  { key: 'helper_phone', required: false },
  { key: 'mission', required: false },
  { key: 'shift_start', required: true },
  { key: 'shift_end', required: true },
  { key: 'booking_status', required: false },
]
const COUNT_KEYS = ['created', 'updated', 'unchanged', 'removed', 'conflicts'] as const

const sources = ref<GaExtSource[]>([])
const loading = ref(true)
const busy = ref(false)
const loadError = ref('')
const selected = ref<GaExtSource | null>(null)
const section = ref<'json' | 'mapping' | 'preview' | 'history'>('json')
const payload = ref<GaExtPayload | null>(null)
const runs = ref<GaExtRun[]>([])
const result = ref<GaExtSyncResult | null>(null)
const jsonView = ref<'tree' | 'raw'>('tree')
const importText = ref('')
const draft = reactive<GaExtMapping>({ records_path: '', fields: {}, total_path: '', next_path: '' })
const cancelledText = ref('')
const snapshotComplete = ref(false)

const formOpen = ref(false)
const editing = ref<GaExtSource | null>(null)
const formError = ref('')
const form = reactive({ name: '', base_url: '', endpoint_path: '', auth_type: 'none' as GaExtAuthType, auth_header: '', auth_user: '', secret: '', clear_secret: false })
const authItems = computed(() => (['none', 'bearer', 'header', 'basic'] as const).map((v) => ({ value: v, title: t(`grossanlass.helferpool.api.authType.${v}`) })))

const parsed = computed(() => (payload.value ? parseJson(payload.value.body) : { ok: false as const }))
const rawText = computed(() => {
  if (!parsed.value.ok) return payload.value?.body ?? ''
  const text = JSON.stringify(parsed.value.value, null, 2)
  return text.length > 200_000 ? `${text.slice(0, 200_000)}\n…` : text
})
const recordLists = computed(() => (parsed.value.ok ? findRecordLists(parsed.value.value) : []))
const records = computed<unknown[]>(() => {
  if (!parsed.value.ok) return []
  const node = valueAt(parsed.value.value, draft.records_path ?? '')
  return Array.isArray(node) ? node : []
})
const leaves = computed(() => leafPaths(records.value))
const missingRequired = computed(() => {
  const f = draft.fields ?? {}
  const out: string[] = []
  for (const target of TARGETS) if (target.required && !f[target.key]) out.push(t(`grossanlass.helferpool.api.target_${target.key}`))
  if (!f.helper_name && !f.helper_first_name && !f.helper_last_name) out.push(t('grossanlass.helferpool.api.target_helper_name'))
  if (!f.helper_key && !f.helper_email) out.push(t('grossanlass.helferpool.api.identity'))
  return out
})

function stamp(iso: string | null): string {
  return iso ? new Date(iso).toLocaleString(locale.value) : '—'
}
function statusColor(s: GaExtSource): string {
  return s.connection_status === 'ok' ? 'success' : s.connection_status === 'error' ? 'error' : 'grey'
}
function pathItems(target: GaExtTarget) {
  const current = draft.fields?.[target]
  const items = leaves.value.map((l) => ({ value: l.path, title: `${l.path}  (${l.sample})` }))
  if (current && !items.some((i) => i.value === current)) items.unshift({ value: current, title: current })
  return items
}
function mappedTargets(path: string): string {
  return TARGETS.filter((tg) => draft.fields?.[tg.key] === path).map((tg) => t(`grossanlass.helferpool.api.target_${tg.key}`)).join(', ')
}
function setField(key: GaExtTarget, value: string | null) {
  draft.fields = { ...(draft.fields ?? {}), [key]: value ?? undefined }
}
function issueText(issue: GaExtIssue): string {
  const key = `grossanlass.helferpool.api.issue.${issue.code}`
  const base = t(key, { field: issue.field ?? '', count: issue.count ?? 0 })
  const text = base === key ? (issue.message ?? issue.code) : base
  return [text, issue.key ? `(${issue.key})` : issue.index !== undefined ? `(#${issue.index + 1})` : ''].filter(Boolean).join(' ')
}

async function load() {
  loading.value = true
  try {
    sources.value = await listGaExtSources(props.departmentId)
    if (selected.value) selected.value = sources.value.find((s) => s.id === selected.value?.id) ?? null
    loadError.value = ''
  } catch (e) {
    loadError.value = apiErrorMessage(e, t('grossanlass.helferpool.api.loadFailed'))
  } finally {
    loading.value = false
  }
}

function adoptMapping(source: GaExtSource) {
  const m = source.mapping ?? {}
  draft.records_path = m.records_path ?? ''
  draft.fields = { ...(m.fields ?? {}) }
  draft.total_path = m.total_path ?? ''
  draft.next_path = m.next_path ?? ''
  cancelledText.value = (m.cancelled_values ?? []).join(', ')
  snapshotComplete.value = source.snapshot_complete
}

async function loadPayload(source: GaExtSource) {
  payload.value = null
  if (!source.latest_payload) return
  try {
    payload.value = await getGaExtPayload(props.departmentId, source.id)
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helferpool.api.loadFailed')))
  }
}

async function select(source: GaExtSource) {
  if (selected.value?.id === source.id) {
    selected.value = null
    return
  }
  selected.value = source
  section.value = 'json'
  result.value = null
  adoptMapping(source)
  await loadPayload(source)
  runs.value = await listGaExtRuns(props.departmentId, source.id).catch(() => [])
  if (!draft.records_path && recordLists.value.length === 1) draft.records_path = recordLists.value[0]!.path
}

/** Vorschläge nur für Datensatzliste, wenn noch nichts festgelegt ist. */
watch(recordLists, (lists) => {
  if (selected.value && !draft.records_path && lists.length === 1) draft.records_path = lists[0]!.path
})

async function refreshSelected(source: GaExtSource) {
  await load()
  const fresh = sources.value.find((s) => s.id === source.id)
  if (fresh && selected.value?.id === fresh.id) {
    selected.value = fresh
    await loadPayload(fresh)
    runs.value = await listGaExtRuns(props.departmentId, fresh.id).catch(() => [])
  }
}

async function act(source: GaExtSource, what: 'test' | 'fetch' | 'sync') {
  busy.value = true
  try {
    if (what === 'test') {
      const res = await testGaExtSource(props.departmentId, source.id)
      if (res.ok) toast.success(t('grossanlass.helferpool.api.testOk'))
      else toast.error(res.error ?? t('grossanlass.helferpool.api.testFailed'))
    } else if (what === 'fetch') {
      await fetchGaExtPayload(props.departmentId, source.id)
      toast.success(t('grossanlass.helferpool.api.fetched'))
      if (selected.value?.id !== source.id) await select(source)
      else section.value = 'json'
    } else {
      const res = await syncGaExtSource(props.departmentId, source.id, { fetch: source.has_url })
      if (selected.value?.id !== source.id) await select(source)
      result.value = res
      section.value = 'preview'
      if (res.outcome === 'failed') toast.error(t('grossanlass.helferpool.api.syncFailed'))
      else toast.success(t('grossanlass.helferpool.api.synced'))
      emit('changed')
    }
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helferpool.api.actionFailed')))
  } finally {
    busy.value = false
    await refreshSelected(source)
  }
}

async function importJson() {
  if (!selected.value) return
  busy.value = true
  try {
    await importGaExtJson(props.departmentId, selected.value.id, importText.value)
    importText.value = ''
    toast.success(t('grossanlass.helferpool.api.imported'))
    await refreshSelected(selected.value)
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helferpool.api.actionFailed')))
  } finally {
    busy.value = false
  }
}

async function saveMapping(thenPreview: boolean) {
  if (!selected.value) return
  const source = selected.value
  busy.value = true
  try {
    const fields = Object.fromEntries(Object.entries(draft.fields ?? {}).filter(([, v]) => v))
    await saveGaExtMapping(props.departmentId, source.id, {
      records_path: draft.records_path ?? '',
      fields,
      cancelled_values: cancelledText.value.split(',').map((v) => v.trim()).filter(Boolean),
      total_path: draft.total_path ?? '',
      next_path: draft.next_path ?? '',
    })
    if (snapshotComplete.value !== source.snapshot_complete) await updateGaExtSource(props.departmentId, source.id, { snapshot_complete: snapshotComplete.value })
    toast.success(t('grossanlass.helferpool.api.mappingSaved'))
    await refreshSelected(source)
    if (thenPreview) {
      result.value = await previewGaExtSync(props.departmentId, source.id, payload.value?.id)
      section.value = 'preview'
    }
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helferpool.api.actionFailed')))
  } finally {
    busy.value = false
  }
}

async function applyPreview() {
  if (!selected.value || !result.value) return
  const source = selected.value
  busy.value = true
  try {
    result.value = await syncGaExtSource(props.departmentId, source.id, { fetch: false, payloadId: result.value.payload_id })
    emit('changed')
    toast.success(t('grossanlass.helferpool.api.synced'))
    await refreshSelected(source)
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helferpool.api.actionFailed')))
  } finally {
    busy.value = false
  }
}

function openForm(source: GaExtSource | null) {
  editing.value = source
  formError.value = ''
  Object.assign(form, {
    name: source?.name ?? '',
    base_url: source?.base_url ?? '',
    endpoint_path: source?.endpoint_path ?? '',
    auth_type: source?.auth_type ?? 'none',
    auth_header: source?.auth_header ?? '',
    auth_user: source?.auth_user ?? '',
    secret: '',
    clear_secret: false,
  })
  formOpen.value = true
}

async function saveSource() {
  busy.value = true
  formError.value = ''
  try {
    const data = {
      name: form.name,
      base_url: form.base_url,
      endpoint_path: form.endpoint_path,
      auth_type: form.auth_type,
      auth_header: form.auth_header,
      auth_user: form.auth_user,
      ...(form.secret ? { secret: form.secret } : {}),
      ...(form.clear_secret ? { clear_secret: true } : {}),
    }
    const saved = editing.value ? await updateGaExtSource(props.departmentId, editing.value.id, data) : await createGaExtSource(props.departmentId, data)
    form.secret = '' // Schlüssel nicht im Formular behalten
    formOpen.value = false
    toast.success(t('grossanlass.helferpool.saved'))
    await load()
    if (!editing.value) await select(sources.value.find((s) => s.id === saved.id) ?? saved)
  } catch (e) {
    formError.value = apiErrorMessage(e, t('grossanlass.helferpool.api.actionFailed'))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.api { display: flex; flex-direction: column; gap: 1rem; }
.api__intro, .muted { color: var(--color-text-secondary, #5f6b76); margin: 0; font-size: 0.9rem; }
.api__error, .src__error { color: var(--v-theme-error, #b3261e); margin: 0; font-size: 0.9rem; }
.api__bar { display: flex; justify-content: flex-end; }
.src { border: 1px solid var(--color-border, #d6dde3); border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; background: var(--color-surface, #fff); }
.src__head { display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start; }
.src__head div { display: flex; flex-direction: column; min-width: 0; }
.src__head small { color: var(--color-text-secondary, #5f6b76); overflow-wrap: anywhere; }
.src__meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.5rem 1rem; margin: 0; }
.src__meta dt { font-size: 0.75rem; color: var(--color-text-secondary, #5f6b76); }
.src__meta dd { margin: 0; font-weight: 500; }
.src__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.detail { border-top: 1px solid var(--color-border, #d6dde3); padding-top: 0.75rem; }
.pane { display: flex; flex-direction: column; gap: 0.75rem; padding-top: 0.75rem; }
.pane__toggle { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.raw { max-height: 420px; overflow: auto; background: var(--color-surface-muted, #f3f5f7); padding: 0.75rem; border-radius: 8px; font-size: 0.8rem; margin: 0; }
.tree, .import { display: flex; flex-direction: column; gap: 0.5rem; }
.lists { display: flex; flex-wrap: wrap; gap: 0.5rem; list-style: none; padding: 0; margin: 0; }
.lists button { border: 1px solid var(--color-border, #d6dde3); border-radius: 8px; padding: 0.25rem 0.6rem; background: transparent; cursor: pointer; }
.lists button.is-active { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), 0.08); }
.leaves, .map { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.leaves th, .leaves td, .map th, .map td { text-align: left; padding: 0.35rem 0.5rem; border-bottom: 1px solid var(--color-border, #e3e8ec); vertical-align: top; overflow-wrap: anywhere; }
.map tr.is-missing td strong { color: var(--v-theme-error, #b3261e); }
.map__extra { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
.req { color: var(--v-theme-error, #b3261e); }
.problems { margin: 0; padding-left: 1.1rem; font-size: 0.9rem; }
.problems .is-error, .problems li:not(.is-warning) { color: var(--v-theme-error, #b3261e); }
.problems .is-warning { color: var(--color-warning, #8a5a00); }
.banner { margin: 0; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.9rem; }
.banner--info { background: rgba(var(--v-theme-primary), 0.08); }
.banner--ok { background: rgba(46, 125, 50, 0.12); }
.counts { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.count { display: flex; flex-direction: column; font-size: 0.8rem; color: var(--color-text-secondary, #5f6b76); min-width: 80px; }
.count strong { font-size: 1.3rem; color: inherit; }
.count--bad strong { color: var(--v-theme-error, #b3261e); }
.conf ul, .runs { margin: 0.25rem 0 0; padding-left: 1.1rem; }
.runs { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; }
.runs li { display: flex; flex-direction: column; gap: 0.15rem; }
.runs__head { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.chg { display: block; color: var(--color-text-secondary, #5f6b76); }
.form { display: flex; flex-direction: column; gap: 0.75rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
