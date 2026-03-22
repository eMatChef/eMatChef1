<template>
  <div class="mail-outbound">
    <h2 class="section-title">Absender-E-Mail</h2>
    <p class="hint">
      Absender und optional SMTP werden in <code>var/app/mail_outbound.json</code> gespeichert (nicht in der Datenbank).
      Ist dort kein vollständiger SMTP-Eintrag aktiv, gilt <strong>MAILER_DSN</strong> aus der Umgebung — fehlt oder ist es
      <code>null://</code>, werden ausgehende Nachrichten als <code>.eml</code> im <strong>lokalen Spool-Ordner</strong> abgelegt
      (kein SMTP).
    </p>

    <div v-if="settings && !isLoading" class="transport-box">
      <span class="transport-label">Aktueller Versandweg:</span>
      {{ transportModeLabel(settings.mailer_transport_mode) }}
      <template v-if="settings.uses_file_spool && settings.mail_spool_path">
        <span class="transport-path">
          Ordner: <code>{{ settings.mail_spool_path }}</code>
        </span>
      </template>
    </div>

    <div v-if="isLoading" class="state">Lade Einstellungen…</div>
    <div v-else-if="error" class="state error">{{ error }}</div>

    <form v-else class="form" @submit.prevent="save">
      <div class="form-row">
        <label for="from-address">Absender-E-Mail</label>
        <input
          id="from-address"
          v-model="form.from_address"
          type="email"
          class="input"
          required
          autocomplete="off"
          :disabled="!canEdit"
        />
      </div>
      <div class="form-row">
        <label for="from-name">Absender-Name (optional)</label>
        <input
          id="from-name"
          v-model="form.from_name"
          type="text"
          class="input"
          maxlength="120"
          placeholder="z. B. eMatChef"
          :disabled="!canEdit"
        />
      </div>
      <p v-if="settings" class="meta">
        Fallback aus Umgebung (MAILER_FROM): <code>{{ settings.env_fallback_address }}</code>
        <span v-if="settings.uses_file" class="badge">JSON aktiv</span>
      </p>

      <h3 class="sub-title">SMTP-Versand (optional)</h3>
      <p class="hint smtp-hint">
        <label class="check-row">
          <input v-model="form.use_custom_smtp" type="checkbox" :disabled="!canEdit" />
          <span>Eigene SMTP-Zugangsdaten verwenden (statt MAILER_DSN)</span>
        </label>
      </p>
      <p class="warn">
        Passwort wird im JSON-Datei-Klartext gespeichert — Datei auf dem Server schützen (Berechtigungen, Backup).
      </p>

      <template v-if="form.use_custom_smtp">
        <div class="form-row">
          <label for="smtp-host">SMTP-Server</label>
          <input
            id="smtp-host"
            v-model="form.smtp_host"
            type="text"
            class="input"
            placeholder="smtp.example.com"
            :disabled="!canEdit"
          />
        </div>
        <div class="form-row">
          <label for="smtp-port">Port</label>
          <input
            id="smtp-port"
            v-model.number="form.smtp_port"
            type="number"
            min="1"
            max="65535"
            class="input input-narrow"
            placeholder="587"
            :disabled="!canEdit"
          />
        </div>
        <div class="form-row">
          <label for="smtp-enc">Verschlüsselung</label>
          <select id="smtp-enc" v-model="form.smtp_encryption" class="input" :disabled="!canEdit">
            <option value="tls">STARTTLS (TLS), typisch Port 587</option>
            <option value="ssl">SSL/TLS (SMTPS), typisch Port 465</option>
            <option value="none">Keine (nur in vertrauenswürdigen Netzen)</option>
          </select>
        </div>
        <div class="form-row">
          <label for="smtp-user">Benutzername</label>
          <input
            id="smtp-user"
            v-model="form.smtp_user"
            type="text"
            class="input"
            autocomplete="off"
            :disabled="!canEdit"
          />
        </div>
        <div class="form-row">
          <label for="smtp-pass">Passwort</label>
          <input
            id="smtp-pass"
            v-model="form.smtp_password"
            type="password"
            class="input"
            autocomplete="new-password"
            :placeholder="settings?.smtp_password_set ? 'Leer lassen = Passwort beibehalten' : 'SMTP-Passwort'"
            :disabled="!canEdit"
          />
        </div>
      </template>

      <div v-if="!canEdit" class="notice">
        Nur Superadmin kann die E-Mail-Einstellungen ändern. Du kannst die aktuellen Werte einsehen.
      </div>

      <template v-if="canEdit">
        <h3 class="sub-title">Testmail</h3>
        <p class="hint testmail-hint">
          Sendet eine kurze Prüfmail über den oben angezeigten Versandweg (SMTP, Umgebung oder Datei-Spool).
        </p>
        <div class="form-row">
          <label for="test-to">Ziel-E-Mail</label>
          <input
            id="test-to"
            v-model="testTo"
            type="email"
            class="input"
            autocomplete="off"
            placeholder="deine@adresse.ch"
          />
        </div>
        <div class="actions test-actions">
          <button type="button" class="btn btn-secondary" :disabled="isTesting" @click="sendTest">
            {{ isTesting ? 'Senden…' : 'Testmail senden' }}
          </button>
        </div>
      </template>

      <div class="actions">
        <button v-if="canEdit" type="submit" class="btn btn-primary" :disabled="isSaving">
          {{ isSaving ? 'Speichern…' : 'Speichern' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
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

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

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
  use_custom_smtp: false,
  smtp_host: '',
  smtp_port: 587 as number | '',
  smtp_user: '',
  smtp_password: '',
  smtp_encryption: 'tls' as 'tls' | 'ssl' | 'none',
})

