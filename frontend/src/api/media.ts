/** Einheitliches Foto-JSON (API, snake_case) — siehe docs/media/README.md */
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

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024
