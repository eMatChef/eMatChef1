# Status page (Better Stack)

Öffentliche Statusseite wie bei eCamp (`status.ecamp3.ch`): **extern** hosten (Better Stack), nicht auf dem gleichen Droplet/Hostpoint wie die App — sonst fällt die Statusseite mit der App aus.

## Voraussetzungen im Code

- Health-Check: `GET https://api.ematchef.ch/api/health` → `{ "status": "ok" }` bei erreichbarer DB, sonst HTTP 503.
- Develop: `GET https://api.dev.ematchef.ch/api/health`
- Footer-Link auf der Marketing-Site zeigt auf `https://status.ematchef.ch` (anpassbar per `VITE_STATUS_PAGE_URL` beim Frontend-Build).

## Setup (einmalig)

1. Account bei [Better Stack](https://betterstack.com/) (Uptime) — Free-Tier reicht für wenige Monitore.
2. **Monitors** anlegen (HTTP, erwarteter Status 200) — **je ein Monitor = eine Komponente** auf der Statusseite:

   | Komponente (Name in Better Stack) | URL | Was sie misst |
   |-----------------------------------|-----|----------------|
   | **Website** (Marketing) | `https://ematchef.ch/` | Hostpoint Landing |
   | **App** (Frontend) | `https://app.ematchef.ch/` | Vue-App Hostpoint |
   | **API** (Backend) | `https://api.ematchef.ch/api/health` | Symfony + DB |

   Optional Develop (eigene Statusseite oder Sektion „Development“):
   - App: `https://app.dev.ematchef.ch/`
   - API: `https://api.dev.ematchef.ch/api/health`

   So siehst du z. B. „App ok, API down“ statt nur „alles rot“.
3. **Status page** erstellen, alle Prod-Monitore zuordnen, Titel z. B. „eMatChef Status“. In der Status-Page-UI können Monitore als **Resources / Components** benannt und gruppiert werden (Website / App / API).
4. **Custom Domain**: `status.ematchef.ch` → CNAME laut Better-Stack-Anleitung (DNS bei Hostpoint).
5. Optional: Incident-Alerts per E-Mail oder Slack.

## Checkliste nach Setup

- [x] Statusseite unter `https://status.ematchef.ch` erreichbar
- [ ] Monitor für `/api/health` zeigt grün — wartet auf Deploy von `GET /api/health` (Code erst pushen → Droplet ziehen; Endpoint ist lokal im Repo, noch nicht auf Prod-API)
- [ ] Footer-Link „Status“ auf `ematchef.ch` öffnet die Statusseite — nach Frontend-Deploy (Hostpoint-Build mit Footer-Link)
- [ ] Test-Incident oder Pause eines Monitors prüft Alerts

Keine Status-UI im Vue-Backend nötig — nur der Health-Endpoint und der Footer-Link.
