import apiClient from './apiClient'

export type UserDriveLicense = {
  drive_classes: string[]
  valid_until: string | null
  document: {
    filename: string
    original_name: string
    url: string
  } | null
}

export async function getDriveLicense(profileId: string): Promise<UserDriveLicense> {
  const { data } = await apiClient.get<UserDriveLicense>(`/api/profiles/${profileId}/drive-license`)
  return data
}

export async function saveDriveLicense(
  profileId: string,
  payload: { drive_classes?: string[]; valid_until?: string | null },
): Promise<UserDriveLicense> {
  const { data } = await apiClient.put<UserDriveLicense>(
    `/api/profiles/${profileId}/drive-license`,
    payload,
  )
  return data
}

export async function uploadDriveLicenseProof(profileId: string, file: File): Promise<UserDriveLicense> {
  const formData = new FormData()
  formData.append('proof', file)
  const { data } = await apiClient.post<UserDriveLicense>(
    `/api/profiles/${profileId}/drive-license/proof`,
    formData,
    {
      transformRequest: [(body, headers) => {
        if (headers && typeof headers === 'object') {
          delete (headers as Record<string, unknown>)['Content-Type']
        }
        return body
      }],
    },
  )
  return data
}

export async function deleteDriveLicenseProof(profileId: string): Promise<UserDriveLicense> {
  const { data } = await apiClient.delete<UserDriveLicense>(
    `/api/profiles/${profileId}/drive-license/proof`,
  )
  return data
}
