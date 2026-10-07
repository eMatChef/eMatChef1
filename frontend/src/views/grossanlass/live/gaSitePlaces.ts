/** Gemeinsame Demo-Koordinaten des Geländes (SVG-Karte, 640×380) für Disposition und Displays. */
export const GA_SITE_PLACES: Record<string, { x: number; y: number }> = {
  Zentrallager: { x: 300, y: 210 },
  'Bar West': { x: 520, y: 100 },
  'Häberli Holz': { x: 90, y: 90 },
  'Festgelände Süd': { x: 480, y: 300 },
  'Info-Pagode Eingang': { x: 580, y: 200 },
  'Lager A': { x: 250, y: 150 },
  'Zeltbau AG': { x: 70, y: 300 },
  'Werkstatt Müller': { x: 160, y: 250 },
  'Festzelt AG': { x: 120, y: 160 },
  'Catering-Zelt': { x: 420, y: 180 },
  'Crew-Zelt Backstage': { x: 380, y: 60 },
  'Sägerei Roth': { x: 40, y: 200 },
}

export function placePos(name: string | null | undefined): { x: number; y: number } {
  return (name && GA_SITE_PLACES[name]) || { x: 320, y: 190 }
}

export function hasPlace(name: string | null | undefined): boolean {
  return !!name && !!GA_SITE_PLACES[name]
}
