import apiClient from './apiClient'
import type { GaDriveProofKind } from '@/views/grossanlass/grossanlassDriveCategories'

export type GrossanlassUserCardDriveDocument = {
  filename: string
  original_name: string
  url: string
}

export type GrossanlassUserCard = {
  user_id: string
  name: string
  ressort: string
  role: string
  code: string
  qr_url: string
  event_name: string
  may_drive: boolean
  drive_classes: string[]
  drive_proof_kind: GaDriveProofKind
  drive_verified: boolean
  drive_verified_at: string | null
  drive_verified_by_name: string | null
  drive_has_extra_regulation: boolean
  drive_document: GrossanlassUserCardDriveDocument | null
  profile_license?: {
    drive_classes: string[]
    valid_until: string | null
    document: GrossanlassUserCardDriveDocument | null
  } | null
  printed: boolean
  printed_at: string | null
}

export async function getGrossanlassUserCards(departmentId: string): Promise<GrossanlassUserCard[]> {
  const response = await apiClient.get<GrossanlassUserCard[]>(
    `/api/departments/${departmentId}/grossanlass/user-cards`,
  )
  return response.data
}

export async function updateGrossanlassUserCard(
  departmentId: string,
  userId: string,
  data: {
    may_drive?: boolean
    print?: boolean
    drive_classes?: string[]
    verify_in_person?: boolean
    verify_document?: boolean
    revoke_verification?: boolean
  },
): Promise<GrossanlassUserCard> {
  const response = await apiClient.patch<GrossanlassUserCard>(
    `/api/departments/${departmentId}/grossanlass/user-cards/${userId}`,
    data,
  )
  return response.data
}

export async function uploadGrossanlassUserCardDriveProof(
  departmentId: string,
  userId: string,
  file: File,
): Promise<GrossanlassUserCard> {
  const formData = new FormData()
  formData.append('proof', file)
  const response = await apiClient.post<GrossanlassUserCard>(
    `/api/departments/${departmentId}/grossanlass/user-cards/${userId}/drive-proof`,
    formData,
    {
      transformRequest: [(data, headers) => {
        if (headers && typeof headers === 'object') {
          delete (headers as Record<string, unknown>)['Content-Type']
        }
        return data
      }],
    },
  )
  return response.data
}

export async function deleteGrossanlassUserCardDriveProof(
  departmentId: string,
  userId: string,
): Promise<GrossanlassUserCard> {
  const response = await apiClient.delete<GrossanlassUserCard>(
    `/api/departments/${departmentId}/grossanlass/user-cards/${userId}/drive-proof`,
  )
  return response.data
}

export async function printMissingGrossanlassUserCards(
  departmentId: string,
): Promise<GrossanlassUserCard[]> {
  const response = await apiClient.post<GrossanlassUserCard[]>(
    `/api/departments/${departmentId}/grossanlass/user-cards/print-missing`,
  )
  return response.data
}
