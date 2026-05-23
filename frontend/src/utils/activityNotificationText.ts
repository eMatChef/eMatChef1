import type { ActivityMwNotification } from '@/api/activityNotifications'

export type ActivityNotificationTextLabels = {
  activityType: (type: string) => string
  actionForType: (type: string) => string
  bellTitle: (creatorName: string) => string
}

function formatActivityNo(no: number | null | undefined): string | null {
  if (no == null) return null
  return `#${String(no).padStart(3, '0')}`
}

/** Betreffzeile in der Nachrichtenzentrale: Aktivitätsname. */
export function formatActivityMwInboxSubject(entry: ActivityMwNotification): string {
  const name = entry.activity_name?.trim() || '–'
  return `«${name}»`
}

/** Vorschau: Aktion · Typ · Gruppe · Nr. */
export function formatActivityMwInboxPreview(
  entry: ActivityMwNotification,
  labels: ActivityNotificationTextLabels,
): string {
  const parts: string[] = []
  const action = labels.actionForType(entry.type || 'activity_submitted')
  if (action) parts.push(action)
  if (entry.type === 'activity_issue_reported' && entry.material_name?.trim()) {
    parts.push(entry.material_name.trim())
  }
  const typeLabel = labels.activityType(entry.activity_type || 'activity')
  if (typeLabel) parts.push(typeLabel)
  if (entry.group_name?.trim()) parts.push(entry.group_name.trim())
  const noLabel = formatActivityNo(entry.activity_no)
  if (noLabel) parts.push(noLabel)
  return parts.join(' · ')
}

/** Glocke: Hauptzeile (wer hat eingereicht). */
export function formatActivityMwBellTitle(
  entry: ActivityMwNotification,
  labels: ActivityNotificationTextLabels,
): string {
  return labels.bellTitle(entry.creator_name?.trim() || '–')
}

/** Glocke: Unterzeile (Aktivität + Meta). */
export function formatActivityMwBellSubtitle(
  entry: ActivityMwNotification,
  labels: ActivityNotificationTextLabels,
): string {
  const metaParts: string[] = []
  const typeLabel = labels.activityType(entry.activity_type || 'activity')
  if (typeLabel) metaParts.push(typeLabel)
  if (entry.group_name?.trim()) metaParts.push(entry.group_name.trim())
  const noLabel = formatActivityNo(entry.activity_no)
  if (noLabel) metaParts.push(noLabel)
  const meta = metaParts.length > 0 ? ` · ${metaParts.join(' · ')}` : ''
  const activity = entry.activity_name?.trim() || '–'
  return `«${activity}»${meta}`
}
