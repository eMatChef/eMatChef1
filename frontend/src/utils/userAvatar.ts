export interface UserAvatarFields {
  name?: string | null
  first_name?: string | null
  last_name?: string | null
  nickname?: string | null
  avatar_initials?: string | null
  background_color?: string | null
  text_color?: string | null
}

export function normalizeHexColor(value: string | null | undefined, fallback: string): string {
  const normalized = String(value ?? '').trim().toUpperCase()
  if (/^#[0-9A-F]{6}$/.test(normalized)) {
    return normalized
  }
  return fallback
}

export function buildAvatarInitials(
  explicitInitials: string | null | undefined,
  nickname: string | null | undefined,
  firstName: string | null | undefined,
  lastName: string | null | undefined
): string {
  const explicit = String(explicitInitials ?? '').trim()
  if (explicit.length > 0) {
    return explicit.slice(0, 2).toUpperCase()
  }
  const nick = String(nickname ?? '').trim()
  if (nick.length > 0) {
    return nick.replace(/\s+/g, '').slice(0, 2).toUpperCase()
  }
  const first = String(firstName ?? '').trim().charAt(0)
  const last = String(lastName ?? '').trim().charAt(0)
  return (first + last).toUpperCase() || '??'
}

export function getUserAvatarStyle(user: UserAvatarFields): { backgroundColor: string; color: string } {
  return {
    backgroundColor: normalizeHexColor(user.background_color, '#EC4899'),
    color: normalizeHexColor(user.text_color, '#FFFFFF'),
  }
}

export function getUserAvatarInitials(user: UserAvatarFields): string {
  return buildAvatarInitials(
    user.avatar_initials,
    user.nickname,
    user.first_name,
    user.last_name
  )
}

export type MemberHoverPart = { label: string; value: string }

export type MemberHoverTooltip = {
  line1?: MemberHoverPart
  line2?: MemberHoverPart
}

/** Zeile 1: «Name: Nachname Vorname»; Zeile 2: «Spitzname: …» (falls vorhanden). */
export function getMemberHoverTooltip(
  member: UserAvatarFields,
  labels: { name: string; nickname: string }
): MemberHoverTooltip {
  const lastName = String(member.last_name ?? '').trim()
  const firstName = String(member.first_name ?? '').trim()
  const nickname = String(member.nickname ?? '').trim()
  const displayName = String(member.name ?? '').trim()

  const fullName = [lastName, firstName].filter(Boolean).join(' ')
    || (displayName && displayName !== nickname ? displayName : '')
    || displayName

  const line1 = fullName ? { label: labels.name, value: fullName } : undefined
  const line2 =
    nickname && nickname !== fullName ? { label: labels.nickname, value: nickname } : undefined

  return { line1, line2 }
}

/** Anzeige «Spitzname Vorname Nachname» — fehlende Teile werden weggelassen. */
export function formatUserNicknameFirstNameLastName(user: UserAvatarFields): string {
  const nickname = String(user.nickname ?? '').trim()
  const firstName = String(user.first_name ?? '').trim()
  const lastName = String(user.last_name ?? '').trim()
  const parts = [nickname, firstName, lastName].filter(Boolean)
  if (parts.length > 0) return parts.join(' ')
  return String(user.name ?? '').trim()
}
