<template>
  <DevicesLayout :department-id="departmentId" :department-name="departmentLabel">
    <section class="devices-panel">
      <h2 class="panel-title">{{ t('devices.home.title') }}</h2>
      <p class="panel-desc muted">{{ t('devices.home.scanActivityHint') }}</p>

      <DevicesScanCapture
        ref="scanRef"
        :is-desktop="isDesktop"
        :hide-input="false"
        show-log
        @scan="onScan"
      />

      <p v-if="scanError" class="scan-error">{{ scanError }}</p>
    </section>

    <section class="devices-panel">
      <h2 class="panel-title">{{ t('devices.home.packingListTitle') }}</h2>
      <p v-if="loadingActivities" class="muted">{{ t('devices.home.loading') }}</p>
      <p v-else-if="packingActivities.length === 0" class="muted">{{ t('devices.home.noPacking') }}</p>
      <ul v-else class="activity-list" :class="{ 'activity-list--desktop': isDesktop }">
        <li v-for="act in packingActivities" :key="act.id">
          <button type="button" class="activity-row" @click="openPack(act.id)">
            <span class="activity-name">{{ act.name }}</span>
            <span class="activity-status">{{ statusLabel(act.status) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section v-if="departments.length > 1" class="devices-panel devices-panel--compact">
      <label class="pin-label" for="pin-dept">{{ t('devices.home.pinDepartment') }}</label>
      <select id="pin-dept" v-model="pinSelect" class="pin-select" @change="onPinChange">
        <option v-for="d in departments" :key="d.department_id" :value="d.department_id">
          {{ d.department?.name || d.department_id }}
        </option>
      </select>
    </section>
  </DevicesLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/apiClient'
import { getPublicActivityByCode } from '@/api/public/publicLookup'
import DevicesLayout from '@/components/devices/DevicesLayout.vue'
import DevicesScanCapture from '@/components/devices/DevicesScanCapture.vue'
import { useDevicesUiMode } from '@/composables/useDevicesUiMode'
import { useAuthStore } from '@/stores/auth'
import { getDevicesOrigin, isDevicesHost, setPinnedDepartmentId, getPinnedDepartmentId } from '@/utils/devicesHost'
import type { ScanParseResult } from '@/utils/scanParser'

interface ActivityRow {
  id: string
  name: string
  status: string
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const { isDesktop } = useDevicesUiMode()

const departmentId = computed(() => String(route.params.departmentId || ''))
const departments = computed(() => authStore.departments || [])
const departmentLabel = computed(() => {
  const d = departments.value.find((x) => x.department_id === departmentId.value)
  return d?.department?.name || departmentId.value
})

const loadingActivities = ref(false)
const packingActivities = ref<ActivityRow[]>([])
const scanError = ref('')
const scanRef = ref<InstanceType<typeof DevicesScanCapture> | null>(null)
const pinSelect = ref(departmentId.value)

function statusLabel(status: string): string {
  const key = `devices.activityStatus.${status}`
  const label = t(key)
  return label !== key ? label : status
}

function mapActivity(raw: Record<string, unknown>): ActivityRow {
  return {
    id: String(raw.id ?? ''),
    name: String(raw.name ?? ''),
    status: String(raw.status ?? ''),
  }
}

async function loadPackingActivities() {
  if (!departmentId.value) return
  loadingActivities.value = true
  try {
    const { data } = await apiClient.get<Record<string, unknown>[]>('/api/activities', {
      params: { department_id: departmentId.value },
    })
    const list = Array.isArray(data) ? data : []
    packingActivities.value = list
      .map((row) => mapActivity(row as Record<string, unknown>))
      .filter((a) => a.status === 'packing')
  } catch {
    packingActivities.value = []
  } finally {
    loadingActivities.value = false
  }
}

function openPack(activityId: string) {
  const id = activityId?.trim()
  if (!id) {
    scanError.value = t('devices.home.scanNotFound')
    return
  }
  const dept = departmentId.value
  if (!dept) {
    scanError.value = t('devices.home.scanNotFound')
    return
  }
  if (!isDevicesHost()) {
    const origin = getDevicesOrigin()
    if (origin) {
      window.location.assign(`${origin}/${encodeURIComponent(dept)}/pack/${encodeURIComponent(id)}`)
      return
    }
  }
  void router.push({
    name: 'DevicesPackSession',
    params: { departmentId: dept, activityId: id },
  })
}

async function onScan(result: ScanParseResult) {
  scanError.value = ''
  if (result.type !== 'activity') {
    scanError.value = t('devices.home.scanExpectActivity')
    return
  }
  try {
    const lookup = await getPublicActivityByCode(result.activityCode)
    const actDept = lookup.department?.id
    const actId = lookup.activity?.id
    if (!actId) {
      scanError.value = t('devices.home.scanNotFound')
      return
    }
    if (actDept && actDept !== departmentId.value) {
      scanError.value = t('devices.home.scanWrongDepartment')
      return
    }
    openPack(actId)
  } catch {
    scanError.value = t('devices.home.scanNotFound')
  }
}

function onPinChange() {
  const id = pinSelect.value
  if (!id) return
  setPinnedDepartmentId(id)
  authStore.setActiveDepartment(id)
  if (id !== departmentId.value) {
    void router.replace({ name: 'DevicesHome', params: { departmentId: id } })
  }
}

onMounted(() => {
  const pinned = getPinnedDepartmentId()
  if (pinned && pinned !== departmentId.value) {
    const has = departments.value.some((d) => d.department_id === pinned)
    if (has) {
      void router.replace({ name: 'DevicesHome', params: { departmentId: pinned } })
      return
    }
  }
  if (!pinned && departmentId.value) {
    setPinnedDepartmentId(departmentId.value)
  }
  pinSelect.value = departmentId.value
  void loadPackingActivities()
})

watch(departmentId, () => {
  pinSelect.value = departmentId.value
  void loadPackingActivities()
})
</script>

<style scoped>
.devices-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 14px;
}

.devices-panel--compact {
  padding: 10px 14px;
}

.panel-title {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 700;
}

.panel-desc {
  margin: 0 0 12px;
  font-size: 13px;
}

.muted {
  color: #64748b;
}

.scan-error {
  margin: 10px 0 0;
  padding: 10px 12px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
}

.activity-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.activity-row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  text-align: left;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 8px;
  background: #f8fafc;
  cursor: pointer;
  font: inherit;
  color: inherit;
}

.activity-row:active {
  background: #e0f2fe;
}

.activity-list--desktop .activity-row {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}

.activity-name {
  font-weight: 600;
  font-size: 15px;
}

.activity-status {
  font-size: 12px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.pin-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
}

.pin-select {
  width: 100%;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  font: inherit;
}
</style>
