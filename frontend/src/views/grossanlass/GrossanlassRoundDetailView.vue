<template>
  <PageShell
    class="grossanlass-round-detail"
    :title="round?.name || t('grossanlass.planung.rounds.pageTitle')"
    :subtitle="roundSubtitle"
  >
    <div v-if="isLoading" class="round-detail-loading">
      <ELoadingState variant="list" :message="t('common.loading')" />
    </div>
    <div v-else-if="error" class="round-detail-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</EButton>
    </div>
    <template v-else-if="round">
      <div class="round-detail-toolbar">
        <EButton variant="secondary" size="small" @click="goBack">
          <v-icon icon="mdi-arrow-left" start size="18" />
          {{ t('grossanlass.planung.rounds.backToList') }}
        </EButton>
        <span class="status-badge" :class="'status-' + round.status">{{ statusLabel(round.status) }}</span>
      </div>

      <div v-if="round.status === 'open'" class="wish-form-card">
        <h3 class="section-title">{{ t('grossanlass.wishes.formTitle') }}</h3>
        <p class="section-hint">{{ t('grossanlass.wishes.formHint') }}</p>

        <ESelect
          v-model="form.groupMode"
          :items="groupModeItems"
          :label="t('grossanlass.wishes.bauprojektLabel')"
          hide-details="auto"
          class="mb-3"
        />

        <ESelect
          v-if="form.groupMode === 'existing'"
          v-model="form.groupId"
          :items="groupSelectItems"
          :label="t('grossanlass.wishes.existingBauprojekt')"
          hide-details="auto"
          class="mb-3"
        />

        <template v-if="form.groupMode === 'new'">
          <ESelect
            v-model="form.parentId"
            :items="parentSelectItems"
            :label="t('grossanlass.wishes.parentRessort')"
            hide-details="auto"
            class="mb-3"
          />
          <ETextField
            v-model="form.newBauprojektName"
            :label="t('grossanlass.wishes.newBauprojektName')"
            :placeholder="t('grossanlass.wishes.newBauprojektPlaceholder')"
            hide-details="auto"
            class="mb-3"
          />
        </template>

        <ESelect
          v-model="form.wishKind"
          :items="wishKindItems"
          :label="t('grossanlass.wishes.kindLabel')"
          hide-details="auto"
          class="mb-3"
        />
        <ETextField v-model="form.label" :label="t('grossanlass.wishes.labelField')" hide-details="auto" class="mb-3" />
        <ETextField v-model="form.quantity" type="number" min="1" :label="t('grossanlass.wishes.quantity')" hide-details="auto" class="mb-3" />
        <ETextField v-model="form.location" :label="t('grossanlass.wishes.location')" hide-details="auto" class="mb-3" />

        <div class="activity-datetime-host wish-period-host mb-3">
          <ActivityOutlinedDatetimeSection
            :title="t('grossanlass.wishes.periodLabel')"
            icon="calendar"
            required
          >
            <ActivityDateTimeFields
              v-model:range="wishDateRange"
              v-model:time-from="wishTimeFrom"
              v-model:time-to="wishTimeTo"
              date-mode="range"
              :department-id="departmentId"
              :show-presets="true"
              :show-markers="true"
              preset-mode="fixed-periods"
              :label-from="t('activities.zeitraum.timeStart')"
              :label-to="t('activities.zeitraum.timeEnd')"
              :aria-label="t('grossanlass.wishes.periodLabel')"
            />
          </ActivityOutlinedDatetimeSection>
        </div>

        <ETextarea v-model="form.notes" :label="t('grossanlass.wishes.notes')" hide-details="auto" rows="2" class="mb-3" />

        <EButton variant="primary" :loading="isSaving" @click="submitWish">
          {{ t('grossanlass.wishes.submit') }}
        </EButton>
      </div>

      <div v-else class="round-closed-hint">
        <v-alert type="info" variant="tonal" :text="t('grossanlass.wishes.roundNotOpen')" />
      </div>

      <div class="wishes-list-section">
        <h3 class="section-title">{{ t('grossanlass.wishes.listTitle') }}</h3>
        <ELoadingState v-if="wishesLoading" variant="inline" :message="t('common.loading')" />
        <EEmptyState
          v-else-if="wishes.length === 0"
          variant="default"
          icon="mdi-clipboard-list-outline"
          :title="t('grossanlass.wishes.emptyTitle')"
          :description="t('grossanlass.wishes.emptyDescription')"
        />
        <div v-else class="wishes-grouped">
          <div v-for="group in wishesByGroup" :key="group.groupId" class="wish-group-card">
            <h4 class="wish-group-title">{{ group.groupName }}</h4>
            <table class="wishes-table">
              <thead>
                <tr>
                  <th>{{ t('grossanlass.wishes.colLabel') }}</th>
                  <th>{{ t('grossanlass.wishes.colQty') }}</th>
                  <th>{{ t('grossanlass.wishes.colLocation') }}</th>
                  <th>{{ t('grossanlass.wishes.colPeriod') }}</th>
                  <th v-if="round.status === 'open'"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="wish in group.items" :key="wish.id">
                  <td>{{ wish.label }} <span class="kind-tag">{{ wishKindLabel(wish.wish_kind) }}</span></td>
                  <td>{{ wish.quantity }}</td>
                  <td>{{ wish.location }}</td>
                  <td>{{ formatPeriod(wish) }}</td>
                  <td v-if="round.status === 'open' && canDeleteWish(wish)">
                    <button class="action-btn action-btn-danger" @click="removeWish(wish)">
                      <v-icon icon="mdi-delete-outline" size="16" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESelect, ETextField, ETextarea } from '@/components/form/base'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import {
  getGrossanlassPlanningRounds,
  type GrossanlassPlanningRound,
  type GrossanlassRoundStatus,
} from '@/api/grossanlassRounds'
import {
  createGrossanlassWish,
  deleteGrossanlassWish,
  getGrossanlassRoundWishes,
  type GrossanlassWishKind,
  type GrossanlassWishLine,
} from '@/api/grossanlassWishes'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

