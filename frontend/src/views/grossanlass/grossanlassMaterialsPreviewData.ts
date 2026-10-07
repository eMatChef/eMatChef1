import type { GrossanlassCommitmentOrigin } from '@/api/grossanlassCommitments'
import type { SandboxComboComponent, SandboxMaterialRow } from '@/views/dev/materialSandboxTypes'

export type GaMaterialsTabId = 'uebersicht' | 'eigen' | 'leihweise' | 'fahrzeuge'
export type GaLifecycle = 'reusable' | 'cut_consumable' | 'loan' | 'buy_resale'

export type GaPreviewRow = SandboxMaterialRow & {
  barcode: string
  tabs: GaMaterialsTabId[]
  lifecycle: GaLifecycle
  source?: string
  validFrom?: string
  validTo?: string
  plate?: string
  vehicleStatus?: string
  location?: string
  presentFromIso?: string
  presentToIso?: string
  handoverFromIso?: string
  handoverToIso?: string
  returnFromIso?: string
  returnToIso?: string
  releasedForEinsatz?: boolean
  origin?: GrossanlassCommitmentOrigin
  sessionCreated?: boolean
  feinWish?: { label: string; ressort: string; fromIso: string; toIso: string }
  parkServices?: Array<{
    id: string
    kind: 'clean' | 'grease' | 'other'
    fromIso: string
    toIso: string
    who: string
    label?: string
  }>
}

type Translate = (key: string) => string

function line(
  id: string,
  name: string,
  qty: number,
  assignment: 'fixed' | 'pool',
  assignmentLabel: string,
  serial?: string | null,
): SandboxComboComponent {
  return { id, name, qty, assignment, assignment_label: assignmentLabel, serial: serial ?? null }
}

