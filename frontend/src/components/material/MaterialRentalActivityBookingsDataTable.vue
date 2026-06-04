<template>
  <v-data-table
    class="batch-table batch-data-table combo-rental-basis-table rental-activity-bookings-table"
    :headers="headers"
    :items="items"
    item-value="activity_id"
    :items-per-page="-1"
    hide-default-footer
    hover
  >
    <template #item.activity_no="{ item }">
      {{ item.activity_no != null ? `#${item.activity_no}` : emDash }}
    </template>
    <template #item.activity="{ item }">
      <div class="rental-activity-cell">
        <router-link
          class="combo-allocation-link"
          :to="`/${departmentId}/activities/${item.activity_id}`"
        >
          {{ item.activity_name }}
        </router-link>
        <div v-if="item.via_combo_material_names" class="rental-booking-via-combo">
          {{
            viaComboLabel(item.via_combo_material_names)
          }}
        </div>
      </div>
    </template>
    <template #item.period="{ item }">
      <span class="rental-activity-period">{{ formatPeriod(item) }}</span>
    </template>
    <template #item.status="{ item }">
      <span class="status-badge rental-activity-status" :class="item.activity_status">
        {{ statusLabel(item.activity_status) }}
      </span>
    </template>
    <template #item.qty="{ item }">
      <span class="combo-rental-col-num">{{ item.qty }}</span>
    </template>
    <template #item.kind="{ item }">
      <span class="rental-booking-kind" :class="kindClass(item.booking_kind)">
        {{ kindLabel(item.booking_kind) }}
      </span>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'MaterialRentalActivityBookingsDataTable' })

export type RentalActivityBookingRow = {
  activity_id: string
  activity_no?: number | null
  activity_name: string
  via_combo_material_names?: string | null
  activity_status: string
  qty: number
  booking_kind: string
}

const props = defineProps<{
  items: RentalActivityBookingRow[]
  departmentId: string
  emDash: string
  formatPeriod: (row: RentalActivityBookingRow) => string
  statusLabel: (status: string) => string
  kindLabel: (kind: string) => string
  kindClass: (kind: string) => string
}>()

const { t } = useI18n()

function viaComboLabel(names: string) {
  return t('components.materialDetail.rentalActivityBookingsViaCombo', { names })
}

const headers = computed(() => [
  { title: t('components.materialDetail.rentalActivityBookingsThNo'), key: 'activity_no', sortable: false, width: '72px' },
  { title: t('components.materialDetail.rentalActivityBookingsThActivity'), key: 'activity', sortable: false },
  { title: t('components.materialDetail.rentalActivityBookingsThPeriod'), key: 'period', sortable: false },
  { title: t('components.materialDetail.rentalActivityBookingsThStatus'), key: 'status', sortable: false },
  { title: t('components.materialDetail.rentalActivityBookingsThQty'), key: 'qty', sortable: false, width: '72px' },
  { title: t('components.materialDetail.rentalActivityBookingsThKind'), key: 'kind', sortable: false },
])
</script>
