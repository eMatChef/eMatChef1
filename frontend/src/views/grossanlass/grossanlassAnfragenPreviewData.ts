export type GaAnfrageStatus =
  | 'entwurf'
  | 'gesendet'
  | 'antwort'
  | 'zusage'
  | 'absage'
  | 'vorschlag'

export type GaAnfrageCategory = {
  id: string
  labelKey: string
}

export type GaAnfrageFirma = {
  id: string
  name: string
  email: string
  place: string
  categoryIds: string[]
  status: GaAnfrageStatus
  tipFrom?: string
}

export const GA_ANFRAGE_CATEGORIES: GaAnfrageCategory[] = [
  { id: 'fahrzeuge', labelKey: 'grossanlass.beschaffung.anfragen.catVehicles' },
  { id: 'zelt', labelKey: 'grossanlass.beschaffung.anfragen.catTent' },
  { id: 'holz', labelKey: 'grossanlass.beschaffung.anfragen.catWood' },
]

export function createGrossanlassAnfragenPreview(): GaAnfrageFirma[] {
  return [
    {
      id: 'a1f3c8e2b904',
      name: 'Müller Transporte',
      email: 'dispo@mueller-transporte.example',
      place: 'Langenthal',
      categoryIds: ['fahrzeuge'],
      status: 'entwurf',
    },
    {
      id: 'b7d21a90c445',
      name: 'Zeltwerk AG',
      email: 'anfragen@zeltwerk.example',
      place: 'Burgdorf',
      categoryIds: ['zelt'],
      status: 'gesendet',
    },
    {
      id: 'c0e88d11f672',
      name: 'Holzbau Keller',
      email: 'info@holzbau-keller.example',
      place: 'Langnau',
      categoryIds: ['holz'],
      status: 'antwort',
    },
    {
      id: 'd4b55e03a118',
      name: 'Eventlift GmbH',
      email: 'hello@eventlift.example',
      place: 'Bern',
      categoryIds: ['fahrzeuge', 'zelt'],
      status: 'zusage',
    },
    {
      id: 'e9aa7c64d230',
      name: 'Bauhilfe Nord',
      email: '',
      place: 'Solothurn',
      categoryIds: ['fahrzeuge'],
      status: 'entwurf',
    },
    {
      id: 'f2c19b87e551',
      name: 'Sägerei Hostettler',
      email: 'verkauf@saegerei-hostettler.example',
      place: 'Signau',
      categoryIds: ['holz'],
      status: 'vorschlag',
      tipFrom: 'Bau / Bühne',
    },
    {
      id: 'a88e01c3d776',
      name: 'PartyZelte West',
      email: 'office@partyzelte-west.example',
      place: 'Biel',
      categoryIds: ['zelt'],
      status: 'absage',
    },
  ]
}

export function anfrageCategoryLabel(
  categoryId: string,
  t: (key: string) => string,
): string {
  const row = GA_ANFRAGE_CATEGORIES.find((item) => item.id === categoryId)
  return row ? t(row.labelKey) : categoryId
}

export function anfrageMaterialList(
  firma: GaAnfrageFirma,
  t: (key: string) => string,
): string {
  return firma.categoryIds.map((id) => anfrageCategoryLabel(id, t)).join(', ')
}

export function anfrageMailPreview(
  firma: GaAnfrageFirma,
  t: (key: string, values?: Record<string, string | number>) => string,
): { subject: string; body: string } {
  const packages = anfrageMaterialList(firma, t)
  return {
    subject: t('grossanlass.beschaffung.anfragen.mailSubject', { event: 'PFF 2027' }),
    body: t('grossanlass.beschaffung.anfragen.mailBody', {
      firma: firma.name,
      packages,
      id: firma.id,
    }),
  }
}
