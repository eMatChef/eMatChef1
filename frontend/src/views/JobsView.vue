<template>
  <div class="jobs-page">
    <header class="jobs-header">
      <h1>{{ t('jobs.title') }}</h1>
      <p>{{ t('jobs.subtitle') }}</p>
    </header>

    <section v-if="isSuperAdmin" class="job-card">
      <div class="job-title-row">
        <h2>{{ t('jobs.unassigned.title') }}</h2>
        <span class="badge">{{ t('jobs.unassigned.badge') }}</span>
      </div>
      <p class="job-description">
        {{ t('jobs.unassigned.description') }}
      </p>

      <div class="controls">
        <ETextField
          id="days"
          v-model.number="days"
          :label="t('jobs.unassigned.daysLabel')"
          type="number"
          hide-details="auto"
          class="days-field"
        />

        <EButton variant="secondary" :loading="loading" @click="loadPreview">
          {{ t('jobs.actions.loadPreview') }}
        </EButton>
        <EButton variant="secondary" :disabled="loading || previewItems.length === 0" @click="downloadCsv">
          {{ t('jobs.actions.downloadList') }}
        </EButton>
        <EButton variant="danger" :loading="loading" :disabled="loading || selectedCount === 0" @click="runCleanup">
          {{ t('jobs.actions.deleteData', { count: selectedCount }) }}
        </EButton>
      </div>

      <v-alert v-if="error" type="error" variant="tonal" class="mb-3" :text="error" />
      <v-alert v-if="success" type="success" variant="tonal" class="mb-3" :text="success" />

      <div class="preview">
        <h3>{{ t('jobs.preview.title') }}</h3>
        <p v-if="loading">{{ t('jobs.preview.loading') }}</p>
        <p v-else-if="previewCount === 0">{{ t('jobs.preview.empty') }}</p>
        <p v-else>
          {{ t('jobs.preview.impact', { total: previewCount, selected: selectedCount }) }}
        </p>

        <table v-if="previewItems.length > 0">
          <thead>
            <tr>
              <th>
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll($event)" />
              </th>
              <th>{{ t('jobs.table.userId') }}</th>
              <th>{{ t('jobs.table.email') }}</th>
              <th>{{ t('jobs.table.createdAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in previewItems" :key="item.user_id">
              <td>
                <input type="checkbox" v-model="selectedUserMap[item.user_id]" />
              </td>
              <td>{{ item.user_id }}</td>
              <td>{{ item.email || '-' }}</td>
              <td>{{ formatDate(item.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-else class="job-card muted">
      <p>{{ t('jobs.superadminOnly') }}</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  previewUnassignedUsersCleanup,
  runUnassignedUsersCleanup,
  type UnassignedCleanupItem
} from '@/api/jobs'
import { EButton, ETextField } from '@/components/form/base'

const { t } = useI18n()
const authStore = useAuthStore()
const isSuperAdmin = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') || authStore.currentDepartmentRole === 'sa'
)

const days = ref(21)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const previewCount = ref(0)
const previewItems = ref<UnassignedCleanupItem[]>([])
const selectedUserMap = ref<Record<string, boolean>>({})
const selectedIds = computed(() =>
  previewItems.value
    .filter((item) => !!selectedUserMap.value[item.user_id])
    .map((item) => item.user_id)
)
const selectedCount = computed(() => selectedIds.value.length)
const isAllSelected = computed(() => previewItems.value.length > 0 && selectedCount.value === previewItems.value.length)

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('de-CH')
}

async function loadPreview() {
  loading.value = true
  error.value = null
  success.value = null
  try {
    const response = await previewUnassignedUsersCleanup(days.value)
    previewCount.value = response.count
    previewItems.value = response.items
    const nextMap: Record<string, boolean> = {}
    response.items.forEach((item) => {
      nextMap[item.user_id] = true
    })
    selectedUserMap.value = nextMap
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('jobs.messages.previewLoadFailed')
  } finally {
    loading.value = false
  }
}

function toggleSelectAll(event: Event) {
  const checked = (event.target as HTMLInputElement).checked
  const nextMap: Record<string, boolean> = {}
  previewItems.value.forEach((item) => {
    nextMap[item.user_id] = checked
  })
  selectedUserMap.value = nextMap
}

function downloadCsv() {
  const header = ['user_id', 'email', 'created_at']
  const rows = previewItems.value.map((item) => [
    item.user_id,
    item.email || '',
    item.created_at
  ])
  const csvContent = [header, ...rows]
    .map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const date = new Date().toISOString().slice(0, 10)
  link.href = url
  link.download = `unassigned-users-preview-${date}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

async function runCleanup() {
  if (selectedCount.value === 0) return

  const confirmed = window.confirm(
    t('jobs.messages.confirmCleanup', { count: selectedCount.value })
  )
  if (!confirmed) return

  loading.value = true
  error.value = null
  success.value = null
  try {
    const result = await runUnassignedUsersCleanup(days.value, false, selectedIds.value)
    success.value = t('jobs.messages.cleanupSuccess', {
      users: result.deleted_users || 0,
      profiles: result.deleted_profiles || 0
    })
    await loadPreview()
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('jobs.messages.cleanupFailed')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (isSuperAdmin.value) {
    await loadPreview()
  }
})
</script>

<style scoped>
.jobs-page {
  padding: 24px;
  max-width: 1100px;
}

.jobs-header h1 {
  margin: 0;
  font-size: 1.75rem;
}

.jobs-header p {
  color: #6b7280;
  margin-top: 8px;
}

.job-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-top: 16px;
}

.job-card.muted {
  color: #6b7280;
}

.job-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.job-title-row h2 {
  margin: 0;
}

.badge {
  font-size: 12px;
  color: #065f46;
  background: #d1fae5;
  padding: 4px 8px;
  border-radius: 999px;
}

.job-description {
  color: #4b5563;
}

.controls {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  margin: 14px 0;
}

.days-field {
  max-width: 100px;
}

.preview h3 {
  margin-bottom: 8px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th,
td {
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  padding: 8px;
}
</style>
