import type { AvailableUser } from '@/api/departments'
import { parseSearchTokens, textMatchesAllTokens } from '@/utils/searchHighlight'

export function availableUserHaystack(user: AvailableUser): string {
  return [
    user.name,
    user.first_name,
    user.last_name,
    user.nickname,
    user.email,
    user.departments_label,
    user.primary_department_name,
  ]
    .filter((part) => part != null && String(part).trim() !== '')
    .join(' ')
}

export function availableUserMatchesQuery(user: AvailableUser, query: string): boolean {
  return textMatchesAllTokens(availableUserHaystack(user), query)
}

export function filterAvailableUsersByQuery(users: AvailableUser[], query: string): AvailableUser[] {
  const trimmed = query.trim()
  if (trimmed.length < 3) return []
  const tokens = parseSearchTokens(trimmed)
  if (tokens.length === 0) return []
  return users.filter((user) => availableUserMatchesQuery(user, trimmed))
}
