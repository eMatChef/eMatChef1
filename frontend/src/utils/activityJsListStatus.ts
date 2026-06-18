export type JsListPhase = 'draft' | 'coach' | 'return'

export interface ActivityListJsInput {
  type: string
  wantsJsMaterial?: boolean
}

export function activityHasJsMaterial(item: ActivityListJsInput): boolean {
  return (
    item.wantsJsMaterial === true && (item.type === 'camp' || item.type === 'event')
  )
}
