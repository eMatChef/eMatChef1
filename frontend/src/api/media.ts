/** Einheitliches Foto-JSON (API, snake_case) — siehe docs/media/README.md */
import type { AxiosResponse } from 'axios'
import apiClient from '@/api/apiClient'

export interface MediaPhoto {
  id?: string
  filename?: string
  url: string
  uploaded_at?: string
  uploaded_by_id?: string
  uploaded_by_name?: string
  original_filename?: string
  context?: string
  context_id?: string
  department_id?: string
  bytes?: number
  width?: number
  height?: number
  mime?: string
  legacy?: boolean
}

export const MAX_ISSUE_PHOTOS = 3

export const IMAGE_UPLOAD_ACCEPT = 'image/jpeg,image/png,image/webp,image/gif'

/** Belege: Bilder + PDF (Buchhaltung) */
export const RECEIPT_UPLOAD_ACCEPT = `${IMAGE_UPLOAD_ACCEPT},application/pdf`

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024
export const MAX_RECEIPT_BYTES = MAX_IMAGE_BYTES
export const MAX_RECEIPTS_PER_BOOKING = 5

export function mediaPhotoKey(photo: MediaPhoto, index: number): string {
  return photo.id || photo.filename || photo.url || String(index)
}

export function normalizeMediaPhotos(
  photos?: MediaPhoto[] | null,
  legacyUrl?: string | null,
): MediaPhoto[] {
  if (photos?.length) return photos
  if (legacyUrl) return [{ url: legacyUrl, legacy: true }]
  return []
}

export function filterMediaPhotos(photos: unknown): MediaPhoto[] {
  if (!Array.isArray(photos)) return []
  return photos.filter(
    (p): p is MediaPhoto => typeof p === 'object' && p !== null && 'url' in p && typeof p.url === 'string',
  )
}

export interface MediaUploadOptions {
  fieldName?: string
}

export function extractMediaUploadError(err: unknown): string {
  const e = err as { response?: { data?: { error?: string; message?: string } }; message?: string }
  return e?.response?.data?.error || e?.response?.data?.message || e?.message || ''
}

export function validateImageFile(file: File, maxBytes = MAX_IMAGE_BYTES): 'tooLarge' | null {
  if (file.size > maxBytes) {
    return 'tooLarge'
  }
  return null
}

export function isPdfMedia(photo: MediaPhoto): boolean {
  return photo.mime === 'application/pdf' || (photo.filename?.toLowerCase().endsWith('.pdf') ?? false)
}

export function validateReceiptFile(file: File, maxBytes = MAX_RECEIPT_BYTES): 'tooLarge' | 'invalidType' | null {
  if (file.size > maxBytes) {
    return 'tooLarge'
  }
  const allowed = RECEIPT_UPLOAD_ACCEPT.split(',')
  if (!allowed.includes(file.type)) {
    return 'invalidType'
  }
  return null
}

/** FormData-Upload ohne JSON-Content-Type (Browser setzt multipart boundary). */
export async function uploadMediaFile<T = unknown>(
  uploadUrl: string,
  file: File,
  options?: MediaUploadOptions,
): Promise<AxiosResponse<T>> {
  const formData = new FormData()
  formData.append(options?.fieldName ?? 'photo', file)

  return apiClient.post<T>(uploadUrl, formData, {
    transformRequest: [(data, headers) => {
      if (headers && typeof headers === 'object') {
        delete (headers as Record<string, unknown>)['Content-Type']
      }
      return data
    }],
  })
}
