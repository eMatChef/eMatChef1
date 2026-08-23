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
    <div class="proc-line-summary__meta">{{ line.group_name }} · {{ line.location }}</div>
    <div v-if="categoryLabel" class="proc-line-summary__meta">{{ categoryLabel }}</div>
    <div v-if="line.budget_chf != null" class="proc-line-summary__meta">
      {{ t('grossanlass.beschaffung.budgetSoll') }}: {{ formatChf(line.budget_chf) }}
    </div>
    <div v-if="line.order" class="proc-line-summary__meta">
      {{ t('grossanlass.beschaffung.costIst') }}: {{ formatChf(line.order.cost_chf) }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatChf, type GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import { procurementStatusClass, procurementStatusLabel } from '@/utils/grossanlassProcurementStatus'

const props = defineProps<{ line: GrossanlassProcurementLine }>()

const { t } = useI18n()

const categoryLabel = computed(() => {
  if (!props.line.category_name) return ''
  if (props.line.category_parent_name) {
    return `${props.line.category_parent_name} / ${props.line.category_name}`
  }
  return props.line.category_name
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
  margin-top: 4px;
}

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