function transportModeLabel(mode: MailerTransportMode): string {
  switch (mode) {
    case 'smtp_json':
      return 'SMTP aus mail_outbound.json'
    case 'env':
      return 'MAILER_DSN (Server-Umgebung)'
    case 'file_spool':
      return 'Lokaler Datei-Spool (.eml, kein SMTP)'
    default:
      return mode
  }
}

function applySettings(data: MailOutboundSettingsDto) {
  settings.value = data
  form.value = {
    from_address: data.from_address,
    from_name: data.from_name || '',
    use_custom_smtp: data.use_custom_smtp,
    smtp_host: data.smtp_host || '',
    smtp_port: data.smtp_port != null && data.smtp_port > 0 ? data.smtp_port : 587,
    smtp_user: data.smtp_user || '',
    smtp_password: '',
    smtp_encryption: (['tls', 'ssl', 'none'].includes(data.smtp_encryption)
      ? data.smtp_encryption
      : 'tls') as 'tls' | 'ssl' | 'none',
  }
}

async function load() {
  isLoading.value = true
  error.value = ''
  try {
    const data = await getMailSettings(departmentId.value)
    applySettings(data)
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Einstellungen konnten nicht geladen werden.'
    settings.value = null
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!canEdit.value) return
  isSaving.value = true
  error.value = ''
  try {
    const name = form.value.from_name.trim()
    const body: MailOutboundSettingsPatch = {
      from_address: form.value.from_address.trim(),
      from_name: name === '' ? null : name,
      use_custom_smtp: form.value.use_custom_smtp,
    }
    if (form.value.use_custom_smtp) {
      body.smtp_host = form.value.smtp_host.trim()
      const p = form.value.smtp_port
      body.smtp_port = p === '' || p === null || (typeof p === 'number' && Number.isNaN(p)) ? null : Number(p)
      body.smtp_user = form.value.smtp_user.trim()
      body.smtp_encryption = form.value.smtp_encryption
      const pwd = form.value.smtp_password.trim()
      if (pwd !== '') {
        body.smtp_password = pwd
      }
    }
    const data = await patchMailSettings(body, departmentId.value)
    applySettings(data)
    form.value.smtp_password = ''
    toast.success('E-Mail-Einstellungen gespeichert.')
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Speichern fehlgeschlagen.'
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}

async function sendTest() {
  const to = testTo.value.trim()
  if (!to) {
    toast.error('Bitte eine Ziel-E-Mail angeben.')
    return
  }
  isTesting.value = true
  try {
    await postMailTestSend(to)
    toast.success('Testmail wurde ausgelöst. Bei Datei-Spool liegt die Nachricht im Spool-Ordner.')
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Testmail fehlgeschlagen.'
    toast.error(msg)
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

.smtp-hint {
  margin-bottom: 8px;
}

.hint code {
  font-size: 12px;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}

.warn {
  margin: 0 0 16px 0;
  padding: 10px 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 12px;
  color: #991b1b;
  line-height: 1.45;
}

.check-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  font-weight: 500;
  color: #334155;
}

.check-row input {
  margin-top: 3px;
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

.input-narrow {
  max-width: 120px;
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

.transport-path {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #64748b;
}

.transport-path code {
  font-size: 12px;
  word-break: break-all;
}

.testmail-hint {
  margin-bottom: 10px;
}

.test-actions {
  margin-bottom: 8px;
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
</style>
