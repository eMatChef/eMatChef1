# Authentication & Account Security

## 1. Zweck

Dieses Dokument definiert die fachlichen und sicherheitsrelevanten Regeln für Authentifizierung, Login-Identitäten, E-Mail-Adressen, Passkeys, TOTP, Recovery, Sessions und den Wechsel zwischen Department- und Grossanlass-Kontexten in eMatChef.

Die Authentifizierung einer Person und deren Berechtigungen innerhalb eines Departments oder Grossanlasses sind getrennte Konzepte.

Grundsatz:

> Eine Person besitzt genau einen eMatChef-Account. Dieser Account kann mehrere Login-Identitäten, E-Mail-Adressen, Passkeys und Memberships besitzen.

---

## 2. Zentrales Account-Modell

Ein eMatChef-User kann besitzen:

- mehrere verifizierte E-Mail-Adressen;
- mehrere externe Login-Identitäten;
- mehrere Passkeys;
- ein TOTP-Setup;
- Recovery Codes;
- mehrere Department-Memberships;
- mehrere Grossanlass-Memberships bzw. -Kontexte;
- mehrere Sessions bzw. Geräte.

E-Mail-Adressen, OAuth-Identitäten und Passkeys erzeugen keine separaten eMatChef-User.

Departments und Grossanlässe definieren den Arbeits- und Berechtigungskontext des Users.

---

## 3. E-Mail-Adressen

Ein User kann mehrere direkt in eMatChef registrierte E-Mail-Adressen besitzen.

Jede E-Mail-Adresse besitzt mindestens:

- Adresse;
- Verifizierungsstatus;
- Kennzeichnung als Primary oder Secondary;
- Login-Berechtigung.

### Primary-Adresse

Jeder User besitzt genau eine `primary` E-Mail-Adresse.

Sie ist standardmäßig zuständig für:

- eMatChef-Systemkommunikation;
- Sicherheitsbenachrichtigungen;
- Passwort-Recovery;
- allgemeine Benachrichtigungen.

Nur eine bereits verifizierte E-Mail-Adresse darf zur Primary-Adresse gemacht werden.

Die aktuelle Primary-Adresse darf nicht gelöscht werden. Zuerst muss eine andere verifizierte Adresse Primary werden.

### Login

Alle direkt in eMatChef registrierten und verifizierten Login-E-Mail-Adressen dürfen zur Anmeldung verwendet werden.

Alle Adressen führen zum selben eMatChef-User.

Eine von Google, Microsoft, MiData oder einem anderen Provider gelieferte E-Mail-Adresse wird dadurch nicht automatisch zu einer eMatChef-Login- oder Recovery-Adresse.

### Ausnahme: Neuanlage über einen Provider

Wird über Google, MiData oder einen anderen externen Provider ein vollständig neuer eMatChef-User angelegt, darf die vom Provider als verifiziert gelieferte E-Mail-Adresse als initiale Primary- und Login-Adresse dieses neuen Users verwendet werden.

Diese Ausnahme gilt ausschließlich bei der Neuanlage eines eMatChef-Users. Für bestehende User gilt:

- eine Provider-E-Mail-Adresse wird niemals automatisch Primary;
- sie ersetzt keine bestehende eMatChef-E-Mail-Adresse;
- sie wird nicht automatisch als weitere Login- oder Recovery-Adresse hinzugefügt;
- eine übereinstimmende E-Mail-Adresse führt nie zu einer automatischen Account-Zusammenführung.

Liefert der Provider keine verifizierte E-Mail-Adresse, wird sie nicht als verifizierte Login-Adresse übernommen.

### Eindeutigkeit

Eine Login-E-Mail-Adresse darf nicht gleichzeitig mehreren eMatChef-Accounts gehören.

Accounts dürfen niemals allein aufgrund einer übereinstimmenden E-Mail-Adresse automatisch zusammengeführt werden.

---

## 4. Department-/Grossanlass-spezifische Benachrichtigungsadresse

Standardmäßig werden Benachrichtigungen an die Primary-Adresse gesendet.

Der User kann für ein Department oder einen Grossanlass eine andere seiner verifizierten E-Mail-Adressen als bevorzugte Benachrichtigungsadresse auswählen.

Beispiel:

- Primary: privat@example.ch
- Department Verein X: verein-x@example.ch
- Grossanlass Y: ga@example.ch

