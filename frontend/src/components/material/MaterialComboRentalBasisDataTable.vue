<template>
  <div class="combo-rental-basis-table-wrap">
    <v-data-table
      class="batch-table batch-data-table combo-rental-basis-table"
      :headers="headers"
      :items="tableItems"
      item-value="_key"
      :items-per-page="-1"
      hide-default-footer
      hover
    >
      <template #item.name="{ item }">
        <span>{{ item.name }}</span>
        <span v-if="showOptionalBadge && item.optional" class="composition-optional-badge">{{ optionalBadge }}</span>
      </template>
      <template #item.qty="{ item }">
        <span class="combo-rental-col-num">{{ item.qty }}</span>
      </template>
      <template #item.perPiece="{ item }">
        <span class="combo-rental-col-num">
          <template v-if="item.perPieceChf != null">{{ currencyFr }} {{ formatChf(item.perPieceChf) }}</template>
          <span v-else class="muted">{{ emDash }}</span>
        </span>
      </template>
      <template #item.line="{ item }">
        <span class="combo-rental-col-num">
          <template v-if="item.lineChf != null">{{ currencyFr }} {{ formatChf(item.lineChf) }}</template>
          <span v-else class="muted">{{ emDash }}</span>
        </span>
      </template>
    </v-data-table>
    <div v-if="totalBasisChf != null" class="combo-rental-total-row">
      <span class="combo-rental-total-label">{{ footerSumLabel }}</span>
      <span class="combo-rental-total-value combo-rental-col-num">
        {{ currencyFr }} {{ formatChf(totalBasisChf) }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'MaterialComboRentalBasisDataTable' })

export type ComboRentalRow = {
  componentId: string
  name: string
  qty: number
  optional?: boolean
  perPieceChf: number | null
  lineChf: number | null
}

const props = defineProps<{
  items: ComboRentalRow[]
  showOptionalBadge?: boolean
  totalBasisChf: number | null
  currencyFr: string
  emDash: string
  formatChf: (value: number) => string
}>()

const { t } = useI18n()

const tableItems = computed(() =>
  props.items.map((row, index) => ({ ...row, _key: `${row.componentId}-${index}` })),
)

const optionalBadge = computed(() => t('components.materialDetail.optionalShortBadge'))
const footerSumLabel = computed(() => t('components.materialDetail.rentalFooterSumOneSet'))

const headers = computed(() => [
  { title: t('components.materialDetail.thComponent'), key: 'name', sortable: false },
  { title: t('components.materialDetail.thQtyInSet'), key: 'qty', sortable: false, width: '88px' },
  { title: t('components.materialDetail.thAvgPurchasePerPc'), key: 'perPiece', sortable: false, width: '120px' },
  { title: t('components.materialDetail.thLine'), key: 'line', sortable: false, width: '120px' },
])
</script>
