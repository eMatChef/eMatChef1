<template>
  <PageShell class="accounting-shell">
    <template #title>{{ t('accounting.shell.title') }}</template>
    <template #subtitle>{{ t('accounting.shell.subtitle') }}</template>

    <template #filters>
      <v-tabs
        :model-value="activeShellTab"
        class="accounting-shell-tabs"
        color="primary"
        @update:model-value="onShellTabChange"
      >
        <v-tab v-if="canManageAccounting" value="overview">{{ t('accounting.shell.tabOverview') }}</v-tab>
        <v-tab v-if="canManageAccounting" value="cost-centers">{{ t('accounting.shell.tabCostCenters') }}</v-tab>
        <v-tab v-if="canManageAccounting" value="bookings">{{ t('accounting.shell.tabBookings') }}</v-tab>
        <v-tab v-if="canViewGroupCosts" value="groups">{{ t('accounting.shell.tabGroups') }}</v-tab>
        <v-tab v-if="canManageAccounting" value="material-costs">{{ t('accounting.shell.tabMaterialCosts') }}</v-tab>
        <v-tab v-if="canManageAccounting" value="amortization">{{ t('accounting.shell.tabAmortization') }}</v-tab>
        <v-tab v-if="canManageAccounting" value="budget">{{ t('accounting.shell.tabBudget') }}</v-tab>
      </v-tabs>
    </template>

    <router-view />
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import { useAccountingAccess } from '@/composables/useAccountingAccess'
import '@/styles/views/accounting-tabs.css'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const departmentId = computed(() => String(route.params.departmentId || ''))
const { canManageAccounting, canViewGroupCosts, ensureGroupsForAccess } = useAccountingAccess(
  () => departmentId.value,
)

async function applyAccessRedirects() {
  await ensureGroupsForAccess()
  if (!canViewGroupCosts.value && route.path.includes('/accounting')) {
    void router.replace({ name: 'Dashboard', params: { departmentId: departmentId.value } })
    return
  }
  if (!canManageAccounting.value && canViewGroupCosts.value && route.name === 'AccountingOverview') {
    void router.replace({ name: 'AccountingGroupCosts', params: { departmentId: departmentId.value } })
  }
}

onMounted(() => {
  void applyAccessRedirects()
})

watch(departmentId, () => {
  void applyAccessRedirects()
})

const activeShellTab = computed(() => {
  const name = route.name
  if (name === 'AccountingCostCenters') return 'cost-centers'
  if (name === 'AccountingBookings') return 'bookings'
  if (name === 'AccountingGroupCosts') return 'groups'
  if (name === 'AccountingMaterialCosts') return 'material-costs'
  if (name === 'AccountingAmortization') return 'amortization'
  if (name === 'AccountingBudget') return 'budget'
  return 'overview'
})

function onShellTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id) return
  const routes: Record<string, string> = {
    overview: 'AccountingOverview',
    'cost-centers': 'AccountingCostCenters',
    bookings: 'AccountingBookings',
    groups: 'AccountingGroupCosts',
    'material-costs': 'AccountingMaterialCosts',
    amortization: 'AccountingAmortization',
    budget: 'AccountingBudget',
  }
  const name = routes[String(tab)] || (canManageAccounting.value ? 'AccountingOverview' : 'AccountingGroupCosts')
  void router.push({ name, params: { departmentId: id } })
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';
</style>