const departmentId = computed(() => String(route.params.departmentId || ''))
const roundId = computed(() => String(route.params.roundId || ''))

const round = ref<GrossanlassPlanningRound | null>(null)
const wishes = ref<GrossanlassWishLine[]>([])
const groups = ref<GrossanlassGroup[]>([])
const isLoading = ref(true)
const wishesLoading = ref(false)
const isSaving = ref(false)
const error = ref('')

const groupsRef = computed(() => groups.value)
const { canFullyManage, isMemberInRessortBranch } = useGrossanlassRessortScope(groupsRef)

const form = ref({
  groupMode: 'existing' as 'existing' | 'new',
  groupId: null as string | null,
  parentId: null as string | null,
  newBauprojektName: '',
  wishKind: 'material' as GrossanlassWishKind,
  label: '',
  quantity: '1',
  location: '',
  notes: '',
})

const validStartAt = ref<Date | null>(null)
const validEndAt = ref<Date | null>(null)

const wishDateRange = computed({
  get: (): [Date, Date] | null => {
    if (!validStartAt.value || !validEndAt.value) return null
    return [startOfLocalDay(validStartAt.value), startOfLocalDay(validEndAt.value)]
  },
  set: (v: [Date, Date] | null) => {
    if (!v || v.length < 2) {
      validStartAt.value = null
      validEndAt.value = null
      return
    }
    const [dStart, dEnd] = v
    const tStart = validStartAt.value ?? defaultQuarterTime(dStart, 9, 0)
    const tEnd = validEndAt.value ?? defaultQuarterTime(dEnd, 17, 0)
    validStartAt.value = combineDayAndTime(dStart, tStart)
    validEndAt.value = combineDayAndTime(dEnd, tEnd)
  },
})

