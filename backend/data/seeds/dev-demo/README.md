# Dev-Demo Seeds

Rollen-User und Banner-Logins kommen aus `app:create-role-users` / `app:dev-demo:reset`
(`*@ematchef.ch` / Passwort `test!ematchef`), inkl. `supplier@ematchef.ch` (Testfirma, Supplier-Bereich)
und Grossanlass-Rollen in der Abteilung **Demo Grossanlass**:
`ga-mw@`, `ga-cmw@`, `ga-ok@`, `ga-komm@`, `ga-spon@`, `ga-bereich@` (Leader), `ga-helfer@`.

Org-Subset-Export für Material/Aktivitäten kann hier abgelegt werden, z. B.:

```text
backend/data/seeds/dev-demo/subset.json
```

Dann `app:dev-demo:reset` um den Import erweitern (noch geplant).
