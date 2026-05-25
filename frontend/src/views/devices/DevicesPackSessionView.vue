<template>
  <DevicesLayout :department-id="departmentId" :department-name="departmentLabel">
    <div class="session-toolbar">
      <button type="button" class="btn-back" @click="goHome">{{ t('devices.pack.back') }}</button>
      <span class="flow-badge">{{ t('devices.pack.flowHin') }}</span>
    </div>

    <p v-if="loading && !packItems.length" class="muted">{{ t('devices.pack.loading') }}</p>
    <p v-else-if="error === 'missing_activity'" class="load-error">{{ t('devices.pack.missingActivity') }}</p>
    <p v-else-if="error" class="load-error">{{ t('devices.pack.loadError') }}</p>

    <template v-else>
      <header class="session-head">
        <h2 class="session-title">{{ activityName || t('devices.pack.untitled') }}</h2>
        <p class="session-status muted">{{ statusLabel(activityStatus) }}</p>
        <p v-if="!isPackListEditable" class="session-warn">{{ t('devices.pack.notEditable') }}</p>
      </header>

      <DevicesScanCapture
        ref="scanRef"
        :is-desktop="isDesktop"
        :hide-input="!isDesktop"
        :show-log="isDesktop"
        :show-hint="true"
        :autofocus="isPackListEditable"
        @scan="onScan"
      />

      <div
        v-if="scanFeedback"
        class="scan-feedback"
        :class="`scan-feedback--${scanFeedback.kind}`"
        role="status"
      >
        {{ scanFeedback.message }}
      </div>

      <section
        v-if="activePackItem && activeLeftQty > 0 && isPackListEditable"
        class="pack-all-panel"
        aria-live="polite"
      >
        <p class="pack-all-title">{{ activePackItem.materialName }}</p>
        <p class="pack-all-meta">
          {{
            t('devices.pack.packAllHint', {
              packed: activePackItem.quantityPacked,
              ordered: activePackItem.quantityOrdered,
              unit: packUnitLabel(activePackItem),
            })
          }}
        </p>
        <button
          type="button"
          class="btn-pack-all"
          :disabled="moving"
          @click="onPackAllRemaining"
        >
          {{
            t('devices.pack.packAllButton', {
              count: activeLeftQty,
              unit: packUnitLabel(activePackItem),
            })
          }}
        </button>
      </section>

      <p v-if="moving" class="muted scan-busy">{{ t('devices.pack.scanBusy') }}</p>

      <div v-if="progress" class="progress-block">
        <div
          class="progress-bar"
          role="progressbar"
          :aria-valuenow="progress.progressPercent"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div class="progress-fill" :style="{ width: `${progress.progressPercent}%` }" />
        </div>
        <p class="progress-text">
          {{
            t('devices.pack.progress', {
              packed: progress.packedItems,
              total: progress.totalItems,
              percent: progress.progressPercent,
            })
          }}
        </p>
      </div>

      <label v-if="isDesktop" class="filter-open">
        <input v-model="onlyOpen" type="checkbox" />
        <span>{{ t('devices.pack.onlyOpen') }}</span>
      </label>

      <ul class="pack-list" :class="{ 'pack-list--desktop': isDesktop }">
        <li
          v-for="item in displayItems"
          :key="item.id"
          class="pack-row"
          :class="{
            'pack-row--done': isRowDone(item),
            'pack-row--flash': item.id === flashItemId,
          }"
        >
          <span class="pack-name">{{ item.materialName }}</span>
          <span class="pack-qty">
            {{ item.quantityPacked }} / {{ item.quantityOrdered }}
            <span v-if="packUnitLabel(item)" class="pack-unit"> {{ packUnitLabel(item) }}</span>
          </span>
          <button
            v-if="!isRowDone(item) && isPackListEditable && item.id === activePackItemId"
            type="button"
            class="btn-pack-all-inline"
            :disabled="moving"
            @click="onPackAllForItem(item)"
          >
            {{ t('devices.pack.packAllShort') }}
          </button>
          <span v-if="isDesktop" class="pack-flag">
            {{ isRowDone(item) ? t('devices.pack.done') : t('devices.pack.open') }}
          </span>
        </li>
      </ul>

      <p v-if="!displayItems.length" class="muted">{{ t('devices.pack.empty') }}</p>
    </template>
  </DevicesLayout>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import DevicesLayout from '@/components/devices/DevicesLayout.vue'
import DevicesScanCapture from '@/components/devices/DevicesScanCapture.vue'
import { useDevicesPackSession } from '@/composables/useDevicesPackSession'
import {
  executeDevicesHinMove,
  executeDevicesHinMoveForPackItem,
  getDevicesHinLeftQty,
  type DevicesHinErrorCode,
  resolveDevicesHinStage,
} from '@/composables/useDevicesPackHin'
import { useDevicesUiMode } from '@/composables/useDevicesUiMode'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useAuthStore } from '@/stores/auth'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { ScanParseResult } from '@/utils/scanParser'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const { isDesktop } = useDevicesUiMode()
const { canManageMaterials } = useDepartmentMemberRole()