const wishTimeFrom = computed({
  get: () => validStartAt.value,
  set: (v: Date | null) => {
    if (!v || !validStartAt.value) return
    validStartAt.value = combineDayAndTime(startOfLocalDay(validStartAt.value), v)
  },
})

const wishTimeTo = computed({
  get: () => validEndAt.value,
  set: (v: Date | null) => {
    if (!v || !validEndAt.value) return
    validEndAt.value = combineDayAndTime(startOfLocalDay(validEndAt.value), v)
  },
})

function defaultQuarterTime(day: Date, hour: number, minute: number): Date {
  return new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
}

const roundSubtitle = computed(() => {
  if (!round.value) return ''
  if (round.value.status === 'open') return t('grossanlass.wishes.roundOpenSubtitle')
  return t('grossanlass.wishes.roundClosedSubtitle')
})

const groupModeItems = computed(() => [
  { title: t('grossanlass.wishes.modeExisting'), value: 'existing' },
  { title: t('grossanlass.wishes.modeNewBauprojekt'), value: 'new' },
])

const wishKindItems = computed(() => [
  { title: t('grossanlass.wishes.kindMaterial'), value: 'material' },
  { title: t('grossanlass.wishes.kindFahrzeug'), value: 'fahrzeug' },
  { title: t('grossanlass.wishes.kindBeides'), value: 'beides' },
])

const selectableGroups = computed(() =>
  groups.value.filter((g) => {
    if (canFullyManage.value) return true
    return isMemberInRessortBranch(g)
  }),
)

const bauprojekte = computed(() =>
  selectableGroups.value.filter((g) => g.node_type === 'bauprojekt' || g.kind === 'teilbereich'),
)

const groupSelectItems = computed(() =>
  bauprojekte.value.map((g) => ({
    title: indentLabel(g),
    value: g.id,
  })),
)

const parentSelectItems = computed(() =>
  selectableGroups.value
    .filter((g) => g.node_type !== 'bauprojekt')
    .map((g) => ({
      title: indentLabel(g),
      value: g.id,
    })),
)

const wishesByGroup = computed(() => {
  const map = new Map<string, { groupId: string; groupName: string; items: GrossanlassWishLine[] }>()
  for (const wish of wishes.value) {
    if (!map.has(wish.group_id)) {
      map.set(wish.group_id, { groupId: wish.group_id, groupName: wish.group_name, items: [] })
    }
    map.get(wish.group_id)!.items.push(wish)
  }
  return Array.from(map.values())
})

function indentLabel(g: GrossanlassGroup): string {
  return `${'  '.repeat(Math.max(0, g.level))}${g.name}`
}

function statusLabel(status: GrossanlassRoundStatus): string {
  switch (status) {
    case 'open':
      return t('grossanlass.planung.rounds.statusOpen')
    case 'closed':
      return t('grossanlass.planung.rounds.statusClosed')
    default:
      return t('grossanlass.planung.rounds.statusScheduled')
  }
}

function wishKindLabel(kind: GrossanlassWishKind): string {
  return wishKindItems.value.find((i) => i.value === kind)?.title || kind
}

function formatPeriod(wish: GrossanlassWishLine): string {
  try {
    const from = new Date(wish.valid_from)
    const to = new Date(wish.valid_to)
    const df = from.toLocaleDateString('de-CH', { dateStyle: 'short' })
    const dt = to.toLocaleDateString('de-CH', { dateStyle: 'short' })
    const tf = from.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' })
    const tt = to.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' })
    return `${df} ${tf} – ${dt} ${tt}`
  } catch {
    return ''
  }
}

function canDeleteWish(wish: GrossanlassWishLine): boolean {
  return wish.created_by_user_id === authStore.userId
}

function goBack() {
  void router.push(`/${departmentId.value}/planung`)
}

