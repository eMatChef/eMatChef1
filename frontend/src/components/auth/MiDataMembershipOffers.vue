<template>
  <div v-if="hasContent" class="midata-offers">
    <p class="midata-offers__intro">
      {{ searchRequired ? t('pendingAssignment.midataSearchIntro') : t('pendingAssignment.midataOnboardingIntro') }}
    </p>

    <div v-for="offer in offers" :key="offer.id" class="midata-offers__offer">
      <p class="midata-offers__name">{{ offer.department_name }}</p>
      <dl class="midata-offers__details">
        <template v-if="offer.region_name">
          <dt>{{ t('pendingAssignment.midataOnboardingRegion') }}</dt>
          <dd>{{ offer.region_name }}</dd>
        </template>
        <dt>{{ t('pendingAssignment.midataOnboardingKantonalverband') }}</dt>
        <dd>{{ offer.kantonalverband_name }}</dd>
        <dt>{{ t('pendingAssignment.midataOnboardingRole') }}</dt>
        <dd>{{ roleLabel(offer.role) }}</dd>
      </dl>
      <p class="midata-offers__hint">
        {{
          offer.department_exists
            ? t('pendingAssignment.midataOnboardingExistingHint')
            : t('pendingAssignment.midataOnboardingRedirectHint')
        }}
      </p>
      <EButton variant="primary" :disabled="starting" @click="start('offer', offer.id)">
        {{
          offer.department_exists
            ? t('pendingAssignment.midataOnboardingJoin')
            : t('pendingAssignment.midataOnboardingSetup')
        }}
      </EButton>
    </div>

    <div v-if="searchRequired" class="midata-offers__search">
      <p v-if="offers.length > 0" class="midata-offers__subtitle">{{ t('layout.profileModal.midataSection') }}</p>
      <ESearchField
        v-model="query"
        :label="t('pendingAssignment.midataSearchLabel')"
        @clear="clearSearch"
      />
      <p class="midata-offers__hint">{{ t('pendingAssignment.midataSearchHint') }}</p>
      <ELoadingState v-if="searching" variant="inline" :message="t('pendingAssignment.searchLoading')" />
      <div v-for="candidate in results" :key="candidate.id" class="midata-offers__hit">
        <div>
          <p class="midata-offers__name">{{ candidate.department_name }}</p>
          <p class="midata-offers__role">{{ roleLabel(candidate.role) }}</p>
        </div>
        <EButton variant="secondary" :disabled="starting" @click="start('candidate', candidate.id)">
          {{ t('pendingAssignment.midataSearchSelect') }}
        </EButton>
      </div>
      <p v-if="showNoResults" class="midata-offers__hint">{{ t('pendingAssignment.midataSearchEmpty') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getMiDataDepartmentOnboardingOffers,
  searchMiDataMembershipCandidates,
  type MiDataDepartmentOnboardingOffer,
  type MiDataMembershipCandidate,
  type MiDataMembershipRole,
} from '@/api/joinRequests'
import { midataOnboardingStartUrl } from '@/utils/midataOnboarding'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESearchField } from '@/components/form/base'

defineOptions({ name: 'MiDataMembershipOffers' })

const emit = defineEmits<{ availability: [available: boolean] }>()

const { t } = useI18n()
const offers = ref<MiDataDepartmentOnboardingOffer[]>([])
const searchRequired = ref(false)
const query = ref('')
const results = ref<MiDataMembershipCandidate[]>([])
const searching = ref(false)
const searched = ref(false)
const starting = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const hasContent = computed(() => offers.value.length > 0 || searchRequired.value)
const showNoResults = computed(
  () => searched.value && !searching.value && query.value.trim().length >= 2 && results.value.length === 0,
)

async function load() {
  try {
    const status = await getMiDataDepartmentOnboardingOffers()
    offers.value = status.offers
    searchRequired.value = status.search_required
  } catch (e) {
    console.error(e)
    offers.value = []
    searchRequired.value = false
  }
  emit('availability', hasContent.value)
}

function roleLabel(role: MiDataMembershipRole): string {
  return role === 'abteilungsleitung'
    ? t('pendingAssignment.midataOnboardingRoleAbteilungsleitung')
    : t('pendingAssignment.midataOnboardingRoleMaterialwart')
}

function clearSearch() {
  query.value = ''
  results.value = []
  searched.value = false
}

function start(kind: 'offer' | 'candidate', id: string) {
  starting.value = true
  window.location.assign(midataOnboardingStartUrl(kind, id))
}

watch(query, (value) => {
  if (searchTimer) clearTimeout(searchTimer)
  const q = value.trim()
  if (q.length < 2) {
    results.value = []
    searched.value = false
    return
  }
  searchTimer = setTimeout(async () => {
    searching.value = true
    try {
      results.value = await searchMiDataMembershipCandidates(q)
    } catch (e) {
      console.error(e)
      results.value = []
    } finally {
      searching.value = false
      searched.value = true
    }
  }, 250)
})

onMounted(load)
onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<style scoped>
.midata-offers__intro {
  margin: 0 0 16px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  line-height: 1.5;
}

.midata-offers__offer,
.midata-offers__hit {
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.midata-offers__offer + .midata-offers__offer,
.midata-offers__hit + .midata-offers__hit {
  margin-top: 12px;
}

.midata-offers__hit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.midata-offers__name {
  margin: 0 0 8px;
  font-size: 1.125rem;
  font-weight: 700;
}

.midata-offers__hit .midata-offers__name {
  margin: 0;
  font-size: 1rem;
}

.midata-offers__role {
  margin: 2px 0 0;
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.midata-offers__details {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 4px 12px;
  margin: 0 0 12px;
  font-size: 14px;
}

.midata-offers__details dt {
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.midata-offers__details dd {
  margin: 0;
}

.midata-offers__hint {
  margin: 8px 0 12px;
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.midata-offers__search {
  margin-top: 16px;
}

.midata-offers__subtitle {
  margin: 0 0 8px;
  font-weight: 600;
}
</style>
