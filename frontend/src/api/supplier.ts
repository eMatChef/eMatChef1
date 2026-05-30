export type SupplierCompanyStatus = 'pending' | 'active' | 'suspended'

export type SupplierMembershipRole = 'admin' | 'member'

export interface SupplierCompanySession {
  id: string
  name: string
  role: SupplierMembershipRole
  status: SupplierCompanyStatus
  capabilities: string[]
  is_primary: boolean
}

export function isActiveSupplierCompany(company: SupplierCompanySession): boolean {
  return company.status === 'active'
}
