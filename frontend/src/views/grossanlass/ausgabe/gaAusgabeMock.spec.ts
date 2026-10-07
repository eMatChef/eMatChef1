import { beforeEach, describe, expect, it } from 'vitest'
import {
  addArticle,
  addNeededFromPlan,
  articleById,
  canConfirm,
  changeQty,
  confirmIssue,
  contextPlan,
  findArticle,
  findPerson,
  isOverdue,
  lineWarnings,
  openLoans,
  personWarnings,
  reassignLoan,
  resetGaAusgabeDemo,
  returnLoan,
  scanArticle,
  setContext,
  setRecipient,
  transferLoan,
  useGaAusgabeMock,
} from './gaAusgabeMock'

const person = (name: string) => findPerson(name)!
const session = () => useGaAusgabeMock().session.value

describe('gaAusgabeMock', () => {
  beforeEach(() => resetGaAusgabeDemo())

  it('finds people and articles by scan code or name', () => {
    expect(findPerson('UK-1001')?.name).toBe('Peter Muster')
    expect(findPerson('lea')?.id).toBe('p-lea')
    expect(findArticle('AS-001')?.name).toBe('Akkuschrauber')
    expect(findArticle('stich')?.id).toBe('a-stichsaege')
    expect(findArticle('gibt-es-nicht')).toBeUndefined()
  })

  it('issues the Bar West example and keeps loans only for returnable articles', () => {
    setRecipient(person('Peter'))
    setContext('c-bar-west')
    addArticle('a-akku', 2)
    addArticle('a-kabelrolle', 1)
    addArticle('a-kabelbinder', 50)
    expect(canConfirm()).toBe(true)
    const before = openLoans().length
    expect(confirmIssue()).toEqual({ lines: 3 })
    expect(openLoans().length).toBe(before + 2)
    expect(articleById('a-kabelbinder')!.stock).toBe(450)
    expect(articleById('a-akku')!.stock).toBe(1)
    expect(useGaAusgabeMock().last.value?.person).toBe('Peter Muster')
    expect(session().lines).toHaveLength(0)
    expect(session().personId).toBe('')
  })

  it('shows plan, issued and needed for the context and allows extra articles', () => {
    const row = contextPlan('c-bar-west').find((entry) => entry.article.id === 'a-kabelbinder')!
    expect(row).toMatchObject({ planned: 50, issued: 25, needed: 25 })
    expect(addNeededFromPlan('c-bar-west')).toBe(4)
    expect(session().lines.find((entry) => entry.articleId === 'a-kabelbinder')!.qty).toBe(25)
    expect(scanArticle('SS-002').ok).toBe(true)
    expect(session().lines.some((entry) => entry.articleId === 'a-stichsaege')).toBe(true)
  })

  it('warns about missing stock, packed, defect, lent and unknown articles', () => {
    expect(lineWarnings({ articleId: 'a-handschuhe', qty: 20 })[0]).toMatchObject({ kind: 'noStock', blocking: true })
    expect(lineWarnings({ articleId: 'a-kantholz', qty: 1 })[0]).toMatchObject({ kind: 'packed', blocking: true })
    expect(lineWarnings({ articleId: 'a-bohrhammer', qty: 1 })[0]).toMatchObject({ kind: 'defect', blocking: true })
    expect(lineWarnings({ articleId: 'a-leiter', qty: 1 })[0]).toMatchObject({ kind: 'lent', blocking: true })
    expect(lineWarnings({ articleId: 'a-kabelrolle', qty: 1 }).some((warning) => warning.kind === 'defect' && !warning.blocking)).toBe(true)
    expect(lineWarnings({ articleId: 'nope', qty: 1 })[0]!.kind).toBe('unknown')
    setRecipient(person('Marco'))
    addArticle('a-leiter', 1)
    expect(canConfirm()).toBe(false)
    expect(personWarnings('Marco Rossi')[0]).toMatchObject({ kind: 'overdue' })
  })

  it('adjusts quantities with plus and minus', () => {
    addArticle('a-akku', 1)
    changeQty('a-akku', 2)
    expect(session().lines[0]!.qty).toBe(3)
    changeQty('a-akku', -3)
    expect(session().lines).toHaveLength(0)
  })

  it('books returns, transfers and reassigns open loans and writes the history', () => {
    expect(openLoans().filter((loan) => isOverdue(loan)).map((loan) => loan.id)).toEqual(['l-4', 'l-2'])
    const stock = articleById('a-funk')!.stock
    expect(returnLoan('l-2')).toBe(true)
    expect(articleById('a-funk')!.stock).toBe(stock + 2)
    expect(transferLoan('l-1', 'p-lea')).toBe(true)
    expect(transferLoan('l-1', 'p-lea')).toBe(false)
    expect(reassignLoan('l-1', 'c-crew-zelt')).toBe(true)
    const kinds = useGaAusgabeMock().history.value.slice(0, 3).map((entry) => entry.kind)
    expect(kinds).toEqual(['reassign', 'transfer', 'return'])
  })
})
