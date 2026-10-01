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

  it('stays planned inside the window until the event itself', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-11-01')).toBe('planned')
  })

  it('is in use during the event period', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-11-01', [
      {
        label: 'grossanlass',
        start_date: '2026-11-01',
        end_date: '2026-11-02',
      } as never,
    ])).toBe('use')
  })

  it('is done after the window', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-11-14')).toBe('done')
  })
})

describe('resolveBuildStatus', () => {
  it('keeps a status set by hand', () => {
    expect(resolveBuildStatus({
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

  it('raises Geplant to Aufbau only when material is on site or work is in progress', () => {
    expect(resolveBuildStatus({
      procurement_progress: 'build',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-09-21')).toBe('build')
  })

  it('shows Geplant / Offerten when material was only quoted or ordered', () => {
    expect(resolveBuildStatus({
      procurement_progress: 'quoted',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-11-01')).toBe('quoted')
  })

  it('keeps a later date status ahead of procurement', () => {
    expect(resolveBuildStatus({
      procurement_progress: 'build',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-11-20')).toBe('done')
  })

  it('stays planned during the Aufbau dates until work or material is on site', () => {
    expect(deriveBuildStatus('2026-10-28', '2026-11-13', '2026-10-29', [
      {
        label: 'aufbau',
        start_date: '2026-10-28',
        end_date: '2026-10-30',
      } as never,
    ])).toBe('planned')
  })

  it('stays planned inside the window when no calendar period is active', () => {
    expect(resolveBuildStatus({
      build_status: '',
      window_start: '2026-10-28',
      window_end: '2026-11-13',
    }, '2026-11-01')).toBe('planned')
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
      'quoted',
      'build',
      'use',
      'teardown',
      'done',
      'aborted',
    ])
  })
})
