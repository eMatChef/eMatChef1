import { reactive } from 'vue'
import type { GaAnfrageStatus } from '@/views/grossanlass/grossanlassAnfragenPreviewData'

export type GaChainParticipantStatus = 'planned' | 'pending' | 'accepted' | 'rejected'

export type GaChainParticipant = {
  id: string
  nameKey: string
  ressortKey: string
  status: GaChainParticipantStatus
}

export type GaIssuePlace = 'lager' | 'assigned' | 'out'
export type GaIssueRecipientKind = 'ressort' | 'guest'
export type GaIssueBucket = 'today' | 'tomorrow' | 'express'

export type GaChainIssue = {
  id: string
  name: string
  qty: number
  family: 'vehicle' | 'material'
  place: GaIssuePlace
  recipientKind?: GaIssueRecipientKind
  recipient: string
  driverOk?: boolean
  bucket: GaIssueBucket
  whenLabel: string
  plannedFor: string
  personId?: string
}

export type GaUserCard = {
  id: string
  name: string
  ressort: string
  role: string
  printed: boolean
  mayDrive: boolean
  code: string
}

export type GaPackLine = {
  id: string
  phase: 'aufbau' | 'anlass'
  name: string
  qty: number
  packed: boolean
}

export type GaFirmReturn = {
  id: string
  name: string
  firm: string
  due: string
  returned: boolean
}

export type GaAnfrageThreadLine = {
  who: 'ok' | 'firm'
  text: string
}

const state = reactive({
  published: false,
  participants: [
    { id: 'wt', nameKey: 'grossanlass.materials.sourceWinterthur', ressortKey: 'grossanlass.chain.regionOst', status: 'planned' as GaChainParticipantStatus },
    { id: 'zh', nameKey: 'grossanlass.materials.sourceZuerich', ressortKey: 'grossanlass.chain.regionWest', status: 'planned' as GaChainParticipantStatus },
    { id: 'us', nameKey: 'grossanlass.materials.sourceUster', ressortKey: 'grossanlass.chain.regionOst', status: 'planned' as GaChainParticipantStatus },
  ] as GaChainParticipant[],
  jsSubmitted: {} as Record<string, boolean>,
  jsQty: {} as Record<string, number>,
  extraLoans: [] as Array<{
    id: string
    departmentId: string
    name: string
    qty: number
    family: 'vehicle' | 'material'
  }>,
  issues: [
    {
      id: 'iss-gator',
      name: 'Gator',
      qty: 1,
      family: 'vehicle' as const,
      place: 'lager' as GaIssuePlace,
      recipient: 'Bau',
      recipientKind: 'ressort' as const,
      bucket: 'today' as GaIssueBucket,
      whenLabel: '08:00',
      plannedFor: 'Bau · Bühne',
    },
    {
      id: 'iss-zelt',
      name: 'Festzelt 10 × 20 m',
      qty: 1,
      family: 'material' as const,
      place: 'assigned' as GaIssuePlace,
      recipientKind: 'ressort' as const,
      recipient: 'Verpflegung',
      bucket: 'today' as GaIssueBucket,
      whenLabel: '09:30',
      plannedFor: 'Verpflegung',
    },
    {
      id: 'iss-geruest',
      name: 'Gerüst',
      qty: 8,
      family: 'material' as const,
      place: 'lager' as GaIssuePlace,
      recipient: 'Bau',
      recipientKind: 'ressort' as const,
      bucket: 'today' as GaIssueBucket,
      whenLabel: '14:00',
      plannedFor: 'Bau · Wasserstelle',
    },
    {
      id: 'iss-tische',
      name: 'Biertische (Pfadi Winterthur)',
      qty: 20,
      family: 'material' as const,
      place: 'lager' as GaIssuePlace,
      recipient: 'Verpflegung',
      recipientKind: 'ressort' as const,
      bucket: 'tomorrow' as GaIssueBucket,
      whenLabel: '10:00',
      plannedFor: 'Verpflegung',
    },
    {
      id: 'iss-kabel',
      name: 'Kabel 32A extra',
      qty: 2,
      family: 'material' as const,
      place: 'lager' as GaIssuePlace,
      recipient: 'Sicherheit',
      recipientKind: 'ressort' as const,
      bucket: 'express' as GaIssueBucket,
      whenLabel: 'sofort',
      plannedFor: '',
    },
  ] as GaChainIssue[],
  userCards: [
    { id: 'uc-lea', name: 'Lea Meier', ressort: 'Verpflegung', role: 'RL', printed: true, mayDrive: false, code: 'EMC-PFF-LEA' },
    { id: 'uc-jonas', name: 'Jonas Keller', ressort: 'Bau', role: 'MW', printed: false, mayDrive: true, code: 'EMC-PFF-JON' },
    { id: 'uc-samira', name: 'Samira Ali', ressort: 'Sicherheit', role: 'Fahrerin', printed: true, mayDrive: true, code: 'EMC-PFF-SAM' },
    { id: 'uc-nico', name: 'Nico Brunner', ressort: 'Bau', role: 'Abholung', printed: false, mayDrive: false, code: 'EMC-PFF-NIC' },
  ] as GaUserCard[],
  pack: [
    { id: 'pk-1', phase: 'aufbau' as const, name: 'Gerüst', qty: 8, packed: true },
    { id: 'pk-2', phase: 'aufbau' as const, name: 'Kabel 32A', qty: 4, packed: false },
    { id: 'pk-3', phase: 'anlass' as const, name: 'Festzelt 10 × 20 m', qty: 1, packed: false },
    { id: 'pk-4', phase: 'anlass' as const, name: 'Biertische', qty: 20, packed: false },
  ] as GaPackLine[],
  returns: [
    { id: 'ret-zelt', name: 'Festzelt 10 × 20 m', firm: 'Pfadi Winterthur', due: '06.09.27', returned: false },
    { id: 'ret-gator', name: 'Gator', firm: 'Meier Bau + Transport', due: '06.09.27', returned: false },
    { id: 'ret-teleskop', name: 'Teleskoplader', firm: 'Meier Bau + Transport', due: '20.07.27', returned: false },
  ] as GaFirmReturn[],
  anfrageStatus: {} as Record<string, GaAnfrageStatus>,
  anfrageThreads: {} as Record<string, GaAnfrageThreadLine[]>,
})

