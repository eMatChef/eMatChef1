<template>
  <div
    class="proc-coverage"
    :class="{
      'proc-coverage--compact': compact && !stack,
      'proc-coverage--stack': stack,
    }"
  >
    <p v-if="oneLiner" class="proc-coverage__line">{{ oneLiner }}</p>

    <template v-if="stack">
      <dl class="proc-coverage__stack">
        <div>
          <dt>{{ t('grossanlass.beschaffung.zusagen.takeAsked') }}</dt>
          <dd>{{ line.quantity }}</dd>
        </div>
        <div>
          <dt>{{ t('grossanlass.beschaffung.zusagen.takeBuy') }}</dt>
          <dd :class="{ 'proc-coverage__ordered': ordered > 0 }">
            <RouterLink
              v-if="ordered > 0 && orderTo"
              :to="orderTo"
              class="proc-coverage__link"
            >
              {{ ordered }}
            </RouterLink>
            <template v-else>{{ ordered }}</template>
          </dd>
        </div>
        <div>
          <dt>{{ t('grossanlass.beschaffung.zusagen.takeSum') }}</dt>
          <dd :class="{ 'proc-coverage__over': open < 0, 'proc-coverage__short': open > 0 }">
            {{ covered }}
          </dd>
        </div>
      </dl>
    </template>

    <p v-else class="proc-coverage__line proc-coverage__line--inline">
      <span>
        {{ t('grossanlass.beschaffung.zusagen.takeNeed') }} {{ line.quantity }}
        <template v-if="wishSum"> · {{ wishSum }}</template>
      </span>
      <span v-if="!compact || otherTaken > 0">
        ·
        <RouterLink
          v-if="loanTo"
          :to="loanTo"
          class="proc-coverage__link"
        >
          {{ t('grossanlass.beschaffung.zusagen.takeTaken') }} {{ otherTaken }}
        </RouterLink>
        <template v-else>
          {{ t('grossanlass.beschaffung.zusagen.takeTaken') }} {{ otherTaken }}
        </template>
      </span>
      <span v-if="ordered > 0">
        ·
        <RouterLink
          v-if="orderTo"
          :to="orderTo"
          class="proc-coverage__link"
        >
          {{ t('grossanlass.beschaffung.zusagen.takeOrdered', { count: ordered }) }}
        </RouterLink>
        <template v-else>
          {{ t('grossanlass.beschaffung.zusagen.takeOrdered', { count: ordered }) }}
        </template>
      </span>
      <small :class="{ 'proc-coverage__over': open < 0 }">
        ·
        {{ open >= 0
          ? t('grossanlass.beschaffung.zusagen.takeRest', { count: open })
          : t('grossanlass.beschaffung.zusagen.takeOver', { count: Math.abs(open) }) }}
      </small>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import type { GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import {
  procurementCoveredQty,
  procurementCoverageOpen,
  procurementLoanedQty,
  procurementOrderedQty,
  procurementWishBreakdown,
} from '@/utils/grossanlassProcurementCoverage'

const props = withDefaults(defineProps<{
  line: GrossanlassProcurementLine
  otherTaken?: number
  hereTaken?: number
  linkLoans?: boolean
  compact?: boolean
  stack?: boolean
}>(), {
  hereTaken: 0,
  linkLoans: true,
  compact: false,
  stack: false,
})

const { t } = useI18n()
const route = useRoute()

const otherTaken = computed(() =>
  props.otherTaken != null ? Math.max(0, props.otherTaken) : procurementLoanedQty(props.line),
)
const ordered = computed(() => procurementOrderedQty(props.line))
const covered = computed(() => procurementCoveredQty(props.line, otherTaken.value, props.hereTaken))
const open = computed(() => procurementCoverageOpen(props.line, otherTaken.value, props.hereTaken))
const wishSum = computed(() => procurementWishBreakdown(props.line, (key, values) => t(key, values)))

const oneLiner = computed(() => {
  if (!props.stack) return ''
  const parts: string[] = []
  if (wishSum.value) parts.push(wishSum.value)
  if (otherTaken.value > 0) {
    parts.push(`${t('grossanlass.beschaffung.zusagen.takeTaken')} ${otherTaken.value}`)
  }
  return parts.join(' · ')
})

const departmentId = computed(() => String(route.params.departmentId || props.line.department_id || ''))
const onAbsprachen = computed(() => route.path.includes('/beschaffung/zusagen'))

const loanTo = computed(() => {
  if (!props.linkLoans || onAbsprachen.value || otherTaken.value <= 0 || !departmentId.value) {
    return null
  }
  return {
    path: `/${departmentId.value}/beschaffung/zusagen`,
    query: { line: props.line.id },
  }
})

const orderTo = computed(() => {
  if (ordered.value <= 0 || !departmentId.value) return null
  return {
    path: `/${departmentId.value}/beschaffung/bestellungen`,
    query: { line: props.line.id },
  }
})
</script>

<style scoped>
.proc-coverage {
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.4;
}
.proc-coverage--compact {
  font-size: 0.78rem;
}
.proc-coverage--compact > span,
.proc-coverage--compact > small {
  display: inline;
}
.proc-coverage__line {
  margin: 0 0 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.78rem;
  color: #334155;
}
.proc-coverage__line--inline {
  margin: 0;
  color: #475569;
}
.proc-coverage__stack {
  display: grid;
  gap: 2px 12px;
  margin: 0;
}
.proc-coverage__stack > div {
  display: grid;
  grid-template-columns: auto minmax(2.5rem, max-content);
  gap: 12px;
  align-items: baseline;
  justify-content: start;
}
.proc-coverage__stack dt {
  margin: 0;
  font-weight: 400;
  color: #64748b;
}
.proc-coverage__stack dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: #0f172a;
  text-align: right;
}
.proc-coverage__link {
  color: #1d4ed8;
  font-weight: 700;
  text-decoration: none;
}
.proc-coverage__link:hover {
  text-decoration: underline;
}
.proc-coverage__ordered {
  color: #1d4ed8;
}
.proc-coverage__over {
  color: #b45309;
}
.proc-coverage__short {
  color: #b45309;
}
</style>
