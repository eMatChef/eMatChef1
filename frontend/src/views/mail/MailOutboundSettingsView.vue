<template>
  <div class="mail-outbound">
    <h2 class="section-title">Absender-E-Mail</h2>
    <p class="hint">
      Absender und optional SMTP werden in <code>var/app/mail_outbound.json</code> gespeichert (nicht in der Datenbank).
      <strong>Versand:</strong> Zuerst <strong>MAILER_DSN</strong> aus der Umgebung (wenn nicht <code>null://</code>), sonst
      vollständiges SMTP aus dieser JSON-Datei<template v-if="!mailInternalSpoolRestricted"
        >, sonst Datei-Spool (<code>.eml</code>)</template
      ><template v-else
        >. In <strong>Produktion</strong> ist kein reiner Datei-Spool ohne SMTP/<code>MAILER_DSN</code> möglich</template
      >.
      <strong>Antworten (Reply-To):</strong> Zuerst <strong>MAILER_REPLY_TO</strong> in der Server-Umgebung, sonst die
      unten konfigurierbare Adresse in JSON.
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

    <div v-if="settings && !isLoading && settings.mailer_transport_mode === 'env'" class="env-mail-info">
      <p class="env-mail-info-title">Was aus der Server-<code>.env</code> kommt (hier nicht editierbar)</p>
      <ul class="env-mail-info-list">
        <li>
          <strong>Versand (SMTP):</strong> Variable <code>MAILER_DSN</code> — ist aktiv; der genaue Wert wird aus
          Sicherheitsgründen <strong>nicht</strong> angezeigt. Änderungen nur in der <code>.env</code> / Compose auf dem
          Server, danach Backend neu starten.
        </li>
        <li>
          <strong>Absender-Fallback:</strong> <code>MAILER_FROM</code> siehe Zeile unter „Absender-E-Mail“
          (<code>{{ settings.env_fallback_address }}</code>).
        </li>
        <li>
          <strong>Reply-To:</strong> Wenn <code>MAILER_REPLY_TO</code> in der <code>.env</code> gesetzt ist, gilt diese
          Adresse (Abschnitt „Reply-To“ unten). Sonst nutzt die App das Reply-To-Feld aus JSON.
        </li>
      </ul>
    </div>

    <div
      v-else-if="settings && !isLoading && settings.mailer_transport_mode === 'smtp_json'"
      class="env-mail-info env-mail-info--neutral"
    >
      <p class="env-mail-info-title">Server-<code>.env</code> und dieser Screen</p>
      <ul class="env-mail-info-list">
        <li>
          <strong>Versand (SMTP):</strong> über die Zugangsdaten in <code>mail_outbound.json</code> (Felder unten) —
          <code>MAILER_DSN</code> in der <code>.env</code> ist leer oder <code>null://…</code>.
        </li>
        <li>
          <strong>Absender-Fallback</strong> (<code>MAILER_FROM</code>) und <strong>Reply-To</strong> (
          <code>MAILER_REPLY_TO</code>) können weiterhin aus der <code>.env</code> kommen — siehe Abschnitte unten.
        </li>
      </ul>
    </div>

    <div
      v-if="settings && !isLoading && mailInternalSpoolRestricted && settings.mailer_transport_mode === 'file_spool'"
      class="smtp-env-override-notice"
    >
      Aktuell ist kein SMTP und kein <code>MAILER_DSN</code> aktiv — es gäbe nur den Datei-Spool, der in Produktion
      gesperrt ist. Bitte SMTP hier ausfüllen und speichern oder <code>MAILER_DSN</code> auf dem Server setzen.
    </div>

    <div
      v-if="settings && !isLoading && settings.use_custom_smtp && settings.mailer_transport_mode === 'env'"
      class="smtp-env-override-notice smtp-env-override-notice--critical"
      role="status"
    >
      <strong>Wichtig:</strong> Auf dem Server ist <strong>MAILER_DSN</strong> gesetzt — <strong>Testmail und Versand</strong>
      laufen nur über diese Umgebungs-DSN, <em>nicht</em> über die SMTP-Felder unten (die werden nur in JSON gespeichert).
      Fehler bei der Testmail betreffen daher fast immer die <code>MAILER_DSN</code> in der Docker-<code>.env</code> (Host,
      Port, <code>?encryption=tls</code> bei Port 587). Willst du stattdessen die Felder hier nutzen:
      <code>MAILER_DSN</code> leer lassen oder auf <code>null://default</code> setzen und Backend neu starten.
    </div>

    <div
      v-if="settings && !isLoading && form.use_custom_smtp && smtpPortEncryptionMismatch"
      class="smtp-port-mismatch-notice"
      role="status"
    >
      <strong>Hinweis:</strong> Port <strong>587</strong> gehört zu <strong>STARTTLS (TLS)</strong>; Port
      <strong>465</strong> zu <strong>SSL/SMTPS</strong>. Bitte Kombination anpassen (und speichern), sonst schlägt SMTP oft
      fehl — sobald Versand über JSON-SMTP aktiv ist.
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

      <h3 id="reply-to-section" class="sub-title">Reply-To (Antwort-Adresse)</h3>
      <p class="hint">
        Wenn Empfänger in der Mail-App auf „Antworten“ tippen, landet die Adresse hier bzw. aus der <code>.env</code>.
      </p>

      <div v-if="settings?.mailer_reply_to_env" class="env-readonly-block">
        <span class="env-readonly-label">Aktiv aus Server-<code>.env</code> (<code>MAILER_REPLY_TO</code>)</span>
        <code class="env-readonly-value">{{ settings.mailer_reply_to_env }}</code>
        <p class="env-readonly-hint">
          Dieses Reply-To hat <strong>Vorrang</strong>. Das Feld „Reply-To in JSON“ unten wird dann ignoriert, bis
          <code>MAILER_REPLY_TO</code> auf dem Server entfernt oder geleert wird.
        </p>
      </div>
      <div v-else class="env-readonly-block env-readonly-block--muted">
        <span class="env-readonly-label">Server-<code>.env</code> (<code>MAILER_REPLY_TO</code>)</span>
        <span class="env-readonly-empty">nicht gesetzt — es gilt die Adresse im Feld „Reply-To in JSON“ (falls ausgefüllt).</span>
      </div>

      <div class="form-row">
        <label for="reply-to">Reply-To in JSON (<code>mail_outbound.json</code>, Fallback)</label>
        <input
          id="reply-to"
          v-model="form.reply_to_address"
          type="email"
          class="input"
          autocomplete="off"
          placeholder="z. B. support@ematchef.ch — nur wenn MAILER_REPLY_TO in .env leer ist"
          :disabled="!canEdit || !!settings?.mailer_reply_to_env"
        />
      </div>
      <p v-if="settings?.mailer_reply_to_env" class="meta">
        JSON-Feld ist deaktiviert, solange <code>MAILER_REPLY_TO</code> in der <code>.env</code> gesetzt ist.
      </p>
      <p v-if="settings?.reply_to_effective" class="meta">
        <strong>Wirksam für neue Mails (Reply-To):</strong> <code>{{ settings.reply_to_effective }}</code>
      </p>

      <h3 class="sub-title">SMTP-Versand (optional)</h3>
      <p class="hint smtp-hint">
        <label class="check-row">
          <input
            type="checkbox"
            :checked="form.use_custom_smtp"
            :disabled="!canEdit"
            @change="onCustomSmtpCheckboxChange"
          />
          <span v-if="settings?.mailer_transport_mode === 'env'"
            >Eigene SMTP-Zugangsdaten in JSON speichern — <strong>Versand/Testmail</strong> nutzen derzeit
            <strong>MAILER_DSN</strong> (gelber Kasten), nicht diese Felder</span
          >
          <span v-else-if="!mailInternalSpoolRestricted"
            >Eigene SMTP-Zugangsdaten verwenden (nur wenn MAILER_DSN auf dem Server leer bzw. null:// ist)</span
          >
          <span v-else
            >Eigene SMTP-Zugangsdaten verwenden (in Produktion erforderlich, wenn kein <code>MAILER_DSN</code> gesetzt
            ist)</span
          >
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
        <p v-if="settings?.mailer_transport_mode === 'env'" class="testmail-uses-env-hint">
          Es wird <strong>MAILER_DSN</strong> getestet (siehe gelber Kasten), nicht die SMTP-Felder in diesem Formular.
        </p>
        <p class="hint testmail-hint">
          Sendet eine kurze Prüfmail über den oben angezeigten Versandweg<template v-if="!mailInternalSpoolRestricted"
            > (SMTP, Umgebung oder Datei-Spool)</template
          >.
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
import { apiErrorMessage } from '@/utils/apiErrorMessage'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

const canEdit = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

/** Backend setzt in Symfony-Umgebung prod auf false */
const mailInternalSpoolRestricted = computed(
  () => settings.value?.mail_internal_spool_allowed === false
)

/** In Prod ohne MAILER_DSN: Checkbox „eigene SMTP“ nicht deaktivieren (sonst nur Datei-Spool) */
const blockUncheckCustomSmtp = computed(
  () => mailInternalSpoolRestricted.value && settings.value?.mailer_transport_mode !== 'env'
)

/** 587 = STARTTLS (tls), 465 = SMTPS (ssl) — typische Fehlkonfiguration */
const smtpPortEncryptionMismatch = computed(() => {
  if (!form.value.use_custom_smtp) return false
  const raw = form.value.smtp_port
  const num = raw === '' || raw === null ? NaN : Number(raw)
  if (Number.isNaN(num) || num <= 0) return false
  return (
    (form.value.smtp_encryption === 'ssl' && num === 587) ||
    (form.value.smtp_encryption === 'tls' && num === 465)
  )
})

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
  use_custom_smtp: false,
  smtp_host: '',
  smtp_port: 587 as number | '',
  smtp_user: '',
  smtp_password: '',
  smtp_encryption: 'tls' as 'tls' | 'ssl' | 'none',
})

