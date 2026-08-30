<template>
  <div class="ga-kategorien-page">
    <p class="ga-kategorien-page__intro">{{ t('grossanlass.einstellungen.kategorienIntro') }}</p>
    <ELoadingState v-if="isLoading" variant="inline" :message="t('common.loading')" />
    <p v-else-if="loadError" class="ga-kategorien-page__error">{{ loadError }}</p>
    <div v-else class="ga-kategorien-page__panel">
      <GrossanlassProcurementCategoryManager
        :department-id="departmentId"
        :categories="categories"
        @created="onCreated"
        @updated="onUpdated"
        @deleted="onDeleted"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementCategoryManager from '@/components/grossanlass/GrossanlassProcurementCategoryManager.vue'
import {
  listGrossanlassProcurementCategories,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'
import {
  descendantIdsOfProcurementCategory,
} from '@/utils/grossanlassProcurementCategoryTree'

defineOptions({ name: 'GrossanlassEinstellungenKategorien' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const categories = ref<GrossanlassProcurementCategory[]>([])
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    categories.value = await listGrossanlassProcurementCategories(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorLoad')
  } finally {
    isLoading.value = false
  }
}

function onCreated(category: GrossanlassProcurementCategory) {
  if (categories.value.some((row) => row.id === category.id)) return
  categories.value = [...categories.value, category]
}

function onUpdated(category: GrossanlassProcurementCategory) {
  categories.value = categories.value.map((row) => (row.id === category.id ? category : row))
}

function onDeleted(categoryId: string) {
  const removed = descendantIdsOfProcurementCategory(categories.value, categoryId)
  categories.value = categories.value.filter((row) => !removed.has(row.id))
}

onMounted(() => {
  void load()
})
watch(departmentId, () => {
  void load()
})
</script>

<style scoped>
.ga-kategorien-page {
  padding: 4px 0 24px;
}

.ga-kategorien-page__intro {
  margin: 0 0 16px;
  color: #64748b;
  font-size: 0.9rem;
}

.ga-kategorien-page__error {
  margin: 0;
  color: #dc2626;
  font-size: 0.88rem;
}

.ga-kategorien-page__panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
</style>
