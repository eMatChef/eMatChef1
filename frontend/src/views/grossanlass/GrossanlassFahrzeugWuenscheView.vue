<template>
  <PageShell
    :title="t('grossanlass.planung.wishForms.vehiclesAtProject')"
    :subtitle="t('grossanlass.planung.wishForms.vehiclesAtProjectHint')"
  >
    <div class="round-detail-toolbar">
      <EButton variant="secondary" size="small" @click="goBack">
        <v-icon icon="mdi-arrow-left" start size="18" />
        {{ t('grossanlass.planung.rounds.backToList') }}
      </EButton>
      <span class="status-badge status-open">{{ t('grossanlass.planung.rounds.statusOpen') }}</span>
    </div>

    <v-tabs v-model="activeTab" class="round-detail-tabs" color="primary">
      <v-tab value="input">{{ t('grossanlass.roundDetail.tabInput') }}</v-tab>
      <v-tab value="responses">
        {{ t('grossanlass.roundDetail.tabResponses') }}
        <span v-if="visibleRows.length > 0" class="tab-badge">{{ visibleRows.length }}</span>
      </v-tab>
    </v-tabs>

    <div v-if="activeTab === 'responses'" class="tab-panel">
      <div class="responses-submit">
        <EButton variant="primary" size="small" @click="activeTab = 'input'">
          <v-icon icon="mdi-plus" start size="18" />
          {{ t('grossanlass.dashboard.submitWish') }}
        </EButton>
      </div>
      <div class="responses-toolbar">
        <ESearchField
          v-model="search"
          :label="t('grossanlass.responses.search')"
          class="responses-search"
        />
      </div>
      <div class="responses-stats">
        <span>{{ t('grossanlass.responses.statTotal', { count: visibleRows.length }) }}</span>
        <span class="stat-pending">{{ t('grossanlass.responses.statSubmitted', { count: visibleRows.length }) }}</span>
      </div>
      <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!visibleRows.length" class="muted">{{ t('grossanlass.planung.wishForms.vehiclesEmpty') }}</p>
      <div v-else class="responses-table-wrap">
        <table class="responses-table">
          <thead>
            <tr>
              <th>{{ t('grossanlass.planung.wishForms.colProject') }}</th>
              <th>{{ t('grossanlass.planung.ressorts.vehicleTask') }}</th>
              <th>{{ t('grossanlass.planung.ressorts.vehicleCategory') }}</th>
              <th>{{ t('grossanlass.planung.ressorts.vehicleWish') }}</th>
              <th>{{ t('grossanlass.material.colWhen') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in visibleRows" :key="row.id">
              <td>{{ row.group_name }}</td>
              <td>{{ row.task_label || '–' }}</td>
              <td>{{ row.category_label || '–' }}</td>
              <td>{{ row.vehicle_label || '–' }}</td>
              <td>{{ whenLabel(row) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <form v-else class="vehicle-form tab-panel" @submit.prevent="submitWish">
      <h2>{{ t('grossanlass.dashboard.submitWish') }}</h2>
      <ESelect
        v-model="form.groupId"
        :items="projectItems"
        :label="t('grossanlass.planung.wishForms.colProject')"
        hide-details
      />
      <ETextField
        v-model="form.task"
        :label="t('grossanlass.planung.ressorts.vehicleTask')"
        hide-details
      />
      <GrossanlassVehicleCategoryField v-model="form.category" :department-id="departmentId" />
      <ETextField
        v-model="form.vehicle"
        :label="t('grossanlass.planung.ressorts.vehicleWish')"
        :placeholder="t('grossanlass.planung.ressorts.materialVehiclePlaceholder')"
        hide-details
      />
      <EDateField
        v-model="form.date"
        :label="t('grossanlass.planung.ressorts.taskBlockDate')"
        :department-id="departmentId"
        allow-past
      />
      <div class="vehicle-form__times">
        <ETimeField v-model="form.time" :label="t('grossanlass.planung.ressorts.taskBlockTimeStart')" hide-details />
        <ETimeField v-model="form.end" :label="t('grossanlass.planung.ressorts.taskBlockTimeEnd')" hide-details />
      </div>
      <EButton variant="primary" type="submit" :loading="saving" :disabled="!canSubmit">
        <v-icon icon="mdi-plus" start size="18" />
        {{ t('grossanlass.dashboard.submitWish') }}
      </EButton>
    </form>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import { EButton, EDateField, ESearchField, ESelect, ETextField, ETimeField } from '@/components/form/base'
import GrossanlassVehicleCategoryField from '@/components/grossanlass/GrossanlassVehicleCategoryField.vue'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  createGrossanlassBauprojektVehicle,
  listGrossanlassVehicleNeeds,
  type GaBauprojektVehicleNeed,
} from '@/api/grossanlassBauprojekt'

type Row = GaBauprojektVehicleNeed & { group_name: string }

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const rows = ref<Row[]>([])
const groups = ref<GrossanlassGroup[]>([])
const departmentId = computed(() => String(route.params.departmentId || ''))
const activeTab = ref(String(route.query.einreichen || '') === '1' ? 'input' : 'responses')
const search = ref('')
const form = ref({
  groupId: '',
  task: '',
  vehicle: '',
  category: '',
  date: '',
  time: '08:00',
  end: '08:15',
})

const projectItems = computed(() =>
  groups.value
    .filter((group) => group.node_type === 'bauprojekt' || group.node_type === 'unterressort')
    .map((group) => ({ title: group.name, value: group.id })),
)

const visibleRows = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return rows.value
  return rows.value.filter((row) =>
    [row.group_name, row.task_label, row.category_label, row.vehicle_label].join(' ').toLowerCase().includes(query),
  )
})

const canSubmit = computed(() =>
  !saving.value
  && !!form.value.groupId
  && (!!form.value.task.trim() || !!form.value.vehicle.trim()),
)

function clockMinutes(value: string): number | null {
  const match = /^(\d{1,2}):(\d{2})$/.exec(value.trim())
  if (!match) return null
  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours > 23 || minutes > 59) return null
  return hours * 60 + minutes
}

