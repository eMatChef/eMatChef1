# Preview-Deploy (zurückgestellt)

Pro-PR-Deployments (`pr-N.ematchef…` o. Ä.) sind **sinnvoll**, brauchen aber:

- eigenen Host oder Subdomain-DNS
- Deploy-Pipeline mit Label (z. B. `deploy!`) und Cleanup

Aktuell: Smoke-E2E läuft gegen **Develop** (`app-dev.ematchef.ch`) — siehe [E2E.md](E2E.md).
