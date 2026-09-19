<template>
  <div class="user-global-admin-accordion">
    <details class="member-profile-accordion" open>
      <summary class="member-profile-accordion__summary">
        {{ t('settings.globalAdminAccordion.sections.role') }}
        <span v-if="readonly" class="user-global-admin-accordion__readonly-badge">
          {{ t('settings.globalAdminAccordion.readOnly') }}
        </span>
      </summary>
      <div class="member-profile-accordion__body">
        <p class="user-global-admin-accordion__subtitle">
          {{ t('settings.globalAdminAccordion.roleSectionHint') }}
        </p>
        <ESelect
          :model-value="globalAdminRole"
          :label="t('settings.globalAdminRoles.fields.globalRole')"
          :items="globalRoleSelectItems"
          :disabled="readonly"
          hide-details="auto"
          @update:model-value="onGlobalRoleChange"
        />
        <p v-if="globalAdminRole !== 'none'" class="user-global-admin-accordion__role-hint">
          {{ roleHintText }}
        </p>
      </div>
    </details>

    <details v-if="globalAdminRole !== 'none'" class="member-profile-accordion" open>
      <summary class="member-profile-accordion__summary">
        {{ t('settings.globalAdminAccordion.sections.scope') }}
      </summary>
      <div class="member-profile-accordion__body">
        <p class="user-global-admin-accordion__subtitle">
          {{ t('settings.globalAdminAccordion.scopeSectionHint') }}
        </p>
        <p v-if="readonly" class="user-global-admin-accordion__scope-summary">
          {{ scopeSummaryText }}
        </p>
        <template v-else>
          <p class="inline-hint">{{ t('settings.globalAdminRoles.scopeHint') }}</p>
          <div v-if="scopeTree.length === 0" class="inline-hint">
            {{ t('settings.globalAdminRoles.organisationScopeEmpty') }}
          </div>
          <div v-else class="scope-tree">
            <div v-for="org in scopeTree" :key="org.id" class="scope-org">
              <ECheckbox
                :model-value="localCapabilities.scope.organisation_ids.includes(org.id)"
                :label="org.name"
                hide-details
                class="scope-org-header"
                @update:model-value="toggleOrganisationScope(org.id, $event)"
              />
              <p v-if="org.flatNodes.length === 0" class="inline-hint scope-org-no-depts">
                {{ t('settings.globalAdminRoles.orgNoDepartmentsYet') }}
              </p>
              <ECheckbox
                v-for="node in org.flatNodes"
                :key="node.id"
                :model-value="localCapabilities.scope.department_root_ids.includes(node.id)"
                :label="node.name"
                :disabled="isOrgFullyScoped(org.id)"
                hide-details
                class="scope-dept-node"
                :class="{ 'scope-dept-node--org-selected': isOrgFullyScoped(org.id) }"
                :style="{ marginLeft: `${28 + node.level * 16}px` }"
                :title="isOrgFullyScoped(org.id) ? t('settings.globalAdminRoles.deptUnderOrgHint') : undefined"
                @update:model-value="toggleDepartmentRoot(node.id, $event)"
              />
            </div>
          </div>
        </template>
      </div>
    </details>

    <details
      v-for="group in capabilityGroups"
      v-show="globalAdminRole !== 'none'"
      :key="group.id"
      class="member-profile-accordion"
    >
      <summary class="member-profile-accordion__summary">
        {{ t(group.accordionTitleKey) }}
      </summary>
      <div class="member-profile-accordion__body">
        <p v-if="group.accordionHintKey" class="user-global-admin-accordion__subtitle">
          {{ t(group.accordionHintKey) }}
        </p>
        <div class="capability-group">
          <ECheckbox
            v-for="item in group.items"
            :key="item.key"
            :model-value="getCapability(item.key)"
            :label="t(item.labelKey)"
            :disabled="readonly"
            hide-details
            @update:model-value="setCapability(item.key, $event)"
          />
        </div>
      </div>
    </details>
  </div>
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Department } from '@/api/departments'
import { ECheckbox, ESelect } from '@/components/form/base'
import { useAdminScopeEditor } from '@/composables/useAdminScopeEditor'
import {
  ADMIN_CAPABILITY_GROUPS,
  cloneAdminCapabilities,
  defaultAdminCapabilities,
  formatAdminScopeSummary,
  getCapabilityValue,
  setCapabilityValue,
  type AdminCapabilities,
  type GlobalAdminRole,
} from '@/utils/adminCapabilities'

