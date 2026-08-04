<template>
  <PageShell class="accounting-shell">
    <template #title>{{ t('accounting.shell.title') }}</template>
    <template #subtitle>{{ t('accounting.shell.subtitle') }}</template>

    <template #filters>
      <v-tabs
        :model-value="activePrimary"
        class="accounting-shell-tabs"
        color="primary"
      >
        <v-tab v-if="canManageAccounting" value="overview" @click="onPrimaryChange('overview')">
          {{ t('accounting.shell.groupOverview') }}
        </v-tab>
        <v-tab v-if="canManageAccounting" value="capture" @click="onPrimaryChange('capture')">
          {{ t('accounting.shell.groupCapture') }}
        </v-tab>
        <v-tab v-if="canViewGroupCosts" value="reports" @click="onPrimaryChange('reports')">
          {{ t('accounting.shell.groupReports') }}
        </v-tab>
        <v-tab v-if="canManageAccounting" value="master" @click="onPrimaryChange('master')">
          {{ t('accounting.shell.groupMaster') }}
        </v-tab>
      </v-tabs>

      <v-tabs
        v-if="detailTabs.length > 1"
        :model-value="activeDetail"
        class="accounting-detail-tabs"
        color="primary"
        density="compact"
      >
        <v-tab
          v-for="tab in detailTabs"
          :key="tab.value"
          :value="tab.value"
          @click="onDetailChange(tab.value)"
        >
          {{ tab.label }}
        </v-tab>
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

type PrimaryGroup = 'overview' | 'capture' | 'reports' | 'master'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const departmentId = computed(() => String(route.params.departmentId || ''))
const { canManageAccounting, canViewGroupCosts, ensureGroupsForAccess } = useAccountingAccess(
  () => departmentId.value,
)

const routeByDetail: Record<string, string> = {
  overview: 'AccountingOverview',
  bookings: 'AccountingBookings',
  'cost-centers': 'AccountingCostCenters',
  groups: 'AccountingGroupCosts',
  'material-costs': 'AccountingMaterialCosts',
  amortization: 'AccountingAmortization',
  budget: 'AccountingBudget',
}

const primaryByRouteName: Record<string, PrimaryGroup> = {
  AccountingOverview: 'overview',
  AccountingBookings: 'capture',
  AccountingCostCenters: 'master',
  AccountingGroupCosts: 'reports',
  AccountingMaterialCosts: 'reports',
  AccountingAmortization: 'reports',
  AccountingBudget: 'reports',
}

const detailByRouteName: Record<string, string> = {
  AccountingOverview: 'overview',
  AccountingBookings: 'bookings',
  AccountingCostCenters: 'cost-centers',
  AccountingGroupCosts: 'groups',
  AccountingMaterialCosts: 'material-costs',
  AccountingAmortization: 'amortization',
  AccountingBudget: 'budget',
}

async function applyAccessRedirects() {
  await ensureGroupsForAccess()
  if (!canViewGroupCosts.value && route.path.includes('/accounting')) {
    void router.replace({ name: 'Dashboard', params: { departmentId: departmentId.value } })
    return
  }
  if (!canManageAccounting.value && canViewGroupCosts.value) {
    const name = String(route.name || '')
    if (name !== 'AccountingGroupCosts') {
      void router.replace({ name: 'AccountingGroupCosts', params: { departmentId: departmentId.value } })
    }
  }
}

onMounted(() => {
  void applyAccessRedirects()
})

watch(departmentId, () => {
  void applyAccessRedirects()
})

const activePrimary = computed<PrimaryGroup>(() => {
  const name = String(route.name || '')
  return primaryByRouteName[name] || (canManageAccounting.value ? 'overview' : 'reports')
})

const activeDetail = computed(() => {
  const name = String(route.name || '')
  return detailByRouteName[name] || ''
})

const detailTabs = computed(() => {
  const primary = activePrimary.value
  if (primary === 'reports') {
    const tabs: { value: string; label: string }[] = [
      { value: 'groups', label: t('accounting.shell.tabGroups') },
    ]
    if (canManageAccounting.value) {
      tabs.push(
        { value: 'material-costs', label: t('accounting.shell.tabMaterialCosts') },
        { value: 'amortization', label: t('accounting.shell.tabAmortization') },
        { value: 'budget', label: t('accounting.shell.tabBudget') },
      )
    }
    return tabs
  }
  // Erfassen / Stammdaten / Übersicht: kein Shell-Detail-Tab (Buchungen hat eigene Innen-Tabs)
  return []
})

function pushRoute(routeName: string) {
  const id = departmentId.value
  if (!id) return
  void router.push({ name: routeName, params: { departmentId: id } })
}

function onPrimaryChange(tab: unknown) {
  const primary = String(tab) as PrimaryGroup
  if (primary === 'overview') {
    pushRoute('AccountingOverview')
    return
  }
  if (primary === 'capture') {
    pushRoute('AccountingBookings')
    return
  }
  if (primary === 'master') {
    pushRoute('AccountingCostCenters')
    return
  }
  if (primary === 'reports') {
    // Bleibe in Auswertungen, wenn schon dort; sonst Gruppenkosten
    if (activePrimary.value === 'reports') {
      return
    }
    pushRoute('AccountingGroupCosts')
  }
}

function onDetailChange(tab: unknown) {
  const detail = String(tab)
  const routeName = routeByDetail[detail]
  if (routeName) pushRoute(routeName)
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';
</style>
