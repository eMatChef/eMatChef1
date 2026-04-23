<template>
  <div class="mail-log">
    <h2 class="section-title">Versandprotokoll</h2>
    <p class="hint">
      Kurzprotokoll ohne sensiblen Inhalt: Zeitpunkt, Art, Absender-Adresse, Empfänger, Betreff — keine Passwörter, kein
      Link-Token, kein Mailtext. Maximal 500 Einträge in <code>var/app/mail_send_log.json</code>. Wer als
      <strong>Benutzer</strong> eine Mail ausgelöst hat, wird hier nicht automatisch mitgeschrieben (dazu müsste jeder
      Versandpfad die User-ID mitgeben).
    </p>

    <div v-if="isLoading" class="state">Lade Log…</div>
    <div v-else-if="error" class="state error">{{ error }}</div>

    <div v-else-if="entries.length === 0" class="state">Noch keine Einträge.</div>

    <div v-else class="table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th>Zeit</th>
            <th>Art</th>
            <th>Von</th>
            <th>An</th>
            <th>Betreff</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in entries" :key="idx">
            <td class="cell-time">{{ formatAt(row.at) }}</td>
            <td class="cell-kind">
              <span class="kind-pill" :title="row.kind">{{ kindLabel(row.kind) }}</span>
            </td>
            <td class="cell-from">{{ row.from || '—' }}</td>
            <td class="cell-to">{{ row.to }}</td>
            <td class="cell-subject">{{ row.subject }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getMailSendLog, type MailSendLogEntry } from '@/api/mailAdmin'

const route = useRoute()

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

const KIND_LABELS: Record<string, string> = {
  'auth.verify_email': 'Registrierung · E-Mail bestätigen',
  'auth.pending_email_change': 'Profil · Neue E-Mail bestätigen',
  'auth.password_reset_code': 'Passwort zurücksetzen',
  'department.invite': 'Department-Einladung',
  'public.found_item_contact': 'Öffentlich · Fund-Hinweis',
  'mail.test': 'Testmail (Einstellungen)',
}

function kindLabel(kind: string): string {
  return KIND_LABELS[kind] || kind
}

function formatAt(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return new Intl.DateTimeFormat('de-CH', {
    dateStyle: 'short',
    timeStyle: 'medium',
  }).format(d)
}

const isLoading = ref(true)
const error = ref('')
const entries = ref<MailSendLogEntry[]>([])

async function load() {
  isLoading.value = true
  error.value = ''
  try {
    entries.value = await getMailSendLog(150, departmentId.value)
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Log konnte nicht geladen werden.'
    entries.value = []
  } finally {
    isLoading.value = false
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
.mail-log {
  width: 100%;
}

.section-title {
  margin: 0 0 8px 0;
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.hint {
  margin: 0 0 18px 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
  max-width: 720px;
}

.hint code {
  font-size: 12px;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}

.state {
  padding: 16px 0;
  color: #64748b;
}

.state.error {
  color: #b91c1c;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.log-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.log-table th {
  text-align: left;
  padding: 10px 12px;
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.log-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.log-table tr:last-child td {
  border-bottom: none;
}

.cell-time {
  white-space: nowrap;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

.cell-kind {
  max-width: 220px;
}

.kind-pill {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  background: #f1f5f9;
  color: #334155;
  font-size: 12px;
}

.cell-to {
  word-break: break-all;
  max-width: 240px;
}

.cell-subject {
  color: #0f172a;
  word-break: break-word;
}
</style>
