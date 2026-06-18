# J+S-Bestellformular PDF (offizielle Vorlage)

**Datei:** `bestellformular_lagersport_trekking_d.pdf`  
**Quelle:** J+S / BASPO «Leihmaterialbestellung Lagersport/Trekking», Formularstand **16.06.2021**

Diese PDF wird **nicht** neu gesetzt. Beim Export befüllt `ActivityJsOrderPdfService` die AcroForm-Felder per **pdftk** (`fill_form` + `flatten`).

Feld-Mapping: `src/Service/Activity/JsOrderPdfFieldMapper.php`

Voraussetzung im Backend-Container: `pdftk-java` (siehe `Dockerfile`).
