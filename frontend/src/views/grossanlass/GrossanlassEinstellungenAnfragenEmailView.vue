<template>
  <div class="ga-mail-settings">
    <p class="intro">{{ t('grossanlass.einstellungen.anfragenEmail.intro') }}</p>

    <section class="panel">
      <h2>{{ t('grossanlass.einstellungen.anfragenEmail.gmailTitle') }}</h2>
      <p v-if="statusLoading" class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.connectionLoading') }}</p>
      <p v-else-if="status?.connected" class="ok">
        {{ t('grossanlass.einstellungen.anfragenEmail.connectedAs', { email: status.email || '' }) }}
      </p>
      <p v-else class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.disconnected') }}</p>
      <p v-if="status?.redirect_uri" class="muted">
        {{ t('grossanlass.einstellungen.anfragenEmail.gmailRedirectHint', { uri: status.redirect_uri }) }}
      </p>
      <p v-if="status && !status.oauth_configured" class="warn">
        {{ t('grossanlass.einstellungen.anfragenEmail.notConfigured', { uri: status.redirect_uri }) }}
      </p>
      <p v-if="gmailQuery === 'ok'" class="ok">{{ t('grossanlass.einstellungen.anfragenEmail.connectOk') }}</p>
      <p v-else-if="gmailQuery === 'error'" class="warn">{{ t('grossanlass.einstellungen.anfragenEmail.connectError') }}</p>
      <div class="actions">
        <EButton
          v-if="!status?.connected"
          variant="primary"
          size="small"
          :disabled="!status?.oauth_configured"
          @click="connect"
        >
          {{ t('grossanlass.beschaffung.anfragen.gmailConnect') }}
        </EButton>
        <EButton v-else variant="secondary" size="small" :loading="disconnecting" @click="disconnect">
          {{ t('grossanlass.einstellungen.anfragenEmail.disconnect') }}
        </EButton>
      </div>
    </section>

    <section class="panel">
      <h2>{{ t('grossanlass.einstellungen.anfragenEmail.routingTitle') }}</h2>
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.routingHint') }}</p>
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.labelRootNotice', { root: effectiveRoot, event: departmentName || '…' }) }}</p>
      <div class="root-row">
        <code class="root-value">{{ effectiveRoot }}</code>
        <EButton v-if="!showRootEdit" variant="text" size="small" @click="revealRootEdit">
          {{ t('grossanlass.einstellungen.anfragenEmail.labelRootEdit') }}
        </EButton>
      </div>
      <ETextField
        v-if="showRootEdit"
        v-model="routing.label_root"
        :label="t('grossanlass.einstellungen.anfragenEmail.labelRoot')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.labelRootHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <ETextField
        v-model="routing.label_inquiries"
        :label="t('grossanlass.einstellungen.anfragenEmail.labelInquiries')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.labelInquiriesHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <ETextField
        v-model="routing.label_waiting"
        :label="t('grossanlass.einstellungen.anfragenEmail.labelWaiting')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.labelWaitingHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <ETextField
        v-model="routing.label_replied"
        :label="t('grossanlass.einstellungen.anfragenEmail.labelReplied')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.labelRepliedHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <ECheckbox
        v-model="routing.label_by_package"
        :label="t('grossanlass.einstellungen.anfragenEmail.labelByPackage')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.labelByPackageHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <ETextarea
        v-model="extraLabelsText"
        :label="t('grossanlass.einstellungen.anfragenEmail.extraLabels')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.extraLabelsHint')"
        persistent-hint
        hide-details="auto"
        rows="3"
        class="mb-3"
      />
      <ETextField
        v-model="routing.reference_prefix"
        :label="t('grossanlass.einstellungen.anfragenEmail.referencePrefix')"
        :hint="t('grossanlass.einstellungen.anfragenEmail.referencePrefixHint')"
        persistent-hint
        hide-details="auto"
        class="mb-3"
      />
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.referenceSample', { sample: referenceSample }) }}</p>
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.labelPreview') }}</p>
      <ul class="label-preview">
        <li v-for="name in labelPreviewNames" :key="name"><code>{{ name }}</code></li>
      </ul>
      <div v-if="status?.connected" class="label-live">
        <div class="actions">
          <EButton variant="secondary" size="small" :loading="labelsLoading" @click="loadLabels">
            {{ t('grossanlass.einstellungen.anfragenEmail.labelsRefresh') }}
          </EButton>
          <EButton variant="text" size="small" @click="openGmailLabels">
            {{ t('grossanlass.einstellungen.anfragenEmail.labelsOpenGmail') }}
          </EButton>
          <EButton variant="primary" size="small" :loading="labelsSyncing" @click="syncLabels">
            {{ t('grossanlass.einstellungen.anfragenEmail.labelsSync') }}
          </EButton>
        </div>
        <p v-if="gmailTree.length" class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.gmailTreeHint') }}</p>
        <p v-else-if="labelsChecked && !labelsError" class="muted">
          {{ t('grossanlass.einstellungen.anfragenEmail.labelsGmailEmpty') }}
        </p>
        <div v-if="gmailTree.length" class="gmail-import">
          <select v-model="importRoot">
            <option v-for="root in gmailRoots" :key="root" :value="root">{{ root }}</option>
          </select>
          <EButton variant="secondary" size="small" :loading="labelsImporting" @click="importFromGmail">
            {{ t('grossanlass.einstellungen.anfragenEmail.labelsImport') }}
          </EButton>
        </div>
        <ul v-if="gmailTree.length" class="label-preview gmail-tree">
          <li v-for="name in gmailTree" :key="name"><code>{{ name }}</code></li>
        </ul>
        <p v-if="labelsError" class="warn">{{ labelsError }}</p>
        <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.labelsMappedHint') }}</p>
        <ul v-if="mappedLabels.length" class="label-live-list">
          <li v-for="row in mappedLabels" :key="row.name">
            <code>{{ row.name }}</code>
            <span class="label-state" :class="row.exists ? 'is-ok' : 'is-missing'">
              {{ row.exists
                ? t('grossanlass.einstellungen.anfragenEmail.labelExists')
                : t('grossanlass.einstellungen.anfragenEmail.labelMissing') }}
            </span>
          </li>
        </ul>
        <template v-if="unusedGmailLabels.length">
          <p class="warn unused-hint">{{ t('grossanlass.einstellungen.anfragenEmail.labelsUnusedHint') }}</p>
          <ul class="label-live-list">
            <li v-for="name in unusedGmailLabels" :key="'unused-' + name">
              <code>{{ name }}</code>
              <span class="label-state is-unused">
                {{ t('grossanlass.einstellungen.anfragenEmail.labelUnused') }}
              </span>
            </li>
          </ul>
        </template>
      </div>
      <p v-else class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.labelsNeedGmail') }}</p>
    </section>

    <section class="panel">
      <h2>{{ t('grossanlass.einstellungen.anfragenEmail.templatesTitle') }}</h2>
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.templatesHint') }}</p>

      <div class="template-tabs">
        <button
          v-for="row in templates"
          :key="row.kind"
          type="button"
          class="template-tab"
          :class="{ active: activeKind === row.kind }"
          @click="activeKind = row.kind"
        >
          {{ kindLabel(row.kind) }}
        </button>
        <EButton
          v-if="unusedKinds.length"
          variant="secondary"
          size="small"
          @click="showAddPicker = true"
        >
          <v-icon icon="mdi-plus" start size="18" />
          {{ t('grossanlass.einstellungen.anfragenEmail.addTemplate') }}
        </EButton>
      </div>

      <ETextField
        v-if="activeTemplate"
        v-model="activeTemplate.subject"
        :label="t('grossanlass.einstellungen.anfragenEmail.subject')"
        hide-details
        class="mb-3"
      />
      <div v-if="activeTemplate" class="editor-block">
        <p class="editor-label">{{ t('grossanlass.einstellungen.anfragenEmail.body') }}</p>
        <TiptapEditor
          :key="activeKind"
          ref="editorRef"
          v-model="activeTemplate.body"
          :placeholder="t('grossanlass.einstellungen.anfragenEmail.bodyPlaceholder')"
          :insert-tokens="insertTokens"
          allow-custom-tokens
          @add-custom-token="openCustomTokenDialog"
        />
        <ul v-if="customPlaceholders.length" class="custom-tokens">
          <li v-for="row in customPlaceholders" :key="row.key">
            <code>{{ tokenMarkup(row.key) }}</code>
            <span v-if="row.sample" class="custom-tokens__sample">{{ row.sample }}</span>
            <button type="button" class="custom-tokens__remove" @click="removeCustomToken(row.key)">
              {{ t('common.delete') }}
            </button>
          </li>
        </ul>
      </div>
      <div class="actions">
        <EButton
          v-if="activeKind !== 'anfrage'"
          variant="secondary"
          size="small"
          @click="removeActiveTemplate"
        >
          {{ t('grossanlass.einstellungen.anfragenEmail.removeTemplate') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="loadPreview">
          {{ t('grossanlass.einstellungen.anfragenEmail.previewAction') }}
        </EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="save(false)">
          {{ t('common.save') }}
        </EButton>
      </div>
      <div v-if="mailPreview" class="preview">
        <p class="mail-subject">{{ mailPreview.subject }}</p>
        <div class="mail-body" v-html="previewHtml" />
      </div>
    </section>

    <EDialog
      v-model="showAddPicker"
      :max-width="520"
      :title="t('grossanlass.einstellungen.anfragenEmail.addTemplateTitle')"
    >
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.addTemplateIntro') }}</p>
      <div class="add-grid">
        <button
          v-for="kind in unusedKinds"
          :key="kind"
          type="button"
          class="add-card"
          @click="addTemplate(kind)"
        >
          <strong>{{ kindLabel(kind) }}</strong>
          <span>{{ t(`grossanlass.einstellungen.anfragenEmail.kindHints.${kind}`) }}</span>
        </button>
      </div>
      <template #actions>
        <EButton variant="secondary" @click="showAddPicker = false">
          {{ t('common.cancel') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showCustomToken"
      :max-width="440"
      :title="t('grossanlass.einstellungen.anfragenEmail.customTokenTitle')"
    >
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.customTokenIntro') }}</p>
      <ETextField
        v-model="customTokenKey"
        :label="t('grossanlass.einstellungen.anfragenEmail.customTokenKey')"
        hide-details
        class="mb-3"
      />
      <ETextField
        v-model="customTokenSample"
        :label="t('grossanlass.einstellungen.anfragenEmail.customTokenSample')"
        hide-details
      />
      <template #actions>
        <EButton variant="secondary" @click="showCustomToken = false">
          {{ t('common.cancel') }}
        </EButton>
        <EButton variant="primary" @click="addCustomToken">
          {{ t('grossanlass.einstellungen.anfragenEmail.customTokenInsert') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ETextField, ETextarea } from '@/components/form/base'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { sanitizeMailHtml } from '@/utils/sanitizeHtml'
import {
  disconnectGrossanlassGmail,
  getGrossanlassGmailLabels,
  getGrossanlassGmailStatus,
  getGrossanlassMailTemplates,
  GROSSANLASS_GMAIL_ROUTING_DEFAULTS,
  GROSSANLASS_MAIL_BUILTIN_PLACEHOLDERS,
  GROSSANLASS_MAIL_OPTIONAL_KINDS,
  grossanlassGmailConnectUrl,
  importGrossanlassGmailLabels,
  saveGrossanlassMailTemplates,
  syncGrossanlassGmailLabels,
  type GrossanlassGmailLabelOverview,
  type GrossanlassGmailLabelRow,
  type GrossanlassGmailRouting,
  type GrossanlassGmailStatus,
  type GrossanlassMailCustomPlaceholder,
  type GrossanlassMailPreview,
  type GrossanlassMailTemplate,
  type GrossanlassMailTemplateKind,
} from '@/api/grossanlassGmail'
import { listGrossanlassProcurementCategories, type GrossanlassProcurementCategory } from '@/api/grossanlassProcurement'

defineOptions({ name: 'GrossanlassEinstellungenAnfragenEmail' })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const gmailQuery = computed(() => String(route.query.gmail || ''))

const status = ref<GrossanlassGmailStatus | null>(null)
const statusLoading = ref(true)
const templates = ref<GrossanlassMailTemplate[]>([])
const customPlaceholders = ref<GrossanlassMailCustomPlaceholder[]>([])
const activeKind = ref<string>('anfrage')
const mailPreview = ref<GrossanlassMailPreview | null>(null)
const saving = ref(false)
const disconnecting = ref(false)
const showAddPicker = ref(false)
const showRootEdit = ref(false)
const showCustomToken = ref(false)
const customTokenKey = ref('')
const customTokenSample = ref('')
const editorRef = ref<{ insertToken: (token: string) => void } | null>(null)
const routing = reactive<GrossanlassGmailRouting>({ ...GROSSANLASS_GMAIL_ROUTING_DEFAULTS })
const extraLabelsText = ref('')
const mappedLabels = ref<GrossanlassGmailLabelRow[]>([])
const gmailTree = ref<string[]>([])
const unusedGmailLabels = ref<string[]>([])
const importRoot = ref('')
const labelsLoading = ref(false)
const labelsSyncing = ref(false)
const labelsImporting = ref(false)
const labelsError = ref('')
const labelsChecked = ref(false)
const procurementCategories = ref<GrossanlassProcurementCategory[]>([])

const DEFAULT_STATUS_PATHS = [
  'Status/Wartet auf Antwort',
  'Status/Antwort erhalten',
  'Status/Zusage',
  'Status/Teilzusage',
  'Status/Absage',
  'Status/Nachfassen',
  'Status/Erledigt',
]

const departmentName = computed(() => {
  const row = authStore.departments.find((d) => d.department_id === departmentId.value)
  return row?.department?.name?.trim() || ''
})

function composedLabelRoot(eventName: string): string {
  const name = eventName.trim().replaceAll('/', '-').slice(0, 80)
  if (!name || name === 'eMatChef') return 'eMatChef'
  if (name.startsWith('eMatChef-')) return name
  return `eMatChef-${name}`.slice(0, 80)
}

const effectiveRoot = computed(() => {
  const raw = routing.label_root.trim().replaceAll('/', '-')
  if (!raw || raw === 'eMatChef') return composedLabelRoot(departmentName.value)
  return raw
})

function revealRootEdit() {
  if (!routing.label_root.trim() || routing.label_root.trim() === 'eMatChef') {
    routing.label_root = effectiveRoot.value
  }
  showRootEdit.value = true
}

const gmailRoots = computed(() => {
  const roots = new Set<string>()
  for (const name of gmailTree.value) {
    roots.add(name.includes('/') ? name.slice(0, name.indexOf('/')) : name)
  }
  return [...roots]
})

const referenceSample = computed(() => `${routing.reference_prefix}iq12beispielx`)

const labelPreviewNames = computed(() => {
  const root = effectiveRoot.value
  const names = [root]
  const inquiries = routing.label_inquiries.trim()
  if (inquiries) names.push(`${root}/${inquiries}`)
  const waiting = routing.label_waiting.trim()
  if (waiting) names.push(`${root}/${waiting}`)
  const replied = routing.label_replied.trim()
  if (replied) names.push(`${root}/${replied}`)
  for (const path of DEFAULT_STATUS_PATHS) {
    names.push(`${root}/${path}`)
  }
  names.push(`${root}/Status`)
  if (routing.label_by_package) {
    const parent = inquiries ? `${root}/${inquiries}` : root
    const cats = procurementCategories.value.map((row) => row.name.trim()).filter(Boolean)
    for (const name of cats) {
      names.push(`${parent}/${name.replaceAll('/', '-')}`)
    }
  }
  for (const line of extraLabelsText.value.split(/\r\n|\n|\r/)) {
    const path = line.trim()
    if (path) names.push(path)
  }
  return [...new Set(names)]
})

const activeTemplate = computed(() => templates.value.find((row) => row.kind === activeKind.value) ?? null)

const unusedKinds = computed(() =>
  GROSSANLASS_MAIL_OPTIONAL_KINDS.filter((kind) => !templates.value.some((row) => row.kind === kind)),
)

const previewHtml = computed(() => sanitizeMailHtml(mailPreview.value?.body || ''))

const insertTokens = computed(() => {
  const builtin = GROSSANLASS_MAIL_BUILTIN_PLACEHOLDERS.map((token) => ({
    token,
    label: `${t(`grossanlass.einstellungen.anfragenEmail.tokenLabels.${token}`)} ({{${token}}})`,
  }))
  const custom = customPlaceholders.value.map((row) => ({
    token: row.key,
    label: row.sample ? `${row.key} — ${row.sample}` : `{{${row.key}}}`,
  }))
  return [...builtin, ...custom]
})

function kindLabel(kind: string): string {
  return t(`grossanlass.einstellungen.anfragenEmail.kinds.${kind}`)
}

function tokenMarkup(key: string): string {
  return '{' + '{' + key + '}' + '}'
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

function bodyForEditor(body: string): string {
  const trimmed = body.trim()
  if (!trimmed) return '<p></p>'
  if (/<[a-z][\s\S]*>/i.test(trimmed)) return body
  return trimmed
    .split(/\n{2,}/)
    .map((part) => `<p>${escapeHtml(part).replace(/\n/g, '<br>')}</p>`)
    .join('')
}

function normalizeTokenKey(raw: string): string {
  return raw
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9_]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
}

function openCustomTokenDialog() {
  customTokenKey.value = ''
  customTokenSample.value = ''
  showCustomToken.value = true
}

function addCustomToken() {
  const key = normalizeTokenKey(customTokenKey.value)
  if (key.length < 2) {
    toast.error(t('grossanlass.einstellungen.anfragenEmail.customTokenInvalid'))
    return
  }
  if ((GROSSANLASS_MAIL_BUILTIN_PLACEHOLDERS as readonly string[]).includes(key)) {
    toast.error(t('grossanlass.einstellungen.anfragenEmail.customTokenBuiltin'))
    return
  }
  const existing = customPlaceholders.value.find((row) => row.key === key)
  if (existing) {
    existing.sample = customTokenSample.value.trim()
  } else {
    customPlaceholders.value.push({ key, sample: customTokenSample.value.trim() })
  }
  showCustomToken.value = false
  editorRef.value?.insertToken(key)
}

function removeCustomToken(key: string) {
  customPlaceholders.value = customPlaceholders.value.filter((row) => row.key !== key)
}

function addTemplate(kind: GrossanlassMailTemplateKind) {
  if (templates.value.some((row) => row.kind === kind)) {
    showAddPicker.value = false
    activeKind.value = kind
    return
  }
  templates.value.push({
    kind,
    subject: t(`grossanlass.einstellungen.anfragenEmail.defaultSubjects.${kind}`),
    body: t(`grossanlass.einstellungen.anfragenEmail.defaultBodies.${kind}`),
  })
  activeKind.value = kind
  showAddPicker.value = false
  mailPreview.value = null
}

async function removeActiveTemplate() {
  if (activeKind.value === 'anfrage') return
  const ok = await confirm.confirm({
    title: t('grossanlass.einstellungen.anfragenEmail.removeConfirmTitle'),
    message: t('grossanlass.einstellungen.anfragenEmail.removeConfirmMessage', {
      name: kindLabel(activeKind.value),
    }),
  })
  if (!ok) return
  templates.value = templates.value.filter((row) => row.kind !== activeKind.value)
  activeKind.value = 'anfrage'
  mailPreview.value = null
}

function clearGmailLiveUi() {
  mappedLabels.value = []
  gmailTree.value = []
  unusedGmailLabels.value = []
  labelsChecked.value = false
  labelsError.value = ''
}

async function refreshGmailStatus(withLabels = true) {
  if (!departmentId.value) return
  const showLoading = status.value === null
  if (showLoading) {
    statusLoading.value = true
  }
  try {
    status.value = await getGrossanlassGmailStatus(departmentId.value)
    if (status.value.connected) {
      if (withLabels) {
        await loadLabels(true)
      }
    } else {
      clearGmailLiveUi()
    }
  } finally {
    statusLoading.value = false
  }
}

async function load() {
  if (!departmentId.value) return
  try {
    await refreshGmailStatus()
    const pack = await getGrossanlassMailTemplates(departmentId.value)
    templates.value = pack.templates.map((row) => ({
      ...row,
      body: bodyForEditor(row.body),
    }))
    customPlaceholders.value = pack.custom_placeholders
    Object.assign(routing, pack.gmail_routing)
    extraLabelsText.value = pack.gmail_routing.extra_labels.join('\n')
    try {
      procurementCategories.value = await listGrossanlassProcurementCategories(departmentId.value)
    } catch {
      procurementCategories.value = []
    }
    if (!templates.value.some((row) => row.kind === activeKind.value)) {
      activeKind.value = templates.value[0]?.kind || 'anfrage'
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError'))
  }
}

function connect() {
  if (!departmentId.value) return
  window.location.assign(grossanlassGmailConnectUrl(departmentId.value))
}

async function disconnect() {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.einstellungen.anfragenEmail.disconnectConfirmTitle'),
    message: t('grossanlass.einstellungen.anfragenEmail.disconnectConfirmMessage'),
  })
  if (!ok) return
  disconnecting.value = true
  try {
    status.value = await disconnectGrossanlassGmail(departmentId.value)
    clearGmailLiveUi()
    toast.success(t('grossanlass.einstellungen.anfragenEmail.disconnectedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    disconnecting.value = false
  }
}

async function save(quiet = false) {
  if (!departmentId.value) return
  saving.value = true
  try {
    const pack = await saveGrossanlassMailTemplates(
      departmentId.value,
      templates.value,
      customPlaceholders.value,
      {
        ...routing,
        label_root: effectiveRoot.value,
        extra_labels: extraLabelsText.value
          .split(/\r\n|\n|\r/)
          .map((line) => line.trim())
          .filter(Boolean),
      },
    )
    templates.value = pack.templates.map((row) => ({
      ...row,
      body: bodyForEditor(row.body),
    }))
    customPlaceholders.value = pack.custom_placeholders
    Object.assign(routing, pack.gmail_routing)
    extraLabelsText.value = pack.gmail_routing.extra_labels.join('\n')
    if (!quiet) {
      toast.success(t('grossanlass.einstellungen.anfragenEmail.saved'))
      if (status.value?.connected) {
        await loadLabels(true)
      }
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
    if (quiet) throw e
  } finally {
    saving.value = false
  }
}

function applyLabelOverview(pack: GrossanlassGmailLabelOverview, applyRouting = false) {
  mappedLabels.value = pack.labels
  gmailTree.value = pack.gmail_labels
  unusedGmailLabels.value = pack.unused_gmail_labels ?? []
  if (applyRouting) {
    Object.assign(routing, pack.gmail_routing)
    extraLabelsText.value = pack.gmail_routing.extra_labels.join('\n')
  }
  if (!importRoot.value || !gmailTree.value.some((name) => name === importRoot.value || name.startsWith(`${importRoot.value}/`))) {
    importRoot.value = pack.suggested_root || gmailRoots.value[0] || ''
  }
}

async function loadLabels(quiet = false) {
  if (!departmentId.value || !status.value?.connected) return
  labelsLoading.value = true
  labelsError.value = ''
  try {
    applyLabelOverview(await getGrossanlassGmailLabels(departmentId.value))
    labelsChecked.value = true
    if (!quiet) {
      toast.success(t('grossanlass.einstellungen.anfragenEmail.labelsRefreshToast', {
        count: gmailTree.value.length,
      }))
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    labelsError.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
    mappedLabels.value = []
    gmailTree.value = []
    unusedGmailLabels.value = []
    if (!quiet) {
      toast.error(labelsError.value)
    }
  } finally {
    labelsLoading.value = false
  }
}

function openGmailLabels() {
  window.open('https://mail.google.com/mail/u/0/#settings/labels', '_blank', 'noopener')
}

async function syncLabels() {
  if (!departmentId.value) return
  labelsSyncing.value = true
  labelsError.value = ''
  try {
    await save(true)
    const result = await syncGrossanlassGmailLabels(departmentId.value)
    applyLabelOverview(result)
    toast.success(t('grossanlass.einstellungen.anfragenEmail.labelsSyncToast', {
      created: result.created,
      renamed: result.renamed,
    }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    labelsError.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError')
  } finally {
    labelsSyncing.value = false
  }
}

async function importFromGmail() {
  if (!departmentId.value) return
  labelsImporting.value = true
  labelsError.value = ''
  try {
    const pack = await importGrossanlassGmailLabels(departmentId.value, importRoot.value)
    applyLabelOverview(pack, true)
    try {
      procurementCategories.value = await listGrossanlassProcurementCategories(departmentId.value)
    } catch {
      procurementCategories.value = []
    }
    toast.success(t('grossanlass.einstellungen.anfragenEmail.labelsImportToast', {
      count: pack.category_names?.length ?? 0,
      created: pack.categories_imported ?? 0,
    }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    labelsError.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError')
  } finally {
    labelsImporting.value = false
  }
}

async function loadPreview() {
  const tpl = activeTemplate.value
  if (!tpl) return
  const areas = procurementCategories.value.map((row) => row.name.trim()).filter(Boolean).join(', ') || 'Bereiche folgen'
  const vars: Record<string, string> = {
    ANREDE: 'Guten Tag',
    FIRMA: 'Muster AG',
    ANLASS: 'Anlass',
    ORT: '',
    ZEITRAUMTEXT: 'Aufbau, Anlasswoche und Rückgabe gemäss Absprache',
    MATERIALLISTE: areas,
    BEREICHE: areas,
    ABSENDER: 'OK Material & Logistik',
    REFERENZ: `${routing.reference_prefix}____________`,
    EMAIL: 'demo@firma.example',
  }
  for (const row of customPlaceholders.value) {
    if (!vars[row.key]) {
      vars[row.key] = row.sample || '{{' + row.key + '}}'
    }
  }
  const apply = (template: string) => {
    let out = template
    for (const [key, value] of Object.entries(vars)) {
      out = out.replaceAll('{{' + key + '}}', value)
    }
    return out
  }
  mailPreview.value = {
    subject: apply(tpl.subject),
    body: apply(tpl.body),
    to: 'demo@firma.example',
    placeholders: vars,
  }
}

async function consumeGmailQuery() {
  if (!gmailQuery.value) return
  await refreshGmailStatus()
  void router.replace({ path: route.path, query: {} })
}

function onPageVisible() {
  if (document.visibilityState === 'visible') {
    void refreshGmailStatus(false)
  }
}

watch(departmentId, (id) => {
  if (id) void load()
}, { immediate: true })

watch(gmailQuery, (value) => {
  if (value) void consumeGmailQuery()
}, { immediate: true })

onMounted(() => {
  document.addEventListener('visibilitychange', onPageVisible)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onPageVisible)
})
</script>

<style scoped>
.ga-mail-settings { padding: 4px 0 24px; display: grid; gap: 16px; }
.intro, .muted { margin: 0 0 8px; color: #64748b; font-size: 0.9rem; }
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.panel h2 { margin: 0 0 8px; font-size: 1rem; }
.root-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0 0 12px;
}
.root-value {
  font-size: 0.9rem;
  background: #f1f5f9;
  padding: 4px 8px;
  border-radius: 6px;
}
.ok { color: #166534; font-size: 0.9rem; }
.warn { color: #9a3412; font-size: 0.85rem; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.mb-3 { margin-bottom: 12px; }
.template-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin: 12px 0;
}
.template-tab {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}
.template-tab.active {
  border-color: #059669;
  background: #ecfdf5;
  color: #047857;
}
.editor-block { margin-bottom: 8px; }
.editor-label {
  margin: 0 0 6px;
  font-size: 0.85rem;
  font-weight: 600;
}
.add-grid {
  display: grid;
  gap: 8px;
}
.add-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  text-align: left;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}
.add-card:hover {
  border-color: #93c5fd;
  background: #f8fafc;
}
.add-card span {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.4;
}
.preview {
  margin-top: 16px;
  background: #f8fafc;
  border-radius: 8px;
  padding: 12px;
}
.mail-subject { font-weight: 700; margin: 0 0 8px; }
.mail-body :deep(p) { margin: 0 0 8px; }
.mail-body :deep(p:last-child) { margin-bottom: 0; }
.custom-tokens {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.custom-tokens li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
}
.custom-tokens code {
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}
.custom-tokens__sample { color: #64748b; }
.custom-tokens__remove {
  border: 0;
  background: none;
  color: #b45309;
  cursor: pointer;
  font-size: 0.8rem;
  padding: 0;
}
.label-preview {
  margin: 8px 0 0;
  padding-left: 1.2rem;
  color: #334155;
  font-size: 0.85rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.gmail-import {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin: 8px 0;
}
.gmail-import select {
  min-width: 12rem;
  flex: 1 1 12rem;
  font: inherit;
  font-size: 0.85rem;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}
.gmail-tree {
  max-height: 12rem;
  overflow: auto;
  margin-top: 8px;
}
.label-live-list {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.label-live-list li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  font-size: 0.82rem;
}
.label-live-list code {
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}
.label-state { font-size: 0.75rem; font-weight: 600; }
.label-state.is-ok { color: #15803d; }
.label-state.is-missing { color: #b45309; }
.label-state.is-unused { color: #9a3412; }
.unused-hint { margin: 14px 0 0; }
</style>
