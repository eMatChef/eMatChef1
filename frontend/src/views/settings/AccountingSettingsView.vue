<template>
  <div class="accounting-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.accountingSettings.title') }}</h1>
        <p class="subtitle">{{ t('settings.accountingSettings.subtitle') }}</p>
      </div>
    </div>

    <ELoadingState v-if="isLoading" variant="page" :message="t('settings.accountingSettings.loading')" />

    <div v-else class="settings-form">
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon timing">
            <v-icon icon="mdi-cash-clock" size="20" />
          </div>
          <div>
            <h3>{{ t('settings.accountingSettings.sections.timing.title') }}</h3>
            <p>{{ t('settings.accountingSettings.sections.timing.description') }}</p>
          </div>
        </div>
        <div class="setting-fields">
          <ESelect
            v-model="form.settlementTimingConsumable"
            :items="timingItems"
            :label="t('settings.accountingSettings.fields.timingConsumable')"
            :hint="t('settings.accountingSettings.hints.timingConsumable')"
            hide-details="auto"
          />
          <ESelect
            v-model="form.settlementTimingExternal"
            :items="timingItems"
            :label="t('settings.accountingSettings.fields.timingExternal')"
            :hint="t('settings.accountingSettings.hints.timingExternal')"
            hide-details="auto"
          />
        </div>
      </div>

      <div class="settings-actions">
        <EButton variant="primary" :disabled="!hasChanges || isSaving" :loading="isSaving" @click="save">
          {{ t('common.save') }}
        </EButton>
        <EButton variant="secondary" :disabled="!hasChanges || isSaving" @click="reset">
          {{ t('common.cancel') }}
        </EButton>
      </div>
      <p v-if="saveError" class="text-error">{{ saveError }}</p>
      <p v-else-if="saveOk" class="text-success">{{ t('settings.accountingSettings.saved') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { EButton, ESelect } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  DEFAULT_ACCOUNTING_SETTINGS,
  getAccountingSettings,
  saveAccountingSettings,
  type AccountingSettings,
  type AccountingSettlementTiming,
} from '@/api/departmentSettings'

defineOptions({ name: 'AccountingSettingsView' })

const { t } = useI18n()
const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))

const isLoading = ref(true)
const isSaving = ref(false)
const saveError = ref('')
const saveOk = ref(false)
const savedForm = ref<AccountingSettings | null>(null)

const form = reactive<AccountingSettings>({
  ...DEFAULT_ACCOUNTING_SETTINGS,
})

const timingItems = computed(() => [
  {
    title: t('settings.accountingSettings.timingValues.accountingOnly'),
    value: 'accounting_only' satisfies AccountingSettlementTiming,
  },
  {
    title: t('settings.accountingSettings.timingValues.offerAtActivity'),
    value: 'offer_at_activity' satisfies AccountingSettlementTiming,
  },
])

const hasChanges = computed(() => {
  if (!savedForm.value) return false
  return JSON.stringify(form) !== JSON.stringify(savedForm.value)
})

async function loadSettings() {
  isLoading.value = true
  saveError.value = ''
  saveOk.value = false
  try {
    const settings = await getAccountingSettings(departmentId.value)
    Object.assign(form, settings)
    savedForm.value = { ...settings }
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    saveError.value = ax.response?.data?.error || t('settings.accountingSettings.loadError')
  } finally {
    isLoading.value = false
  }
}

function reset() {
  if (!savedForm.value) return
  Object.assign(form, savedForm.value)
  saveOk.value = false
  saveError.value = ''
}

async function save() {
  isSaving.value = true
  saveError.value = ''
  saveOk.value = false
  try {
    await saveAccountingSettings(departmentId.value, { ...form })
    savedForm.value = { ...form }
    saveOk.value = true
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    saveError.value = ax.response?.data?.error || t('settings.accountingSettings.saveError')
  } finally {
    isSaving.value = false
  }
}

watch(departmentId, () => {
  void loadSettings()
})

onMounted(() => {
  void loadSettings()
})
</script>

<style scoped>
.accounting-settings {
  max-width: 40rem;
}

.settings-header {
  margin-bottom: 1.5rem;
}

.settings-header h1 {
  margin: 0 0 0.35rem;
  font-size: 1.5rem;
}

.subtitle {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
}

.settings-section {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem 1.1rem;
  margin-bottom: 1.25rem;
  background: #fff;
}

.section-header {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.section-header h3 {
  margin: 0 0 0.25rem;
  font-size: 1rem;
}

.section-header p {
  margin: 0;
  font-size: 0.85rem;
  color: #64748b;
}

.section-icon {
  flex: 0 0 auto;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: #ecfdf5;
  color: #047857;
}

.setting-fields {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.settings-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.text-error {
  color: #b91c1c;
  margin-top: 0.75rem;
}

.text-success {
  color: #15803d;
  margin-top: 0.75rem;
}
</style>
