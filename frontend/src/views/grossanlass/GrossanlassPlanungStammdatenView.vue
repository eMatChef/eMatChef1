<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.stammdaten.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else>
      <div class="form">
        <ETextField
          :model-value="pack?.department_name || deptName"
          :label="t('grossanlass.planung.stammdaten.name')"
          disabled
          hide-details
        />
        <EDateRangeField
          v-model:start="periodStart"
          v-model:end="periodEnd"
          class="mt-3"
          :label="t('grossanlass.planung.stammdaten.period')"
          :department-id="departmentId"
          :disabled="!canManage"
          :allow-past="true"
          :block-closed-dates="false"
          :show-presets="false"
          :show-markers="true"
        />
        <ETextField
          v-model="locationText"
          class="mt-3"
          :label="t('grossanlass.planung.stammdaten.location')"
          :placeholder="t('grossanlass.planung.stammdaten.locationPlaceholder')"
          :disabled="!canManage"
          hide-details
        />
        <p class="hint">
          <router-link :to="`/${departmentId}/einstellungen/standorte`">
            {{ t('grossanlass.planung.stammdaten.openStandorte') }}
          </router-link>
        </p>
        <ETextarea
          v-model="notes"
          class="mt-3"
          :label="t('grossanlass.planung.stammdaten.notes')"
          :placeholder="t('grossanlass.planung.stammdaten.notesPlaceholder')"
          :disabled="!canManage"
          rows="3"
          hide-details="auto"
        />
        <div v-if="canManage" class="actions">
          <EButton variant="primary" size="small" :loading="saving" @click="save">
            {{ t('common.save') }}
          </EButton>
        </div>
      </div>

      <section class="panel">
        <GrossanlassKeyDatesPanel :department-id="departmentId" />
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import GrossanlassKeyDatesPanel from '@/components/grossanlass/GrossanlassKeyDatesPanel.vue'
import { EButton, EDateRangeField, ETextField, ETextarea } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getGrossanlassPlanung, updateGrossanlassPlanung, type GrossanlassPlanungOverview } from '@/api/grossanlassPlanung'

defineOptions({ name: 'GrossanlassPlanungStammdaten' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const membership = computed(() =>
  authStore.departments.find((d) => d.department_id === departmentId.value),
)
const deptName = computed(() => membership.value?.department?.name || '')

const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const periodStart = ref('')
const periodEnd = ref('')
const locationText = ref('')
const notes = ref('')
const canManage = computed(() => pack.value?.can_manage !== false)

function toDay(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : ''
}

function apply(next: GrossanlassPlanungOverview) {
  pack.value = next
  periodStart.value = toDay(next.config.planned_event_start)
  periodEnd.value = toDay(next.config.planned_event_end)
  locationText.value = next.config.location_text || ''
  notes.value = next.config.notes || ''
}

async function load() {
  if (!departmentId.value) return
  loading.value = true
  error.value = ''
  try {
    apply(await getGrossanlassPlanung(departmentId.value))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!departmentId.value) return
  saving.value = true
  try {
    apply(await updateGrossanlassPlanung(departmentId.value, {
      location_text: locationText.value,
      notes: notes.value,
      planned_event_start: periodStart.value || undefined,
      planned_event_end: periodEnd.value || null,
    }))
    toast.success(t('grossanlass.planung.stammdaten.saved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.hint a { color: #166534; }
.warn { color: #9a3412; }
.form { max-width: 520px; }
.mt-3 { margin-top: 12px; }
.actions { margin-top: 16px; }
.panel {
  margin-top: 24px;
  max-width: 640px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
</style>
