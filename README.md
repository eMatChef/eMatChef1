# eMatChef v4.01

eMatChef ist ein Tool zur Materialverwaltung und Vermietung von Material.
Es unterstützt den Bestand, Bewegungen und Rollenrechte in einem Vue-Frontend mit Symfony-Backend.

## Schnellstart

### Voraussetzungen
- Docker + Docker Compose
- Node.js 18+ (nur fuer lokale Frontend-Entwicklung)

### Start
```bash
docker-compose up -d
```

### Frontend lokal entwickeln
```bash
cd frontend
npm install
npm run dev
```

### Backend vorbereiten
```bash
cd backend
composer install
```

## Services
- Frontend: http://localhost:5173
- Backend API: http://localhost:8081
- PostgreSQL: localhost:5432
- Adminer: http://localhost:8082

## Wichtige Hinweise
- Keine `.env`-Dateien oder `*.pem`-Keys committen.
- Lokale/Dev-Daten liegen ausserhalb des Repos (z. B. `backend/data/`).

## Nützliche Backend-Befehle
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:migrate
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:status
```

## Org-Subset Seed (gezielte Startdaten)

Fuer den gezielten Start mit nur bestimmten Organisationen (z. B. `org_js000000`, `GLOBALORG001`) gibt es zwei Commands:

```bash
# Export auf Quell-Umgebung
docker exec ematchef_v401-backend-1 php bin/console app:org-subset:export --org=org_js000000 --org=GLOBALORG001 --with-global-templates --output=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json

# Import auf Ziel-Umgebung (inkl. Superadmin)
docker exec ematchef_v401-backend-1 php bin/console app:org-subset:import --file=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json --ensure-superadmin
```

Weitere Details: `docs/SETUP_ORG_SUBSET_SEED.md`