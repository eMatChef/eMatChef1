import type { ActivityMwNotification } from '@/api/activityNotifications'
import type {
  PendingDepartmentActivityInvite,
  ReceivedDepartmentInviteNotification,
} from '@/api/joinRequests'
import type { UserDirectMessage } from '@/api/inboxMessages'
import type { PublicFoundItemMessage } from '@/api/publicFoundMessages'
import { buildAvatarInitials, type UserAvatarFields } from '@/utils/userAvatar'

/** Art der Quelle in Posteingang / Glocke */
export type NotificationSenderKind = 'user' | 'system' | 'task' | 'department'

export type NotificationSystemVariant = 'default' | 'activity'
export type NotificationTaskVariant = 'qr_contact'

export interface NotificationSenderDescriptor {
  kind: NotificationSenderKind
  /** Hauptzeile („Von“ / Quelle) */
  label: string
  /** Zusatz (z. B. einreichende Person, Finder) */
  sublabel?: string | null
  /** Profil-Avatar bei user; optional Akteur bei system (kleines Overlay) */
  user?: UserAvatarFields | null
  systemVariant?: NotificationSystemVariant
  taskVariant?: NotificationTaskVariant
}

export interface NotificationSenderLabels {
  systemDefault: string
  systemActivity: string
  taskQrSource: string
  externalSenderFallback: string
}

/** Anzeige in der „Von“-Zeile */
export function getSenderPrimaryLine(sender: NotificationSenderDescriptor): string {
  const sub = String(sender.sublabel ?? '').trim()
  if (sub && (sender.kind === 'system' || sender.kind === 'task')) {
    return `${sender.label} · ${sub}`
  }
  return sender.label
}

export function userFieldsFromActivityMw(entry: ActivityMwNotification): UserAvatarFields {
  return {
    name: entry.creator_name,
    first_name: entry.creator_first_name,
    last_name: entry.creator_last_name,
    nickname: entry.creator_nickname,
    avatar_initials: entry.creator_avatar_initials,
    background_color: entry.creator_background_color,
    text_color: entry.creator_text_color,
  }
}

export function userFieldsFromDepartmentInvite(
  inv: ReceivedDepartmentInviteNotification,
): UserAvatarFields {
  return {
    name: inv.invited_by_name,
    first_name: inv.invited_by_first_name,
    last_name: inv.invited_by_last_name,
    nickname: inv.invited_by_nickname,
    avatar_initials: inv.invited_by_avatar_initials,
    background_color: inv.invited_by_background_color,
    text_color: inv.invited_by_text_color,
  }
}

export function userFieldsFromDepartmentName(departmentName: string): UserAvatarFields {
  const name = departmentName || '?'
  const parts = name.split(/\s+/).filter(Boolean)
  const first = parts[0] ?? ''
  const last = parts.length > 1 ? parts[parts.length - 1] : ''
  return {
    name,
    first_name: first,
    last_name: last,
    avatar_initials: buildAvatarInitials(null, null, first, last),
    background_color: '#0D9488',
    text_color: '#FFFFFF',
  }
}

export function senderFromActivityMw(
  entry: ActivityMwNotification,
  labels: NotificationSenderLabels,
): NotificationSenderDescriptor {
  const creator = String(entry.creator_name ?? '').trim()
  return {
    kind: 'system',
    systemVariant: 'activity',
    label: labels.systemActivity,
    sublabel: creator || null,
    user: creator ? userFieldsFromActivityMw(entry) : null,
  }
}

export function senderFromDepartmentInvite(
  inv: ReceivedDepartmentInviteNotification,
): NotificationSenderDescriptor {
  const name = String(inv.invited_by_name ?? '').trim()
  return {
    kind: 'user',
    label: name || '?',
    user: userFieldsFromDepartmentInvite(inv),
  }
}

export function senderFromPublicFound(
  msg: PublicFoundItemMessage,
  labels: NotificationSenderLabels,
): NotificationSenderDescriptor {
  const finder = String(msg.sender_name ?? '').trim() || String(msg.sender_email ?? '').trim()
  return {
    kind: 'task',
    taskVariant: 'qr_contact',
    label: msg.material_name || labels.taskQrSource,
    sublabel: finder || null,
  }
}

export function senderFromActivityInvite(
  invite: PendingDepartmentActivityInvite,
): NotificationSenderDescriptor {
  return {
    kind: 'department',
    label: invite.source_department_name || '?',
    user: userFieldsFromDepartmentName(invite.source_department_name || '?'),
  }
}

export function userFieldsFromDirectMessage(msg: UserDirectMessage) {
  return {
    name: msg.sender_name,
    first_name: msg.sender_first_name,
    last_name: msg.sender_last_name,
    nickname: msg.sender_nickname,
    avatar_initials: msg.sender_avatar_initials,
    background_color: msg.sender_background_color,
    text_color: msg.sender_text_color,
  }
}

export function senderFromUserMessage(msg: UserDirectMessage): NotificationSenderDescriptor {
  const name = String(msg.sender_name ?? '').trim()
  return {
    kind: 'user',
    label: name || '?',
    user: userFieldsFromDirectMessage(msg),
  }
}