Diese Auswahl beeinflusst ausschließlich Benachrichtigungen.

Sie beeinflusst nicht:

- Identität;
- Membership;
- Rollen;
- Login;
- OAuth-Zuordnung.

Wird eine konfigurierte Adresse gelöscht oder verliert ihren gültigen/verifizierten Status, fällt der Kontext automatisch auf die Primary-Adresse zurück.

---

## 5. Externe Login-Identitäten

Ein User kann mehrere externe Login-Identitäten besitzen, beispielsweise:

- Google;
- Microsoft;
- MiData/Hitobito;
- zukünftige Provider.

Eine externe Identität wird über mindestens

`provider + provider subject`

eindeutig einem eMatChef-User zugeordnet.

Die E-Mail-Adresse eines Providers ist nicht die dauerhafte technische Identität.

Eine Provider-Identität darf genau einem eMatChef-User zugeordnet sein.

### Verknüpfen

Eine zusätzliche Provider-Identität soll grundsätzlich kontrolliert mit einem bestehenden eMatChef-Account verbunden werden.

Eine bloße Übereinstimmung der E-Mail-Adresse darf bestehende Accounts nicht automatisch zusammenführen.

### Trennen

Der User kann verbundene Provider wieder trennen.

Das Trennen beispielsweise von MiData:

- löscht nicht den eMatChef-User;
- löscht nicht automatisch Departments;
- löscht nicht automatisch Memberships;
- löscht keine eMatChef-Fachdaten;
- entfernt nur die entsprechende Identity-Verknüpfung.

Die letzte tatsächlich nutzbare Login-Methode darf nicht entfernt werden.

Sicherheitskritische Änderungen eines Admin-Accounts erfordern Step-up Authentication.

---

## 6. Login-Kontext

Der Login-Weg darf beeinflussen, in welchem Department bzw. Grossanlass der User startet.

Der Login-Weg erzeugt jedoch niemals einen separaten User.

Beispiel:

- Login über MiData Verein X → Department X;
- Login über Identität Verein S → Department S oder zugehöriger Grossanlass;
- beide Logins gehören zum selben eMatChef-User.

Die Auflösung des Startkontexts erfolgt grundsätzlich in folgender Reihenfolge:

1. eindeutiger Kontext der verwendeten Provider-Identität;
2. zuletzt verwendeter Kontext dieser Login-Identität;
3. bestehendes `last_used_department` des Users;
4. Primary Department;
5. erster zulässiger Kontext bzw. Kontextauswahl.

Das bereits bestehende eMatChef-System für `last_used_department` bleibt erhalten und wird erweitert, nicht ersetzt.

Grossanlässe werden weiterhin über das bestehende Department-/Grossanlass-Kontextmodell behandelt.

---

## 7. Kontextwechsel

Der User kann zwischen seinen verfügbaren Departments und Grossanlässen wechseln.

eMatChef merkt sich den zuletzt verwendeten Kontext.

Beim nächsten Login soll dieser wieder verwendet werden, sofern der verwendete Login-Kontext keinen spezifischeren Zielkontext vorgibt.

Beim Wechsel in einen sicherheitskritischen Kontext prüft eMatChef, ob eine ausreichende starke Authentifizierung vorliegt.

Beispiel:

User ist:

- in Department A normaler User;
- in Department B `orgchef`.

Der User kann Department A normal verwenden.

Beim Wechsel zu Department B kann eine Step-up Authentication erforderlich sein.

Die Sicherheitsprüfung muss serverseitig durchgesetzt werden. Eine reine Frontend-Prüfung ist nicht ausreichend.

---

## 8. Admin-Rollen

Folgende Rollen gelten für diese Security-Regeln als administrative Rollen:

- `superadmin`;
- `orgchef`;
- `suborgchef`.

Die tatsächlichen Berechtigungen und Reset-Rechte folgen zusätzlich der bestehenden Organisations- und Department-Hierarchie.

---

## 9. TOTP

eMatChef verwendet standardbasiertes TOTP.

Unterstützt werden übliche Authenticator-Apps wie:

- Google Authenticator;
- Microsoft Authenticator;
- 2FAS;
- Bitwarden;
- kompatible RFC-6238-Anwendungen.

Einrichtung erfolgt über:

- QR-Code;
- manuellen Setup-Key.

