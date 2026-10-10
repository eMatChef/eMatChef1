/**
 * Offene Ersteinrichtung eines Grossanlasses: welche Seiten des Departments sind erreichbar?
 * Reine Navigationshilfe: die Sperre erzwingt der Server (`GrossanlassSetupGateSubscriber`).
 *
 * - Alle Mitglieder: das Dashboard (dort steht der Einrichtungsstand bzw. der Hinweis) und die Hilfe (Dokumentation, GA-Hilfe).
 * - MW, Co-MW, OK-Leitung zusätzlich: Stammdaten, Ressorts und Benutzer unter «Grossanlass verwalten» (/ga/activity-settings).
 *   Die Konfiguration (`/ga/settings/...`, `/dept/settings/...`) ist bis zur Freigabe gesperrt; das Profil (`/profile`) bleibt erreichbar. Die Einrichtungs-Tour liegt im Hilfe-Hub.
 */
export function isGrossanlassSetupAllowedPath(path: string, departmentId: string, canSetup: boolean): boolean {
  const base = `/${departmentId}`
  if (path !== base && !path.startsWith(`${base}/`)) return true // nicht dieses Department
  const rest = path.slice(base.length).replace(/\/+$/, '')
  if (rest === '' || rest === '/ga/dashboard' || rest === '/dept/dashboard') return true
  if (rest === '/dept/help' || rest.startsWith('/dept/help/')) return true
  if (rest === '/ga/help' || rest.startsWith('/ga/help/')) return true
  if (!canSetup) return false
  if (rest === '/ga/activity-settings' || rest === '/ga/activity-settings/general' || rest === '/ga/activity-settings/units' || rest === '/ga/activity-settings/users') return true
  // Konfiguration (/ga/settings, /dept/settings) bleibt bis zur Freigabe gesperrt; /profile liegt ausserhalb des Departments.
  return false
}
