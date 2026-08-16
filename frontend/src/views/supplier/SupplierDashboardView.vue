<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierDashboard.title') }}</h1>
      <p class="welcome-text">
        {{ t('dashboard.welcome', { name: dashboardDisplayName, date: formatDate(new Date()) }) }}
      </p>
      <p v-if="dashboard?.company_name" class="supplier-page-subtitle">{{ dashboard.company_name }}</p>
    </header>

    <ELoadingState
      v-if="loading"
      variant="inline"
      :message="t('supplierDashboard.loading')"
    />
    <div v-else-if="loadError" class="supplier-page-error">
      <v-alert type="error" variant="tonal" :text="loadError" />
    </div>

    <div v-else class="dashboard-content">
      <div class="offer-grid">
        <router-link
          v-if="dashboard?.sales.offered"
          :to="supplierLink('/catalog')"
          class="offer-card"
        >
          <v-icon icon="mdi-storefront" size="28" class="offer-icon" />
          <h2>{{ t('supplierDashboard.salesTitle') }}</h2>
          <p class="offer-stat">{{ dashboard.sales.item_count }}</p>
          <p class="offer-label">{{ t('supplierDashboard.articleCount') }}</p>
          <span class="offer-cta">{{ t('supplierDashboard.toCatalog') }}</span>
        </router-link>

        <router-link
          v-if="dashboard?.workshop.offered"
          :to="supplierLink('/repairs')"
          class="offer-card"
        >
          <v-icon icon="mdi-hammer-wrench" size="28" class="offer-icon" />
          <h2>{{ t('supplierDashboard.workshopTitle') }}</h2>
          <p class="offer-stat">{{ dashboard.workshop.open_count }}</p>
          <p class="offer-label">{{ t('supplierDashboard.openRepairs') }}</p>
          <span class="offer-cta">{{ t('supplierDashboard.toRepairs') }}</span>
        </router-link>
      </div>

      <p v-if="!dashboard?.sales.offered && !dashboard?.workshop.offered" class="no-offers">
        {{ t('supplierDashboard.noOffers') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getSupplierDashboard, type SupplierDashboard } from '@/api/supplierCompanies'
import { intlLocaleForUiLanguage } from '@/config/languages'
import { useAuthStore } from '@/stores/auth'
import ELoadingState from '@/components/layout/ELoadingState.vue'

const route = useRoute()
const { t, locale } = useI18n()
const authStore = useAuthStore()

const companyId = computed(() => route.params.companyId as string)
const loading = ref(true)
const loadError = ref('')
const dashboard = ref<SupplierDashboard | null>(null)

const intlLocale = computed(() => intlLocaleForUiLanguage(String(locale.value)))
const dashboardDisplayName = computed(() => {
  const nickname = authStore.profile?.nickname?.trim()
  if (nickname) return nickname
  return authStore.userDisplayName
})

function supplierLink(subpath: string): string {
  return `/supplier/${companyId.value}${subpath}`
}

function formatDate(d: Date): string {
  return d.toLocaleDateString(intlLocale.value, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

async function loadDashboard() {
  loading.value = true
  loadError.value = ''
  try {
    const { dashboard: data } = await getSupplierDashboard(companyId.value)
    dashboard.value = data
  } catch {
    loadError.value = t('supplierDashboard.loadFailed')
    dashboard.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
watch(companyId, loadDashboard)
</script>

<style scoped>
.supplier-page {
  max-width: 960px;
  padding: 8px 4px 32px;
}

.supplier-page-header h1 {
  margin: 0 0 4px;
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
}

.welcome-text {
  margin: 0 0 4px;
  color: #6b7280;
  font-size: 0.95rem;
}

.supplier-page-subtitle {
  margin: 0;
  color: #374151;
  font-weight: 600;
}

.supplier-page-error {
  margin-top: 16px;
}

.dashboard-content {
  margin-top: 24px;
}

.offer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.offer-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 20px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.offer-card:hover {
  border-color: #10b981;
  background: #f9fafb;
}

.offer-icon {
  color: #059669;
  margin-bottom: 8px;
}

.offer-card h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
}

.offer-stat {
  margin: 8px 0 0;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.1;
  color: #111827;
}

.offer-label {
  margin: 0 0 12px;
  font-size: 0.875rem;
  color: #6b7280;
}

.offer-cta {
  margin-top: auto;
  font-size: 0.875rem;
  font-weight: 500;
  color: #059669;
}

.no-offers {
  color: #6b7280;
}
</style>