SMS-2FA wird nicht verwendet.

### Admins

Für `superadmin`, `orgchef` und `suborgchef` ist TOTP verpflichtend.

Solange mindestens eine entsprechende Adminberechtigung besteht, darf TOTP nicht vollständig deaktiviert werden.

Wird ein User zum Admin befördert und besitzt noch kein TOTP, bleiben administrative Funktionen blockiert, bis TOTP eingerichtet wurde.

Wird die letzte Adminrolle entfernt, bleibt bestehendes TOTP aktiviert, wird aber optional und kann anschließend vom User deaktiviert werden.

### Normale User

Normale User können TOTP freiwillig aktivieren und deaktivieren.

---

## 10. Passkeys

Passkeys stehen allen Usern zur Verfügung.

Technische Grundlage ist WebAuthn/FIDO2.

Ein User kann mehrere Passkeys registrieren, beispielsweise:

- Windows Hello;
- iPhone / Face ID / Touch ID;
- Hardware Security Key;
- weitere kompatible Geräte.

Biometrische Daten verlassen das Endgerät nicht und werden nicht von eMatChef gespeichert.

Passkeys gehören zum eMatChef-User, nicht zu:

- einer E-Mail-Adresse;
- einem Department;
- einem Grossanlass;
- einer OAuth-Identität.

### Admins

Passkeys sind für Admins optional.

TOTP muss trotzdem als verpflichtender Fallback eingerichtet sein.

Ein erfolgreicher Passkey-Login gilt als starke Authentifizierung und benötigt für diesen Login keinen zusätzlichen TOTP-Code.

### Verwaltung

Unter Profil → Sicherheit werden registrierte Passkeys angezeigt, einschließlich geeigneter Metadaten wie:

- frei wählbarer Name;
- erstellt am;
- zuletzt verwendet;
- Typ, soweit technisch sinnvoll verfügbar.

Passkeys können hinzugefügt und entfernt werden.

Das Entfernen eines Passkeys ist eine sicherheitskritische Aktion und benötigt Step-up Authentication.

Der Passkey, der als alleiniger Nachweis für die aktuelle Aktion verwendet wird, darf nicht die einzige Grundlage dafür sein, sich selbst als letzten Sicherheitsnachweis zu entfernen.

---

## 11. Recovery Codes

Jeder TOTP-User erhält genau drei Recovery Codes.

Regeln:

- jeder Code ist einmal verwendbar;
- Codes werden sicher bzw. gehasht gespeichert;
- verwendete Codes werden sofort ungültig;
- neue Codes können generiert werden;
- das Generieren neuer Codes invalidiert alle bisherigen Codes;
- Admins werden gewarnt, wenn nur noch ein Recovery Code vorhanden ist.

Ein Recovery Code ersetzt TOTP für genau einen Authentifizierungsvorgang.

Er deaktiviert TOTP nicht.

Nach vollständigem TOTP-Reset werden:

- alter TOTP-Secret ungültig;
- alte Recovery Codes ungültig;
- neuer TOTP-Secret eingerichtet;
- drei neue Recovery Codes erzeugt.

---

## 12. Superadmin Recovery

Der `superadmin` besitzt zusätzlich eine separate verifizierte Recovery-E-Mail-Adresse.

Diese:

- ist nicht automatisch eine Login-Adresse;
- ist von normalen Login-E-Mail-Adressen logisch getrennt;
- dient ausschließlich der Notfallwiederherstellung.

Wenn TOTP und alle Recovery Codes verloren wurden, kann ein kurzlebiger einmal verwendbarer Recovery-Prozess über diese Adresse gestartet werden.

Dieser Prozess deaktiviert 2FA nicht.

Er erlaubt ausschließlich die sichere Neueinrichtung von TOTP und Recovery Codes.

Änderungen der Superadmin-Recovery-Adresse benötigen Step-up Authentication und Verifizierung der neuen Adresse.

---

## 13. Admin Recovery

`orgchef` und `suborgchef` können bei vollständigem Verlust ihrer zweiten Faktoren durch einen entsprechend berechtigten übergeordneten Administrator zurückgesetzt werden.

Die Berechtigung folgt der bestehenden Organisationshierarchie.

Grundsätzlich:

