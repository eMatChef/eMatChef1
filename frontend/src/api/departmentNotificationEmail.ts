import apiClient from './apiClient'

export type DepartmentNotificationEmail = {
  /** Adresse, an die Benachrichtigungen dieses Departments tatsächlich gehen */
  effective_email: string
  primary_email: string
  /** null = Hauptadresse verwenden */
  selected_email: string | null
  /** Hauptadresse und eigene verifizierte Adressen */
  options: string[]
}

const url = (departmentId: string) => `/api/departments/${departmentId}/my-notification-email`

export async function getDepartmentNotificationEmail(departmentId: string): Promise<DepartmentNotificationEmail> {
  const { data } = await apiClient.get<DepartmentNotificationEmail>(url(departmentId))
  return data
}

/** email = null stellt auf die Hauptadresse zurück. */
export async function setDepartmentNotificationEmail(
  departmentId: string,
  email: string | null,
): Promise<DepartmentNotificationEmail> {
  const { data } = await apiClient.put<DepartmentNotificationEmail>(url(departmentId), { email })
  return data
}
