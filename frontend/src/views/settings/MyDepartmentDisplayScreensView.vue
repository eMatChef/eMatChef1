<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ t('settings.displayScreens.title') }}</h1>
      <p class="description">{{ t('settings.displayScreens.description') }}</p>
      <p class="muted path-hint">{{ t('settings.displayScreens.pathHint') }}</p>
      <p class="muted path-hint">
        {{ t('settings.displayScreens.kioskEntryHint') }}
        <code class="inline-code">{{ kioskEntryUrl }}</code>
      </p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :items="departmentSelectItems"
        :label="t('settings.common.selectDepartment')"
        hide-details
        @update:model-value="onDepartmentChange"
      />
    </div>

    <div v-if="!canManage" class="card">
      <p class="muted">{{ t('settings.displayScreens.noPermission') }}</p>
    </div>

    <template v-else>
      <div class="card create-card">
        <h3 class="section-heading">{{ t('settings.displayScreens.createTitle') }}</h3>
        <div class="create-row">
          <ETextField
            v-model="newScreenName"
            class="create-row__name"
            :placeholder="t('settings.displayScreens.namePlaceholder')"
            :disabled="creating"
            maxlength="120"
            hide-details
          />
          <EButton
            variant="primary"
            :disabled="creating || !newScreenName.trim()"
            :loading="creating"
            @click="createScreen"
          >
            {{ t('common.create') }}
          </EButton>
        </div>
      </div>

      <ELoadingState v-if="loading" variant="inline" :message="t('settings.displayScreens.loading')" />

      <div
        v-for="screen in activeScreens"
        :key="screen.id"
        class="card screen-card"
        :class="{ 'screen-card--collapsed': useAccordion && !isExpanded(screen.id) }"
      >
        <button
          v-if="useAccordion"
          type="button"
          class="accordion-toggle"
          :aria-expanded="isExpanded(screen.id)"
          @click="toggleExpanded(screen.id)"
        >
          <span class="accordion-title">{{ screen.name }}</span>
          <span v-if="screen.revoked_at" class="badge revoked">{{ t('settings.displayScreens.revoked') }}</span>
          <span class="accordion-chevron" :class="{ open: isExpanded(screen.id) }" aria-hidden="true">▼</span>
        </button>
        <h3 v-else class="screen-head-title">{{ screen.name }}</h3>

        <div v-show="!useAccordion || isExpanded(screen.id)" class="screen-card-body">
          <template v-if="!screen.revoked_at && drafts[screen.id]">
            <div class="screen-columns">
              <section class="settings-block settings-block--content">
                <h4 class="block-title">{{ t('settings.displayScreens.contentSectionTitle') }}</h4>
                <ETextarea
                  :id="`subtitle-${screen.id}`"
                  v-model="drafts[screen.id].subtitle_text"
                  :label="t('settings.displayScreens.subtitleLabel')"
                  rows="1"
                  maxlength="500"
                  :placeholder="t('settings.displayScreens.subtitlePlaceholder')"
                  hide-details="auto"
                  class="subtitle-field"
                />
                <p class="muted field-hint">{{ t('settings.displayScreens.subtitleHint') }}</p>

                <p class="label label--tight content-label">{{ t('settings.displayScreens.showPanelsLabel') }}</p>

                <details class="settings-accordion" open>
                  <summary>{{ t('settings.displayScreens.accordionActivities') }}</summary>
                  <div class="accordion-body">
                    <ECheckbox
                      v-model="drafts[screen.id].show_activities"
                      :label="t('settings.displayScreens.showActivities')"
                      hide-details
                    />
                    <template v-if="drafts[screen.id].show_activities">
                      <p class="label label--tight">{{ t('settings.displayScreens.activityTypesLabel') }}</p>
                      <div class="checkbox-group checkbox-group--nested">
                        <ECheckbox
                          v-for="activityType in displayActivityTypes"
                          :key="activityType"
                          v-model="drafts[screen.id].activity_types[activityType]"
                          :label="t(`activities.types.${activityType}`)"
                          hide-details
                        />
                      </div>
                      <p class="label label--tight">{{ t('settings.displayScreens.activityStatusesLabel') }}</p>
                      <div class="checkbox-group checkbox-group--nested">
                        <ECheckbox
                          v-for="status in displayActivityStatuses"
                          :key="status"
                          v-model="drafts[screen.id].activity_statuses[status]"
                          :label="t(`activities.status.${status}`)"
                          hide-details
                        />
                      </div>
                      <p class="muted field-hint">{{ t('settings.displayScreens.activityFiltersHint') }}</p>
                    </template>
                  </div>
                </details>

                <details class="settings-accordion">
                  <summary>{{ t('settings.displayScreens.accordionWorkshop') }}</summary>
                  <div class="accordion-body">
                    <ECheckbox
                      v-model="drafts[screen.id].show_workshop"
                      :label="t('settings.displayScreens.showWorkshop')"
                      hide-details
                    />
                    <template v-if="drafts[screen.id].show_workshop">
                      <p class="label label--tight">{{ t('settings.displayScreens.workshopStatusesLabel') }}</p>
                      <div class="checkbox-group checkbox-group--nested">
                        <ECheckbox
                          v-for="status in displayWorkshopStatuses"
                          :key="status"
                          v-model="drafts[screen.id].workshop_statuses[status]"
                          :label="t(`workshop.status.${status}`)"
                          hide-details
                        />
                      </div>
                    </template>
                  </div>
                </details>

                <details class="settings-accordion">
                  <summary>{{ t('settings.displayScreens.accordionStatistics') }}</summary>
                  <div class="accordion-body">
                    <ECheckbox
                      v-model="drafts[screen.id].show_statistics"
                      :label="t('settings.displayScreens.showStatistics')"
                      hide-details
                    />
                    <p class="muted field-hint">{{ t('settings.displayScreens.statisticsHint') }}</p>
                  </div>
                </details>

                <EButton
                  variant="primary"
                  size="small"
                  :disabled="savingSettingsId === screen.id"
                  :loading="savingSettingsId === screen.id"
                  @click="saveSettings(screen)"
                >
                  {{ t('settings.displayScreens.saveSettings') }}
                </EButton>
              </section>

              <section class="settings-block settings-block--access">
                <h4 class="block-title">{{ t('settings.displayScreens.accessSectionTitle') }}</h4>
                <div class="access-layout">
                  <div class="access-main">
                    <label class="label label--tight">{{ t('settings.displayScreens.urlLabel') }}</label>
                    <div class="url-row url-row--compact">
                      <ETextField
                        class="url-input-field"
                        :model-value="screen.display_url"
                        readonly
                        hide-details
                        @focus="selectInputText"
                      />
                      <div class="url-actions">
                        <EButton variant="secondary" size="small" @click="copyUrl(screen.display_url)">
                          {{ t('settings.displayScreens.copyUrl') }}
                        </EButton>
                        <EButton variant="secondary" size="small" @click="openDisplayUrl(screen.display_url)">
                          {{ t('settings.displayScreens.openDisplay') }}
                        </EButton>
                      </div>
                    </div>
                    <p class="muted setup-hint">{{ t('settings.displayScreens.setupHint') }}</p>
                    <div v-if="screen.access_code_hint || screen.last_used_at" class="meta-row">
                      <span v-if="screen.access_code_hint" class="muted meta-item">
                        {{ t('settings.displayScreens.codeHint', { hint: screen.access_code_hint }) }}
                      </span>
                      <span v-if="screen.last_used_at" class="muted meta-item">
                        {{ t('settings.displayScreens.lastUsed', { date: formatDate(screen.last_used_at) }) }}
                      </span>
                    </div>
                  </div>
                  <div v-if="screenQrById[screen.id]" class="qr-block qr-block--side">
                    <img :src="screenQrById[screen.id]" :alt="t('settings.displayScreens.qrAlt')" />
                    <p class="muted qr-caption">{{ t('settings.displayScreens.qrCaption') }}</p>
                  </div>
                </div>
              </section>
            </div>

            <div class="screen-footer">
              <EButton
                variant="secondary"
                size="small"
                :disabled="rotatingId === screen.id"
                :loading="rotatingId === screen.id"
                @click="rotateCode(screen)"
              >
                {{ t('settings.displayScreens.rotateCode') }}
              </EButton>
              <EButton
                variant="danger"
                size="small"
                :disabled="revokingId === screen.id"
                :loading="revokingId === screen.id"
                @click="revokeScreen(screen)"
              >
                {{ t('settings.displayScreens.revoke') }}
              </EButton>
            </div>
          </template>

          <div v-else class="revoked-line">
            <p class="muted revoked-meta">
              <span class="badge revoked">{{ t('settings.displayScreens.revoked') }}</span>
              <span class="revoked-url">{{ screen.display_url }}</span>
            </p>
            <EButton
              variant="primary"
              size="small"
              :disabled="reactivatingId === screen.id"
              :loading="reactivatingId === screen.id"
              @click="reactivateScreen(screen)"
            >
              {{ t('settings.displayScreens.reactivate') }}
            </EButton>
          </div>
        </div>
      </div>

      <EEmptyState
        v-if="!loading && activeScreens.length === 0"
        :title="t('settings.displayScreens.empty')"
        compact
      />
    </template>

    <EDialog
      v-model="setupDialogOpen"
      :max-width="520"
      :title="t('settings.displayScreens.setupModalTitle')"
    >
      <p class="muted">{{ t('settings.displayScreens.setupModalHint') }}</p>

      <ETextField
        class="mt-3 url-input-field"
        :model-value="revealedSetup?.url ?? ''"
        :label="t('settings.displayScreens.urlLabel')"
        readonly
        hide-details
        @focus="selectInputText"
      />
      <div class="url-actions mt-2">
        <EButton variant="primary" size="small" :disabled="!revealedSetup" @click="copyUrl(revealedSetup!.url)">
          {{ t('settings.displayScreens.copyUrl') }}
        </EButton>
        <EButton variant="secondary" size="small" :disabled="!revealedSetup" @click="openDisplayUrl(revealedSetup!.url)">
          {{ t('settings.displayScreens.openDisplay') }}
        </EButton>
      </div>

      <p class="label code-label">{{ t('settings.displayScreens.accessCodeLabel') }}</p>
      <code class="access-code">{{ revealedSetup?.code }}</code>

      <div v-if="setupQrDataUrl" class="qr-block modal-qr">
        <img :src="setupQrDataUrl" :alt="t('settings.displayScreens.qrAlt')" />
      </div>

      <template #actions>
        <EButton variant="primary" size="small" @click="copySetupBundle">{{ t('settings.displayScreens.copyAll') }}</EButton>
        <EButton variant="secondary" size="small" @click="copyCode">{{ t('settings.displayScreens.copyCode') }}</EButton>
        <EButton variant="secondary" size="small" @click="dismissRevealedSetup">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { copyTextToClipboard } from '@/utils/clipboard'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import {
  createDisplayScreen,
  listDisplayScreens,
  reactivateDisplayScreen,
  revokeDisplayScreen,
  rotateDisplayScreenCode,
  updateDisplayScreenSettings,
  type DisplayScreenSettings,
} from '@/api/displayScreens'
import {
  DISPLAY_ACTIVITY_STATUSES,
  DISPLAY_ACTIVITY_TYPES,
  DISPLAY_WORKSHOP_STATUSES,
  listFromRecord,
  normalizeDisplayActivityStatuses,
  normalizeDisplayActivityTypes,
  normalizeDisplayWorkshopStatuses,
  recordFromList,
  type DisplayActivityStatus,
  type DisplayActivityType,
  type DisplayWorkshopStatus,
} from '@/constants/displayScreenConfig'