export function chainState() {
  return state
}

export function isGrossanlassPublished(): boolean {
  return state.published
}

export function listChainParticipants(): GaChainParticipant[] {
  return state.participants
}

export function publishGrossanlassPreview() {
  state.published = true
  for (const row of state.participants) {
    if (row.status === 'planned') row.status = 'pending'
  }
}

export function acceptGuestInvite(departmentId: string) {
  const row = state.participants.find((item) => item.id === departmentId)
  if (row) row.status = 'accepted'
}

export function rejectGuestInvite(departmentId: string) {
  const row = state.participants.find((item) => item.id === departmentId)
  if (row) row.status = 'rejected'
}

export function setGuestJsQty(articleId: string, departmentId: string, qty: number) {
  state.jsQty[`${articleId}:${departmentId}`] = qty
  state.jsSubmitted[departmentId] = true
}

export function guestJsQty(articleId: string, departmentId: string, fallback: number): number {
  return state.jsQty[`${articleId}:${departmentId}`] ?? fallback
}

export function isGuestJsSubmitted(departmentId: string): boolean {
  return !!state.jsSubmitted[departmentId]
}

export function addGuestOfferedLoan(loan: { departmentId: string; name: string; qty: number; family: 'vehicle' | 'material' }) {
  state.extraLoans.push({
    id: `guest-extra-${Date.now().toString(36)}`,
    ...loan,
  })
}

export function listExtraGuestLoans() {
  return state.extraLoans
}

export function listIssues(): GaChainIssue[] {
  return state.issues
}

export function issueItem(
  id: string,
  recipientKind: GaIssueRecipientKind,
  recipient: string,
  driverOk?: boolean,
  personId?: string,
) {
  const row = state.issues.find((item) => item.id === id)
  if (!row) return
  row.place = 'out'
  row.recipientKind = recipientKind
  row.recipient = recipient
  row.personId = personId
  if (row.family === 'vehicle') row.driverOk = driverOk ?? false
}

export function listUserCards(): GaUserCard[] {
  return state.userCards
}

export function userCardById(id: string): GaUserCard | undefined {
  return state.userCards.find((row) => row.id === id)
}

export function issuesForCard(cardId: string): GaChainIssue[] {
  const card = userCardById(cardId)
  if (!card) return []
  return state.issues.filter((row) => row.recipient === card.ressort || row.personId === cardId)
}

export function printUserCard(id: string) {
  const row = state.userCards.find((item) => item.id === id)
  if (row) row.printed = true
}

export function printUnprintedUserCards() {
  for (const row of state.userCards) {
    if (!row.printed) row.printed = true
  }
}

export function setUserMayDrive(id: string, mayDrive: boolean) {
  const row = state.userCards.find((item) => item.id === id)
  if (row) row.mayDrive = mayDrive
}

export function stockCounts() {
  return {
    lager: state.issues.filter((row) => row.place === 'lager').length,
    assigned: state.issues.filter((row) => row.place === 'assigned').length,
    out: state.issues.filter((row) => row.place === 'out').length,
  }
}

export function listPackLines(): GaPackLine[] {
  return state.pack
}

export function togglePacked(id: string) {
  const row = state.pack.find((item) => item.id === id)
  if (row) row.packed = !row.packed
}

export function listFirmReturns(): GaFirmReturn[] {
  return state.returns
}

export function markReturned(id: string) {
  const row = state.returns.find((item) => item.id === id)
  if (row) row.returned = true
}

export function anfrageStatusOf(id: string, fallback: GaAnfrageStatus): GaAnfrageStatus {
  return state.anfrageStatus[id] ?? fallback
}

export function setAnfrageStatus(id: string, status: GaAnfrageStatus) {
  state.anfrageStatus[id] = status
}

export function anfrageThread(id: string): GaAnfrageThreadLine[] {
  return state.anfrageThreads[id] ?? []
}

export function addAnfrageThread(id: string, line: GaAnfrageThreadLine) {
  const current = state.anfrageThreads[id] ?? []
  state.anfrageThreads[id] = [...current, line]
}

export function markAnfrageDraftsSent(ids: string[]) {
  for (const id of ids) {
    state.anfrageStatus[id] = 'gesendet'
    const thread = state.anfrageThreads[id] ?? []
    if (!thread.length) {
      state.anfrageThreads[id] = [{ who: 'ok', text: 'Entwurf als gesendet markiert (Vorschau, kein Gmail).' }]
    }
  }
}

export function simulateFirmReply(id: string) {
  addAnfrageThread(id, {
    who: 'firm',
    text: 'Danke für die Anfrage — wir können den Zeitraum voraussichtlich abdecken. Offerte folgt.',
  })
  setAnfrageStatus(id, 'antwort')
}

export function markAnfrageZusage(id: string) {
  addAnfrageThread(id, { who: 'ok', text: 'Als Zusage erfasst (Vorschau).' })
  setAnfrageStatus(id, 'zusage')
}
