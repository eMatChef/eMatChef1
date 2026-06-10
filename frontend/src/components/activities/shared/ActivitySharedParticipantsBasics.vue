<template>
  <div class="activity-shared-participants-basics">
    <div
      class="activity-material-scope-tabs activity-shared-dept-flags"
      role="tablist"
      :aria-label="t('activities.sharedBasics.deptFlagsAria')"
    >
      <button
        type="button"
        class="activity-material-scope-tab"
        role="tab"
        :aria-selected="selectedDeptId === hostDepartmentId"
        @click="selectDepartment(hostDepartmentId)"
      >
        {{ hostDepartmentName }}
        <span class="activity-shared-dept-host-badge">{{ t('activities.sharedBasics.hostBadge') }}</span>
      </button>
      <button
        v-for="inv in visibleInvites"
        :key="inv.id"
        type="button"
        class="activity-material-scope-tab"
        role="tab"
        :aria-selected="selectedDeptId === inv.id"
        @click="selectDepartment(inv.id)"
      >
        {{ inv.name || inv.id }}
        <span v-if="inv.status === 'pending'" class="activity-shared-dept-status text-muted">
          · {{ t('activities.detail.invitePending') }}
        </span>
      </button>
    </div>

    <div
      v-if="selectedParticipant"
      class="activity-shared-group-panel"
      role="tabpanel"
      :aria-label="selectedParticipant.label"
    >
      <div class="activity-shared-group-row-head">
        <span class="activity-shared-group-dept">{{ selectedParticipant.label }}</span>
        <span v-if="selectedParticipant.isHost" class="activity-shared-dept-host-badge">
          {{ t('activities.sharedBasics.hostBadge') }}
        </span>
        <span
          v-else-if="selectedParticipant.invite?.organisation_name"
          class="text-muted activity-shared-group-org"
        >
          ({{ selectedParticipant.invite.organisation_name }})
        </span>
        <span
          v-if="selectedParticipant.id === viewerDepartmentId"
          class="activity-shared-dept-own-hint text-muted"
        >
          · {{ t('activities.sharedBasics.yourDepartment') }}
        </span>
      </div>

      <slot
        v-if="selectedParticipant.isHost"
        name="host-group"
        :department-id="hostDepartmentId"
        :can-edit="canEditHostGroup && selectedDeptId === hostDepartmentId"
      />
      <slot
        v-else-if="selectedParticipant.invite"
        name="guest-group"
        :invite="selectedParticipant.invite"
        :department-id="selectedParticipant.id"
        :can-edit="canEditGuestGroup(selectedParticipant.id)"
        :group-id="selectedParticipant.invite.group_id ?? null"
        :group-name="selectedParticipant.invite.group_name ?? null"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityInvitedDepartmentApi } from '@/api/activities'

const props = defineProps<{
  hostDepartmentId: string
  hostDepartmentName: string
  invitedDepartments: ActivityInvitedDepartmentApi[]
  viewerDepartmentId: string
  canEditHostGroup: boolean
  guestAssignDepartmentId?: string | null
  guestCanAssignGroup?: boolean
}>()

const emit = defineEmits<{
  'update:selectedDepartmentId': [id: string]
}>()

const { t } = useI18n()

const selectedDeptId = ref('')

const visibleInvites = computed(() =>
  (props.invitedDepartments ?? []).filter((inv) => (inv.status ?? 'pending') !== 'rejected'),
)

const allParticipantIds = computed(() => [
  props.hostDepartmentId,
  ...visibleInvites.value.map((inv) => inv.id),
])

function defaultSelectedId(): string {
  const viewer = props.viewerDepartmentId
  if (viewer && allParticipantIds.value.includes(viewer)) return viewer
  return props.hostDepartmentId
}

watch(
  () => [props.viewerDepartmentId, props.hostDepartmentId, visibleInvites.value.map((i) => i.id).join(',')] as const,
  () => {
    if (!selectedDeptId.value || !allParticipantIds.value.includes(selectedDeptId.value)) {
      selectedDeptId.value = defaultSelectedId()
    }
  },
  { immediate: true },
)

const selectedParticipant = computed(() => {
  const id = selectedDeptId.value
  if (id === props.hostDepartmentId) {
    return {
      id,
      label: props.hostDepartmentName,
      isHost: true,
      invite: null as ActivityInvitedDepartmentApi | null,
    }
  }
  const inv = visibleInvites.value.find((i) => i.id === id)
  if (!inv) return null
  return {
    id,
    label: inv.name || inv.id,
    isHost: false,
    invite: inv,
  }
})

function selectDepartment(id: string) {
  if (!allParticipantIds.value.includes(id)) return
  selectedDeptId.value = id
  emit('update:selectedDepartmentId', id)
}

function canEditGuestGroup(departmentId: string): boolean {
  if (departmentId !== props.viewerDepartmentId) return false
  if (!props.guestCanAssignGroup) return false
  return props.guestAssignDepartmentId === departmentId
}
</script>

<style scoped>
.activity-shared-participants-basics {
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: 100%;
}

.activity-shared-dept-flags {
  margin-bottom: 0;
}

.activity-shared-dept-host-badge {
  margin-left: 4px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  opacity: 0.85;
}

.activity-shared-dept-status,
.activity-shared-dept-own-hint {
  font-weight: 500;
  font-size: 11px;
}

.activity-shared-group-panel {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
}

.activity-shared-group-row-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
  margin-bottom: 8px;
}

.activity-shared-group-dept {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.activity-shared-group-org {
  font-size: 12px;
}
</style>

<style>
@import '@/components/activities/shared/activityMaterialScopeTabs.css';
</style>
