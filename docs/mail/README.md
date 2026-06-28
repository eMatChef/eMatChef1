# E-Mail-Versand (MAILER_DSN)

Transaktionsmail in eMatChef (Registrierung, Passwort-Reset, Einladungen, J+S-Coach, …) läuft über **Symfony Mailer** und **`MAILER_DSN`** in der Server-Umgebung.

Auf dem API-Server (DigitalOcean) ist **kein SMTP** zuverlässig (Ports 587/465 oft blockiert). Deshalb: Versand per **HTTPS-API** — im Projekt über **`ses+api://…`** (Symfony `symfony/amazon-mailer`).

Absender-Metadaten (From-Name, Reply-To) werden in der App unter **Superadmin → E-Mail → Einstellungen** gepflegt (`mail_outbound.json`). Der **Transport** kommt ausschliesslich aus `MAILER_DSN`.

---

## Architektur (Kurz)

```text
Symfony AppMailer  →  MAILER_DSN (HTTPS)  →  Mail-Provider (SES)
Cloudflare DNS     →  DKIM + SPF für ematchef.ch
DigitalOcean API   →  MAILER_DSN + MAILER_FROM in .env / Compose-Override
```

**Versandprotokoll:** Superadmin → E-Mail → **Log** (`backend/var/app/mail_send_log.json`, max. 500 Einträge). Erfolg und Fehler (z. B. `auth.verify_email` / `auth.verify_email.failed`).

---

## Checkliste (Prod live)

| Schritt | Erledigt? |
|---------|-----------|
| SES-Region **eu-central-1** (Frankfurt) | ☐ |
| Domain **ematchef.ch** in SES **Verified** | ☐ |
| DKIM-CNAME in **Cloudflare** (DNS only, grau) | ☐ |
| SPF-TXT angepasst (`include:amazonses.com`) | ☐ |
| SES **Production Access** (nicht Sandbox) | ☐ |
| IAM-User + Access Key (nur `ses:SendEmail`) | ☐ |
| `MAILER_DSN` + `MAILER_FROM` auf Prod-Server | ☐ |
| Deploy mit `symfony/amazon-mailer` | ☐ |
| `mailer:test` + App-Testmail OK | ☐ |

---

## 1. AWS SES einrichten

### 1.1 Richtige Console (nicht Lightsail)

1. [AWS Console](https://console.aws.amazon.com) → Region oben: **Europe (Frankfurt) `eu-central-1`**
2. Suche: **SES** → **Amazon Simple Email Service**
3. Direktlink Identities: [SES Identities eu-central-1](https://eu-central-1.console.aws.amazon.com/ses/home?region=eu-central-1#/verified-identities)

Alle Schritte in **derselben Region** — `MAILER_DSN` muss `region=eu-central-1` enthalten.

### 1.2 Domain verifizieren

1. **Configuration** → **Identities** → **Create identity**
2. **Identity type:** Domain  
3. **Domain:** `ematchef.ch`  
4. **Easy DKIM:** Enabled, RSA 2048  
5. **Custom MAIL FROM:** aus (erstmal)  
6. **Route 53:** Nein — DNS liegt bei **Cloudflare**

SES zeigt **3 CNAME-Einträge** für DKIM.

### 1.3 DNS in Cloudflare

[Cloudflare Dashboard](https://dash.cloudflare.com) → `ematchef.ch` → **DNS**

**DKIM (3× CNAME von SES):**

| Typ | Name | Ziel | Proxy |
|-----|------|------|-------|
| CNAME | `xxxx._domainkey` (von SES) | Wert von SES | **DNS only** (graue Wolke) |

**SPF (bestehenden TXT anpassen):**

Aktuell oft:

```text
v=spf1 redirect=spf.mail.hostpoint.ch
```

Für SES **und** Hostpoint-Postfächer:

```text
v=spf1 include:amazonses.com include:spf.mail.hostpoint.ch ~all
```

`redirect=` durch `include:` ersetzen — mit `redirect` darf SES nicht mit drin sein.

**Optional DMARC** (`_dmarc`):

```text
v=DMARC1; p=none; rua=mailto:admin@ematchef.ch
```

Warten (15–60 Min.), in SES: Identity **Verified**, DKIM **Successful**.

### 1.4 Production Access (Sandbox verlassen)

Neue SES-Konten starten in der **Sandbox** → Mail geht nur an **verifizierte** Empfänger.

1. SES → **Account dashboard** → **Request production access**
2. Angaben (Beispiel):
   - **Mail type:** Transactional  
   - **Website:** `https://ematchef.ch`  
   - **Use case:** Registrierung, E-Mail-Verifikation, Passwort-Reset, Department-Einladungen — keine Newsletter  
   - **Volume:** z. B. unter 500/Monat  

Bearbeitung oft 24–48 h.

**Sandbox-Test vorher:** Identity → Email address → deine Test-Adresse verifizieren → nur dorthin senden.

### 1.5 IAM Access Key

1. **IAM** → **Users** → **Create user** (z. B. `ematchef-ses`)
2. Policy (minimal):

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["ses:SendEmail", "ses:SendRawEmail"],
    "Resource": "*"
  }]
}
```

3. User → **Security credentials** → **Create access key**  
4. Use case: **Application running outside AWS**  
5. **Access Key ID** + **Secret Access Key** sicher speichern (Secret nur einmal sichtbar)

Root-Account-Keys **nicht** für Symfony verwenden.

---

## 2. Server-Konfiguration (DigitalOcean Prod)

Verzeichnis: `/opt/ematchef/prod`

In **`.env`** und/oder **`docker-compose.override.yml`** (siehe `deploy/docker-compose.override.prod.example.yml`):

```env
MAILER_FROM="eMatChef <noreply@ematchef.ch>"
MAILER_DSN="ses+api://ACCESS_KEY_ID:SECRET_ACCESS_KEY@default?region=eu-central-1"
```

**Sonderzeichen im Secret URL-encoden** (`+` → `%2B`, `/` → `%2F`, `@` → `%40`).

Optional:

```env
MAILER_REPLY_TO="support@ematchef.ch"
MAILER_BRAND_LOGO_URL="https://ematchef.ch/favicon.svg"
```

### Deploy & Neustart

Nach Code-Deploy (Branch `prod` / `deploy/prod-update.sh`):

```bash
cd /opt/ematchef/prod
export HOST_UID=$(id -u) HOST_GID=$(id -g)
docker compose -p ematchef-prod up -d backend
docker compose -p ematchef-prod exec backend php bin/console cache:clear --env=prod
```

**Pitfall:** Steht in der Container-Umgebung noch `MAILER_DSN=null://null`, überschreibt das `backend/.env.local`. Container **neu erstellen** nach `.env`-Änderung.

