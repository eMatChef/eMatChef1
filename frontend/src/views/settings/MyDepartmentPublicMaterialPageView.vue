<template>
  <div class="public-material-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.publicMaterialPage.title') }}</h2>
        <p class="settings-description">{{ t('settings.publicMaterialPage.description') }}</p>
      </div>
    </div>

    <ECard v-if="userDepartments.length > 1" class="department-card" variant="outlined">
      <div class="department-card__body">
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :items="departmentSelectItems"
        :label="t('settings.common.selectDepartment')"
        item-title="title"
        item-value="value"
        hide-details
        @update:model-value="onDepartmentChange"
      />
      </div>
    </ECard>

    <ECard v-if="!canManageDepartmentSensitiveSettings" variant="outlined">
      <div class="card-body">
        <p class="muted">{{ t('settings.publicMaterialPage.noPermission') }}</p>
      </div>
    </ECard>

    <ECard v-else variant="outlined" class="settings-form-card">
      <div class="settings-form-fields">
        <section class="form-section">
          <ETextField
            id="public-contact-email"
            v-model="publicContactEmail"
            type="email"
            :label="t('settings.publicMaterialPage.publicContactEmailLabel')"
            :placeholder="t('settings.publicMaterialPage.publicContactEmailPlaceholder')"
            hide-details
          />
          <EButton
            variant="secondary"
            size="small"
            class="email-prefill-btn"
            :disabled="!departmentEmailCandidate"
            @click="applyDepartmentEmail"
          >
            {{ t('settings.publicMaterialPage.useDepartmentEmail') }}
          </EButton>
        </section>

        <ETextarea
          id="public-contact-note"
          v-model="publicContactNote"
          :label="t('settings.publicMaterialPage.publicContactNoteLabel')"
          :placeholder="t('settings.publicMaterialPage.publicContactNotePlaceholder')"
          rows="3"
          hide-details
        />

        <section class="form-section form-section--toggles">
          <ECheckbox
            v-model="publicShowContactForm"
            :label="t('settings.publicMaterialPage.showContactForm')"
            hide-details
          />
          <ECheckbox
            v-model="publicShowContactEmail"
            :label="t('settings.publicMaterialPage.showContactEmail')"
            :disabled="!hasContactEmail"
            hide-details
          />
          <ECheckbox
            v-model="publicShowContactNote"
            :label="t('settings.publicMaterialPage.showContactNote')"
            hide-details
          />
        </section>

        <v-alert
          v-if="showContactEmailInvalid"
          type="error"
          variant="tonal"
          density="compact"
          :text="t('settings.publicMaterialPage.contactEmailRequiredWhenVisible')"
        />
        <p v-if="!hasContactEmail" class="hint-text">
          {{ t('settings.publicMaterialPage.contactEmailToggleDisabledHint') }}
        </p>

        <ESelect
          id="public-found-delivery"
          v-model="publicFoundContactDelivery"
          :label="t('settings.publicMaterialPage.deliveryLabel')"
          :items="deliverySelectItems"
          item-title="title"
          item-value="value"
          hide-details
        />

        <div class="preview-card">
        <h3 class="preview-card__title">{{ t('settings.publicMaterialPage.previewTitle') }}</h3>
        <p class="preview-hint">{{ t('settings.publicMaterialPage.previewHint') }}</p>
        <div class="preview-public-card">
          <p class="preview-code">
            {{ t('settings.publicMaterialPage.previewCodeLine', { code: t('settings.publicMaterialPage.previewSampleCode') }) }}
          </p>
          <h4 class="preview-material-name">{{ t('settings.publicMaterialPage.previewSampleMaterialTitle') }}</h4>
          <p class="preview-material-desc">{{ t('settings.publicMaterialPage.previewSampleMaterialDesc') }}</p>

          <div class="preview-info-grid">
            <div>
              <dt>{{ t('settings.publicMaterialPage.previewLabelDepartment') }}</dt>
              <dd>{{ selectedDepartmentName }}</dd>
            </div>
            <div>
              <dt>{{ t('common.manufacturer') }}</dt>
              <dd>{{ t('settings.publicMaterialPage.previewSampleManufacturer') }}</dd>
            </div>
          </div>

          <div v-if="publicShowContactForm" class="preview-contact-collapsible">
            <button type="button" class="preview-contact-toggle" disabled>
              <span class="preview-contact-toggle-label">{{ t('settings.publicMaterialPage.previewContactMaterialKeeper') }}</span>
              <v-icon icon="mdi-chevron-down" size="20" />
            </button>
            <div class="preview-contact-panel">
              <div v-if="publicShowContactEmail || publicShowContactNote" class="preview-contact-box">
                <h5>{{ t('settings.publicMaterialPage.previewContactHeading') }}</h5>
                <p v-if="publicShowContactEmail">
                  {{ t('settings.publicMaterialPage.previewEmailLine', { email: publicContactEmail || '-' }) }}
                </p>
                <p v-if="publicShowContactNote" class="preview-contact-note">{{ publicContactNote || '-' }}</p>
              </div>

              <div v-if="previewCanDeliverMessage" class="preview-found-form-box">
                <h5 class="preview-found-form-title">{{ t('settings.publicMaterialPage.previewSendMessageTitle') }}</h5>
                <p class="preview-found-form-hint">{{ t('settings.publicMaterialPage.previewSendMessageHint') }}</p>
                <div class="preview-found-form">
                  <ETextField
                    :label="`${t('settings.publicMaterialPage.previewYourName')} (${t('settings.publicMaterialPage.previewOptionalParen')})`"
                    :placeholder="t('settings.publicMaterialPage.previewNamePlaceholder')"
                    disabled
                    hide-details
                  />
                  <ETextField
                    type="email"
                    :label="`${t('settings.publicMaterialPage.previewYourEmail')} (${t('settings.publicMaterialPage.previewOptionalRepliesParen')})`"
                    :placeholder="t('settings.publicMaterialPage.previewEmailPlaceholder')"
                    disabled
                    hide-details
                  />
                  <ETextarea
                    :label="`${t('settings.publicMaterialPage.previewMessageLabel')} ${t('settings.publicMaterialPage.previewRequiredStar')}`"
                    :placeholder="t('settings.publicMaterialPage.previewMessagePlaceholder')"
                    rows="4"
                    disabled
                    hide-details
                  />
                  <EButton variant="secondary" size="small" disabled>
                    {{ t('settings.publicMaterialPage.previewSubmitToKeeper') }}
                  </EButton>
                </div>
              </div>
              <div v-else class="preview-found-form-box preview-found-form-unavailable">
                <p class="muted">{{ t('settings.publicMaterialPage.previewNoMessageDelivery') }}</p>
              </div>
            </div>
          </div>
          <p v-else class="muted">{{ t('settings.publicMaterialPage.previewContactSectionOff') }}</p>
        </div>
      </div>

        <div class="save-row">
          <EButton variant="primary" :disabled="isSaveDisabled" :loading="isSavingPublicSettings" @click="savePublicSettings">
            {{ isSavingPublicSettings ? t('common.saving') : t('common.save') }}
          </EButton>
          <p v-if="isSaveDisabled && !isSavingPublicSettings" class="hint-text save-hint">
            {{ t('settings.publicMaterialPage.saveDisabledReason') }}
          </p>
        </div>
      </div>
    </ECard>
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
import { EButton, ECard, ECheckbox, ESelect, ETextField, ETextarea } from '@/components/form/base'

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

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => ({
    title: dept.department?.name || dept.department_id,
    value: dept.department_id,
  })),
)

