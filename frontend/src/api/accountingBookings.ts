import apiClient from '@/api/apiClient'

export type AccountingBooking = {
  id: string
  department_id: string
  cost_center_id: string
  cost_center_name: string
  group_id: string | null
  group_name: string | null
  amount: string
  booked_at: string
  entry_type: string
  payment_method: string | null
  receipt_label: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export type BookingListParams = {
  year?: string
  cost_center_id?: string
}

export async function listBookings(
  departmentId: string,
  params?: BookingListParams
): Promise<AccountingBooking[]> {
  const { data } = await apiClient.get<AccountingBooking[]>(
    `/api/departments/${departmentId}/accounting/bookings`,
    { params: params || {} }
  )
  return data
}

/** Kalenderjahre mit mindestens einer Buchung in diesem Department (absteigend). */
export async function getBookingYears(departmentId: string): Promise<number[]> {
  const { data } = await apiClient.get<{ years: number[] }>(
    `/api/departments/${departmentId}/accounting/bookings/years`
  )
  return data.years
}

export type BookingCreateBody = {
  amount: string | number
  booked_at: string
  cost_center_id: string
  entry_type: string
  payment_method?: string | null
  group_id?: string | null
  receipt_label?: string | null
  notes?: string | null
  /** Verknüpft die Buchung mit einem offenen Anschaffungs-Auftrag (pending → recorded). */
  acquisition_follow_up_id?: string | null
}

export async function createBooking(
  departmentId: string,
  body: BookingCreateBody
): Promise<AccountingBooking> {
  const { data } = await apiClient.post<AccountingBooking>(
    `/api/departments/${departmentId}/accounting/bookings`,
    body
  )
  return data
}

export async function updateBooking(
  departmentId: string,
  id: string,
  body: Partial<BookingCreateBody>
): Promise<AccountingBooking> {
  const { data } = await apiClient.patch<AccountingBooking>(
    `/api/departments/${departmentId}/accounting/bookings/${id}`,
    body
  )
  return data
}

export async function deleteBooking(departmentId: string, id: string): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/accounting/bookings/${id}`)
}
