<template>
  <EDialog v-model="open" :max-width="520" :title="t('notificationsCenter.activityInviteDecisionTitle')">
    <div v-if="invite" class="activity-invite-decision">
      <p class="activity-invite-decision__intro">
        {{
          t('notificationsCenter.activityInviteDecisionIntro', {
            name: invite.activity_name,
            department: invite.source_department_name,
          })
        }}
      </p>
      <div class="form-group">
        <label for="activity-invite-group">{{ t('notificationsCenter.activityInviteGroupLabel') }}</label>
        <select
          id="activity-invite-group"
          v-model="selectedGroupId"
          class="form-input"
          :disabled="loadingGroups || deciding"
        >
          <option value="">{{ t('notificationsCenter.activityInviteGroupPlaceholder') }}</option>
          <option v-for="g in flatGroups" :key="g.id" :value="g.id">
            {{ groupOptionLabel(g) }}
          </option>
        </select>
        <p class="field-hint text-muted">{{ t('notificationsCenter.activityInviteGroupHint') }}</p>
      </div>
      <p v-if="errorMessage" class="activity-invite-decision__error">{{ errorMessage }}</p>
    </div>
    <template #actions>
      <EButton
        variant="primary"
        size="small"
        :disabled="!canAccept || deciding"
        :loading="deciding && pendingDecision === 'accepted'"
        @click="submit('accepted')"
      >
        {{ t('notificationsCenter.accept') }}
      </EButton>
      <EButton
        variant="danger"
        size="small"
        :disabled="deciding"
        :loading="deciding && pendingDecision === 'rejected'"
        @click="submit('rejected')"
      >
        {{ t('notificationsCenter.reject') }}
      </EButton>
      <EButton variant="secondary" size="small" :disabled="deciding" @click="close">
        {{ t('common.cancel') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import { getGroups, type Group } from '@/api/groups'
import { decidePendingDepartmentInvite, type PendingDepartmentActivityInvite } from '@/api/joinRequests'
import { flattenGroupsWithLevel, type GroupWithLevel } from '@/utils/groupHierarchy'

const props = defineProps<{
  visible: boolean
  invite: PendingDepartmentActivityInvite | null
  departmentId: string
}>()

const emit = defineEmits<{
  close: []
  decided: [decision: 'accepted' | 'rejected']
}>()

const { t } = useI18n()

const groups = ref<Group[]>([])
const loadingGroups = ref(false)
const selectedGroupId = ref('')
const deciding = ref(false)
const pendingDecision = ref<'accepted' | 'rejected' | null>(null)
const errorMessage = ref('')

const open = computed({
  get: () => props.visible,
  set: (value: boolean) => {
    if (!value) emit('close')
  },
})

const flatGroups = computed(() => flattenGroupsWithLevel(groups.value))

const canAccept = computed(() => selectedGroupId.value.trim() !== '' && !loadingGroups.value)

watch(
  () => [props.visible, props.departmentId] as const,
  async ([isOpen, deptId]) => {
    if (!isOpen || !deptId) return
    selectedGroupId.value = ''
    errorMessage.value = ''
    pendingDecision.value = null
    loadingGroups.value = true
    try {
      groups.value = await getGroups(deptId)
    } catch {
      groups.value = []
      errorMessage.value = t('notificationsCenter.activityInviteGroupsLoadFailed')
    } finally {
      loadingGroups.value = false
    }
  },
  { immediate: true },
)

function groupOptionLabel(g: GroupWithLevel): string {
  const indent = g._level > 0 ? `${'— '.repeat(g._level)}` : ''
  return `${indent}${g.name}`
}

function close() {
  if (deciding.value) return
  emit('close')
}

async function submit(decision: 'accepted' | 'rejected') {
  if (!props.invite || !props.departmentId || deciding.value) return
  if (decision === 'accepted' && !canAccept.value) {
    errorMessage.value = t('notificationsCenter.activityInviteGroupRequired')
    return
  }

  deciding.value = true
  pendingDecision.value = decision
  errorMessage.value = ''
  try {
    await decidePendingDepartmentInvite({
      invite: props.invite,
      departmentId: props.departmentId,
      decision,
      groupId: decision === 'accepted' ? selectedGroupId.value : undefined,
    })
    emit('decided', decision)
    emit('close')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    errorMessage.value = e?.response?.data?.error || t('notificationsCenter.toastDecisionFailed')
  } finally {
    deciding.value = false
    pendingDecision.value = null
  }
}
</script>

<style scoped>
.activity-invite-decision__intro {
  margin: 0 0 16px;
  color: #374151;
  line-height: 1.45;
}

.activity-invite-decision__error {
  margin: 12px 0 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
</style>
