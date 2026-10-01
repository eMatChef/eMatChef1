import { describe, expect, it } from 'vitest'
import { stripGeoSearchLabel } from './geoSearch'

describe('stripGeoSearchLabel', () => {
  it('removes HTML tags from Swisstopo labels', () => {
    expect(stripGeoSearchLabel('<b>Zürich</b> <i>Stadt</i>')).toBe('Zürich Stadt')
  })

  it('normalizes whitespace', () => {
    expect(stripGeoSearchLabel('  Bern   BE  ')).toBe('Bern BE')
  })
})
