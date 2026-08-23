<template>
  <div class="ga-mail-settings">
    <p class="intro">{{ t('grossanlass.einstellungen.anfragenEmail.intro') }}</p>

    <section class="panel">
      <h2>{{ t('grossanlass.einstellungen.anfragenEmail.gmailTitle') }}</h2>
      <p v-if="status?.connected" class="ok">
        {{ t('grossanlass.einstellungen.anfragenEmail.connectedAs', { email: status.email || '' }) }}
      </p>
      <p v-else class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.disconnected') }}</p>
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
      <h2>{{ t('grossanlass.einstellungen.anfragenEmail.templatesTitle') }}</h2>
      <p class="muted">{{ t('grossanlass.einstellungen.anfragenEmail.templatesHint') }}</p>
      <ESelect
        v-model="activeKind"
        :items="kindItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.einstellungen.anfragenEmail.templateKind')"
        hide-details
        class="kind-select"
      />
      <ETextField
        v-if="activeTemplate"
        v-model="activeTemplate.subject"
        :label="t('grossanlass.einstellungen.anfragenEmail.subject')"
        hide-details
        class="mb-3"
      />
      <ETextarea
        v-if="activeTemplate"
        v-model="activeTemplate.body"
        :label="t('grossanlass.einstellungen.anfragenEmail.body')"
        rows="12"
        hide-details
      />
      <div class="actions">
        <EButton variant="secondary" size="small" :loading="previewing" @click="loadPreview">
          {{ t('grossanlass.einstellungen.anfragenEmail.previewAction') }}
        </EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="save">
          {{ t('common.save') }}
        </EButton>
      </div>
      <div v-if="mailPreview" class="preview">
        <p class="mail-subject">{{ mailPreview.subject }}</p>
        <pre>{{ mailPreview.body }}</pre>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect, ETextarea, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  disconnectGrossanlassGmail,
  getGrossanlassGmailStatus,
  getGrossanlassMailTemplates,
  grossanlassGmailConnectUrl,
  previewGrossanlassMail,
  saveGrossanlassMailTemplates,
  type GrossanlassGmailStatus,
  type GrossanlassMailPreview,
  type GrossanlassMailTemplate,
} from '@/api/grossanlassGmail'

defineOptions({ name: 'GrossanlassEinstellungenAnfragenEmail' })

const KINDS = ['anfrage', 'dank_absage', 'zusage_ok', 'nicht_genommen'] as const

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const gmailQuery = computed(() => String(route.query.gmail || ''))

const status = ref<GrossanlassGmailStatus | null>(null)
const templates = ref<GrossanlassMailTemplate[]>([])
const activeKind = ref<(typeof KINDS)[number]>('anfrage')
const mailPreview = ref<GrossanlassMailPreview | null>(null)
const saving = ref(false)
const previewing = ref(false)
const disconnecting = ref(false)

const kindItems = computed(() =>
  KINDS.map((kind) => ({
    title: t(`grossanlass.einstellungen.anfragenEmail.kinds.${kind}`),
    value: kind,
  })),
)

const activeTemplate = computed(() => templates.value.find((row) => row.kind === activeKind.value) ?? null)

async function load() {
  if (!departmentId.value) return
  try {
    status.value = await getGrossanlassGmailStatus(departmentId.value)
    templates.value = await getGrossanlassMailTemplates(departmentId.value)
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
  disconnecting.value = true
  try {
    status.value = await disconnectGrossanlassGmail(departmentId.value)
    toast.success(t('grossanlass.einstellungen.anfragenEmail.disconnectedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    disconnecting.value = false
  }
}

async function save() {
  if (!departmentId.value) return
  saving.value = true
  try {
    templates.value = await saveGrossanlassMailTemplates(departmentId.value, templates.value)
    toast.success(t('grossanlass.einstellungen.anfragenEmail.saved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function loadPreview() {
  if (!departmentId.value) return
  previewing.value = true
  try {
    mailPreview.value = await previewGrossanlassMail(departmentId.value, { kind: activeKind.value })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    previewing.value = false
  }
}

onMounted(async () => {
  await load()
  if (gmailQuery.value) {
    void router.replace({ path: route.path, query: {} })
  }
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
.ok { color: #166534; font-size: 0.9rem; }
.warn { color: #9a3412; font-size: 0.85rem; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.kind-select { max-width: 360px; margin: 12px 0; }
.mb-3 { margin-bottom: 12px; }
.preview {
  margin-top: 16px;
  background: #f8fafc;
  border-radius: 8px;
  padding: 12px;
}
.mail-subject { font-weight: 700; margin: 0 0 8px; }
.preview pre {
  margin: 0;
  white-space: pre-wrap;
  font: inherit;
  font-size: 0.85rem;
  line-height: 1.45;
}
</style>
