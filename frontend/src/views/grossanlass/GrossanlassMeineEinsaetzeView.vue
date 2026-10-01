<template>
  <PageShell
    class="grossanlass-meine-einsaetze"
    :title="t('grossanlass.meineEinsaetze.title')"
    :subtitle="t('grossanlass.meineEinsaetze.subtitle')"
  >
    <GrossanlassMeineEinsaetzeSkeleton
      v-if="isLoading"
      role="status"
      :aria-label="t('common.loading')"
    />

    <div v-else-if="error" class="meine-einsaetze-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="isEmpty"
      variant="default"
      icon="mdi-truck-delivery-outline"
      :title="t('grossanlass.meineEinsaetze.emptyTitle')"
      :description="t('grossanlass.meineEinsaetze.emptyDescription')"
    />

    <div v-else class="meine-einsaetze-content">
      <GrossanlassFahrauftragList
        v-if="myTripRows.length"
        :rows="myTripRows"
        :busy-id="busyId"
        :can-start-trip="canStartTrip"
        pack-only
        clickable
        @toggle-packed="onTogglePacked"
        @open="(row) => openAssignmentDetail(toAssignment(row, 'fahrauftrag'))"
      />

      <section v-if="otherTripRows.length" class="meine-einsaetze-section">
        <h3>{{ t('grossanlass.meineEinsaetze.fahrauftraegeTitle') }}</h3>
        <ul class="meine-einsaetze-list">
          <li v-for="row in otherTripRows" :key="row.id">
            <GrossanlassHelperAssignmentRow
              :assignment="toAssignment(row, 'fahrauftrag')"
              @open="openAssignmentDetail"
            />
          </li>
        </ul>
      </section>

      <section v-if="bauRows.length" class="meine-einsaetze-section">
        <h3>{{ t('grossanlass.meineEinsaetze.bauauftraegeTitle') }}</h3>
        <ul class="meine-einsaetze-list">
          <li v-for="row in bauRows" :key="row.id">
            <GrossanlassHelperAssignmentRow
              :assignment="toAssignment(row, 'bauauftrag')"
              @open="openAssignmentDetail"
            />
          </li>
        </ul>
      </section>

      <section v-if="einsatzRows.length" class="meine-einsaetze-section">
        <h3>{{ t('grossanlass.meineEinsaetze.einsaetzeTitle') }}</h3>
        <ul class="meine-einsaetze-list">
          <li v-for="row in einsatzRows" :key="row.id">
            <GrossanlassHelperAssignmentRow
              :assignment="toAssignment(row, 'einsatz')"
              @open="openAssignmentDetail"
            />
          </li>
        </ul>
      </section>
    </div>

    <GrossanlassHelperAssignmentDetailDialog
      v-model="assignmentDetailOpen"
      :assignment="selectedAssignment"
      :cards="data?.cards ?? []"
      :busy="busyId === selectedAssignment?.id"
      :can-toggle-packed="canTogglePackedSelected"
      @toggle-packed="onTogglePackedFromDetail"
    />
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassMeineEinsaetzeSkeleton from '@/views/grossanlass/GrossanlassMeineEinsaetzeSkeleton.vue'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  getGrossanlassMyEinsaetze,
  updateGrossanlassEinsatz,
  type GaMyEinsaetzePayload,
  type GaUebersichtEinsatz,
} from '@/api/grossanlassUebersicht'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'
import GrossanlassHelperAssignmentDetailDialog from '@/views/grossanlass/GrossanlassHelperAssignmentDetailDialog.vue'
import GrossanlassHelperAssignmentRow from '@/views/grossanlass/GrossanlassHelperAssignmentRow.vue'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  formatHelperWhenLabel,
  toHelperAssignment,
  groupsToOrgGroups,
  type GaHelperAssignment,
  type GaHelperTaskKind,
} from '@/views/grossanlass/grossanlassHelperAssignment'

const route = useRoute()
const { t, locale } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

