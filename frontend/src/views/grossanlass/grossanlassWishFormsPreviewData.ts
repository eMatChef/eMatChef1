import type { GrossanlassRoundStatus } from '@/api/grossanlassRounds'

export type GaWishFormPurpose = 'material_wish' | 'company_tip' | 'free'

export type GaPreviewFieldType = 'text' | 'number' | 'select' | 'date_range'

export type GaPreviewFormField = {
  id: string
  type: GaPreviewFieldType
  label: string
  required: boolean
  core?: boolean
  choices?: string[]
  multiple?: boolean
}

export type GaPreviewWishForm = {
  id: string
  name: string
  purpose: Exclude<GaWishFormPurpose, 'material_wish'>
  status: GrossanlassRoundStatus
  opens_at: string | null
  closes_at: string | null
  intro_text: string
  fields: GaPreviewFormField[]
  samples: Array<{ title: string; meta: string }>
}

type Translate = (key: string, values?: Record<string, string | number>) => string

export const PREVIEW_CUSTOM_FIELD_TYPES: GaPreviewFieldType[] = ['text', 'number', 'select', 'date_range']

export function clonePreviewFormFields(fields: GaPreviewFormField[]): GaPreviewFormField[] {
  return fields.map((field) => ({
    ...field,
    choices: field.choices ? [...field.choices] : undefined,
  }))
}

export function clonePreviewWishForm(form: GaPreviewWishForm): GaPreviewWishForm {
  return {
    ...form,
    fields: clonePreviewFormFields(form.fields),
    samples: form.samples.map((sample) => ({ ...sample })),
  }
}

export function defaultCompanyTipFields(t: Translate): GaPreviewFormField[] {
  return [
    {
      id: 'core-company-name',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.companyName'),
      required: true,
      core: true,
    },
    {
      id: 'core-company-place',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.place'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-url',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.website'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-category',
      type: 'select',
      label: t('grossanlass.planung.wishForms.fields.category'),
      required: false,
      core: true,
      multiple: true,
      choices: [
        t('grossanlass.beschaffung.anfragen.catVehicles'),
        t('grossanlass.beschaffung.anfragen.catTent'),
        t('grossanlass.beschaffung.anfragen.catWood'),
      ],
    },
    {
      id: 'core-company-offering',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.offering'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-notes',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.notes'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-salutation',
      type: 'select',
      label: t('grossanlass.planung.wishForms.fields.salutation'),
      required: false,
      core: true,
      choices: [
        t('grossanlass.planung.wishForms.fields.salutationHerr'),
        t('grossanlass.planung.wishForms.fields.salutationFrau'),
      ],
    },
    {
      id: 'core-company-first-name',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.firstName'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-last-name',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.lastName'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-email',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.email'),
      required: false,
      core: true,
    },
    {
      id: 'core-company-phone',
      type: 'text',
      label: t('grossanlass.planung.wishForms.fields.phone'),
      required: false,
      core: true,
    },
  ]
}

export function defaultPreviewFieldsForPurpose(
  t: Translate,
  purpose: Exclude<GaWishFormPurpose, 'material_wish'>,
): GaPreviewFormField[] {
  if (purpose === 'company_tip') {
    return defaultCompanyTipFields(t)
  }
  return []
}

export function createGrossanlassWishFormPreview(t: Translate): GaPreviewWishForm[] {
  return [
    {
      id: 'preview-firma',
      name: t('grossanlass.planung.wishForms.demoCompanyFleet'),
      purpose: 'company_tip',
      status: 'open',
      opens_at: '2027-05-04T08:00:00',
      closes_at: '2027-05-18T18:00:00',
      intro_text: '',
      fields: defaultCompanyTipFields(t),
      samples: [
        {
          title: t('grossanlass.materials.sourceMeier'),
          meta: t('grossanlass.planung.wishForms.demoCompanyMetaFleet'),
        },
        {
          title: t('grossanlass.materials.sourceHuber'),
          meta: t('grossanlass.planung.wishForms.demoCompanyMetaYard'),
        },
      ],
    },
    {
      id: 'preview-firma-zelt',
      name: t('grossanlass.planung.wishForms.demoCompanyTent'),
      purpose: 'company_tip',
      status: 'closed',
      opens_at: '2027-04-06T08:00:00',
      closes_at: '2027-04-20T18:00:00',
      intro_text: '',
      fields: defaultCompanyTipFields(t),
      samples: [
        {
          title: t('grossanlass.materials.sourceWinterthur'),
          meta: t('grossanlass.planung.wishForms.demoCompanyMetaTent'),
        },
      ],
    },
    {
      id: 'preview-frei-fehlt',
      name: t('grossanlass.planung.wishForms.demoFreeMissing'),
      purpose: 'free',
      status: 'open',
      opens_at: '2027-06-01T08:00:00',
      closes_at: null,
      intro_text: '',
      fields: [
        {
          id: 'demo-free-idea',
          type: 'text',
          label: t('grossanlass.planung.wishForms.fields.idea'),
          required: true,
        },
        {
          id: 'demo-free-area',
          type: 'text',
          label: t('grossanlass.planung.wishForms.fields.area'),
          required: false,
        },
      ],
      samples: [
        {
          title: t('grossanlass.planung.wishForms.demoFreeSample1'),
          meta: t('grossanlass.planung.wishForms.demoFreeSample1Meta'),
        },
        {
          title: t('grossanlass.planung.wishForms.demoFreeSample2'),
          meta: t('grossanlass.planung.wishForms.demoFreeSample2Meta'),
        },
      ],
    },
  ]
}
