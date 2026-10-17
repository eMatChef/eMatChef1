import {
  getInviteNotifications,
  getPendingDepartmentActivityInvites,
  getReceivedDepartmentInvites,
  type InviteAcceptedNotification,
  type GrossanlassMwAssignedNotification,
  type GrossanlassRoundOpenedNotification,
  type PendingDepartmentActivityInvite,
  type ReceivedDepartmentInviteNotification,
  type ReceivedUserInboxNotification,
} from '@/api/joinRequests'
import { getPublicFoundMessages, type PublicFoundItemMessage } from '@/api/publicFoundMessages'
import { getActivityMwNotifications, type ActivityMwNotification } from '@/api/activityNotifications'
import { getUserActivityStatusNotifications } from '@/api/activityUserNotifications'
import { getUserDirectMessages, type UserDirectMessage } from '@/api/inboxMessages'

/**
 * Gemeinsame Ladelogik der Nachrichtenzentrale und der Glocke (TopHeader).
 * Beide zeigen dieselben Quellen; sie unterscheiden sich nur in Bucket, Limit und den Rollen,
 * die eine Quelle sehen dürfen. Nicht erlaubte Quellen werden gar nicht erst abgefragt (die
 * Berechtigung prüft zusätzlich immer das Backend). Ein einzelner Fehler leert nur diese Quelle.
 */
export interface InboxSourceOptions {
  /** `unread` für die Glocke, `all` für die Zentrale. */
  bucket: 'unread' | 'all'
  limit: number
  /** Status-Bucket der QR-Kontakte (Glocke: nur offene). */
  qrBucket: 'open' | 'all'
  /** Quellen mit Rollenbeschränkung. */
  includeQr: boolean
  includeActivityMw: boolean
  includeInviteAccepted: boolean
  includeCampInvites: boolean
}

export interface InboxSources {
  receivedInvites: { unreadCount: number | null; items: ReceivedUserInboxNotification[] }
  /** Aus `receivedInvites` abgeleitet (Einladungen und Grossanlass-Meldungen). */
  departmentInvites: ReceivedDepartmentInviteNotification[]
  grossanlassMwAssigned: GrossanlassMwAssignedNotification[]
  grossanlassRoundOpened: GrossanlassRoundOpenedNotification[]
  userMessages: { unreadCount: number | null; items: UserDirectMessage[] }
  campInvites: PendingDepartmentActivityInvite[]
  found: { unreadCount: number | null; items: PublicFoundItemMessage[] }
  activityMw: { unreadCount: number | null; items: ActivityMwNotification[] }
  activityUser: { unreadCount: number | null; items: ActivityMwNotification[] }
  inviteAccepted: InviteAcceptedNotification[]
}

function unreadOf(value: { unread_count?: unknown }): number | null {
  return typeof value.unread_count === 'number' ? value.unread_count : null
}

export async function loadInboxSources(departmentId: string, opts: InboxSourceOptions): Promise<InboxSources> {
  const { bucket, limit } = opts

  const [received, userMsg, camp, found, activityMw, activityUser, inviteAccepted] = await Promise.all([
    getReceivedDepartmentInvites({ bucket, limit }).catch(() => ({
      count: 0,
      unread_count: 0,
      items: [] as ReceivedUserInboxNotification[],
    })),
    getUserDirectMessages(departmentId, { bucket, limit }).catch(() => ({
      unread_count: 0,
      items: [] as UserDirectMessage[],
    })),
    opts.includeCampInvites
      ? getPendingDepartmentActivityInvites(departmentId).catch(() => ({
          count: 0,
          items: [] as PendingDepartmentActivityInvite[],
        }))
      : Promise.resolve({ count: 0, items: [] as PendingDepartmentActivityInvite[] }),
    opts.includeQr
      ? getPublicFoundMessages(departmentId, { bucket: opts.qrBucket, limit }).catch(() => ({
          unread_count: 0,
          items: [] as PublicFoundItemMessage[],
        }))
      : Promise.resolve({ unread_count: 0, items: [] as PublicFoundItemMessage[] }),
    opts.includeActivityMw
      ? getActivityMwNotifications(departmentId, { bucket, limit }).catch(() => ({
          unread_count: 0,
          items: [] as ActivityMwNotification[],
        }))
      : Promise.resolve({ unread_count: 0, items: [] as ActivityMwNotification[] }),
    getUserActivityStatusNotifications(departmentId, { bucket, limit }).catch(() => ({
      unread_count: 0,
      items: [] as ActivityMwNotification[],
    })),
    opts.includeInviteAccepted
      ? getInviteNotifications(departmentId, { bucket, limit }).catch(() => [] as InviteAcceptedNotification[])
      : Promise.resolve([] as InviteAcceptedNotification[]),
  ])

  const receivedItems = received.items || []

  return {
    receivedInvites: { unreadCount: unreadOf(received), items: receivedItems },
    departmentInvites: receivedItems.filter(
      (i): i is ReceivedDepartmentInviteNotification => i.type === 'department_invite',
    ),
    grossanlassMwAssigned: receivedItems.filter(
      (i): i is GrossanlassMwAssignedNotification => i.type === 'grossanlass_mw_assigned',
    ),
    grossanlassRoundOpened: receivedItems.filter(
      (i): i is GrossanlassRoundOpenedNotification => i.type === 'grossanlass_round_opened',
    ),
    userMessages: { unreadCount: unreadOf(userMsg), items: userMsg.items || [] },
    campInvites: camp.items || [],
    found: { unreadCount: unreadOf(found), items: found.items || [] },
    activityMw: { unreadCount: unreadOf(activityMw), items: activityMw.items || [] },
    activityUser: { unreadCount: unreadOf(activityUser), items: activityUser.items || [] },
    inviteAccepted: inviteAccepted || [],
  }
}
