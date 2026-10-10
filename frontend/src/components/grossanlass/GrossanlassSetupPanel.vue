<template>
  <section class="ga-setup" data-onboarding="ga-setup-panel">
    <header class="ga-setup__head">
      <h2 class="ga-setup__title">{{ t('grossanlass.setup.title') }}</h2>
      <p class="ga-setup__lead">{{ canSetup ? t('grossanlass.setup.lead') : t('grossanlass.setup.leadOther') }}</p>
    </header>

    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <v-alert v-else-if="forbidden" type="info" variant="tonal" :text="t('grossanlass.setup.pendingForRole')" />
    <v-alert v-else-if="error" type="error" variant="tonal" :text="error" />

    <template v-else-if="status">
      <ol class="ga-setup__steps">
        <li
          v-for="step in status.steps"
          :key="step.id"
          class="ga-setup__step"
          :class="{ 'is-done': step.done }"
          :data-testid="`ga-setup-step-${step.id}`"
        >
          <v-icon :icon="step.done ? 'mdi-check-circle' : 'mdi-circle-outline'" size="22" class="ga-setup__icon" />
          <div class="ga-setup__body">
            <strong>{{ t(`grossanlass.setup.steps.${step.id}.title`) }}</strong>
            <span class="ga-setup__hint">{{ t(`grossanlass.setup.steps.${step.id}.hint`) }}</span>
            <ul v-if="!step.done" class="ga-setup__missing">
              <li v-for="(missing, index) in step.missing" :key="index">{{ missingLabel(missing) }}</li>
            </ul>
          </div>
          <router-link v-if="canSetup" :to="stepLink(step.id)" class="ga-setup__open">
            {{ t('grossanlass.setup.open') }}
          </router-link>
        </li>
      </ol>

      <div class="ga-setup__actions" data-onboarding="ga-setup-release">
        <template v-if="status.can_release">
          <EButton variant="primary" :disabled="!status.complete || releasing" :loading="releasing" @click="release">
            {{ t('grossanlass.setup.release') }}
          </EButton>
          <span v-if="!status.complete" class="ga-setup__hint">{{ t('grossanlass.setup.releaseBlocked') }}</span>
        </template>
        <span v-else-if="canSetup" class="ga-setup__hint">{{ t('grossanlass.setup.releaseByMwOrOk') }}</span>
        <EButton v-if="canTour" variant="secondary" size="small" @click="startTour">
          {{ t('grossanlass.setup.startTour') }}
        </EButton>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useOnboardingTour } from '@/composables/useOnboardingTour'
import { canUseGrossanlassSetupTour } from '@/utils/onboardingGate'
import { isOnboardingTourCompleted } from '@/utils/onboardingTourProgress'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'
import {
  getGrossanlassSetup,
  releaseGrossanlassSetup,
  type GaSetupMissing,
  type GaSetupStatus,
  type GaSetupStepId,
} from '@/api/grossanlassSetup'

const props = defineProps<{ departmentId: string }>()
const emit = defineEmits<{ (e: 'released'): void }>()

const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const { startTour: launchTour, canStartToursOnViewport } = useOnboardingTour({ bindTargetSync: false })

const status = ref<GaSetupStatus | null>(null)
const loading = ref(true)
const forbidden = ref(false)
const error = ref('')
const releasing = ref(false)

const canSetup = computed(() => status.value?.can_setup ?? false)
const canTour = computed(() => canUseGrossanlassSetupTour(authStore, props.departmentId) && canStartToursOnViewport.value)

function stepLink(step: GaSetupStepId): string {
  const base = `/${props.departmentId}`
  if (step === 'stammdaten') return `${base}/ga/activity-settings/general`
  if (step === 'ressorts') return `${base}/ga/activity-settings/units`
  return `${base}/ga/activity-settings/users`
}

function missingLabel(missing: GaSetupMissing): string {
  return t(`grossanlass.setup.missing.${missing.code}`, { name: missing.name ?? '' })
}

async function load() {
  loading.value = true
  forbidden.value = false
  error.value = ''
  try {
    status.value = await getGrossanlassSetup(props.departmentId)
  } catch (e: unknown) {
    const response = (e as { response?: { status?: number } }).response
    if (response?.status === 403) forbidden.value = true
    else error.value = apiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function release() {
  if (releasing.value) return
  releasing.value = true
  try {
    status.value = await releaseGrossanlassSetup(props.departmentId)
    authStore.markGrossanlassSetupReleased(props.departmentId)
    toast.success(t('grossanlass.setup.releasedToast'))
    emit('released')
  } catch (e: unknown) {
    // 422: Pflichtdaten fehlen (Stand neu laden); 403: keine Berechtigung
    toast.error(apiErrorMessage(e, t('grossanlass.setup.releaseError')))
    await load()
  } finally {
    releasing.value = false
  }
}

function startTour() {
  launchTour('ga-setup', props.departmentId)
}

/** Einmaliger Start beim ersten Öffnen; danach nur noch über Knopf oder Hilfe. Der Fortschritt gehört dem Benutzer. */
function autoStartTourOnce() {
  const profileId = authStore.profileId
  if (!profileId || !canTour.value || !canSetup.value || status.value?.released) return
  if (isOnboardingTourCompleted(profileId, props.departmentId, 'ga-setup')) return
  const key = `ga_setup_tour_offered_${profileId}_${props.departmentId}`
  try {
    if (localStorage.getItem(key)) return
    localStorage.setItem(key, '1')
  } catch {
    return
  }
  startTour()
}

onMounted(async () => {
  await load()
  autoStartTourOnce()
})
</script>

<style scoped>
.ga-setup {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border: 1px solid var(--color-border, rgba(0, 0, 0, 0.12));
  border-radius: 12px;
  background: var(--color-surface, #fff);
}
.ga-setup__title {
  margin: 0;
  font-size: 1.15rem;
}
.ga-setup__lead,
.ga-setup__hint {
  margin: 4px 0 0;
  color: var(--color-text-muted, rgba(0, 0, 0, 0.6));
  font-size: 0.9rem;
}
.ga-setup__steps {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.ga-setup__step {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--color-border, rgba(0, 0, 0, 0.12));
  border-radius: 10px;
}
.ga-setup__step.is-done .ga-setup__icon {
  color: var(--color-success, #2e7d32);
}
.ga-setup__body {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.ga-setup__missing {
  margin: 6px 0 0;
  padding-left: 18px;
  font-size: 0.88rem;
}
.ga-setup__open {
  white-space: nowrap;
  font-weight: 600;
}
.ga-setup__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
</style>
