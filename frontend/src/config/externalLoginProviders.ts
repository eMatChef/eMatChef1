export type ExternalLoginProviderKey = 'midata' | 'cevidb' | 'jubladb'

export interface ExternalLoginProvider {
  key: ExternalLoginProviderKey
  label: string
  organisation: string
  icon: string
  enabled: boolean
}

export const externalLoginProviders: readonly ExternalLoginProvider[] = [
  {
    key: 'midata',
    label: 'MiData',
    organisation: 'Pfadi (PBS)',
    icon: '/provider-logos/pbs.svg',
    enabled: true,
  },
  {
    key: 'cevidb',
    label: 'CeviDB',
    organisation: 'Cevi',
    icon: '/provider-logos/cevi.png',
    enabled: false,
  },
  {
    key: 'jubladb',
    label: 'JublaDB',
    organisation: 'Jubla',
    icon: '/provider-logos/jubla.png',
    enabled: false,
  },
]