const props = withDefaults(
  defineProps<{
    globalAdminRole: GlobalAdminRole
    adminCapabilities: AdminCapabilities
    departments: Department[]
    organisations: Array<{ id: string; name: string }>
    readonly?: boolean
  }>(),
  { readonly: false },
)

const emit = defineEmits<{
  'update:globalAdminRole': [GlobalAdminRole]
  'update:adminCapabilities': [AdminCapabilities]
}>()

const { t } = useI18n()

const capabilityGroups = ADMIN_CAPABILITY_GROUPS.map((group) => ({
  ...group,
  accordionTitleKey: `settings.globalAdminAccordion.capabilitySections.${group.id}.title`,
  accordionHintKey: `settings.globalAdminAccordion.capabilitySections.${group.id}.hint`,
}))

const globalRoleSelectItems = computed(() => [
  { title: t('settings.adminUsers.globalRoles.none'), value: 'none' },
  { title: t('settings.adminUsers.globalRoles.org'), value: 'org' },
  { title: t('settings.adminUsers.globalRoles.sub'), value: 'sub' },
])

const localCapabilities = computed({
  get: () => props.adminCapabilities,
  set: (value: AdminCapabilities) => emit('update:adminCapabilities', value),
})

const departmentsRef = computed(() => props.departments)
const organisationsRef = computed(() => props.organisations)

const { scopeTree, isOrgFullyScoped, toggleOrganisationScope, toggleDepartmentRoot } = useAdminScopeEditor(
  localCapabilities,
  departmentsRef,
  organisationsRef,
)

const roleHintText = computed(() => {
  if (props.globalAdminRole === 'org') return t('settings.globalAdminAccordion.roleHintOrg')
  if (props.globalAdminRole === 'sub') return t('settings.globalAdminAccordion.roleHintSub')
  return ''
})

const scopeSummaryLabels = computed(() => ({
  all: t('settings.globalAdminRoles.scopeAll'),
  orgs: (names: string[]) => t('settings.globalAdminRoles.scopeOrgsOnly', { names: names.join(', ') }),
  depts: (names: string[]) => t('settings.globalAdminRoles.scopeDeptsOnly', { names: names.join(', ') }),
  mixed: (orgNames: string[], deptNames: string[]) =>
    t('settings.globalAdminRoles.scopeOrgsAndDepts', {
      orgs: orgNames.join(', '),
      depts: deptNames.join(', '),
    }),
}))

const scopeSummaryText = computed(() => {
  const orgNameById = new Map(props.organisations.map((o) => [o.id, o.name]))
  const deptNameById = new Map(props.departments.map((d) => [d.id, d.name]))
  return formatAdminScopeSummary(
    props.adminCapabilities.scope,
    orgNameById,
    deptNameById,
    scopeSummaryLabels.value,
  )
})

function onGlobalRoleChange(value: GlobalAdminRole | null) {
  const role = (value || 'none') as GlobalAdminRole
  emit('update:globalAdminRole', role)
  emit('update:adminCapabilities', cloneAdminCapabilities(defaultAdminCapabilities(role)))
}

function getCapability(dotKey: string): boolean {
  return getCapabilityValue(props.adminCapabilities, dotKey)
}

function setCapability(dotKey: string, value: boolean | null) {
  if (props.readonly) return
  emit('update:adminCapabilities', setCapabilityValue(props.adminCapabilities, dotKey, !!value))
}
</script>

<style src="@/styles/components/department-member-detail.css"></style>
<style src="@/styles/components/user-global-admin-accordion.css"></style>
