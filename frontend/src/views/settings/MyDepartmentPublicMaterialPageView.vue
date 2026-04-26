<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ t('settings.publicMaterialPage.title') }}</h1>
      <p class="description">{{ t('settings.publicMaterialPage.description') }}</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <label class="label" for="department-select">{{ t('settings.common.selectDepartment') }}:</label>
      <select id="department-select" v-model="selectedDepartmentId" @change="onDepartmentChange" class="input">
        <option v-for="dept in userDepartments" :key="dept.department_id" :value="dept.department_id">
          {{ dept.department?.name || dept.department_id }}
        </option>
      </select>
    </div>

    <div v-if="!canManageDepartmentSensitiveSettings" class="card">
      <p class="muted">{{ t('settings.publicMaterialPage.noPermission') }}</p>
    </div>

    <div v-else class="card">
      <div class="field">
        <div class="label-row">
          <label class="label">{{ t('settings.publicMaterialPage.publicContactEmailLabel') }}</label>
          <button
            type="button"
            class="link-btn"
            :disabled="!departmentEmailCandidate"
            @click="applyDepartmentEmail"
          >
            {{ t('settings.publicMaterialPage.useDepartmentEmail') }}
          </button>
        </div>
        <input
          v-model="publicContactEmail"
          type="email"
          class="input"
          :placeholder="t('settings.publicMaterialPage.publicContactEmailPlaceholder')"
        />
      </div>
      <div class="field">
        <label class="label">{{ t('settings.publicMaterialPage.publicContactNoteLabel') }}</label>
        <textarea
          v-model="publicContactNote"
          class="input"
          rows="3"
          :placeholder="t('settings.publicMaterialPage.publicContactNotePlaceholder')"
        />
      </div>
      <div class="field toggles">
        <label><input v-model="publicShowContactForm" type="checkbox" /> {{ t('settings.publicMaterialPage.showContactForm') }}</label>
        <label>
          <input v-model="publicShowContactEmail" type="checkbox" :disabled="!hasContactEmail" />
          {{ t('settings.publicMaterialPage.showContactEmail') }}
        </label>
        <label><input v-model="publicShowContactNote" type="checkbox" /> {{ t('settings.publicMaterialPage.showContactNote') }}</label>
      </div>
      <p v-if="showContactEmailInvalid" class="validation-error">
        {{ t('settings.publicMaterialPage.contactEmailRequiredWhenVisible') }}
      </p>
      <p v-if="!hasContactEmail" class="hint-text">
        {{ t('settings.publicMaterialPage.contactEmailToggleDisabledHint') }}
      </p>
      <div class="field">
        <label class="label">{{ t('settings.publicMaterialPage.deliveryLabel') }}</label>
        <select v-model="publicFoundContactDelivery" class="input">
          <option value="email">{{ t('settings.publicMaterialPage.deliveryEmail') }}</option>
          <option value="in_app">{{ t('settings.publicMaterialPage.deliveryInApp') }}</option>
          <option value="both">{{ t('settings.publicMaterialPage.deliveryBoth') }}</option>
        </select>
      </div>

      <div class="preview-card">
        <h3>{{ t('settings.publicMaterialPage.previewTitle') }}</h3>
        <p class="preview-hint">{{ t('settings.publicMaterialPage.previewHint') }}</p>
        <div class="preview-public-card">
          <p class="preview-code">Code: M-EXAMPLE</p>
          <h4 class="preview-material-name">Beispielmaterial</h4>
          <p class="preview-material-desc">Statische Vorschau der öffentlichen Materialseite.</p>

          <div class="preview-info-grid">
            <div>
              <dt>Abteilung</dt>
              <dd>{{ userDepartments.find((d) => d.department_id === selectedDepartmentId)?.department?.name || '-' }}</dd>
            </div>
            <div>
              <dt>Hersteller</dt>
              <dd>Beispiel Hersteller</dd>
            </div>
          </div>

          <div v-if="publicShowContactForm" class="preview-contact-collapsible">
            <button type="button" class="preview-contact-toggle" disabled>
              <span class="preview-contact-toggle-label">Materialwart kontaktieren</span>
              <span aria-hidden="true">⌄</span>
            </button>
            <div class="preview-contact-panel">
              <div v-if="publicShowContactEmail || publicShowContactNote" class="preview-contact-box">
                <h5>Kontakt</h5>
                <p v-if="publicShowContactEmail">E-Mail: {{ publicContactEmail || '-' }}</p>
                <p v-if="publicShowContactNote" class="preview-contact-note">{{ publicContactNote || '-' }}</p>
              </div>

              <div v-if="previewCanDeliverMessage" class="preview-found-form-box">
                <h5 class="preview-found-form-title">Nachricht senden</h5>
                <p class="preview-found-form-hint">
                  Du hast diesen Artikel gefunden oder möchtest den Materialwart erreichen? Sende eine kurze Nachricht.
                </p>
                <form class="preview-found-form" @submit.prevent>
                  <label class="preview-found-label">
                    Dein Name <span class="optional">(optional)</span>
                    <input type="text" maxlength="120" placeholder="z. B. Vorname" disabled />
                  </label>
                  <label class="preview-found-label">
                    Deine E-Mail <span class="optional">(optional, für Rückfragen)</span>
                    <input type="email" maxlength="200" placeholder="name@beispiel.ch" disabled />
                  </label>
                  <label class="preview-found-label">
                    Nachricht <span class="req">*</span>
                    <textarea
                      rows="4"
                      maxlength="4000"
                      required
                      placeholder="z. B. Wo liegt der Artikel? Wann hast du ihn gefunden?"
                      disabled
                    />
                  </label>
                  <button type="button" class="preview-found-submit" disabled>An Materialwart senden</button>
                </form>
              </div>
              <div v-else class="preview-found-form-box preview-found-form-unavailable">
                <p class="muted">
                  Für diese Abteilung ist aktuell keine Kontaktmöglichkeit für Nachrichten aktiv.
                </p>
              </div>
            </div>
          </div>
          <p v-else class="muted">Kontaktbereich ist deaktiviert.</p>
        </div>
      </div>

      <button class="btn" :disabled="isSavingPublicSettings || isSaveDisabled" @click="savePublicSettings">
        {{ isSavingPublicSettings ? t('settings.publicMaterialPage.saving') : t('settings.publicMaterialPage.save') }}
      </button>
      <p v-if="isSaveDisabled && !isSavingPublicSettings" class="hint-text">
        {{ t('settings.publicMaterialPage.saveDisabledReason') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useDepartmentSettingsManagerAccess } from '@/composables/useDepartmentSettingsManagerAccess'
import { getPublicSharingSettings, savePublicSharingSettings, type PublicFoundContactDelivery } from '@/api/departmentSettings'
import { getAddresses } from '@/api/addresses'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const { t } = useI18n()

const selectedDepartmentId = ref<string | null>(null)
const isSavingPublicSettings = ref(false)
const publicContactEmail = ref('')
const publicContactNote = ref('')
const publicShowContactForm = ref(true)
const publicShowContactEmail = ref(true)
const publicShowContactNote = ref(true)
const publicFoundContactDelivery = ref<PublicFoundContactDelivery>('both')
const departmentEmailCandidate = ref('')

const {
  userDepartments,
  effectiveDepartmentId,
  canManageDepartmentSensitiveSettings,
} = useDepartmentSettingsManagerAccess(selectedDepartmentId)
const previewCanDeliverMessage = computed(() => {
  if (!publicShowContactForm.value) return false
  if (publicFoundContactDelivery.value === 'both') return true
  if (publicFoundContactDelivery.value === 'email') return publicShowContactEmail.value && !!publicContactEmail.value.trim()
  return true
})
const hasContactEmail = computed(() => !!publicContactEmail.value.trim())
const showContactEmailInvalid = computed(() => publicShowContactEmail.value && !publicContactEmail.value.trim())
const isSaveDisabled = computed(() => showContactEmailInvalid.value || !hasContactEmail.value)

async function loadDepartmentEmailCandidate(deptId: string) {
  departmentEmailCandidate.value = ''
  try {
    const result = await getAddresses(deptId)
    const withEmail = result.addresses.filter((a) => !!a.email?.trim())
    const primaryBilling = withEmail.find((a) => a.type === 'billing' && a.is_primary)
    const anyBilling = withEmail.find((a) => a.type === 'billing')
    const primaryAny = withEmail.find((a) => a.is_primary)
    departmentEmailCandidate.value = (
      primaryBilling?.email ||
      anyBilling?.email ||
      primaryAny?.email ||
      withEmail[0]?.email ||
      ''
    ).trim()
  } catch {
    departmentEmailCandidate.value = ''
  }
}

function applyDepartmentEmail() {
  if (!departmentEmailCandidate.value) return
  publicContactEmail.value = departmentEmailCandidate.value
}

async function loadPublicSettings(deptId: string) {
  try {
    const settings = await getPublicSharingSettings(deptId)
    publicContactEmail.value = settings.publicContactEmail
    publicContactNote.value = settings.publicContactNote
    publicShowContactForm.value = settings.publicShowContactForm
    publicShowContactEmail.value = settings.publicShowContactEmail
    publicShowContactNote.value = settings.publicShowContactNote
    publicFoundContactDelivery.value = settings.publicFoundContactDelivery
    await loadDepartmentEmailCandidate(deptId)
    if (!publicContactEmail.value.trim() && departmentEmailCandidate.value) {
      publicContactEmail.value = departmentEmailCandidate.value
    }
  } catch {
    publicContactEmail.value = ''
    publicContactNote.value = ''
    publicShowContactForm.value = true
    publicShowContactEmail.value = true
    publicShowContactNote.value = true
    publicFoundContactDelivery.value = 'both'
    await loadDepartmentEmailCandidate(deptId)
    if (departmentEmailCandidate.value) {
      publicContactEmail.value = departmentEmailCandidate.value
    }
  }
}

async function savePublicSettings() {
  const deptId = effectiveDepartmentId.value
  if (!deptId || isSavingPublicSettings.value) return
  if (showContactEmailInvalid.value) {
    toast.error(t('settings.publicMaterialPage.contactEmailRequiredWhenVisible'))
    return
  }
  isSavingPublicSettings.value = true
  try {
    await savePublicSharingSettings(deptId, {
      publicContactEmail: publicContactEmail.value.trim(),
      publicContactNote: publicContactNote.value.trim(),
      publicShowContactForm: publicShowContactForm.value,
      publicShowContactEmail: publicShowContactEmail.value,
      publicShowContactNote: publicShowContactNote.value,
      publicFoundContactDelivery: publicFoundContactDelivery.value,
    })
    toast.success(t('settings.publicMaterialPage.saveSuccess'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.publicMaterialPage.saveError'))
  } finally {
    isSavingPublicSettings.value = false
  }
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
  await loadPublicSettings(newDeptId)
}

watch(
  [effectiveDepartmentId, () => authStore.departments?.length ?? 0],
  async ([deptId]) => {
    if (!deptId) return
    if (selectedDepartmentId.value !== deptId) {
      selectedDepartmentId.value = deptId
    }
    await loadPublicSettings(deptId)
  },
  { immediate: true }
)

watch(
  () => publicContactEmail.value,
  (value) => {
    if (!value.trim()) {
      publicShowContactEmail.value = false
    }
  }
)
</script>

<style scoped>
.settings-page { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description, .muted { color: #6b7280; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.label { display: block; margin-bottom: 8px; font-size: 13px; color: #6b7280; }
.label-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.link-btn { border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; border-radius: 8px; padding: 6px 10px; font-size: 12px; font-weight: 600; cursor: pointer; }
.link-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.field { margin-bottom: 12px; }
.validation-error { margin: -6px 0 12px; color: #dc2626; font-size: 13px; }
.hint-text { margin: -4px 0 12px; color: #64748b; font-size: 13px; }
.input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; background: #fff; font-size: 14px; }
.toggles { display: flex; flex-direction: column; gap: 8px; }
.preview-card { margin-top: 16px; margin-bottom: 14px; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #ffffff; }
.preview-card h3 { margin: 0 0 4px; font-size: 15px; color: #1f2937; }
.preview-hint { margin: 0 0 10px; font-size: 13px; color: #6b7280; }
.preview-public-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; background: #f8fafc; }
.preview-code { margin: 0 0 8px; font-size: 12px; color: #6b7280; font-weight: 600; }
.preview-material-name { margin: 0; font-size: 20px; color: #111827; }
.preview-material-desc { margin: 8px 0 14px; color: #374151; }
.preview-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.preview-info-grid dt { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 3px; }
.preview-info-grid dd { margin: 0; color: #111827; font-weight: 500; }
.preview-contact-collapsible { border-top: 1px solid #e5e7eb; padding-top: 12px; }
.preview-contact-toggle { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #ffffff; color: #111827; font-weight: 600; }
.preview-contact-toggle-label { text-align: left; }
.preview-contact-panel { margin-top: 10px; }
.preview-contact-box { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
.preview-contact-box h5 { margin: 0 0 6px; font-size: 14px; }
.preview-contact-box p { margin: 4px 0; color: #374151; }
.preview-contact-note { color: #4b5563; }
.preview-found-form-box { margin-top: 10px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
.preview-found-form-title { margin: 0; font-size: 15px; }
.preview-found-form-hint { margin: 6px 0 10px; font-size: 13px; color: #4b5563; }
.preview-found-form { display: flex; flex-direction: column; gap: 10px; }
.preview-found-label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #374151; }
.preview-found-label input, .preview-found-label textarea { border: 1px solid #d1d5db; border-radius: 8px; padding: 9px 10px; background: #f9fafb; color: #6b7280; }
.preview-found-submit { border: none; border-radius: 8px; background: #cbd5e1; color: #334155; padding: 10px 12px; font-weight: 600; }
.preview-found-form-unavailable { color: #6b7280; }
.btn { border: none; border-radius: 8px; background: #16a34a; color: #fff; padding: 10px 14px; cursor: pointer; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
