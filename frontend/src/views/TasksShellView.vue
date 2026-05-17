<template>
  <div class="dept-page tasks-shell">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>{{ t('tasksShell.title') }}</h1>
        <span class="subtitle">{{ subtitleText }}</span>
      </div>
    </div>

    <div v-if="showTasksTabs" class="filter-bar tasks-shell-tabs">
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
          {{ t('tasksShell.tabPrint') }}
        </router-link>
      </div>
    </div>

    <router-view />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()

const departmentId = computed(() => String(route.params.departmentId || ''))

const departmentRole = computed(() => String(authStore.currentDepartmentRole || 'u').toLowerCase().trim())
const isUserRole = computed(() => ['u', 'user'].includes(departmentRole.value))
const canManagePrintTasks = computed(() => !isUserRole.value)

const showTasksTabs = computed(() => canManagePrintTasks.value)

const subtitleText = computed(() =>
  isUserRole.value ? t('tasksShell.subtitleUser') : t('tasksShell.subtitleManager')
)
</script>

<style scoped>
.tasks-shell-tabs {
  margin-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 0;
}
</style>