const departmentId = computed(() => String(route.params.departmentId || ''))
const activityId = computed(() => String(route.params.activityId || ''))

const departmentLabel = computed(() => {
  const d = authStore.departments.find((x) => x.department_id === departmentId.value)
  return d?.department?.name || departmentId.value
})

const {
  loading,
  error,
  activityName,
  activityStatus,
  activityType,
  isPackListEditable,
  packItems,
  progress,
  refresh,
} = useDevicesPackSession(activityId)

const onlyOpen = ref(false)
const moving = ref(false)
const flashItemId = ref<string | null>(null)
const activePackItemId = ref<string | null>(null)
const scanRef = ref<InstanceType<typeof DevicesScanCapture> | null>(null)

let flashTimer: ReturnType<typeof setTimeout> | null = null
let feedbackTimer: ReturnType<typeof setTimeout> | null = null

const scanFeedback = ref<{ kind: 'success' | 'error' | 'warning'; message: string } | null>(null)

const hinStageLabel = computed(() => {
  const { stage } = resolveDevicesHinStage(
    activityType.value,
    activityStatus.value,
    canManageMaterials.value,
  )
  const key = `devices.pack.stages.${stage}`
  const label = t(key)
  return label !== key ? label : stage
})

function statusLabel(status: string): string {
  const key = `devices.activityStatus.${status}`
  const label = t(key)
  return label !== key ? label : status
}

function isRowDone(item: ActivityPackItem): boolean {
  return item.isFullyPacked || item.quantityPacked >= item.quantityOrdered
}

const displayItems = computed(() => {
  const items = packItems.value || []
  if (!onlyOpen.value) return items
  return items.filter((i) => !isRowDone(i))
})

const activePackItem = computed(() => {
  if (!activePackItemId.value) return null
  return packItems.value.find((p) => p.id === activePackItemId.value) ?? null
})

const activeLeftQty = computed(() => {
  const item = activePackItem.value
  if (!item) return 0
  return getDevicesHinLeftQty(
    item,
    activityType.value,
    activityStatus.value,
    canManageMaterials.value,
  )
})

function packUnitLabel(item: ActivityPackItem): string {
  if (item.packUnit?.trim()) return item.packUnit.trim()
  if (item.packSize != null && item.packSize > 1) {
    return t('devices.pack.packUnitBundle', { size: item.packSize })
  }
  return t('devices.pack.packUnitDefault')
}

function hinErrorMessage(code: DevicesHinErrorCode, detail?: string): string {
  const key = `devices.pack.hinErrors.${code}`
  const msg = t(key)
  if (detail && code === 'move_failed') {
    return `${msg !== key ? msg : code}: ${detail}`
  }
  return msg !== key ? msg : code
}

function setFeedback(kind: 'success' | 'error' | 'warning', message: string) {
  scanFeedback.value = { kind, message }
  if (feedbackTimer) clearTimeout(feedbackTimer)
  feedbackTimer = setTimeout(() => {
    scanFeedback.value = null
  }, 4000)
}

function flashRow(itemId: string) {
  flashItemId.value = itemId
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => {
    flashItemId.value = null
  }, 1200)
}

function applyHinOutcome(outcome: Awaited<ReturnType<typeof executeDevicesHinMove>>, moveAll: boolean) {
  if (!outcome.ok) {
    setFeedback(
      outcome.code === 'offline' ? 'warning' : 'error',
      hinErrorMessage(outcome.code, outcome.detail),
    )
    return
  }

  const idx = packItems.value.findIndex((p) => p.id === outcome.packItem.id)
  if (idx !== -1) {
    packItems.value[idx] = outcome.packItem
  }

  activePackItemId.value = outcome.packItem.id
  flashRow(outcome.packItem.id)

  const leftAfter = getDevicesHinLeftQty(
    outcome.packItem,
    activityType.value,
    activityStatus.value,
    canManageMaterials.value,
  )
  if (leftAfter <= 0) {
    activePackItemId.value = null
  }

  if (moveAll) {
    setFeedback(
      'success',
      t('devices.pack.packAllSuccess', {
        name: outcome.materialName,
        count: outcome.moveQty,
        unit: packUnitLabel(outcome.packItem),
      }),
    )
  } else {
    setFeedback(
      'success',
      t('devices.pack.hinSuccess', {
        name: outcome.materialName,
        qty: outcome.moveQty,
        unit: packUnitLabel(outcome.packItem),
        stage: hinStageLabel.value,
        remaining: leftAfter,
      }),
    )
  }
  void refresh(true)
}

