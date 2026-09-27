import { describe, expect, it } from 'vitest'
import {
  deriveBuildStatus,
  gaBuildStatusSelectItems,
  resolveBuildStatus,
  showsGaBuildStatus,
} from '@/utils/grossanlassBuildStatus'

describe('deriveBuildStatus', () => {
  it('is planned without a window', () => {
    expect(deriveBuildStatus(null, null, '2026-09-21')).toBe('planned')
  })

  it('is planned before the window', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-09-21')).toBe('planned')
  })

  it('is in use during the window', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-11-01')).toBe('use')
  })

  it('is done after the window', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-11-14')).toBe('done')
  })
})

describe('resolveBuildStatus', () => {
  it('follows the window even when a stored status exists', () => {
    expect(resolveBuildStatus({
      build_status: 'build',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-09-21')).toBe('planned')
  })

  it('uses a status reported on the Bauauftrag', () => {
    expect(resolveBuildStatus({
      node_type: 'bauprojekt',
      build_status: 'build',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-09-21')).toBe('build')
  })

  it('uses the earliest reported Bauauftrag under a Bereich', () => {
    expect(resolveBuildStatus({
      node_type: 'unterressort',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-11-01', ['done', 'build'])).toBe('build')
  })

  it('uses Aufbau from the fixed dates inside the window', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-10-29', [
      {
        label: 'aufbau',
        start_date: '2026-10-28',
        end_date: '2026-10-30',
      } as never,
    ])).toBe('build')
  })

  it('falls back to the window when empty', () => {
    expect(resolveBuildStatus({
      build_status: '',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-11-01')).toBe('use')
  })
})

describe('showsGaBuildStatus', () => {
  it('is only for Bereich and Bauprojekt', () => {
    expect(showsGaBuildStatus({ node_type: 'ressort' })).toBe(false)
    expect(showsGaBuildStatus({ node_type: 'unterressort' })).toBe(true)
    expect(showsGaBuildStatus({ node_type: 'bauprojekt' })).toBe(true)
  })
})

describe('gaBuildStatusSelectItems', () => {
  it('starts with automatic then the six statuses', () => {
    const items = gaBuildStatusSelectItems((key) => key.split('.').pop() || key)
    expect(items[0]).toEqual({ title: 'auto', value: '' })
    expect(items.map((item) => item.value)).toEqual([
      '',
      'planned',
      'build',
      'use',
      'teardown',
      'done',
      'aborted',
    ])
  })
})
