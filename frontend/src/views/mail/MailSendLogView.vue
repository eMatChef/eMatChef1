<template>
  <div class="mail-log">
    <h2 class="section-title">{{ t('mail.log.title') }}</h2>
    <p class="hint">
      {{ t('mail.log.hint') }}
    </p>

    <div v-if="isLoading" class="state">{{ t('mail.log.loading') }}</div>
    <div v-else-if="error" class="state error">{{ error }}</div>

    <div v-else-if="entries.length === 0" class="state">{{ t('mail.log.empty') }}</div>

    <div v-else class="table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th>{{ t('mail.log.columns.time') }}</th>
            <th>{{ t('mail.log.columns.kind') }}</th>
            <th>{{ t('mail.log.columns.from') }}</th>
            <th>{{ t('mail.log.columns.to') }}</th>
            <th>{{ t('mail.log.columns.subject') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in entries" :key="idx">
            <td class="cell-time">{{ formatAt(row.at) }}</td>
            <td class="cell-kind">
              <span class="kind-pill" :title="row.kind">{{ kindLabel(row.kind) }}</span>
            </td>
            <td class="cell-from">{{ row.from || t('mail.log.dash') }}</td>
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
import { useI18n } from 'vue-i18n'
import { getMailSendLog, type MailSendLogEntry } from '@/api/mailAdmin'

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

const KIND_LABELS: Record<string, string> = {
  'auth.verify_email': 'mail.log.kinds.authVerifyEmail',
  'auth.pending_email_change': 'mail.log.kinds.authPendingEmailChange',
  'auth.password_reset_code': 'mail.log.kinds.authPasswordReset',
  'department.invite': 'mail.log.kinds.departmentInvite',
  'public.found_item_contact': 'mail.log.kinds.publicFoundItem',
  'mail.test': 'mail.log.kinds.mailTest',
  'mail.test.failed': 'mail.log.kinds.mailTestFailed',
}

function kindLabel(kind: string): string {
  const key = KIND_LABELS[kind]
  return key ? t(key) : kind
}

function formatAt(iso: string): string {
  if (!iso) return t('mail.log.dash')
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
    error.value = err.response?.data?.error || t('mail.log.loadError')
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