const deliverySelectItems = computed(() => [
  { title: t('settings.publicMaterialPage.deliveryEmail'), value: 'email' as const },
  { title: t('settings.publicMaterialPage.deliveryInApp'), value: 'in_app' as const },
  { title: t('settings.publicMaterialPage.deliveryBoth'), value: 'both' as const },
])

const selectedDepartmentName = computed(
  () => userDepartments.value.find((d) => d.department_id === selectedDepartmentId.value)?.department?.name || '-',
)

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
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'response' in err
        ? (err as { response?: { data?: { error?: string } } }).response?.data?.error
        : undefined
    toast.error(msg || t('settings.publicMaterialPage.saveError'))
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
  { immediate: true },
)

watch(
  () => publicContactEmail.value,
  (value) => {
    if (!value.trim()) {
      publicShowContactEmail.value = false
    }
  },
)
</script>

<style scoped>
.public-material-settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.settings-title {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.settings-description,
.muted {
  color: var(--color-text-muted, #6b7280);
  margin: 0;
}

.settings-description {
  margin-top: 4px;
  font-size: 14px;
  line-height: 1.4;
}

.department-card {
  max-width: 480px;
}

.public-material-settings :deep(.e-card.v-card) {
  overflow: visible;
}

.card-body,
.department-card__body,
.settings-form-fields {
  box-sizing: border-box;
  padding: 20px 24px 24px;
  overflow: visible;
}

.department-card__body :deep(.e-form-field.autosave-field),
.settings-form-fields :deep(.e-form-field.autosave-field) {
  margin-bottom: 0;
}

.settings-form-fields {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-section--toggles {
  gap: 10px;
}

.email-prefill-btn {
  align-self: flex-start;
}

.hint-text {
  margin: -12px 0 0;
  color: var(--color-text-muted, #64748b);
  font-size: 13px;
  line-height: 1.4;
}

.preview-card {
  padding: 16px;
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 10px;
  background: #fff;
}

.preview-card__title {
  margin: 0 0 4px;
  font-size: 15px;
  color: var(--color-text, #1f2937);
}

.preview-hint {
  margin: 0 0 10px;
  font-size: 13px;
  color: var(--color-text-muted, #6b7280);
}

.preview-public-card {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 14px;
  background: var(--color-surface-muted, #f8fafc);
}

.preview-code {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
  font-weight: 600;
}

.preview-material-name {
  margin: 0;
  font-size: 20px;
  color: var(--color-text, #111827);
}

.preview-material-desc {
  margin: 8px 0 14px;
  color: var(--color-text, #374151);
}

.preview-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}

.preview-info-grid dt {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
  text-transform: uppercase;
  margin-bottom: 3px;
}

.preview-info-grid dd {
  margin: 0;
  color: var(--color-text, #111827);
  font-weight: 500;
}

.preview-contact-collapsible {
  border-top: 1px solid var(--color-border, #e5e7eb);
  padding-top: 12px;
}

.preview-contact-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 10px;
  background: #fff;
  color: var(--color-text, #111827);
  font-weight: 600;
}

.preview-contact-toggle-label {
  text-align: left;
}

.preview-contact-panel {
  margin-top: 10px;
}

.preview-contact-box {
  background: #fff;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 12px;
}

.preview-contact-box h5 {
  margin: 0 0 6px;
  font-size: 14px;
}

.preview-contact-box p {
  margin: 4px 0;
  color: var(--color-text, #374151);
}

.preview-contact-note {
  color: var(--color-text-muted, #4b5563);
  white-space: pre-wrap;
}

.preview-found-form-box {
  margin-top: 10px;
  background: #fff;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 12px;
}

.preview-found-form-title {
  margin: 0;
  font-size: 15px;
}

.preview-found-form-hint {
  margin: 6px 0 10px;
  font-size: 13px;
  color: var(--color-text-muted, #4b5563);
}

.preview-found-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.preview-found-form :deep(.e-form-field.autosave-field) {
  margin-bottom: 0;
}

.preview-found-form-unavailable {
  color: var(--color-text-muted, #6b7280);
}

.save-row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding-top: 4px;
  border-top: 1px solid var(--color-border, #e5e7eb);
}

.save-hint {
  margin: 0;
}
</style>
