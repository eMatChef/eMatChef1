<template>
  <PageShell class="tasks-shell">
    <template #title>{{ t('tasksShell.title') }}</template>
    <template #subtitle>{{ subtitleText }}</template>

    <template v-if="showTasksTabs" #filters>
      <v-tabs
        :model-value="activeShellTab"
        class="tasks-shell-tabs"
        color="primary"
        @update:model-value="onShellTabChange"
      >
        <v-tab v-if="showGaMasterTab" value="ga-master">{{ t('grossanlass.aufgaben.tabMaster') }}</v-tab>
        <v-tab v-if="isGrossanlass" value="ga-mine">{{ t('grossanlass.aufgaben.tabMine') }}</v-tab>
        <v-tab value="general">{{ t('tasksShell.tabGeneral') }}</v-tab>
        <v-tab v-if="!isGrossanlass || canManagePrintTasks" value="inventory">{{ t('tasksShell.tabInventory') }}</v-tab>
        <v-tab v-if="!isGrossanlass || canManagePrintTasks" value="print">{{ t('common.print') }}</v-tab>
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
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import '@/styles/views/tasks-tabs.css'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const authStore = useAuthStore()
const { isUserRole } = useDepartmentMemberRole()
const isGrossanlass = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))
/** Prototyp: alle Grossanlass-Rollen sehen vorerst die MW-Masteransicht; Rollen-Sichten folgen. */
const showGaMasterTab = computed(() => isGrossanlass.value)
const canManagePrintTasks = computed(() => !isUserRole.value)

const showTasksTabs = computed(() => canManagePrintTasks.value || isGrossanlass.value)

const subtitleText = computed(() =>
  showGaMasterTab.value
    ? t('grossanlass.aufgaben.subtitle')
    : isUserRole.value ? t('tasksShell.subtitleUser') : t('tasksShell.subtitleManager')
)

const activeShellTab = computed(() => {
  if (route.name === 'TasksGaMaster') return 'ga-master'
  if (route.name === 'TasksGaMine') return 'ga-mine'
  if (route.name === 'TasksPrint') return 'print'
  if (route.name === 'TasksInventory') return 'inventory'
  return 'general'
})

function onShellTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id) return
  if (tab === 'ga-master') {
    void router.push({ name: 'TasksGaMaster', params: { departmentId: id } })
  } else if (tab === 'ga-mine') {
    void router.push({ name: 'TasksGaMine', params: { departmentId: id } })
  } else if (tab === 'print') {
    void router.push({ name: 'TasksPrint', params: { departmentId: id } })
  } else if (tab === 'inventory') {
    void router.push({ name: 'TasksInventory', params: { departmentId: id } })
  } else {
    void router.push({ name: 'TasksGeneral', params: { departmentId: id } })
  }
}
</script>

