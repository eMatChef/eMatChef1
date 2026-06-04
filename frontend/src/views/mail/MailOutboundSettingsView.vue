<template>
  <div class="mail-outbound">
    <h2 class="section-title">{{ t('mail.outbound.title') }}</h2>
    <p class="hint">
      {{ t('mail.outbound.hintIntro') }} <strong>MAILER_DSN</strong> {{ t('mail.outbound.hintOutro') }}
    </p>

    <div v-if="settings && !isLoading" class="transport-box">
      <span class="transport-label">{{ t('mail.outbound.currentTransport') }}</span>
      {{ transportModeLabel(settings.mailer_transport_mode) }}
    </div>

    <div v-if="settings && !isLoading && settings.mailer_transport_mode === 'env_missing'" class="env-missing-notice">
      <strong>{{ t('mail.outbound.mailerMissingTitle') }}</strong> {{ t('mail.outbound.mailerMissingBody') }}
    </div>

    <ELoadingState v-if="isLoading" variant="page" :message="t('mail.outbound.loading')" />
    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
    </div>

    <form v-else class="form" @submit.prevent="save">
      <ETextField
        id="from-address"
        v-model="form.from_address"
        :label="t('mail.outbound.fromAddress')"
        type="email"
        required
        autocomplete="off"
        :disabled="!canEdit"
        hide-details="auto"
      />
      <ETextField
        id="from-name"
        v-model="form.from_name"
        :label="t('mail.outbound.fromName')"
        maxlength="120"
        :placeholder="t('mail.outbound.fromNamePlaceholder')"
        :disabled="!canEdit"
        hide-details="auto"
      />
      <p v-if="settings" class="meta">
        {{ t('mail.outbound.envFallback') }} <code>{{ settings.env_fallback_address }}</code>
        <span v-if="settings.uses_file" class="badge">{{ t('mail.outbound.jsonActive') }}</span>
      </p>

      <h3 id="reply-to-section" class="sub-title">{{ t('mail.outbound.replyToTitle') }}</h3>
      <p class="hint">
        {{ t('mail.outbound.replyToHintIntro') }} <code>MAILER_REPLY_TO</code> {{ t('mail.outbound.replyToHintOutro') }}
      </p>

      <div v-if="settings?.mailer_reply_to_env" class="env-readonly-block">
        <span class="env-readonly-label">{{ t('mail.outbound.replyToEnvActive') }}</span>
        <code class="env-readonly-value">{{ settings.mailer_reply_to_env }}</code>
        <p class="env-readonly-hint">
          {{ t('mail.outbound.replyToEnvHint') }}
        </p>
      </div>
      <div v-else class="env-readonly-block env-readonly-block--muted">
        <span class="env-readonly-label">{{ t('mail.outbound.replyToEnvLabel') }}</span>
        <span class="env-readonly-empty">{{ t('mail.outbound.replyToEnvNotSet') }}</span>
      </div>

      <ETextField
        id="reply-to"
        v-model="form.reply_to_address"
        :label="t('mail.outbound.replyToJson')"
        type="email"
        autocomplete="off"
        :placeholder="t('mail.outbound.replyToPlaceholder')"
        :disabled="!canEdit || !!settings?.mailer_reply_to_env"
        hide-details="auto"
      />
      <p v-if="settings?.reply_to_effective" class="meta">
        <strong>{{ t('mail.outbound.replyToEffective') }}</strong> <code>{{ settings.reply_to_effective }}</code>
      </p>

      <v-alert v-if="!canEdit" type="warning" variant="tonal" class="mb-3" :text="t('mail.outbound.superadminOnly')" />

      <template v-if="canEdit">
        <h3 class="sub-title">{{ t('mail.outbound.testTitle') }}</h3>
        <p class="hint testmail-hint">
          {{ t('mail.outbound.testHintIntro') }} <strong>MAILER_DSN</strong> {{ t('mail.outbound.testHintOutro') }}
        </p>
        <ETextField
          id="test-to"
          v-model="testTo"
          :label="t('mail.outbound.testTarget')"
          type="email"
          autocomplete="off"
          :placeholder="t('mail.outbound.testPlaceholder')"
          hide-details="auto"
        />
        <div class="actions test-actions">
          <EButton variant="secondary" :loading="isTesting" @click="sendTest">
            {{ isTesting ? t('mail.outbound.sending') : t('mail.outbound.sendTest') }}
          </EButton>
        </div>
      </template>

      <div class="actions">
        <EButton v-if="canEdit" variant="primary" type="submit" :loading="isSaving">
          {{ isSaving ? t('common.saving') : t('common.save') }}
        </EButton>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  getMailSettings,
  patchMailSettings,
  postMailTestSend,
  type MailOutboundSettingsDto,
  type MailOutboundSettingsPatch,
  type MailerTransportMode,
} from '@/api/mailAdmin'
import { useToast } from '@/composables/useToast'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ETextField } from '@/components/form/base'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const { t } = useI18n()

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

