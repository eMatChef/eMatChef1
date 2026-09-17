<template>
  <div class="proc-line-summary">
    <div class="proc-line-summary__head">
      <div>
        <strong>{{ line.quantity }}× {{ line.label }}</strong>
        <span class="proc-status" :class="procurementStatusClass(line.status)">
          {{ procurementStatusLabel(line.status, t) }}
        </span>
      </div>
      <slot name="actions" />
    </div>
    <div v-if="metaLine" class="proc-line-summary__meta">{{ metaLine }}</div>
    <GrossanlassProcurementCoverage
      :line="line"
      compact
      class="proc-line-summary__coverage"
    />
    <div v-if="moneyLine" class="proc-line-summary__meta">{{ moneyLine }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassProcurementCoverage from '@/components/grossanlass/GrossanlassProcurementCoverage.vue'
import { formatChf, type GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import { procurementStatusClass, procurementStatusLabel } from '@/utils/grossanlassProcurementStatus'
import { formatGaDateLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = defineProps<{ line: GrossanlassProcurementLine }>()

const { t, locale } = useI18n()

const categoryLabel = computed(() => {
  if (!props.line.category_name) return ''
  if (props.line.category_parent_name) {
    return `${props.line.category_parent_name} / ${props.line.category_name}`
  }
  return props.line.category_name
})

const needLine = computed(() => {
  const from = props.line.need_from
  const to = props.line.need_to
  if (!from && !to) return ''
  const fromLabel = from ? formatGaDateLabel(from, locale.value) : '—'
  const toLabel = to ? formatGaDateLabel(to, locale.value) : '—'
  return t('grossanlass.beschaffung.needWindow', { from: fromLabel, to: toLabel })
})

const metaLine = computed(() => {
  return [
    [props.line.group_name, props.line.location].filter(Boolean).join(' · '),
    categoryLabel.value,
    needLine.value,
  ].filter(Boolean).join(' · ')
})

const moneyLine = computed(() => {
  const parts: string[] = []
  if (props.line.budget_chf != null) {
    parts.push(`${t('grossanlass.beschaffung.budgetSoll')}: ${formatChf(props.line.budget_chf)}`)
  }
  if (props.line.order) {
    parts.push(`${t('grossanlass.beschaffung.costIst')}: ${formatChf(props.line.order.cost_chf)}`)
  }
  return parts.join(' · ')
})
</script>

<style scoped>
.proc-line-summary__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.proc-line-summary__meta {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 3px;
  line-height: 1.35;
}

.proc-line-summary__coverage { margin-top: 4px; }

.proc-status {
  display: inline-block;
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}

.proc-status--bedarf { background: #e0e7ff; color: #3730a3; }
.proc-status--offerte-eingeholt { background: #fef3c7; color: #92400e; }
.proc-status--budgetiert { background: #dbeafe; color: #1d4ed8; }
.proc-status--bestellt { background: #fce7f3; color: #9d174d; }
.proc-status--teilweise-erhalten { background: #ffedd5; color: #c2410c; }
.proc-status--erhalten { background: #d1fae5; color: #065f46; }
</style>
