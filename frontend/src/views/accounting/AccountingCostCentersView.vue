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
      <EButton
        v-if="items.length > 0 && hasMissingDefaults"
        variant="secondary"
        :disabled="isLoading || isApplyingStandardSeeds"
        :loading="isApplyingStandardSeeds"
        @click="createStandardCostCenters"
      >
        {{ t('accounting.costCenters.applyMissingDefaults') }}
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

    <section v-if="items.length" class="rules-section">
      <h2 class="rules-section-title">{{ t('accounting.costCenters.rulesTitle') }}</h2>
      <p class="description rules-intro">{{ t('accounting.costCenters.rulesIntro') }}</p>
      <div class="cost-centers-table-wrap">
        <table class="cost-centers-table rules-table">
          <thead>
            <tr>
              <th>{{ t('accounting.costCenters.rulesColSource') }}</th>
              <th>{{ t('accounting.costCenters.rulesColCostCenter') }}</th>
              <th>{{ t('accounting.costCenters.rulesColEntryType') }}</th>
              <th>{{ t('accounting.costCenters.rulesColPayment') }}</th>
              <th class="col-actions">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sk in ruleSourceKinds" :key="sk">
              <td>{{ t(accountingFollowUpKindKey(sk)) }}</td>
              <td>
                <ESelect
                  :model-value="ruleDrafts[sk]?.cost_center_id || ''"
                  :items="costCenterSelectItems"
                  density="compact"
                  hide-details
                  @update:model-value="(v) => setRuleDraft(sk, 'cost_center_id', String(v))"
                />
              </td>
              <td>
                <ESelect
                  :model-value="ruleDrafts[sk]?.default_entry_type || ''"
                  :items="entryTypeSelectItems"
                  density="compact"
                  hide-details
                  @update:model-value="(v) => setRuleDraft(sk, 'default_entry_type', String(v))"
                />
              </td>
              <td>
                <ESelect
                  :model-value="ruleDrafts[sk]?.default_payment_method || ''"
                  :items="paymentSelectItems"
                  density="compact"
                  hide-details
                  @update:model-value="(v) => setRuleDraft(sk, 'default_payment_method', String(v))"
                />
              </td>
              <td class="col-actions">
                <EButton variant="secondary" size="small" :loading="ruleSaving === sk" @click="saveRule(sk)">
                  {{ t('accounting.costCenters.rulesSave') }}
                </EButton>
                <EButton
                  v-if="rulesByKind[sk]"
                  variant="text"
                  size="small"
                  color="error"
                  @click="removeRule(sk)"
                >
                  {{ t('accounting.costCenters.rulesDelete') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

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
  bootstrapCostCenters,
  type AccountingCostCenter
} from '@/api/accountingCostCenters'
import {
  listCostCenterRules,
  upsertCostCenterRule,
  deleteCostCenterRule,
  COST_CENTER_RULE_SOURCE_KINDS,
  type AccountingCostCenterRule,
} from '@/api/accountingCostCenterRules'
import { accountingFollowUpKindKey } from '@/utils/accountingFollowUpLabels'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

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

const form = reactive({
  name: '',
  account_code: '' as string,
  description: '' as string,
  sort_order: 0
})

const ruleSourceKinds = COST_CENTER_RULE_SOURCE_KINDS
const rules = ref<AccountingCostCenterRule[]>([])
const ruleDrafts = reactive<Record<string, { cost_center_id: string; default_entry_type: string; default_payment_method: string }>>({})
const ruleSaving = ref<string | null>(null)

const rulesByKind = computed(() => {
  const map: Record<string, AccountingCostCenterRule> = {}
  for (const r of rules.value) {
    map[r.source_kind] = r
  }
  return map
})

const costCenterSelectItems = computed(() =>
  items.value.map((c) => ({ title: c.name, value: c.id }))
)

/** Fehlende Standard-Regeln → Button «Defaults ergänzen» anbieten. */
const hasMissingDefaults = computed(() =>
  ruleSourceKinds.some((sk) => !rulesByKind.value[sk]),
)

const ENTRY_KEYS = ['purchase', 'repair_external', 'repair_internal', 'amortization', 'other'] as const
const PAYMENT_KEYS = ['advance_mw', 'cash_group', 'supplier_invoice', 'association', 'other'] as const

const entryTypeSelectItems = computed(() => [
  { title: t('accounting.common.dash'), value: '' },
  ...ENTRY_KEYS.map((k) => ({ title: t(`accounting.entryType.${k}`), value: k })),
])
const paymentSelectItems = computed(() => [
  { title: t('accounting.common.dash'), value: '' },
  ...PAYMENT_KEYS.map((k) => ({ title: t(`accounting.paymentMethod.${k}`), value: k })),
])

function syncRuleDrafts() {
  for (const sk of ruleSourceKinds) {
    const existing = rulesByKind.value[sk]
    ruleDrafts[sk] = {
      cost_center_id: existing?.cost_center_id || '',
      default_entry_type: existing?.default_entry_type || '',
      default_payment_method: existing?.default_payment_method || '',
    }
  }
}

function setRuleDraft(sk: string, field: 'cost_center_id' | 'default_entry_type' | 'default_payment_method', value: string) {
  if (!ruleDrafts[sk]) {
    ruleDrafts[sk] = { cost_center_id: '', default_entry_type: '', default_payment_method: '' }
  }
  ruleDrafts[sk][field] = value
}

async function loadRules() {
  try {
    rules.value = await listCostCenterRules(departmentId.value)
  } catch {
    rules.value = []
  }
  syncRuleDrafts()
}

async function saveRule(sk: string) {
  const draft = ruleDrafts[sk]
  if (!draft?.cost_center_id) return
  ruleSaving.value = sk
  try {
    await upsertCostCenterRule(departmentId.value, {
      source_kind: sk,
      cost_center_id: draft.cost_center_id,
      default_entry_type: draft.default_entry_type || null,
      default_payment_method: draft.default_payment_method || null,
    })
    toast.success(t('accounting.costCenters.rulesSaved'))
    await loadRules()
  } catch {
    toast.error(t('accounting.common.saveFailed'))
  } finally {
    ruleSaving.value = null
  }
}

async function removeRule(sk: string) {
  const rule = rulesByKind.value[sk]
  if (!rule) return
  try {
    await deleteCostCenterRule(departmentId.value, rule.id)
    toast.success(t('accounting.costCenters.rulesDeleted'))
    await loadRules()
  } catch {
    toast.error(t('accounting.common.deleteFailed'))
  }
}

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
    await loadRules()
  } catch (e: unknown) {
    const msg = e && typeof e === 'object' && 'response' in e ? (e as { response?: { data?: { error?: string } } }).response?.data?.error : null
    loadError.value = msg || t('accounting.costCenters.loadError')
    items.value = []
  } finally {
    isLoading.value = false
  }
}

async function createStandardCostCenters() {
  if (!departmentId.value) return
  isApplyingStandardSeeds.value = true
  try {
    const result = await bootstrapCostCenters(departmentId.value)
    if (result.cost_centers_created === 0 && result.rules_created === 0) {
      toast.success(t('accounting.costCenters.toastSeedsNone'))
    } else {
      toast.success(
        t('accounting.costCenters.toastSeedsOk', {
          centers: result.cost_centers_created,
          rules: result.rules_created,
        }),
      )
    }
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

.rules-section {
  margin-top: 36px;
}

.rules-section-title {
  font-size: 1.05rem;
  font-weight: 600;
  margin-bottom: 8px;
}

.rules-intro {
  margin-bottom: 16px;
  font-size: 14px;
}
</style>
