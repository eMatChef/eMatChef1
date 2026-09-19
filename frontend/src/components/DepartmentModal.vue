<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="1180"
    :title="dialogTitle"
    scrollable
    persistent
    :retain-focus="false"
    card-class="department-modal-card"
  >
    <form id="department-modal-form" class="department-modal-body" @submit.prevent="handleSubmit">
      <details
        class="dept-modal-accordion"
        :open="stammdatenAccordionOpen"
        @toggle="onStammdatenAccordionToggle"
      >
        <summary class="dept-modal-accordion__summary">
          {{ t('components.departmentModal.accordionStammdaten') }}
        </summary>
        <div class="dept-modal-accordion__body">
          <ETextField
            id="department-name"
            v-model="formData.name"
            :label="t('components.departmentModal.nameLabel')"
            :placeholder="t('components.departmentModal.namePlaceholder')"
            hide-details="auto"
            class="mb-3"
          />

          <ESelect
            id="organisation"
            v-model="formData.organisationId"
            :items="organisationItems"
            :label="t('components.departmentModal.organisationLabel')"
            hide-details="auto"
            class="mb-3"
            @update:model-value="onOrganisationChange"
          />

          <div v-if="formData.organisationId" class="form-group">
            <label class="form-label">{{ t('components.departmentModal.parentLabel') }}</label>
            <div class="tree-select-container">
              <div class="tree-select-header">
                <span>{{ t('components.departmentModal.parentPrompt') }}</span>
                <button
                  type="button"
                  @click="formData.parentId = null"
                  class="btn-clear-parent"
                  :class="{ active: formData.parentId === null }"
                >
                  {{ t('components.departmentModal.noParent') }}
                </button>
              </div>
              <div class="tree-select-content">
                <div
                  v-for="dept in availableParentDepartmentsTree"
                  :key="dept.id"
                  class="tree-select-item"
                  :class="{
                    selected: formData.parentId === dept.id,
                    disabled: isEdit && dept.id === props.department?.id
                  }"
                  :style="{ paddingLeft: `${dept.level * 20 + 12}px` }"
                  @click="selectParentDepartment(dept)"
                >
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="folder-icon">
                    <path
                      d="M2 4C2 3.44772 2.44772 3 3 3H6.58579C6.851 3 7.10536 3.10536 7.29289 3.29289L8.70711 4.70711C8.89464 4.89464 9.149 5 9.41421 5H13C13.5523 5 14 5.44772 14 6V12C14 12.5523 13.5523 13 13 13H3C2.44772 13 2 12.5523 2 12V4Z"
                      fill="currentColor"
                    />
                  </svg>
                  <span>{{ dept.name }}</span>
                </div>
              </div>
            </div>
            <p class="form-hint">
              {{ t('components.departmentModal.parentHint') }}
            </p>
          </div>
        </div>
      </details>

      <UsersSettingsView
        v-if="isEdit && props.department?.id"
        :key="`${props.department.id}-${initialFocus}`"
        :department-id="props.department.id"
        :is-grossanlass="isGrossanlassDept"
        :initial-open-section="usersInitialOpenSection"
        embedded
        @changed="emit('users-changed')"
      />

      <v-alert v-if="error" type="error" variant="tonal" class="mt-2" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="department-modal-form"
        :loading="isSubmitting"
        :disabled="isSubmitting"
      >
        {{ isEdit ? t('common.save') : t('common.add') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import UsersSettingsView, { type UsersSettingsOpenSection } from '@/views/settings/UsersSettingsView.vue'
import {
  createDepartment,
  updateDepartment,
  getDepartments,
  type Department,
} from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import {
  filterOrganisationsForUserPickers,
  memberOrganisationIdsFromUserDepartments,
  prepareOrganisationsForOrgSubAdminList,
  sortOrganisationsMembersFirst
} from '@/utils/organisationUserPicker'

interface Props {
  isOpen: boolean
  department?: Department | null
  preselectedOrganisationId?: string | null
  preselectedParentId?: string | null
  /** Beim Öffnen: Stammdaten oder Benutzer-Akkordeon fokussieren. */
  initialFocus?: 'stammdaten' | 'users'
}

const props = withDefaults(defineProps<Props>(), {
  department: null,
  preselectedOrganisationId: null,
  preselectedParentId: null,
  initialFocus: 'stammdaten',
})

const emit = defineEmits<{
  close: []
  saved: []
  'users-changed': []
}>()

const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()
const isSuperAdmin = computed(() =>
  (authStore.userRoles || []).includes('ROLE_SUPERADMIN')
)
const memberOrganisationIds = computed(() =>
  memberOrganisationIdsFromUserDepartments(authStore.departments)
)
const isEdit = computed(() => !!props.department)
const isGrossanlassDept = computed(() => Boolean(props.department?.is_grossanlass))

const dialogTitle = computed(() => {
  if (isEdit.value && props.department?.name) {
    return t('components.departmentModal.editTitleNamed', { name: props.department.name })
  }
  if (isEdit.value) {
    return t('components.departmentModal.editTitle')
  }
  return t('components.departmentModal.addTitle')
})

const usersInitialOpenSection = computed((): UsersSettingsOpenSection | null =>
  props.initialFocus === 'users' ? 'members' : null,
)

const dialogOpen = computed({
  get: () => props.isOpen,
  set: (value: boolean) => {
    if (!value) close()
  },
})

const organisationItems = computed(() =>
  organisations.value.map((org) => ({ title: org.name, value: org.id })),
)

const isSubmitting = ref(false)
const error = ref<string | null>(null)
const organisations = ref<Organisation[]>([])
const allDepartments = ref<Department[]>([])
const stammdatenAccordionOpen = ref(true)

function onStammdatenAccordionToggle(event: Event) {
  stammdatenAccordionOpen.value = (event.target as HTMLDetailsElement).open
}

function applyInitialFocus() {
  stammdatenAccordionOpen.value = props.initialFocus !== 'users'
}

const formData = ref({
  name: '',
  organisationId: '',
  parentId: null as string | null
})

const availableParentDepartmentsTree = computed(() => {
  if (!formData.value.organisationId) {
    return []
  }

  const currentDeptId = isEdit.value ? props.department?.id : null

  const isDescendant = (deptId: string): boolean => {
    if (!currentDeptId) return false
    const dept = allDepartments.value.find(d => d.id === deptId)
    if (!dept || !dept.parent_id) return false
    if (dept.parent_id === currentDeptId) return true
    return isDescendant(dept.parent_id)
  }

  const available = allDepartments.value.filter(dept =>
    dept.organisation_id === formData.value.organisationId &&
    dept.id !== currentDeptId &&
    !isDescendant(dept.id)
  )

  const mainDepts = available.filter(d => !d.parent_id)

  interface TreeDept {
    id: string
    name: string
    level: number
  }

  function buildTree(parentId: string | null, level: number): TreeDept[] {
    const children = available.filter(d => d.parent_id === parentId)
    const result: TreeDept[] = []

    children.forEach(dept => {
      result.push({
        id: dept.id,
        name: dept.name,
        level
      })
      result.push(...buildTree(dept.id, level + 1))
    })

    return result
  }

  const tree: TreeDept[] = []
  mainDepts.forEach(dept => {
    tree.push({
      id: dept.id,
      name: dept.name,
      level: 0
    })
    tree.push(...buildTree(dept.id, 1))
  })

  return tree
})

function selectParentDepartment(dept: { id: string }) {
  if (isEdit.value && dept.id === props.department?.id) {
    return
  }
  formData.value.parentId = dept.id
}

watch(
  () => [props.department, props.preselectedOrganisationId, props.preselectedParentId],
  (tuple) => {
    const dept = tuple[0] as Department | null | undefined
    const preselOrgId = tuple[1] as string | null | undefined
    const preselParentId = tuple[2] as string | null | undefined
    if (dept) {
      formData.value = {
        name: dept.name,
        organisationId: dept.organisation_id,
        parentId: dept.parent_id || null
      }
    } else {
      formData.value = {
        name: '',
        organisationId: preselOrgId || '',
        parentId: preselParentId || null
      }
    }
  },
  { immediate: true }
)

function onOrganisationChange() {
  formData.value.parentId = null
}

watch(() => props.isOpen, async (open) => {
  if (open) {
    error.value = null
    applyInitialFocus()
    try {
      const [rawOrgs, depts] = await Promise.all([getOrganisations(), getDepartments()])
      allDepartments.value = depts
      const picked = filterOrganisationsForUserPickers(rawOrgs)
      let list = prepareOrganisationsForOrgSubAdminList(picked, {
        isSuperAdmin: isSuperAdmin.value,
        memberOrganisationIds: memberOrganisationIds.value
      })
      const editOrgId = props.department?.organisation_id
      if (editOrgId && !list.some((o) => o.id === editOrgId)) {
        const missing =
          picked.find((o) => o.id === editOrgId) || rawOrgs.find((o) => o.id === editOrgId)
        if (missing) {
          list = sortOrganisationsMembersFirst(
            [missing, ...list.filter((o) => o.id !== missing.id)],
            memberOrganisationIds.value
          )
        }
      }
      organisations.value = list
    } catch {
      error.value = t('components.departmentModal.loadDataError')
    }
  }
})

watch(
  () => props.initialFocus,
  () => {
    if (props.isOpen) applyInitialFocus()
  },
)

async function handleSubmit() {
  if (!formData.value.name || !formData.value.organisationId) {
    error.value = t('components.departmentModal.validationFillAll')
    return
  }

  try {
    isSubmitting.value = true
    error.value = null

    if (isEdit.value && props.department) {
      await updateDepartment(props.department.id, {
        name: formData.value.name,
        organisation_id: formData.value.organisationId,
        parent_id: formData.value.parentId || null
      })
    } else {
      await createDepartment({
        name: formData.value.name,
        organisation_id: formData.value.organisationId,
        parent_id: formData.value.parentId || null
      })
    }

    emit('saved')
    close()
  } catch (err: any) {
    const msg = err.response?.data?.error || t('components.departmentModal.saveErrorFallback')
    error.value = msg
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  emit('close')
  formData.value = { name: '', organisationId: '', parentId: null }
  error.value = null
}
</script>

<style scoped>
:deep(.department-modal-card) {
  max-height: calc(100vh - 48px);
}

.department-modal-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dept-modal-accordion {
  margin: 0;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
  overflow: hidden;
}

.dept-modal-accordion__summary {
  cursor: pointer;
  list-style: none;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

.dept-modal-accordion__summary::-webkit-details-marker {
  display: none;
}

.dept-modal-accordion__summary::after {
  content: '▾';
  margin-left: auto;
  color: #94a3b8;
  transition: transform 0.15s ease;
}

.dept-modal-accordion[open] > .dept-modal-accordion__summary::after {
  transform: rotate(-180deg);
}

.dept-modal-accordion__body {
  padding: 12px 14px 14px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
  margin-bottom: 0;
}

.tree-select-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  overflow: hidden;
}

.tree-select-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: white;
  border-bottom: 1px solid #e5e7eb;
  font-size: 14px;
  color: #374151;
}

.btn-clear-parent {
  padding: 6px 12px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 12px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-clear-parent:hover {
  background: #e5e7eb;
  color: #374151;
}

.btn-clear-parent.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.tree-select-content {
  max-height: 300px;
  overflow-y: auto;
  padding: 8px;
}

.tree-select-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  margin: 2px 0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  color: #374151;
}

.tree-select-item:hover {
  background: #e5e7eb;
}

.tree-select-item.selected {
  background: #dbeafe;
  color: #1e40af;
  font-weight: 500;
}

.tree-select-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tree-select-item.disabled:hover {
  background: transparent;
}

.tree-select-item .folder-icon {
  color: #6b7280;
  flex-shrink: 0;
}

.tree-select-item.selected .folder-icon {
  color: #3b82f6;
}
</style>