async function load() {
  if (!departmentId.value || !roundId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const [rounds, groupList] = await Promise.all([
      getGrossanlassPlanningRounds(departmentId.value),
      getGrossanlassGroups(departmentId.value),
    ])
    groups.value = groupList
    round.value = rounds.find((r) => r.id === roundId.value) || null
    if (!round.value) {
      error.value = t('grossanlass.planung.rounds.errorLoad')
      return
    }
    await loadWishes()
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.planung.rounds.errorLoad')
  } finally {
    isLoading.value = false
  }
}

async function loadWishes() {
  if (!departmentId.value || !roundId.value) return
  wishesLoading.value = true
  try {
    wishes.value = await getGrossanlassRoundWishes(departmentId.value, roundId.value)
  } catch {
    wishes.value = []
  } finally {
    wishesLoading.value = false
  }
}

async function submitWish() {
  if (!departmentId.value || !roundId.value) return
  const payload: Parameters<typeof createGrossanlassWish>[2] = {
    wish_kind: form.value.wishKind,
    label: form.value.label.trim(),
    quantity: parseInt(form.value.quantity, 10) || 0,
    location: form.value.location.trim(),
    valid_from: validStartAt.value?.toISOString() ?? '',
    valid_to: validEndAt.value?.toISOString() ?? '',
    notes: form.value.notes.trim() || null,
  }

  if (form.value.groupMode === 'new') {
    if (!form.value.parentId || !form.value.newBauprojektName.trim()) {
      toast.error(t('grossanlass.wishes.errorBauprojekt'))
      return
    }
    payload.new_bauprojekt = {
      name: form.value.newBauprojektName.trim(),
      parent_id: form.value.parentId,
    }
  } else if (!form.value.groupId) {
    toast.error(t('grossanlass.wishes.errorGroup'))
    return
  } else {
    payload.group_id = form.value.groupId
  }

  if (!payload.label || !payload.location || !payload.valid_from || !payload.valid_to || payload.quantity < 1) {
    toast.error(t('grossanlass.wishes.errorRequired'))
    return
  }

  isSaving.value = true
  try {
    await createGrossanlassWish(departmentId.value, roundId.value, payload)
    toast.success(t('grossanlass.wishes.created'))
    form.value.label = ''
    form.value.location = ''
    form.value.notes = ''
    validStartAt.value = null
    validEndAt.value = null
    if (form.value.groupMode === 'new') {
      const groupList = await getGrossanlassGroups(departmentId.value)
      groups.value = groupList
      form.value.newBauprojektName = ''
    }
    await loadWishes()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.wishes.errorSave'))
  } finally {
    isSaving.value = false
  }
}

async function removeWish(wish: GrossanlassWishLine) {
  if (!departmentId.value || !roundId.value) return
  try {
    await deleteGrossanlassWish(departmentId.value, roundId.value, wish.id)
    wishes.value = wishes.value.filter((w) => w.id !== wish.id)
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.wishes.errorDelete'))
  }
}

onMounted(load)
</script>

<style scoped>
.round-detail-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-open { background: #d1fae5; color: #065f46; }
.status-closed { background: #f3f4f6; color: #4b5563; }
.status-scheduled { background: #e0e7ff; color: #3730a3; }

.wish-form-card,
.wishes-list-section {
  margin-bottom: 28px;
}

.section-title {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 600;
}

.section-hint {
  margin: 0 0 16px;
  color: #6b7280;
  font-size: 0.9rem;
}

.wish-group-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 16px;
}

.wish-group-title {
  margin: 0 0 10px;
  font-size: 0.95rem;
  font-weight: 600;
}

.wishes-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.wishes-table th,
.wishes-table td {
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
}

.kind-tag {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.72rem;
  color: #6b7280;
}

.action-btn {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  width: 28px;
  height: 28px;
  cursor: pointer;
}

.action-btn-danger { color: #dc2626; }

.wish-period-host {
  max-width: 720px;
}
</style>

<style>
@import '@/styles/components/activity-datetime-field.css';
@import '@/styles/components/activity-datetime-layout.css';
</style>
