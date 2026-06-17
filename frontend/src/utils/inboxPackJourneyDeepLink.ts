import type { ActivityMwNotification } from '@/api/activityNotifications'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  defaultJourneyStepForStatus,
  isValidJourneyStep,
} from '@/components/activities/materialJourneySteps'
import { packWorkflowProfileForActivityType } from '@/components/activities/packWorkflowProfile'
import type { RouteLocationRaw } from 'vue-router'

const PACK_JOURNEY_NOTIFICATION_TYPES = new Set([
  'activity_submitted',
  'activity_packed',
  'activity_at_event',
  'activity_returned',
  'activity_returned_mw',
  'activity_pack_crate_check_incomplete',
])

export function shouldDeepLinkToPackJourney(entry: ActivityMwNotification): boolean {
  return PACK_JOURNEY_NOTIFICATION_TYPES.has(entry.type || '')
}

export function journeyStepForInboxNotification(
  entry: ActivityMwNotification,
  canManageMaterials: boolean,
): JourneyStep | null {
  const rawStep = entry.journey_step
  if (typeof rawStep === 'string' && isValidJourneyStep(rawStep, profileForEntry(entry))) {
    return rawStep
  }

  const profile = profileForEntry(entry)
  const type = entry.type || ''
  const status = entry.activity_status ?? ''

  if (type === 'activity_submitted') return 'pack'
  if (type === 'activity_packed') {
    return defaultJourneyStepForStatus('packed', profile, canManageMaterials)
  }
  if (type === 'activity_at_event') {
    return defaultJourneyStepForStatus('at_event', profile, canManageMaterials)
  }
  if (type === 'activity_returned' || type === 'activity_returned_mw') {
    return defaultJourneyStepForStatus('returned', profile, canManageMaterials)
  }
  if (type === 'activity_pack_crate_check_incomplete') {
    if (status === 'at_event') {
      return defaultJourneyStepForStatus('at_event', profile, canManageMaterials)
    }
    if (status === 'returned') return 'return'
    return 'pack'
  }

  return null
}

function profileForEntry(entry: ActivityMwNotification) {
  return packWorkflowProfileForActivityType(entry.activity_type || 'activity')
}

export function routeForInboxActivityNotification(
  departmentId: string,
  entry: ActivityMwNotification,
  options: { canManageMaterials: boolean },
): RouteLocationRaw {
  if (!entry.activity_id || String(entry.activity_id).startsWith('demo-')) {
    return { path: `/${departmentId}/activities` }
  }

  if (!shouldDeepLinkToPackJourney(entry)) {
    return {
      name: 'ActivityDetail',
      params: { departmentId, activityId: entry.activity_id },
    }
  }

  const step = journeyStepForInboxNotification(entry, options.canManageMaterials)
  if (!step) {
    return {
      name: 'ActivityDetail',
      params: { departmentId, activityId: entry.activity_id },
    }
  }

  return {
    name: 'ActivityPackJourney',
    params: {
      departmentId,
      activityId: entry.activity_id,
      step,
    },
  }
}
