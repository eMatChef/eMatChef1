<template>
  <PageShell class="tasks-shell">
    <template #title>{{ t('tasksShell.title') }}</template>
    <template #subtitle>{{ subtitleText }}</template>

    <template v-if="showTasksTabs" #filters>
      <div class="filter-bar tasks-shell-tabs">
        <div class="filter-tabs">
          <router-link
            :to="{ name: 'TasksGeneral', params: { departmentId } }"
            class="filter-tab"
            active-class="active"
          >
            {{ t('tasksShell.tabGeneral') }}
          </router-link>
          <router-link
            v-if="canManagePrintTasks"
            :to="{ name: 'TasksPrint', params: { departmentId } }"
            class="filter-tab"
            active-class="active"
          >
            {{ t('common.print') }}
          </router-link>
        </div>
      </div>
    </template>

    <router-view />
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const { isUserRole } = useDepartmentMemberRole()
const canManagePrintTasks = computed(() => !isUserRole.value)

const showTasksTabs = computed(() => canManagePrintTasks.value)

const subtitleText = computed(() =>
  isUserRole.value ? t('tasksShell.subtitleUser') : t('tasksShell.subtitleManager')
)
</script>

<style scoped>
.tasks-shell-tabs {
  margin-bottom: 0;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0;
}
</style>
