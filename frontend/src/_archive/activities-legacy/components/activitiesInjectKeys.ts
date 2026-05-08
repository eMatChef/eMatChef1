import type { InjectionKey } from 'vue'

/** Kontext für ActivitiesDetailView (provide/inject aus ActivitiesView) */
export const ACTIVITIES_DETAIL_INJECT = Symbol('activitiesDetail') as InjectionKey<Record<string, unknown>>
