<template>
  <span v-if="status?.supported" class="demo-reset">
    <button type="button" class="demo-reset__button" data-testid="demo-reset-open" @click="openDialog">
      <i class="mdi mdi-restore-alert" aria-hidden="true" />
      {{ t('demoReset.button') }}
    </button>

    <EDialog v-model="open" :max-width="720" :title="t('demoReset.title')" scrollable>
      <p v-if="loading" class="demo-reset__muted">{{ t('demoReset.loading') }}</p>
      <div v-else-if="error" class="demo-reset__alert" role="alert">{{ error }}</div>
      <template v-else-if="preview">
        <p class="demo-reset__lead">
          {{ t('demoReset.lead', { department: preview.plan.department.name, scenario: status?.label ?? preview.plan.scenario }) }}
        </p>

        <div v-if="preview.plan.blocked.length" class="demo-reset__alert" role="alert">
          <strong>{{ t('demoReset.blockedTitle') }}</strong>
          <ul class="demo-reset__list">
            <li v-for="reason in preview.plan.blocked" :key="reason">{{ reason }}</li>
          </ul>
        </div>

        <section class="demo-reset__section">
          <h3>{{ t('demoReset.deleteTitle') }}</h3>
          <ul class="demo-reset__list">
            <li>{{ t('demoReset.deleteGroups', { n: preview.plan.delete.groups.length }) }}<span v-if="groupNames"> ({{ groupNames }})</span></li>
            <li>{{ t('demoReset.deleteGroupMembers', { n: preview.plan.delete.group_members }) }}</li>
            <li>{{ t('demoReset.deletePlaces', { n: preview.plan.delete.places }) }}</li>
            <li>{{ t('demoReset.deleteAddresses', { n: preview.plan.delete.addresses.length }) }}</li>
            <li>{{ t('demoReset.deleteJoinRequests', { n: preview.plan.delete.join_requests }) }}</li>
          </ul>
        </section>

        <section class="demo-reset__section">
          <h3>{{ t('demoReset.restoreTitle') }}</h3>
          <ul class="demo-reset__list">
            <li v-for="[field, change] in configChanges" :key="field">
              {{ t(`demoReset.fields.${field}`) }}: <span class="demo-reset__value">{{ format(change.from) }}</span> → <span class="demo-reset__value">{{ format(change.to) }}</span>
            </li>
            <li v-for="item in preview.plan.restore.managed" :key="item">{{ managedLabel(item) }}</li>
            <li v-if="preview.plan.restore.recreated > 0">{{ t('demoReset.recreated', { n: preview.plan.restore.recreated }) }}</li>
            <li>{{ t('demoReset.restoreSetup') }}</li>
            <li>{{ t('demoReset.restoreClock') }}</li>
          </ul>
        </section>

        <section class="demo-reset__section">
          <h3>{{ t('demoReset.keepTitle') }}</h3>
          <ul class="demo-reset__list">
            <li>{{ t('demoReset.keepAccounts') }}</li>
            <li>{{ t('demoReset.keepMemberships') }}</li>
            <li v-if="extraMemberships.length">{{ t('demoReset.keepExtraMemberships', { n: extraMemberships.length }) }}</li>
            <li>{{ t('demoReset.keepOthers') }}</li>
          </ul>
        </section>

        <ECheckbox v-if="!preview.plan.blocked.length" v-model="confirmed" :label="t('demoReset.confirm')" hide-details />
      </template>

      <template #actions>
        <EButton variant="secondary" size="small" @click="open = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          data-testid="demo-reset-execute"
          :disabled="!canExecute"
          :loading="executing"
          @click="execute"
        >
          {{ t('demoReset.execute') }}
        </EButton>
      </template>
    </EDialog>
  </span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EButton, ECheckbox, EDialog } from '@/components/form/base'
import {
  executeDemoReset,
  getDemoResetStatus,
  previewDemoReset,
  type DemoResetConfigChange,
  type DemoResetPreview,
  type DemoResetStatus,
} from '@/api/demoReset'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()

