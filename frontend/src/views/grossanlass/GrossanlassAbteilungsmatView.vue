<template>
  <div class="page">
    <ECard class="card" variant="elevated">
      <h1 class="title">{{ t('grossanlass.abteilungsmat.title') }}</h1>
      <p class="lead">{{ t('grossanlass.abteilungsmat.lead') }}</p>
      <ul class="points">
        <li>{{ t('grossanlass.abteilungsmat.points.stock') }}</li>
        <li>{{ t('grossanlass.abteilungsmat.points.lend') }}</li>
        <li>{{ t('grossanlass.abteilungsmat.points.sameAccount') }}</li>
      </ul>
      <p class="note">{{ t('grossanlass.abteilungsmat.note') }}</p>
    </ECard>

    <ECard class="card" variant="elevated">
      <h2 class="form-title">{{ t('grossanlass.abteilungsmat.formTitle') }}</h2>
      <p class="lead">{{ t('grossanlass.abteilungsmat.formIntro') }}</p>

      <v-alert v-if="sent" type="success" variant="tonal" density="compact" class="alert">
        {{ t('grossanlass.abteilungsmat.sent') }}
      </v-alert>

      <form v-else class="form" @submit.prevent="submit">
        <ESelect
          v-model="organisationId"
          :items="organisationItems"
          :label="t('pendingAssignment.organisationRequired')"
          :disabled="loading"
        />
        <ETextField
          v-model="departmentName"
          :label="t('pendingAssignment.departmentNameRequired')"
          :placeholder="t('pendingAssignment.departmentNameModalPlaceholder')"
          :disabled="loading"
        />
        <ETextField
          v-model="affiliation"
          :label="t('pendingAssignment.affiliationModalLabel')"
          :placeholder="t('pendingAssignment.affiliationModalPlaceholder')"
          :disabled="loading"
        />
        <ParentDepartmentPicker
          v-if="organisationId"
          :organisation-id="organisationId"
          :disabled="loading"
          @update:model-value="parentPick = $event"
        />
        <ETextarea
          v-model="message"
          :label="t('pendingAssignment.messageToAdmin')"
          :placeholder="t('pendingAssignment.messageToAdminPlaceholder')"
          :rows="3"
          :disabled="loading"
        />
        <div v-if="turnstileRequired" ref="turnstileContainerRef" class="turnstile" />
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="alert">
          {{ error }}
        </v-alert>
        <EButton
          type="submit"
          variant="primary"
          :disabled="loading || !organisationId || !departmentName.trim()"
          :loading="loading"
        >
          {{ t('grossanlass.abteilungsmat.submit') }}
        </EButton>
      </form>
    </ECard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { createAdminJoinRequest } from '@/api/joinRequests'
import { getOrganisations, type Organisation } from '@/api/organisations'
import ParentDepartmentPicker, {
  type ParentDepartmentPickerValue,
} from '@/components/auth/ParentDepartmentPicker.vue'
import { EButton, ECard, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { useTurnstile } from '@/composables/useTurnstile'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'

defineOptions({ name: 'GrossanlassAbteilungsmatView' })

const { t } = useI18n()
const organisations = ref<Organisation[]>([])
const organisationId = ref('')
const departmentName = ref('')
const affiliation = ref('')
const parentPick = ref<ParentDepartmentPickerValue | null>(null)
const message = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const sent = ref(false)

const {
  isRequired: turnstileRequired,
  containerRef: turnstileContainerRef,
  init: initTurnstile,
  getToken: getTurnstileToken,
  reset: resetTurnstile,
} = useTurnstile()

const organisationItems = computed(() =>
  organisations.value.map((org) => ({ title: org.name, value: org.id })),
)

onMounted(async () => {
  void initTurnstile()
  try {
    organisations.value = filterOrganisationsForUserPickers(await getOrganisations())
  } catch {
    organisations.value = []
  }
})

async function submit() {
  const name = departmentName.value.trim()
  if (!name || !organisationId.value) return
  if (turnstileRequired.value && !getTurnstileToken()) {
    error.value = t('login.validationCaptcha')
    return
  }
  loading.value = true
  error.value = null
  try {
    await createAdminJoinRequest({
      requestedDepartmentName: name,
      requestedAffiliation: affiliation.value.trim() || undefined,
      requestedOrganisationId: organisationId.value,
      requestedParentDepartmentId: parentPick.value?.departmentId || undefined,
      requestedParentDepartmentName: parentPick.value?.departmentName || undefined,
      message: message.value.trim() || undefined,
      turnstileToken: getTurnstileToken(),
    })
    sent.value = true
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('grossanlass.abteilungsmat.sendFailed')
    resetTurnstile()
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.page {
  max-width: 720px;
  margin: 0 auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  padding: 20px;
}

.title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 700;
}

.form-title {
  margin: 0 0 8px;
  font-size: 1.125rem;
  font-weight: 600;
}

.lead,
.note {
  margin: 0 0 12px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.75);
}

.points {
  margin: 0 0 12px;
  padding-left: 1.2rem;
  line-height: 1.5;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.alert {
  margin-bottom: 12px;
}

.turnstile {
  min-height: 65px;
}
</style>
