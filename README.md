# eMatChef v4.01

Materialverwaltungssystem für Vermietungen - Neuaufbau

## 🚀 Schnellstart

### Voraussetzungen
- Docker & Docker Compose
- Node.js 18+ (für lokale Frontend-Entwicklung)

### 1. Services starten
```bash
docker-compose up -d
```

### 2. Frontend (lokal entwickeln)
```bash
cd frontend
npm install
npm run dev
```

### 3. Backend Setup
```bash
cd backend
composer install
```

## 📁 Projektstruktur

```
eMatChef_v4.01/
├── frontend/          # Vue 3 + Vite Frontend
├── backend/           # Symfony Backend
├── docker-compose.yml # Docker Services
└── README.md
```

## 🔧 Services

- **Frontend**: http://localhost:5173
- **Backend**: http://localhost:8081
- **Database**: localhost:5432
- **Adminer**: http://localhost:8082

### Zugangsdaten

**Datenbank (PostgreSQL):**
- Host: `localhost:5432`
- Database: `mvdb`
- User: `mvuser`
- Password: `mvpass`

**Adminer:**
- URL: http://localhost:8082
- Server: `db`
- User: `mvuser`
- Password: `mvpass`
- Database: `mvdb`

## 🔐 Test-Zugänge

- **Admin**: `admin@example.com` / `password` (ROLE_SUPERADMIN)
- **Manager**: `manager@example.com` / `password` (ROLE_MANAGER)
- **User**: `user@example.com` / `password` (ROLE_USER)

## 📝 Features

- ✅ Login-Seite (Startseite)
- ✅ Materialverwaltung (mit Sidebar & Header)
- ✅ Authentifizierung (JWT Token)
- ✅ Berechtigungssystem (Permissions)
- ✅ Datenbank-Migrationen (Doctrine)
- 🔄 Dashboard (geplant)
- 🔄 Ausleihverwaltung (geplant)

## 🗄️ Datenbank-Migrationen

### Migrationen ausführen
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:migrate
```

### Neue Migration erstellen
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:generate
```

### Schema aus Entities generieren
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:schema:update --force
```

### Migration-Status prüfen
```bash
docker exec ematchef_v401-backend-1 php bin/console doctrine:migrations:status
```



Inateck Barcodescanner Pro 8 black (Pro 8 black)