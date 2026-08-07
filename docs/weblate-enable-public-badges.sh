#!/usr/bin/env bash
# Live README-Badges: Projekt öffentlich lesbar + Login-Zwang für Widgets/API lockern.
#
# Auf dem Weblate-Droplet:
#   curl -fsSL … | bash
#   oder: bash docs/weblate-enable-public-badges.sh
#
# Danach (einmalig) in docker-compose / .env prüfen:
#   WEBLATE_REQUIRE_LOGIN=0
# und `docker compose up -d` (sonst bleiben Widgets hinter Login).
#
# Übersetzen bleibt account-pflichtig; anonym nur Lesen/Badges.
set -euo pipefail
cd /opt/weblate

echo "== Projekt access_control → Public =="
docker compose exec -T weblate weblate shell <<'PY'
from weblate.trans.models import Project

# 0 = Public, 1 = Protected, 100 = Private
p = Project.objects.get(slug="ematchef")
before = p.access_control
p.access_control = 0
p.enable_hooks = True
p.save()
print(f"project={p.slug} access_control: {before} → {p.access_control} (0=Public)")
print("languages:", ", ".join(sorted(t.language.code for c in p.component_set.all() for t in c.translation_set.all())))
PY

echo
echo "== Hinweis Docker-Env =="
if grep -Rqs 'WEBLATE_REQUIRE_LOGIN' .env docker-compose*.yml 2>/dev/null; then
  grep -Rsn 'WEBLATE_REQUIRE_LOGIN' .env docker-compose*.yml 2>/dev/null || true
  echo "→ Auf WEBLATE_REQUIRE_LOGIN=0 setzen und neu starten, falls noch 1/true."
else
  echo "WEBLATE_REQUIRE_LOGIN nicht gefunden — in .env ergänzen:"
  echo "  WEBLATE_REQUIRE_LOGIN=0"
fi

echo
echo "== Smoke-Tests (nach Restart) =="
echo "  curl -sI https://translate.ematchef.ch/widget/ematchef/-/en/svg-badge.svg | head -1"
echo "  curl -s https://translate.ematchef.ch/api/projects/ematchef/ | head -c 200"
echo "Erwartung: HTTP 200 + JSON/SVG ohne Login-Redirect."
