import type { ActivityPackItem } from '@/api/activityPackItems'

export function packRackLabel(pi: ActivityPackItem): string {
  return pi.storageRackName?.trim() || ''
}
