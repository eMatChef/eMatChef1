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
        <v-tab value="overview">{{ t('accounting.shell.tabOverview') }}</v-tab>
        <v-tab value="cost-centers">{{ t('accounting.shell.tabCostCenters') }}</v-tab>
        <v-tab value="bookings">{{ t('accounting.shell.tabBookings') }}</v-tab>
        <v-tab value="material-costs">{{ t('accounting.shell.tabMaterialCosts') }}</v-tab>
        <v-tab value="budget">{{ t('accounting.shell.tabBudget') }}</v-tab>
      </v-tabs>
    </template>

    <router-view />
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/accounting-tabs.css'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const departmentId = computed(() => String(route.params.departmentId || ''))

const activeShellTab = computed(() => {
  const name = route.name
  if (name === 'AccountingCostCenters') return 'cost-centers'
  if (name === 'AccountingBookings') return 'bookings'
  if (name === 'AccountingMaterialCosts') return 'material-costs'
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
    'material-costs': 'AccountingMaterialCosts',
    budget: 'AccountingBudget',
  }
  const name = routes[String(tab)] || 'AccountingOverview'
  void router.push({ name, params: { departmentId: id } })
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';
</style>
