import apiClient from './apiClient'

export interface DepartmentPathSegment {
  type: 'organisation' | 'department'
  name: string
  id?: string
  current?: boolean
}

export interface PublicDepartmentSearchResult {
  id: string
  name: string
  organisation_id: string
  organisation_name: string
  parent_id?: string | null
  breadcrumb?: DepartmentPathSegment[]
}

export interface PublicParentDepartment {
  id: string
  name: string
  has_children: boolean
}

export interface PublicDepartmentSearchGrouped {
  in_organisation: PublicDepartmentSearchResult[]
  other_organisations: PublicDepartmentSearchResult[]
}

export async function searchPublicDepartments(orgId: string, query: string): Promise<PublicDepartmentSearchResult[]> {
  const { data } = await apiClient.get<PublicDepartmentSearchResult[]>(
    `/api/public/organisations/${orgId}/departments/search`,
    { params: { q: query } }
  )
  return data
}

/** Suche in gewaehlter Organisation + aehnliche Treffer in anderen Organisationen. */
export async function searchPublicDepartmentsGlobal(
  query: string,
  preferredOrganisationId: string,
): Promise<PublicDepartmentSearchGrouped> {
  const { data } = await apiClient.get<PublicDepartmentSearchGrouped>('/api/public/departments/search', {
    params: { q: query, preferred_organisation_id: preferredOrganisationId },
  })
  return data
}

export async function getPublicParentDepartments(orgId: string): Promise<PublicParentDepartment[]> {
  const { data } = await apiClient.get<PublicParentDepartment[]>(
    `/api/public/organisations/${orgId}/departments/parents`
  )
  return data
}

export async function getPublicDepartmentBreadcrumb(
  orgId: string,
  departmentId: string,
): Promise<DepartmentPathSegment[]> {
  const { data } = await apiClient.get<{ segments: DepartmentPathSegment[] }>(
    `/api/public/organisations/${orgId}/departments/${departmentId}/breadcrumb`,
  )
  return data.segments
}
