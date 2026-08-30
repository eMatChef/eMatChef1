import { describe, expect, it } from 'vitest'
import {
  defaultPdfItemLabels,
  findMentionedNames,
  htmlToPlainForMatch,
  matchInquiryMailPositions,
} from '@/utils/grossanlassInquiryMailPositions'

describe('grossanlassInquiryMailPositions', () => {
  it('allows omitting a wanted item and flags extra Bedarf or packages', () => {
    const body =
      '<strong>Sanitär &amp; Wasserversorgung</strong><br>Wasserschlauch 32mm'
    const match = matchInquiryMailPositions(
      body,
      ['Wasserschlauch 32mm', 'Container'],
      ['Generator', 'Werkzeuge'],
    )
    expect(match.mentioned).toEqual(['Wasserschlauch 32mm'])
    expect(match.omitted).toEqual(['Container'])
    expect(match.unexpected).toEqual([])

    const bad = matchInquiryMailPositions(
      `${body}<p>Bitte auch Generator und Werkzeuge.</p>`,
      ['Wasserschlauch 32mm'],
      ['Container', 'Werkzeuge', 'Generator'],
    )
    expect(bad.unexpected).toEqual(['Werkzeuge', 'Generator'])
  })

  it('does not treat Containerkran as Container or Kran', () => {
    const plain = htmlToPlainForMatch('Bitte Containerkran liefern.')
    expect(findMentionedNames(plain, ['Kran', 'Container'])).toEqual([])
    expect(findMentionedNames(plain, ['Kran', 'Container', 'Containerkran'])).toEqual([
      'Containerkran',
    ])
  })

  it('does not treat Utility-Fahrzeuge as the package Fahrzeuge', () => {
    expect(findMentionedNames('Gelände- und Utility-Fahrzeuge', ['Fahrzeuge'])).toEqual([])
  })

  it('defaults the PDF to omitted items, otherwise the full allowed list', () => {
    expect(defaultPdfItemLabels(['Schlauch', 'Container'], ['Container'])).toEqual(['Container'])
    expect(defaultPdfItemLabels(['Schlauch', 'Container'], [])).toEqual(['Schlauch', 'Container'])
  })
})
