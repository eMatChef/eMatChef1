/** Marker in address.additional_info — verknüpft Benutzeradresse mit Profil. */
export const USER_ADDRESS_TYPE = 'user'
export const PROFILE_ADDRESS_MARKER_PREFIX = '__emc_profile__:'

export function profileAddressMarker(profileId: string): string {
  return `${PROFILE_ADDRESS_MARKER_PREFIX}${profileId}`
}

export function isProfileAddressMarker(additionalInfo: string | null | undefined, profileId: string): boolean {
  if (!additionalInfo || !profileId) return false
  return additionalInfo.trim() === profileAddressMarker(profileId)
}

export function findAddressForProfile<T extends { type: string; additional_info?: string | null }>(
  addresses: T[],
  profileId: string
): T | undefined {
  return addresses.find(
    (a) => a.type === USER_ADDRESS_TYPE && isProfileAddressMarker(a.additional_info, profileId)
  )
}
