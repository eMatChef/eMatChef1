<template>
  <div class="integrations-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.integrations.title') }}</h1>
        <p class="subtitle">{{ t('settings.integrations.subtitle') }}</p>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="page"
      :message="t('settings.integrations.loading')"
    />

    <div v-else class="settings-form">
      <section class="settings-section">
        <div class="section-header">
          <div class="section-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path d="M3 10h18" />
            </svg>
          </div>
          <div>
            <h3>{{ t('settings.integrations.fcalTitle') }}</h3>
            <p>
              {{ t('settings.integrations.fcalDescriptionBefore') }}
              <strong>{{ t('settings.integrations.activitiesStrong') }}</strong> {{ t('settings.integrations.fcalDescriptionMiddle') }} <strong>{{ t('settings.integrations.geoIdStrong') }}</strong> {{ t('settings.integrations.fcalDescriptionAfter') }}
              <a href="https://feiertagskalender.ch/api/openapi.php?hl=de" target="_blank" rel="noopener noreferrer"
                >{{ t('settings.integrations.apiDocumentation') }}</a
              >{{ t('settings.integrations.dot') }}
            </p>
          </div>
        </div>
        <div class="setting-fields">
          <div class="field-group field-group--wide">
            <ETextField
              id="fcal-api-key"
              v-model="fcalApiKeyInput"
              :label="t('settings.integrations.apiKeyLabel')"
              type="password"
              autocomplete="off"
              :placeholder="t('settings.integrations.apiKeyPlaceholder')"
              hide-details="auto"
            />
            <span class="field-hint">
              <template v-if="status?.fcalApiKeyConfigured">{{ t('settings.integrations.keyStored') }}</template>
              <template v-else>{{ t('settings.integrations.keyMissing') }}</template>
              {{ t('settings.integrations.keyHint') }}
            </span>
            <EButton
              v-if="status?.fcalApiKeyConfigured"
              variant="text"
              size="small"
              class="btn-remove-key"
              :disabled="isSaving"
              @click="removeKey"
            >
              {{ t('settings.integrations.removeStoredKey') }}
            </EButton>
          </div>
        </div>
      </section>

      <div class="save-bar">
        <div v-if="dirty" class="unsaved-hint">{{ t('settings.integrations.unsavedChanges') }}</div>
        <div class="save-actions">
          <EButton variant="secondary" :disabled="!dirty || isSaving" @click="resetInput">{{ t('settings.integrations.reset') }}</EButton>
          <EButton variant="primary" :loading="isSaving" :disabled="!dirty || isSaving" @click="save">
            {{ isSaving ? t('settings.integrations.saving') : t('common.save') }}
          </EButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getFcalIntegration, saveFcalIntegration, type FcalIntegrationStatus } from '@/api/adminIntegrations'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ETextField } from '@/components/form/base'

const toast = useToast()
const { t } = useI18n()
const isLoading = ref(true)
const isSaving = ref(false)
const status = ref<FcalIntegrationStatus | null>(null)
const fcalApiKeyInput = ref('')
const initialSnapshot = ref('')

const dirty = computed(() =>
  fcalApiKeyInput.value !== initialSnapshot.value
)

async function load() {
  isLoading.value = true
  try {
    status.value = await getFcalIntegration()
    initialSnapshot.value = ''
    fcalApiKeyInput.value = ''
  } catch (e) {
    console.error(e)
    toast.error(t('settings.integrations.toastLoadError'))
  } finally {
    isLoading.value = false
  }
}

function resetInput() {
  fcalApiKeyInput.value = initialSnapshot.value
}

async function save() {
  isSaving.value = true
  try {
    const current = status.value
    if (!current) {
      throw new Error('Integration status not loaded')
    }
    status.value = await saveFcalIntegration(
      fcalApiKeyInput.value,
      current.authSessionLimitPerMinute,
      current.authRefreshLimitPerMinute,
      current.autologout
    )
    initialSnapshot.value = fcalApiKeyInput.value
    fcalApiKeyInput.value = ''
    toast.success(t('settings.integrations.toastSaved'))
  } catch (e) {
    console.error(e)
    toast.error(t('settings.integrations.toastSaveError'))
  } finally {
    isSaving.value = false
  }
}

async function removeKey() {
  if (!confirm(t('settings.integrations.confirmDisableFcal'))) return
  isSaving.value = true
  try {
    status.value = await saveFcalIntegration(
      '',
      status.value?.authSessionLimitPerMinute ?? 120,
      status.value?.authRefreshLimitPerMinute ?? 30,
      status.value?.autologout ?? {
        timeoutMs: 3600000,
        warningMs: 300000,
        activityThrottleMs: 5000,
        refreshIntervalMs: 1500000,
        activityEvents: 'click,keydown,scroll',
      }
    )
    initialSnapshot.value = ''
    fcalApiKeyInput.value = ''
    toast.success(t('settings.integrations.toastRemoved'))
  } catch (e) {
    console.error(e)
    toast.error(t('settings.integrations.toastRemoveError'))
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.integrations-settings {
  min-height: 400px;
}

.settings-header {
  margin-bottom: 28px;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.loading-state {
  display: none;
}

.settings-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 20px;
}

.section-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  flex-shrink: 0;
  background: #e0e7ff;
  color: #4338ca;
}

.section-header h3 {
  margin: 0 0 6px 0;
  font-size: 16px;
  font-weight: 600;
}

.section-header p {
  margin: 0;
  font-size: 14px;
  color: #4b5563;
  line-height: 1.5;
}

.section-header a {
  color: var(--emc-brand-accent, #059669);
}

.field-group--wide {
  max-width: 480px;
}

.field-hint {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #6b7280;
}

.btn-remove-key {
  display: block;
  margin-top: 12px;
  color: #b91c1c;
}

.save-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 8px;
}

.unsaved-hint {
  font-size: 13px;
  color: #b45309;
}

.save-actions {
  display: flex;
  gap: 10px;
}
</style>
