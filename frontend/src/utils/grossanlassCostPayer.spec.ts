import { describe, expect, it } from 'vitest'
import { grossanlassPayerSelectItems } from '@/utils/grossanlassCostPayer'

const labels = { central: 'Logistik / zentral', potSuffix: '(Anlass-Topf)' }

describe('grossanlassPayerSelectItems', () => {
  const groups = [
    { id: 'infra', name: '07 Infrastruktur', parent_id: null, level: 0 },
    { id: 'mat', name: 'Materialien', parent_id: 'infra', level: 1 },
    { id: 'log', name: 'Logistik', parent_id: null, level: 0 },
  ]

  it('puts the event pot first and indents other payers', () => {
    const items = grossanlassPayerSelectItems(groups, 'log', labels)
    expect(items.map((row) => row.value)).toEqual(['log', 'infra', 'mat'])
    expect(items[0]).toMatchObject({
      name: 'Logistik (Anlass-Topf)',
      depth: 0,
    })
    expect(items[1]).toMatchObject({ name: '07 Infrastruktur', depth: 1 })
    expect(items[2]).toMatchObject({ name: 'Materialien', depth: 2 })
  })

  it('uses a central row when no logistics group exists', () => {
    const items = grossanlassPayerSelectItems(groups, null, labels)
    expect(items[0]).toMatchObject({ value: null, name: 'Logistik / zentral', depth: 0 })
    expect(items[1]).toMatchObject({ value: 'infra', depth: 1 })
  })
})
