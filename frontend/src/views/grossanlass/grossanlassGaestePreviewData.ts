export type GaGuestLoanStatus = 'offered' | 'accepted' | 'declined'
export type GaJsSubmitStatus = 'submitted' | 'missing'

export type GaGuestJsLine = {
  departmentId: string
  departmentName: string
  qty: number
  status: GaJsSubmitStatus
}

export type GaGuestJsArticle = {
  id: string
  name: string
  unit: string
  catalogHint: string
  lines: GaGuestJsLine[]
}

export type GaGuestLoan = {
  id: string
  departmentId: string
  departmentName: string
  name: string
  qty: number
  family: 'vehicle' | 'material'
  fromLabel: string
  toLabel: string
  presentFromIso: string
  presentToIso: string
  status: GaGuestLoanStatus
  bookable: boolean
}

type Translate = (key: string, values?: Record<string, string | number>) => string

export function createGuestJsArticles(t: Translate): GaGuestJsArticle[] {
  const winterthur = t('grossanlass.materials.sourceWinterthur')
  const zuerich = t('grossanlass.materials.sourceZuerich')
  const uster = t('grossanlass.materials.sourceUster')

  return [
    {
      id: 'js-zeltbahn',
      name: t('grossanlass.materials.gaeste.jsZeltbahn'),
      unit: t('grossanlass.materials.gaeste.unitPiece'),
      catalogHint: t('grossanlass.materials.gaeste.jsCatalogHint'),
      lines: [
        { departmentId: 'wt', departmentName: winterthur, qty: 20, status: 'submitted' },
        { departmentId: 'zh', departmentName: zuerich, qty: 12, status: 'submitted' },
        { departmentId: 'us', departmentName: uster, qty: 0, status: 'missing' },
      ],
    },
    {
      id: 'js-kochtopf',
      name: t('grossanlass.materials.gaeste.jsKochtopf'),
      unit: t('grossanlass.materials.gaeste.unitPiece'),
      catalogHint: t('grossanlass.materials.gaeste.jsCatalogHint'),
      lines: [
        { departmentId: 'wt', departmentName: winterthur, qty: 4, status: 'submitted' },
        { departmentId: 'zh', departmentName: zuerich, qty: 2, status: 'submitted' },
        { departmentId: 'us', departmentName: uster, qty: 0, status: 'missing' },
      ],
    },
    {
      id: 'js-netz',
      name: t('grossanlass.materials.gaeste.jsNetz'),
      unit: t('grossanlass.materials.gaeste.unitPiece'),
      catalogHint: t('grossanlass.materials.gaeste.jsCatalogHint'),
      lines: [
        { departmentId: 'wt', departmentName: winterthur, qty: 2, status: 'submitted' },
        { departmentId: 'zh', departmentName: zuerich, qty: 0, status: 'submitted' },
        { departmentId: 'us', departmentName: uster, qty: 0, status: 'missing' },
      ],
    },
  ]
}

export function jsArticleTotal(article: GaGuestJsArticle): number {
  return article.lines.reduce((sum, line) => sum + (line.status === 'submitted' ? line.qty : 0), 0)
}

export function createGuestLoans(t: Translate): GaGuestLoan[] {
  const winterthur = t('grossanlass.materials.sourceWinterthur')
  const zuerich = t('grossanlass.materials.sourceZuerich')
  const uster = t('grossanlass.materials.sourceUster')

  return [
    {
      id: 'guest-tische',
      departmentId: 'wt',
      departmentName: winterthur,
      name: t('grossanlass.materials.gaeste.loanTables'),
      qty: 20,
      family: 'material',
      fromLabel: '03.09.27',
      toLabel: '06.09.27',
      presentFromIso: '2027-09-03T08:00:00',
      presentToIso: '2027-09-06T18:00:00',
      status: 'accepted',
      bookable: true,
    },
    {
      id: 'guest-pavillon',
      departmentId: 'wt',
      departmentName: winterthur,
      name: t('grossanlass.materials.gaeste.loanPavilion'),
      qty: 4,
      family: 'material',
      fromLabel: '03.09.27',
      toLabel: '06.09.27',
      presentFromIso: '2027-09-03T08:00:00',
      presentToIso: '2027-09-06T18:00:00',
      status: 'accepted',
      bookable: true,
    },
    {
      id: 'guest-transporter',
      departmentId: 'zh',
      departmentName: zuerich,
      name: t('grossanlass.materials.gaeste.loanVan'),
      qty: 2,
      family: 'vehicle',
      fromLabel: '02.09.27',
      toLabel: '06.09.27',
      presentFromIso: '2027-09-02T08:00:00',
      presentToIso: '2027-09-06T18:00:00',
      status: 'offered',
      bookable: false,
    },
    {
      id: 'guest-baenke',
      departmentId: 'us',
      departmentName: uster,
      name: t('grossanlass.materials.gaeste.loanBenches'),
      qty: 50,
      family: 'material',
      fromLabel: '03.09.27',
      toLabel: '05.09.27',
      presentFromIso: '2027-09-03T08:00:00',
      presentToIso: '2027-09-05T20:00:00',
      status: 'offered',
      bookable: false,
    },
  ]
}
