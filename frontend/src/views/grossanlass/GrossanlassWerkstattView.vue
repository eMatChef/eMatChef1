<template>
  <PageShell
    class="ga-werkstatt-shell"
    :title="t('grossanlass.werkstatt.title')"
    :subtitle="t('grossanlass.werkstatt.subtitle')"
  >
    <template #actions>
      <EButton variant="primary" @click="openCreate">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.werkstatt.newCase') }}
      </EButton>
    </template>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />
    <v-alert v-else-if="error" type="error" variant="tonal" :text="error" class="mb-3" />
    <EEmptyState
      v-else-if="cases.length === 0"
      variant="create"
      icon="mdi-wrench"
      :title="t('grossanlass.werkstatt.emptyTitle')"
      :description="t('grossanlass.werkstatt.emptyText')"
    >
      <template #actions>
        <EButton variant="primary" @click="openCreate">{{ t('grossanlass.werkstatt.newCase') }}</EButton>
      </template>
    </EEmptyState>

    <table v-else class="data-table">
      <thead>
        <tr>
          <th>{{ t('grossanlass.werkstatt.colCase') }}</th>
          <th>{{ t('grossanlass.werkstatt.colOrigin') }}</th>
          <th>{{ t('grossanlass.werkstatt.colPath') }}</th>
          <th>{{ t('grossanlass.werkstatt.colStatus') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in cases" :key="row.id">
          <td>
            <strong>{{ row.title }}</strong>
            <span v-if="row.material_label" class="meta">{{ row.material_label }}</span>
            <span v-if="row.owner_firm_name" class="meta">{{ row.owner_firm_name }}</span>
          </td>
          <td>{{ t(`grossanlass.werkstatt.origin.${row.origin}`) }}</td>
          <td>{{ t(`grossanlass.werkstatt.path.${row.path}`) }}</td>
          <td>
            <span class="chip" :class="row.status">{{ t(`grossanlass.werkstatt.status.${row.status}`) }}</span>
          </td>
          <td class="actions">
            <EButton
              v-if="row.status !== 'done' && row.status !== 'cancelled'"
              variant="secondary"
              size="small"
              @click="setPath(row, 'repair')"
            >
              {{ t('grossanlass.werkstatt.actionRepair') }}
            </EButton>
            <EButton
              v-if="row.status !== 'done' && row.status !== 'cancelled'"
              variant="secondary"
              size="small"
              @click="setPath(row, 'owner')"
            >
              {{ t('grossanlass.werkstatt.actionOwner') }}
            </EButton>
            <EButton
              v-if="row.status !== 'done' && row.status !== 'cancelled'"
              variant="primary"
              size="small"
              @click="setStatus(row, 'done')"
            >
              {{ t('grossanlass.werkstatt.actionDone') }}
            </EButton>
          </td>
        </tr>
      </tbody>
    </table>

    <EDialog v-model="showCreate" :max-width="560" :title="t('grossanlass.werkstatt.newCase')">
      <div class="create-form">
        <ETextField v-model="form.title" :label="t('grossanlass.werkstatt.fieldTitle')" hide-details="auto" />
        <ETextField v-model="form.material_label" :label="t('grossanlass.werkstatt.fieldMaterial')" hide-details="auto" />
        <ESelect
          v-model="form.origin"
          :items="originItems"
          :label="t('grossanlass.werkstatt.fieldOrigin')"
          hide-details="auto"
        />
        <ETextField
          v-if="form.origin === 'loan'"
          v-model="form.owner_firm_name"
          :label="t('grossanlass.werkstatt.fieldOwner')"
          hide-details="auto"
        />
        <ESelect
          v-model="form.path"
          :items="pathItems"
          :label="t('grossanlass.werkstatt.fieldPath')"
          hide-details="auto"
        />
        <ETextarea v-model="form.description" :label="t('grossanlass.werkstatt.fieldDescription')" hide-details="auto" />
      </div>
      <template #actions>
        <EButton variant="secondary" @click="showCreate = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!form.title.trim() || isSaving" @click="createCase">
          {{ t('grossanlass.werkstatt.create') }}
        </EButton>
      </template>
    </EDialog>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import {
  createGrossanlassWorkshopCase,
  getGrossanlassWorkshopCases,
  updateGrossanlassWorkshopCase,
  type GrossanlassWorkshopCase,
  type GrossanlassWorkshopOrigin,
  type GrossanlassWorkshopPath,
  type GrossanlassWorkshopStatus,
} from '@/api/grossanlassWorkshopCases'

defineOptions({ name: 'GrossanlassWerkstatt' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const isLoading = ref(false)
const isSaving = ref(false)
const error = ref('')
const cases = ref<GrossanlassWorkshopCase[]>([])
const showCreate = ref(false)
const form = reactive({
  title: '',
  description: '',
  origin: 'loan' as GrossanlassWorkshopOrigin,
  owner_firm_name: '',
  material_label: '',
  path: 'repair' as GrossanlassWorkshopPath,
})

const originItems = computed(() => [
  { title: t('grossanlass.werkstatt.origin.own'), value: 'own' },
  { title: t('grossanlass.werkstatt.origin.loan'), value: 'loan' },
  { title: t('grossanlass.werkstatt.origin.buy'), value: 'buy' },
])

const pathItems = computed(() => [
  { title: t('grossanlass.werkstatt.path.repair'), value: 'repair' },
  { title: t('grossanlass.werkstatt.path.owner'), value: 'owner' },
])

function resetForm() {
  form.title = ''
  form.description = ''
  form.origin = 'loan'
  form.owner_firm_name = ''
  form.material_label = ''
  form.path = 'repair'
}

function openCreate() {
  resetForm()
  showCreate.value = true
}

function replaceRow(next: GrossanlassWorkshopCase) {
  cases.value = cases.value.map((row) => (row.id === next.id ? next : row))
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    cases.value = await getGrossanlassWorkshopCases(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.werkstatt.loadError')
  } finally {
    isLoading.value = false
  }
}

async function createCase() {
  if (!departmentId.value || !form.title.trim()) return
  isSaving.value = true
  try {
    const created = await createGrossanlassWorkshopCase(departmentId.value, {
      title: form.title.trim(),
      description: form.description.trim(),
      origin: form.origin,
      owner_firm_name: form.origin === 'loan' ? form.owner_firm_name.trim() : '',
      material_label: form.material_label.trim(),
      path: form.path,
    })
    cases.value = [created, ...cases.value]
    showCreate.value = false
    toast.success(t('grossanlass.werkstatt.created'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.werkstatt.saveError'))
  } finally {
    isSaving.value = false
  }
}

async function setPath(row: GrossanlassWorkshopCase, path: GrossanlassWorkshopPath) {
  if (!departmentId.value) return
  try {
    replaceRow(
      await updateGrossanlassWorkshopCase(departmentId.value, row.id, {
        path,
        status: path === 'owner' ? 'waiting_owner' : 'in_progress',
      }),
    )
  } catch {
    toast.error(t('grossanlass.werkstatt.saveError'))
  }
}

async function setStatus(row: GrossanlassWorkshopCase, status: GrossanlassWorkshopStatus) {
  if (!departmentId.value) return
  try {
    replaceRow(await updateGrossanlassWorkshopCase(departmentId.value, row.id, { status }))
  } catch {
    toast.error(t('grossanlass.werkstatt.saveError'))
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.create-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}

.data-table th,
.data-table td {
  padding: 12px 14px;
  text-align: left;
  vertical-align: top;
  border-bottom: 1px solid #f1f5f9;
}

.data-table th {
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
  background: #f8fafc;
}

.data-table td strong {
  display: block;
}

.data-table .meta {
  display: block;
  margin-top: 2px;
  color: #64748b;
  font-size: 0.82rem;
}

.data-table .actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #e2e8f0;
  color: #334155;
}

.chip.in_progress {
  background: #dbeafe;
  color: #1d4ed8;
}

.chip.waiting_owner {
  background: #fef3c7;
  color: #b45309;
}

.chip.done {
  background: #dcfce7;
  color: #166534;
}

.chip.cancelled {
  background: #f1f5f9;
  color: #64748b;
}
</style>
