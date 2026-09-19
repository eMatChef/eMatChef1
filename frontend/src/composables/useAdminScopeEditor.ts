import { computed, type Ref } from 'vue'
import type { Department } from '@/api/departments'
import {
  cloneAdminCapabilities,
  type AdminCapabilities,
} from '@/utils/adminCapabilities'

interface DeptScopeNode {
  id: string
  name: string
  children: DeptScopeNode[]
}

export interface FlatDeptNode {
  id: string
  name: string
  level: number
}

export interface OrgScopeNode {
  id: string
  name: string
  flatNodes: FlatDeptNode[]
}

function flattenDeptNodes(nodes: DeptScopeNode[], level = 0): FlatDeptNode[] {
  const out: FlatDeptNode[] = []
  for (const node of nodes) {
    out.push({ id: node.id, name: node.name, level })
    out.push(...flattenDeptNodes(node.children, level + 1))
  }
  return out
}

export function useAdminScopeEditor(
  capabilities: Ref<AdminCapabilities>,
  departments: Ref<Department[]>,
  organisations: Ref<Array<{ id: string; name: string }>>,
) {
  const scopeTree = computed((): OrgScopeNode[] => {
    const depts = departments.value

    function buildDeptTree(orgId: string, parentId: string | null): DeptScopeNode[] {
      return depts
        .filter((d) => d.organisation_id === orgId && (d.parent_id || null) === parentId)
        .map((d) => ({
          id: d.id,
          name: d.name,
          children: buildDeptTree(orgId, d.id),
        }))
    }

    return organisations.value.map((org) => ({
      id: org.id,
      name: org.name,
      flatNodes: flattenDeptNodes(buildDeptTree(org.id, null)),
    }))
  })

  function deptIdsInOrganisation(orgId: string): Set<string> {
    return new Set(departments.value.filter((d) => d.organisation_id === orgId).map((d) => d.id))
  }

  function getDeptAncestorIds(deptId: string): string[] {
    const ancestors: string[] = []
    let parentId = departments.value.find((d) => d.id === deptId)?.parent_id
    while (parentId) {
      ancestors.push(parentId)
      parentId = departments.value.find((d) => d.id === parentId)?.parent_id
    }
    return ancestors
  }

  function getDeptDescendantIds(deptId: string): string[] {
    const result: string[] = []
    const stack = departments.value.filter((d) => d.parent_id === deptId).map((d) => d.id)
    while (stack.length > 0) {
      const id = stack.pop()!
      result.push(id)
      for (const child of departments.value) {
        if (child.parent_id === id) stack.push(child.id)
      }
    }
    return result
  }

  function isOrgFullyScoped(orgId: string): boolean {
    return capabilities.value.scope.organisation_ids.includes(orgId)
  }

  function toggleOrganisationScope(orgId: string, checked: boolean | null) {
    const caps = cloneAdminCapabilities(capabilities.value)
    const orgIds = new Set(caps.scope.organisation_ids)
    const inOrg = deptIdsInOrganisation(orgId)

    if (checked) {
      orgIds.add(orgId)
      caps.scope.department_root_ids = caps.scope.department_root_ids.filter((id) => !inOrg.has(id))
    } else {
      orgIds.delete(orgId)
    }

    caps.scope.organisation_ids = Array.from(orgIds)
    capabilities.value = caps
  }

  function toggleDepartmentRoot(deptId: string, checked: boolean | null) {
    const caps = cloneAdminCapabilities(capabilities.value)
    const ids = new Set(caps.scope.department_root_ids)
    const dept = departments.value.find((d) => d.id === deptId)

    if (checked) {
      if (dept?.organisation_id) {
        caps.scope.organisation_ids = caps.scope.organisation_ids.filter((id) => id !== dept.organisation_id)
      }
      for (const anc of getDeptAncestorIds(deptId)) ids.delete(anc)
      for (const desc of getDeptDescendantIds(deptId)) ids.delete(desc)
      ids.add(deptId)
    } else {
      ids.delete(deptId)
    }

    caps.scope.department_root_ids = Array.from(ids)
    capabilities.value = caps
  }

  return {
    scopeTree,
    isOrgFullyScoped,
    toggleOrganisationScope,
    toggleDepartmentRoot,
  }
}
