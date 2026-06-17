<template>
  <v-list v-if="items.length > 0" class="activity-list-mobile" lines="three">
    <v-list-item
      v-for="item in items"
      :key="item.id"
      class="activity-list-mobile__item"
      :class="{ 'activity-list-mobile__item--draft': item.status === 'draft' }"
      @click="emit('open', item)"
    >
      <template #prepend>
        <span
          class="activity-list-mobile__status-dot status-dot"
          :class="activityStatusClass(item.status)"
        />
      </template>

      <v-list-item-title class="activity-list-mobile__title">
        {{ item.name }}
        <span v-if="item.no" class="activity-list-shared__no">{{ item.no }}</span>
      </v-list-item-title>

      <v-list-item-subtitle class="activity-list-mobile__meta">
        <span class="activity-list-type-badges activity-list-type-badges--inline">
          <span class="type-badge" :class="item.type">{{ typeLabel(item.type) }}</span>
          <span
            v-if="activityHasJsMaterial(item)"
            class="type-badge js"
            :class="item.jsListPhase ? `js-phase-${item.jsListPhase}` : undefined"
          >{{ t('activities.common.jsBadge') }}</span>
        </span>
        ·
        <span class="status-label" :class="activityStatusClass(item.status)">{{ statusLabel(item.status) }}</span>
      </v-list-item-subtitle>

      <v-list-item-subtitle v-if="item.usageStart" class="activity-list-mobile__line">
        {{ periodLabel(item) }}
        <template v-if="item.itemCount">
          · {{ t('common.material') }} {{ item.itemCount }}
        </template>
        <template v-if="item.totalPrice">
          · CHF {{ item.totalPrice.toFixed(2) }}
        </template>
      </v-list-item-subtitle>

      <v-list-item-subtitle
        v-if="item.type !== 'external' && groupPathLines(item).length"
        class="activity-list-mobile__line"
      >
        <span
          v-for="(line, lineIdx) in groupPathLines(item).slice(0, 2)"
          :key="lineIdx"
          class="activity-list-mobile__group-line"
          :style="{ paddingLeft: `${line.level * 8}px` }"
        >{{ line.label }}</span>
      </v-list-item-subtitle>

      <template #append>
        <v-btn
          icon
          variant="text"
          size="small"
          density="compact"
          :aria-label="t('common.openDetail')"
          @click.stop="emit('open', item)"
        >
          <v-icon icon="mdi-chevron-right" size="22" />
        </v-btn>
      </template>
    </v-list-item>
  </v-list>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { GroupPathLine } from '@/utils/groupHierarchy'
import { activityStatusClass } from '@/utils/activityStatus'
import { activityHasJsMaterial } from '@/utils/activityJsListStatus'
import type { ActivityListItem } from './activityListItem'
import '@/styles/components/activity-list-mobile.css'

defineOptions({ name: 'ActivityListMobile' })

defineProps<{
  items: ActivityListItem[]
  typeLabel: (type: string) => string
  statusLabel: (status: string) => string
  periodLabel: (item: ActivityListItem) => string
  groupPathLines: (item: ActivityListItem) => GroupPathLine[]
}>()

const emit = defineEmits<{
  open: [item: ActivityListItem]
}>()

const { t } = useI18n()
</script>
