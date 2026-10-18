import type { GaPlace } from '@/api/grossanlassLogistics'

export type GaStoragePlaceOption = { id: string; name: string }

/** Lagerstandorte (kind=lagerplatz) — gespiegelt aus Einstellungen → Standorte → Lagerstandorte. */
export function matplaetzeFromPlaces(places: GaPlace[]): GaStoragePlaceOption[] {
  return places
    .filter((place) => place.kind === 'lagerplatz')
    .map((place) => ({ id: place.id, name: place.name }))
    .sort((a, b) => a.name.localeCompare(b.name, 'de'))
}
