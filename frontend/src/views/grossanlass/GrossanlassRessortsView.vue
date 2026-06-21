<template>
  <PageShell
    class="grossanlass-ressorts-shell"
    :title="pageTitle"
  >
    <GrossanlassRessortsTab />
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import GrossanlassRessortsTab from '@/views/grossanlass/GrossanlassRessortsTab.vue'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const pageTitle = computed(() => {
  const id = departmentId.value
  const dept = authStore.departments.find((d) => d.department_id === id)
  return dept?.department?.name || t('grossanlass.label')
})
</script>

<style scoped>
.grossanlass-ressorts-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