const status = ref<DemoResetStatus | null>(null)
const open = ref(false)
const loading = ref(false)
const executing = ref(false)
const error = ref('')
const preview = ref<DemoResetPreview | null>(null)
const confirmed = ref(false)

const departmentId = computed(() => (typeof route?.params?.departmentId === 'string' ? route.params.departmentId : ''))

async function loadStatus() {
  status.value = null
  if (!departmentId.value || !authStore.isLoggedIn) return
  const id = departmentId.value
  try {
    const result = await getDemoResetStatus(id)
    if (id === departmentId.value) status.value = result
  } catch {
    status.value = null
  }
}

watch([departmentId, () => authStore.isLoggedIn], loadStatus, { immediate: true })

const groupNames = computed(() => (preview.value?.plan.delete.groups ?? []).map((g) => g.name).join(', '))
const configChanges = computed(() => Object.entries(preview.value?.plan.restore.config ?? {}) as [string, DemoResetConfigChange][])
const extraMemberships = computed(() => preview.value?.plan.keep.memberships_extra ?? [])
const canExecute = computed(() => !!preview.value && preview.value.plan.blocked.length === 0 && confirmed.value && !executing.value && !loading.value)

function format(value: unknown): string {
  if (value === null || value === undefined || value === '') return t('demoReset.empty')
  if (typeof value === 'boolean') return value ? t('demoReset.yes') : t('demoReset.no')
  if (Array.isArray(value)) return value.length ? value.join(', ') : t('demoReset.empty')
  return String(value)
}

/** «grossanlass-setup:membership:ga-ok (role)» → verständlicher Text. */
function managedLabel(item: string): string {
  const match = /^[^:]+:(department|config|membership)(?::([^ ]+))? \((.*)\)$/.exec(item)
  if (!match) return item
  const [, kind, account, fields] = match
  return t(`demoReset.managed.${kind}`, { account: account ?? '', fields })
}

async function openDialog() {
  open.value = true
  confirmed.value = false
  preview.value = null
  error.value = ''
  loading.value = true
  try {
    preview.value = await previewDemoReset(departmentId.value)
  } catch (err) {
    error.value = messageOf(err)
  } finally {
    loading.value = false
  }
}

async function execute() {
  if (!preview.value || !status.value?.scenario) return
  executing.value = true
  error.value = ''
  const id = departmentId.value
  try {
    await executeDemoReset(id, status.value.scenario, preview.value.plan_hash)
    open.value = false
    // Der Einrichtungsstand (offen/freigegeben) steckt in der Sitzung: komplett neu laden und das Setup-Dashboard öffnen.
    window.location.assign(`/${id}/dept/dashboard`)
  } catch (err) {
    error.value = messageOf(err)
    preview.value = null
  } finally {
    executing.value = false
  }
}

function messageOf(err: unknown): string {
  const data = (err as { response?: { data?: { error?: string } } })?.response?.data
  return data?.error || t('demoReset.errorGeneric')
}
</script>

<style scoped>
.demo-reset {
  display: inline-flex;
  align-items: center;
  margin-left: 0.75rem;
}

.demo-reset__button {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.15rem 0.6rem;
  border: 1px solid #854d0e;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.55);
  color: #422006;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.demo-reset__button:hover,
.demo-reset__button:focus-visible {
  background: #fff;
}

.demo-reset__alert {
  margin-bottom: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 6px;
  background: #fee2e2;
  color: #7f1d1d;
}

.demo-reset__lead {
  margin: 0 0 0.75rem;
}

.demo-reset__muted {
  color: #6b7280;
}

.demo-reset__section {
  margin: 0 0 0.9rem;
}

.demo-reset__section h3 {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
}

.demo-reset__list {
  list-style: disc;
  margin: 0;
  padding-left: 1.2rem;
}

.demo-reset__value {
  font-weight: 600;
}
</style>
