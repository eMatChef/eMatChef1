<template>
  <div class="helferauftrag-print">
    <div class="print-toolbar">
      <EButton variant="secondary" size="small" @click="goBack">{{ t('common.back') }}</EButton>
      <EButton
        v-if="canPrintHelper"
        variant="primary"
        size="small"
        :disabled="!briefing"
        @click="printSheet"
      >
        {{ t('grossanlass.planung.ressorts.printHelper') }}
      </EButton>
    </div>
    <p v-if="!canPrintHelper" class="muted">{{ t('grossanlass.planung.ressorts.printOnlyWhenFixed') }}</p>
    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <GrossanlassHelferauftragSheet v-else-if="briefing && canPrintHelper" :briefing="briefing" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { EButton } from '@/components/form/base'
import GrossanlassHelferauftragSheet from '@/components/grossanlass/GrossanlassHelferauftragSheet.vue'
import { getGrossanlassBauprojekt, type GaBauprojektBriefing } from '@/api/grossanlassBauprojekt'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const canPrintHelper = computed(() => {
  const departmentId = String(route.params.departmentId || '')
  const row = authStore.departments.find((d) => d.department_id === departmentId)
  return (row?.department?.grossanlass_config?.status || 'draft') !== 'draft'
})

const loading = ref(false)
const error = ref<string | null>(null)
const briefing = ref<GaBauprojektBriefing | null>(null)

async function load() {
  const departmentId = String(route.params.departmentId || '')
  const groupId = String(route.params.groupId || '')
  if (!departmentId || !groupId) return
  loading.value = true
  error.value = null
  try {
    briefing.value = await getGrossanlassBauprojekt(departmentId, groupId)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('public.lookup.notFound')
  } finally {
    loading.value = false
  }
}

function printSheet() {
  window.print()
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }
  void router.push({ name: 'GrossanlassRessorts', params: { departmentId: route.params.departmentId } })
}

onMounted(load)
</script>

<style scoped>
.helferauftrag-print { padding: 16px; }
.print-toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.muted { color: #64748b; }
.error { color: #b91c1c; }
@media print {
  .print-toolbar { display: none; }
}
</style>