watch(() => form.value.time, (time) => {
  const start = clockMinutes(time)
  const end = clockMinutes(form.value.end)
  if (start == null) return
  const minEnd = start + 15
  if (end == null || ((end - start + 24 * 60) % (24 * 60)) < 15) {
    const total = minEnd % (24 * 60)
    form.value.end = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
  }
})

watch(activeTab, (tab) => {
  void router.replace({ query: tab === 'input' ? { einreichen: '1' } : {} })
})

async function submitWish() {
  if (!canSubmit.value) return
  saving.value = true
  const start = clockMinutes(form.value.time)
  const end = clockMinutes(form.value.end)
  let duration: number | null = null
  if (start != null && end != null) {
    duration = end - start
    if (duration < 0) duration += 24 * 60
    if (duration < 15) duration = 15
  }
  try {
    await createGrossanlassBauprojektVehicle(departmentId.value, form.value.groupId, {
      task_label: form.value.task.trim(),
      vehicle_label: form.value.vehicle.trim(),
      category_label: form.value.category.trim() || null,
      starts_at: form.value.date ? `${form.value.date}T${form.value.time || '08:00'}:00` : null,
      duration_minutes: duration,
    })
    toast.success(t('grossanlass.planung.ressorts.vehicleSaved'))
    form.value.task = ''
    form.value.vehicle = ''
    form.value.category = ''
    rows.value = await listGrossanlassVehicleNeeds(departmentId.value)
    activeTab.value = 'responses'
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    saving.value = false
  }
}

function whenLabel(row: Row): string {
  if (!row.starts_at) return '–'
  const start = new Date(row.starts_at)
  if (Number.isNaN(start.getTime())) return '–'
  const end = new Date(start)
  if (row.duration_minutes && row.duration_minutes > 0) {
    end.setMinutes(end.getMinutes() + row.duration_minutes)
  }
  const pad = (value: number) => String(value).padStart(2, '0')
  const day = `${pad(start.getDate())}.${pad(start.getMonth() + 1)}.${start.getFullYear()}`
  const from = `${pad(start.getHours())}:${pad(start.getMinutes())}`
  const to = `${pad(end.getHours())}:${pad(end.getMinutes())}`
  return `${day} ${from}–${to}`
}

function goBack() {
  const departmentId = String(route.params.departmentId || '')
  void router.push(`/${departmentId}/planung`)
}

onMounted(async () => {
  try {
    const [needs, tree] = await Promise.all([
      listGrossanlassVehicleNeeds(departmentId.value),
      getGrossanlassGroups(departmentId.value),
    ])
    rows.value = needs
    groups.value = tree
    form.value.groupId = projectItems.value[0]?.value || ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.round-detail-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}
.status-open { background: #d1fae5; color: #065f46; }
.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  margin-left: 6px;
  padding: 0 5px;
  border-radius: 999px;
  background: #f59e0b;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
}
.tab-panel { padding: 20px 0 8px; }
.responses-submit { margin-bottom: 16px; }
.responses-toolbar { display: flex; margin-bottom: 12px; width: 100%; }
.responses-search { flex: 1 1 640px; min-width: min(100%, 480px); max-width: none; width: 100%; }
.responses-search :deep(.e-search-field),
.responses-search :deep(.search-field) { width: 100%; max-width: none; }
.responses-stats { display: flex; gap: 14px; margin-bottom: 14px; font-size: 0.85rem; color: #64748b; }
.stat-pending { color: #b45309; }
.responses-table-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 10px; }
.responses-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.responses-table th, .responses-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f3f4f6; }
.responses-table thead th { background: #f8fafc; font-weight: 600; }
.vehicle-form {
  display: grid;
  gap: 12px;
  max-width: 640px;
  margin-bottom: 28px;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.vehicle-form h2 { margin: 0; font-size: 1.05rem; }
.vehicle-form__times { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.muted { color: #64748b; }
.error { color: #b91c1c; }
.vehicle-wishes { width: 100%; border-collapse: collapse; }
.vehicle-wishes th,
.vehicle-wishes td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
.vehicle-wishes th { color: #64748b; font-size: 0.8rem; font-weight: 600; }
</style>