const displayActivityTypes = DISPLAY_ACTIVITY_TYPES
const displayActivityStatuses = DISPLAY_ACTIVITY_STATUSES
const displayWorkshopStatuses = DISPLAY_WORKSHOP_STATUSES

interface ScreenDraft {
  subtitle_text: string
  show_activities: boolean
  show_workshop: boolean
  show_statistics: boolean
  activity_types: Record<DisplayActivityType, boolean>
  activity_statuses: Record<DisplayActivityStatus, boolean>
  workshop_statuses: Record<DisplayWorkshopStatus, boolean>
}

function draftFromScreen(screen: DisplayScreenSettings): ScreenDraft {
  return {
    subtitle_text: screen.subtitle_text || '',
    show_activities: screen.show_activities !== false,
    show_workshop: screen.show_workshop !== false,
    show_statistics: screen.show_statistics === true,
    activity_types: recordFromList(DISPLAY_ACTIVITY_TYPES, normalizeDisplayActivityTypes(screen.activity_types)),
    activity_statuses: recordFromList(
      DISPLAY_ACTIVITY_STATUSES,
      normalizeDisplayActivityStatuses(screen.activity_statuses),
    ),
    workshop_statuses: recordFromList(
      DISPLAY_WORKSHOP_STATUSES,
      normalizeDisplayWorkshopStatuses(screen.workshop_statuses),
    ),
  }
}

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t, locale } = useI18n()

