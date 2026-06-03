<template>
  <v-data-table
    class="batch-table batch-data-table workshop-tickets-mini"
    :headers="headers"
    :items="items"
    item-value="id"
    :items-per-page="-1"
    hide-default-footer
    hover
  >
    <template #item.type="{ item }">
      {{ item.type_label }}
    </template>
    <template #item.status="{ item }">
      <span class="status-badge">{{ item.status_label }}</span>
    </template>
    <template #item.activity="{ item }">
      <router-link
        v-if="item.activity_id"
        :to="`/${departmentId}/activities/${item.activity_id}`"
        class="link-btn"
      >
        {{ openLabel }}
      </router-link>
      <span v-else class="muted">{{ emDash }}</span>
    </template>
    <template #item.actions="{ item }">
      <router-link
        class="btn-outline btn-sm"
        :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId, ticket: item.id } }"
      >
        {{ workshopLabel }}
      </router-link>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'MaterialWorkshopTicketsDataTable' })

export type WorkshopTicketRow = {
  id: string
  title: string
  type_label: string
  status_label: string
  activity_id?: string | null
}

defineProps<{
  items: WorkshopTicketRow[]
  departmentId: string
  materialId: string
  emDash: string
}>()

const { t } = useI18n()

const openLabel = computed(() => t('components.materialDetail.btnOpen'))
const workshopLabel = computed(() => t('components.materialDetail.btnInWorkshop'))

const headers = computed(() => [
  { title: t('components.materialDetail.thTicket'), key: 'title', sortable: false },
  { title: t('components.materialDetail.thType'), key: 'type', sortable: false },
  { title: t('common.status'), key: 'status', sortable: false },
  { title: t('components.materialDetail.thActivity'), key: 'activity', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '140px' },
])
</script>