const departmentId = computed(() => String(route.params.departmentId || ''))
const data = ref<GaMyEinsaetzePayload | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const helperOrgGroups = computed(() => groupsToOrgGroups(groups.value))
const isLoading = ref(true)
const error = ref('')
const busyId = ref<string | null>(null)
const assignmentDetailOpen = ref(false)
const selectedAssignment = ref<GaHelperAssignment | null>(null)

function toPreview(row: GaUebersichtEinsatz): GaPreviewEinsatz {
  return toHelperAssignment(row, locale.value, undefined, helperOrgGroups.value)
}

function toAssignment(row: GaPreviewEinsatz, taskKind: GaHelperTaskKind): GaHelperAssignment {
  const source = [...(data.value?.fahrauftraege ?? []), ...(data.value?.einsaetze ?? []), ...(data.value?.bauauftraege ?? [])]
    .find((item) => item.id === row.id)
  if (source) {
    return toHelperAssignment(source, locale.value, taskKind, helperOrgGroups.value)
  }
  return {
    ...row,
    taskKind,
    timeRangeLabel: formatHelperWhenLabel(row.fromIso, row.toIso, locale.value),
  }
}

const tripRows = computed(() => (data.value?.fahrauftraege ?? []).map(toPreview))
const myTripRows = computed(() => tripRows.value.filter((row) => isMyChauffeurTrip(row)))
const otherTripRows = computed(() => tripRows.value.filter((row) => !isMyChauffeurTrip(row)))
const bauRows = computed(() => (data.value?.bauauftraege ?? []).map(toPreview))
const einsatzRows = computed(() => (data.value?.einsaetze ?? []).map(toPreview))

const isEmpty = computed(
  () => !tripRows.value.length && !bauRows.value.length && !einsatzRows.value.length,
)

const canTogglePackedSelected = computed(() => {
  const row = selectedAssignment.value
  if (!row || row.taskKind !== 'fahrauftrag') return false
  if (row.operable === false) return false
  if (row.chauffeurUserId !== authStore.userId) return false
  return row.status !== 'issued'
})

function canStartTrip(row: GaPreviewEinsatz): boolean {
  if (!row.destinationPlaceId || !row.chauffeurUserId) return false
  const card = (data.value?.cards ?? []).find((item) => item.user_id === row.chauffeurUserId)
  return card?.may_drive ?? false
}

function isMyChauffeurTrip(row: GaPreviewEinsatz): boolean {
  return row.chauffeurUserId === authStore.userId
}

function openAssignmentDetail(assignment: GaHelperAssignment) {
  selectedAssignment.value = assignment
  assignmentDetailOpen.value = true
}

function syncSelectedAssignment(result: GaMyEinsaetzePayload, assignmentId: string) {
  const updated = [...result.fahrauftraege, ...result.einsaetze, ...result.bauauftraege].find(
    (row) => row.id === assignmentId,
  )
  if (updated && selectedAssignment.value) {
    selectedAssignment.value = toHelperAssignment(updated, locale.value, selectedAssignment.value.taskKind, helperOrgGroups.value)
  }
}

async function onTogglePacked(row: GaPreviewEinsatz) {
  if (!departmentId.value || !isMyChauffeurTrip(row)) return
  busyId.value = row.id
  try {
    const result = await updateGrossanlassEinsatz(departmentId.value, row.id, { packed: !row.packed })
    if ('fahrauftraege' in result) {
      data.value = result
      syncSelectedAssignment(result, row.id)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.meineEinsaetze.errorUpdate'))
  } finally {
    busyId.value = null
  }
}

async function onTogglePackedFromDetail(assignment: GaHelperAssignment) {
  await onTogglePacked(assignment)
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const [payload, groupList] = await Promise.all([
      getGrossanlassMyEinsaetze(departmentId.value),
      getGrossanlassGroups(departmentId.value),
    ])
    data.value = payload
    groups.value = groupList
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.meineEinsaetze.errorLoad')
    data.value = null
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.meine-einsaetze-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.meine-einsaetze-section h3 {
  margin: 0 0 10px;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

.meine-einsaetze-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}

.meine-einsaetze-list li {
  margin: 0;
  padding: 0;
}
</style>