- `superadmin` kann untergeordnete Admins zurücksetzen;
- `orgchef` nur Admins innerhalb seines zulässigen Organisations-/Child-Bereichs;
- entsprechende Hierarchie- und Scope-Regeln müssen wiederverwendet werden.

Ein Admin-Reset deaktiviert 2FA nicht dauerhaft.

Nach dem Reset bleiben administrative Funktionen gesperrt, bis TOTP neu eingerichtet wurde.

---

## 14. Password Recovery

„Passwort vergessen“ darf über jede direkt in eMatChef registrierte und verifizierte Login-E-Mail-Adresse gestartet werden.

Eine nur durch einen OAuth-Provider bekannte E-Mail-Adresse reicht dafür nicht aus.

Die Recovery-Kommunikation erfolgt grundsätzlich über die eMatChef-Primary-Adresse.

Ein Passwort-Reset:

- entfernt TOTP nicht;
- entfernt Passkeys nicht;
- entfernt OAuth-Verknüpfungen nicht;
- umgeht Admin-2FA nicht.

---

## 15. Trusted Devices / MFA Trust

Trusted Device und Login-Session sind unterschiedliche Konzepte.

Für TOTP kann ein Gerät als vertrauenswürdig markiert werden.

Gültigkeit:

- Admin-Kontext: 30 Tage;
- normaler User: 90 Tage.

Ein gültiger Trust verhindert bei normalen Logins eine erneute TOTP-Abfrage.

Er verlängert nicht die normale eMatChef-Session.

Das bestehende Auto-Logout inklusive Warnung vor Session-Ende bleibt unverändert maßgeblich.

Trusted-Device-Zustände müssen serverseitig widerrufbar sein.

Passkey-Login benötigt keinen TOTP-Trust, da der Passkey selbst starke Authentifizierung darstellt.

---

## 16. Step-up Authentication

Bestimmte Aktionen verlangen unabhängig von Trusted Device eine frische starke Authentifizierung.

Step-up kann erfolgen durch:

- Passkey;
- TOTP.

Beispiele:

- Wechsel in einen geschützten Admin-Kontext, falls keine ausreichend aktuelle starke Authentifizierung vorliegt;
- Adminrolle vergeben oder entfernen;
- TOTP zurücksetzen;
- Passkey entfernen;
- Login-E-Mail-Adresse sicherheitskritisch ändern;
- Primary-Adresse ändern;
- Superadmin-Recovery-Adresse ändern;
- OAuth-Identität eines Admins verbinden oder trennen;
- andere kritische Account-Security-Aktionen.

Die konkrete Gültigkeitsdauer eines Step-up-Nachweises kann technisch zentral definiert werden und darf nicht mit der 30-/90-Tage-Trusted-Device-Frist verwechselt werden.

---

## 17. Sessions und Geräte

Das bestehende eMatChef-Session-Timeout und die bestehende Auto-Logout-Warnung bleiben bestehen.

Zusätzlich soll unter Profil → Sicherheit eine Übersicht „Geräte & Sessions“ verfügbar sein.

Darstellbar sind beispielsweise:

- Browser;
- Betriebssystem;
- Session erstellt;
- letzte Aktivität;
- gegebenenfalls grobe IP-/Standortinformation.

Es wird kein invasiver Hardware-Fingerprint aufgebaut.

Der User kann:

- einzelne Sessions beenden;
- alle anderen Sessions beenden.

Sicherheitsereignisse wie Account-Recovery oder vollständiger 2FA-Reset können bestehende Sessions und Trusted-Device-Tokens invalidieren.

---

## 18. Sicherheitsaktivitäten

Der User erhält unter Profil → Sicherheit eine nachvollziehbare Übersicht sicherheitsrelevanter Account-Aktivitäten.

Dazu gehören insbesondere:

- neue Anmeldung;
- Passwort geändert;
- Passwort zurückgesetzt;
- E-Mail-Adresse hinzugefügt/entfernt;
- Primary-Adresse geändert;
- TOTP aktiviert;
- TOTP zurückgesetzt;
- Recovery Code verwendet;
- Recovery Codes neu generiert;
- Passkey hinzugefügt;
- Passkey entfernt;
- OAuth-Provider verbunden;
- OAuth-Provider getrennt;
- Sessions beendet;
- Trusted Device widerrufen;
- administrativer 2FA-Reset.

