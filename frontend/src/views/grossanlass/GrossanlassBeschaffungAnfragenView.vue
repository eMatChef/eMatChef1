<template>
  <div class="ga-anfragen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.anfragen.intro') }}</p>

    <div class="gmail-strip">
      <div>
        <strong>{{ t('grossanlass.beschaffung.anfragen.gmailTitle') }}</strong>
        <p v-if="gmailStatus?.connected">
          {{ t('grossanlass.beschaffung.anfragen.gmailConnected', { email: gmailStatus.email || '' }) }}
        </p>
        <p v-else>{{ t('grossanlass.beschaffung.anfragen.gmailDisconnected') }}</p>
      </div>
      <div class="gmail-strip__actions">
        <EButton
          v-if="!gmailStatus?.connected"
          variant="secondary"
          size="small"
          @click="goGmailSettings"
        >
          {{ t('grossanlass.beschaffung.anfragen.gmailConnect') }}
        </EButton>
        <EButton v-else variant="secondary" size="small" @click="goGmailSettings">
          {{ t('grossanlass.beschaffung.anfragen.gmailSettings') }}
        </EButton>
        <EButton
          variant="secondary"
          size="small"
          :disabled="!gmailStatus?.connected"
          :loading="isSyncing"
          @click="syncGmail"
        >
          {{ t('grossanlass.beschaffung.anfragen.gmailSync') }}
        </EButton>
      </div>
    </div>

    <div class="ga-anfragen__toolbar">
      <div class="view-toggle" role="tablist">
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'firms' }"
          @click="view = 'firms'"
        >
          {{ t('grossanlass.beschaffung.anfragen.viewFirms') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'category' }"
          @click="view = 'category'"
        >
          {{ t('grossanlass.beschaffung.anfragen.viewCategory') }}
        </button>
      </div>
      <ESearchField
        v-model="query"
        class="ga-anfragen__search"
        :label="t('grossanlass.beschaffung.anfragen.search')"
      />
      <EButton variant="secondary" size="small" :loading="isImporting" @click="importTips">
        {{ t('grossanlass.beschaffung.anfragen.importTips') }}
      </EButton>
      <EButton variant="secondary" size="small" @click="createOpen = true">
        {{ t('grossanlass.beschaffung.anfragen.addFirm') }}
      </EButton>
      <EButton variant="primary" size="small" :disabled="!selected.length" @click="draftsOpen = true">
        {{ t('grossanlass.beschaffung.anfragen.createDrafts', { count: selected.length || 0 }) }}
      </EButton>
    </div>

    <div v-if="view === 'firms'" class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-check">
              <input
                type="checkbox"
                :checked="allVisibleSelected"
                @change="toggleAllVisible"
              >
            </th>
            <th>{{ t('grossanlass.beschaffung.anfragen.colFirm') }}</th>
            <th>{{ t('grossanlass.beschaffung.anfragen.colPackages') }}</th>
            <th>{{ t('grossanlass.beschaffung.anfragen.colStatus') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="firma in filteredFirms" :key="firma.id" :class="{ 'is-blocked': !firma.email }">
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selected.includes(firma.id)"
                :disabled="!canDraft(firma)"
                @change="toggle(firma.id)"
              >
            </td>
            <td>
              <strong>{{ firma.name }}</strong>
              <span class="meta">{{ firma.place }} · {{ firma.email || t('grossanlass.beschaffung.anfragen.missingEmail') }}</span>
              <span v-if="firma.tip_from" class="meta">{{ t('grossanlass.beschaffung.anfragen.tipFrom', { ressort: firma.tip_from }) }}</span>
            </td>
            <td>
              <span v-for="categoryId in firma.category_ids" :key="categoryId" class="pkg-chip">
                {{ anfrageCategoryLabel(categoryId, tr) }}
              </span>
            </td>
            <td>
              <span class="status-chip" :class="`status-chip--${firma.status}`">
                {{ t(`grossanlass.beschaffung.anfragen.status.${firma.status}`) }}
              </span>
            </td>
            <td>
              <EButton variant="text" size="small" @click="openPreview(firma)">
                {{ t('grossanlass.beschaffung.anfragen.preview') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="category-list">
      <section v-for="block in categoryBlocks" :key="block.id" class="category-card">
        <h3>{{ block.label }} <span>{{ block.firms.length }}</span></h3>
        <ul>
          <li v-for="firma in block.firms" :key="firma.id">
            <div>
              <strong>{{ firma.name }}</strong>
              <span class="meta">{{ firma.place }}</span>
            </div>
            <span class="status-chip" :class="`status-chip--${firma.status}`">
              {{ t(`grossanlass.beschaffung.anfragen.status.${firma.status}`) }}
            </span>
            <EButton variant="text" size="small" @click="openPreview(firma)">
              {{ t('grossanlass.beschaffung.anfragen.preview') }}
            </EButton>
          </li>
        </ul>
      </section>
    </div>

    <EDialog
      v-model="previewOpen"
      :title="t('grossanlass.beschaffung.anfragen.previewTitle')"
      max-width="560"
    >
      <template v-if="previewFirma">
        <p class="mail-kicker">{{ previewFirma.name }} · {{ previewFirma.email || t('grossanlass.beschaffung.anfragen.missingEmail') }}</p>
        <p class="mail-subject">{{ previewMail.subject }}</p>
        <pre class="mail-body">{{ previewMail.body }}</pre>
        <ul v-if="previewThread.length" class="thread">
          <li v-for="(line, index) in previewThread" :key="index">
            <strong>{{ t(`grossanlass.beschaffung.anfragen.threadWho.${line.who}`) }}</strong>
            {{ line.text }}
          </li>
        </ul>
      </template>
      <template #actions>
        <EButton variant="secondary" size="small" @click="previewOpen = false">
          {{ t('common.close') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!previewFirma?.gmail_open_url"
          @click="openGmail"
        >
          {{ t('grossanlass.beschaffung.anfragen.openGmail') }}
        </EButton>
        <EButton
          v-if="previewStatus === 'entwurf'"
          variant="primary"
          size="small"
          @click="markPreviewSent"
        >
          {{ t('grossanlass.beschaffung.anfragen.markSent') }}
        </EButton>
        <EButton
          v-else-if="previewStatus === 'gesendet'"
          variant="primary"
          size="small"
          @click="replyPreview"
        >
          {{ t('grossanlass.beschaffung.anfragen.simulateReply') }}
        </EButton>
        <EButton
          v-else-if="previewStatus === 'antwort'"
          variant="primary"
          size="small"
          @click="acceptPreview"
        >
          {{ t('grossanlass.beschaffung.anfragen.markZusage') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="createOpen" :title="t('grossanlass.beschaffung.anfragen.addFirm')" :max-width="480">
      <ETextField v-model="createForm.name" :label="t('grossanlass.beschaffung.anfragen.colFirm')" hide-details="auto" class="mb-2" />
      <ETextField v-model="createForm.email" :label="t('grossanlass.beschaffung.anfragen.emailLabel')" hide-details="auto" class="mb-2" />
      <ETextField v-model="createForm.place" :label="t('grossanlass.beschaffung.anfragen.placeLabel')" hide-details="auto" class="mb-2" />
      <ETextField v-model="createForm.categories" :label="t('grossanlass.beschaffung.anfragen.packagesLabel')" :hint="t('grossanlass.beschaffung.anfragen.packagesHint')" hide-details="auto" />
      <template #actions>
        <EButton variant="secondary" size="small" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!createForm.name.trim()" :loading="isSaving" @click="createFirm">
          {{ t('common.add') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="draftsOpen"
      :title="t('grossanlass.beschaffung.anfragen.draftsTitle')"
      max-width="560"
    >
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.draftsHint') }}</p>
      <ul class="draft-list">
        <li v-for="firma in selectedFirms" :key="firma.id">
          <strong>{{ firma.name }}</strong>
          <span>{{ anfrageMailPreview(firma, tr).subject }}</span>
        </li>
      </ul>
      <template #actions>
        <EButton variant="secondary" size="small" @click="draftsOpen = false">
          {{ t('common.close') }}
        </EButton>
        <EButton variant="primary" size="small" :loading="isDrafting" @click="confirmDrafts">
          {{ t('grossanlass.beschaffung.anfragen.draftsConfirm') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDialog, ESearchField, ETextField } from '@/components/form/base'
import {
  anfrageCategoryLabel,
  anfrageMailPreview,
  type GaAnfrageFirma,
} from '@/views/grossanlass/grossanlassAnfragenPreviewData'
import {
  createGrossanlassInquiry,
  createGrossanlassInquiryDrafts,
  getGrossanlassInquiries,
  importGrossanlassInquiryTips,
  markGrossanlassInquiriesSent,
  recordGrossanlassInquiryReply,
  syncGrossanlassInquiryGmail,
  updateGrossanlassInquiry,
  type GrossanlassInquiry,
} from '@/api/grossanlassInquiries'
import {
  getGrossanlassGmailStatus,
  previewGrossanlassMail,
  type GrossanlassGmailStatus,
  type GrossanlassMailPreview,
} from '@/api/grossanlassGmail'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const view = ref<'firms' | 'category'>('firms')
const query = ref('')
const selected = ref<string[]>([])
const previewOpen = ref(false)
const draftsOpen = ref(false)
const createOpen = ref(false)
const isLoading = ref(false)
const isSaving = ref(false)
const isImporting = ref(false)
const isDrafting = ref(false)
const isSyncing = ref(false)
const firms = ref<GrossanlassInquiry[]>([])
const previewFirma = ref<GrossanlassInquiry | null>(null)
const gmailStatus = ref<GrossanlassGmailStatus | null>(null)
const livePreview = ref<GrossanlassMailPreview | null>(null)
const createForm = reactive({ name: '', email: '', place: '', categories: '' })

function asMailFirma(row: GrossanlassInquiry): GaAnfrageFirma {
  return {
    id: row.id,
    name: row.name,
    email: row.email,
    place: row.place,
    categoryIds: row.category_ids,
    status: row.status,
    tipFrom: row.tip_from ?? undefined,
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    firms.value = await getGrossanlassInquiries(departmentId.value)
    gmailStatus.value = await getGrossanlassGmailStatus(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError'))
  } finally {
    isLoading.value = false
  }
}

function replaceFirm(next: GrossanlassInquiry) {
  firms.value = firms.value.map((row) => (row.id === next.id ? next : row))
  if (previewFirma.value?.id === next.id) previewFirma.value = next
}

function canDraft(firma: GrossanlassInquiry): boolean {
  return !!firma.email && firma.status !== 'vorschlag' && firma.status !== 'absage' && firma.status !== 'zusage'
}

const filteredFirms = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return firms.value
  return firms.value.filter((firma) => {
    const packages = firma.category_ids.map((id) => anfrageCategoryLabel(id, tr)).join(' ')
    return `${firma.name} ${firma.place} ${firma.email} ${packages}`.toLowerCase().includes(q)
  })
})

const visibleIds = computed(() => filteredFirms.value.filter(canDraft).map((firma) => firma.id))
const allVisibleSelected = computed(
  () => visibleIds.value.length > 0 && visibleIds.value.every((id) => selected.value.includes(id)),
)
const selectedFirms = computed(() => firms.value.filter((firma) => selected.value.includes(firma.id)))

const previewMail = computed(() => {
  if (livePreview.value) {
    return { subject: livePreview.value.subject, body: livePreview.value.body }
  }
  return previewFirma.value
    ? anfrageMailPreview(asMailFirma(previewFirma.value), tr)
    : { subject: '', body: '' }
})

const previewStatus = computed(() => previewFirma.value?.status ?? 'entwurf')
const previewThread = computed(() => previewFirma.value?.thread ?? [])

const categoryBlocks = computed(() => {
  const labels = new Map<string, GrossanlassInquiry[]>()
  for (const firma of filteredFirms.value) {
    const keys = firma.category_ids.length ? firma.category_ids : ['_none']
    for (const key of keys) {
      const list = labels.get(key) ?? []
      list.push(firma)
      labels.set(key, list)
    }
  }
  return [...labels.entries()].map(([id, list]) => ({
    id,
    label: id === '_none' ? t('grossanlass.beschaffung.anfragen.noPackage') : anfrageCategoryLabel(id, tr),
    firms: list,
  }))
})

function toggle(id: string) {
  selected.value = selected.value.includes(id)
    ? selected.value.filter((item) => item !== id)
    : [...selected.value, id]
}

function toggleAllVisible() {
  if (allVisibleSelected.value) {
    selected.value = selected.value.filter((id) => !visibleIds.value.includes(id))
    return
  }
  selected.value = [...new Set([...selected.value, ...visibleIds.value])]
}

function goGmailSettings() {
  const dept = departmentId.value
  if (!dept) return
  void router.push(`/${dept}/einstellungen/anfragen-email`)
}

async function openPreview(firma: GrossanlassInquiry) {
  previewFirma.value = firma
  previewOpen.value = true
  livePreview.value = null
  if (!departmentId.value) return
  try {
    livePreview.value = await previewGrossanlassMail(departmentId.value, {
      kind: 'anfrage',
      inquiry_id: firma.id,
    })
  } catch {
    livePreview.value = null
  }
}

function openGmail() {
  const url = previewFirma.value?.gmail_open_url
  if (url) window.open(url, '_blank', 'noopener')
}

async function syncGmail() {
  if (!departmentId.value) return
  isSyncing.value = true
  try {
    const updated = await syncGrossanlassInquiryGmail(departmentId.value)
    updated.forEach(replaceFirm)
    toast.success(t('grossanlass.beschaffung.anfragen.gmailSyncToast', { count: updated.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isSyncing.value = false
  }
}

async function markPreviewSent() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    const updated = await markGrossanlassInquiriesSent(departmentId.value, [previewFirma.value.id])
    updated.forEach(replaceFirm)
    toast.success(t('grossanlass.beschaffung.anfragen.sentToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function replyPreview() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    replaceFirm(await recordGrossanlassInquiryReply(departmentId.value, previewFirma.value.id))
    toast.success(t('grossanlass.beschaffung.anfragen.replyToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function acceptPreview() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    replaceFirm(
      await updateGrossanlassInquiry(departmentId.value, previewFirma.value.id, { status: 'zusage' }),
    )
    toast.success(t('grossanlass.beschaffung.anfragen.zusageToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function confirmDrafts() {
  if (!departmentId.value) return
  if (!gmailStatus.value?.connected) {
    toast.error(t('grossanlass.beschaffung.anfragen.draftsNeedGmail'))
    goGmailSettings()
    return
  }
  isDrafting.value = true
  try {
    const updated = await createGrossanlassInquiryDrafts(departmentId.value, selected.value)
    updated.forEach(replaceFirm)
    draftsOpen.value = false
    selected.value = []
    toast.success(t('grossanlass.beschaffung.anfragen.gmailDraftToast', { count: updated.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isDrafting.value = false
  }
}

async function importTips() {
  if (!departmentId.value) return
  isImporting.value = true
  try {
    const created = await importGrossanlassInquiryTips(departmentId.value)
    toast.success(t('grossanlass.beschaffung.anfragen.importedTips', { count: created.length }))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isImporting.value = false
  }
}

async function createFirm() {
  if (!departmentId.value || !createForm.name.trim()) return
  isSaving.value = true
  try {
    const created = await createGrossanlassInquiry(departmentId.value, {
      name: createForm.name.trim(),
      email: createForm.email.trim(),
      place: createForm.place.trim(),
      category_ids: createForm.categories,
    })
    firms.value = [created, ...firms.value]
    createForm.name = ''
    createForm.email = ''
    createForm.place = ''
    createForm.categories = ''
    createOpen.value = false
    toast.success(t('grossanlass.beschaffung.anfragen.createdToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>


<style scoped>
.ga-anfragen { padding: 4px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.gmail-strip {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #fff;
}
.gmail-strip p { margin: 4px 0 0; color: #64748b; font-size: 0.82rem; }
.gmail-strip__actions { display: flex; flex-wrap: wrap; gap: 8px; }
.ga-anfragen__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  margin-bottom: 14px;
}
.ga-anfragen__search { flex: 1 1 220px; min-width: min(100%, 200px); }
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
.table-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.data-table th { background: #f8fafc; font-weight: 600; }
.col-check { width: 36px; }
.meta { display: block; color: #64748b; font-size: 0.75rem; margin-top: 2px; }
.is-blocked td { background: #fff7ed; }
.pkg-chip {
  display: inline-flex;
  margin: 0 4px 4px 0;
  padding: 2px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  font-size: 0.72rem;
  font-weight: 600;
}
.status-chip {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}
.status-chip--entwurf { background: #eff6ff; color: #1d4ed8; }
.status-chip--gesendet { background: #ecfeff; color: #0e7490; }
.status-chip--antwort { background: #fef9c3; color: #a16207; }
.status-chip--zusage { background: #dcfce7; color: #15803d; }
.status-chip--absage { background: #fee2e2; color: #b91c1c; }
.status-chip--vorschlag { background: #f3e8ff; color: #7e22ce; }
.category-list { display: grid; gap: 12px; }
.category-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 12px 14px;
}
.category-card h3 {
  display: flex;
  justify-content: space-between;
  margin: 0 0 8px;
  font-size: 0.9rem;
}
.category-card h3 span { color: #64748b; font-weight: 500; }
.category-card ul { list-style: none; margin: 0; padding: 0; }
.category-card li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-top: 1px solid #f1f5f9;
}
.mail-kicker, .review-hint { margin: 0 0 8px; color: #64748b; font-size: 0.82rem; }
.mail-subject { margin: 0 0 10px; font-weight: 700; }
.mail-body {
  margin: 0;
  white-space: pre-wrap;
  font: inherit;
  font-size: 0.85rem;
  line-height: 1.45;
  background: #f8fafc;
  border-radius: 8px;
  padding: 12px;
}
.thread { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
.thread li {
  background: #f8fafc;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 0.82rem;
}
.thread strong { display: block; font-size: 0.72rem; color: #64748b; }
.draft-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.draft-list li { display: flex; flex-direction: column; gap: 2px; font-size: 0.85rem; }
.draft-list span { color: #64748b; font-size: 0.78rem; }
</style>
