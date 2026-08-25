<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.freigabe.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else>
      <section class="card">
        <h3>{{ t('grossanlass.planung.freigabe.checklistTitle') }}</h3>
        <ul class="checks">
          <li :class="{ ok: pack?.checks.period }">
            <v-icon :icon="pack?.checks.period ? 'mdi-checkbox-marked-outline' : 'mdi-checkbox-blank-outline'" size="20" />
            {{ t('grossanlass.planung.freigabe.checkPeriod') }}
          </li>
          <li :class="{ ok: pack?.checks.ressorts }">
            <v-icon :icon="pack?.checks.ressorts ? 'mdi-checkbox-marked-outline' : 'mdi-checkbox-blank-outline'" size="20" />
            {{ t('grossanlass.planung.freigabe.checkRessorts') }}
          </li>
          <li :class="{ ok: pack?.checks.participants, optional: true }">
            <v-icon :icon="pack?.checks.participants ? 'mdi-checkbox-marked-outline' : 'mdi-checkbox-blank-outline'" size="20" />
            {{ t('grossanlass.planung.freigabe.checkParticipants') }}
            <span class="meta">{{ t('grossanlass.planung.freigabe.checkParticipantsHint') }}</span>
          </li>
        </ul>
      </section>

      <div class="actions">
        <EButton
          variant="primary"
          :disabled="published || !pack?.can_manage || !pack?.checks.period"
          :loading="publishing"
          @click="onPublish"
        >
          {{ published ? t('grossanlass.chain.publishedBadge') : t('grossanlass.planung.freigabe.publish') }}
        </EButton>
        <EButton variant="secondary" :disabled="!published" @click="goGuest">
          {{ t('grossanlass.chain.openGuestView') }}
        </EButton>
      </div>
      <p class="hint">{{ published ? t('grossanlass.planung.freigabe.publishedHint') : t('grossanlass.planung.freigabe.publishHint') }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { EButton } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getGrossanlassPlanung, publishGrossanlass, type GrossanlassPlanungOverview } from '@/api/grossanlassPlanung'

defineOptions({ name: 'GrossanlassPlanungFreigabe' })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const publishing = ref(false)
const error = ref('')
const published = computed(() => pack.value?.config.status === 'published')

async function load() {
  if (!departmentId.value) return
  loading.value = true
  error.value = ''
  try {
    pack.value = await getGrossanlassPlanung(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
  } finally {
    loading.value = false
  }
}

async function onPublish() {
  if (!departmentId.value) return
  publishing.value = true
  try {
    pack.value = await publishGrossanlass(departmentId.value)
    toast.success(t('grossanlass.chain.publishedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    publishing.value = false
  }
}

function goGuest() {
  const id = departmentId.value
  if (id) void router.push(`/${id}/gast-vorschau`)
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 10px; color: #64748b; font-size: 0.9rem; max-width: 560px; }
.warn { color: #9a3412; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
  max-width: 560px;
}
.card h3 { margin: 0 0 12px; font-size: 0.95rem; }
.checks { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.checks li { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; flex-wrap: wrap; }
.checks li.ok { color: #166534; }
.meta { font-size: 0.78rem; color: #94a3b8; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