async function runHinMove(
  runner: () => Promise<Awaited<ReturnType<typeof executeDevicesHinMove>>>,
  moveAll: boolean,
) {
  if (moving.value) return
  moving.value = true
  try {
    const outcome = await runner()
    applyHinOutcome(outcome, moveAll)
  } finally {
    moving.value = false
    scanRef.value?.focusInput()
  }
}

async function onScan(result: ScanParseResult) {
  if (result.type === 'activity') {
    setFeedback('warning', t('devices.pack.scanExpectMaterial'))
    return
  }

  if (result.type === 'unknown') {
    setFeedback('error', t('devices.pack.scanUnknown'))
    return
  }

  await runHinMove(
    () =>
      executeDevicesHinMove({
        activityId: activityId.value,
        departmentId: departmentId.value,
        activityType: activityType.value,
        activityStatus: activityStatus.value,
        isPackListEditable: isPackListEditable.value,
        packItems: packItems.value,
        scan: result,
        canManageMaterials: canManageMaterials.value,
      }),
    false,
  )
}

function onPackAllForItem(item: ActivityPackItem) {
  activePackItemId.value = item.id
  void onPackAllRemaining()
}

async function onPackAllRemaining() {
  const item = activePackItem.value
  if (!item || activeLeftQty.value <= 0) return

  await runHinMove(
    () =>
      executeDevicesHinMoveForPackItem({
        activityId: activityId.value,
        departmentId: departmentId.value,
        activityType: activityType.value,
        activityStatus: activityStatus.value,
        isPackListEditable: isPackListEditable.value,
        packItem: item,
        moveAll: true,
        canManageMaterials: canManageMaterials.value,
      }),
    true,
  )
}

function goHome() {
  void router.push({ name: 'DevicesHome', params: { departmentId: departmentId.value } })
}

watch(isPackListEditable, (editable) => {
  if (editable) {
    scanRef.value?.focusInput()
  }
})

onUnmounted(() => {
  if (flashTimer) clearTimeout(flashTimer)
  if (feedbackTimer) clearTimeout(feedbackTimer)
})
</script>

<style scoped>
.session-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.btn-back {
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 8px;
  padding: 8px 12px;
  font: inherit;
  cursor: pointer;
}

.flow-badge {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #1d4ed8;
  background: #dbeafe;
  padding: 4px 8px;
  border-radius: 6px;
}

.session-head {
  margin-bottom: 14px;
}

.session-title {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  line-height: 1.25;
}

.session-status {
  margin: 4px 0 0;
  font-size: 13px;
}

.session-warn {
  margin: 8px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #b45309;
}

.scan-feedback {
  margin: 0 0 12px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
}

.scan-feedback--success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
}

.scan-feedback--error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

.scan-feedback--warning {
  background: #fef9c3;
  color: #854d0e;
  border: 1px solid #fde047;
}

.scan-busy {
  margin: 0 0 8px;
  font-size: 13px;
}

.progress-block {
  margin-bottom: 16px;
}

.progress-bar {
  height: 12px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #16a34a;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.progress-text {
  margin: 8px 0 0;
  font-size: 14px;
  font-weight: 600;
}

.filter-open {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
  cursor: pointer;
}

.pack-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.pack-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 6px 12px;
  padding: 12px 14px;
  margin-bottom: 8px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
  transition: background 0.2s ease, border-color 0.2s ease;
}

.pack-row--done {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.pack-row--flash {
  background: #dbeafe;
  border-color: #60a5fa;
}

.pack-all-panel {
  margin: 0 0 14px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 2px solid #2563eb;
  background: #eff6ff;
}

.pack-all-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 800;
}

.pack-all-meta {
  margin: 0 0 12px;
  font-size: 14px;
  color: #334155;
}

.btn-pack-all {
  width: 100%;
  border: none;
  border-radius: 10px;
  padding: 14px 16px;
  font: inherit;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  color: #fff;
  background: #2563eb;
}

.btn-pack-all:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-pack-all-inline {
  grid-column: 1 / -1;
  margin-top: 4px;
  border: 1px solid #2563eb;
  border-radius: 8px;
  padding: 8px 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #1d4ed8;
  background: #eff6ff;
  cursor: pointer;
}

.btn-pack-all-inline:disabled {
  opacity: 0.55;
}

.pack-unit {
  font-size: 12px;
  color: #64748b;
}

.pack-list--desktop .pack-row {
  grid-template-columns: 1fr auto auto;
  align-items: center;
}

.pack-list--desktop .btn-pack-all-inline {
  grid-column: auto;
  margin-top: 0;
}

.pack-name {
  font-weight: 600;
  font-size: 15px;
}

.pack-qty {
  font-variant-numeric: tabular-nums;
  font-size: 14px;
  color: #334155;
}

.pack-flag {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
}

.pack-row--done .pack-flag {
  color: #15803d;
}

.muted {
  color: #64748b;
}

.load-error {
  color: #b91c1c;
  font-weight: 600;
}
</style>
