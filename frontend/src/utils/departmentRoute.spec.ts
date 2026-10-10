// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { pathAfterDepartmentSwitch } from './departmentRoute'

const OLD = 'aaaaaaaaaaaa'
const NEW = 'bbbbbbbbbbbb'

const known = (path: string) => {
  const staticPaths = [`/${NEW}/dept/settings`, `/${NEW}/ga/planung/transporte`]
  if (staticPaths.includes(path)) return { matched: [{}], name: 'x', params: { departmentId: NEW } }
  if (path.startsWith(`/${NEW}/dept/activities/`)) {
    return { matched: [{}], name: 'ActivityDetail', params: { departmentId: NEW, activityId: 'a1' } }
  }
  return { matched: [], name: undefined, params: {} }
}
const same = { oldIsGrossanlass: false, newIsGrossanlass: false, resolve: known }

describe('pathAfterDepartmentSwitch', () => {
  it('keeps a valid sub path within the same department type and carries only tab', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/settings`, { tab: 'x', ticket: '9' }, OLD, NEW, same)).toBe(
      `/${NEW}/dept/settings?tab=x`,
    )
    expect(
      pathAfterDepartmentSwitch(`/${OLD}/ga/planung/transporte`, {}, OLD, NEW, { ...same, oldIsGrossanlass: true, newIsGrossanlass: true }),
    ).toBe(`/${NEW}/ga/planung/transporte`)
  })

  it('opens the dashboard when the type changes', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/ga/planung/transporte`, {}, OLD, NEW, { ...same, oldIsGrossanlass: true })).toBe(
      `/${NEW}/dept/dashboard`,
    )
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/settings`, {}, OLD, NEW, { ...same, newIsGrossanlass: true })).toBe(
      `/${NEW}/ga/dashboard`,
    )
  })

  it('opens the dashboard for unknown routes and routes that carry object ids', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/unknown`, {}, OLD, NEW, same)).toBe(`/${NEW}/dept/dashboard`)
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/activities/a1`, {}, OLD, NEW, same)).toBe(`/${NEW}/dept/dashboard`)
  })

  it('rejects invalid or malicious ids', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/settings`, {}, OLD, 'javascript:alert(1)', same)).toBeNull()
    expect(pathAfterDepartmentSwitch(`/${OLD}/dept/settings`, {}, '../../../evil', NEW, same)).toBeNull()
    expect(pathAfterDepartmentSwitch('/other/dept/settings', {}, OLD, NEW, same)).toBeNull()
  })
})
