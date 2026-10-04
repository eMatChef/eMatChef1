// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { htmlToPlainText } from './sanitizeHtml'

describe('htmlToPlainText', () => {
  it('strips tags in compact layout', () => {
    expect(htmlToPlainText('<b>Zürich</b> <i>Stadt</i>', 'compact')).toBe('Zürich Stadt')
  })

  it('normalizes whitespace in compact layout', () => {
    expect(htmlToPlainText('  Bern   BE  ', 'compact')).toBe('Bern BE')
  })

  it('converts mail HTML to plain text with line breaks', () => {
    expect(htmlToPlainText('<p>Hallo</p><p>Welt</p>', 'mail')).toBe('Hallo\n\nWelt')
    expect(htmlToPlainText('Zeile 1<br>Zeile 2', 'mail')).toBe('Zeile 1\nZeile 2')
  })

  it('decodes HTML entities in mail layout', () => {
    expect(htmlToPlainText('A&amp;B&nbsp;C', 'mail')).toBe('A&B C')
  })
})