const selectedDepartmentId = ref<string | null>(null)
const screens = ref<DisplayScreenSettings[]>([])
const loading = ref(false)
const creating = ref(false)
const newScreenName = ref('')
const rotatingId = ref<string | null>(null)
const revokingId = ref<string | null>(null)
const reactivatingId = ref<string | null>(null)
const revealedSetup = ref<{ url: string; code: string } | null>(null)
const setupQrDataUrl = ref('')
const screenQrById = ref<Record<string, string>>({})
const expandedIds = ref<Set<string>>(new Set())
const drafts = ref<Record<string, ScreenDraft>>({})
const savingSettingsId = ref<string | null>(null)

const kioskEntryUrl = computed(() => {
  const origin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  if (origin) return `${origin}/display`
  if (typeof window !== 'undefined') return `${window.location.origin}/display`
  return '/display'
})

const userDepartments = computed(() => authStore.departments || [])

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => ({
    title: dept.department?.name || dept.department_id,
    value: dept.department_id,
  })),
)

const setupDialogOpen = computed({
  get: () => revealedSetup.value !== null,
  set: (open: boolean) => {
    if (!open) dismissRevealedSetup()
  },
})

function selectInputText(event: FocusEvent) {
  const el = event.target as HTMLInputElement | null
  el?.select?.()
}

