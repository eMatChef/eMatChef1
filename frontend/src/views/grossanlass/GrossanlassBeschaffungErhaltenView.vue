<template>
  <div class="beschaffung-erhalten">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.erhalten.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="lines.length === 0"
      variant="default"
      icon="mdi-package-check"
      :title="t('grossanlass.beschaffung.erhalten.emptyTitle')"
      :description="t('grossanlass.beschaffung.erhalten.emptyDescription')"
    />

    <div v-else class="lines-list">
      <article v-for="line in lines" :key="line.id" class="line-card">
        <GrossanlassProcurementLineSummary :line="line" />

        <div class="receive-progress">
          {{ t('grossanlass.beschaffung.erhalten.receivedProgress', {
            received: line.received_quantity_sum,
            total: line.quantity,
          }) }}
        </div>

        <div class="sources-block">
          <h4>{{ t('grossanlass.beschaffung.erhalten.distributionTitle') }}</h4>
          <p class="muted">{{ t('grossanlass.beschaffung.erhalten.distributionHint') }}</p>
          <ul class="sources-list">
            <li v-for="source in line.source_wishes" :key="source.id" class="source-row">
              <div class="source-info">
                <strong>{{ source.quantity }}× {{ source.label }}</strong>
                <span class="source-meta">{{ source.group_name }} · {{ source.location }}</span>
                <span class="source-meta">{{ source.created_by_name }}</span>
              </div>
              <ETextField
                v-model="allocationForms[line.id][source.id]"
                type="number"
                min="0"
                :max="source.quantity"
                :label="t('grossanlass.beschaffung.erhalten.receivedQty')"
                hide-details
                density="compact"
                class="qty-input"
              />
            </li>
          </ul>
        </div>

        <div class="receive-actions">
          <EButton
            variant="secondary"
            size="small"
            :loading="savingLineId === line.id"
            @click="receiveFull(line)"
          >
            {{ t('grossanlass.beschaffung.erhalten.receiveFull') }}
          </EButton>
          <EButton
            variant="primary"
            size="small"
            :loading="savingLineId === line.id"
            @click="saveAllocation(line)"
          >
            {{ t('grossanlass.beschaffung.erhalten.saveAllocation') }}
          </EButton>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import { EButton, ETextField } from '@/components/form/base'
import {
  listGrossanlassProcurementLines,
  recordGrossanlassProcurementReceived,
  type GrossanlassProcurementLine,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const savingLineId = ref<string | null>(null)
const allocationForms = reactive<Record<string, Record<string, string>>>({})

function ensureAllocations(line: GrossanlassProcurementLine) {
  const map: Record<string, string> = {}
  for (const source of line.source_wishes) {
    map[source.id] = String(source.received_quantity ?? 0)
  }
  allocationForms[line.id] = map
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    const all = await listGrossanlassProcurementLines(departmentId())
    lines.value = all.filter(
      (l) => l.order && l.status !== 'erhalten' && ['bestellt', 'teilweise_erhalten'].includes(l.status),
    )
    lines.value.forEach(ensureAllocations)
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.erhalten.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

async function receiveFull(line: GrossanlassProcurementLine) {
  savingLineId.value = line.id
  try {
    await recordGrossanlassProcurementReceived(departmentId(), line.id, { full: true })
    toast.success(t('grossanlass.beschaffung.erhalten.saveSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.erhalten.errorSave'))
  } finally {
    savingLineId.value = null
  }
}

async function saveAllocation(line: GrossanlassProcurementLine) {
  savingLineId.value = line.id
  const form = allocationForms[line.id]
  try {
    await recordGrossanlassProcurementReceived(departmentId(), line.id, {
      allocations: line.source_wishes.map((s) => ({
        wish_line_id: s.id,
        quantity: Number(form[s.id] || 0),
      })),
    })
    toast.success(t('grossanlass.beschaffung.erhalten.saveSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.erhalten.errorSave'))
  } finally {
    savingLineId.value = null
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-erhalten { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.lines-list { display: flex; flex-direction: column; gap: 12px; }
.line-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; background: #fff; }
.receive-progress { margin-top: 8px; font-size: 0.82rem; font-weight: 600; color: #334155; }
.sources-block { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e5e7eb; }
.sources-block h4 { margin: 0 0 4px; font-size: 0.85rem; font-weight: 600; }
.muted { margin: 0 0 8px; font-size: 0.75rem; color: #94a3b8; }
.sources-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.source-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fafafa; }
.source-meta { display: block; font-size: 0.75rem; color: #64748b; margin-top: 2px; }
.qty-input { width: 120px; flex-shrink: 0; }
.receive-actions { display: flex; gap: 8px; margin-top: 12px; }
</style>
