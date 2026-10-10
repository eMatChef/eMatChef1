/**
 * Offene Ersteinrichtung eines Grossanlasses: welche Seiten des Departments sind erreichbar?
 * Reine Navigationshilfe: die Sperre erzwingt der Server (`GrossanlassSetupGateSubscriber`).
 *
 * - Alle Mitglieder: das Dashboard (dort steht der Einrichtungsstand bzw. der Hinweis) und die Hilfe (Dokumentation, GA-Hilfe).
 * - MW, Co-MW, OK-Leitung zusätzlich: Stammdaten und Ressorts unter «Grossanlass verwalten» und die Einstellungen
 *   (Mitglieder, Mein Department). Die Einrichtungs-Tour liegt im Hilfe-Hub.
 */
export function isGrossanlassSetupAllowedPath(path: string, departmentId: string, canSetup: boolean): boolean {
  const base = `/${departmentId}`
  if (path !== base && !path.startsWith(`${base}/`)) return true // nicht dieses Department
  const rest = path.slice(base.length).replace(/\/+$/, '')
  if (rest === '' || rest === '/ga/dashboard' || rest === '/dept/dashboard') return true
  if (rest === '/dept/help' || rest.startsWith('/dept/help/')) return true
  if (rest === '/ga/help' || rest.startsWith('/ga/help/')) return true
  if (rest === '/ga-hilfe' || rest.startsWith('/ga-hilfe/')) return true // alter Pfad, leitet weiter
  if (!canSetup) return false
  if (rest === '/ga/einstellungen' || rest === '/ga/einstellungen/stammdaten' || rest === '/ga/einstellungen/ressorts') return true
  return rest === '/dept/settings' || rest.startsWith('/dept/settings/')
}
