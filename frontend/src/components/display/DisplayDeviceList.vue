<template>
  <div class="device-list">
    <p v-if="!devices.length" class="muted">{{ t('display.devices.none') }}</p>
    <ul v-else class="device-rows">
      <li v-for="device in devices" :key="device.id" class="device-row">
        <div class="device-main">
          <strong class="device-name">{{ device.name }}</strong>
          <span class="chip" :class="`chip--${device.approval_state}`">{{ t(`display.devices.state.${device.approval_state}`) }}</span>
          <span v-if="device.approval_state !== 'revoked'" class="chip" :class="device.online ? 'chip--online' : 'chip--offline'">
            {{ device.online ? t('display.devices.online') : t('display.devices.offline') }}
          </span>
        </div>
        <div class="device-meta muted">
          <span v-if="device.approval_state === 'revoked'">
            {{ t('display.devices.revokedAt', { date: formatDate(device.revoked_at) }) }}
          </span>
          <span v-else-if="device.approval_state === 'expired'">
            {{ t('display.devices.expiredAt', { date: formatDate(device.approval_expires_at) }) }}
          </span>
          <span v-else>{{ t('display.devices.validUntil', { date: formatDate(device.approval_expires_at), days: daysLeft(device) }) }}</span>
          <span>
            {{ device.last_contact_at ? t('display.devices.lastContact', { date: formatDate(device.last_contact_at, true) }) : t('display.devices.neverContacted') }}
          </span>
          <span v-if="device.created_via !== 'pairing'">{{ t(`display.devices.via.${device.created_via}`) }}</span>
        </div>
        <div class="device-actions">
          <template v-if="device.approval_state !== 'revoked'">
            <EButton variant="secondary" size="small" @click="openRename(device)">{{ t('display.devices.rename') }}</EButton>
            <EButton
              :variant="device.approval_state === 'expired' ? 'primary' : 'secondary'"
              size="small"
              :loading="busyId === device.id"
              @click="extend(device)"
            >
              {{ device.approval_state === 'expired' ? t('display.devices.reapprove') : t('display.devices.extend') }}
            </EButton>
            <EButton v-if="targets.length" variant="secondary" size="small" @click="openReassign(device)">
              {{ t('display.devices.reassign') }}
            </EButton>
            <EButton variant="danger" size="small" :disabled="busyId === device.id" @click="revoke(device)">
              {{ t('display.devices.revoke') }}
            </EButton>
          </template>
          <template v-else>
            <span class="muted device-hint">{{ t('display.devices.revokedHint') }}</span>
            <EButton variant="danger" size="small" :disabled="busyId === device.id" @click="remove(device)">
              {{ t('display.devices.delete') }}
            </EButton>
          </template>
        </div>
      </li>
    </ul>

    <EDialog v-model="renameOpen" :max-width="420" :title="t('display.devices.rename')">
      <ETextField v-model="renameValue" :label="t('display.pairDialog.deviceName')" maxlength="120" hide-details />
      <template #actions>
        <EButton variant="secondary" size="small" @click="renameOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!renameValue.trim()" @click="saveRename">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>

    <EDialog v-model="reassignOpen" :max-width="420" :title="t('display.devices.reassign')">
      <p class="muted">{{ t('display.devices.reassignHint') }}</p>
      <ESelect v-model="reassignTo" :items="targetItems" :label="t('display.devices.reassignTarget')" hide-details />
      <template #actions>
        <EButton variant="secondary" size="small" @click="reassignOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!reassignTo" @click="saveReassign">{{ t('display.devices.reassign') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import {
  deleteDisplayDevice,
  extendDisplayDevice,
  revokeDisplayDevice,
  updateDisplayDevice,
  type DisplayDeviceRow,
} from '@/api/displayDevice'

/** Geräte eines Infoscreens: Department und Grossanlass nutzen dieselbe Verwaltung. */
const props = defineProps<{
  departmentId: string
  devices: DisplayDeviceRow[]
  /** Andere aktive Infoscreens desselben Departments (Ziele für „Zuweisen“). */
  targets: { id: string; name: string }[]
}>()

const emit = defineEmits<{ changed: [] }>()

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const busyId = ref<string | null>(null)
const renameOpen = ref(false)
const renameValue = ref('')
const renameTarget = ref<DisplayDeviceRow | null>(null)
const reassignOpen = ref(false)
const reassignTo = ref<string | null>(null)
const reassignTarget = ref<DisplayDeviceRow | null>(null)

const targetItems = computed(() => props.targets.map((s) => ({ value: s.id, title: s.name })))

function formatDate(iso: string | null, withTime = false): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const tag = String(locale.value ?? '').startsWith('de') ? 'de-CH' : 'en-CH'
  return d.toLocaleString(tag, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' })
}

function daysLeft(device: DisplayDeviceRow): number {
  return Math.max(0, Math.ceil((new Date(device.approval_expires_at).getTime() - Date.now()) / 86_400_000))
}

function errorMessage(err: unknown, fallback: string): string {
  return (err as { response?: { data?: { error?: string } } })?.response?.data?.error || fallback
}

async function run(device: DisplayDeviceRow, action: () => Promise<unknown>, success: string) {
  busyId.value = device.id
  try {
    await action()
    toast.success(success)
    emit('changed')
  } catch (err) {
    toast.error(errorMessage(err, t('display.devices.error')))
  } finally {
    busyId.value = null
  }
}

function openRename(device: DisplayDeviceRow) {
  renameTarget.value = device
  renameValue.value = device.name
  renameOpen.value = true
}

async function saveRename() {
  const device = renameTarget.value
  if (!device) return
  renameOpen.value = false
  await run(device, () => updateDisplayDevice(props.departmentId, device.id, { name: renameValue.value.trim() }), t('display.devices.toastRenamed'))
}

async function extend(device: DisplayDeviceRow) {
  await run(device, () => extendDisplayDevice(props.departmentId, device.id), t('display.devices.toastExtended'))
}

function openReassign(device: DisplayDeviceRow) {
  reassignTarget.value = device
  reassignTo.value = null
  reassignOpen.value = true
}

async function saveReassign() {
  const device = reassignTarget.value
  const target = reassignTo.value
  if (!device || !target) return
  reassignOpen.value = false
  await run(device, () => updateDisplayDevice(props.departmentId, device.id, { screen_id: target }), t('display.devices.toastReassigned'))
}

async function revoke(device: DisplayDeviceRow) {
  const ok = await confirm.confirm({
    title: t('display.devices.confirmRevokeTitle'),
    message: t('display.devices.confirmRevokeMessage', { name: device.name }),
    confirmText: t('display.devices.revoke'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  await run(device, () => revokeDisplayDevice(props.departmentId, device.id), t('display.devices.toastRevoked'))
}

async function remove(device: DisplayDeviceRow) {
  const ok = await confirm.confirm({
    title: t('display.devices.confirmDeleteTitle'),
    message: t('display.devices.confirmDeleteMessage', { name: device.name }),
    confirmText: t('display.devices.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  await run(device, () => deleteDisplayDevice(props.departmentId, device.id), t('display.devices.toastDeleted'))
}
</script>

<style scoped>
.device-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.device-row {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.device-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.device-name {
  font-size: 15px;
}

.device-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  font-size: 13px;
}

.device-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.device-hint {
  font-size: 13px;
}

.chip {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: #f3f4f6;
  color: #374151;
}

.chip--active,
.chip--online {
  background: #dcfce7;
  color: #166534;
}

.chip--expired {
  background: #fef3c7;
  color: #92400e;
}

.chip--revoked {
  background: #fee2e2;
  color: #991b1b;
}

.muted {
  color: #6b7280;
}
</style>
