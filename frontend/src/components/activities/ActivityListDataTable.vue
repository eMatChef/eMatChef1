<template>
  <v-data-table
    class="activity-list-dt__table"
    :headers="headers"
    :items="items"
    :items-per-page="-1"
    item-value="id"
    hover
    hide-default-footer
    :sort-by="sortBy"
    :row-props="rowProps"
    @update:sort-by="onSortBy"
    @click:row="onRowClick"
  >
    <template #item.statusDot="{ item }">
      <span class="status-dot" :class="activityStatusClass(item.status)" />
    </template>

    <template #item.name="{ item }">
      <div class="activity-list-dt__name-cell">
        <div class="activity-list-shared__name">{{ item.name }}</div>
        <div v-if="item.no" class="activity-list-shared__no">{{ item.no }}</div>
        <div v-if="shareHint(item)" class="activity-list-shared__share-hint">{{ shareHint(item) }}</div>
        <div v-if="shareStatus(item)" class="activity-list-shared__share-status">{{ shareStatus(item) }}</div>
      </div>
    </template>

    <template #item.type="{ item }">
      <div class="activity-list-type-badges">
        <span class="type-badge" :class="item.type">{{ typeLabel(item.type) }}</span>
        <span
          v-if="activityHasJsMaterial(item)"
          class="type-badge js"
          :class="item.jsListPhase ? `js-phase-${item.jsListPhase}` : undefined"
        >{{ t('activities.common.jsBadge') }}</span>
      </div>
    </template>

    <template #item.group="{ item }">
      <span v-if="item.type === 'external'" class="activity-list-shared__muted">–</span>
      <div v-else-if="groupPathLines(item).length" class="activity-list-shared__group-path">
        <span
          v-for="(line, lineIdx) in groupPathLines(item)"
          :key="lineIdx"
          class="activity-list-shared__group-line"
          :style="{ paddingLeft: `${line.level * 12}px` }"
        >{{ line.label }}</span>
      </div>
      <span v-else class="activity-list-shared__muted">–</span>
    </template>

    <template #item.period="{ item }">
      <span v-if="item.usageStart" class="activity-list-shared__period">{{ periodLabel(item) }}</span>
      <span v-else class="activity-list-shared__muted">–</span>
    </template>

    <template #item.items="{ item }">
      <span v-if="item.itemCount" class="activity-list-shared__items-badge">{{ item.itemCount }}</span>
      <span v-else class="activity-list-shared__muted">0</span>
    </template>

    <template #item.price="{ item }">
      <span v-if="item.totalPrice" class="activity-list-shared__price">CHF {{ item.totalPrice.toFixed(2) }}</span>
      <span v-else class="activity-list-shared__muted">–</span>
    </template>

    <template #item.status="{ item }">
      <span class="status-label" :class="activityStatusClass(item.status)">{{ statusLabel(item.status) }}</span>
    </template>

    <template #item.issues="{ item }">
      <router-link
        v-if="['at_event', 'returned', 'completed'].includes(item.status)"
        class="activity-list-shared__issues-link"
        :to="`/${departmentId}/activities/${item.id}?tab=issues`"
        @click.stop
      >
        {{ t('activities.table.issues') }}
      </router-link>
      <span v-else class="activity-list-shared__muted">–</span>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GroupPathLine } from '@/utils/groupHierarchy'
import { activityStatusClass } from '@/utils/activityStatus'
import { activityHasJsMaterial } from '@/utils/activityJsListStatus'
import type { ActivityListItem } from './activityListItem'
import '@/styles/components/activity-list-data-table.css'

defineOptions({ name: 'ActivityListDataTable' })

const props = defineProps<{
  items: ActivityListItem[]
  departmentId: string
  selectedId: string | null
  sortField: string
  sortDir: 'asc' | 'desc'
  typeLabel: (type: string) => string
  statusLabel: (status: string) => string
  periodLabel: (item: ActivityListItem) => string
  groupPathLines: (item: ActivityListItem) => GroupPathLine[]
  shareHint: (item: ActivityListItem) => string | null
  shareStatus: (item: ActivityListItem) => string | null
}>()

const emit = defineEmits<{
  open: [item: ActivityListItem]
  select: [id: string]
  sort: [payload: { field: string; order: 'asc' | 'desc' }]
}>()

const { t } = useI18n()

const headers = computed(() => [
  { title: '', key: 'statusDot', sortable: false, width: '44px' },
  { title: t('common.name'), key: 'name', sortable: true, minWidth: '200px' },
  { title: t('activities.table.type'), key: 'type', sortable: false, width: '120px' },
  { title: t('common.group'), key: 'group', sortable: false, minWidth: '140px' },
  { title: t('activities.table.period'), key: 'period', sortable: true, width: '130px' },
  { title: t('common.material'), key: 'items', sortable: false, align: 'center' as const, width: '88px' },
  { title: t('activities.table.price'), key: 'price', sortable: true, align: 'end' as const, width: '110px' },
  { title: t('common.status'), key: 'status', sortable: false, width: '130px' },
  { title: t('activities.table.issues'), key: 'issues', sortable: false, align: 'end' as const, width: '100px' },
])

const sortBy = computed(() => {
  const key =
    props.sortField === 'date' ? 'period' : props.sortField === 'price' ? 'price' : 'name'
  return [{ key, order: props.sortDir }]
})

function rowProps({ item }: { item: ActivityListItem }) {
  const classes = ['activity-list-dt__row']
  if (item.status === 'draft') classes.push('activity-list-dt__row--draft')
  if (item.id === props.selectedId) classes.push('activity-list-dt__row--selected')
  return {
    class: classes.join(' '),
    onDblclick: () => emit('open', item),
  }
}

function onRowClick(_event: Event, { item }: { item: ActivityListItem }) {
  emit('select', item.id)
}

function onSortBy(value: Array<{ key: string; order: 'asc' | 'desc' }>) {
  const first = value[0]
  if (!first) return
  const field = first.key === 'period' ? 'date' : first.key
  emit('sort', { field, order: first.order })
}
</script>
