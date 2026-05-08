# Archiv: Aktivitäten (Legacy)

Enthält den **vorigen Stand** vor dem schrittweisen Neuaufbau (siehe `docs/activities-refactor.md`).

| Datei | Inhalt |
|--------|--------|
| `ActivitiesView.full.vue` | Vollständige Listen- **und** Detail-Orchestrierung (`provide`/`inject`), tausende Zeilen. |
| `components/ActivitiesDetailView.vue` | Detail-UI mit `inject(ACTIVITIES_DETAIL_INJECT)`. |
| `components/activitiesInjectKeys.ts` | `ACTIVITIES_DETAIL_INJECT` Symbol. |

**Nicht verschoben** (weiter aktiv):

- `frontend/src/styles/views/activities/*.css` – Styles; bei Bedarf für die neue Übersicht weiterverwenden oder ausdünnen.

**Hinweis:** Diese Dateien werden **nicht** vom Build der App importiert. Zum Wiederherstellen: Inhalte kopieren oder Dateien unter `src/views` / `src/components/activities` zurücklegen und Router/Exports anpassen.
