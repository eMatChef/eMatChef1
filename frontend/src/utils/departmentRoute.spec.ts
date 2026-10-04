// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { assignPathAfterDepartmentSwitch, pathAfterDepartmentSwitch } from './departmentRoute'

const OLD = 'aaaaaaaaaaaa'
const NEW = 'bbbbbbbbbbbb'

describe('pathAfterDepartmentSwitch', () => {
  it('replaces the department segment when ids are valid', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/settings/join`, OLD, NEW)).toBe(`/${NEW}/settings/join`)
  })

  it('rejects invalid or malicious ids', () => {
    expect(pathAfterDepartmentSwitch(`/${OLD}/settings`, OLD, 'javascript:alert(1)')).toBeNull()
    expect(pathAfterDepartmentSwitch(`/${OLD}/settings`, '../../../evil', NEW)).toBeNull()
    expect(pathAfterDepartmentSwitch('/other/settings', OLD, NEW)).toBeNull()
  })
})

describe('assignPathAfterDepartmentSwitch', () => {
  beforeEach(() => {
    vi.stubGlobal('location', { assign: vi.fn(), reload: vi.fn() })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('assigns a validated path', () => {
    assignPathAfterDepartmentSwitch(`/${OLD}/settings`, OLD, NEW)
    expect(window.location.assign).toHaveBeenCalledWith(`/${NEW}/settings`)
  })
})