Bei administrativen Aktionen muss nachvollziehbar sein, welcher berechtigte Administrator die Aktion ausgelöst hat.

Das Security-Audit-Log darf keine Secrets, TOTP-Secrets, Recovery Codes oder OAuth-Tokens enthalten.

---

## 19. Sicherheitsbenachrichtigungen

Bei wesentlichen Security-Änderungen sendet eMatChef zusätzlich eine Sicherheitsbenachrichtigung an die Primary-Adresse.

Dazu gehören insbesondere:

- Passwort geändert/zurückgesetzt;
- neue Login-E-Mail-Adresse;
- Login-E-Mail-Adresse entfernt;
- Passkey hinzugefügt/entfernt;
- TOTP eingerichtet/zurückgesetzt;
- OAuth-Identität verbunden/getrennt;
- Admin-Recovery;
- relevante Account-Recovery-Ereignisse.

Bei Änderung der Primary-Adresse sollen sowohl die bisherige als auch die neue Adresse informiert werden.

---

## 20. Grundsätzliche Sicherheitsgrenzen

Frontend-Sperren sind ausschließlich UX.

Alle sicherheitsrelevanten Regeln müssen vom Symfony-Backend durchgesetzt werden.

Insbesondere dürfen nicht ausschließlich frontendseitig abgesichert werden:

- Adminzugriff;
- Kontextwechsel;
- Step-up;
- TOTP;
- Passkey-Verwaltung;
- Identity Linking;
- Recovery;
- E-Mail-Änderungen;
- Session-Widerruf.

Das Vue-Frontend verwendet für sämtliche API-Kommunikation ausschließlich den bestehenden Axios-`apiClient`.

`fetch()` wird nicht verwendet.

---

## 21. Implementierungsprinzip

Diese Spec beschreibt den gewünschten fachlichen Zielzustand.

Vor der Implementierung muss der bestehende Code analysiert werden.

Vorhandene Mechanismen werden erweitert und nicht parallel neu implementiert, insbesondere:

- User/Profile;
- Department Memberships;
- `last_used_department`;
- Department-/Grossanlass-Kontext;
- bestehende Rollen und Organisationshierarchie;
- bestehende Symfony-Security;
- bestehende Sessions und Auto-Logout;
- vorhandene OAuth-/MiData-Mechanismen;
- bestehender Axios-`apiClient`.

IST-Zustand und Zielzustand müssen bei der Umsetzung klar getrennt bleiben.
---

## 22. Offene Security-Befunde (IST, noch nicht behoben)

Diese Punkte beschreiben den aktuellen Code-Stand, nicht das Zielbild. Sie sind bei der weiteren Umsetzung zu beheben.

### Scope-Semantik leerer Scopes — behoben (Oktober 2026)

Früher wertete `AdminCapabilityChecker` einen leeren Scope als «alle Organisationen/Departments» und schnitt Organisations- und Department-Zuweisungen (Schnittmenge). Jetzt gilt: Zuweisungen werden vereinigt, ein leerer Scope gibt **keine** hierarchischen Verwaltungsrechte, Organisationsebene verlangt eine Organisations-Zuweisung (`canAdministerOrganisation`), und Department-bezogene Schreib- und Lesezugriffe laufen über `canAdministerDepartment` bzw. `DepartmentAccessGuard`. Auswirkungen auf bestehende Konten zeigt `app:admin:scope-report`. Offen bleibt die Kandidatensuche `GET /api/departments/grossanlass/available-users` (Benutzersuche nicht auf den Bereich eingeschränkt).

### E-Mail-Wechsel bei unbestätigten Accounts im Profil

`GET /api/auth/verify` behandelt jeden Token als E-Mail-Wechsel, sobald `pendingEmail` gesetzt ist, und setzt dabei `emailVerified` nicht. Ein unbestätigter User (z. B. MiData-Neuanlage ohne vom Provider verifizierte E-Mail, die per OAuth trotzdem angemeldet ist) kann über `PATCH /api/profiles/{id}` einen E-Mail-Wechsel anstoßen. Dabei wird der offene Registrierungs-Token überschrieben und der Account bleibt nach Bestätigung unbestätigt.

Der Admin-Endpoint `PATCH /api/users/{id}/admin` lehnt E-Mail-Änderungen für unbestätigte User bereits ab (409). Der Profil-Endpoint ist noch offen.