function openDisplayUrl(url: string) {
  if (!url) return
  window.open(url, '_blank', 'noopener,noreferrer')
}
const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find((d) => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})
const canManage = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(
    normalizedRole,
  )
})
const activeScreens = computed(() => screens.value)
const useAccordion = computed(() => activeScreens.value.length > 1)

function isExpanded(screenId: string): boolean {
  return expandedIds.value.has(screenId)
}

function toggleExpanded(screenId: string) {
  const next = new Set(expandedIds.value)
  if (next.has(screenId)) {
    next.delete(screenId)
  } else {
    next.add(screenId)
  }
  expandedIds.value = next
}

function syncExpandedFromScreens(list: DisplayScreenSettings[]) {
  const active = list.filter((s) => !s.revoked_at)
  if (active.length === 1) {
    expandedIds.value = new Set([active[0].id])
    return
  }
  if (active.length > 1) {
    expandedIds.value = new Set()
    return
  }
  expandedIds.value = new Set()
}

function initDraftsFromScreens(list: DisplayScreenSettings[]) {
  const next: Record<string, ScreenDraft> = {}
  for (const s of list) {
    next[s.id] = draftFromScreen(s)
  }
  drafts.value = next
}

async function saveSettings(screen: DisplayScreenSettings) {
  if (!selectedDepartmentId.value || screen.revoked_at) return
  const draft = drafts.value[screen.id]
  if (!draft) return
  if (!draft.show_activities && !draft.show_workshop && !draft.show_statistics) {
    toast.error(t('settings.displayScreens.errorNoPanel'))
    return
  }
  if (draft.show_activities && listFromRecord(DISPLAY_ACTIVITY_TYPES, draft.activity_types).length === 0) {
    toast.error(t('settings.displayScreens.errorNoActivityType'))
    return
  }
  if (draft.show_activities && listFromRecord(DISPLAY_ACTIVITY_STATUSES, draft.activity_statuses).length === 0) {
    toast.error(t('settings.displayScreens.errorNoActivityStatus'))
    return
  }
  if (draft.show_workshop && listFromRecord(DISPLAY_WORKSHOP_STATUSES, draft.workshop_statuses).length === 0) {
    toast.error(t('settings.displayScreens.errorNoWorkshopStatus'))
    return
  }

  savingSettingsId.value = screen.id
  try {
    const updated = await updateDisplayScreenSettings(selectedDepartmentId.value, screen.id, {
      subtitle_text: draft.subtitle_text.trim() || null,
      show_activities: draft.show_activities,
      show_workshop: draft.show_workshop,
      show_statistics: draft.show_statistics,
      activity_types: listFromRecord(DISPLAY_ACTIVITY_TYPES, draft.activity_types),
      activity_statuses: listFromRecord(DISPLAY_ACTIVITY_STATUSES, draft.activity_statuses),
      workshop_statuses: listFromRecord(DISPLAY_WORKSHOP_STATUSES, draft.workshop_statuses),
    })
    screens.value = screens.value.map((s) => (s.id === screen.id ? updated : s))
    initDraftsFromScreens(screens.value)
    toast.success(t('settings.displayScreens.toastSettingsSaved'))
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(msg || t('settings.displayScreens.toastSettingsError'))
  } finally {
    savingSettingsId.value = null
  }
}

