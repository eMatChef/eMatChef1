<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierTeam.title') }}</h1>
      <p v-if="companyName" class="supplier-page-subtitle">{{ companyName }}</p>
    </header>
    <p class="supplier-page-placeholder">{{ t('supplierTeam.placeholder') }}</p>
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

const companyName = computed(() => {
  const companyId = route.params.companyId as string
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId)
  return company?.name || authStore.activeSupplierCompanyName
})
</script>

<style scoped>
.supplier-page {
  max-width: 960px;
  padding: 24px;
}

.supplier-page-header h1 {
  margin: 0 0 8px;
  font-size: 1.75rem;
  font-weight: 600;
  color: #111827;
}

.supplier-page-subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 1rem;
}

.supplier-page-placeholder {
  margin-top: 24px;
  color: #4b5563;
  line-height: 1.5;
}
</style>
