# Mediathek (Zukunft) — Department-Gallery

**Status:** Idee / Phase 2 — **nicht** Teil von Paket 0–6.  
**Ist-Zustand (Hybrid Phase 1):** Material-Detail bietet **Bild hinzufügen** mit Upload, Kamera und URL-Import (lokal gespeichert). Siehe `MaterialImagePicker.vue`.

---

## 1. Ausgangslage

Heute speichert eMatChef Fotos **kontextgebunden**:

| Kontext | Pfad | Lebensdauer |
|---------|------|-------------|
| Material | `var/uploads/material/{dept}/{materialId}/` | solange Material existiert |
| Werkstatt | `var/uploads/workshop/{dept}/{ticketId}/` | Retention nach `completed_at` |
| Schaden | `var/uploads/issues/{dept}/{issueId}/` | an Ticket/Issue gekoppelt |

**Problem bei vielen identischen Artikeln:** Dasselbe Zeltbild wird pro Material-Item erneut hochgeladen → Speicherduplikate, keine zentrale Pflege.

**Lösung (optional):** Department-weite **Mediathek** — Bilder einmal hochladen, mehrfach zuweisen.

---

## 2. Zielbild (WordPress-ähnlich)

### Settings → Gallery

- Navigationspunkt unter Department-Einstellungen: **Mediathek** / **Gallery**
- Grid- und Listenansicht, Suche, Filter (Typ, Datum, Uploader)
- Upload mehrerer Dateien, optional Tags (`product`, `manual`, `damage`, …)
- Metadaten: Dateiname, Grösse, Abmessungen, MIME, `uploaded_at`, `uploaded_by`

### Zuweisung an Material

Im Material-Detail (oder `MaterialImagePicker`) vierter Weg:

1. Hochladen  
2. Kamera  
3. Von URL  
4. **Aus Mediathek wählen** → Modal mit Grid, Vorschau, «Auswählen»

Material referenziert dann ein **Asset**, statt eine eigene Kopie zu besitzen — oder es wird beim Zuweisen **einmal kopiert** (einfacherer Start).

---

## 3. Zwei Architektur-Varianten

### Variante A — Referenz (dedupliziert)

```
department_media_asset (id, department_id, filename, url, meta…)
material_item.photos[] → { asset_id, url, … }  // kein eigener Ordner pro Material
```

- **Pro:** wenig Speicher, zentrale Updates möglich  
- **Contra:** Löschen in Mediathek betrifft alle verknüpften Materialien; komplexere Berechtigungen

### Variante B — Copy-on-assign (einfacher Einstieg)

Beim «Aus Mediathek wählen» wird die Datei wie heute nach `material/{dept}/{materialId}/` **kopiert**.

- **Pro:** Material bleibt unabhängig; passt zum bestehenden `MediaStorageService`  
- **Contra:** weiterhin Duplikate auf Platte, Mediathek ist eher «Vorlagen-Sammlung»

**Empfehlung für Paket 7:** mit **Variante B** starten, später auf Referenz umstellen wenn Bedarf da ist.

---

## 4. Backend (Entwurf)

### Neue Entität

`DepartmentMediaAsset`:

- `id`, `department_id`, `filename`, `original_filename`
- `bytes`, `width`, `height`, `mime`
- `tags` JSON (optional)
- `uploaded_by_id`, `uploaded_at`
- `deleted_at` (Soft-Delete)

### Speicherort

```
var/uploads/library/{departmentId}/{assetId}/{filename}
```

Neuer Kontext in `MediaStorageService`: `CONTEXT_DEPARTMENT_LIBRARY`.

### API (Vorschlag)

| Methode | Route | Beschreibung |
|---------|-------|--------------|
| GET | `/api/departments/{deptId}/media` | Liste (paginiert, Suche, Filter) |
| POST | `/api/departments/{deptId}/media` | Upload |
| GET | `/api/departments/{deptId}/media/{assetId}` | Metadaten |
| GET | `/api/departments/{deptId}/media/{assetId}/file` | Bild ausliefern |
| DELETE | `/api/departments/{deptId}/media/{assetId}` | Soft-Delete |
| POST | `/api/materials/{materialId}/photos/from-asset` | Asset → Material (Copy-on-assign) |

### Berechtigungen

- Lesen: Department-Mitglied  
- Schreiben/Löschen: `canManageMaterials` oder eigenes `canManageMedia`  
- Kein Cross-Department-Zugriff

---

## 5. Frontend (Entwurf)

| Baustein | Pfad | Aufgabe |
|----------|------|---------|
| **MediaLibraryView** | `views/settings/MediaLibraryView.vue` | Settings-Seite |
| **MediaLibraryPickerModal** | `components/media/MediaLibraryPickerModal.vue` | Auswahl-Modal |
| **MaterialImagePicker** | erweitern | 4. Option «Mediathek» |
| API | `frontend/src/api/departmentMedia.ts` | CRUD + assign |

Sidebar-Route z. B. unter Department-Settings neben bestehenden Einträgen.

---

## 6. Abgrenzung zu anderen Features

| Feature | Mediathek? |
|---------|------------|
| Werkstatt-Fotos (Schaden, Reparatur) | **Nein** — ephemeral, Retention |
| Lieferanten-Katalog-Bilder | Später evtl. **eigener** Kontext oder Import in Mediathek |
| Material-Templates | Mediathek als **Quelle** für Template-Vorschaubilder denkbar |
| Blog / Landing | Eher Site-Assets, nicht Department-Mediathek |

---

## 7. Paket-Schnitt (wenn umgesetzt)

| Schritt | Aufwand | Abhängigkeit |
|---------|---------|--------------|
| Entität + Storage + Upload-API | M | Paket 0 |
| Settings-Grid + Suche | M | — |
| Picker-Modal + MaterialImagePicker | S | Hybrid Phase 1 ✅ |
| Copy-on-assign Endpoint | S | Material photos |
| Referenz-Modell (optional) | L | Nutzungsanalyse |

---

## 8. Offene Entscheidungen

1. **Copy vs. Referenz** beim Zuweisen (siehe §3)  
2. **Tags** — feste Liste vs. freie Tags  
3. **Quota** pro Department (MB/Anzahl)  
4. **Retention** — Mediathek-Bilder unbegrenzt vs. Aufräumen unbenutzter Assets  
5. **Import** bestehender Material-Fotos retroaktiv in Mediathek?

---

## Siehe auch

- [README.md](./README.md) — Ist-Modell kontextgebundener Fotos  
- [plan.md](./plan.md) — Pakete 0–6 (abgeschlossen)  
- [wiederverwendbare-komponenten.md](../wiederverwendbare-komponenten.md) — `MaterialImagePicker`, `PhotoUpload`, `PhotoGallery`
