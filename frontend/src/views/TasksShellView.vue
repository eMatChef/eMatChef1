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
        <v-tab value="general">{{ t('tasksShell.tabGeneral') }}</v-tab>
        <v-tab value="inventory">{{ t('tasksShell.tabInventory') }}</v-tab>
        <v-tab value="print">{{ t('common.print') }}</v-tab>
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
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import '@/styles/views/tasks-tabs.css'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const { isUserRole } = useDepartmentMemberRole()
const canManagePrintTasks = computed(() => !isUserRole.value)

const showTasksTabs = computed(() => canManagePrintTasks.value)

const subtitleText = computed(() =>
  isUserRole.value ? t('tasksShell.subtitleUser') : t('tasksShell.subtitleManager')
)

const activeShellTab = computed(() => {
  if (route.name === 'TasksPrint') return 'print'
  if (route.name === 'TasksInventory') return 'inventory'
  return 'general'
})

function onShellTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id) return
  if (tab === 'print') {
    void router.push({ name: 'TasksPrint', params: { departmentId: id } })
  } else if (tab === 'inventory') {
    void router.push({ name: 'TasksInventory', params: { departmentId: id } })
  } else {
    void router.push({ name: 'TasksGeneral', params: { departmentId: id } })
  }
}
</script>

