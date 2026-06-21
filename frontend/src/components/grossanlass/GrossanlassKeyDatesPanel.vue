<template>
  <div class="grossanlass-key-dates">
    <h3 class="key-dates-title">{{ t('grossanlass.planung.keyDates.title') }}</h3>
    <p class="key-dates-hint">{{ t('grossanlass.planung.keyDates.hint') }}</p>

    <ul v-if="fixedPeriods.length > 0" class="key-dates-list">
      <li v-for="period in fixedPeriods" :key="period.id" class="key-dates-item">
        <span class="key-dates-badge">{{ period.typeLabel }}</span>
        <span class="key-dates-name">{{ period.name }}</span>
        <span class="key-dates-range">{{ period.rangeText }}</span>
      </li>
    </ul>

    <ELoadingState v-else-if="loading" variant="inline" :message="t('common.loading')" />

    <p v-else class="key-dates-empty">{{ t('grossanlass.planung.keyDates.empty') }}</p>

    <p v-if="canManageMaterials" class="key-dates-manage">
      <router-link :to="fixedDatesLink" class="key-dates-link">
        {{ t('grossanlass.planung.keyDates.openFixedDates') }}
      </router-link>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { listDepartmentCalendarPeriods, type DepartmentCalendarPeriod } from '@/api/calendarPeriods'

const props = defineProps<{
  departmentId: string
}>()

const { t } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()

const periods = ref<DepartmentCalendarPeriod[]>([])
const loading = ref(false)

const fixedDatesLink = computed(() => `/${props.departmentId}/settings/my-department/fixed-dates`)

function formatRange(startIso: string, endIso: string): string {
  try {
    const start = new Date(startIso)
    const end = new Date(endIso)
    const df = (d: Date) => d.toLocaleDateString('de-CH', { dateStyle: 'short' })
    if (df(start) === df(end)) return df(start)
    return `${df(start)} – ${df(end)}`
  } catch {
    return `${startIso} – ${endIso}`
  }
}

const fixedPeriods = computed(() =>
  periods.value
    .filter((p) => p.label === 'camp_week' || p.label === 'other')
    .map((p) => ({
      id: p.id,
      name: p.name,
      typeLabel: t(`settings.fixedDates.labels.${p.label}`),
      rangeText: formatRange(p.start_date, p.end_date),
    })),
)

async function loadPeriods() {
  if (!props.departmentId) return
  loading.value = true
  try {
    const y = new Date().getFullYear()
    periods.value = await listDepartmentCalendarPeriods(props.departmentId, [y - 1, y, y + 1, y + 2])
  } catch {
    periods.value = []
  } finally {
    loading.value = false
  }
}

onMounted(loadPeriods)
</script>

<style scoped>
.grossanlass-key-dates {
  margin-bottom: 20px;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #f9fafb;
}

.key-dates-title {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.key-dates-hint {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: #6b7280;
}

.key-dates-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.key-dates-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 8px 12px;
  align-items: center;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.key-dates-badge {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #6b7280;
  white-space: nowrap;
}

.key-dates-name {
  font-weight: 500;
  color: #111827;
}

.key-dates-range {
  font-size: 0.85rem;
  color: #4b5563;
  white-space: nowrap;
}

.key-dates-empty {
  margin: 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.key-dates-manage {
  margin: 12px 0 0;
  font-size: 0.82rem;
}

.key-dates-link {
  color: #2563eb;
  text-decoration: none;
  font-weight: 500;
}

.key-dates-link:hover {
  text-decoration: underline;
}

@media (max-width: 640px) {
  .key-dates-item {
    grid-template-columns: 1fr;
    gap: 4px;
  }
}
</style>
