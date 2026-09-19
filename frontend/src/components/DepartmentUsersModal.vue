<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="980"
    :title="dialogTitle"
    scrollable
    :retain-focus="false"
    card-class="department-users-modal-card"
  >
    <div class="department-users-modal-body">
      <p v-if="organisationId && departmentOptions.length === 0" class="empty-hint">
        {{ t('components.departmentUsersModal.noDepartmentsInOrg') }}
      </p>
      <template v-else>
        <ESelect
          v-if="organisationId && departmentOptions.length > 1"
          v-model="selectedDepartmentId"
          :items="departmentOptions"
          :label="t('components.departmentUsersModal.departmentLabel')"
          hide-details="auto"
          class="mb-3"
        />
        <UsersSettingsView
          v-if="activeDepartmentId"
          :key="activeDepartmentId"
          :department-id="activeDepartmentId"
          :is-grossanlass="activeDepartmentIsGrossanlass"
          initial-open-section="members"
          embedded
          @changed="emit('changed')"
        />
      </template>
    </div>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EDialog, ESelect } from '@/components/form/base'
import UsersSettingsView from '@/views/settings/UsersSettingsView.vue'
import type { Department } from '@/api/departments'

const props = withDefaults(
  defineProps<{
    isOpen: boolean
    /** Direkt ein Department bearbeiten */
    departmentId?: string | null
    /** Organisation: Department-Auswahl aus Liste */
    organisationId?: string | null
    organisationName?: string
    departmentName?: string
    departments?: Department[]
  }>(),
  {
    departmentId: null,
    organisationId: null,
    organisationName: '',
    departmentName: '',
    departments: () => [],
  },
)

const emit = defineEmits<{
  close: []
  changed: []
}>()

const { t } = useI18n()

const selectedDepartmentId = ref('')

const dialogOpen = computed({
  get: () => props.isOpen,
  set: (value: boolean) => {
    if (!value) emit('close')
  },
})

const orgDepartments = computed(() => {
  if (!props.organisationId) return []
  return props.departments
    .filter((dept) => dept.organisation_id === props.organisationId)
    .sort((a, b) => a.name.localeCompare(b.name, 'de'))
})

function buildDepartmentTreeOptions(depts: Department[]) {
  const byParent = new Map<string | null, Department[]>()
  depts.forEach((dept) => {
    const parentId = dept.parent_id ?? null
    const list = byParent.get(parentId) ?? []
    list.push(dept)
    byParent.set(parentId, list)
  })

  const items: Array<{ title: string; value: string }> = []

  function walk(parentId: string | null, level: number) {
    const children = byParent.get(parentId) ?? []
    children
      .sort((a, b) => a.name.localeCompare(b.name, 'de'))
      .forEach((dept) => {
        items.push({
          title: `${'— '.repeat(level)}${dept.name}`,
          value: dept.id,
        })
        walk(dept.id, level + 1)
      })
  }

  walk(null, 0)
  return items
}

const departmentOptions = computed(() => buildDepartmentTreeOptions(orgDepartments.value))

const activeDepartmentId = computed(() => {
  if (props.departmentId) return props.departmentId
  if (props.organisationId) return selectedDepartmentId.value || departmentOptions.value[0]?.value || ''
  return ''
})

const activeDepartmentIsGrossanlass = computed(() => {
  const id = activeDepartmentId.value
  if (!id) return null
  return Boolean(props.departments.find((dept) => dept.id === id)?.is_grossanlass)
})

const dialogTitle = computed(() => {
  if (props.departmentName) {
    return t('components.departmentUsersModal.titleDepartment', { name: props.departmentName })
  }
  if (props.organisationName) {
    return t('components.departmentUsersModal.titleOrganisation', { name: props.organisationName })
  }
  return t('settings.departmentUsers.title')
})

watch(
  () => [props.isOpen, props.departmentId, props.organisationId, props.departments] as const,
  ([open]) => {
    if (!open) {
      selectedDepartmentId.value = ''
      return
    }
    if (props.departmentId) {
      selectedDepartmentId.value = props.departmentId
      return
    }
    selectedDepartmentId.value = departmentOptions.value[0]?.value ?? ''
  },
  { immediate: true },
)
</script>

<style scoped>
.department-users-modal-body {
  padding: 4px 0 8px;
}

.empty-hint {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
}
</style>
