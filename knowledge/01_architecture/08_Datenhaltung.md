# 08 Datenhaltung

## Datenbank

- `IMPLEMENTIERT` PostgreSQL 16 (`docker-compose.yml`: Image `postgres:16-alpine`, DB `mvdb`, User `mvuser`; `backend/config/packages/doctrine.yaml`: `server_version: '16'`). Zeitzone UTC (`TZ`/`PGTZ` im Compose, `date.timezone = UTC` im Backend-Image). Kein Host-Port; Zugriff über Adminer (Port 8082) oder Override `docker-compose.override.local-ports.example.yml`.
- `IMPLEMENTIERT` Verbindung über `DATABASE_URL` (Env). Produktive Zugangsdaten liegen ausserhalb des Repos.
- `IMPLEMENTIERT` Persistenz-Volume `postgres_data`; Datei-Uploads separat auf dem Backend-Dateisystem (siehe unten).

## Doctrine ORM

- `IMPLEMENTIERT` Doctrine ORM 2 mit PHP-Attribute-Mapping, `auto_mapping` auf `backend/src/Entity/` (`App\Entity`), Naming-Strategie `underscore_number_aware`; Proxy-Klassen in `prod` vorab erzeugt (`doctrine.yaml`).
- `IMPLEMENTIERT` Spalten- und Tabellennamen teils explizit (`#[ORM\Table(name: …)]`, `#[ORM\Column(name: …)]`); Tabelle `"group"` ist gequotet (`Entity/Group.php`).
- `IMPLEMENTIERT` Repositories: 24 Klassen in `backend/src/Repository/`; die meisten Zugriffe laufen über `EntityManager::getRepository(...)` mit `findOneBy`/`QueryBuilder` im Controller oder Service. Direktes SQL (`executeQuery`/`executeStatement`/`createNativeQuery`) kommt in rund 30 Dateien vor; `ENTSCHIEDEN` (`AGENTS.md`): kein neues direktes SQL, wenn Doctrine/Repository genügt.
- `IMPLEMENTIERT` Primärschlüssel sind überwiegend anwendungsseitig erzeugte 12-Zeichen-Strings (`Util/IdGenerator.php`, `EventListener/IdGeneratorSubscriber.php`, `GeneratedValue(strategy: 'NONE')`), keine UUID-/Auto-Increment-Standardstrategie. Fremdschlüssel werden oft doppelt abgebildet: Skalarspalte (`department_id`) **und** Relation (`ManyToOne`) in derselben Entity (`Entity/Activity.php`, `Entity/Group.php`, `Entity/Membership.php`); Abfragen filtern typischerweise auf die Skalarspalte.
- `IMPLEMENTIERT` Löschverhalten: z. B. `Activity.department` mit `onDelete: 'CASCADE'`, `Department.memberships` mit `cascade: persist/remove` und `orphanRemoval`. Weitere Fälle nicht einzeln geprüft.
- `IMPLEMENTIERT` Typische Spalten: `created_at`/`updated_at` (`datetime`), JSON-Spalten für flexible Konfigurationen (`Organisation.allowed_languages`, `Profile.admin_capabilities`, `Activity.invited_departments`).

## Datenzuordnung (Mandant)

Spalte `department_id` an fachlichen Tabellen, `organisation_id` am Department; keine Mandantenfilter auf Doctrine-Ebene. Details und Prüfmechanismen: [04](./04_Mandantenstruktur.md). Adressen haben Scope `department` / `supplier` / `global` (`Entity/Address.php`).

## Migrationen

- `IMPLEMENTIERT` Doctrine Migrations: `backend/migrations/` (Namespace `DoctrineMigrations`, `doctrine_migrations.yaml`), 290 Dateien `VersionYYYYMMDDHHMMSS.php` (erste `20260203…`, jüngste im Arbeitsstand `20261106…`), Hilfstrait `migrations/CreatesTableUnlessExistsTrait.php` (macht Tabellenanlage nach fehlgeschlagenem Lauf wiederholbar).
- `IMPLEMENTIERT` Migrationen laufen automatisch beim Backend-Start (`backend/docker-entrypoint.sh`: `doctrine:migrations:migrate --no-interaction`) und in der CI gegen eine Test-DB (`.github/workflows/ci.yml`).
- `IMPLEMENTIERT` Alle 290 Migrationen enthalten eine `down()`-Methode (Suche nach `function down`); ob diese jeweils verlustfrei umkehrbar sind, wurde nicht geprüft (`UNKLAR`). Kein Schema-Dump/Baseline-File.
- `ENTSCHIEDEN` (`AGENTS.md`): Schemaänderung = Entity anpassen → neue Migration → Produktivdaten bedenken → Tests; bestehende, möglicherweise angewandte Migrationen nie ändern; keine destruktive Datenänderung ohne Auftrag.
- `UNKLAR` Abgleich Entity-Mapping ↔ Schema (`doctrine:schema:validate`) wurde nicht ausgeführt.
- `UNKLAR` Der in `Service/PrimaryDepartmentService.php` genannte Unique-Index `uniq_membership_one_primary_per_user` ist in `backend/migrations/` nicht auffindbar.

## Dateien und weitere Speicher

| Speicher | Ort | Beleg |
| --- | --- | --- |
| Uploads (Fotos, Dokumente, Belege) | `backend/var/uploads/{departmentId}/{photos\|documents}/…` | `Service/Media/MediaStorageService.php`; Zugriff über `/media` und `MediaFileAccessService` |
| Aufbewahrung/Kompression | Commands `MediaRetentionCommand`, `MediaCompressLegacyCommand` | `backend/src/Command/` |
| JWT-Schlüssel | `backend/config/jwt/*.pem` (gitignoriert, beim Start erzeugt) | `docker-entrypoint.sh` |
| Einstellungen/Integrationen | DB-gestützte Stores (`IntegrationSettingsStore`, `MailOutboundSettingsStore`, `MediaSettingsStore`) | `Service/Integration/`, `Service/Mail/`, `Service/Media/` |
| Cache (OAuth-State-Replay u. a.) | Symfony-Cache (Standardkonfiguration; `framework.yaml` setzt keine eigene Cache-Konfiguration) | `Service/Auth/OAuthStateReplayGuard.php` (`UNKLAR`, Adapter nicht geprüft) |
| Demo-Zustand | `DemoSeedRecord`, Seeds unter `backend/data/seeds/` | `Entity/DemoSeedRecord.php` |

`UNKLAR` Backup-, Retention- und Lösch-Konzept für Produktivdaten ist im Code nicht beschrieben (`deploy/`/`docs/` nicht untersucht). Audit-Daten: Löschung nur über `DepartmentResetService`; IP/User-Agent der Sicherheitsereignisse werden per `app:security-activity:purge-context` nach 90 Tagen geleert (Beleg: `Command/PurgeSecurityActivityContextCommand.php`, `docs/authentication-security.md`).

## Tests mit Datenbank

- `IMPLEMENTIERT` PHPUnit (`backend/phpunit.xml.dist`, `backend/tests/`, 164 Testdateien) inkl. Integrationstests (`tests/Integration`); CI migriert eine PostgreSQL-Test-DB vor dem Lauf.
- `IMPLEMENTIERT` Seeds/Demo: `Command/Demo*`, `Service/Demo/`, `Service/Bootstrap/`. `ENTSCHIEDEN`: keine DB-Resets oder Seed-Änderungen ohne Auftrag.