function onCustomSmtpCheckboxChange(e: Event) {
  const el = e.target as HTMLInputElement
  const next = el.checked
  if (blockUncheckCustomSmtp.value && !next) {
    el.checked = true
    toast.error(
      'In Produktion ist der lokale Datei-Mailspool deaktiviert. SMTP aktiv lassen oder MAILER_DSN auf dem Server setzen.'
    )
    return
  }
  form.value.use_custom_smtp = next
}

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
  let smtpPort = data.smtp_port != null && data.smtp_port > 0 ? data.smtp_port : 587
  let smtpEnc = (['tls', 'ssl', 'none'].includes(data.smtp_encryption)
    ? data.smtp_encryption
    : 'tls') as 'tls' | 'ssl' | 'none'
  // Häufiger Fehler (z. B. Hostpoint): 587 + SSL — 587 ist STARTTLS
  if (smtpEnc === 'ssl' && smtpPort === 587) {
    smtpEnc = 'tls'
  }
  if (smtpEnc === 'tls' && smtpPort === 465) {
    smtpEnc = 'ssl'
  }
  form.value = {
    from_address: data.from_address,
    from_name: data.from_name || '',
    reply_to_address: data.reply_to_address || '',
    use_custom_smtp: data.use_custom_smtp,
    smtp_host: data.smtp_host || '',
    smtp_port: smtpPort,
    smtp_user: data.smtp_user || '',
    smtp_password: '',
    smtp_encryption: smtpEnc,
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

function validateSmtpPortEncryption(): boolean {
  if (!smtpPortEncryptionMismatch.value) return true
  toast.error(
    'Port und Verschlüsselung passen nicht zusammen: Port 587 → „STARTTLS (TLS)“, Port 465 → „SSL/TLS (SMTPS)“.'
  )
  return false
}

async function save() {
  if (!canEdit.value) return
  if (!validateSmtpPortEncryption()) return
  isSaving.value = true
  error.value = ''
  try {
    const name = form.value.from_name.trim()
    const body: MailOutboundSettingsPatch = {
      from_address: form.value.from_address.trim(),
      from_name: name === '' ? null : name,
      reply_to_address: form.value.reply_to_address.trim(),
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
    toast.error(apiErrorMessage(err, 'Speichern fehlgeschlagen.'))
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
    if (settings.value?.uses_file_spool && settings.value?.mail_internal_spool_allowed !== false) {
      toast.success('Testmail wurde ausgelöst. Bei Datei-Spool liegt die Nachricht im Spool-Ordner.')
    } else {
      toast.success('Testmail wurde ausgelöst.')
    }
  } catch (err: any) {
    toast.error(apiErrorMessage(err, 'Testmail fehlgeschlagen.'))
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

watch(
  () => form.value.smtp_encryption,
  (enc) => {
    if (!form.value.use_custom_smtp) return
    const raw = form.value.smtp_port
    const n = raw === '' || raw === null ? NaN : Number(raw)
    if (Number.isNaN(n)) return
    if (enc === 'ssl' && n === 587) {
      form.value.smtp_port = 465
    } else if (enc === 'tls' && n === 465) {
      form.value.smtp_port = 587
    }
  }
)
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

.env-mail-info {
  margin: 0 0 20px 0;
  padding: 12px 14px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 13px;
  color: #14532d;
  line-height: 1.55;
}

.env-mail-info-title {
  margin: 0 0 8px 0;
  font-weight: 600;
  font-size: 13px;
}

.env-mail-info-list {
  margin: 0;
  padding-left: 1.15rem;
}

.env-mail-info-list li {
  margin-bottom: 6px;
}

.env-mail-info-list li:last-child {
  margin-bottom: 0;
}

.env-mail-info--neutral {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #334155;
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

.smtp-env-override-notice {
  margin: 0 0 20px 0;
  padding: 12px 14px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  font-size: 13px;
  color: #78350f;
  line-height: 1.55;
}

.smtp-env-override-notice--critical {
  border-color: #f59e0b;
  background: #fff7ed;
  border-left: 4px solid #ea580c;
}

.smtp-port-mismatch-notice {
  margin: 0 0 16px 0;
  padding: 10px 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 13px;
  color: #991b1b;
  line-height: 1.5;
}

.testmail-uses-env-hint {
  margin: 0 0 10px 0;
  padding: 8px 10px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: 13px;
  color: #1e3a5f;
  line-height: 1.45;
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