export function createGrossanlassMaterialsPreview(t: Translate): GaPreviewRow[] {
  const lager = t('grossanlass.material.statLager')
  const usedUp = t('grossanlass.material.usedUp')
  const resale = t('grossanlass.material.resold')
  const bau = t('grossanlass.material.sampleRessortBau')
  const technik = t('grossanlass.material.sampleRessortTechnik')
  const wasser = t('grossanlass.material.sampleRessortWasser')
  const verpflegung = t('grossanlass.material.sampleRessortVerpflegung')

  return [
    {
      id: 'geruest',
      name: t('grossanlass.material.sampleGeruest'),
      barcode: 'GERUEST-01',
      is_combo: true,
      material_type: 'physical_combo',
      lifecycle: 'reusable',
      tabs: ['uebersicht', 'eigen'],
      location: t('grossanlass.materials.locZentrallager'),
      category_name: t('grossanlass.materials.catInfra'),
      total_stock: 12,
      issued_out: 4,
      repair_stock: 0,
      available: 8,
      components: [
        line('geruest-lager', lager, 8, 'pool', lager),
        line('geruest-p1', t('grossanlass.material.sampleWho1'), 3, 'fixed', bau, t('grossanlass.material.sampleWhen1')),
        line('geruest-p2', t('grossanlass.material.sampleWho2'), 1, 'fixed', technik, t('grossanlass.material.sampleWhen2')),
      ],
    },
    {
      id: 'kabel',
      name: t('grossanlass.material.sampleKabel'),
      barcode: 'KABEL-32A-004',
      is_combo: true,
      material_type: 'physical_combo',
      lifecycle: 'reusable',
      tabs: ['uebersicht', 'eigen'],
      location: t('grossanlass.materials.locZentrallager'),
      category_name: t('grossanlass.materials.catElektro'),
      total_stock: 6,
      issued_out: 2,
      repair_stock: 0,
      available: 4,
      components: [
        line('kabel-lager', lager, 4, 'pool', lager),
        line('kabel-p3', t('grossanlass.material.sampleWho3'), 2, 'fixed', technik, t('grossanlass.material.sampleWhen3')),
      ],
    },
    {
      id: 'folie',
      name: t('grossanlass.material.sampleFolie'),
      barcode: 'FOLIE-PE-200',
      is_combo: true,
      is_consumable: true,
      lifecycle: 'cut_consumable',
      tabs: ['uebersicht', 'eigen'],
      location: t('grossanlass.materials.locZentrallager'),
      category_name: t('grossanlass.materials.catVerbrauch'),
      total_stock: 200,
      issued_out: 180,
      repair_stock: 0,
      available: 20,
      pack_unit: 'm',
      components: [
        line('folie-lager', lager, 20, 'pool', lager),
        line('folie-cut1', usedUp, 120, 'fixed', wasser, t('grossanlass.material.sampleWhen4')),
        line('folie-cut2', usedUp, 60, 'fixed', bau, t('grossanlass.material.sampleWhen1')),
      ],
    },
    {
      id: 'zelt',
      name: t('grossanlass.material.sampleZelt'),
      barcode: 'ZELT-10X20-LEIH',
      is_combo: true,
      lifecycle: 'loan',
      tabs: ['uebersicht', 'leihweise'],
      source: t('grossanlass.materials.sourceWinterthur'),
      validFrom: '10.07.2026',
      validTo: '22.07.2026',
      category_name: t('grossanlass.materials.catZelt'),
      total_stock: 1,
      issued_out: 1,
      repair_stock: 0,
      available: 0,
      components: [
        line('zelt-out', t('grossanlass.material.sampleWho1'), 1, 'fixed', verpflegung, t('grossanlass.material.sampleWhen5')),
      ],
    },
    {
      id: 'teleskop',
      name: t('grossanlass.material.sampleTeleskop'),
      barcode: 'TEL-MEIER-07',
      is_combo: true,
      lifecycle: 'loan',
      tabs: ['uebersicht', 'leihweise'],
      source: t('grossanlass.materials.sourceMeier'),
      validFrom: '12.07.2026',
      validTo: '20.07.2026',
      category_name: t('grossanlass.materials.catMaschine'),
      total_stock: 1,
      issued_out: 1,
      repair_stock: 0,
      available: 0,
      components: [
        line('tel-out', t('grossanlass.material.sampleWho2'), 1, 'fixed', bau, t('grossanlass.material.sampleWhen2')),
      ],
    },
    {
      id: 'paletten',
      name: t('grossanlass.material.samplePaletten'),
      barcode: 'PAL-EURO-40',
      is_combo: true,
      lifecycle: 'buy_resale',
      tabs: ['uebersicht', 'eigen'],
      location: t('grossanlass.materials.locZentrallager'),
      category_name: t('grossanlass.materials.catLogistik'),
      total_stock: 40,
      issued_out: 22,
      repair_stock: 0,
      available: 18,
      components: [
        line('pal-lager', lager, 18, 'pool', lager),
        line('pal-bau', t('grossanlass.material.sampleWho2'), 12, 'fixed', bau, t('grossanlass.material.sampleWhen2')),
        line('pal-sale', resale, 10, 'fixed', t('grossanlass.material.sampleResalePlace'), t('grossanlass.material.sampleWhen6')),
      ],
    },
    {
      id: 'gator',
      name: t('grossanlass.materials.sampleGator'),
      barcode: 'GATOR-ZH-441',
      is_combo: true,
      lifecycle: 'loan',
      tabs: ['fahrzeuge', 'uebersicht'],
      plate: 'ZH 441 207',
      vehicleStatus: t('grossanlass.materials.vehicleInUse'),
      source: t('grossanlass.materials.sourceMeier'),
      validFrom: '12.07.2026',
      validTo: '20.07.2026',
      category_name: t('grossanlass.materials.catFahrzeug'),
      total_stock: 1,
      issued_out: 1,
      repair_stock: 0,
      available: 0,
      components: [
        line('gator-out', t('grossanlass.material.sampleWho3'), 1, 'fixed', technik, t('grossanlass.material.sampleWhen3')),
      ],
    },
    {
      id: 'anhaenger',
      name: t('grossanlass.materials.sampleTrailer'),
      barcode: 'ANH-PFF-02',
      is_combo: true,
      lifecycle: 'reusable',
      tabs: ['fahrzeuge', 'eigen'],
      plate: 'ZH 882 019',
      vehicleStatus: t('grossanlass.materials.vehicleLager'),
      location: t('grossanlass.materials.locZentrallager'),
      category_name: t('grossanlass.materials.catFahrzeug'),
      total_stock: 1,
      issued_out: 0,
      repair_stock: 0,
      available: 1,
      components: [line('anh-lager', lager, 1, 'pool', lager)],
    },
    {
      id: 'transporter',
      name: t('grossanlass.materials.sampleTransporter'),
      barcode: 'TRANS-VK-09',
      is_combo: true,
      lifecycle: 'buy_resale',
      tabs: ['fahrzeuge', 'uebersicht'],
      plate: 'ZH 110 334',
      vehicleStatus: t('grossanlass.materials.vehicleResale'),
      category_name: t('grossanlass.materials.catFahrzeug'),
      total_stock: 1,
      issued_out: 0,
      repair_stock: 0,
      available: 1,
      components: [
        line('trans-lager', lager, 1, 'pool', lager),
        line('trans-sale', resale, 1, 'fixed', t('grossanlass.material.sampleResalePlace'), t('grossanlass.material.sampleWhen6')),
      ],
    },
  ]
}

export function searchPreviewRows(rows: GaPreviewRow[], query: string): GaPreviewRow[] {
  const q = query.trim().toLowerCase()
  if (!q) return rows
  return rows.filter((row) => previewSearchHaystack(row).includes(q))
}

export function findPreviewRowByCode(rows: GaPreviewRow[], query: string): GaPreviewRow | undefined {
  const q = query.trim().toLowerCase()
  if (!q) return undefined
  return rows.find((row) => row.barcode.toLowerCase() === q || row.id.toLowerCase() === q)
}

export function findPreviewRowById(rows: GaPreviewRow[], id: string): GaPreviewRow | undefined {
  return rows.find((row) => row.id === id)
}

function previewSearchHaystack(row: GaPreviewRow): string {
  const parts = [
    row.name,
    row.barcode,
    row.category_name,
    row.location,
    row.source,
    row.plate,
    row.vehicleStatus,
    ...(row.components ?? []).flatMap((c) => [c.name, c.assignment_label, c.serial]),
  ]
  return parts.filter(Boolean).join(' ').toLowerCase()
}
