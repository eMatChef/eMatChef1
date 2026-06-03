import apiClient from '@/api/apiClient'
import type { MediaPhoto } from '@/api/media'
import { uploadMediaFile } from '@/api/media'

export type AccountingBookingSource = {
  follow_up_id: string
  source_kind: string
  activity_id: string | null
  activity_name: string | null
  material_batch_id: string | null
  workshop_ticket_id: string | null
}

export type AccountingBooking = {
  id: string
  department_id: string
  cost_center_id: string
  cost_center_name: string
  cost_center_account_code?: string | null
  /** Optional: Zuordnung für Kosten pro Material (Reparatur, manuell; Einkauf oft via Anschaffungs-Follow-up). */
  material_item_id: string | null
  material_name: string | null
  group_id: string | null
  group_name: string | null
  amount: string
  booked_at: string
  entry_type: string
  payment_method: string | null
  payment_status: string
  receipt_label: string | null
  notes: string | null
  source?: AccountingBookingSource | null
  receipts?: MediaPhoto[]
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
  payment_status?: string | null
  group_id?: string | null
  receipt_label?: string | null
  notes?: string | null
  material_item_id?: string | null
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

/** CSV-Export der Buchungen für ein Kalenderjahr (Finanztool-Abgleich). */
export async function exportBookingsCsv(departmentId: string, year: number): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(
    `/api/departments/${departmentId}/accounting/bookings/export`,
    {
      params: { year: String(year) },
      responseType: 'blob',
    },
  )
  return data
}

export async function uploadBookingReceipt(
  departmentId: string,
  bookingId: string,
  file: File,
): Promise<MediaPhoto[]> {
  const { data } = await uploadMediaFile<{ receipts: MediaPhoto[] }>(
    `/api/departments/${departmentId}/accounting/bookings/${bookingId}/receipts`,
    file,
    { fieldName: 'receipt' },
  )
  return data.receipts
}

export async function deleteBookingReceipt(
  departmentId: string,
  bookingId: string,
  filename: string,
): Promise<MediaPhoto[]> {
  const { data } = await apiClient.delete<{ receipts: MediaPhoto[] }>(
    `/api/departments/${departmentId}/accounting/bookings/${bookingId}/receipts/${encodeURIComponent(filename)}`,
  )
  return data.receipts
}