Prüfen:

```bash
docker compose -p ematchef-prod exec backend env | rg "^MAILER_DSN=|^MAILER_FROM="
docker compose -p ematchef-prod exec backend php bin/console mailer:test deine@echte-mail.de --env=prod
```

In der App: **Superadmin → E-Mail → Einstellungen → Testmail**.

API-Check: `GET /api/mail/settings` → `mailer_transport_mode` soll **`env`** sein (nicht `env_missing`).

---

## 3. Lokal (Entwicklung)

```bash
cp backend/.env.local.example backend/.env.local
# ACCESS_KEY / SECRET eintragen (gleicher SES-Key wie Prod oder separater Dev-Key)
docker compose restart backend
```

In der Sandbox nur an verifizierte Adressen senden, solange kein Production Access.

---

## 4. Versandprotokoll

| Ort | Inhalt |
|-----|--------|
| App | Superadmin → E-Mail → **Log** |
| Server | `backend/var/app/mail_send_log.json` |

Beispiel-Arten:

| Kind | Bedeutung |
|------|-----------|
| `auth.verify_email` | Registrierung |
| `auth.password_reset_code` | Passwort-Reset |
| `department.invite` | Department-Einladung |
| `mail.test` | Testmail aus Einstellungen |
| `*.failed` | Versand fehlgeschlagen (Fehlertext im Betreff) |

---

## 5. Fehlerbehebung

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| `Email address is not verified` (Empfänger) | SES Sandbox | Production Access beantragen |
| `MessageRejected` / Absender | From passt nicht zur Domain | `MAILER_FROM` = `@ematchef.ch`, Domain in SES verified |
| `Could not authenticate` | Falscher Key/Secret oder Region | IAM-Key prüfen, `region=eu-central-1` |
| `Maximum credits exceeded` (SendGrid) | Alter Provider | SendGrid durch SES ersetzen, `MAILER_DSN` tauschen |
| Test OK, App 500 | Cache / alter DSN | `cache:clear --env=prod`, Container neu starten |
| DKIM pending | DNS falsch / orange Cloudflare | CNAME **DNS only**, Namen exakt wie SES |
| Mail im Spam | SPF/DKIM fehlen | SPF + DKIM prüfen (MXToolbox) |

SES-Versand prüfen: AWS Console → SES → **Account dashboard** / Sending statistics.

Ausgehendes **HTTPS (443)** vom Droplet muss erreichbar sein (kein SMTP nötig).

---

## 6. Kosten (Orientierung)

SES: grob **0,10 USD / 1000 Mails**. Bei typischem eMatChef-Volumen (Registrierung, Reset, Einladungen) meist **unter 1 CHF/Monat**.

AWS Billing → Budget-Alarm (z. B. 5 USD) empfohlen.

---

## 7. Referenzen im Repo

| Datei | Inhalt |
|-------|--------|
| `backend/.env.local.example` | DSN-Format lokal |
| `deploy/docker-compose.override.prod.example.yml` | Prod-Env-Variablen |
| `deploy/docker-compose.prod.env.example` | Beispiel `.env` |
| `deploy/SERVER-UPDATE.md` | Abschnitt **3a. E-Mail** |
| `backend/src/Service/Mail/AppMailer.php` | Versand + Log |
| `frontend/src/views/mail/MailSendLogView.vue` | Log-UI |

---

## 8. Abschluss SendGrid

Wenn SES auf Prod stabil läuft:

1. SendGrid-Abo kündigen / Key deaktivieren  
2. Alte `sendgrid+api://…` DSNs aus Server-Env entfernen  
3. Test: Registrierung + Passwort-Reset auf `app.ematchef.ch`  