const canEdit = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

const isLoading = ref(true)
const isSaving = ref(false)
const isTesting = ref(false)
const error = ref('')
const settings = ref<MailOutboundSettingsDto | null>(null)
const testTo = ref('')

const form = ref({
  from_address: '',
  from_name: '',
  reply_to_address: '',
})

function transportModeLabel(mode: MailerTransportMode): string {
  if (mode === 'env_missing') return t('mail.outbound.transportMissing')
  return t('mail.outbound.transportEnv')
}

function applySettings(data: MailOutboundSettingsDto) {
  settings.value = data
  form.value = {
    from_address: data.from_address,
    from_name: data.from_name || '',
    reply_to_address: data.reply_to_address || '',
  }
}

async function load() {
  isLoading.value = true
  error.value = ''
  try {
    const data = await getMailSettings(departmentId.value)
    applySettings(data)
  } catch (err: unknown) {
    error.value = apiErrorMessage(err, t('mail.outbound.loadError'))
    settings.value = null
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!canEdit.value) return
  isSaving.value = true
  try {
    const name = form.value.from_name.trim()
    const body: MailOutboundSettingsPatch = {
      from_address: form.value.from_address.trim(),
      from_name: name === '' ? null : name,
      reply_to_address: form.value.reply_to_address.trim(),
    }
    const data = await patchMailSettings(body, departmentId.value)
    applySettings(data)
    toast.success(t('mail.outbound.toastSaved'))
  } catch (err: any) {
    toast.error(apiErrorMessage(err, t('mail.outbound.toastSaveError')))
  } finally {
    isSaving.value = false
  }
}

async function sendTest() {
  const to = testTo.value.trim()
  if (!to) {
    toast.error(t('mail.outbound.testTargetRequired'))
    return
  }
  isTesting.value = true
  try {
    await postMailTestSend(to)
    toast.success(t('mail.outbound.testSent'))
  } catch (err: any) {
    toast.error(apiErrorMessage(err, t('mail.outbound.testFailed')))
  } finally {
    isTesting.value = false
  }
}

onMounted(() => {
  load()
})

watch(departmentId, () => {
  load()
})
</script>

<style scoped>
.mail-outbound {
  max-width: 640px;
}

.section-title {
  margin: 0 0 8px 0;
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.sub-title {
  margin: 22px 0 8px 0;
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.hint {
  margin: 0 0 20px 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

.hint code {
  font-size: 12px;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}

.state {
  padding: 16px;
  color: #64748b;
}

.state.error {
  color: #b91c1c;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row label {
  font-size: 13px;
  font-weight: 500;
  color: #334155;
}

.input {
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
}

.input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.input:disabled {
  background: #f8fafc;
  color: #64748b;
}

.meta {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.meta code {
  font-size: 12px;
}

.badge {
  margin-left: 8px;
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 11px;
  font-weight: 600;
}

.notice {
  padding: 12px 14px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 13px;
  color: #92400e;
}

.actions {
  display: flex;
  gap: 10px;
}

.btn {
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: #2563eb;
  color: white;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #e2e8f0;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.transport-box {
  margin: 0 0 20px 0;
  padding: 12px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
}

.transport-label {
  font-weight: 600;
  margin-right: 6px;
}

.env-missing-notice {
  margin: 0 0 20px 0;
  padding: 12px 14px;
  background: #fff7ed;
  border: 1px solid #fdba74;
  border-left: 4px solid #ea580c;
  border-radius: 8px;
  font-size: 13px;
  color: #9a3412;
  line-height: 1.55;
}

.env-readonly-block {
  margin: 0 0 14px 0;
  padding: 10px 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.env-readonly-block--muted {
  background: #fafafa;
  color: #64748b;
}

.env-readonly-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}

.env-readonly-value {
  display: block;
  font-size: 14px;
  padding: 6px 8px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  word-break: break-all;
}

.env-readonly-empty {
  font-size: 13px;
  line-height: 1.45;
}

.env-readonly-hint {
  margin: 8px 0 0 0;
  font-size: 12px;
  color: #64748b;
  line-height: 1.45;
}

.testmail-hint {
  margin-bottom: 10px;
}

.test-actions {
  margin-bottom: 8px;
}
</style>
