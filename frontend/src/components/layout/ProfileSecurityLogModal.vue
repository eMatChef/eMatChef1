<template>
  <EDialog v-model="model" :title="t('layout.profileModal.activity.fullLogTitle')" :max-width="860" :z-index="2600" scrollable>
    <div class="flex flex-col gap-3" data-testid="log-modal">
      <p class="text-[0.82rem] text-slate-500">
        {{ t('layout.profileModal.activity.retention', { days: RETENTION_DAYS }) }}
      </p>

      <form class="grid grid-cols-1 gap-x-3 sm:grid-cols-3" data-testid="log-filters" @submit.prevent="applyFilters">
        <ESelect
          v-model="filterAction"
          :label="t('layout.profileModal.activity.filterType')"
          :items="actionItems"
          hide-details="auto"
        />
        <ETextField v-model="filterFrom" type="date" :label="t('layout.profileModal.activity.filterFrom')" hide-details="auto" />
        <ETextField v-model="filterTo" type="date" :label="t('layout.profileModal.activity.filterTo')" hide-details="auto" />
        <div class="flex gap-2 sm:col-span-3">
          <EButton variant="primary" size="small" type="submit" :disabled="loading" data-testid="apply-filters">
            {{ t('layout.profileModal.activity.filterApply') }}
          </EButton>
          <EButton variant="text" size="small" type="button" :disabled="loading" data-testid="reset-filters" @click="resetFilters">
            {{ t('layout.profileModal.activity.filterReset') }}
          </EButton>
        </div>
      </form>

      <p v-if="loadError" class="text-[0.85rem] text-red-700" data-testid="log-error">{{ loadError }}</p>
      <p v-else-if="loaded && events.length === 0" class="text-[0.82rem] text-slate-500" data-testid="log-empty">
        {{ t('layout.profileModal.activity.empty') }}
      </p>
      <ol v-else class="flex flex-col gap-1" data-testid="log-events">
        <li v-for="e in events" :key="e.id" class="rounded border border-slate-100 px-3 py-2 text-[0.82rem]">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="font-medium text-slate-800">{{ eventLabel(e) }}</span>
            <time class="text-[0.75rem] text-slate-500" :datetime="e.created_at">{{ formatDate(e.created_at) }}</time>
          </div>
          <ul v-if="metaParts(e).length" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[0.75rem] text-slate-600" data-testid="log-meta">
            <li v-for="part in metaParts(e)" :key="part">{{ part }}</li>
          </ul>
        </li>
      </ol>
    </div>

    <template #actions>
      <EButton v-if="nextCursor" variant="secondary" size="small" :loading="loading" :disabled="loading" data-testid="log-load-more" @click="load(true)">
        {{ t('layout.profileModal.activity.loadMore') }}
      </EButton>
      <span class="flex-1" />
      <EButton variant="text" size="small" data-testid="log-close" @click="model = false">{{ t('common.close') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { useSecurityActivityFormat } from '@/components/layout/useSecurityActivityFormat'
import { useAuthStore } from '@/stores/auth'
import { getSecurityActivity, type SecurityActivityEvent, type SecurityActivityFilters } from '@/api/profileSecurity'

const RETENTION_DAYS = 90
const PAGE_SIZE = 40
const ALL = ''

const model = defineModel<boolean>({ default: false })

const { t } = useI18n()
const { formatDate, actionLabel, eventLabel, metaParts } = useSecurityActivityFormat()
const authStore = useAuthStore()

const events = ref<SecurityActivityEvent[]>([])
const nextCursor = ref<string | null>(null)
const actions = ref<string[]>([])
const loadError = ref('')
const loaded = ref(false)
const loading = ref(false)

const filterAction = ref<string>(ALL)
const filterFrom = ref('')
const filterTo = ref('')
let applied: SecurityActivityFilters = {}

const actionItems = computed(() => [
  { title: t('layout.profileModal.activity.filterAllTypes'), value: ALL },
  ...actions.value.map((a) => ({ title: actionLabel(a), value: a })),
])

// Beim Öffnen immer frisch und ungefiltert starten; beim Schliessen den Speicher freigeben.
watch(model, (isOpen) => {
  if (isOpen) {
    filterAction.value = ALL
    filterFrom.value = ''
    filterTo.value = ''
    applied = {}
    void load(false)
  } else {
    events.value = []
    nextCursor.value = null
    loaded.value = false
  }
})

function applyFilters() {
  applied = {
    ...(filterAction.value ? { action: filterAction.value } : {}),
    ...(filterFrom.value ? { from: filterFrom.value } : {}),
    ...(filterTo.value ? { to: filterTo.value } : {}),
  }
  void load(false)
}

function resetFilters() {
  filterAction.value = ALL
  filterFrom.value = ''
  filterTo.value = ''
  applyFilters()
}

async function load(more: boolean) {
  const id = authStore.profileId || authStore.profile?.id || ''
  if (!id || loading.value) return
  loadError.value = ''
  loading.value = true
  try {
    const page = await getSecurityActivity(id, PAGE_SIZE, more ? nextCursor.value : null, applied)
    events.value = more ? [...events.value, ...page.events] : page.events
    nextCursor.value = page.next_cursor
    if (page.actions) actions.value = page.actions
    loaded.value = true
  } catch {
    loadError.value = t('layout.profileModal.activity.loadError')
  } finally {
    loading.value = false
  }
}
</script>
