<template>
  <div class="grossanlass-key-dates">
    <h3 class="key-dates-title">{{ t('grossanlass.planung.keyDates.title') }}</h3>
    <p class="key-dates-hint">{{ t('grossanlass.planung.keyDates.hint') }}</p>

    <ul v-if="fixedPeriods.length > 0" class="key-dates-list">
      <li v-for="period in fixedPeriods" :key="period.id" class="key-dates-item">
        <span class="key-dates-badge">{{ period.typeLabel }}</span>
        <span v-if="period.name" class="key-dates-name">{{ period.name }}</span>
        <span v-else class="key-dates-name key-dates-name--empty"></span>
        <span class="key-dates-range">{{ period.rangeText }}</span>
      </li>
    </ul>

    <ELoadingState v-else-if="loading" variant="inline" :message="t('common.loading')" />

    <p v-else class="key-dates-empty">{{ t('grossanlass.planung.keyDates.empty') }}</p>

    <p v-if="canManageMaterials" class="key-dates-manage">
      <button type="button" class="key-dates-link" @click="showManager = true">
        {{ t('grossanlass.planung.keyDates.openFixedDates') }}
      </button>
    </p>

    <EDialog
      v-model="showManager"
      :title="t('settings.fixedDates.title')"
      :max-width="960"
      :retain-focus="false"
      :z-index="2400"
    >
      <p class="manager-lead">{{ t('settings.fixedDates.descriptionGrossanlass') }}</p>
      <DepartmentFixedDatesManager
        v-if="showManager && departmentId"
        :department-id="departmentId"
        @changed="loadPeriods"
      />
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useCalendarPeriodsCacheRevision } from '@/composables/useCalendarPeriodsCache'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EDialog } from '@/components/form/base'
import DepartmentFixedDatesManager from '@/components/settings/DepartmentFixedDatesManager.vue'
import {
  calendarPeriodTime,
  listDepartmentCalendarPeriods,
  GROSSANLASS_TIME_MODULE_LABELS,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'

const props = defineProps<{
  departmentId: string
}>()

const { t, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()
const cacheRevision = useCalendarPeriodsCacheRevision()

const periods = ref<DepartmentCalendarPeriod[]>([])
const loading = ref(false)
const showManager = ref(false)

function formatDateTime(iso: string, time: string | undefined, fallback: string): string {
  const day = iso.slice(0, 10)
  const [y, m, d] = day.split('-').map((x) => parseInt(x, 10))
  if (!y || !m || !d) return iso
  const dateText = new Date(y, m - 1, d).toLocaleDateString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
  return `${dateText}, ${calendarPeriodTime(time, fallback)}`
}

function formatRange(row: DepartmentCalendarPeriod): string {
  const start = formatDateTime(row.start_date, row.start_time, '00:00')
  const end = formatDateTime(row.end_date, row.end_time, '23:59')
  return start === end ? start : `${start} – ${end}`
}

const KEY_DATE_LABELS = new Set<string>([...GROSSANLASS_TIME_MODULE_LABELS, 'other'])

const fixedPeriods = computed(() =>
  periods.value
    .filter((p) => KEY_DATE_LABELS.has(p.label))
    .slice()
    .sort((a, b) => {
      const sa = `${a.start_date}T${calendarPeriodTime(a.start_time, '00:00')}`
      const sb = `${b.start_date}T${calendarPeriodTime(b.start_time, '00:00')}`
      return sb.localeCompare(sa) || a.name.localeCompare(b.name)
    })
    .map((p) => {
      const typeLabel = t(`settings.fixedDates.labels.${p.label}`)
      const showName = p.name.trim().toLocaleLowerCase() !== typeLabel.toLocaleLowerCase()
      return {
        id: p.id,
        name: showName ? p.name : '',
        typeLabel,
        rangeText: formatRange(p),
      }
    }),
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

watch(() => [props.departmentId, cacheRevision.value] as const, () => void loadPeriods(), { immediate: true })
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

.manager-lead {
  margin: 0 0 12px;
  font-size: 0.9rem;
  color: #64748b;
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
  border: 0;
  background: none;
  padding: 0;
  cursor: pointer;
  font: inherit;
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
