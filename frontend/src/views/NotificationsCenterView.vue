<template>
  <div class="dept-page notifications-center-view">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>Nachrichtenzentrale</h1>
        <span class="subtitle">Offene Einladungen zu Camps und Anlässen anderer Abteilungen.</span>
      </div>
    </div>

    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Benachrichtigungen werden geladen...</p>
    </div>
    <div v-else-if="items.length === 0" class="empty-state">
      <h3>Keine Benachrichtigungen</h3>
      <p>Es liegen keine offenen Einladungen vor.</p>
    </div>
    <div v-else class="notifications-table-wrapper">
      <table class="notifications-table">
        <thead>
          <tr>
            <th>Abteilung</th>
            <th>Aktivität</th>
            <th>Typ</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="invite in items"
            :key="`${invite.activity_id}-${invite.source_department_id}`"
          >
            <td>{{ invite.source_department_name }}</td>
            <td>{{ invite.activity_name }}</td>
            <td>{{ invite.activity_type === 'camp' ? 'Camp' : 'Anlass' }}</td>
            <td class="notifications-actions-cell">
              <button type="button" class="btn-success btn-xs" @click="decide(invite, 'accepted')">
                Annehmen
              </button>
              <button type="button" class="btn-danger-outline btn-xs" @click="decide(invite, 'rejected')">
                Ablehnen
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from '@/composables/useToast'
import {
  getPendingDepartmentActivityInvites,
  decideDepartmentActivityInvite,
  type PendingDepartmentActivityInvite,
} from '@/api/joinRequests'

const route = useRoute()
const toast = useToast()
const isLoading = ref(false)
const items = ref<PendingDepartmentActivityInvite[]>([])

const departmentId = computed(() => String(route.params.departmentId || ''))

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    const result = await getPendingDepartmentActivityInvites(departmentId.value)
    items.value = result.items || []
  } catch {
    items.value = []
    toast.error('Benachrichtigungen konnten nicht geladen werden.')
  } finally {
    isLoading.value = false
  }
}

async function decide(invite: PendingDepartmentActivityInvite, decision: 'accepted' | 'rejected') {
  if (!departmentId.value) return
  try {
    await decideDepartmentActivityInvite({
      activityId: invite.activity_id,
      departmentId: departmentId.value,
      decision,
    })
    items.value = items.value.filter(
      (e) => !(e.activity_id === invite.activity_id && e.source_department_id === invite.source_department_id)
    )
    toast.success(decision === 'accepted' ? 'Einladung angenommen' : 'Einladung abgelehnt')
  } catch (err: any) {
    toast.error(err?.response?.data?.error || 'Entscheid konnte nicht gespeichert werden')
  }
}

onMounted(load)
watch(departmentId, () => load())
</script>

<style scoped>
.notifications-actions-cell {
  white-space: nowrap;
}
.notifications-actions-cell .btn-xs + .btn-xs {
  margin-left: 8px;
}
</style>