async function buildQrDataUrl(url: string): Promise<string> {
  const payload = url.trim()
  if (!payload) return ''
  return QRCode.toDataURL(payload, { width: 128, margin: 1 })
}

async function refreshScreenQrs(list: DisplayScreenSettings[]) {
  const next: Record<string, string> = {}
  await Promise.all(
    list
      .filter((s) => !s.revoked_at && s.display_url)
      .map(async (s) => {
        next[s.id] = await buildQrDataUrl(s.display_url)
      }),
  )
  screenQrById.value = next
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const tag = String(locale.value ?? '').startsWith('de') ? 'de-CH' : 'en-CH'
  return d.toLocaleString(tag, { dateStyle: 'medium', timeStyle: 'short' })
}

async function showRevealedSetup(url: string, code: string) {
  revealedSetup.value = { url, code }
  setupQrDataUrl.value = await buildQrDataUrl(url)
}

function dismissRevealedSetup() {
  revealedSetup.value = null
  setupQrDataUrl.value = ''
}

async function loadScreens(deptId: string) {
  if (!canManage.value) {
    screens.value = []
    screenQrById.value = {}
    return
  }
  loading.value = true
  try {
    screens.value = await listDisplayScreens(deptId)
    initDraftsFromScreens(screens.value)
    syncExpandedFromScreens(screens.value)
    await refreshScreenQrs(screens.value)
  } catch {
    screens.value = []
    screenQrById.value = {}
    toast.error(t('settings.displayScreens.toastLoadError'))
  } finally {
    loading.value = false
  }
}

async function createScreen() {
  if (!selectedDepartmentId.value || !newScreenName.value.trim()) return
  creating.value = true
  try {
    const created = await createDisplayScreen(selectedDepartmentId.value, newScreenName.value.trim())
    screens.value = [created, ...screens.value]
    initDraftsFromScreens(screens.value)
    syncExpandedFromScreens(screens.value)
    newScreenName.value = ''
    if (created.access_code && created.display_url) {
      await showRevealedSetup(created.display_url, created.access_code)
    }
    await refreshScreenQrs(screens.value)
    toast.success(t('settings.displayScreens.toastCreated'))
  } catch (err: unknown) {
    const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(msg || t('settings.displayScreens.toastCreateError'))
  } finally {
    creating.value = false
  }
}

