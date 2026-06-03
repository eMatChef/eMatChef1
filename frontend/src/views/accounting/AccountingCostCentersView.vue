<template>
  <div class="accounting-subpage cost-centers-page">
    <p class="description" style="margin-bottom: 20px">
      {{ t('accounting.costCenters.introBefore') }}<strong>{{ t('accounting.costCenters.introStrong') }}</strong>{{ t('accounting.costCenters.introAfter') }}
    </p>

    <div class="page-toolbar">
      <EButton variant="primary" :disabled="isLoading" @click="openCreate">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('accounting.costCenters.newButton') }}
      </EButton>
    </div>

    <ELoadingState v-if="isLoading" variant="inline" :message="t('accounting.common.loading')" />
    <p v-else-if="loadError" class="error-inline">{{ loadError }}</p>

    <EEmptyState
      v-else-if="items.length === 0"
      :title="t('accounting.costCenters.emptyText')"
    >
      <template #actions>
        <EButton variant="primary" :disabled="isLoading" @click="openCreate">
          {{ t('accounting.costCenters.createButton') }}
        </EButton>
        <EButton
          variant="secondary"
          :disabled="isLoading || isApplyingStandardSeeds"
          :loading="isApplyingStandardSeeds"
          @click="createStandardCostCenters"
        >
          {{ t('accounting.costCenters.applySeeds') }}
        </EButton>
      </template>
    </EEmptyState>

    <div v-else class="cost-centers-table-wrap">
      <table class="cost-centers-table">
        <thead>
          <tr>
            <th>{{ t('common.name') }}</th>
            <th>{{ t('accounting.common.accountCode') }}</th>
            <th>{{ t('accounting.common.sortOrder') }}</th>
            <th class="col-actions">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>
              <strong>{{ row.name }}</strong>
              <div v-if="row.description" class="muted" style="font-size: 13px; margin-top: 4px">{{ row.description }}</div>
            </td>
            <td>{{ row.account_code || t('accounting.common.dash') }}</td>
            <td>{{ row.sort_order }}</td>
            <td class="col-actions">
              <EButton variant="text" size="small" :title="t('common.edit')" @click="openEdit(row)">
                <v-icon icon="mdi-pencil-outline" size="20" />
              </EButton>
              <EButton variant="text" size="small" color="error" :title="t('common.delete')" @click="onDelete(row)">
                <v-icon icon="mdi-delete-outline" size="20" />
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EDialog
      v-model="modalOpen"
      :max-width="520"
      :title="editingId ? t('accounting.costCenters.modalEditTitle') : t('accounting.costCenters.modalCreateTitle')"
    >
      <ETextField
        v-model="form.name"
        :label="t('accounting.costCenters.labelNameStar')"
        :placeholder="t('accounting.costCenters.placeholderName')"
        maxlength="255"
        hide-details="auto"
      />
      <ETextField
        v-model="form.account_code"
        class="mt-3"
        :label="t('accounting.costCenters.labelAccountOptional')"
        :placeholder="t('accounting.costCenters.placeholderCode')"
        maxlength="32"
        hide-details="auto"
      />
      <ETextField
        v-model.number="form.sort_order"
        class="mt-3"
        type="number"
        :label="t('accounting.costCenters.labelSort')"
        hide-details="auto"
      />
      <ETextarea
        v-model="form.description"
        class="mt-3"
        :label="t('accounting.costCenters.labelDescriptionOptional')"
        :placeholder="t('accounting.costCenters.placeholderDescription')"
        rows="3"
        hide-details="auto"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeModal">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="save">
          {{ saving ? t('accounting.common.saving') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import {
  listCostCenters,
  createCostCenter,
  updateCostCenter,
  deleteCostCenter,
  type AccountingCostCenter
} from '@/api/accountingCostCenters'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ETextField, ETextarea } from '@/components/form/base'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId ?? ''))

const items = ref<AccountingCostCenter[]>([])
const isLoading = ref(true)
const loadError = ref('')
const modalOpen = ref(false)
const editingId = ref<string | null>(null)
const saving = ref(false)
/** Standard-Vorschläge-Button (expliziter Name für Template/HMR mit KeepAlive). */
const isApplyingStandardSeeds = ref(false)

const SEED_KEYS = [
  { key: 'material' as const, sort_order: 10 },
  { key: 'general' as const, sort_order: 20 },
  { key: 'events' as const, sort_order: 30 },
]

const form = reactive({
  name: '',
  account_code: '' as string,
  description: '' as string,
  sort_order: 0
})

function resetForm() {
  form.name = ''
  form.account_code = ''
  form.description = ''
  form.sort_order = 0
  editingId.value = null
}

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    items.value = await listCostCenters(departmentId.value)
  } catch (e: unknown) {
    const msg = e && typeof e === 'object' && 'response' in e ? (e as { response?: { data?: { error?: string } } }).response?.data?.error : null
    loadError.value = msg || t('accounting.costCenters.loadError')
    items.value = []
  } finally {
    isLoading.value = false
  }
}

async function createStandardCostCenters() {
  if (items.value.length > 0 || !departmentId.value) return
  isApplyingStandardSeeds.value = true
  try {
    for (const row of SEED_KEYS) {
      await createCostCenter(departmentId.value, {
        name: t(`accounting.costCenters.seeds.${row.key}.name`),
        description: t(`accounting.costCenters.seeds.${row.key}.description`),
        sort_order: row.sort_order,
      })
    }
    toast.success(t('accounting.costCenters.toastSeedsOk'))
    await load()
  } catch {
    toast.error(t('accounting.costCenters.toastSeedsFail'))
    await load()
  } finally {
    isApplyingStandardSeeds.value = false
  }
}

onMounted(async () => {
  await load()
  if (String(route.query.openCreate) === '1') {
    await nextTick()
    openCreate()
    await router.replace({
      name: 'AccountingCostCenters',
      params: { departmentId: departmentId.value },
      query: {},
    })
  }
})

function openCreate() {
  resetForm()
  modalOpen.value = true
}

function openEdit(row: AccountingCostCenter) {
  editingId.value = row.id
  form.name = row.name
  form.account_code = row.account_code || ''
  form.description = row.description || ''
  form.sort_order = row.sort_order
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

async function save() {
  const name = form.name.trim()
  if (!name) {
    toast.error(t('accounting.costCenters.toastNameRequired'))
    return
  }
  saving.value = true
  try {
    const payload = {
      name,
      account_code: form.account_code.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sort_order) ? form.sort_order : 0
    }
    if (editingId.value) {
      await updateCostCenter(departmentId.value, editingId.value, payload)
      toast.success(t('accounting.costCenters.toastSaved'))
    } else {
      await createCostCenter(departmentId.value, payload)
      toast.success(t('accounting.costCenters.toastCreated'))
    }
    closeModal()
    await load()
  } catch {
    toast.error(t('accounting.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function onDelete(row: AccountingCostCenter) {
  const ok = await confirmDialog({
    title: t('accounting.costCenters.deleteTitle'),
    message: t('accounting.costCenters.deleteMessage', { name: row.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteCostCenter(departmentId.value, row.id)
    toast.success(t('accounting.common.deleted'))
    await load()
  } catch {
    toast.error(t('accounting.common.deleteFailed'))
  }
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.error-inline {
  padding: 16px;
  border-radius: 8px;
  background: #fef2f2;
  color: #b91c1c;
}
</style>