async function rotateCode(screen: DisplayScreenSettings) {
  if (!selectedDepartmentId.value || screen.revoked_at) return
  const ok = await confirm.confirm({
    title: t('settings.displayScreens.confirmRotateTitle'),
    message: t('settings.displayScreens.confirmRotateMessage'),
    confirmText: t('settings.displayScreens.rotateCode'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return

  rotatingId.value = screen.id
  try {
    const updated = await rotateDisplayScreenCode(selectedDepartmentId.value, screen.id)
    screens.value = screens.value.map((s) => (s.id === screen.id ? updated : s))
    if (updated.access_code && updated.display_url) {
      await showRevealedSetup(updated.display_url, updated.access_code)
    }
    await refreshScreenQrs(screens.value)
    toast.success(t('settings.displayScreens.toastRotated'))
  } catch {
    toast.error(t('settings.displayScreens.toastRotateError'))
  } finally {
    rotatingId.value = null
  }
}

async function revokeScreen(screen: DisplayScreenSettings) {
  if (!selectedDepartmentId.value || screen.revoked_at) return
  const ok = await confirm.confirm({
    title: t('settings.displayScreens.confirmRevokeTitle'),
    message: t('settings.displayScreens.confirmRevokeMessage'),
    confirmText: t('settings.displayScreens.revoke'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  revokingId.value = screen.id
  try {
    const updated = await revokeDisplayScreen(selectedDepartmentId.value, screen.id)
    screens.value = screens.value.map((s) => (s.id === screen.id ? updated : s))
    await refreshScreenQrs(screens.value)
    toast.success(t('settings.displayScreens.toastRevoked'))
  } catch {
    toast.error(t('settings.displayScreens.toastRevokeError'))
  } finally {
    revokingId.value = null
  }
}

async function reactivateScreen(screen: DisplayScreenSettings) {
  if (!selectedDepartmentId.value || !screen.revoked_at) return
  const ok = await confirm.confirm({
    title: t('settings.displayScreens.confirmReactivateTitle'),
    message: t('settings.displayScreens.confirmReactivateMessage'),
    confirmText: t('settings.displayScreens.reactivate'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return

  reactivatingId.value = screen.id
  try {
    const updated = await reactivateDisplayScreen(selectedDepartmentId.value, screen.id)
    screens.value = screens.value.map((s) => (s.id === screen.id ? updated : s))
    initDraftsFromScreens(screens.value)
    syncExpandedFromScreens(screens.value)
    if (updated.access_code && updated.display_url) {
      await showRevealedSetup(updated.display_url, updated.access_code)
    }
    await refreshScreenQrs(screens.value)
    toast.success(t('settings.displayScreens.toastReactivated'))
  } catch {
    toast.error(t('settings.displayScreens.toastReactivateError'))
  } finally {
    reactivatingId.value = null
  }
}

async function copyUrl(url: string) {
  const ok = await copyTextToClipboard(url)
  if (ok) toast.success(t('settings.displayScreens.toastCopiedUrl'))
  else toast.error(t('settings.addressModal.clipboardDenied'))
}

async function copyCode() {
  if (!revealedSetup.value?.code) return
  const ok = await copyTextToClipboard(revealedSetup.value.code)
  if (ok) toast.success(t('settings.displayScreens.toastCopiedCode'))
  else toast.error(t('settings.addressModal.clipboardDenied'))
}

async function copySetupBundle() {
  if (!revealedSetup.value) return
  const text = `${t('settings.displayScreens.urlLabel')}: ${revealedSetup.value.url}\n${t('settings.displayScreens.accessCodeLabel')}: ${revealedSetup.value.code}`
  const ok = await copyTextToClipboard(text)
  if (ok) toast.success(t('settings.displayScreens.toastCopiedAll'))
  else toast.error(t('settings.addressModal.clipboardDenied'))
}

async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  await authStore.setActiveDepartment(newDeptId)
  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  await loadScreens(newDeptId)
}

onMounted(async () => {
  selectedDepartmentId.value = authStore.activeDepartmentId || (userDepartments.value[0]?.department_id ?? null)
  if (selectedDepartmentId.value) await loadScreens(selectedDepartmentId.value)
})
</script>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.header h1 {
  margin: 0;
  font-size: 22px;
}
.description,
.muted {
  color: #6b7280;
}
.description {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.4;
}
.path-hint {
  margin: 4px 0 0;
  font-size: 12px;
  line-height: 1.4;
}
.card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
}
.label {
  display: block;
  margin-bottom: 6px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
}
.label--tight {
  margin-bottom: 4px;
}
.code-label {
  margin-top: 12px;
}
.input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 10px;
  background: #fff;
  font: inherit;
}
.input--sm {
  padding: 6px 8px;
  font-size: 13px;
}
.url-input {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
}
.create-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.create-row__name {
  flex: 1;
  min-width: 200px;
}

.url-input-field :deep(input) {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
}
.create-card .section-heading,
.section-heading {
  margin: 0 0 8px;
  font-size: 15px;
}
.url-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: stretch;
}
.url-row .url-input {
  flex: 1;
  min-width: 180px;
}
.url-row--compact {
  flex-direction: column;
  align-items: stretch;
}
.url-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.screen-head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.screen-head-title {
  margin: 0 0 8px;
  font-size: 16px;
}
.accordion-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin: 0 0 0;
  padding: 0 0 8px;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 17px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
  color: inherit;
}
.accordion-title {
  flex: 1;
}
.accordion-chevron {
  font-size: 12px;
  transition: transform 0.2s ease;
  color: #64748b;
}
.accordion-chevron.open {
  transform: rotate(180deg);
}
.screen-card--collapsed {
  padding-bottom: 12px;
}
.screen-card-body {
  padding-top: 2px;
}
.screen-columns {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px 20px;
}
@media (min-width: 900px) {
  .screen-columns {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
  }
}
.settings-block {
  margin: 0;
  min-width: 0;
}
.settings-block--content {
  padding-right: 0;
}
@media (min-width: 900px) {
  .settings-block--content {
    padding-right: 16px;
    border-right: 1px dashed #e2e8f0;
  }
}
.block-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}
.textarea {
  resize: vertical;
  min-height: 40px;
  font: inherit;
  line-height: 1.4;
}
.field-hint {
  margin: 4px 0 8px;
  font-size: 11px;
  line-height: 1.35;
}
.content-label {
  margin: 6px 0 4px;
}
.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 10px;
}
.checkbox-group--nested {
  margin-left: 12px;
  padding-left: 10px;
  border-left: 2px solid #e5e7eb;
}
.settings-accordion {
  margin: 8px 0;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
}
.settings-accordion > summary {
  cursor: pointer;
  padding: 10px 12px;
  font-weight: 600;
  font-size: 14px;
  list-style: none;
}
.settings-accordion > summary::-webkit-details-marker {
  display: none;
}
.settings-accordion > summary::before {
  content: '▸ ';
  display: inline-block;
  transition: transform 0.15s ease;
}
.settings-accordion[open] > summary::before {
  transform: rotate(90deg);
}
.accordion-body {
  padding: 0 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
@media (min-width: 520px) {
  .checkbox-group {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 10px 16px;
  }
  .checkbox-group--nested {
    flex-direction: column;
    gap: 4px;
  }
}
.checkbox-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  cursor: pointer;
}
.checkbox-row input {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}
.access-layout {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.access-main {
  flex: 1;
  min-width: 0;
}
.setup-hint {
  margin: 6px 0 0;
  font-size: 12px;
  line-height: 1.4;
}
.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  margin-top: 8px;
  font-size: 12px;
}
.meta-item {
  line-height: 1.35;
}
.screen-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #e5e7eb;
}
.revoked-line {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  margin-top: 8px;
}
.revoked-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.revoked-url {
  word-break: break-all;
  font-size: 12px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.badge.revoked {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fee2e2;
  color: #b91c1c;
}
.empty {
  padding: 8px 0;
}
.inline-code {
  display: inline-block;
  margin-left: 4px;
  padding: 2px 6px;
  background: #e2e8f0;
  border-radius: 4px;
  font-size: 12px;
}
.qr-block img {
  display: block;
  width: 108px;
  height: 108px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 3px;
  background: #fff;
}
.qr-block--side {
  flex-shrink: 0;
  text-align: center;
}
.qr-caption {
  margin: 4px 0 0;
  font-size: 10px;
  line-height: 1.3;
  max-width: 108px;
}
@media (max-width: 599px) {
  .access-layout {
    flex-direction: column;
  }
  .qr-block--side {
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
  }
  .qr-block--side .qr-caption {
    max-width: none;
    margin: 0;
  }
}
.access-code {
  display: block;
  margin: 8px 0 0;
  padding: 14px;
  text-align: center;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: 0.3em;
  background: #111827;
  color: #fff;
  border-radius: 10px;
}
.modal-qr {
  text-align: center;
}
.code-label {
  margin-top: 12px;
}
</style>
